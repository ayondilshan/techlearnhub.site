<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
$pageTitle = 'Subjects';
require_once __DIR__ . '/includes/header.php';

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

$resourcesBySubject = [];
foreach ($subjects as $s) {
    // Pull only "Notes" type resources for this subject (Past Papers excluded), no LIMIT — we'll sort + trim in PHP
    $stmt = $pdo->prepare("SELECT title FROM resources WHERE subject_id = ? AND type = 'Notes' ORDER BY created_at DESC");
    $stmt->execute([$s['id']]);
    $titles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Separate "Full Notes" items from Unit items
    $unitItems = [];
    $fullNoteItems = [];
    foreach ($titles as $title) {
        if (stripos($title, 'full note') !== false) {
            $fullNoteItems[] = $title;
        } else {
            $unitItems[] = $title;
        }
    }

    // Natural sort so UNIT 2 comes before UNIT 10 (extracts the number after "UNIT")
    usort($unitItems, function ($a, $b) {
        preg_match('/UNIT\s*0*(\d+)/i', $a, $ma);
        preg_match('/UNIT\s*0*(\d+)/i', $b, $mb);
        $numA = isset($ma[1]) ? (int)$ma[1] : PHP_INT_MAX;
        $numB = isset($mb[1]) ? (int)$mb[1] : PHP_INT_MAX;
        if ($numA === $numB) {
            return strnatcasecmp($a, $b); // fallback for ties / non-unit titles
        }
        return $numA <=> $numB;
    });

    // Limit how many unit items show in the preview card (adjust as needed)
    $previewLimit = 30;
    $preview = array_slice($unitItems, 0, $previewLimit);

    // Always show "Full Notes" last, even if unit items were trimmed
    foreach ($fullNoteItems as $fn) {
        $preview[] = $fn;
    }

    $resourcesBySubject[$s['id']] = $preview;
}
?>
<h1 style="margin-bottom:20px;">All Subjects</h1>
<div class="subject-lesson-grid">
  <?php foreach ($subjects as $s): $cls = subjectColorClass($s['code']); $items = $resourcesBySubject[$s['id']]; ?>
  <a href="resources.php?subject=<?= urlencode($s['code']) ?>" class="subject-lesson-card <?= $cls ?>">
    <div class="subject-lesson-icon"><?= subjectIcon($s['code']) ?></div>
    <h3><?= clean($s['name']) ?></h3>
    <p><?= clean($s['description']) ?></p>
    <?php if ($items): ?>
    <div class="subject-lesson-list">
      <?php foreach ($items as $title): ?>
        <div class="subject-lesson-item"><span class="dot"></span><?= clean($title) ?></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="subject-lesson-empty">No resources uploaded yet.</p>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>