<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_admin();
$pageTitle = 'Manage Subjects';

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([(int)$_GET['delete']]);
    set_flash('success', 'Subject deleted.');
    header('Location: manage_subjects.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($code && $name) {
        $stmt = $pdo->prepare("INSERT INTO subjects (code, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$code, $name, $description]);
        set_flash('success', 'Subject added.');
    } else {
        set_flash('error', 'Code and name are required.');
    }
    header('Location: manage_subjects.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
?>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <section>
    <h1>Manage Subjects</h1>
    <?php require_once __DIR__ . '/../includes/alerts.php'; ?>

    <div class="form-box" style="max-width:100%; margin:20px 0;">
      <h2>Add Subject</h2>
      <form method="post">
        <div class="form-group"><label>Code (e.g. SFT)</label><input type="text" name="code" required></div>
        <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Description</label><textarea name="description" rows="2"></textarea></div>
        <button type="submit" class="btn">Add Subject</button>
      </form>
    </div>

    <table>
      <tr><th>Code</th><th>Name</th><th>Description</th><th></th></tr>
      <?php foreach ($subjects as $s): ?>
      <tr>
        <td><?= clean($s['code']) ?></td>
        <td><?= clean($s['name']) ?></td>
        <td><?= clean($s['description']) ?></td>
        <td><a href="?delete=<?= (int)$s['id'] ?>" onclick="return confirm('Delete subject? Related resources will also be removed.')" style="color:var(--error)">Delete</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
