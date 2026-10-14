<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_login();

$quizId = (int)($_GET['id'] ?? 0);

// Quiz එක අයිති පරිශීලකයාටමදැයි පරීක්ෂා කිරීම
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND user_id = ?");
$stmt->execute([$quizId, $_SESSION['user_id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: my_quizzes.php');
    exit;
}

// ප්‍රශ්න සහ විවරණ ලබා ගැනීම
$qStmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();

$pageTitle = 'Paper Review: ' . clean($quiz['topic']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <aside class="sidebar">
    <a href="index.php">🏠 Overview</a>
    <a href="my_quizzes.php" class="active">📝 My Quizzes</a>
    <a href="my_mindmaps.php">🧠 My Mind-maps</a>
    <a href="profile.php">👤 Profile</a>
  </aside>
  
  <section>
    <div style="margin-bottom: 20px;">
      <a href="my_quizzes.php" style="color:var(--muted); text-decoration:none; font-size: 0.9rem;">← Back to My Quizzes</a>
      <h1 style="margin-top: 10px;"><?= clean($quiz['topic']) ?></h1>
      <p style="color:var(--muted); font-size: 0.88rem;">Difficulty: <?= clean($quiz['difficulty']) ?> | Total Questions: <?= count($questions) ?></p>
    </div>

    <?php if (empty($questions)): ?>
      <div class="card" style="text-align:center; padding:30px; color:var(--muted);">
        මෙම Quiz එක සඳහා ප්‍රශ්න හමු නොවීය.
      </div>
    <?php else: ?>
      
      <?php foreach ($questions as $i => $q): ?>
        <div class="card" style="margin-bottom:16px; border-left: 4px solid #16a34a;">
          
          <!-- ප්‍රශ්නය -->
          <p style="font-weight:600; margin-bottom:12px; font-size:1.02rem; line-height: 1.5;">
            <?= ($i + 1) . '. ' . clean($q['question']) ?>
          </p>
          
          <!-- පිළිතුරු (Options) -->
          <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:12px;">
            <?php 
            $correctOpt = strtolower($q['correct_option'] ?? 'a'); 
            $correctText = $q['option_' . $correctOpt] ?? '';

            foreach (['a', 'b', 'c', 'd'] as $opt): 
               if (empty($q['option_' . $opt])) continue;
               
               $isCorrect = ($opt === $correctOpt);
               $bg = $isCorrect ? 'rgba(34,197,94,.15)' : 'var(--bg-subtle, #f9fafb)';
               $border = $isCorrect ? '1px solid #16a34a' : '1px solid var(--border, #e5e7eb)';
               $color = $isCorrect ? '#15803d' : 'inherit';
            ?>
               <div style="padding:10px 14px; border-radius:8px; background:<?= $bg ?>; border: <?= $border ?>; color: <?= $color ?>; font-size:0.95rem;">
                 <b><?= strtoupper($opt) ?>.</b> <?= clean($q['option_' . $opt]) ?>
                 <?= $isCorrect ? ' <span style="float:right; font-weight:bold;">✔ (නිවැරදි පිළිතුර)</span>' : '' ?>
               </div>
            <?php endforeach; ?>
          </div>
          
          <!-- විවරණය (Explanation) -->
          <div style="margin-top:12px; border-top:1px dashed var(--border, #ccc); padding-top:10px; background: #f8fafc; padding: 12px; border-radius: 8px;">
            <div style="color:#16a34a; font-weight:700; margin-bottom:4px; font-size:0.9rem;">
              ✅ නිවැරදි උත්තරය: <?= clean($correctText) ?>
            </div>
            
            <?php if (!empty($q['explanation'])): ?>
              <div style="color:var(--muted); font-size:0.90rem; line-height: 1.5; margin-top: 6px;">
                💡 <b>විවරණය (Explanation):</b> <?= clean($q['explanation']) ?>
              </div>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>