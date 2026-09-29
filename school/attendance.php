<?php
/**
 * school/attendance.php - Feature removed
 *
 * Daily student attendance tracking has been discontinued.
 * Redirects to school dashboard.
 */
require_once __DIR__ . '/auth_guard.php';

header('Location: /LSU-PORTAL/school/dashboard.php');
exit;
