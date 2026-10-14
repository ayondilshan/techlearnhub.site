<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Account Verified';
require_once __DIR__ . '/includes/header.php';

$msg = $_GET['msg'] ?? 'Account created successfully!';
$to = $_GET['to'] ?? 'login.php';
?>

<div class="auth-page" style="text-align:center; padding: 60px 20px;">
  <div class="auth-card" style="max-width:480px; margin:0 auto; padding:40px; background:var(--card-bg, #1e1e2e); border-radius:16px;">
    <div style="font-size:64px; margin-bottom:16px;">✅</div>
    <h2 style="margin-bottom:12px; color:#10b981;"><?= htmlspecialchars($msg) ?></h2>
    <p style="color:var(--muted, #a6adc8); margin-bottom:24px;">Your account has been set up. You can now log in to access TechLearn Hub.</p>
    <a href="<?= htmlspecialchars($to) ?>" class="pill-btn-filled" style="display:inline-block; padding:12px 30px; text-decoration:none; background:#7c3aed; color:#fff; border-radius:30px; font-weight:600;">Continue →</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>