<?php

require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 

require_login();
$pageTitle = 'My Quizzes';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->prepare("SELECT q.*, (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id=q.id) attempts
                        FROM quizzes q WHERE q.user_id=? ORDER BY q.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$quizzes = $stmt->fetchAll();

function difficulty_style($d) {
    switch (strtolower($d)) {
        case 'easy':   return ['bg' => '#dcfce7', 'text' => '#15803d', 'icon' => '🟢'];
        case 'medium': return ['bg' => '#fef3c7', 'text' => '#b45309', 'icon' => '🟠'];
        case 'hard':   return ['bg' => '#fee2e2', 'text' => '#b91c1c', 'icon' => '🔴'];
        default:       return ['bg' => '#ede9fe', 'text' => '#6d28d9', 'icon' => '⚪'];
    }
}
?>
<div class="dashboard-layout">
  <aside class="sidebar">
    <a href="index.php">🏠 Overview</a>
    <a href="my_quizzes.php" class="active">📝 My Quizzes</a>
    <a href="my_mindmaps.php">🧠 My Mind-maps</a>
    <a href="profile.php">👤 Profile</a>
  </aside>
  <section>
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
      <h1>My Quizzes</h1>
      <a href="../quiz_generator.php" class="btn" style="padding:10px 18px; font-size:.88rem;">+ Generate Quiz</a>
    </div>

    <?php if (empty($quizzes)): ?>
      <div class="card" style="text-align:center; padding:48px 20px;">
        <div style="font-size:2.4rem; margin-bottom:10px;">🗂️</div>
        <p style="color:var(--muted); margin-bottom:14px;">No quizzes yet.</p>
        <a href="../quiz_generator.php" class="btn">Generate one →</a>
      </div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($quizzes as $q):
        $ds = difficulty_style($q['difficulty']);
      ?>
      <div class="card" style="border-left:4px solid <?= $ds['text'] ?>; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
          <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:10px;">
            <h3 style="font-size:1.05rem;"><?= clean($q['topic']) ?></h3>
            <span class="badge" style="background:<?= $ds['bg'] ?>; color:<?= $ds['text'] ?>; white-space:nowrap;">
              <?= $ds['icon'] ?> <?= clean($q['difficulty']) ?>
            </span>
          </div>
          <div style="display:flex; flex-direction:column; gap:6px; font-size:.85rem; color:var(--muted); margin-bottom: 16px;">
            <span>🔁 <?= (int)$q['attempts'] ?> attempt<?= $q['attempts'] == 1 ? '' : 's' ?></span>
            <span>📅 <?= date('Y-m-d', strtotime($q['created_at'])) ?></span>
          </div>
        </div>
        <div>
          <!-- පේපර් එකක් ලෙස නැවත කිරීමට සහ බලන්න පුළුවන් Link එක -->
          <a href="take_quiz.php?id=<?= $q['id'] ?>" class="btn" style="width: 100%; text-align: center; padding: 8px; font-size: 0.85rem; display: block;">Take Paper 📝</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>