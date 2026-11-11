<?php
/**
 * Vortex Esports - Central Database & Configuration File
 * Host: 127.0.0.1 | User: root | Pass: root | DB: vortex_esports
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'vortex_esports');
define('APP_NAME', 'Vortex Esports');
define('CURRENCY_SYMBOL', '₹');

// Establish PDO Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If database does not exist or connection fails, offer install redirection
    if (!defined('INSTALLING')) {
        die("<div style='background:#0f172a;color:#f87171;padding:24px;font-family:sans-serif;max-width:500px;margin:50px auto;border-radius:12px;border:1px solid #ef4444;'>
            <h2 style='margin-top:0;'>Database Connection Error</h2>
            <p>Could not connect to MySQL database <strong>" . DB_NAME . "</strong>.</p>
            <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
            <a href='install.php' style='display:inline-block;background:#3b82f6;color:#fff;padding:10px 20px;text-decoration:none;border-radius:8px;font-weight:bold;'>Run Database Installer (install.php)</a>
        </div>");
    }
}

/**
 * Flash message helpers
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success' or 'error'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check User Login Status
 */
function check_user_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Check Admin Login Status
 */
function check_admin_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Get current user wallet balance
 */
function get_wallet_balance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $res = $stmt->fetch();
    return $res ? floatval($res['wallet_balance']) : 0.00;
}

/**
 * Format Currency with Indian Rupee symbol
 */
function format_inr($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

/**
 * Sanitize text input
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>