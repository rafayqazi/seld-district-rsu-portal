<?php
/**
 * admin/at-risk-students.php — REDIRECT STUB
 * This feature has been replaced by School Infrastructure Risk Monitoring.
 * Redirects to admin/at-risk-schools.php
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

header('Location: ' . BASE_URL . '/admin/at-risk-schools.php');
exit;
