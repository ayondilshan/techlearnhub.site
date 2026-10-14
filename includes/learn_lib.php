<?php
/**
 * Code Lab helpers (learning paths).
 * Load AFTER config/config.php so $pdo, the session, BASE_URL, clean() and set_flash() exist.
 */

// Week resets / streaks use Sri Lanka time unless config.php already chose a timezone.
if (date_default_timezone_get() === 'UTC') {
    date_default_timezone_set('Asia/Colombo');
}

// Leaderboard rounds are counted from this fixed Monday so every student sees the same round.
const LP_LEADERBOARD_ANCHOR = '2026-01-05';

const LP_LANGS = ['html' => 'HTML', 'css' => 'CSS', 'php' => 'PHP', 'python' => 'Python'];

const LP_CHECK_TYPES = [
    'code_contains', 'code_regex',
    'dom_exists', 'dom_count_min', 'dom_text_contains', 'dom_style',
    'output_equals', 'output_contains',
];

/* ---------- session / access ---------- */

function lp_uid(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function lp_is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** Any logged-in user may open the Code Lab. Admins get a preview mode (nothing is saved). */
function lp_require_user(): void
{
    if (lp_uid() === 0) {
        set_flash('error', 'Please log in to use the Code Lab.');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function lp_tables_ready(PDO $pdo): bool
{
    try {
        $r = $pdo->query("SELECT 1 FROM lp_modules LIMIT 1");
        return $r !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/* ---------- settings ---------- */

function lp_settings(PDO $pdo): array
{
    $s = ['weekly_points_goal' => 100, 'weekly_tasks_goal' => 5, 'streak_goal' => 2, 'leaderboard_days' => 4];
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM lp_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($s as $k => $v) {
            if (isset($rows[$k])) {
                $s[$k] = max(1, (int)$rows[$k]);
            }
        }
    } catch (Throwable $e) {
        // keep defaults
    }
    return $s;
}

/* ---------- queries ---------- */

function lp_modules(PDO $pdo, int $uid, bool $includeHidden = false): array
{
    $sql = "SELECT m.*,
              (SELECT COUNT(*) FROM lp_tasks t WHERE t.module_id = m.id AND t.is_published = 1) AS total_tasks,
              (SELECT COUNT(*) FROM lp_tasks t
                 JOIN lp_progress p ON p.task_id = t.id AND p.user_id = ? AND p.status = 'completed'
                 WHERE t.module_id = m.id AND t.is_published = 1) AS done_tasks,
              (SELECT COUNT(*) FROM lp_saved s WHERE s.module_id = m.id AND s.user_id = ?) AS is_saved
            FROM lp_modules m "
        . ($includeHidden ? '' : 'WHERE m.is_published = 1 ')
        . "ORDER BY m.sort_order, m.id";
    $st = $pdo->prepare($sql);
    $st->execute([$uid, $uid]);
    return $st->fetchAll();
}

function lp_tasks(PDO $pdo, int $moduleId, int $uid, bool $includeHidden = false): array
{
    $sql = "SELECT t.*, p.status AS p_status, p.completed_at
            FROM lp_tasks t
            LEFT JOIN lp_progress p ON p.task_id = t.id AND p.user_id = ?
            WHERE t.module_id = ? "
        . ($includeHidden ? '' : 'AND t.is_published = 1 ')
        . "ORDER BY t.sort_order, t.id";
    $st = $pdo->prepare($sql);
    $st->execute([$uid, $moduleId]);
    return $st->fetchAll();
}

function lp_total_points(PDO $pdo, int $uid): int
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(points_awarded), 0) FROM lp_progress WHERE user_id = ? AND status = 'completed'");
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
}

/* ---------- weekly mission ---------- */

function lp_week_start(): DateTime
{
    return new DateTime('monday this week 00:00:00');
}

function lp_days_to_next_mission(): int
{
    $now  = new DateTime('now');
    $next = new DateTime('monday next week 00:00:00');
    return max(1, (int)ceil(($next->getTimestamp() - $now->getTimestamp()) / 86400));
}

function lp_week_stats(PDO $pdo, int $uid): array
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(points_awarded), 0) AS pts, COUNT(*) AS cnt
                         FROM lp_progress
                         WHERE user_id = ? AND status = 'completed' AND completed_at >= ?");
    $st->execute([$uid, lp_week_start()->format('Y-m-d H:i:s')]);
    $row = $st->fetch();
    return ['points' => (int)($row['pts'] ?? 0), 'tasks' => (int)($row['cnt'] ?? 0)];
}

/** Consecutive days (ending today or yesterday) on which the user completed at least one task. */
function lp_streak(PDO $pdo, int $uid): int
{
    $st = $pdo->prepare("SELECT DISTINCT DATE(completed_at) AS d
                         FROM lp_progress
                         WHERE user_id = ? AND status = 'completed' AND completed_at IS NOT NULL
                         ORDER BY d DESC LIMIT 60");
    $st->execute([$uid]);
    $days = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!$days) {
        return 0;
    }
    $cursor = new DateTime('today');
    if ($days[0] !== $cursor->format('Y-m-d')) {
        $cursor->modify('-1 day');
        if ($days[0] !== $cursor->format('Y-m-d')) {
            return 0;
        }
    }
    $streak = 0;
    foreach ($days as $d) {
        if ($d === $cursor->format('Y-m-d')) {
            $streak++;
            $cursor->modify('-1 day');
        } else {
            break;
        }
    }
    return $streak;
}

/* ---------- leaderboard (a new round every N days) ---------- */

