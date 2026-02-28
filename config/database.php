<?php
// Database configuration — update these values to match your environment
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_name');
define('DB_USER', 'user_name');
define('DB_PASS', 'user_pass');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns an open, charset-configured MySQLi connection.
 * On failure it sends a 500 JSON response and halts execution.
 */
function getDbConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Database connection failed']));
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}
