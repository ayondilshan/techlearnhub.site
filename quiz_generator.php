<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
require_login();

// ============================================================
// CONFIG — adjust these two if they don't match your setup
// ============================================================
// Folder where uploaded resource files (PDFs) actually live on disk.
// If file_path in the DB is just a filename like "1790528823_ICTSFT.pdf",
// this should point to the folder that contains it.
define('RESOURCE_UPLOAD_DIR', __DIR__ . '/uploads');

// ============================================================
// Pure-PHP PDF text extractor (no composer / no exec needed)
// Works reasonably well for text-based PDFs (typed, not scanned).
// Sinhala PDFs made from scanned images or non-Unicode fonts may
// not extract cleanly — that needs OCR, which is a separate task.
// ============================================================
function extract_pdf_text(string $filePath): string
{
    $raw = @file_get_contents($filePath);
    if ($raw === false) return '';

    $text = '';
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams)) {
        foreach ($streams[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }
            $content = ($decoded !== false) ? $decoded : $stream;

            // Only look inside actual text-showing blocks (BT...ET), which is
            // where real page content lives. This skips font tables, XObject
            // definitions and other stream metadata that isn't real text.
            if (!preg_match_all('/BT(.*?)ET/s', $content, $textBlocks)) {
                continue;
            }

            foreach ($textBlocks[1] as $block) {
                if (preg_match_all('/\((?:\\\\.|[^()])*\)/s', $block, $tm)) {
                    foreach ($tm[0] as $t) {
                        $t = substr($t, 1, -1);
                        $t = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $t);
                        $text .= $t . ' ';
                    }
                }
            }
        }
    }

    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// Strip control/garbled characters that a partial PDF decode can leave behind,
// which otherwise pollute the AI prompt and can break its JSON output.
function sanitize_extracted_text(string $text): string
{
    // Drop anything that isn't valid UTF-8 first — invalid bytes make the /u
    // regex below return null, and null then breaks later string functions.
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8'); // strips invalid sequences
    }

    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = $clean ?? $text;

    $clean = preg_replace('/([^\p{L}\p{N}\s])\1{2,}/u', '$1', $text);
    $text = $clean ?? $text;

    $clean = preg_replace('/\s+/', ' ', $text);
    $text = $clean ?? $text;

    return trim($text);
}

// If a "sentence" of extracted text is mostly not letters/numbers/spaces,
// the PDF decode almost certainly failed (garbled binary noise). In that
// case we should NOT pass it to the AI — it confuses the model far more
// than it helps, and can make it wrongly think the topic is off-syllabus.
function is_readable_text(string $text): bool
{
    $len = mb_strlen($text);
    if ($len < 40) return false; // too short to be useful anyway

    $lettersDigitsSpaces = preg_match_all('/[\p{L}\p{N}\s.,;:!?()\/-]/u', $text);
    $ratio = $lettersDigitsSpaces / max($len, 1);

    return $ratio >= 0.75;
}

// Get resource text: prefer cached `content` column, else extract from PDF
// and cache it back into `content` for next time. Fails silently and safely
// if the `content` column doesn't exist in this DB.
function get_resource_text(PDO $pdo, array $resource): string
{
    $text = trim($resource['content'] ?? '');
    if ($text !== '') return $text;

    if (empty($resource['file_path'])) return '';
    $path = rtrim(RESOURCE_UPLOAD_DIR, '/') . '/' . ltrim($resource['file_path'], '/');
    if (!is_file($path)) return '';

    $text = extract_pdf_text($path);
    $text = sanitize_extracted_text($text);
    if (!is_readable_text($text)) return ''; // decode likely failed — don't feed noise to the AI

    if ($text !== '') {
        try {
            $upd = $pdo->prepare("UPDATE resources SET content = ? WHERE id = ?");
            $upd->execute([$text, $resource['id']]);
        } catch (Throwable $e) {
            // `content` column may not exist — ignore, we still return the text for this run
        }
    }
    return $text;
}

// Fetch a subject's resources, Past Papers first, then Notes, newest first.
// Falls back gracefully if the `content` column doesn't exist yet.
function fetch_subject_resources(PDO $pdo, int $subjectId): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, type, file_path, content FROM resources
             WHERE subject_id = ?
             ORDER BY FIELD(type, 'Past Paper', 'Notes'), created_at DESC"
        );
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        $stmt = $pdo->prepare(
            "SELECT id, title, type, file_path, NULL AS content FROM resources
             WHERE subject_id = ?
             ORDER BY FIELD(type, 'Past Paper', 'Notes'), created_at DESC"
        );
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll();
    }
}

