<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/learn_lib.php';
require_once __DIR__ . '//db.php';
lp_require_user();

$uid = lp_uid();
$isAdmin = lp_is_admin();
$id = (int)($_GET['id'] ?? 0);

if (!lp_tables_ready($pdo)) {
    header('Location: learn.php');
    exit;
}

$st = $pdo->prepare("SELECT t.*, m.title AS module_title, m.is_published AS module_published
                     FROM lp_tasks t JOIN lp_modules m ON m.id = t.module_id
                     WHERE t.id = ?");
$st->execute([$id]);
$task = $st->fetch();

if (!$task || (!$isAdmin && (!$task['is_published'] || !$task['module_published']))) {
    set_flash('error', 'That task is not available.');
    header('Location: learn.php');
    exit;
}

// previous / next task inside the same module
$siblings = lp_tasks($pdo, (int)$task['module_id'], $uid, $isAdmin);
$prevUrl = $nextUrl = '';
foreach ($siblings as $i => $s) {
    if ((int)$s['id'] === $id) {
        if ($i > 0) {
            $prevUrl = 'learn_task.php?id=' . (int)$siblings[$i - 1]['id'];
        }
        if ($i < count($siblings) - 1) {
            $nextUrl = 'learn_task.php?id=' . (int)$siblings[$i + 1]['id'];
        }
    }
}
$position = 0;
foreach ($siblings as $i => $s) {
    if ((int)$s['id'] === $id) {
        $position = $i + 1;
    }
}

// progress for this student
$progress = null;
if (!$isAdmin) {
    $p = $pdo->prepare("SELECT status, code FROM lp_progress WHERE user_id = ? AND task_id = ?");
    $p->execute([$uid, $id]);
    $progress = $p->fetch() ?: null;
    $pdo->prepare("INSERT INTO lp_progress (user_id, task_id, status, last_activity) VALUES (?, ?, 'started', ?)
                   ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)")
        ->execute([$uid, $id, date('Y-m-d H:i:s')]);
}

$lang = $task['language'];
$isWeb = in_array($lang, ['html', 'css'], true);
$fileName = ['html' => 'index.html', 'css' => 'index.html', 'php' => 'index.php', 'python' => 'main.py'][$lang] ?? 'code.txt';
$checks = json_decode((string)$task['checks'], true);
if (!is_array($checks)) {
    $checks = [];
}

$cfg = [
    'taskId'    => (int)$task['id'],
    'language'  => $lang,
    'checks'    => $checks,
    'starter'   => (string)$task['starter_code'],
    'saved'     => $progress['code'] ?? null,
    'completed' => ($progress['status'] ?? '') === 'completed',
    'points'    => (int)$task['points'],
    'nextUrl'   => $nextUrl,
    'backUrl'   => 'learn.php?module=' . (int)$task['module_id'],
    'api'       => BASE_URL . 'api/learn_progress.php',
    'preview'   => $isAdmin,
];

$pageTitle = $task['title'];
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/learn.css?v=2">


<div class="lp lp-work">
  <?php require_once __DIR__ . '/includes/alerts.php'; ?>

  <div class="lp-work-top">
    <a class="lp-back" href="<?= lp_e($cfg['backUrl']) ?>">&lsaquo; <?= lp_e($task['module_title']) ?></a>
    <div class="lp-work-title">
      <h1><?= lp_e($task['title']) ?></h1>
      <span class="lp-lang lp-lang-<?= lp_e($lang) ?>"><?= lp_e(LP_LANGS[$lang] ?? $lang) ?></span>
      <span class="lp-pts"><?= (int)$task['points'] ?> pts</span>
      <?php if ($task['is_project']): ?><span class="lp-tag">Project</span><?php endif; ?>
      <?php if ($cfg['completed']): ?><span class="lp-status is-done">Completed</span><?php endif; ?>
    </div>
    <div class="lp-work-nav" aria-label="Task navigation">
      <?php if ($prevUrl): ?><a class="lp-btn lp-btn-ghost" href="<?= lp_e($prevUrl) ?>" aria-label="Previous task">&lsaquo;</a><?php endif; ?>
      <span class="lp-work-count"><?= $position ?> / <?= count($siblings) ?></span>
      <?php if ($nextUrl): ?><a class="lp-btn lp-btn-ghost" href="<?= lp_e($nextUrl) ?>" aria-label="Next task">&rsaquo;</a><?php endif; ?>
    </div>
  </div>

  <?php if ($isAdmin): ?><p class="lp-note">Admin preview: you can try the task and its checks, but nothing is saved.</p><?php endif; ?>

  <div class="lp-work-grid">

    <!-- ========== left: instructions + requirements ========== -->
    <section class="lp-panel lp-instructions" aria-label="Task instructions">
      <div class="lp-rendered"><?= lp_render_text((string)$task['instructions']) ?></div>

      <?php if (trim((string)$task['hint']) !== ''): ?>
        <details class="lp-hint">
          <summary>Need a hint?</summary>
          <div class="lp-rendered"><?= lp_render_text((string)$task['hint']) ?></div>
        </details>
      <?php endif; ?>

      <h2 class="lp-h2">Requirements</h2>
      <ul class="lp-checklist" id="lpChecklist"></ul>
      <div class="lp-result" id="lpResult" role="status" aria-live="polite"></div>
    </section>

    <!-- ========== right: editor + output ========== -->
    <section class="lp-panel lp-editor-panel" aria-label="Code editor">
      <div class="lp-toolbar">
        <span class="lp-file"><?= lp_e($fileName) ?></span>
        <span class="lp-toolbar-space"></span>
        <button type="button" class="lp-btn lp-btn-ghost" id="lpReset">Reset</button>
        <?php if ($lang === 'python'): ?><button type="button" class="lp-btn lp-btn-ghost" id="lpRun">Run</button><?php endif; ?>
        <button type="button" class="lp-btn lp-btn-solid" id="lpCheck">Check answer</button>
      </div>

      <textarea id="lpCode" spellcheck="false" aria-label="Code editor"><?= lp_e($progress['code'] ?? $task['starter_code']) ?></textarea>
      <p class="lp-kbd-tip">Tip: press <kbd>Ctrl</kbd> + <kbd>Enter</kbd> to <?= $lang === 'python' ? 'run your code' : 'check your answer' ?>.</p>

      <?php if ($isWeb): ?>
        <div class="lp-out">
          <div class="lp-out-head">Live preview</div>
          <iframe id="lpPreview" title="Live preview of your page" sandbox="allow-scripts allow-forms allow-modals"></iframe>
        </div>
        <div class="lp-checker-host" aria-hidden="true" id="lpCheckerHost"></div>

      <?php elseif ($lang === 'python'): ?>
        <div class="lp-out">
          <div class="lp-out-head">Console <span class="lp-engine" id="lpEngine">Loading Python...</span></div>
          <pre id="lpConsole" aria-live="polite">Press Run to see your output here.</pre>
          <details class="lp-stdin">
            <summary>Program input (one line for each input())</summary>
            <textarea id="lpStdin" rows="3" spellcheck="false" placeholder="Type the values your program will read, one per line"></textarea>
          </details>
        </div>

      <?php else: ?>
        <div class="lp-out">
          <div class="lp-out-head">How PHP tasks are checked</div>
          <p class="lp-out-note">PHP runs on a server, so it cannot run inside the browser. When you press <strong>Check answer</strong>, your code is read for each requirement on the left. Test your finished script in XAMPP or on your own server as well.</p>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>window.LP_TASK = <?= lp_json($cfg) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closetag.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/learn_workspace.js?v=2"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
