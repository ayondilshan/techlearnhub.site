<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/reply_helpers.php';
$pageTitle = 'Contact Us';
require_once __DIR__ . '/includes/header.php';

$success = false;
$error = '';

// Login වෙලා ඉන්න user ගේ account email එක (reply notification එක හරියට ලැබෙන්න මේක use කරනවා)
$isUser       = function_exists('is_logged_in') && is_logged_in() && !empty($_SESSION['user_id']);
$accountEmail = $isUser ? cm_user_email() : '';
$lockEmail    = ($accountEmail !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = $lockEmail ? $accountEmail : trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$message) {
        $error = 'Please fill in your name, email and message.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $uid  = $isUser ? (int)$_SESSION['user_id'] : null;
        $subj = $subject ?: '(No subject)';
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, name, email, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$uid, $name, $email, $subj, $message]);
        } catch (Exception $e) {
            // user_id column තාම නැත්නම් කලින් විදිහටම save කරනවා
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $subj, $message]);
            } catch (Exception $e2) {
                // Table may not exist yet — that's fine, we still try to email below.
            }
        }

        $to = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@' . preg_replace('#^https?://#', '', rtrim(BASE_URL, '/'));
        $mailSubject = '[' . SITE_NAME . ' Contact] ' . ($subject ?: 'New message from ' . $name);
        $mailBody = "Name: $name\nEmail: $email\n\nMessage:\n$message";
        $headers = "From: $name <$email>\r\nReply-To: $email\r\n";
        @mail($to, $mailSubject, $mailBody, $headers);

        $success = true;
    }
}
?>
<div class="about-section" style="padding:44px 40px;">
  <div class="about-header" style="max-width:640px;">
    <span class="about-tag">💬 Contact Us</span>
    <h2>Let's Talk. We're Here to Help</h2>
    <p>ප්‍රශ්නයක්, feedback එකක්, නැත්නම් suggestion එකක් තියෙනවද? අපිට පහළ form එක හරහා, නැත්නම් direct විදියටම, ලියන්න. හැකි ඉක්මනින්ම reply කරන්නම්.</p>
  </div>

  <div style="display:flex; gap:32px; flex-wrap:wrap; align-items:flex-start; max-width:900px; margin:0 auto;">

    <div style="flex:1; min-width:260px; display:flex; flex-direction:column; gap:16px;">
      <div class="card-pastel">
        <h3>📧 Email</h3>
        <p style="margin-top:6px;">support@techlearnhub.lk</p>
      </div>
      <div class="card-pastel">
        <h3>📱 Phone / WhatsApp</h3>
        <p style="margin-top:6px;">+94 76 169 7214</p>
      </div>
      <div class="card-pastel">
        <h3>⏰ Response Time</h3>
        <p style="margin-top:6px;">සාමාන්‍යයෙන් පැය 3-5 ඇතුළත reply කරනවා.</p>
      </div>
    </div>

    <div class="form-box" style="flex:1.4; min-width:300px; margin:0;">
      <h2>Send a Message</h2>

      <?php if ($success): ?>
        <div class="alert alert-success">✓ ස්තූතියි! ඔයාගේ message එක ලැබුණා. ඉක්මනින්ම reply කරන්නම්.<?= $lockEmail ? ' Reply එක ආවම site එකේ උඩින් notification එකක් පෙන්නනවා.' : '' ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-error"><?= clean($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label for="name">Your Name</label>
          <input type="text" id="name" name="name" placeholder="e.g. Nimal Perera" value="<?= isset($_POST['name']) ? clean($_POST['name']) : ($isUser ? clean($_SESSION['user_name'] ?? '') : '') ?>" required>
        </div>
        <div class="form-group">
          <label for="email">Email Address<?= $lockEmail ? ' (ඔයාගේ account email)' : '' ?></label>
          <?php if ($lockEmail): ?>
            <input type="email" id="email" name="email" value="<?= clean($accountEmail) ?>" readonly>
          <?php else: ?>
            <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= isset($_POST['email']) ? clean($_POST['email']) : '' ?>" required>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label for="subject">Subject (optional)</label>
          <input type="text" id="subject" name="subject" placeholder="e.g. Question about ICT notes" value="<?= isset($_POST['subject']) ? clean($_POST['subject']) : '' ?>">
        </div>
        <div class="form-group">
          <label for="message">Message</label>
          <textarea id="message" name="message" rows="5" placeholder="ඔයාගේ message එක මෙතන ලියන්න..." required><?= isset($_POST['message']) ? clean($_POST['message']) : '' ?></textarea>
        </div>
        <button type="submit" class="btn" style="width:100%;">Send Message</button>
      </form>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>