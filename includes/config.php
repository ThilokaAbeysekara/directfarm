<?php

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'directfarm_lk');

define('BASE_URL', 'http://localhost/directfarm/');

function initializeDatabase() {
    $temp_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    if ($temp_conn->connect_error) {
        die("Database connection failed: " . $temp_conn->connect_error);
    }

    // Check if database exists
    $result = $temp_conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");

    if ($result->num_rows === 0) {
        // Database doesn't exist, import SQL file
        $sql_file = dirname(__DIR__) . '/database.sql';

        if (!file_exists($sql_file)) {
            die("Database file not found: " . $sql_file);
        }

        $sql = file_get_contents($sql_file);

        
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                if (!$temp_conn->query($statement)) {
                    die("Error executing SQL: " . $temp_conn->error);
                }
            }
        }
    }

    $temp_conn->close();
}

function getDB() {
    static $conn = null;
    if ($conn === null) {
        // Initialize database if needed
        initializeDatabase();

        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireLogin($redirect = '../index.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

function requireRole($role, $redirect = '../index.php') {
    requireLogin($redirect);
    if (!hasRole($role)) {
        header("Location: $redirect");
        exit;
    }
}

function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($amount) {
    return 'Rs. ' . number_format($amount, 2);
}
?>
