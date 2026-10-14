<?php
$servername = "sql102.infinityfree.com";        // Hostname
$username   = "if0_43012076";                  // MySQL Username (vPanel Account Username)
$password   = "24iZP592JBg";    // InfinityFree Account Password
$dbname     = "if0_43012076_techlearn_hub";    // Database Name

// Connection එක සෑදීම
$conn = new mysqli($servername, $username, $password, $dbname);

// Connection එක පරීක්ෂා කිරීම
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>