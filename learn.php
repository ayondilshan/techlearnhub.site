<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/learn_lib.php';
require_once __DIR__ . '/db.php';
lp_require_user();

$pageTitle = 'Code Lab';
$uid = lp_uid();
$isAdmin = lp_is_admin();

$tab = $_GET['tab'] ?? 'current';
if (!in_array($tab, ['current', 'recent', 'saved'], true)) {
    $tab = 'current';
}

$setupError = !lp_tables_ready($pdo);
$modules = $tasks = $recent = [];
$module = null;
$moduleNo = 0;
$currentTaskId = 0;
$continueUrl = '';
$goals = [];
$totalPoints = $streak = 0;
$round = null;
$lbTop = [];
$myRank = null;
$prevWinner = null;
$lbDays = 4;

if (!$setupError) {
    $modules = lp_modules($pdo, $uid);

    // ---- which module is open? ----
    $wanted = (int)($_GET['module'] ?? 0);
    foreach ($modules as $i => $m) {
        if ((int)$m['id'] === $wanted) {
            $module = $m;
            $moduleNo = $i + 1;
        }
    }
    if (!$module) {
        foreach ($modules as $i => $m) {          // first unfinished module
            if ((int)$m['done_tasks'] < (int)$m['total_tasks']) {
                $module = $m;
                $moduleNo = $i + 1;
                break;
            }
        }
    }
    if (!$module && $modules) {
        $module = $modules[0];
        $moduleNo = 1;
    }

    if ($module) {
        $tasks = lp_tasks($pdo, (int)$module['id'], $uid);
        foreach ($tasks as $t) {                  // first unfinished task
            if ($t['p_status'] !== 'completed') {
                $currentTaskId = (int)$t['id'];
                break;
            }
        }
        if (!$currentTaskId && $tasks) {
            $currentTaskId = (int)$tasks[0]['id'];
        }
        if ($currentTaskId) {
            $continueUrl = 'learn_task.php?id=' . $currentTaskId;
        }
    }

    if ($tab === 'recent') {
        $st = $pdo->prepare("SELECT t.id, t.title, t.icon, t.language, t.points, m.title AS module_title, p.status, p.last_activity
                             FROM lp_progress p
                             JOIN lp_tasks t ON t.id = p.task_id AND t.is_published = 1
                             JOIN lp_modules m ON m.id = t.module_id AND m.is_published = 1
                             WHERE p.user_id = ?
                             ORDER BY p.last_activity DESC
                             LIMIT 12");
        $st->execute([$uid]);
        $recent = $st->fetchAll();
    }

    // ---- weekly mission ----
    $cfg = lp_settings($pdo);
    $wk = lp_week_stats($pdo, $uid);
    $streak = lp_streak($pdo, $uid);
    $totalPoints = lp_total_points($pdo, $uid);
    $goals = [
        ['label' => 'Earn points',     'icon' => 'target', 'now' => $wk['points'],                 'max' => $cfg['weekly_points_goal']],
        ['label' => 'Complete tasks',  'icon' => 'check',  'now' => $wk['tasks'],                  'max' => $cfg['weekly_tasks_goal']],
        ['label' => 'Go on a ' . $cfg['streak_goal'] . '-day streak', 'icon' => 'flame', 'now' => min($streak, $cfg['streak_goal']), 'max' => $cfg['streak_goal']],
    ];

    // ---- leaderboard (new round every N days) ----
    $lbDays = $cfg['leaderboard_days'];
    $round = lp_round($lbDays);
    $lbAll = lp_leaderboard($pdo, $round['start'], $round['end'], 500);
    $lbTop = array_slice($lbAll, 0, 10);
    foreach ($lbAll as $i => $r) {
        if ((int)$r['id'] === $uid) {
            $myRank = ['rank' => $i + 1, 'score' => (int)$r['score']];
            break;
        }
    }
    $prevWinner = lp_leaderboard($pdo, $round['prev_start'], $round['start'], 1)[0] ?? null;
}

$missionDone = $goals && count(array_filter($goals, fn($g) => $g['now'] >= $g['max'])) === count($goals);
$daysLeft = lp_days_to_next_mission();

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/learn.css?v=2">


<div class="lp" data-view="path">
  <?php require_once __DIR__ . '/includes/alerts.php'; ?>

  <?php if ($setupError): ?>
    <div class="lp-empty">
      <h2>Code Lab needs one-time setup</h2>
      <p>The database tables are missing. Import <code>sql/learning_tool.sql</code> in phpMyAdmin, then reload this page.</p>
    </div>

  <?php else: ?>
  <div class="lp-grid">

    <!-- ================= MAIN COLUMN ================= -->
    <section class="lp-main" aria-label="My learning">
      <header class="lp-head">
        <h1>My Learning</h1>
        <nav class="lp-tabs" aria-label="Learning views">
          <a class="lp-tab <?= $tab === 'current' ? 'is-active' : '' ?>" href="learn.php"<?= $tab === 'current' ? ' aria-current="page"' : '' ?>>Current</a>
          <a class="lp-tab <?= $tab === 'recent' ? 'is-active' : '' ?>" href="learn.php?tab=recent"<?= $tab === 'recent' ? ' aria-current="page"' : '' ?>>Recent</a>
          <a class="lp-tab <?= $tab === 'saved' ? 'is-active' : '' ?>" href="learn.php?tab=saved"<?= $tab === 'saved' ? ' aria-current="page"' : '' ?>>Saved</a>
        </nav>
      </header>

      <?php if (!$modules): ?>
        <div class="lp-empty">
          <h2>No modules yet</h2>
          <p><?= $isAdmin ? 'Add your first module in the admin panel under Code Lab.' : 'New lessons are on the way. Check back soon.' ?></p>
          <?php if ($isAdmin): ?><a class="lp-btn" href="admin/manage_learning.php">Open Code Lab admin</a><?php endif; ?>
        </div>

      <?php elseif ($tab === 'recent'): ?>
        <?php if (!$recent): ?>
          <div class="lp-empty">
            <h2>Nothing here yet</h2>
            <p>Tasks you open will show up here so you can jump back in.</p>
            <a class="lp-btn" href="learn.php">Go to your path</a>
          </div>
        <?php else: ?>
          <ul class="lp-list">
            <?php foreach ($recent as $r): ?>
              <li>
                <a class="lp-row" href="learn_task.php?id=<?= (int)$r['id'] ?>">
                  <span class="lp-row-icon" aria-hidden="true"><?= lp_e($r['icon'] ?: '💻') ?></span>
                  <span class="lp-row-text">
                    <strong><?= lp_e($r['title']) ?></strong>
                    <small><?= lp_e($r['module_title']) ?> &middot; <?= lp_e(date('M j, g:i a', strtotime($r['last_activity']))) ?></small>
                  </span>
                  <span class="lp-lang lp-lang-<?= lp_e($r['language']) ?>"><?= lp_e(LP_LANGS[$r['language']] ?? $r['language']) ?></span>
                  <span class="lp-status <?= $r['status'] === 'completed' ? 'is-done' : '' ?>"><?= $r['status'] === 'completed' ? 'Completed' : 'In progress' ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

      <?php elseif ($tab === 'saved'): ?>
        <?php $saved = array_values(array_filter($modules, fn($m) => (int)$m['is_saved'] > 0)); ?>
        <?php if (!$saved): ?>
          <div class="lp-empty">
            <h2>No saved modules</h2>
            <p>Use the Save button on a module to keep it here for later.</p>
            <a class="lp-btn" href="learn.php">Browse modules</a>
          </div>
        <?php else: ?>
          <ul class="lp-list">
            <?php foreach ($saved as $m): $pct = $m['total_tasks'] ? round(100 * $m['done_tasks'] / $m['total_tasks']) : 0; ?>
              <li>
                <a class="lp-row" href="learn.php?module=<?= (int)$m['id'] ?>">
                  <span class="lp-row-icon" aria-hidden="true"><?= lp_e($m['icon'] ?: '💻') ?></span>
                  <span class="lp-row-text">
                    <strong><?= lp_e($m['title']) ?></strong>
                    <small><?= lp_e($m['unit_label']) ?></small>
                  </span>
                  <span class="lp-status"><?= (int)$m['done_tasks'] ?>/<?= (int)$m['total_tasks'] ?> tasks &middot; <?= $pct ?>%</span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

      <?php else: /* ===== current ===== */ ?>

        <!-- module switcher -->
        <div class="lp-strip" role="list" aria-label="Modules">
          <?php foreach ($modules as $i => $m): ?>
            <a role="listitem" class="lp-chip <?= (int)$m['id'] === (int)$module['id'] ? 'is-active' : '' ?>" href="learn.php?module=<?= (int)$m['id'] ?>">
              <span aria-hidden="true"><?= lp_e($m['icon'] ?: '💻') ?></span>
              <span><?= lp_e($m['title']) ?><br><small><?= (int)$m['done_tasks'] ?>/<?= (int)$m['total_tasks'] ?> done</small></span>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- module banner -->
        <?php $total = (int)$module['total_tasks']; $done = (int)$module['done_tasks']; $pct = $total ? (int)round(100 * $done / $total) : 0; ?>
        <div class="lp-banner">
          <div class="lp-banner-text">
            <small>Module <?= (int)$moduleNo ?></small>
            <h2><?= lp_e($module['title']) ?></h2>
            <?php if ($module['unit_label']): ?><p class="lp-unit"><?= lp_e($module['unit_label']) ?></p><?php endif; ?>
            <div class="lp-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>" aria-label="Module progress">
              <span style="width:<?= $pct ?>%"></span>
            </div>
            <p class="lp-progress-label"><?= $done ?> of <?= $total ?> tasks complete</p>
          </div>
          <div class="lp-banner-art" aria-hidden="true"><?= lp_e($module['icon'] ?: '💻') ?></div>
          <button type="button" class="lp-save" data-module="<?= (int)$module['id'] ?>" aria-pressed="<?= $module['is_saved'] ? 'true' : 'false' ?>">
            <span class="lp-save-icon" aria-hidden="true"><?= $module['is_saved'] ? '★' : '☆' ?></span>
            <span class="lp-save-text"><?= $module['is_saved'] ? 'Saved' : 'Save' ?></span>
          </button>
        </div>

        <?php if (!$tasks): ?>
          <div class="lp-empty">
            <h2>No tasks in this module yet</h2>
            <p>Tasks will appear here as soon as they are published.</p>
          </div>
        <?php else: ?>

          <!-- path view -->
          <div class="lp-pathwrap" id="lpPathWrap">
            <svg class="lp-lines" id="lpLines" aria-hidden="true"></svg>
            <ol class="lp-path" id="lpPath">
              <?php foreach ($tasks as $i => $t):
                $state = $t['p_status'] === 'completed' ? 'done' : ((int)$t['id'] === $currentTaskId ? 'current' : 'todo'); ?>
                <li class="lp-node lp-pos-<?= $i % 4 ?> is-<?= $state ?>" data-state="<?= $state ?>">
                  <a class="lp-node-link" href="learn_task.php?id=<?= (int)$t['id'] ?>"
                     aria-label="<?= lp_e($t['title']) ?>, <?= $state === 'done' ? 'completed' : ($state === 'current' ? 'up next' : 'not started') ?>">
                    <span class="lp-tile-wrap">
                      <span class="lp-tile" aria-hidden="true"></span>
                      <span class="lp-tile-icon" aria-hidden="true"><?= lp_e($t['icon'] ?: '💻') ?></span>
                      <?php if ($state === 'done'): ?><span class="lp-tick" aria-hidden="true">✓</span><?php endif; ?>
                    </span>
                    <span class="lp-node-label">
                      <strong><?= lp_e($t['title']) ?></strong>
                      <small><?= lp_e(LP_LANGS[$t['language']] ?? $t['language']) ?> &middot; <?= (int)$t['points'] ?> pts<?= $t['is_project'] ? ' &middot; Project' : '' ?></small>
                    </span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>

          <!-- classic view -->
          <ul class="lp-list lp-classic" id="lpClassicList">
            <?php foreach ($tasks as $t):
              $state = $t['p_status'] === 'completed' ? 'done' : ((int)$t['id'] === $currentTaskId ? 'current' : 'todo'); ?>
              <li>
                <a class="lp-row is-<?= $state ?>" href="learn_task.php?id=<?= (int)$t['id'] ?>">
                  <span class="lp-row-icon" aria-hidden="true"><?= lp_e($t['icon'] ?: '💻') ?></span>
                  <span class="lp-row-text">
                    <strong><?= lp_e($t['title']) ?></strong>
                    <small><?= lp_e($t['summary']) ?></small>
                  </span>
                  <span class="lp-lang lp-lang-<?= lp_e($t['language']) ?>"><?= lp_e(LP_LANGS[$t['language']] ?? $t['language']) ?></span>
                  <span class="lp-pts"><?= (int)$t['points'] ?> pts</span>
                  <span class="lp-status <?= $state === 'done' ? 'is-done' : '' ?>"><?= $state === 'done' ? 'Completed' : ($state === 'current' ? 'Up next' : 'Not started') ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="lp-foot">
            <label class="lp-switch">
              <input type="checkbox" id="lpClassic">
              <span class="lp-switch-ui" aria-hidden="true"></span>
              <span>Classic view</span>
            </label>
            <?php if ($continueUrl): ?>
              <a class="lp-btn" href="<?= lp_e($continueUrl) ?>"><?= $done > 0 && $done < $total ? 'Continue' : ($done >= $total ? 'Review' : 'Start') ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <!-- ================= WEEKLY MISSION ================= -->
    <aside class="lp-side" aria-label="Weekly mission">
      <div class="lp-timer"><?= lp_icon_svg('clock') ?><span><?= $daysLeft ?> day<?= $daysLeft === 1 ? '' : 's' ?> to next mission</span></div>
      <div class="lp-wk">
        <div class="lp-wk-head">
          <span class="lp-wk-art" aria-hidden="true"><?= $missionDone ? '🏅' : '🎯' ?></span>
          <div>
            <h2>Weekly Mission</h2>
            <p><?= $missionDone ? 'Mission complete. Great week!' : 'Hit all three goals before the week resets.' ?></p>
          </div>
        </div>
        <?php foreach ($goals as $g): $p = $g['max'] ? min(100, (int)round(100 * $g['now'] / $g['max'])) : 0; ?>
          <div class="lp-goal <?= $g['now'] >= $g['max'] ? 'is-met' : '' ?>">
            <div class="lp-goal-top">
              <span><?= lp_e($g['label']) ?></span>
              <span class="lp-goal-num"><?= (int)$g['now'] ?> / <?= (int)$g['max'] ?></span>
            </div>
            <div class="lp-goal-bar">
              <?= lp_icon_svg($g['icon']) ?>
              <div class="lp-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $p ?>" aria-label="<?= lp_e($g['label']) ?>"><span style="width:<?= $p ?>%"></span></div>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="lp-wk-total">
          <span><strong><?= (int)$totalPoints ?></strong> points earned</span>
          <span><?= lp_icon_svg('flame') ?> <strong><?= (int)$streak ?></strong>-day streak</span>
        </div>
      </div>
      <?php if ($isAdmin): ?>
        <p class="lp-note">Admin preview: your own progress is not saved.</p>
      <?php endif; ?>
      <?php if ($round): ?>
      <div class="lp-lb" aria-label="Leaderboard">
        <div class="lp-lb-head">
          <span class="lp-lb-art" aria-hidden="true">🏆</span>
          <div>
            <h2>Leaderboard</h2>
            <p>New round every <?= (int)$lbDays ?> days &middot; ends in <?= lp_e(lp_time_left($round['seconds_left'])) ?></p>
          </div>
        </div>
        <?php if (!$lbTop): ?>
          <p class="lp-lb-empty">No scores yet this round. Complete a task to take the top spot.</p>
        <?php else: ?>
          <ol class="lp-lb-list">
            <?php foreach ($lbTop as $i => $r): $medal = [0 => '🥇', 1 => '🥈', 2 => '🥉'][$i] ?? null; ?>
              <li class="lp-lb-row <?= (int)$r['id'] === $uid ? 'is-me' : '' ?>">
                <span class="lp-lb-rank"><?= $medal ?: '#' . ($i + 1) ?></span>
                <span class="lp-lb-name"><?= lp_e(lp_display_name($r['first_name'])) ?><?= (int)$r['id'] === $uid ? ' (you)' : '' ?></span>
                <span class="lp-lb-score"><?= (int)$r['score'] ?> pts</span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
        <?php if ($myRank && $myRank['rank'] > 10): ?>
          <p class="lp-lb-me">Your rank: <strong>#<?= (int)$myRank['rank'] ?></strong> &middot; <?= (int)$myRank['score'] ?> pts</p>
        <?php elseif (!$myRank && !$isAdmin): ?>
          <p class="lp-lb-me">You have no points this round yet.</p>
        <?php endif; ?>
        <?php if ($prevWinner): ?>
          <p class="lp-lb-prev">Last round winner: <strong><?= lp_e(lp_display_name($prevWinner['first_name'])) ?></strong> &middot; <?= (int)$prevWinner['score'] ?> pts</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </aside>

  </div>
  <?php endif; ?>
</div>

<script>window.LP_PAGE = { api: <?= lp_json(BASE_URL . 'api/learn_progress.php') ?> };</script>
<script src="<?= BASE_URL ?>assets/js/learn_path.js?v=3"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>