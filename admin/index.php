<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_admin();
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';

$stats = [
    'users' => $pdo->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch()['c'],
    'resources' => $pdo->query("SELECT COUNT(*) c FROM resources")->fetch()['c'],
    'subjects' => $pdo->query("SELECT COUNT(*) c FROM subjects")->fetch()['c'],
    'quizzes' => $pdo->query("SELECT COUNT(*) c FROM quizzes")->fetch()['c'],
    'mindmaps' => $pdo->query("SELECT COUNT(*) c FROM mindmaps")->fetch()['c'],
    'downloads' => $pdo->query("SELECT COALESCE(SUM(downloads),0) c FROM resources")->fetch()['c'],
];
?>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <section>
    <h1>Admin Dashboard</h1>
    <div class="grid" style="margin-top:20px;">
      <div class="card"><h3><?= (int)$stats['users'] ?></h3><p>Registered Students</p></div>
      <div class="card"><h3><?= (int)$stats['resources'] ?></h3><p>Resources Uploaded</p></div>
      <div class="card"><h3><?= (int)$stats['subjects'] ?></h3><p>Subjects</p></div>
      <div class="card"><h3><?= (int)$stats['quizzes'] ?></h3><p>Quizzes Generated</p></div>
      <div class="card"><h3><?= (int)$stats['mindmaps'] ?></h3><p>Mind-maps Created</p></div>
      <div class="card"><h3><?= (int)$stats['downloads'] ?></h3><p>Total Downloads</p></div>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
