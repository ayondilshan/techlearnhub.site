<?php
// =====================================================================
// api/generate_mindmap.php  (v2 - compatible with older PHP 7.x)
// =====================================================================
ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@set_time_limit(180);
@ini_set('memory_limit', '256M');

// Set to false to skip reading uploaded notes / past papers completely.
define('MM_USE_SOURCES', true);

// While troubleshooting, show the real PHP error text. Set to false when everything works.
define('MM_DEBUG', true);

function mm_out($data)
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    $json = json_encode($data, $flags);
    if ($json === false) $json = json_encode(array('error' => 'Could not encode the response.'));
    echo $json;
    exit;
}

// Registered before the requires so fatals inside included files are caught too.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        error_log('mindmap fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        $msg = 'Server error while generating the mind-map. Please try again.';
        if (MM_DEBUG) {
            $msg .= ' [DEBUG: ' . $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line'] . ']';
        }
        echo json_encode(array('error' => $msg), JSON_UNESCAPED_UNICODE);
    }
});

// Works whether this file sits in htdocs/ (root) or in htdocs/api/
if (is_file(__DIR__ . '/api/ai_helper.php')) {
    $mmApiDir = __DIR__ . '/api';
    $mmRoot   = __DIR__;
} else {
    $mmApiDir = __DIR__;
    $mmRoot   = dirname(__DIR__);
}

foreach (array($mmApiDir . '/ai_helper.php', $mmRoot . '/config/config.php', $mmRoot . '/db.php') as $mmReq) {
    if (!is_file($mmReq)) {
        mm_out(array('error' => 'Server setup problem: missing file ' . basename(dirname($mmReq)) . '/' . basename($mmReq)));
    }
    require_once $mmReq;
}
// api/db.php doesn't exist on every install - the root db.php already provides $pdo.
if (is_file($mmApiDir . '/db.php')) {
    require_once $mmApiDir . '/db.php';
}

if (!is_logged_in()) {
    http_response_code(401);
    mm_out(array('error' => 'Please log in first.'));
}

// Read user id, then release the session lock (the AI call is slow).
$mmUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// ---------------------------------------------------------------------
// Uploaded notes / past papers -> optional context (never allowed to break generation)
// ---------------------------------------------------------------------
define('MM_RESOURCE_UPLOAD_DIR', $mmRoot . '/uploads');

function mm_extract_pdf_text($filePath)
{
    $size = @filesize($filePath);
    if ($size === false || $size > 3 * 1024 * 1024) return '';

    $raw = @file_get_contents($filePath);
    if ($raw === false) return '';

    $text = '';
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams)) {
        foreach ($streams[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) $decoded = @gzinflate($stream);
            $content = ($decoded !== false) ? $decoded : $stream;

            if (!preg_match_all('/BT(.*?)ET/s', $content, $textBlocks)) continue;

            foreach ($textBlocks[1] as $block) {
                if (preg_match_all('/\((?:\\\\.|[^()])*\)/s', $block, $tm)) {
                    foreach ($tm[0] as $t) {
                        $t = substr($t, 1, -1);
                        $t = str_replace(array('\\(', '\\)', '\\\\'), array('(', ')', '\\'), $t);
                        $text .= $t . ' ';
                    }
                }
            }
            if (strlen($text) > 60000) break;
        }
    }
    $clean = preg_replace('/\s+/', ' ', $text);
    return trim($clean === null ? $text : $clean);
}

function mm_sanitize_text($text)
{
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    if ($clean !== null) $text = $clean;
    $clean = preg_replace('/([^\p{L}\p{N}\s])\1{2,}/u', '$1', $text);
    if ($clean !== null) $text = $clean;
    $clean = preg_replace('/\s+/', ' ', $text);
    if ($clean !== null) $text = $clean;
    return trim($text);
}

function mm_is_readable($text)
{
    $len = mb_strlen($text);
    if ($len < 40) return false;
    $ok = preg_match_all('/[\p{L}\p{N}\s.,;:!?()\/-]/u', $text);
    return ($ok / max($len, 1)) >= 0.75;
}