// Build the source-material block to feed the AI: mostly past papers, some notes.
function build_source_context(PDO $pdo, int $subjectId): string
{
    $resources = fetch_subject_resources($pdo, $subjectId);
    if (!$resources) return '';

    $pastPapers = array_filter($resources, fn($r) => stripos($r['type'] ?? '', 'past paper') !== false);
    $notes      = array_filter($resources, fn($r) => stripos($r['type'] ?? '', 'past paper') === false);

    $maxPastPapers = 3;
    $maxNotes = 2;
    $perDocCharLimit = 2000;
    $maxTotalChars = 7000; // hard cap on combined source text sent to the AI

    $blocks = [];

    $used = 0;
    foreach ($pastPapers as $r) {
        if ($used >= $maxPastPapers) break;
        $t = get_resource_text($pdo, $r);
        if ($t === '') continue;
        $blocks[] = "--- PAST PAPER: " . $r['title'] . " ---\n" . mb_substr($t, 0, $perDocCharLimit);
        $used++;
    }

    $used = 0;
    foreach ($notes as $r) {
        if ($used >= $maxNotes) break;
        $t = get_resource_text($pdo, $r);
        if ($t === '') continue;
        $blocks[] = "--- NOTES: " . $r['title'] . " ---\n" . mb_substr($t, 0, $perDocCharLimit);
        $used++;
    }

    $combined = implode("\n\n", $blocks);
    if (mb_strlen($combined) > $maxTotalChars) {
        $combined = mb_substr($combined, 0, $maxTotalChars) . "\n[...truncated...]";
    }
    return $combined;
}

// ---- Handle AJAX POST request from the page's own JS: generate quiz, return JSON ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Safety net: never let a raw PHP error/warning corrupt the JSON response.
    // Suppress on-screen error output for this request and log instead; if a
    // fatal error still happens, catch it on shutdown and emit clean JSON.
    ini_set('display_errors', '0');
    header('Content-Type: application/json');

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['error' => 'Server error while generating the quiz. Please try again.']);
        }
    });

    try {
        quiz_generator_handle_post($pdo);
    } catch (Throwable $e) {
        error_log('quiz_generator error: ' . $e->getMessage());
        echo json_encode(['error' => 'Something went wrong generating the quiz: ' . $e->getMessage()]);
    }
    exit;
}

