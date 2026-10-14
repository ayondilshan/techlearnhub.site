<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)");
    $stmt->execute([$email]);
    echo json_encode(['success' => true, 'message' => 'Subscribed! Thank you.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // duplicate email
        echo json_encode(['success' => true, 'message' => "You're already subscribed!"]);
    } else {
        echo json_encode(['error' => 'Something went wrong. Please try again.']);
    }
}