<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SITE_NAME', 'TechLearn Hub');

// Localhost සහ Live Server සඳහා BASE_URL එක ඉබේම සැකසීම
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    define('BASE_URL', 'http://localhost/techlearn-hub/');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? '';
    define('BASE_URL', $protocol . $host . '/');
}

// Resource PDFs Upload කරන Folder එක
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB

/**
 * ---------------------------------------------------------
 * AI API KEY (Google Gemini)
 * ---------------------------------------------------------
 */
define('AI_API_KEY', 'AQ.Ab8RN6JZwsziYTfcPOmefr9lcBU6I80yemxsLgk38g9r0dORIw');
define('AI_MODEL', 'gemini-3.5-flash-lite');
define('AI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL . ':generateContent');

// Root Folder එකේ ඇති db.php සම්බන්ධ කිරීම
require_once __DIR__ . '/../db.php';

// ---- Helpers Functions ----
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
}

function clean($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function set_flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function subjectIcon($code) {
    return match($code) {
        'SFT' => '🦠',
        'ICT' => '💻',
        'ET' => '🔧',
        'BST' => '🌱',
        'GIT' => '🖥️',
        'English' => '📖',
        default => '📚'
    };
}

function subjectColorClass($code) {
    return match($code) {
        'SFT' => 'subj-purple',
        'ICT' => 'subj-blue',
        'ET' => 'subj-orange',
        'BST' => 'subj-green',
          'GIT' => 'subj-blue',
        'English' => 'subj-pink',
        default => 'subj-blue'
    };
}
?> 