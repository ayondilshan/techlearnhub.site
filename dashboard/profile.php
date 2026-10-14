<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_login();
$pageTitle = 'Profile';

$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $pdo->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $uid]);
    $_SESSION['user_name'] = $name;
    if ($newPass) {
        if (strlen($newPass) < 6) {
            set_flash('error', 'Password must be at least 6 characters.');
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            set_flash('success', 'Profile & password updated.');
        }
    } else {
        set_flash('success', 'Profile updated.');
    }
    header('Location: profile.php');
    exit;
}
require_once __DIR__ . '/../includes/header.php';

$initial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
?>
<div class="dashboard-layout">
  <aside class="sidebar">
    <a href="index.php">🏠 Overview</a>
    <a href="my_quizzes.php">📝 My Quizzes</a>
    <a href="my_mindmaps.php">🧠 My Mind-maps</a>
    <a href="profile.php" class="active">👤 Profile</a>
  </aside>
  <section>
    <h1>Profile</h1>

    <div class="card-pastel" style="display:flex; align-items:center; gap:16px; margin-top:16px; max-width:100%;">
      <div class="greeting-avatar" style="width:56px; height:56px; font-size:1.4rem;"><?= clean($initial) ?></div>
      <div>
        <div style="font-weight:700; font-size:1.1rem;"><?= clean($user['name']) ?></div>
        <div style="color:var(--muted); font-size:.85rem;"><?= clean($user['email']) ?></div>
      </div>
    </div>

    <div class="form-box" style="margin:20px 0 0 0; max-width:100%;">
      <?php require_once __DIR__ . '/../includes/alerts.php'; ?>
      <form method="post">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" value="<?= clean($user['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" value="<?= clean($user['email']) ?>" disabled>
        </div>
        <div class="form-group">
          <label>New Password (leave blank to keep current)</label>
          <input type="password" name="new_password" minlength="6" placeholder="••••••">
        </div>
        <button type="submit" class="btn">Save Changes</button>
      </form>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>