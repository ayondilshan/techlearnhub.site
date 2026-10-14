<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
$pageTitle = 'Sign Up';

// Older databases may not have first_name / last_name yet (see sql/users_first_last_name.sql).
$hasSplitNames = false;
try {
    $hasSplitNames = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'first_name'")->fetch();
} catch (Throwable $e) {
    $hasSplitNames = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$first || !$last || !$email || !$password) {
        set_flash('error', 'All fields are required.');
    } elseif (mb_strlen($first) > 60 || mb_strlen($last) > 60) {
        set_flash('error', 'First name and last name must be 60 characters or less.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Invalid email address.');
    } elseif (strlen($password) < 6) {
        set_flash('error', 'Password must be at least 6 characters.');
    } elseif ($password !== $confirm) {
        set_flash('error', 'Passwords do not match.');
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            set_flash('error', 'An account with this email already exists.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $fullName = $first . ' ' . $last;   // `name` is still used all over the site
            if ($hasSplitNames) {
                $stmt = $pdo->prepare("INSERT INTO users (name, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, 'student')");
                $stmt->execute([$fullName, $first, $last, $email, $hash]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
                $stmt->execute([$fullName, $email, $hash]);
            }
            header('Location: verified.php?msg=' . urlencode('Account created — Verified ✓') . '&to=' . urlencode('index.php'));
            exit;
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo-box"><img src="<?= BASE_URL ?>assets/img/logo.png?v=4" alt="TechLearn Hub"></div>
    <div class="auth-title"><span class="cyan">TechLearn</span><span class="white"></span> <span class="cyan">Hub</span></div>
    <div class="auth-tagline">Sri Lanka's Free A/L Technology Platform</div>

    <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="auth-alert auth-alert-<?= clean($f['type']) ?>"><?= clean($f['msg']) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="auth-name-row">
        <div class="auth-field">
          <label for="regFirst">First Name</label>
          <div class="auth-input-wrap">
            <input type="text" name="first_name" id="regFirst" placeholder="Kasun" required maxlength="60" autocomplete="given-name" value="<?= clean($_POST['first_name'] ?? '') ?>">
          </div>
        </div>
        <div class="auth-field">
          <label for="regLast">Last Name</label>
          <div class="auth-input-wrap">
            <input type="text" name="last_name" id="regLast" placeholder="Perera" required maxlength="60" autocomplete="family-name" value="<?= clean($_POST['last_name'] ?? '') ?>">
          </div>
        </div>
      </div>
      <div class="auth-field">
        <label>Email Address</label>
        <div class="auth-input-wrap">
          <span class="field-icon">✉️</span>
          <input type="email" name="email" placeholder="you@example.com" required value="<?= clean($_POST['email'] ?? '') ?>">
        </div>
      </div>
      <div class="auth-field">
        <label>Password</label>
        <div class="auth-input-wrap">
          <span class="field-icon">🔒</span>
          <input type="password" name="password" id="regPassword" placeholder="At least 6 characters" required minlength="6">
          <button type="button" class="auth-toggle-eye" onclick="const p=document.getElementById('regPassword'); p.type = p.type==='password' ? 'text' : 'password';">👁</button>
        </div>
      </div>
      <div class="auth-field">
        <label>Confirm Password</label>
        <div class="auth-input-wrap">
          <span class="field-icon">🔒</span>
          <input type="password" name="confirm_password" id="regConfirmPassword" placeholder="Re-enter your password" required minlength="6">
          <button type="button" class="auth-toggle-eye" onclick="const p=document.getElementById('regConfirmPassword'); p.type = p.type==='password' ? 'text' : 'password';">👁</button>
        </div>
      </div>
      <button type="submit" class="auth-btn-primary" style="margin-top:6px;">SIGN UP →</button>
    </form>

    <p class="auth-footer-text" style="margin-top:22px;">Already have an account? <a href="login.php">Login →</a></p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>