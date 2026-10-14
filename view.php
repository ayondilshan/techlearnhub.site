<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);

// MySQLi ($conn) භාවිතයෙන් Query එක සකසමු
$stmt = $conn->prepare("SELECT file_path FROM resources WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$resource = $result->fetch_assoc();

if (!$resource) {
    http_response_code(404);
    die('Resource not found.');
}

$filePath = UPLOAD_DIR . basename($resource['file_path']);
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File missing on server.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($resource['file_path']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>