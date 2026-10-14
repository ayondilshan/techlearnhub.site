<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 

$email = 'admin@techlearnhub.com';
$password = 'Admin@123';

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
$stmt->execute([$email]);
$user = $stmt->fetch();

echo "<pre>";
if (!$user) {
    echo "❌ NO USER FOUND with that email + role=admin.\n";
    echo "Check: is email typed exactly right in DB? Any extra spaces?\n";
} else {
    echo "✅ User found: " . $user['name'] . "\n";
    echo "Email in DB: [" . $user['email'] . "]\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Status: " . $user['status'] . "\n";
    echo "Password hash: " . $user['password'] . "\n\n";

    if (password_verify($password, $user['password'])) {
        echo "✅ Password MATCHES 'Admin@123'\n";
    } else {
        echo "❌ Password does NOT match 'Admin@123'\n";
    }
}
echo "</pre>";