<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 

require_login();
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$uid = $_SESSION['user_id'];
$quizCount = $pdo->prepare("SELECT COUNT(*) c FROM quizzes WHERE user_id=?");
$quizCount->execute([$uid]);
$quizCount = $quizCount->fetch()['c'];

$mmCount = $pdo->prepare("SELECT COUNT(*) c FROM mindmaps WHERE user_id=?");
$mmCount->execute([$uid]);
$mmCount = $mmCount->fetch()['c'];

$attempts = $pdo->prepare("SELECT qa.*, q.topic FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id=q.id WHERE qa.user_id=? ORDER BY qa.attempted_at DESC LIMIT 5");
$attempts->execute([$uid]);
$attempts = $attempts->fetchAll();

$initial = strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1));

// ---- Competition Rank / XP widget ----
// XP source: 10 XP per quiz generated + 15 XP per mind-map created.
// (I don't have a dedicated XP/points table, so this is computed on the fly
// from counts you already track. Tell me if you'd rather award XP per
// correct quiz answer, per attempt, etc. and I'll adjust the formula.)
$totalXp = ((int)$quizCount * 10) + ((int)$mmCount * 15);

$ranks = [
    ['name' => 'Recruit',  'icon' => '🛡️', 'min' => 0],
    ['name' => 'Bronze',   'icon' => '🥉', 'min' => 100],
    ['name' => 'Silver',   'icon' => '🥈', 'min' => 300],
    ['name' => 'Gold',     'icon' => '🥇', 'min' => 600],
    ['name' => 'Platinum', 'icon' => '💎', 'min' => 1000],
    ['name' => 'Diamond',  'icon' => '🔷', 'min' => 1500],
    ['name' => 'Master',   'icon' => '👑', 'min' => 2500],
    ['name' => 'Legend',   'icon' => '🏆', 'min' => 4000],
];

$currentIndex = 0;
foreach ($ranks as $i => $r) {
    if ($totalXp >= $r['min']) $currentIndex = $i;
}
$currentRank = $ranks[$currentIndex];
$nextRank = $ranks[$currentIndex + 1] ?? null;

if ($nextRank) {
    $span = $nextRank['min'] - $currentRank['min'];
    $progressPct = $span > 0 ? min(100, (($totalXp - $currentRank['min']) / $span) * 100) : 100;
    $xpToNext = $nextRank['min'] - $totalXp;
} else {
    $progressPct = 100;
    $xpToNext = 0;
}
?>
<div class="dashboard-layout">
  <aside class="sidebar">
    <a href="index.php" class="active">🏠 Overview</a>
    <a href="my_quizzes.php">📝 My Quizzes</a>
    <a href="my_mindmaps.php">🧠 My Mind-maps</a>
    <a href="profile.php">👤 Profile</a>
    
  </aside>
  <section>
    <div class="greeting-bar">
      <div class="greeting-left">
        <div class="greeting-avatar"><?= clean($initial) ?></div>
        <div>
          Welcome back, <b><?= clean($_SESSION['user_name']) ?></b> 👋
        </div>
      </div>
      <div class="greeting-right">
        <a href="../mindmap_generator.php" class="btn" style="padding:9px 16px; font-size:.85rem;">+ New Mind-map</a>
        <a href="../quiz_generator.php" class="btn btn-outline" style="padding:9px 16px; font-size:.85rem;">+ New Quiz</a>
         <a href="../Learn.php" class="btn btn-outline" style="padding:9px 16px; font-size:.85rem;">+ Programming Quiz</a>
      </div>
    </div>

    <div class="rank-widget">
      <div class="rank-widget-top">
        <div class="rank-widget-id">
          <div class="rank-icon-current"><?= $currentRank['icon'] ?></div>
          <div>
            <div class="rank-widget-label">Competition Rank</div>
            <div class="rank-widget-name"><?= clean($currentRank['name']) ?></div>
          </div>
        </div>
        <div class="rank-xp-pill">Total XP: <b><?= (int)$totalXp ?></b></div>
      </div>

      <div class="rank-progress-meta">
        <span><?= $nextRank ? ((int)$xpToNext . ' XP to ' . clean($nextRank['name'])) : 'Max rank reached 🎉' ?></span>
        <span><?= round($progressPct) ?>%</span>
      </div>
      <div class="rank-progress-track">
        <div class="rank-progress-fill" style="width:<?= $progressPct ?>%;"></div>
      </div>

      <div class="rank-steps">
        <?php foreach ($ranks as $i => $r): ?>
          <div class="rank-step <?= $i <= $currentIndex ? 'reached' : '' ?> <?= $i === $currentIndex ? 'current' : '' ?>">
            <div class="rank-step-icon"><?= $r['icon'] ?></div>
            <div class="rank-step-label"><?= clean($r['name']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid" style="margin:20px 0 28px;">
      <div class="card-pastel">
        <div style="font-size:1.6rem; margin-bottom:6px;">📝</div>
        <h3 style="font-size:1.8rem;"><?= (int)$quizCount ?></h3>
        <p>Quizzes generated</p>
      </div>
      <div class="card-pastel">
        <div style="font-size:1.6rem; margin-bottom:6px;">🧠</div>
        <h3 style="font-size:1.8rem;"><?= (int)$mmCount ?></h3>
        <p>Mind-maps created</p>
      </div>
    </div>

    <div style="display:flex; align-items:center; justify-content:space-between; margin:20px 0 10px;">
      <h2>Recent Quiz Attempts</h2>
      <?php if (!empty($attempts)): ?>
        <a href="my_quizzes.php" style="font-size:.85rem; color:var(--primary); font-weight:600;">View all →</a>
      <?php endif; ?>
    </div>

    <?php if (empty($attempts)): ?>
      <div class="card" style="text-align:center; padding:36px 20px;">
        <div style="font-size:2rem; margin-bottom:8px;">📭</div>
        <p style="color:var(--muted)">No quiz attempts yet. <a href="../quiz_generator.php" style="color:var(--primary); font-weight:600;">Try one now →</a></p>
      </div>
    <?php else: ?>
    <table>
      <tr><th>Topic</th><th>Score</th><th>Date</th></tr>
      <?php foreach ($attempts as $a):
        $pct = $a['total'] > 0 ? ($a['score'] / $a['total']) * 100 : 0;
        $badgeStyle = '';
        if ($pct >= 70) { $badgeClass = 'alert-success'; }
        elseif ($pct >= 40) { $badgeClass = ''; $badgeStyle = 'background:#fef3c7;color:#b45309;'; }
        else { $badgeClass = 'alert-error'; }
      ?>
      <tr>
        <td><?= clean($a['topic']) ?></td>
        <td>
          <span class="badge <?= $badgeClass ?>" style="<?= $badgeStyle ?> padding:4px 12px;">
            <?= (int)$a['score'] ?> / <?= (int)$a['total'] ?>
          </span>
        </td>
        <td style="color:var(--muted); font-size:.85rem;"><?= date('Y-m-d H:i', strtotime($a['attempted_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>