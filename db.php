<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$is_local = isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_local) {
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "techlearn_hub";
} else {
    $servername = "sql102.infinityfree.com";
    $username   = "if0_43012076";
    $password   = "24iZP592JBg";
    $dbname     = "if0_43012076_techlearn_hub";
}

// 1. MySQLi Connection ($conn)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("MySQLi Connection failed: " . $conn->connect_error);
}

// 2. PDO Connection ($pdo)
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}