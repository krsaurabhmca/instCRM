<?php
// config/app.php
ob_start(); // Buffer all output so redirect() works even after HTML has been sent

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!file_exists(__DIR__ . '/db.php')) {
    header("Location: /instcrm/install.php");
    exit;
}

require_once __DIR__ . '/db.php';

// Application constants
define('APP_NAME', 'InstCRM');
define('BASE_URL', 'http://localhost/instcrm');

// Sanitization helpers
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Redirect helper
function redirect($url) {
    if (strpos($url, '/') === 0) {
        $url = BASE_URL . $url;
    }
    header("Location: " . $url);
    exit;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Require user to be logged in
function require_login() {
    if (!is_logged_in()) {
        redirect('/auth/login.php');
    }
}

// Require user to be an admin
function require_admin() {
    require_login();
    if (($_SESSION['user_role'] ?? '') !== 'Admin') {
        set_flash_message('danger', 'Access denied. Administrator privileges required.');
        redirect('/dashboard.php');
    }
}

// Get logged-in user details
function current_user() {
    return $_SESSION['user'] ?? null;
}

// Get tenant ID
function get_tenant_id() {
    return $_SESSION['tenant_id'] ?? null;
}

// CSRF Token generation & checking
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Flash messages helpers
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icons = [
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-exclamation-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info'    => 'bi-info-circle-fill',
        ];
        $icon = $icons[$flash['type']] ?? 'bi-info-circle-fill';
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . '" role="alert">
                <i class="bi ' . $icon . '"></i>
                ' . htmlspecialchars($flash['message']) . '
              </div>';
    }
}
?>
