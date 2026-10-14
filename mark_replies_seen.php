<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/reply_helpers.php';

header('Content-Type: application/json');

$okToken = isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$okToken || !cm_mark_replies_seen()) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}
echo json_encode(['ok' => true]);