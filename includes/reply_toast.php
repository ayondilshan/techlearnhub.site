<?php
/*
  Header.php එකේ <body> tag එකට පස්සේ include කරන්න:
  <?php require_once __DIR__ . '/reply_toast.php'; ?>

  Login වෙලා ඉන්න user කෙනෙක්ට admin reply එකක් ලැබිලා තියෙනවා නම්,
  ඕනෑම page එකක උඩ දකුණු කෙළවරේ notification එකක් පෙන්නනවා. Email යවන්නේ නැහැ.
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/reply_helpers.php';

$__toastReplies = [];
if (function_exists('is_logged_in') && is_logged_in() && isset($pdo)) {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
    }
    $__toastReplies = cm_fetch_unread(5);
}

if ($__toastReplies):
    $__toastSig = md5(implode(',', array_column($__toastReplies, 'id')) . $__toastReplies[0]['replied_at']);
?>
<style>
  #replyToast { position: fixed; top: 72px; right: 16px; width: 340px; max-width: calc(100vw - 24px);
    z-index: 99999; background: #fff; border: 1px solid #c7d2fe; border-left: 5px solid #4f46e5;
    border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.18); font-family: inherit; animation: rtDrop .35s ease-out; }
  @keyframes rtDrop { from { opacity: 0; transform: translateY(-14px); } to { opacity: 1; transform: none; } }
  @media (prefers-reduced-motion: reduce) { #replyToast { animation: none; } }
  #replyToast .rt-head { display: flex; align-items: center; gap: 8px; padding: 12px 14px 4px; }
  #replyToast .rt-title { font-weight: 600; flex: 1; font-size: 15px; }
  #replyToast .rt-close { background: none; border: 0; font-size: 20px; line-height: 1; cursor: pointer; color: #666; padding: 2px 6px; }
  #replyToast .rt-body { padding: 4px 14px 12px; font-size: 14px; line-height: 1.5; color: #333; }
  #replyToast .rt-item { margin-bottom: 8px; }
  #replyToast .rt-sub { font-size: 12px; color: #777; }
  #replyToast .rt-text { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-wrap; word-wrap: break-word; }
  #replyToast .rt-item:not(:first-child) { display: none; }
  #replyToast.open .rt-text { -webkit-line-clamp: unset; display: block; }
  #replyToast.open .rt-item:not(:first-child) { display: block; }
  #replyToast .rt-actions { display: flex; gap: 8px; margin-top: 6px; }
  #replyToast .rt-btn { padding: 7px 12px; border-radius: 8px; border: 1px solid #4f46e5; background: #fff; color: #4f46e5; cursor: pointer; font-size: 13px; }
  #replyToast .rt-btn.primary { background: #4f46e5; color: #fff; }
  #replyToast .rt-btn:focus-visible, #replyToast .rt-close:focus-visible { outline: 2px solid #4f46e5; outline-offset: 2px; }
</style>
<div id="replyToast" role="status" aria-live="polite" data-sig="<?= clean($__toastSig) ?>" hidden>
  <div class="rt-head">
    <span class="rt-title">🔔 Admin reply එකක් ලැබිලා (<?= count($__toastReplies) ?>)</span>
    <button type="button" class="rt-close" id="rtClose" aria-label="Close">&times;</button>
  </div>
  <div class="rt-body">
    <?php foreach ($__toastReplies as $r): ?>
      <div class="rt-item">
        <div class="rt-sub"><?= clean($r['subject'] ?: '(No subject)') ?> · <?= clean(date('Y-m-d', strtotime($r['replied_at']))) ?></div>
        <div class="rt-text"><?= clean($r['reply']) ?></div>
      </div>
    <?php endforeach; ?>
    <div class="rt-actions">
      <button type="button" class="rt-btn" id="rtToggle">සම්පූර්ණයෙන් බලන්න</button>
      <button type="button" class="rt-btn primary" id="rtRead">Read කළා</button>
    </div>
  </div>
</div>
<script>
(function () {
  var t = document.getElementById('replyToast');
  if (!t) return;
  var sig = t.getAttribute('data-sig'), key = 'replyToastHidden';
  // 🔔 icon එකෙන් toast එක ආපහු පෙන්නන්න
  window.showReplyToast = function () {
    t.hidden = false;
    try { sessionStorage.removeItem(key); } catch (e) {}
  };
  try { if (sessionStorage.getItem(key) === sig) return; } catch (e) {}
  t.hidden = false;

  document.getElementById('rtClose').onclick = function () {
    t.hidden = true;
    try { sessionStorage.setItem(key, sig); } catch (e) {}
  };
  document.getElementById('rtToggle').onclick = function () {
    t.classList.toggle('open');
    this.textContent = t.classList.contains('open') ? 'හකුලන්න' : 'සම්පූර්ණයෙන් බලන්න';
  };
  document.getElementById('rtRead').onclick = function () {
    var fd = new FormData();
    fd.append('csrf', <?= json_encode($_SESSION['csrf']) ?>);
    fetch('<?= defined('BASE_URL') ? BASE_URL : '/' ?>mark_replies_seen.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function () { t.hidden = true; })
      .catch(function () { t.hidden = true; });
  };
})();
</script>
<?php endif; ?>