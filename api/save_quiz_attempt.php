<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$score = (int)($input['score'] ?? 0);
$total = (int)($input['total'] ?? 0);

// attach to the most recent quiz created by this user
$stmt = $pdo->prepare("SELECT id FROM quizzes WHERE user_id=? ORDER BY id DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$quiz = $stmt->fetch();

if ($quiz) {
    $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, score, total) VALUES (?, ?, ?, ?)")
        ->execute([$quiz['id'], $_SESSION['user_id'], $score, $total]);
}

echo json_encode(['ok' => true]);