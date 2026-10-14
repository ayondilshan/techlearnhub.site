<?php
// Error Display සක්‍රිය කිරීම
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

// Safe Database Queries (Error එකක් ආවොත් Site එක Crash නොවන පරිදි)
try {
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
    $counts = $pdo->query("SELECT s.code, COUNT(r.id) c FROM subjects s LEFT JOIN resources r ON r.subject_id = s.id GROUP BY s.id")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalResources = $pdo->query("SELECT COUNT(*) c FROM resources")->fetch()['c'] ?? 0;
    $totalStudents = $pdo->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch()['c'] ?? 0;
} catch (PDOException $e) {
    echo "<div style='background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin:20px; font-family:sans-serif;'>";
    echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    $subjects = [];
    $counts = [];
    $totalResources = 0;
    $totalStudents = 0;
}
?>
<meta name="description" content="TechLearn Hub — ලංකාවේ A/L Technology (SFT, ICT, ET, BST, English) සිසුන් වෙනුවෙන්ම හදපු AI-Powered Study Platform එකක්. Notes, Past Papers, Tutorials සහ Cypher AI, Mind-map Generator, Quiz Generator වගේ Smart Tools එකම තැනකින්.">
<meta name="keywords" content="TechLearn Hub,techlearnhub ,  tech learn hub,al tech hub, SFT, ICT, ET, BST, English, Sri Lanka students, study platform, AI study tool, quiz generator, mind map, past papers, Sri Lankan school subjects, online learning">
<meta name="google-adsense-account" content="ca-pub-9627710948740041">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9627710948740041"
     crossorigin="anonymous"></script>
