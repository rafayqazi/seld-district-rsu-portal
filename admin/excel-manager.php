<?php
/**
 * admin/excel-manager.php — Redirect Stub
 * Excel DB Management Hub has been restricted from front-end access.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

header('Location: ' . BASE_URL . '/admin/dashboard.php');
exit;
