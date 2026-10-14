<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
session_destroy();
header('Location: ' . BASE_URL . 'index.php');
exit;