<div class="light-hero">
  <div class="light-hero-text">
    <h1>TechLearn <span class="accent">Hub</span><br>Learn.Grow.Succeed</h1>
    <p class="subtitle">A/L Technology සිසුන් සඳහා නිර්මාණය කළ, AI-Powered Study Platform එකක්. Notes, Past Papers, Tutorials සහ Smart AI Tools — SFT, ICT, ET, BST, English සඳහා එකම තැනකින්.</p>
    <div class="light-hero-actions">
      <a href="resources.php" class="pill-btn-filled">📁 Resources</a>
      <a href="study_helper.php" class="pill-btn-outline">🤖 Cypher Study Helper</a>    
    </div>
    <span class="light-hero-badge">🇱🇰 Sri Lanka's AI-Powered A/L Study Platform</span>
  </div>
  <div class="light-hero-graphic sf-box" id="holoBox">
    <iframe title="" frameborder="0" allowfullscreen mozallowfullscreen="true" webkitallowfullscreen="true"
      allow="autoplay; fullscreen; xr-spatial-tracking" xr-spatial-tracking execution-while-out-of-viewport execution-while-not-rendered web-share width="560" height="600"
      src="https://sketchfab.com/models/59fc99d8dcb146f3a6c16dbbcc4680da/embed?autostart=1&preload=1&transparent=1&ui_infos=0&ui_controls=0&ui_stop=0&ui_watermark=0&ui_watermark_link=0&ui_hint=0&ui_help=0&ui_settings=0&ui_vr=0&ui_fullscreen=0&ui_annotations=0"></iframe>
  </div>
  <style>
    .light-hero-graphic.sf-box{flex:1 1 420px; max-width:560px; width:100%; aspect-ratio:auto; height:auto; margin:-20px 0; display:flex; flex-direction:column;}
    .sf-box iframe{aspect-ratio:560/600; height:auto!important; min-height:380px;}
    .sf-box iframe{flex:1; width:100%; border:0; border-radius:20px; background:transparent;}
    .sf-credit{font-size:11px; margin:4px 0 0; text-align:center; color:#6b7280;}
    .sf-credit a{color:#7c3aed; font-weight:600; text-decoration:none;}
  </style>
</div>

<h2 style="margin-bottom:16px;">Subjects</h2>
<div class="subject-grid-dark">
  <?php foreach ($subjects as $s): $cls = function_exists('subjectColorClass') ? subjectColorClass($s['code']) : ''; ?>
  <div class="subject-card-dark <?= $cls ?>">
    <div class="subject-card-dark-top">
      <div class="subject-card-dark-icon"><?= function_exists('subjectIcon') ? subjectIcon($s['code']) : '📚' ?></div>
      <span class="subject-card-dark-count">📄 <?= (int)($counts[$s['code']] ?? 0) ?></span>
    </div>
    <h3><?= function_exists('clean') ? clean($s['code']) : htmlspecialchars($s['code']) ?></h3>
    <p><?= function_exists('clean') ? clean($s['name']) : htmlspecialchars($s['name']) ?></p>
    <a href="resources.php?subject=<?= urlencode($s['code']) ?>" class="view-btn">View Resources →</a>
  </div>
  <?php endforeach; ?>
</div>

<div class="about-section">
  <div class="about-header">
    <span class="about-tag">🎓 About Us</span>
    <h2>Who We Are. Learn About Us</h2>
    <p>අපි TechLearn Hub — ලංකාවේ A/L Technology (SFT, ICT, ET, BST, English) සිසුන් වෙනුවෙන්ම හදපු AI-Powered Study Platform එකක්. උසස් අධ්‍යාපනයට අවශ්‍ය Notes, Past Papers, Tutorials සහ Smart AI Tools (Cypher Chatbot / Mind-map Generator / Quiz Generator / Code Lab) සියල්ල එකම තැනකින්.</p>
  </div>
  <div class="about-body">
    <div class="about-photo-card">
      <div class="logo-frame">
        <img src="<?= defined('BASE_URL') ? BASE_URL : '' ?>assets/img/logo.png?v=4" alt="TechLearn Hub" onerror="this.closest('.logo-frame').style.display='none'">
      </div>
    </div>
    <div class="about-stats-grid">
      <div class="about-stat-box">
        <div class="icon">📁</div>
        <div class="num"><?= (int)$totalResources ?>+</div>
        <div class="desc">Study resources we deliver</div>
      </div>
      <div class="about-stat-box">
        <div class="icon">📚</div>
        <div class="num"><?= (int)count($subjects) ?></div>
        <div class="desc">A/L Technology subjects covered</div>
      </div>
      <div class="about-stat-box">
        <div class="icon">🏆</div>
        <div class="num">3</div>
        <div class="desc">AI-powered tools built for you</div>
      </div>
      <div class="about-stat-box">
        <div class="icon">👥</div>
        <div class="num"><?= (int)$totalStudents ?>+</div>
        <div class="desc">Students learning with us</div>
      </div>
    </div>
  </div>
</div>

<h2 style="margin:8px 0 16px;">Tools</h2>
<div class="tools-grid-dark">   
  <div class="tool-card-dark" data-href="study_helper.php" role="link" tabindex="0">
    <div class="tool-card-dark-top"><span class="tool-card-dark-icon">🤖</span></div>
    <h3> Cypher Study Helper</h3>
    <p>Ask study questions and get instant AI answers.</p>
    <div class="tool-card-dark-footer">
      <span class="tool-card-dark-cat">💬 AI Tool</span>
      <span class="tool-card-dark-rating"><span class="star">★</span> 1.1k</span>    
    </div>
  </div>
  <div class="tool-card-dark" data-href="mindmap_generator.php" role="link" tabindex="0">
    <div class="tool-card-dark-top"><span class="tool-card-dark-icon">🧠</span></div>
    <h3>Mind-map Generator</h3>
    <p>Turn any topic into a visual mind-map instantly.</p>
    <div class="tool-card-dark-footer">
      <span class="tool-card-dark-cat">🔀 AI Tool</span>
      <span class="tool-card-dark-rating"><span class="star">★</span> 1k</span>
    </div>
  </div>
  <div class="tool-card-dark" data-href="quiz_generator.php" role="link" tabindex="0">
    <div class="tool-card-dark-top"><span class="tool-card-dark-icon">📝</span></div>
    <h3>Quiz Generator</h3>
    <p>AI-generated practice quizzes with instant marking.</p>
    <div class="tool-card-dark-footer">
      <span class="tool-card-dark-cat">📄 AI Tool</span>
      <span class="tool-card-dark-rating"><span class="star">★</span> 1.5k</span>
    </div>
  </div>
  <div class="tool-card-dark" data-href="learn.php" role="link" tabindex="0">
    <div class="tool-card-dark-top"><span class="tool-card-dark-icon">⚡</span></div>
    <h3>Code Lab</h3>
    <p>Learn. Build. Create. Innovate</p>
    <div class="tool-card-dark-footer">
      <span class="tool-card-dark-cat">💬 AI Tool</span>
      <span class="tool-card-dark-rating"><span class="star">★</span> 1.2k</span>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.tool-card-dark[data-href]').forEach(function(card){
  card.addEventListener('click', function(){
    window.location.href = card.dataset.href;
  });
  card.addEventListener('keydown', function(e){
    if (e.key === 'Enter') window.location.href = card.dataset.href;
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>