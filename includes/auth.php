<?php
/**
 * includes/auth.php
 * 
 * Session authentication guard.
 * Include this at the TOP of every protected page.
 * 
 * Usage:
 *   require_once dirname(__DIR__) . '/includes/auth.php';
 */

// Load config if not already loaded
if (!defined('APP_NAME')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// Configure secure session cookie settings (must be before session_start)
$httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
    (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $httpsEnabled,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ────────────────────────────────────────────────────────────────
// Session Timeout Check
// ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}

$sessionAge = time() - (int)$_SESSION['created_at'];
if ($sessionAge > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?reason=timeout');
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_regenerate_id(true);
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?reason=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// ────────────────────────────────────────────────────────────────
// Authentication Check
// ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['lsu_logged_in']) || $_SESSION['lsu_logged_in'] !== true) {
    // Not authenticated — redirect to login
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// ────────────────────────────────────────────────────────────────
// Role check helpers
// ────────────────────────────────────────────────────────────────
function require_role(string $role): void {
    if (!isset($_SESSION['lsu_role']) || $_SESSION['lsu_role'] !== $role) {
        header('Location: ' . BASE_URL . '/login.php?reason=unauthorized');
        exit;
    }
}

function is_admin(): bool {
    return isset($_SESSION['lsu_login_type']) && $_SESSION['lsu_login_type'] === 'admin';
}

function is_school(): bool {
    return isset($_SESSION['lsu_login_type']) && $_SESSION['lsu_login_type'] === 'school';
}

function require_admin(): void {
    if (!is_admin()) {
        if (is_school()) {
            header('Location: ' . BASE_URL . '/school/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/login.php?reason=unauthorized');
        }
        exit;
    }
}

function require_school(): void {
    if (!isset($_SESSION['lsu_logged_in']) || $_SESSION['lsu_logged_in'] !== true) {
        header('Location: ' . BASE_URL . '/login.php?reason=unauthorized');
        exit;
    }
}

/**
 * Escape output to prevent XSS.
 * Always use this when printing user-supplied or session data to the page.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate or retrieve current session CSRF token.
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify submitted CSRF token against session token.
 */
function verify_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