function quiz_generator_handle_post(PDO $pdo): void
{
    require_once __DIR__ . '/api/ai_helper.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $topic = trim($input['topic'] ?? '');
    $subjectId = (int)($input['subject'] ?? 0) ?: null;
    $difficulty = $input['difficulty'] ?? 'Medium';
    $count = max(3, min(10, (int)($input['count'] ?? 5)));
    $language = $input['language'] ?? 'auto';

    if (!$topic) {
        echo json_encode(['error' => 'Topic is required']);
        return;
    }

    $languageInstruction = match ($language) {
        'sinhala' => "IMPORTANT: Write the quiz title, questions, options and explanations in proper Sinhala script (සිංහල), regardless of what language the topic was typed in. Keep well-known English technical terms as-is within the Sinhala text where that reads more naturally.",
        'english' => "IMPORTANT: Write the quiz title, questions, options and explanations in English, regardless of what language the topic was typed in.",
        default => "Detect the language of the topic given by the user — if it's in Sinhala or Singlish, write the quiz title, questions, options and explanations in Sinhala script; if it's in English, write them in English.",
    };

    // Pull real source material (past papers + notes) for this subject, if one was picked
    $sourceContext = $subjectId ? build_source_context($pdo, $subjectId) : '';
    $hasSource = trim($sourceContext) !== '';

    $sourceInstruction = $hasSource
        ? "SOURCE MATERIAL PROVIDED BELOW is real content the student has uploaded: actual past exam papers and study notes for this subject. "
          . "At least 70% of the questions you generate MUST be based directly on, or be close variations/rephrasings of, the real questions found in the PAST PAPER sections. "
          . "Use the NOTES sections only to fill in extra concept coverage or write better explanations. "
          . "Do not invent unrelated content when real source material is given — stay grounded in what's provided. "
          . "If a past paper question doesn't fit multiple-choice format well, adapt it into an equivalent MCQ testing the same knowledge."
        : "No uploaded past paper/notes content was available for this subject, so generate the quiz from general A/L syllabus knowledge on the topic.";

    $systemPrompt = "You are a quiz-generating engine for a Sri Lankan A/L (Advanced Level) student study platform. "
        . "IMPORTANT — STAY ON TOPIC: Only generate quiz questions related to these 5 A/L subjects: "
        . "SFT (Software Technology), ICT, ET (Engineering Technology), BST (Bio System Technology), and English. "
        . "If the requested topic is clearly unrelated to these subjects (e.g. entertainment, sports, celebrities, "
        . "general trivia, or anything with no connection to the A/L syllabus for these subjects), respond with "
        . 'ONLY this exact JSON: { "error": "off_topic" } — nothing else. '
        . "Base this off-topic judgment ONLY on the requested topic text itself — never on the SOURCE MATERIAL section below. "
        . "If SOURCE MATERIAL is provided, treat it purely as reference content to draw questions from; if parts of it look noisy, incomplete, or hard to read, just ignore those parts and use whatever is usable, or fall back to general syllabus knowledge — this is never a reason to call the topic off_topic. "
        . "Otherwise, $languageInstruction "
        . "$sourceInstruction "
        . "Respond ONLY with valid JSON, no markdown fences, no extra text, no trailing commas. "
        . 'The JSON shape must be exactly: { "title": string, "questions": [ { "question": string, "options": [string, string, string, string], "answer": number (0-3, the index of the correct option), "explanation": string } ] }';

    $userMsg = "Create $count multiple-choice questions on the topic \"$topic\" at $difficulty difficulty, suitable for a school/college student.";
    if ($hasSource) {
        $userMsg .= "\n\nSOURCE MATERIAL:\n" . $sourceContext;
    }
    $userMsg .= "\n\nReturn ONLY the JSON object.";

    $result = call_claude($systemPrompt, $userMsg, 4000, true);
    if (isset($result['error'])) {
        echo json_encode(['error' => $result['error']]);
        return;
    }

    $quiz = extract_json($result['text']);

    if (is_array($quiz) && isset($quiz['error']) && $quiz['error'] === 'off_topic') {
        echo json_encode(['error' => "This topic doesn't seem related to SFT, ICT, ET, BST or English. Please enter a topic from your A/L syllabus."]);
        return;
    }

    if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
        echo json_encode(['error' => 'AI returned an unexpected format. Please try again.']);
        return;
    }

    // Best-effort save to quiz history (schema uses option_a..d + a letter answer; adapt from options[]/index)
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO quizzes (user_id, subject_id, topic, difficulty) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $subjectId, $topic, $difficulty]);
        $quizId = $pdo->lastInsertId();

        $qStmt = $pdo->prepare("INSERT INTO quiz_questions
            (quiz_id, question, option_a, option_b, option_c, option_d, correct_option, explanation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $letters = ['a', 'b', 'c', 'd'];
        foreach ($quiz['questions'] as $q) {
            if (!isset($q['question'], $q['options']) || !is_array($q['options']) || count($q['options']) < 4) continue;
            $answerIndex = (int)($q['answer'] ?? 0);
            $qStmt->execute([
                $quizId, $q['question'],
                $q['options'][0] ?? '', $q['options'][1] ?? '', $q['options'][2] ?? '', $q['options'][3] ?? '',
                $letters[$answerIndex] ?? 'a', $q['explanation'] ?? ''
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Non-fatal — still return the quiz to the student even if history saving failed.
    }

    echo json_encode(['quiz' => $quiz]);
    return;
}

// ---- Normal GET request: render the page ----
$pageTitle = 'Quiz Generator';
require_once __DIR__ . '/includes/header.php';
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
?>
<h1 style="margin-bottom:16px;">📝 AI Quiz Generator</h1>
<p style="color:var(--muted); margin-bottom:16px;">Generate an instant multiple-choice quiz on any topic and get marked automatically. Pick a subject to base it on your uploaded notes & past papers.</p>

<div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
  <input type="text" id="quizTopic" placeholder="e.g. Database Normalization" style="flex:1; min-width:200px; padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
  <select id="quizSubject" style="padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
    <option value="">General</option>
    <?php foreach ($subjects as $s): ?>
      <option value="<?= (int)$s['id'] ?>"><?= clean($s['code']) ?></option>
    <?php endforeach; ?>
  </select>
  <select id="quizLanguage" style="padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
    <option value="auto">Auto language</option>
    <option value="sinhala" selected>සිංහල (Sinhala)</option>
    <option value="english">English</option>
  </select>
  <select id="quizDifficulty" style="padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
    <option>Easy</option>
    <option selected>Medium</option>
    <option>Hard</option>
  </select>
  <select id="quizCount" style="padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
    <option value="5" selected>5 Questions</option>
    <option value="10">10 Questions</option>
  </select>
  <button class="btn" id="quizBtn" onclick="generateQuiz()">Generate Quiz</button>
</div>
<p style="color:var(--muted); font-size:13px; margin-bottom:10px;">💡 Select a subject to generate questions mostly from your uploaded past papers.</p>
<p id="quizStatus" style="color:var(--muted); margin-bottom:10px;"></p>
<div id="quizContainer" style="font-family:'Noto Sans Sinhala','Iskoola Pota',system-ui,sans-serif;"></div>

<script>
let currentQuiz = null;

function esc(s) {
  const d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

async function generateQuiz() {
  const topic = document.getElementById('quizTopic').value.trim();
  const status = document.getElementById('quizStatus');
  const btn = document.getElementById('quizBtn');
  const box = document.getElementById('quizContainer');

  if (!topic) { status.textContent = 'Please enter a topic.'; return; }

  status.textContent = 'Generating quiz…';
  btn.disabled = true;
  box.innerHTML = '';

  try {
    const res = await fetch('quiz_generator.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        topic,
        subject: document.getElementById('quizSubject').value,
        language: document.getElementById('quizLanguage').value,
        difficulty: document.getElementById('quizDifficulty').value,
        count: document.getElementById('quizCount').value
      })
    });

    const raw = await res.text();
    let data;
    try { data = JSON.parse(raw); }
    catch (e) {
      console.error('Server returned non-JSON:', raw);
      throw new Error('Server returned an invalid response. Open the browser console (F12) to see it.');
    }

    if (data.error) throw new Error(data.error);
    if (!data.quiz || !Array.isArray(data.quiz.questions) || data.quiz.questions.length === 0) {
      throw new Error('AI returned an incomplete quiz. Please try again.');
    }
    currentQuiz = data.quiz;
    renderQuiz();
    status.textContent = '';
  } catch (err) {
    status.textContent = 'Error generating quiz: ' + err.message;
  } finally {
    btn.disabled = false;
  }
}

function renderQuiz() {
  const box = document.getElementById('quizContainer');
  let html = '<h2 style="margin-bottom:12px;">' + esc(currentQuiz.title || 'Quiz') + '</h2>';

  currentQuiz.questions.forEach((q, i) => {
    html += '<div class="quiz-q" id="q' + i + '" style="border:1px solid var(--border); border-radius:10px; padding:14px; margin-bottom:12px;">';
    html += '<p style="font-weight:600; margin-bottom:8px;">' + (i + 1) + '. ' + esc(q.question) + '</p>';
    q.options.forEach((opt, j) => {
      html += '<label style="display:block; padding:6px 8px; cursor:pointer;">'
            + '<input type="radio" name="q' + i + '" value="' + j + '"> ' + esc(opt) + '</label>';
    });
    html += '<div class="quiz-expl" style="display:none; margin-top:8px;"></div>';
    html += '</div>';
  });

  html += '<button class="btn" onclick="markQuiz()">Submit &amp; Mark</button> <span id="quizScore" style="margin-left:12px; font-weight:600;"></span>';
  box.innerHTML = html;
}

function markQuiz() {
  let score = 0;
  currentQuiz.questions.forEach((q, i) => {
    const card = document.getElementById('q' + i);
    const chosen = document.querySelector('input[name="q' + i + '"]:checked');
    const correct = parseInt(q.answer, 10);
    const labels = card.querySelectorAll('label');

    labels.forEach((l, j) => {
      l.querySelector('input').disabled = true;
      if (j === correct) l.style.background = 'rgba(34,197,94,.18)';
    });

    if (chosen && parseInt(chosen.value, 10) === correct) {
      score++;
    } else if (chosen) {
      labels[parseInt(chosen.value, 10)].style.background = 'rgba(239,68,68,.18)';
    }

    // Correct answer shown in a distinct green line, explanation (if any) below it in muted gray
    const expl = card.querySelector('.quiz-expl');
    const correctText = (q.options && q.options[correct] !== undefined) ? q.options[correct] : '';
    expl.innerHTML =
      '<div style="color:#16a34a; font-weight:700;">✅ නිවැරදි උත්තරය: ' + esc(correctText) + '</div>'
      + (q.explanation ? '<div style="color:var(--muted); margin-top:4px;">' + esc(q.explanation) + '</div>' : '');
    expl.style.display = 'block';
  });

  document.getElementById('quizScore').textContent =
    'Score: ' + score + ' / ' + currentQuiz.questions.length;

  // Submit button has done its job — turn it into a "back to Home" button
 const submitBtn = document.querySelector('#quizContainer .btn');
  if (submitBtn) {
    submitBtn.outerHTML = `
      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 15px;">
        <button class="btn" onclick="window.location.href='index.php'">🏠 Home එකට යන්න</button>
        <button class="btn btn-outline" onclick="window.location.href='dashboard/take_quiz.php'">📝 Answer Sheet (take_quiz)</button>
      </div>
    `;
  }
    }
    

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>