function mm_get_resource_text($pdo, $resource, $allowExtract = true)
{
    $text = trim(isset($resource['content']) ? (string)$resource['content'] : '');
    if ($text !== '') return $text;
    if (!$allowExtract) return '';

    if (empty($resource['file_path'])) return '';
    $path = rtrim(MM_RESOURCE_UPLOAD_DIR, '/') . '/' . ltrim($resource['file_path'], '/');
    if (!is_file($path)) return '';

    $text = mm_sanitize_text(mm_extract_pdf_text($path));
    if (!mm_is_readable($text)) return '';

    try {
        $upd = $pdo->prepare("UPDATE resources SET content = ? WHERE id = ?");
        $upd->execute(array($text, $resource['id']));
    } catch (Throwable $e) { /* content column may not exist - ignore */ }

    return $text;
}

// Topic words used to find the most relevant notes / passages
function mm_keywords($topic)
{
    $stop = array('the', 'and', 'for', 'with', 'from', 'into', 'about', 'what', 'how', 'are', 'its', 'note', 'notes');
    $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($topic), -1, PREG_SPLIT_NO_EMPTY);
    $out = array();
    if (!$parts) return $out;
    foreach ($parts as $p) {
        if (mb_strlen($p) < 3 || in_array($p, $stop, true)) continue;
        $out[$p] = true;
    }
    return array_keys($out);
}

function mm_hits($haystack, $keywords)
{
    if (!$keywords) return 0;
    $h = mb_strtolower($haystack);
    $n = 0;
    foreach ($keywords as $k) {
        $n += mb_substr_count($h, $k);
    }
    return $n;
}

// Pick the passages of a long text that mention the topic the most
function mm_best_excerpt($text, $keywords, $limit)
{
    if (mb_strlen($text) <= $limit) return $text;

    $win = 1200;
    $total = mb_strlen($text);
    $chunks = array();
    for ($i = 0, $idx = 0; $i < $total; $i += $win, $idx++) {
        $c = mb_substr($text, $i, $win);
        $chunks[] = array('i' => $idx, 'text' => $c, 'score' => mm_hits($c, $keywords));
    }
    $order = $chunks;
    usort($order, function ($a, $b) {
        if ($a['score'] === $b['score']) return $a['i'] - $b['i'];
        return $b['score'] - $a['score'];
    });
    $take = max(1, (int)floor($limit / $win));
    $picked = array_slice($order, 0, $take);
    usort($picked, function ($a, $b) { return $a['i'] - $b['i']; });

    $out = array();
    foreach ($picked as $c) $out[] = $c['text'];
    return implode(' ... ', $out);
}

function mm_fetch_resources($pdo, $subjectId, $resourceId)
{
    $where = '';
    $params = array();
    if ($resourceId) {
        $where = 'WHERE id = ?';
        $params[] = $resourceId;
    } elseif ($subjectId) {
        $where = 'WHERE subject_id = ?';
        $params[] = $subjectId;
    }
    $variants = array(
        "SELECT id, title, type, file_path, content, subject_id FROM resources $where ORDER BY created_at DESC LIMIT 80",
        "SELECT id, title, type, file_path, NULL AS content, subject_id FROM resources $where ORDER BY created_at DESC LIMIT 80",
        "SELECT id, title, type, file_path, NULL AS content FROM resources $where LIMIT 80"
    );
    foreach ($variants as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            // try the next, simpler query
        }
    }
    return array();
}

function mm_label($resource)
{
    return (stripos(isset($resource['type']) ? $resource['type'] : '', 'past paper') !== false) ? 'PAST PAPER' : 'NOTES';
}

