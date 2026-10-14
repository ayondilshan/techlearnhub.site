<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_admin();
$pageTitle = 'Manage Users';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id=? AND role='student'");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $newStatus = $row['status'] === 'active' ? 'banned' : 'active';
        $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $id]);
        set_flash('success', "User status updated to $newStatus.");
    }
    header('Location: manage_users.php');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([(int)$_GET['delete']]);
    set_flash('success', 'User deleted.');
    header('Location: manage_users.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
$users = $pdo->query("SELECT * FROM users WHERE role='student' ORDER BY created_at DESC")->fetchAll();
?>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <section>
    <h1>Manage Users</h1>
    <?php require_once __DIR__ . '/../includes/alerts.php'; ?>
    <table style="margin-top:16px;">
      <tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th></th></tr>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= clean($u['name']) ?></td>
        <td><?= clean($u['email']) ?></td>
        <td><span class="badge <?= $u['status']==='active'?'badge-tutorials':'badge-marking' ?>"><?= clean($u['status']) ?></span></td>
        <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
        <td>
          <a href="?toggle=<?= (int)$u['id'] ?>"><?= $u['status']==='active' ? 'Ban' : 'Unban' ?></a> |
          <a href="?delete=<?= (int)$u['id'] ?>" onclick="return confirm('Delete this user?')" style="color:var(--error)">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
