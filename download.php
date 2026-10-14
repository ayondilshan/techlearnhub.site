<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
$stmt->execute([$id]);
$resource = $stmt->fetch();

if (!$resource) {
    http_response_code(404);
    die('Resource not found.');
}

$filePath = UPLOAD_DIR . basename($resource['file_path']);
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File missing on server.');
}

// increment download counter
$pdo->prepare("UPDATE resources SET downloads = downloads + 1 WHERE id = ?")->execute([$id]);

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($resource['file_path']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
