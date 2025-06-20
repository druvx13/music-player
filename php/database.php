<?php
// php/database.php
// Database configuration
$host = "localhost";
$db = "db_name";
$user = "user_name";
$pass = "user_pass";

// Create connection using MySQLi
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
