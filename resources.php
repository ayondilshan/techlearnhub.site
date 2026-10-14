<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
$pageTitle = 'Resources';
require_once __DIR__ . '/includes/header.php';

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$types = ['Notes','Past Papers','Tutorials','Marking Schemes','Unit Papers'];

$subjectFilter = $_GET['subject'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$sql = "SELECT r.*, s.code AS subject_code, s.name AS subject_name
        FROM resources r JOIN subjects s ON r.subject_id = s.id WHERE 1=1";
$params = [];
if ($subjectFilter !== '') { $sql .= " AND s.code = ?"; $params[] = $subjectFilter; }
if ($typeFilter !== '') { $sql .= " AND r.type = ?"; $params[] = $typeFilter; }
$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

function badgeClass($type) {
    return match($type) {
        'Notes' => 'badge-notes',
        'Past Papers' => 'badge-pastpapers',
        'Tutorials' => 'badge-tutorials',
        'Marking Schemes' => 'badge-marking',
        'Unit Papers' => 'badge-unit',
        default => ''
    };
}
function typeIcon($type) {
    return match($type) {
        'Notes' => '📝',
        'Past Papers' => '📄',
        'Tutorials' => '🎓',
        'Marking Schemes' => '✅',
        'Unit Papers' => '📘',
        default => '📁'
    };
}
function ribbonClass($type) {
    return match($type) {
        'Notes' => 'gh-ribbon-notes',
        'Past Papers' => 'gh-ribbon-pastpapers',
        'Tutorials' => 'gh-ribbon-tutorials',
        'Marking Schemes' => 'gh-ribbon-marking',
        'Unit Papers' => 'gh-ribbon-unit',
        default => 'gh-ribbon-notes'
    };
}

// Counts for pill badges
$totalCount = $pdo->query("SELECT COUNT(*) c FROM resources")->fetch()['c'];
$subjectCounts = $pdo->query(
    "SELECT s.code, COUNT(r.id) c FROM subjects s LEFT JOIN resources r ON r.subject_id = s.id GROUP BY s.id"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$typeCounts = $pdo->query(
    "SELECT type, COUNT(*) c FROM resources GROUP BY type"
)->fetchAll(PDO::FETCH_KEY_PAIR);

function filterUrl($subject, $type) {
    $params = [];
    if ($subject !== '') $params['subject'] = $subject;
    if ($type !== '') $params['type'] = $type;
    return 'resources.php' . ($params ? '?' . http_build_query($params) : '');
}
?>
<h1 style="margin-bottom:20px;">Resources</h1>

<div class="filter-bar">
  <div class="filter-row">
    <?php foreach ($subjects as $s): ?>
    <a href="<?= filterUrl($s['code'], $typeFilter) ?>" class="filter-pill <?= $subjectFilter === $s['code'] ? 'active' : '' ?>">
      <?= subjectIcon($s['code']) ?> <?= clean($s['code']) ?> <span class="count"><?= (int)($subjectCounts[$s['code']] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="filter-row">
    <?php foreach ($types as $t): ?>
    <a href="<?= filterUrl($subjectFilter, $t) ?>" class="filter-pill <?= $typeFilter === $t ? 'active' : '' ?>">
      <?= typeIcon($t) ?> <?= clean($t) ?> <span class="count"><?= (int)($typeCounts[$t] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="filter-meta">
    <span>Showing <b><?= count($resources) ?></b> of <b><?= (int)$totalCount ?></b> resources</span>
  </div>
</div>

<?php if (empty($resources)): ?>
  <p style="color:var(--muted)">No resources found for this filter yet.</p>
<?php else: ?>
<div class="resource-grid">
  <?php foreach ($resources as $r): ?>
  <div class="gh-card">
    <span class="gh-card-ribbon <?= ribbonClass($r['type']) ?>"><?= clean($r['type']) ?></span>
    <div class="gh-card-top">
      <div class="gh-card-icon"><?= subjectIcon($r['subject_code']) ?></div>
      <span class="gh-card-title"><?= clean($r['title']) ?></span>
    </div>
    <div class="gh-card-desc"><?= $r['description'] ? clean($r['description']) : 'Study material for ' . clean($r['subject_code']) . ' subject' ?></div>
    <div class="gh-tags">
      <span class="gh-tag">#<?= clean($r['subject_code']) ?></span>
      <span class="gh-tag">#<?= strtolower(str_replace(' ', '', clean($r['type']))) ?></span>
      <span class="gh-tag">#pdf</span>
    </div>
    <div class="gh-card-footer">
      <span class="gh-footer-left">💻 <?= clean($r['subject_code']) ?> &nbsp; <span class="gh-star">⭐ <?= (int)$r['downloads'] ?></span></span>
      <span class="gh-footer-right">📄 PDF</span>
    </div>
    <div class="gh-card-actions">
      <a href="view.php?id=<?= (int)$r['id'] ?>" target="_blank" class="gh-btn-view">👁 View</a>
      <a href="download.php?id=<?= (int)$r['id'] ?>" class="gh-btn-download">⬇ Download</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>