<?php
/**
 * index.php — Portal entry point.
 * Redirects authenticated users to dashboard, others to login.
 */

require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (isset($_SESSION['lsu_logged_in']) && $_SESSION['lsu_logged_in'] === true) {
    if (isset($_SESSION['lsu_login_type']) && $_SESSION['lsu_login_type'] === 'school') {
        header('Location: ' . BASE_URL . '/school/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    }
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
