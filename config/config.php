<?php
/**
 * LSU Portal — Application Configuration
 * 
 * Configured for Tando Allahyar District (SELD, Sindh).
 * Supports dynamic configuration loaded from data/settings.csv.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Path Helpers & Excel Database Layer
// ─────────────────────────────────────────────────────────────────────────────
if (!defined('ROOT_PATH'))     define('ROOT_PATH',     dirname(__DIR__));
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', ROOT_PATH . '/includes');
if (!defined('ADMIN_PATH'))    define('ADMIN_PATH',    ROOT_PATH . '/admin');
if (!defined('DATA_PATH'))     define('DATA_PATH',     ROOT_PATH . '/data');

// Dynamic BASE_URL detection (works seamlessly on root domain or subdirectory)
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = preg_replace('#/(admin|school|includes|scratch|data|assets)(/.*)?$#i', '', $scriptDir);
    $baseUrl = ($scriptDir === '/' || $scriptDir === '.' || empty($scriptDir)) ? '' : rtrim($scriptDir, '/');
    define('BASE_URL', $baseUrl);
}

// Load Excel Database Engine
require_once INCLUDES_PATH . '/excel_db.php';
ExcelDB::init();

// ─────────────────────────────────────────────────────────────────────────────
// Dynamic App Identity from Excel Settings
// ─────────────────────────────────────────────────────────────────────────────
$dynamicSettings = ExcelDB::getSettings();

define('APP_NAME',            $dynamicSettings['app_name'] ?? 'District RSU Portal');
define('APP_DEPARTMENT',      $dynamicSettings['department'] ?? 'School Education & Literacy Department');
define('APP_DISTRICT',        $dynamicSettings['district'] ?? 'Tando Allahyar District');
define('APP_GOVT',            $dynamicSettings['govt'] ?? 'Government of Sindh');
define('ACADEMIC_YEAR',       $dynamicSettings['academic_year'] ?? '2026-2027');
define('ATTENDANCE_TARGET',   (int)($dynamicSettings['attendance_target'] ?? 95));
define('HIGH_RISK_THRESHOLD', (int)($dynamicSettings['high_risk_threshold'] ?? 60));
define('CONTACT_EMAIL',       $dynamicSettings['contact_email'] ?? 'deo.tandoallahyar@seld.gos.pk');
define('CONTACT_PHONE',       $dynamicSettings['contact_phone'] ?? '+92 22 3892401');

// ─────────────────────────────────────────────────────────────────────────────
// Temporary Admin Credentials (fallback)
// ─────────────────────────────────────────────────────────────────────────────
define('ADMIN_USERNAME',      'admin');
define('ADMIN_PASSWORD_HASH', '$2y$12$HNLhVquFLCVCMiZivNkOP.Feq35F5sR4CCIqGKG7PSKGk9XGjaCqm');

// ─────────────────────────────────────────────────────────────────────────────
// Session Configuration
// ─────────────────────────────────────────────────────────────────────────────
define('SESSION_NAME',    'lsu_portal_session');
define('SESSION_TIMEOUT', 7200); // 2 hours in seconds

// ─────────────────────────────────────────────────────────────────────────────
// Database Placeholders
// ─────────────────────────────────────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'lsu_portal');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');
