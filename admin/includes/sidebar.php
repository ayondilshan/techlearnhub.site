<?php $current = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar">
  <a href="index.php" class="<?= $current==='index.php'?'active':'' ?>">📊 Dashboard</a>
  <a href="manage_resources.php" class="<?= $current==='manage_resources.php'?'active':'' ?>">📖 Resources </a>
  <a href="manage_subjects.php" class="<?= $current==='manage_subjects.php'?'active':'' ?>">📚 Subjects</a>
  <a href="manage_learning.php" class="<?= $current==='manage_learning.php'?'active':'' ?>">⚡ MC Lab</a>
      <a href="admin_messages.php" class="<?= $current==='admin_messages.php'?'active':'' ?>">Messages</a>
  <a href="manage_users.php" class="<?= $current==='manage_users.php'?'active':'' ?>">👤 Users</a>
  <a href="../logout.php">🚪 Logout</a>
</aside>
