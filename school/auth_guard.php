<?php
/**
 * school/auth_guard.php
 * 
 * Authentication and school context resolver for the School Portal.
 * Included at the top of every page in /school/.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';

require_school();

$user_type = $_SESSION['lsu_login_type'] ?? 'school';
$current_school = null;

if ($user_type === 'school') {
    $semis = $_SESSION['lsu_school_semis'] ?? '';
    $cnic  = $_SESSION['lsu_cnic'] ?? $_SESSION['lsu_user_handle'] ?? '';

    if (!empty($semis)) {
        $current_school = ExcelDB::getSchoolBySemis($semis);
    }
    if (!$current_school && !empty($cnic)) {
        $current_school = ExcelDB::getSchoolByCnic($cnic);
    }
} elseif ($user_type === 'admin') {
    // Admin viewing school portal
    $targetSemis = trim($_GET['semis'] ?? $_SESSION['lsu_school_semis'] ?? '');
    if (!empty($targetSemis)) {
        $current_school = ExcelDB::getSchoolBySemis($targetSemis);
    }
    if (!$current_school) {
        $allSchools = ExcelDB::all('schools');
        $current_school = $allSchools[0] ?? null;
    }
}

if (!$current_school) {
    // Fallback default school if database empty
    $allSchools = ExcelDB::all('schools');
    if (!empty($allSchools)) {
        $current_school = $allSchools[0];
    } else {
        header('Location: ' . BASE_URL . '/login.php?reason=no_school');
        exit;
    }
}

$school_id       = $current_school['id'] ?? '1';
$school_semis    = $current_school['semis_code'] ?? '403010001';
$school_name     = $current_school['school_name'] ?? 'District Government School';
$hm_name         = $current_school['head_master'] ?? ($_SESSION['lsu_username'] ?? 'Head Master');
$hm_cnic         = $current_school['cnic'] ?? ($_SESSION['lsu_cnic'] ?? 'N/A');
$hm_phone        = $current_school['phone'] ?? '';
$school_taluka   = $current_school['taluka'] ?? 'Tando Allahyar';
$school_level    = $current_school['level'] ?? 'Primary';
$school_gender   = $current_school['gender'] ?? 'Co-education';
$school_address  = $current_school['address'] ?? '';
$school_logo     = $current_school['logo'] ?? '';
if (empty($school_logo) && isset($_SESSION['lsu_avatar']) && !empty($_SESSION['lsu_avatar'])) {
    $school_logo = $_SESSION['lsu_avatar'];
}
