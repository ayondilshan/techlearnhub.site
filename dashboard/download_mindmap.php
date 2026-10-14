<?php
// Streams a saved mind-map (owned by the logged-in user) as a downloadable
// .txt outline. Place this file in the same folder as my_mindmaps.php.
//
// NOTE: this auto-detects which column in `mindmaps` holds the saved tree
// JSON, trying a few common names. If your schema uses a different column,
// add it to $dataCols below (or tell me the real column name and I'll
// hard-code it).

require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Missing mind-map id.');
}

$stmt = $pdo->prepare("SELECT * FROM mindmaps WHERE id=? AND user_id=?");
$stmt->execute([$id, $_SESSION['user_id']]);
$mindmap = $stmt->fetch();

if (!$mindmap) {
    http_response_code(404);
    exit('Mind-map not found.');
}

$dataCols = ['data', 'tree', 'tree_json', 'content', 'mindmap_data', 'structure'];
$raw = null;
foreach ($dataCols as $c) {
    if (!empty($mindmap[$c])) { $raw = $mindmap[$c]; break; }
}

if (!$raw) {
    http_response_code(404);
    exit('No saved content for this mind-map yet.');
}

$tree = json_decode($raw, true);
if (!$tree || !isset($tree['title'])) {
    http_response_code(500);
    exit('Saved mind-map data is not in the expected format.');
}

// Build a plain-text outline (same shape as the in-browser download).
$lines = [];
$lines[] = $tree['title'];
foreach (($tree['children'] ?? []) as $branch) {
    $lines[] = '  - ' . $branch['title'];
    foreach (($branch['children'] ?? []) as $kid) {
        $lines[] = '      - ' . $kid['title'];
    }
}
$outline = implode("\n", $lines) . "\n";

$filename = preg_replace('/[^a-z0-9]/i', '_', $tree['title']) . '_mindmap.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($outline));
echo $outline;
exit;