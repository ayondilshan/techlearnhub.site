<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
$pageTitle = 'Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'student'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'banned') {
            set_flash('error', 'Your account has been suspended.');
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            header('Location: verified.php?msg=' . urlencode('Login successful — Verified ✓') . '&to=' . urlencode('index.php'));
            exit;
        }
    } else {
        set_flash('error', 'Invalid email or password.');
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo-box"><img src="<?= BASE_URL ?>assets/img/favicon-32x32.png"></div>
    <div class="auth-title"><span class="cyan">TechLearn </span><span class="white"></span> <span class="cyan">Hub</span></div>
    <div class="auth-tagline">Sri Lanka's Free A/L Technology Platform</div>

    <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="auth-alert auth-alert-<?= clean($f['type']) ?>"><?= clean($f['msg']) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="auth-field">
        <label>Email Address</label>
        <div class="auth-input-wrap">
          <span class="field-icon">✉️</span>
          <input type="email" name="email" placeholder="you@example.com" required>
        </div>
      </div>
      <div class="auth-field">
        <label>Password</label>
        <div class="auth-input-wrap">
          <span class="field-icon">🔒</span>
          <input type="password" name="password" id="loginPassword" placeholder="Your password" required>
          <button type="button" class="auth-toggle-eye" onclick="const p=document.getElementById('loginPassword'); p.type = p.type==='password' ? 'text' : 'password';">👁</button>
        </div>
      </div>
      <div class="auth-row" style="justify-content:flex-end;">
        <a href="#" onclick="alert('Please contact the admin to reset your password.'); return false;">Forgot your password?</a>
      </div>
      <button type="submit" class="auth-btn-primary">SIGN IN →</button>
    </form>

    <p class="auth-footer-text">Don't have an account? <a href="register.php">Create one free →</a></p>
  </div>
</div>