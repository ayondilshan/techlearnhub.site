<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/../includes/learn_lib.php';

header('Content-Type: application/json; charset=utf-8');

function lp_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lp_out(['success' => false, 'error' => 'POST only.'], 405);
}

$uid = lp_uid();
if ($uid === 0) {
    lp_out(['success' => false, 'error' => 'Please log in first.'], 401);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = [];
}
$action  = (string)($in['action'] ?? '');
$isAdmin = lp_is_admin();
$now     = date('Y-m-d H:i:s');

try {

    /* ---------- save / unsave a module ---------- */
    if ($action === 'toggle_save') {
        $mid = (int)($in['module_id'] ?? 0);
        $chk = $pdo->prepare("SELECT id FROM lp_modules WHERE id = ?");
        $chk->execute([$mid]);
        if (!$chk->fetch()) {
            lp_out(['success' => false, 'error' => 'Module not found.'], 404);
        }
        $has = $pdo->prepare("SELECT 1 FROM lp_saved WHERE user_id = ? AND module_id = ?");
        $has->execute([$uid, $mid]);
        if ($has->fetch()) {
            $pdo->prepare("DELETE FROM lp_saved WHERE user_id = ? AND module_id = ?")->execute([$uid, $mid]);
            lp_out(['success' => true, 'saved' => false]);
        }
        $pdo->prepare("INSERT INTO lp_saved (user_id, module_id) VALUES (?, ?)")->execute([$uid, $mid]);
        lp_out(['success' => true, 'saved' => true]);
    }

    /* ---------- everything below is about a task ---------- */
    if (!in_array($action, ['complete', 'save_code'], true)) {
        lp_out(['success' => false, 'error' => 'Unknown action.'], 400);
    }

    $taskId = (int)($in['task_id'] ?? 0);
    $code   = mb_substr((string)($in['code'] ?? ''), 0, 60000);

    $st = $pdo->prepare("SELECT t.id, t.points, t.module_id
                         FROM lp_tasks t JOIN lp_modules m ON m.id = t.module_id
                         WHERE t.id = ? " . ($isAdmin ? '' : 'AND t.is_published = 1 AND m.is_published = 1'));
    $st->execute([$taskId]);
    $task = $st->fetch();
    if (!$task) {
        lp_out(['success' => false, 'error' => 'Task not found.'], 404);
    }

    // Admins can try every task, but nothing is recorded for them.
    if ($isAdmin) {
        lp_out(['success' => true, 'preview' => true, 'points_awarded' => 0]);
    }

    $cur = $pdo->prepare("SELECT status FROM lp_progress WHERE user_id = ? AND task_id = ?");
    $cur->execute([$uid, $taskId]);
    $row = $cur->fetch();
    $alreadyDone = $row && $row['status'] === 'completed';

    if ($action === 'save_code') {
        $attempt = !empty($in['attempt']) ? 1 : 0;
        $pdo->prepare("INSERT INTO lp_progress (user_id, task_id, status, code, attempts, last_activity)
                       VALUES (?, ?, 'started', ?, ?, ?)
                       ON DUPLICATE KEY UPDATE code = VALUES(code), attempts = attempts + VALUES(attempts), last_activity = VALUES(last_activity)")
            ->execute([$uid, $taskId, $code, $attempt, $now]);
        lp_out(['success' => true]);
    }

    /* ---------- complete ---------- */
    $award = $alreadyDone ? 0 : (int)$task['points'];
    if ($alreadyDone) {
        $pdo->prepare("UPDATE lp_progress SET code = ?, attempts = attempts + 1, last_activity = ? WHERE user_id = ? AND task_id = ?")
            ->execute([$code, $now, $uid, $taskId]);
    } else {
        $pdo->prepare("INSERT INTO lp_progress (user_id, task_id, status, code, points_awarded, attempts, last_activity, completed_at)
                       VALUES (?, ?, 'completed', ?, ?, 1, ?, ?)
                       ON DUPLICATE KEY UPDATE status = 'completed', code = VALUES(code), points_awarded = VALUES(points_awarded),
                                               attempts = attempts + 1, last_activity = VALUES(last_activity), completed_at = VALUES(completed_at)")
            ->execute([$uid, $taskId, $code, $award, $now, $now]);
    }

    // is the whole module finished now?
    $mod = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM lp_tasks WHERE module_id = ? AND is_published = 1) AS total,
        (SELECT COUNT(*) FROM lp_tasks t JOIN lp_progress p ON p.task_id = t.id AND p.user_id = ? AND p.status = 'completed'
           WHERE t.module_id = ? AND t.is_published = 1) AS done");
    $mod->execute([(int)$task['module_id'], $uid, (int)$task['module_id']]);
    $m = $mod->fetch();

    $cfg = lp_settings($pdo);
    $wk  = lp_week_stats($pdo, $uid);

    lp_out([
        'success'        => true,
        'already_done'   => $alreadyDone,
        'points_awarded' => $award,
        'total_points'   => lp_total_points($pdo, $uid),
        'module_done'    => (int)$m['total'] > 0 && (int)$m['done'] >= (int)$m['total'],
        'week'           => ['points' => $wk['points'], 'tasks' => $wk['tasks'], 'goal_points' => $cfg['weekly_points_goal'], 'goal_tasks' => $cfg['weekly_tasks_goal']],
    ]);

} catch (Throwable $e) {
    lp_out(['success' => false, 'error' => 'Server error. Please try again.'], 500);
}