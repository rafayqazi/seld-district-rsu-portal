<?php
/**
 * admin/attendance.php - Feature removed
 *
 * Daily student attendance tracking has been discontinued.
 * Redirects to admin dashboard.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

header('Location: /LSU-PORTAL/admin/dashboard.php');
exit;
