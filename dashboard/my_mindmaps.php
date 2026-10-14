<?php
 
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 

require_login();
$pageTitle = 'My Mind-maps';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->prepare("SELECT m.*, s.code AS subject_code FROM mindmaps m LEFT JOIN subjects s ON m.subject_id=s.id WHERE m.user_id=? ORDER BY m.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$mindmaps = $stmt->fetchAll();

// The column that stores the saved mind-map tree (JSON) can vary by schema.
// download_mindmap.php auto-detects the right one; we just check here so we
// can hide the Download button on rows that have nothing to download yet.
$dataCols = ['data', 'tree', 'tree_json', 'content', 'mindmap_data', 'structure'];
function mm_has_data($row, $dataCols) {
    foreach ($dataCols as $c) {
        if (!empty($row[$c])) return true;
    }
    return false;
}

$subjectPalette = ['#1e3a8a', '#5b21b6', '#92400e', '#065f46', '#9d174d'];
?>
<div class="dashboard-layout">
  <aside class="sidebar">
    <a href="index.php">🏠 Overview</a>
    <a href="my_quizzes.php">📝 My Quizzes</a>
    <a href="my_mindmaps.php" class="active">🧠 My Mind-maps</a>
    <a href="profile.php">👤 Profile</a>
  </aside>
  <section>
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
      <h1>My Mind-maps</h1>
      <a href="../mindmap_generator.php" class="btn" style="padding:10px 18px; font-size:.88rem;">+ Create Mind-map</a>
    </div>

    <?php if (empty($mindmaps)): ?>
      <div class="card" style="text-align:center; padding:48px 20px;">
        <div style="font-size:2.4rem; margin-bottom:10px;">🧠</div>
        <p style="color:var(--muted); margin-bottom:14px;">No mind-maps saved yet.</p>
        <a href="../mindmap_generator.php" class="btn">Create one →</a>
      </div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($mindmaps as $i => $m):
        $color = $subjectPalette[$i % count($subjectPalette)];
        $canDownload = mm_has_data($m, $dataCols);
      ?>
      <div class="card" style="border-left:4px solid <?= $color ?>;">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
          <div style="width:34px; height:34px; border-radius:9px; background:<?= $color ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0;">🧠</div>
          <h3 style="font-size:1.02rem;"><?= clean($m['title']) ?></h3>
        </div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:.85rem; color:var(--muted); margin-bottom:14px;">
          <span>📚 <?= clean($m['subject_code'] ?? 'General') ?></span>
          <span>📅 <?= date('Y-m-d', strtotime($m['created_at'])) ?></span>
        </div>
        <?php if ($canDownload): ?>
          <a href="download_mindmap.php?id=<?= (int)$m['id'] ?>" class="btn" style="width:100%; text-align:center; padding:9px; font-size:.85rem;">⬇ Download</a>
        <?php else: ?>
          <span style="display:block; text-align:center; padding:9px; font-size:.8rem; color:var(--muted); border:1px dashed var(--border); border-radius:8px;">
            Download unavailable for this mind-map
          </span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>