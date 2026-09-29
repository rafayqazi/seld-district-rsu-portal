<?php
/**
 * school/attendance.php - Feature removed
 *
 * Daily student attendance tracking has been discontinued.
 * Redirects to school dashboard.
 */
require_once __DIR__ . '/auth_guard.php';

header('Location: ' . BASE_URL . '/school/dashboard.php');
exit;
