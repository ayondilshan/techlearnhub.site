<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
header('Content-Type: application/json');

function voter_id() {
    if (!empty($_SESSION['user_id'])) {
        return 'user_' . $_SESSION['user_id'];
    }
    if (empty($_COOKIE['tlh_voter'])) {
        $id = 'anon_' . bin2hex(random_bytes(16));
        setcookie('tlh_voter', $id, time() + 60 * 60 * 24 * 365, '/');
        $_COOKIE['tlh_voter'] = $id;
        return $id;
    }
    return $_COOKIE['tlh_voter'];
}

function tool_state($pdo, $tool, $voter) {
    $totalStmt = $pdo->prepare("SELECT COUNT(*) c FROM tool_stars WHERE tool_slug = ?");
    $totalStmt->execute([$tool]);
    $total = (int)$totalStmt->fetch()['c'];

    $mineStmt = $pdo->prepare("SELECT 1 FROM tool_stars WHERE tool_slug = ? AND voter_id = ?");
    $mineStmt->execute([$tool, $voter]);
    $starred = (bool)$mineStmt->fetch();

    return ['total' => $total, 'starred' => $starred];
}

$tool = trim($_SERVER['REQUEST_METHOD'] === 'GET' ? ($_GET['tool'] ?? '') : (json_decode(file_get_contents('php://input'), true)['tool'] ?? ''));

if (!$tool) {
    echo json_encode(['success' => false, 'error' => 'Missing tool.']);
    exit;
}

$voter = voter_id();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $state = tool_state($pdo, $tool, $voter);
        echo json_encode(['success' => true] + $state);
        exit;
    }

    // POST = toggle star on/off for this voter
    $checkStmt = $pdo->prepare("SELECT 1 FROM tool_stars WHERE tool_slug = ? AND voter_id = ?");
    $checkStmt->execute([$tool, $voter]);

    if ($checkStmt->fetch()) {
        $del = $pdo->prepare("DELETE FROM tool_stars WHERE tool_slug = ? AND voter_id = ?");
        $del->execute([$tool, $voter]);
    } else {
        $ins = $pdo->prepare("INSERT INTO tool_stars (tool_slug, voter_id, created_at) VALUES (?, ?, NOW())");
        $ins->execute([$tool, $voter]);
    }

    $state = tool_state($pdo, $tool, $voter);
    echo json_encode(['success' => true] + $state);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Could not update star.']);
}