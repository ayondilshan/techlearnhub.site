<?php
/**
 * Simple flash-message system.
 * Call set_flash('success'|'error', 'text') then redirect;
 * include this file wherever you want alerts to render.
 */

if (!empty($_SESSION['flash'])):
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
<div class="alert alert-<?= clean($f['type']) ?>"><?= clean($f['msg']) ?></div>
<?php endif; ?>
