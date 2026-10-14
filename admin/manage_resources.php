<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_admin();
$pageTitle = 'Manage Resources';

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id=?");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $filePath = UPLOAD_DIR . basename($row['file_path']);
        if (file_exists($filePath)) unlink($filePath);
        $pdo->prepare("DELETE FROM resources WHERE id=?")->execute([$id]);
        set_flash('success', 'Resource deleted.');
    }
    header('Location: manage_resources.php');
    exit;
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (!$title || !$subjectId || !$type || empty($_FILES['file']['name'])) {
        set_flash('error', 'All fields including the PDF file are required.');
    } elseif ($_FILES['file']['type'] !== 'application/pdf' && !str_ends_with(strtolower($_FILES['file']['name']), '.pdf')) {
        set_flash('error', 'Only PDF files are allowed.');
    } elseif ($_FILES['file']['size'] > MAX_FILE_SIZE) {
        set_flash('error', 'File too large. Max 20MB.');
    } else {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $_FILES['file']['name']);
        $dest = UPLOAD_DIR . $safeName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $stmt = $pdo->prepare("INSERT INTO resources (subject_id, type, title, description, file_path, file_size, uploaded_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$subjectId, $type, $title, $description, $safeName, $_FILES['file']['size'], $_SESSION['user_id']]);
            set_flash('success', 'Resource uploaded successfully.');
        } else {
            set_flash('error', 'File upload failed.');
        }
    }
    header('Location: manage_resources.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$types = ['Notes','Past Papers','Tutorials','Marking Schemes','Unit Papers'];
$resources = $pdo->query("SELECT r.*, s.code subject_code FROM resources r JOIN subjects s ON r.subject_id=s.id ORDER BY r.created_at DESC")->fetchAll();
?>
<div class="dashboard-layout">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <section>
    <h1>Manage Resources</h1>
    <?php require_once __DIR__ . '/../includes/alerts.php'; ?>

    <div class="form-box" style="max-width:100%; margin:20px 0;">
      <h2>Upload New Resource</h2>
      <form method="post" enctype="multipart/form-data">
        <div class="form-group">
          <label>Title</label>
          <input type="text" name="title" required>
        </div>
        <div class="form-group">
          <label>Subject</label>
          <select name="subject_id" required>
            <option value="">Select subject</option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= clean($s['code']) ?> - <?= clean($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type" required>
            <?php foreach ($types as $t): ?>
              <option value="<?= clean($t) ?>"><?= clean($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Description (optional)</label>
          <textarea name="description" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>PDF File (max 20MB)</label>
          <input type="file" name="file" accept="application/pdf" required>
        </div>
        <button type="submit" class="btn">Upload</button>
      </form>
    </div>

    <h2 style="margin-bottom:12px;">Existing Resources</h2>
    <table>
      <tr><th>Title</th><th>Subject</th><th>Type</th><th>Downloads</th><th>Uploaded</th><th></th></tr>
      <?php foreach ($resources as $r): ?>
      <tr>
        <td><?= clean($r['title']) ?></td>
        <td><?= clean($r['subject_code']) ?></td>
        <td><?= clean($r['type']) ?></td>
        <td><?= (int)$r['downloads'] ?></td>
        <td><?= date('Y-m-d', strtotime($r['created_at'])) ?></td>
        <td>
          <a href="../download.php?id=<?= (int)$r['id'] ?>">View</a> |
          <a href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this resource?')" style="color:var(--error)">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
