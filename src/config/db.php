<?php
// src/config/db.php

// Include the user's configuration file.
// It's expected to be located at src/config/config.php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Fallback or error if config.php is missing.
    // This ensures the application doesn't break completely if config.php is not yet created,
    // though API endpoints requiring DB connection will fail.
    // A more robust solution might be to die() with a setup instruction.
    error_log("Database configuration file (src/config/config.php) not found. Please create it from config.php.template.");
    // Define constants with null or default values if not defined to prevent errors later
    if (!defined('DB_HOST')) define('DB_HOST', null);
    if (!defined('DB_NAME')) define('DB_NAME', null);
    if (!defined('DB_USER')) define('DB_USER', null);
    if (!defined('DB_PASS')) define('DB_PASS', null);
    if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
}

/**
 * Establishes a database connection using MySQLi.
 *
 * @return mysqli|null Returns a mysqli connection object on success, or null on failure.
 */
function get_db_connection(): ?mysqli {
    // Check if essential DB constants are defined and not null
    if (DB_HOST === null || DB_NAME === null || DB_USER === null || DB_PASS === null) {
        error_log("Database connection parameters are not fully configured in src/config/config.php.");
        return null;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable error reporting for mysqli

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        // Set character set (important for handling various character encodings)
        if (defined('DB_CHARSET') && !empty(DB_CHARSET)) {
            if (!$conn->set_charset(DB_CHARSET)) {
                error_log("Error loading character set " . DB_CHARSET . ": " . $conn->error);
            }
        }

        return $conn;
    } catch (mysqli_sql_exception $e) {
        error_log("Database Connection Failed: " . $e->getMessage());
        // In a production environment, you might want to log this to a file instead of echoing,
        // or show a generic error page to the user.
        // For development, this is informative.
        // Consider how to handle this for the user - perhaps a friendly error message.
        // For now, returning null and letting the calling code handle it.
        return null;
    }
}

?>
