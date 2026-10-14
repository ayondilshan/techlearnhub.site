<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
$pageTitle = 'Admin Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_name'] = $admin['name'];
        $_SESSION['role'] = 'admin';
        header('Location: index.php');
        exit;
    } else {
        set_flash('error', 'Invalid admin credentials.');
    }
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="form-box">
  <h2>Admin Login</h2>
  <?php require_once __DIR__ . '/../includes/alerts.php'; ?>
  <form method="post">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit" class="btn" style="width:100%;">Login as Admin</button>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
