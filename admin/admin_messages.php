<?php
// DEBUG: 500 error එකේ real reason එක බලන්න මේ දෙක uncomment කරන්න. හරි ගියාම ආපහු comment කරන්න.
// ini_set('display_errors', 1); error_reporting(E_ALL);

// File එක root folder එකේ හෝ admin/ වගේ sub-folder එකක තිබුණත් වැඩ කරන්න
$root = is_file(__DIR__ . '/config/config.php') ? __DIR__ : dirname(__DIR__);
require_once $root . '/config/config.php';
require_once $root . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ------------------------------------------------------------------
   ADMIN CHECK  —  ඔයාගේ existing admin login එකට match වෙන්න මේක වෙනස් කරන්න.
   උදා: $_SESSION['is_admin'], $_SESSION['role'] === 'admin' වගේ.
------------------------------------------------------------------- */
if (!function_exists('cm_is_admin')) {
    function cm_is_admin() {
        if (function_exists('is_admin') && is_admin()) return true;
        return !empty($_SESSION['admin'])
            || !empty($_SESSION['is_admin'])
            || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    }
}

if (!cm_is_admin()) {
    header('Location: ' . (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/login.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
}

$flash = '';

// Delete message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
            $flash = 'Message එක delete කළා.';
        }
    } else {
        $flash = 'Invalid request. Page එක refresh කරලා නැවත try කරන්න.';
    }
}

// Send / update reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    if (hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $id    = (int)($_POST['id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if ($id > 0 && $reply !== '') {
            try {
                $pdo->prepare("UPDATE contact_messages SET reply = ?, replied_at = NOW(), reply_seen = 0 WHERE id = ?")
                    ->execute([$reply, $id]);
                $flash = 'Reply එක save කළා. User login වුණාම site එකේ උඩින් notification එකක් පෙනෙනවා.';
            } catch (Exception $e) {
                $flash = 'Reply save කරන්න බැරි වුණා. notifications_migration.sql එක run කළාද බලන්න.';
            }
        } else {
            $flash = 'Reply එක හිස්ව තියන්න බැහැ.';
        }
    } else {
        $flash = 'Invalid request. Page එක refresh කරලා නැවත try කරන්න.';
    }
}

// Search + pagination
$q       = trim($_GET['q'] ?? '');
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = '';
$params = [];
if ($q !== '') {
    $where  = "WHERE name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?";
    $like   = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages $where");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM contact_messages $where ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('cm_page_url')) {
    function cm_page_url($p, $q) {
        return '?' . http_build_query(array_filter(['q' => $q, 'page' => $p]));
    }
}

