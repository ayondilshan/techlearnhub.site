<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db.php';

// Safe fallbacks so a missing definition never kills the <head>
if (!defined('SITE_NAME')) { define('SITE_NAME', 'TechLearn Hub'); }
if (!defined('BASE_URL'))  { define('BASE_URL', '/'); }
if (!function_exists('clean')) {
    function clean($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$__title = isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME;
$__desc  = $pageDescription ?? $metaDescription
    ?? 'A/L Technology සිසුන් සඳහා Notes, Past Papers, Tutorials සහ AI-Powered Study Tools - SFT, ICT, ET, BST, English';
$__url   = BASE_URL . ltrim($_SERVER['REQUEST_URI'] ?? '', '/');
$__keys  = $metaKeywords
    ?? 'A/L Technology, SFT notes, ICT notes, ET notes, BST notes, A/L past papers Sri Lanka, TechLearn Hub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($__title) ?></title>
<meta name="description" content="<?= clean($__desc) ?>">
<meta name="keywords" content="<?= clean($__keys) ?>">
<link rel="canonical" href="<?= clean($__url) ?>">

<!-- Favicon -->
<link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png?v=5">
<link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png?v=5">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/img/logo.png?v=5">
<meta name="theme-color" content="#4f46e5">

<!-- Open Graph -->
<meta property="og:title" content="<?= clean($__title) ?>">
<meta property="og:description" content="<?= clean($__desc) ?>">
<meta property="og:image" content="<?= BASE_URL ?>assets/img/logo.png">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= clean($__url) ?>">
<meta property="og:site_name" content="<?= clean(SITE_NAME) ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= clean($__title) ?>">
<meta name="twitter:description" content="<?= clean($__desc) ?>">
<meta name="twitter:image" content="<?= BASE_URL ?>assets/img/logo.png">

<!-- AdSense (load once, here only) -->
<meta name="google-adsense-account" content="ca-pub-9627710948740041">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9627710948740041"
     crossorigin="anonymous"></script>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-NJ7Q2BN7TH"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-NJ7Q2BN7TH');
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=44">
</head>
<body>
    <body>
</head>
<body>
</head>
<body>
<?php if (is_file(__DIR__ . '/reply_toast.php')) { require_once __DIR__ . '/reply_toast.php'; } ?>
<canvas id="matrixRain"></canvas>

<header class="navbar navbar-light">
  <div class="nav-container">
    <a href="<?= BASE_URL ?>index.php" class="logo logo-light">
      <span class="logo-circle"><img src="<?= BASE_URL ?>assets/img/logo.png?v=4" alt="TechLearn Hub" style="width:100%; height:100%; object-fit:cover;"></span>
      TechLearn <span>Hub</span>
    </a>
    <nav>
      <ul class="nav-links nav-links-light" id="navLinks">
        <?php $__cur = basename($_SERVER['PHP_SELF']); ?>
        <li><a href="<?= BASE_URL ?>index.php" class="nav-pill <?= $__cur === 'index.php' ? 'active-link' : '' ?>">🏠 Home</a></li>
        <li><a href="<?= BASE_URL ?>subjects.php" class="nav-pill <?= $__cur === 'subjects.php' ? 'active-link' : '' ?>">📖 Subjects</a></li>
        <li><a href="<?= BASE_URL ?>resources.php" class="nav-pill <?= $__cur === 'resources.php' ? 'active-link' : '' ?>">📁 Resources</a></li>
        <?php if (is_logged_in()): ?>
        <li><a href="<?= BASE_URL ?>study_helper.php" class="nav-pill <?= $__cur === 'study_helper.php' ? 'active-link' : '' ?>">🤖 Cypher Study Helper</a></li>
        <li><a href="<?= BASE_URL ?>mindmap_generator.php" class="nav-pill <?= $__cur === 'mindmap_generator.php' ? 'active-link' : '' ?>">🔀 Mind Map</a></li>
        <li><a href="<?= BASE_URL ?>quiz_generator.php" class="nav-pill <?= $__cur === 'quiz_generator.php' ? 'active-link' : '' ?>">📝 Quiz</a></li>
        <li><a href="<?= BASE_URL ?>learn.php" class="nav-pill <?= in_array($__cur, ['learn.php', 'learn_task.php'], true) ? 'active-link' : '' ?>">⚡ Code Lab</a></li>
        <?php endif; ?>
        <?php if (is_logged_in()): $__initial = strtoupper(substr($_SESSION['user_name'], 0, 1)); $__firstName = explode(' ', trim($_SESSION['user_name']))[0]; ?>
                  <?php $__unread = function_exists('cm_unread_reply_count') ? cm_unread_reply_count() : 0; ?>
          <li style="position:relative;">
            <button type="button" id="navBellBtn" class="nav-pill" style="border:0;cursor:pointer;position:relative;" aria-label="Notifications<?= $__unread ? ' (' . $__unread . ' new)' : '' ?>">
              🔔<?php if ($__unread > 0): ?><span style="position:absolute;top:-4px;right:-4px;background:#e11d48;color:#fff;border-radius:999px;font-size:11px;font-weight:700;min-width:18px;height:18px;line-height:18px;text-align:center;padding:0 4px;"><?= $__unread ?></span><?php endif; ?>
            </button>
            <div id="navBellEmpty" hidden style="position:absolute;top:calc(100% + 8px);right:0;white-space:nowrap;background:#fff;border:1px solid #ddd;border-radius:10px;padding:10px 14px;box-shadow:0 8px 20px rgba(0,0,0,.12);font-size:13px;z-index:1000;">අලුත් notifications නැහැ</div>
            <script>
            (function () {
              var b = document.getElementById('navBellBtn'), e = document.getElementById('navBellEmpty');
              if (!b) return;
              b.addEventListener('click', function () {
                if (typeof window.showReplyToast === 'function') { window.showReplyToast(); return; }
                e.hidden = false;
                setTimeout(function () { e.hidden = true; }, 2500);
              });
            })();
            </script>
          </li> 
          <li class="nav-user-item">
            <button type="button" class="nav-user-light" id="navUserBtn" aria-haspopup="true" aria-expanded="false" aria-controls="navUserMenuLight" title="<?= clean($_SESSION['user_name']) ?>">
              <span class="nav-user-avatar"><?= clean($__initial) ?></span>
              <?= clean($__firstName) ?>
            </button>
            <div class="nav-user-menu-light" id="navUserMenuLight">
              <a href="<?= BASE_URL ?>dashboard/index.php">📊 Dashboard</a>
              <a href="<?= BASE_URL ?>logout.php" class="is-logout">🚪 Logout</a>
            </div>
          </li>
        <?php else: ?>
          <li><a href="<?= BASE_URL ?>login.php" class="nav-pill">👤 Login</a></li>
          <li><a href="<?= BASE_URL ?>register.php" class="nav-signup-light">✨ Sign Up</a></li>
        <?php endif; ?>
      </ul>
      <button class="hamburger" onclick="document.getElementById('navLinks').classList.toggle('open')">☰</button>
    </nav>
  </div>
</header>
<main class="main-content">