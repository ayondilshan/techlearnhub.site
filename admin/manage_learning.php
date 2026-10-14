<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_admin();
require_once __DIR__ . '/../includes/learn_lib.php';
$pageTitle = 'Manage Code Lab';

$ready = lp_tables_ready($pdo);

function lp_back(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* =========================================================
   POST actions
   ========================================================= */
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect = 'manage_learning.php';

    try {
        if ($action === 'save_settings') {
            $limits = ['weekly_points_goal' => 10000, 'weekly_tasks_goal' => 100, 'streak_goal' => 30, 'leaderboard_days' => 30];
            $up = $pdo->prepare("INSERT INTO lp_settings (setting_key, setting_value) VALUES (?, ?)
                                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($limits as $key => $max) {
                $up->execute([$key, (string)max(1, min($max, (int)($_POST[$key] ?? 1)))]);
            }
            set_flash('success', 'Goals and leaderboard settings updated.');

        } elseif ($action === 'save_module') {
            $id    = (int)($_POST['id'] ?? 0);
            $title = mb_substr(trim($_POST['title'] ?? ''), 0, 150);
            $desc  = mb_substr(trim($_POST['description'] ?? ''), 0, 300);
            $unit  = mb_substr(trim($_POST['unit_label'] ?? ''), 0, 120);
            $icon  = mb_substr(trim($_POST['icon'] ?? ''), 0, 8) ?: '💻';
            $order = trim($_POST['sort_order'] ?? '');
            $pub   = isset($_POST['is_published']) ? 1 : 0;

            if ($title === '') {
                set_flash('error', 'Module title is required.');
                lp_back($id ? "manage_learning.php?edit_module=$id" : $redirect);
            }
            if ($order === '') {
                $order = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lp_modules")->fetchColumn();
            }
            if ($id) {
                $pdo->prepare("UPDATE lp_modules SET title=?, description=?, unit_label=?, icon=?, sort_order=?, is_published=? WHERE id=?")
                    ->execute([$title, $desc, $unit, $icon, (int)$order, $pub, $id]);
                set_flash('success', 'Module updated.');
            } else {
                $pdo->prepare("INSERT INTO lp_modules (title, description, unit_label, icon, sort_order, is_published) VALUES (?,?,?,?,?,?)")
                    ->execute([$title, $desc, $unit, $icon, (int)$order, $pub]);
                $id = (int)$pdo->lastInsertId();
                set_flash('success', 'Module added. Now add its tasks below.');
                $redirect = "manage_learning.php?module=$id#tasks";
            }

        } elseif ($action === 'delete_module') {
            $pdo->prepare("DELETE FROM lp_modules WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
            set_flash('success', 'Module deleted together with its tasks and student progress.');

        } elseif ($action === 'save_task') {
            $id       = (int)($_POST['id'] ?? 0);
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $title    = mb_substr(trim($_POST['title'] ?? ''), 0, 150);
            $summary  = mb_substr(trim($_POST['summary'] ?? ''), 0, 255);
            $icon     = mb_substr(trim($_POST['icon'] ?? ''), 0, 8) ?: '💻';
            $lang     = $_POST['language'] ?? 'html';
            $points   = max(0, min(1000, (int)($_POST['points'] ?? 10)));
            $order    = trim($_POST['sort_order'] ?? '');
            $isProj   = isset($_POST['is_project']) ? 1 : 0;
            $pub      = isset($_POST['is_published']) ? 1 : 0;
            $instr    = (string)($_POST['instructions'] ?? '');
            $starter  = (string)($_POST['starter_code'] ?? '');
            $hint     = (string)($_POST['hint'] ?? '');
            $checks   = lp_clean_checks($_POST['checks_json'] ?? '[]');

            $redirect = "manage_learning.php?module=$moduleId#tasks";
            if (!isset(LP_LANGS[$lang])) {
                $lang = 'html';
            }
            $exists = $pdo->prepare("SELECT id FROM lp_modules WHERE id = ?");
            $exists->execute([$moduleId]);
            if (!$exists->fetch()) {
                set_flash('error', 'Pick a module first.');
                lp_back('manage_learning.php');
            }
            if ($title === '') {
                set_flash('error', 'Task title is required.');
                lp_back($id ? "manage_learning.php?edit_task=$id" : $redirect);
            }
            if ($order === '') {
                $q = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lp_tasks WHERE module_id = ?");
                $q->execute([$moduleId]);
                $order = (int)$q->fetchColumn();
            }
            if ($id) {
                $pdo->prepare("UPDATE lp_tasks SET module_id=?, title=?, summary=?, icon=?, language=?, instructions=?, starter_code=?, hint=?, checks=?, points=?, sort_order=?, is_project=?, is_published=? WHERE id=?")
                    ->execute([$moduleId, $title, $summary, $icon, $lang, $instr, $starter, $hint, $checks, $points, (int)$order, $isProj, $pub, $id]);
                set_flash('success', 'Task updated.');
            } else {
                $pdo->prepare("INSERT INTO lp_tasks (module_id, title, summary, icon, language, instructions, starter_code, hint, checks, points, sort_order, is_project, is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$moduleId, $title, $summary, $icon, $lang, $instr, $starter, $hint, $checks, $points, (int)$order, $isProj, $pub]);
                set_flash('success', 'Task added.');
            }

        } elseif ($action === 'delete_task') {
            $tid = (int)($_POST['id'] ?? 0);
            $mid = (int)($_POST['module_id'] ?? 0);
            $pdo->prepare("DELETE FROM lp_tasks WHERE id = ?")->execute([$tid]);
            set_flash('success', 'Task deleted.');
            $redirect = "manage_learning.php?module=$mid#tasks";
        }
    } catch (Throwable $e) {
        set_flash('error', 'Database error: ' . $e->getMessage());
    }
    lp_back($redirect);
}

/* =========================================================
   Page data
   ========================================================= */
$modules = $tasks = [];
$editModule = $editTask = null;
$moduleId = 0;
$currentModule = null;
$settings = ['weekly_points_goal' => 100, 'weekly_tasks_goal' => 5, 'streak_goal' => 2, 'leaderboard_days' => 4];

if ($ready) {
    $settings = lp_settings($pdo);
    $modules = $pdo->query("SELECT m.*,
            (SELECT COUNT(*) FROM lp_tasks t WHERE t.module_id = m.id) AS task_count,
            (SELECT COUNT(*) FROM lp_progress p JOIN lp_tasks t ON t.id = p.task_id WHERE t.module_id = m.id AND p.status = 'completed') AS completions
        FROM lp_modules m ORDER BY m.sort_order, m.id")->fetchAll();

    if (!empty($_GET['edit_module'])) {
        $s = $pdo->prepare("SELECT * FROM lp_modules WHERE id = ?");
        $s->execute([(int)$_GET['edit_module']]);
        $editModule = $s->fetch() ?: null;
    }
    if (!empty($_GET['edit_task'])) {
        $s = $pdo->prepare("SELECT * FROM lp_tasks WHERE id = ?");
        $s->execute([(int)$_GET['edit_task']]);
        $editTask = $s->fetch() ?: null;
        if ($editTask) {
            $moduleId = (int)$editTask['module_id'];
        }
    }
    if (!$moduleId) {
        $moduleId = (int)($_GET['module'] ?? 0);
    }
    foreach ($modules as $m) {
        if ((int)$m['id'] === $moduleId) {
            $currentModule = $m;
        }
    }
    if ($currentModule) {
        $s = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM lp_progress p WHERE p.task_id = t.id AND p.status = 'completed') AS done_count
                            FROM lp_tasks t WHERE t.module_id = ? ORDER BY t.sort_order, t.id");
        $s->execute([$moduleId]);
        $tasks = $s->fetchAll();
    } else {
        $moduleId = 0;
    }
}

$initChecks = [];
if ($editTask) {
    $dec = json_decode((string)$editTask['checks'], true);
    $initChecks = is_array($dec) ? $dec : [];
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .lpa-box { max-width: 100%; margin: 20px 0; }
  .lpa-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
  .lpa-grid .form-group { margin-bottom: 0; }
  .lpa-row { display: flex; gap: 20px; flex-wrap: wrap; align-items: center; margin: 14px 0; }
  .lpa-row label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
  .lpa-row input[type=checkbox] { width: auto; }
  .lpa-mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px; }
  .lpa-help { font-size: .85rem; color: #6b7280; margin-top: 4px; }
  .lpa-actions a, .lpa-actions button { margin-right: 8px; }
  .lpa-link { background: none; border: 0; padding: 0; font: inherit; color: var(--error); cursor: pointer; text-decoration: underline; }
  .lpa-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; background: #e5e7eb; }
  .lpa-pill.on { background: #dcfce7; color: #166534; }
  .lpa-pill.off { background: #fee2e2; color: #991b1b; }
  .chk-row { display: grid; grid-template-columns: 170px 1fr 1fr 1fr 1.2fr 36px; gap: 8px; align-items: start; padding: 10px; margin-bottom: 8px; background: rgba(120,120,120,.07); border-radius: 8px; }
  .chk-row select, .chk-row input, .chk-row textarea { width: 100%; margin: 0; font-size: 13px; }
  .chk-row [hidden] { display: none !important; }
  .chk-remove { height: 34px; border: 0; border-radius: 6px; cursor: pointer; background: #fee2e2; color: #991b1b; font-weight: 700; }
  @media (max-width: 900px) { .chk-row { grid-template-columns: 1fr; } .chk-remove { width: 100%; } }
</style>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <section>
    <h1>Manage Code Lab</h1>
    <?php require_once __DIR__ . '/../includes/alerts.php'; ?>

<?php if (!$ready): ?>
    <div class="form-box lpa-box">
      <h2>One-time setup needed</h2>
      <p>The Code Lab tables do not exist yet. Import <strong>sql/learning_tool.sql</strong> in phpMyAdmin (Import tab), then reload this page.</p>
    </div>

<?php else: ?>

    <!-- ================= Weekly mission goals ================= -->
    <div class="form-box lpa-box">
      <h2>Weekly mission and leaderboard</h2>
      <form method="post">
        <input type="hidden" name="action" value="save_settings">
        <div class="lpa-grid">
          <div class="form-group"><label>Points to earn each week</label><input type="number" name="weekly_points_goal" min="1" value="<?= (int)$settings['weekly_points_goal'] ?>"></div>
          <div class="form-group"><label>Tasks to complete each week</label><input type="number" name="weekly_tasks_goal" min="1" value="<?= (int)$settings['weekly_tasks_goal'] ?>"></div>
          <div class="form-group"><label>Streak goal (days in a row)</label><input type="number" name="streak_goal" min="1" max="30" value="<?= (int)$settings['streak_goal'] ?>"></div>
          <div class="form-group"><label>Leaderboard resets every (days)</label><input type="number" name="leaderboard_days" min="1" max="30" value="<?= (int)$settings['leaderboard_days'] ?>"></div>
        </div>
        <p class="lpa-help">The week runs Monday to Sunday. Students see these goals in the Weekly Mission card. The leaderboard ranks points earned in the current round and starts fresh when the round ends.</p>
        <button type="submit" class="btn" style="margin-top:12px;">Save settings</button>
      </form>
    </div>

    <!-- ================= Modules ================= -->
    <h2 style="margin:24px 0 12px;">Modules</h2>
    <table>
      <tr><th>Order</th><th></th><th>Module</th><th>Syllabus unit</th><th>Tasks</th><th>Completions</th><th>Status</th><th></th></tr>
      <?php foreach ($modules as $m): ?>
      <tr>
        <td><?= (int)$m['sort_order'] ?></td>
        <td><?= lp_e($m['icon']) ?></td>
        <td><strong><?= lp_e($m['title']) ?></strong></td>
        <td><?= lp_e($m['unit_label']) ?></td>
        <td><?= (int)$m['task_count'] ?></td>
        <td><?= (int)$m['completions'] ?></td>
        <td><span class="lpa-pill <?= $m['is_published'] ? 'on' : 'off' ?>"><?= $m['is_published'] ? 'Published' : 'Hidden' ?></span></td>
        <td class="lpa-actions">
          <a href="?module=<?= (int)$m['id'] ?>#tasks">Tasks</a>
          <a href="?edit_module=<?= (int)$m['id'] ?>#module-form">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this module, all its tasks and every student\'s progress on them?')">
            <input type="hidden" name="action" value="delete_module">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="lpa-link">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$modules): ?><tr><td colspan="8">No modules yet. Add the first one below.</td></tr><?php endif; ?>
    </table>

    <div class="form-box lpa-box" id="module-form">
      <h2><?= $editModule ? 'Edit module' : 'Add module' ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="save_module">
        <input type="hidden" name="id" value="<?= (int)($editModule['id'] ?? 0) ?>">
        <div class="lpa-grid">
          <div class="form-group"><label>Title</label><input type="text" name="title" required maxlength="150" value="<?= lp_e($editModule['title'] ?? '') ?>"></div>
          <div class="form-group"><label>Syllabus unit label</label><input type="text" name="unit_label" maxlength="120" placeholder="A/L ICT · Unit 9 · Programming" value="<?= lp_e($editModule['unit_label'] ?? '') ?>"></div>
          <div class="form-group"><label>Icon (one emoji)</label><input type="text" name="icon" maxlength="8" placeholder="🐍" value="<?= lp_e($editModule['icon'] ?? '') ?>"></div>
          <div class="form-group"><label>Order (blank = last)</label><input type="number" name="sort_order" value="<?= isset($editModule['sort_order']) ? (int)$editModule['sort_order'] : '' ?>"></div>
        </div>
        <div class="form-group" style="margin-top:14px;"><label>Short description</label><textarea name="description" rows="2" maxlength="300"><?= lp_e($editModule['description'] ?? '') ?></textarea></div>
        <div class="lpa-row">
          <label><input type="checkbox" name="is_published" value="1" <?= !$editModule || $editModule['is_published'] ? 'checked' : '' ?>> Published (visible to students)</label>
        </div>
        <button type="submit" class="btn"><?= $editModule ? 'Save module' : 'Add module' ?></button>
        <?php if ($editModule): ?><a href="manage_learning.php" style="margin-left:12px;">Cancel</a><?php endif; ?>
      </form>
    </div>

    <!-- ================= Tasks ================= -->
    <h2 id="tasks" style="margin:32px 0 12px;">Tasks</h2>
    <?php if (!$currentModule): ?>
      <p>Choose <strong>Tasks</strong> next to a module above to add or edit its tasks.</p>
    <?php else: ?>
      <p style="margin-bottom:12px;">Module: <strong><?= lp_e($currentModule['icon']) ?> <?= lp_e($currentModule['title']) ?></strong></p>
      <table>
        <tr><th>Order</th><th></th><th>Task</th><th>Language</th><th>Points</th><th>Done by</th><th>Status</th><th></th></tr>
        <?php foreach ($tasks as $t): ?>
        <tr>
          <td><?= (int)$t['sort_order'] ?></td>
          <td><?= lp_e($t['icon']) ?></td>
          <td><strong><?= lp_e($t['title']) ?></strong><?= $t['is_project'] ? ' <span class="lpa-pill on">Project</span>' : '' ?><br><small><?= lp_e($t['summary']) ?></small></td>
          <td><?= lp_e(LP_LANGS[$t['language']] ?? $t['language']) ?></td>
          <td><?= (int)$t['points'] ?></td>
          <td><?= (int)$t['done_count'] ?></td>
          <td><span class="lpa-pill <?= $t['is_published'] ? 'on' : 'off' ?>"><?= $t['is_published'] ? 'Published' : 'Hidden' ?></span></td>
          <td class="lpa-actions">
            <a href="?edit_task=<?= (int)$t['id'] ?>#task-form">Edit</a>
            <a href="../learn_task.php?id=<?= (int)$t['id'] ?>" target="_blank" rel="noopener">Preview</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this task and student progress on it?')">
              <input type="hidden" name="action" value="delete_task">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <input type="hidden" name="module_id" value="<?= (int)$moduleId ?>">
              <button type="submit" class="lpa-link">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><tr><td colspan="8">No tasks in this module yet.</td></tr><?php endif; ?>
      </table>

      <div class="form-box lpa-box" id="task-form">
        <h2><?= $editTask ? 'Edit task' : 'Add task' ?></h2>
        <form method="post" id="taskForm">
          <input type="hidden" name="action" value="save_task">
          <input type="hidden" name="id" value="<?= (int)($editTask['id'] ?? 0) ?>">
          <input type="hidden" name="module_id" value="<?= (int)$moduleId ?>">
          <input type="hidden" name="checks_json" id="checksJson" value="[]">

          <div class="lpa-grid">
            <div class="form-group"><label>Title</label><input type="text" name="title" required maxlength="150" value="<?= lp_e($editTask['title'] ?? '') ?>"></div>
            <div class="form-group"><label>Language</label>
              <select name="language">
                <?php foreach (LP_LANGS as $k => $label): ?>
                  <option value="<?= lp_e($k) ?>" <?= ($editTask['language'] ?? 'html') === $k ? 'selected' : '' ?>><?= lp_e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Points</label><input type="number" name="points" min="0" max="1000" value="<?= (int)($editTask['points'] ?? 10) ?>"></div>
            <div class="form-group"><label>Icon (one emoji)</label><input type="text" name="icon" maxlength="8" placeholder="🧮" value="<?= lp_e($editTask['icon'] ?? '') ?>"></div>
            <div class="form-group"><label>Order (blank = last)</label><input type="number" name="sort_order" value="<?= isset($editTask['sort_order']) ? (int)$editTask['sort_order'] : '' ?>"></div>
          </div>

          <div class="form-group" style="margin-top:14px;"><label>Short summary (shown in the classic list)</label><input type="text" name="summary" maxlength="255" value="<?= lp_e($editTask['summary'] ?? '') ?>"></div>

          <div class="form-group">
            <label>Instructions</label>
            <textarea name="instructions" rows="10" class="lpa-mono"><?= lp_e($editTask['instructions'] ?? '') ?></textarea>
            <p class="lpa-help">Blank line = new paragraph. Lines starting with <code>- </code> make a bullet list, <code>1. </code> a numbered list. Wrap code in `backticks`, bold in **stars**. Use three backticks on their own line to start and end a code block.</p>
          </div>

          <div class="form-group">
            <label>Starter code (what the student sees first)</label>
            <textarea name="starter_code" rows="10" class="lpa-mono" spellcheck="false"><?= lp_e($editTask['starter_code'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label>Hint (optional)</label>
            <textarea name="hint" rows="2"><?= lp_e($editTask['hint'] ?? '') ?></textarea>
          </div>

          <h3 style="margin:22px 0 6px;">Requirements (automatic checks)</h3>
          <p class="lpa-help" style="margin-bottom:10px;">The student passes when <strong>every</strong> requirement is met. The message is what the student sees in their checklist.</p>
          <details style="margin-bottom:12px;">
            <summary style="cursor:pointer;">What does each check type do?</summary>
            <ul style="margin:8px 0 0 20px; font-size:.9rem; line-height:1.6;">
              <li><strong>Code contains</strong>: the text must appear in the code (not case sensitive).</li>
              <li><strong>Code matches pattern</strong>: a regular expression, good for PHP and for flexible spacing.</li>
              <li><strong>Element exists</strong> (HTML/CSS): a CSS selector such as <code>h1</code> or <code>nav a</code> must match something on the page.</li>
              <li><strong>Element count at least</strong>: for example selector <code>ul li</code> and value <code>3</code>.</li>
              <li><strong>Element text contains</strong>: leave the value empty to only require that the element is not empty.</li>
              <li><strong>Element style equals</strong>: selector, CSS property (for example <code>color</code>) and the expected value. Colours can be written as names or hex. Use px for sizes, not em or %.</li>
              <li><strong>Python output equals / contains</strong>: the program runs with the "Program input" text (one value per line) and its output is compared.</li>
            </ul>
          </details>

          <div id="checkRows"></div>
          <button type="button" class="btn" id="addCheck" style="background:#4b5563;">+ Add requirement</button>

          <div class="lpa-row" style="margin-top:20px;">
            <label><input type="checkbox" name="is_project" value="1" <?= !empty($editTask['is_project']) ? 'checked' : '' ?>> Syllabus project (shows a Project tag)</label>
            <label><input type="checkbox" name="is_published" value="1" <?= !$editTask || $editTask['is_published'] ? 'checked' : '' ?>> Published</label>
          </div>
          <button type="submit" class="btn"><?= $editTask ? 'Save task' : 'Add task' ?></button>
          <?php if ($editTask): ?><a href="?module=<?= (int)$moduleId ?>#tasks" style="margin-left:12px;">Cancel</a><?php endif; ?>
        </form>
      </div>

      <script type="application/json" id="checksInit"><?= lp_json($initChecks) ?></script>
      <script>
      (function () {
        var TYPES = {
          code_contains:     { label: 'Code contains',            sel: null,               val: 'Text the code must contain', extra: null },
          code_regex:        { label: 'Code matches pattern',     sel: null,               val: 'Regular expression (not case sensitive)', extra: null },
          dom_exists:        { label: 'Element exists',           sel: 'CSS selector, e.g. h1', val: null, extra: null },
          dom_count_min:     { label: 'Element count at least',   sel: 'CSS selector, e.g. ul li', val: 'Minimum count, e.g. 3', extra: null },
          dom_text_contains: { label: 'Element text contains',    sel: 'CSS selector', val: 'Text inside (empty = any text)', extra: null },
          dom_style:         { label: 'Element style equals',     sel: 'CSS selector', val: 'Expected value, e.g. #ff0000', extra: 'CSS property, e.g. color' },
          output_equals:     { label: 'Python output equals',     sel: null,               val: 'Expected output', extra: 'Program input (one value per line)' },
          output_contains:   { label: 'Python output contains',   sel: null,               val: 'Text the output must contain', extra: 'Program input (one value per line)' }
        };
        var box = document.getElementById('checkRows');
        var json = document.getElementById('checksJson');

        function apply(row) {
          var m = TYPES[row.querySelector('.c-type').value];
          [['.c-sel', 'sel'], ['.c-val', 'val'], ['.c-extra', 'extra']].forEach(function (p) {
            var f = row.querySelector(p[0]);
            f.hidden = m[p[1]] === null;
            f.placeholder = m[p[1]] || '';
            f.setAttribute('aria-label', m[p[1]] || '');
          });
        }

        function addRow(c) {
          c = c || { type: 'code_contains', selector: '', value: '', extra: '', message: '' };
          var row = document.createElement('div');
          row.className = 'chk-row';
          var opts = Object.keys(TYPES).map(function (k) { return '<option value="' + k + '">' + TYPES[k].label + '</option>'; }).join('');
          row.innerHTML =
            '<select class="c-type" aria-label="Check type">' + opts + '</select>' +
            '<input class="c-sel" type="text">' +
            '<textarea class="c-val" rows="2"></textarea>' +
            '<textarea class="c-extra" rows="2"></textarea>' +
            '<input class="c-msg" type="text" placeholder="Message the student sees" aria-label="Message the student sees">' +
            '<button type="button" class="chk-remove" aria-label="Remove requirement">✕</button>';
          row.querySelector('.c-type').value = TYPES[c.type] ? c.type : 'code_contains';
          row.querySelector('.c-sel').value = c.selector || '';
          row.querySelector('.c-val').value = c.value || '';
          row.querySelector('.c-extra').value = c.extra || '';
          row.querySelector('.c-msg').value = c.message || '';
          row.querySelector('.c-type').addEventListener('change', function () { apply(row); });
          row.querySelector('.chk-remove').addEventListener('click', function () { row.remove(); });
          box.appendChild(row);
          apply(row);
        }

        var init = [];
        try { init = JSON.parse(document.getElementById('checksInit').textContent) || []; } catch (e) {}
        init.forEach(addRow);
        document.getElementById('addCheck').addEventListener('click', function () { addRow(); });

        document.getElementById('taskForm').addEventListener('submit', function () {
          var out = [];
          box.querySelectorAll('.chk-row').forEach(function (row) {
            out.push({
              type: row.querySelector('.c-type').value,
              selector: row.querySelector('.c-sel').value,
              value: row.querySelector('.c-val').value,
              extra: row.querySelector('.c-extra').value,
              message: row.querySelector('.c-msg').value
            });
          });
          json.value = JSON.stringify(out);
        });
      })();
      </script>
    <?php endif; ?>

<?php endif; ?>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
