<?php
/**
 * school/students.php — REDIRECT STUB
 *
 * Student roster management is deprecated; school-level enrollment is managed directly on the School Profile.
 * This stub safely redirects legacy requests to the School Dashboard.
 */

require_once dirname(__DIR__) . '/config/config.php';
header('Location: ' . BASE_URL . '/school/dashboard.php');
exit;
