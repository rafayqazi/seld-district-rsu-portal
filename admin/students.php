<?php
/**
 * admin/students.php — REDIRECT STUB
 *
 * Student Overview has been deprecated in favor of school-level enrollment tracking.
 * This stub safely redirects any legacy or bookmarked requests to the School Directory.
 */

require_once dirname(__DIR__) . '/config/config.php';
header('Location: ' . BASE_URL . '/admin/schools.php');
exit;
