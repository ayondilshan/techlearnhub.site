<?php
// TEMPORARY diagnostic page. Test කරලා ඉවර වුණාම server එකෙන් DELETE කරන්න.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=UTF-8');

function ok($b) { return $b ? '✅' : '❌'; }
function mask($e) { $p = explode('@', (string)$e); return $p[0] === '' ? '(empty)' : substr($p[0], 0, 2) . '***@' . ($p[1] ?? '?'); }

echo '<pre style="font:14px/1.7 monospace;padding:16px">';

$logged = function_exists('is_logged_in') && is_logged_in();
echo ok($logged) . " is_logged_in()\n";
if (!$logged) { echo "Login වෙලා ආපහු මේ page එක open කරන්න.\n"; exit; }

echo "\nSession keys: " . implode(', ', array_keys($_SESSION)) . "\n";

$h = __DIR__ . '/includes/';
echo "\nFiles:\n";
foreach (['reply_helpers.php', 'reply_toast.php', 'header.php'] as $f) {
    echo '  ' . ok(is_file($h . $f)) . " includes/$f\n";
}
echo '  ' . ok(is_file(__DIR__ . '/mark_replies_seen.php')) . " mark_replies_seen.php\n";
$hdr = is_file($h . 'header.php') ? file_get_contents($h . 'header.php') : '';
echo '  ' . ok(strpos($hdr, 'reply_toast.php') !== false) . " header.php එකේ reply_toast include line එක තියෙනවද\n";

require_once $h . 'reply_helpers.php';
$email = cm_user_email();
echo "\nResolved user email: " . ($email !== '' ? mask($email) : '❌ (හිස් - email එක හොයාගන්න බැරි වුණා)') . "\n";
echo "  \$_SESSION['user_id'] = " . (isset($_SESSION['user_id']) ? 'set' : '❌ not set') . "\n";

echo "\nDatabase:\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM contact_messages")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['reply', 'replied_at', 'reply_seen'] as $c) {
        echo '  ' . ok(in_array($c, $cols, true)) . " column `$c`\n";
    }
    if (in_array('reply', $cols, true)) {
        $rows = $pdo->query("SELECT id, email, (reply IS NOT NULL AND reply <> '') AS has_reply, reply_seen FROM contact_messages")->fetchAll(PDO::FETCH_ASSOC);
        echo "\ncontact_messages rows (" . count($rows) . "):\n";
        foreach ($rows as $r) {
            $match = $email !== '' && strtolower($r['email']) === strtolower($email);
            echo "  #{$r['id']} " . mask($r['email']) . ' | reply: ' . ($r['has_reply'] ? 'yes' : 'no')
               . ' | seen: ' . (int)$r['reply_seen'] . ' | ' . ($match ? 'ඔයාගේ email එකට MATCH' : 'match නැහැ') . "\n";
        }
        echo "\nUnread replies for you: " . cm_unread_reply_count() . "\n";
    }
} catch (Exception $e) {
    echo '  ❌ ' . htmlspecialchars($e->getMessage()) . "\n";
}
echo '</pre>';