$pageTitle = 'Contact Messages';
require_once $root . '/includes/header.php';
?>
<style>
  .msg-wrap { max-width: 980px; margin: 0 auto; padding: 32px 20px; }
  .msg-top { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
  .msg-top h2 { margin: 0; }
  .msg-count { color: #666; font-size: 14px; }
  .msg-search { display: flex; gap: 8px; }
  .msg-search input { padding: 9px 12px; border: 1px solid #ccc; border-radius: 8px; min-width: 220px; }
  .msg-card { background: #fff; border: 1px solid #e3e3e3; border-radius: 10px; margin-bottom: 12px; }
  .msg-card summary { list-style: none; cursor: pointer; padding: 14px 16px; display: flex; gap: 12px; align-items: baseline; flex-wrap: wrap; }
  .msg-card summary::-webkit-details-marker { display: none; }
  .msg-card summary:focus-visible { outline: 2px solid #3b6fd4; outline-offset: 2px; border-radius: 10px; }
  .msg-name { font-weight: 600; }
  .msg-subject { color: #444; flex: 1; min-width: 140px; }
  .msg-date { color: #888; font-size: 13px; margin-left: auto; }
  .msg-body { padding: 0 16px 16px; border-top: 1px solid #eee; }
  .msg-meta { font-size: 14px; color: #555; margin: 12px 0; }
  .msg-text { white-space: pre-wrap; word-wrap: break-word; line-height: 1.6; background: #f8f8f8; padding: 12px 14px; border-radius: 8px; }
  .msg-actions { display: flex; gap: 10px; margin-top: 12px; }
  .msg-btn { padding: 8px 14px; border-radius: 8px; border: 1px solid #ccc; background: #fff; cursor: pointer; text-decoration: none; color: inherit; font-size: 14px; }
  .msg-btn.danger { border-color: #d9534f; color: #b52b27; }
  .msg-empty { text-align: center; color: #777; padding: 48px 0; }
  .msg-pager { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; margin-top: 20px; }
  .msg-pager a, .msg-pager span { padding: 7px 12px; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: inherit; }
  .msg-pager .current { background: #222; color: #fff; border-color: #222; }
</style>

<div class="msg-wrap">
  <div class="msg-top">
    <div>
      <h2>Contact Messages</h2>
      <div class="msg-count"><?= $total ?> message<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' found' : '' ?></div>
    </div>
    <form class="msg-search" method="GET" action="">
      <input type="search" name="q" value="<?= clean($q) ?>" placeholder="Name, email, subject හෝ message එකෙන් search කරන්න">
      <button type="submit" class="msg-btn">Search</button>
      <?php if ($q !== ''): ?><a class="msg-btn" href="?">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= clean($flash) ?></div>
  <?php endif; ?>

  <?php if (!$messages): ?>
    <div class="msg-empty">
      <?= $q !== '' ? 'ඔයාගේ search එකට match වෙන messages නැහැ.' : 'තාම messages ආවේ නැහැ.' ?>
    </div>
  <?php else: ?>
    <?php foreach ($messages as $m): ?>
      <details class="msg-card">
        <summary>
          <span class="msg-name"><?= clean($m['name']) ?></span>
          <span class="msg-subject"><?= clean($m['subject'] ?: '(No subject)') ?></span>
          <span class="msg-date"><?= $m['created_at'] ? clean(date('Y-m-d H:i', strtotime($m['created_at']))) : '' ?></span>
        </summary>
        <div class="msg-body">
          <div class="msg-meta">
            From: <a href="mailto:<?= clean($m['email']) ?>"><?= clean($m['email']) ?></a>
          </div>
          <div class="msg-text"><?= clean($m['message']) ?></div>
          <?php if (!empty($m['reply'])): ?>
            <div class="msg-meta" style="margin-top:14px;">
              ඔයාගේ reply (<?= clean(date('Y-m-d H:i', strtotime($m['replied_at']))) ?>) —
              <?= !empty($m['reply_seen']) ? 'User බැලුවා ✓' : 'User තාම බලලා නැහැ' ?>
            </div>
            <div class="msg-text" style="background:#eef5ff;"><?= clean($m['reply']) ?></div>
          <?php endif; ?>
          <form method="POST" action="" style="margin-top:12px;">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <input type="hidden" name="csrf" value="<?= clean($_SESSION['csrf']) ?>">
            <textarea name="reply" rows="3" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;" placeholder="Reply එක මෙතන ලියන්න..."></textarea>
            <button type="submit" class="msg-btn" style="margin-top:8px;"><?= !empty($m['reply']) ? 'Update reply' : 'Send reply' ?></button>
          </form>
          <div class="msg-actions">
            <a class="msg-btn"
               href="mailto:<?= clean($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'Your message')) ?>">Reply by email</a>
            <form method="POST" action="" onsubmit="return confirm('මේ message එක delete කරන්නද?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <input type="hidden" name="csrf" value="<?= clean($_SESSION['csrf']) ?>">
              <button type="submit" class="msg-btn danger">Delete</button>
            </form>
          </div>
        </div>
      </details>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
      <nav class="msg-pager" aria-label="Pagination">
        <?php if ($page > 1): ?><a href="<?= cm_page_url($page - 1, $q) ?>">Previous</a><?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= cm_page_url($i, $q) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="<?= cm_page_url($page + 1, $q) ?>">Next</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once $root . '/includes/footer.php'; ?>