/** Start/end of the current round and the start of the previous one. */
function lp_round(int $days): array
{
    $days   = max(1, $days);
    $anchor = new DateTime(LP_LEADERBOARD_ANCHOR . ' 00:00:00');
    $today  = new DateTime('today');
    $since  = $today >= $anchor ? (int)$anchor->diff($today)->days : 0;
    $index  = intdiv($since, $days);

    $start = (clone $anchor)->modify('+' . ($index * $days) . ' days');
    $end   = (clone $start)->modify('+' . $days . ' days');
    $prev  = (clone $start)->modify('-' . $days . ' days');
    return ['start' => $start, 'end' => $end, 'prev_start' => $prev, 'seconds_left' => max(0, $end->getTimestamp() - time())];
}

/** "2d 5h" / "5h" / "under 1h" */
function lp_time_left(int $seconds): string
{
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    if ($d > 0) {
        return $d . 'd ' . $h . 'h';
    }
    return $h > 0 ? $h . 'h' : 'under 1h';
}

/** True when the users table already has the first_name column (see sql/users_first_last_name.sql). */
function lp_has_first_name(PDO $pdo): bool
{
    static $has = null;
    if ($has === null) {
        try {
            $has = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'first_name'")->fetch();
        } catch (Throwable $e) {
            $has = false;
        }
    }
    return $has;
}

/** Students ranked by the points they earned between $start and $end. */
function lp_leaderboard(PDO $pdo, DateTime $start, DateTime $end, int $limit = 10): array
{
    // first name only: users.first_name when it exists, otherwise the first word of users.name
    $firstName = lp_has_first_name($pdo)
        ? "COALESCE(NULLIF(TRIM(u.first_name), ''), SUBSTRING_INDEX(TRIM(u.name), ' ', 1))"
        : "SUBSTRING_INDEX(TRIM(u.name), ' ', 1)";
    $st = $pdo->prepare("SELECT u.id, $firstName AS first_name, SUM(p.points_awarded) AS score, MAX(p.completed_at) AS last_at
                         FROM lp_progress p
                         JOIN users u ON u.id = p.user_id
                         WHERE p.status = 'completed' AND p.points_awarded > 0
                           AND p.completed_at >= ? AND p.completed_at < ?
                           AND u.role = 'student' AND u.status = 'active'
                         GROUP BY u.id
                         HAVING score > 0
                         ORDER BY score DESC, last_at ASC, u.id ASC
                         LIMIT " . max(1, $limit));
    $st->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    return $st->fetchAll();
}

/** Leaderboard label: the first name only (shortened if it is very long). */
function lp_display_name(string $firstName): string
{
    return mb_strimwidth(trim($firstName), 0, 20, '…');
}

/* ---------- text helpers ---------- */

function lp_e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** JSON that is safe to print inside a <script> tag. */
function lp_json($v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

function lp_inline(string $s): string
{
    $s = lp_e($s);
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    return $s;
}

/**
 * Turns the admin's plain-text instructions into safe HTML.
 * Supported: blank line = new paragraph, "- item" lists, "1. item" lists,
 * `inline code`, **bold**, and ``` fenced code blocks. Everything else is escaped.
 */
function lp_render_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return '';
    }
    $parts = preg_split('/^```[a-zA-Z]*\n(.*?)\n```[ \t]*$/ms', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            $html .= '<pre class="lp-code"><code>' . lp_e($part) . '</code></pre>';
            continue;
        }
        foreach (preg_split('/\n{2,}/', trim($part)) as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $html .= lp_render_block(explode("\n", $block));
        }
    }
    return $html;
}

function lp_render_block(array $lines): string
{
    $out = '';
    $mode = null;   // 'ul' | 'ol' | 'p'
    $buf = [];
    $flush = function () use (&$out, &$mode, &$buf) {
        if ($mode === 'ul' || $mode === 'ol') {
            $out .= "<$mode>" . implode('', array_map(fn($li) => '<li>' . lp_inline($li) . '</li>', $buf)) . "</$mode>";
        } elseif ($mode === 'p' && $buf) {
            $out .= '<p>' . implode('<br>', array_map('lp_inline', $buf)) . '</p>';
        }
        $mode = null;
        $buf = [];
    };
    foreach ($lines as $line) {
        if (preg_match('/^\s*-\s+(.*)$/', $line, $m)) {
            $type = 'ul';
            $val = $m[1];
        } elseif (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) {
            $type = 'ol';
            $val = $m[1];
        } else {
            $type = 'p';
            $val = $line;
        }
        if ($mode !== $type) {
            $flush();
            $mode = $type;
        }
        $buf[] = $val;
    }
    $flush();
    return $out;
}

/** Validates the admin's checks JSON and returns clean JSON. */
function lp_clean_checks($json): string
{
    $arr = json_decode((string)$json, true);
    if (!is_array($arr)) {
        return '[]';
    }
    $out = [];
    foreach ($arr as $c) {
        if (!is_array($c)) {
            continue;
        }
        $type = (string)($c['type'] ?? '');
        if (!in_array($type, LP_CHECK_TYPES, true)) {
            continue;
        }
        $out[] = [
            'type'     => $type,
            'selector' => trim((string)($c['selector'] ?? '')),
            'value'    => (string)($c['value'] ?? ''),
            'extra'    => (string)($c['extra'] ?? ''),
            'message'  => trim((string)($c['message'] ?? '')),
        ];
        if (count($out) >= 40) {
            break;
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

function lp_icon_svg(string $name): string
{
    $paths = [
        'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.5"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
        'check'  => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'flame'  => '<path d="M12 3c1 3.5 5 5.5 5 10a5 5 0 0 1-10 0c0-2 1-3.2 2-4.2.3 1.4 1.1 2 2 2 0-2-.5-4.5 1-7.8z"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="lp-svg" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}
