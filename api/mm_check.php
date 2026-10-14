<?php
// Temporary diagnostic. Upload to api/, open in browser, send me the output, then DELETE it.
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "PHP version: " . PHP_VERSION . "\n";
echo "mbstring: " . (function_exists('mb_strlen') ? 'yes' : 'NO') . "\n";
echo "curl: " . (function_exists('curl_init') ? 'yes' : 'NO') . "\n";
echo "json: " . (function_exists('json_encode') ? 'yes' : 'NO') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n\n";

$files = array('ai_helper.php', '../config/config.php', '../db.php', 'db.php', 'generate_mindmap.php');
foreach ($files as $f) {
    echo str_pad($f, 26) . (is_file(__DIR__ . '/' . $f) ? 'found' : 'MISSING') . "\n";
}
echo "\n";

foreach (array('ai_helper.php', '../config/config.php', '../db.php', 'db.php') as $f) {
    if (is_file(__DIR__ . '/' . $f)) {
        require_once __DIR__ . '/' . $f;
        echo "loaded OK: $f\n";
    }
}
echo "\n";

foreach (array('is_logged_in', 'call_claude', 'extract_json') as $fn) {
    echo str_pad("function $fn", 26) . (function_exists($fn) ? 'yes' : 'NO') . "\n";
}
echo str_pad('\$pdo', 26) . (isset($pdo) ? 'yes' : 'NO') . "\n";

echo "\nDone.\n";