// Returns array('text' => prompt context, 'used' => titles used, 'unreadable' => titles we couldn't read)
function mm_build_source_context($pdo, $subjectId, $resourceId, $topic)
{
    $result = array('text' => '', 'used' => array(), 'unreadable' => array());
    if (!function_exists('mb_strlen') || !function_exists('mb_substr')) return $result;

    $keywords = mm_keywords($topic);
    $rows = mm_fetch_resources($pdo, $subjectId, $resourceId);
    if (!$rows) return $result;

    // A) The student picked one specific resource
    if ($resourceId) {
        $r = $rows[0];
        $t = mm_get_resource_text($pdo, $r, true);
        if ($t === '') {
            $result['unreadable'][] = $r['title'];
            return $result;
        }
        $result['text'] = "--- " . mm_label($r) . ": " . $r['title'] . " ---\n" . mm_best_excerpt($t, $keywords, 9000);
        $result['used'][] = $r['title'];
        return $result;
    }

    // B) Auto: rank by how well the title matches the topic, read the best few
    foreach ($rows as $i => $r) {
        $rows[$i]['_tscore'] = mm_hits(isset($r['title']) ? $r['title'] : '', $keywords);
        $rows[$i]['_idx'] = $i;
    }
    usort($rows, function ($a, $b) {
        if ($a['_tscore'] === $b['_tscore']) return $a['_idx'] - $b['_idx'];
        return $b['_tscore'] - $a['_tscore'];
    });

    $extractsLeft = 4; // PDFs parsed per request (results are cached in resources.content)
    $cands = array();
    foreach ($rows as $r) {
        $cached = trim(isset($r['content']) ? (string)$r['content'] : '');
        if ($cached === '') {
            if ($extractsLeft <= 0 || empty($r['file_path'])) continue;
            $extractsLeft--;
            $t = mm_get_resource_text($pdo, $r, true);
            if ($t === '') {
                $result['unreadable'][] = $r['title'];
                continue;
            }
        } else {
            $t = $cached;
        }
        $score = $r['_tscore'] * 20 + min(mm_hits($t, $keywords), 60);
        // With "General" (no subject), only use resources that actually relate to the topic
        if (!$subjectId && $keywords && $score === 0) continue;
        $cands[] = array('r' => $r, 'text' => $t, 'score' => $score);
    }
    usort($cands, function ($a, $b) { return $b['score'] - $a['score']; });
    $cands = array_slice($cands, 0, 5);

    $blocks = array();
    foreach ($cands as $c) {
        $blocks[] = "--- " . mm_label($c['r']) . ": " . $c['r']['title'] . " ---\n" . mm_best_excerpt($c['text'], $keywords, 1800);
        $result['used'][] = $c['r']['title'];
    }
    $combined = implode("\n\n", $blocks);
    if (mb_strlen($combined) > 9500) {
        $combined = mb_substr($combined, 0, 9500) . "\n[...truncated...]";
    }
    $result['text'] = $combined;
    return $result;
}

// ---------------------------------------------------------------------
// Main handler
// ---------------------------------------------------------------------
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $topic = trim(isset($input['topic']) ? $input['topic'] : '');
    $subjectId = (int)(isset($input['subject']) ? $input['subject'] : 0);
    if (!$subjectId) $subjectId = null;

    if ($topic === '') {
        mm_out(array('error' => 'Topic is required'));
    }

    $resourceRaw = isset($input['resource']) ? $input['resource'] : '';
    $resourceId = is_numeric($resourceRaw) ? (int)$resourceRaw : 0;
    $sourceContext = '';
    $sourceInfo = array('used' => array(), 'unreadable' => array());
    if (MM_USE_SOURCES && $resourceRaw !== 'none') {
        try {
            $ctx = mm_build_source_context($pdo, $subjectId, $resourceId, $topic);
            $sourceContext = $ctx['text'];
            $sourceInfo = array('used' => $ctx['used'], 'unreadable' => $ctx['unreadable']);
        } catch (Throwable $e) {
            error_log('mindmap source context error: ' . $e->getMessage());
            $sourceContext = '';
        }
    }
    $hasSource = trim($sourceContext) !== '';

    $sourceInstruction = $hasSource
        ? "SOURCE MATERIAL is provided below: excerpts from the student's OWN uploaded study notes and/or real "
          . "past exam papers. Build the mind-map PRIMARILY from the NOTES sections: follow their terminology, "
          . "headings, definitions and examples, and let them shape the branch structure. Only fill obvious gaps "
          . "from general syllabus knowledge. Cross-check each bullet item against any PAST PAPER sections: "
          . "whenever an item clearly corresponds to something tested in the past papers, add \"important\": true "
          . "to that item (omit or set false otherwise). If parts of the source material look noisy, garbled or "
          . "irrelevant to the topic, ignore those parts and rely on general syllabus knowledge - never let "
          . "unclear text stop you from producing a complete, high-quality mind-map."
        : "No uploaded notes/past papers were available for this topic, so build the mind-map from general A/L syllabus knowledge on the topic.";

    $systemPrompt = "You generate DETAILED, EXAM-READY, BILINGUAL mind-map structures for Sri Lankan A/L "
        . "(Advanced Level) students, in the style of a full syllabus-unit revision mind-map (many branches, "
        . "each packed with specific bullet points - like a textbook unit summary, not a vague overview). "
        . "IMPORTANT - STAY ON TOPIC: Only generate mind-maps for topics related to these 5 A/L subjects: "
        . "SFT (Software Technology), ICT, ET (Engineering Technology), BST (Bio System Technology), and English. "
        . "Base this off-topic judgment ONLY on the requested topic text itself - never on the SOURCE MATERIAL "
        . "provided below. "
        . "If the requested topic is clearly unrelated to these subjects, respond with ONLY this exact JSON: "
        . '{ "error": "off_topic" } - nothing else. '
        . "Otherwise, $sourceInstruction "
        . "Respond ONLY with valid JSON, no markdown fences, no extra text, no trailing commas. "
        . "EVERY piece of text - the main title, every branch title, and every bullet item - MUST be given in "
        . "BOTH languages as an object { \"en\": \"...\", \"si\": \"...\" }: en = English, si = proper Sinhala "
        . "script (සිංහල), written the way a Sri Lankan A/L textbook would phrase it. Keep well-known English "
        . "technical terms and abbreviations (HTML, CPU, API, RFID, GPS, ATM, CAD, CAM, etc.) written in English "
        . "even inside the Sinhala text. Never leave 'si' empty or identical to 'en' unless the term genuinely "
        . "has no Sinhala equivalent. "
        . "STRUCTURE: exactly ONE level of branches under the title - do not nest a third level. Include "
        . "exactly 6 main branches that together cover the topic completely, and 5-7 specific bullet items "
        . "under each branch (short phrases or named examples, under ~8 words per language, not full "
        . "sentences). "
        . "The JSON shape is exactly: "
        . '{ "title": {"en": string, "si": string}, '
        . '"children": [ { "title": {"en": string, "si": string}, '
        . '"items": [ {"en": string, "si": string, "important": boolean} ] } ] }';

    $userMsg = "Create a detailed, comprehensive, BILINGUAL (English + Sinhala) mind-map for the topic "
        . "\"$topic\", covering what a student needs to know for an A/L exam on this.";
    if ($hasSource) {
        $userMsg .= "\n\nSOURCE MATERIAL:\n" . $sourceContext;
    }
    $userMsg .= "\n\nReturn ONLY the JSON object.";

    $result = call_claude($systemPrompt, $userMsg, 6000, true);
    if (!is_array($result)) {
        mm_out(array('error' => 'The AI service did not respond. Please try again.'));
    }
    if (isset($result['error'])) {
        mm_out(array('error' => $result['error']));
    }

    $tree = extract_json(isset($result['text']) ? $result['text'] : '');

    if (is_array($tree) && isset($tree['error']) && $tree['error'] === 'off_topic') {
        mm_out(array('error' => 'This topic doesn\'t seem related to SFT, ICT, ET, BST or English. Please enter a topic from your A/L syllabus.'));
    }

    if (!is_array($tree) || !isset($tree['title'])) {
        mm_out(array('error' => 'The AI response was incomplete or invalid. Please try again - try a shorter, more specific topic.'));
    }

    if (is_array($tree['title'])) {
        $titleForDb = isset($tree['title']['en']) ? $tree['title']['en'] : (isset($tree['title']['si']) ? $tree['title']['si'] : $topic);
    } else {
        $titleForDb = $tree['title'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO mindmaps (user_id, subject_id, title, content) VALUES (?, ?, ?, ?)");
        $stmt->execute(array($mmUserId, $subjectId, $titleForDb, json_encode($tree, JSON_UNESCAPED_UNICODE)));
    } catch (Throwable $e) {
        error_log('mindmap save error: ' . $e->getMessage());
        // Non-fatal - still return the mind-map.
    }

    mm_out(array('mindmap' => $tree, 'sources' => $sourceInfo));
} catch (Throwable $e) {
    error_log('mindmap_generator error: ' . $e->getMessage());
    mm_out(array('error' => 'Something went wrong generating the mind-map: ' . $e->getMessage()));
}