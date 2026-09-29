<?php
/**
 * admin/school-profile.php — Dynamic School Profile & Administrative Editor
 * 
 * Powered by ExcelDB backend.
 * Displays real-time metrics, leadership details (Head Master & CNIC), facilities,
 * enrollment, attendance rates, and provides complete in-place administrative editing.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'school-profile';
$page_title  = 'School Profile — ' . APP_NAME;

// ─── Fetch School Record ─────────────────────────────────────────────────────
$allSchools = ExcelDB::all('schools');
$semis = trim($_GET['semis'] ?? '');
$school = null;

if (!empty($semis)) {
    $school = ExcelDB::find('schools', 'semis_code', $semis);
}

if (!$school && !empty($allSchools)) {
    $school = $allSchools[0];
    $semis = $school['semis_code'] ?? '';
}

if (!$school) {
    header('Location: ' . BASE_URL . '/admin/schools.php');
    exit;
}

$available_talukas = ExcelDB::getTalukas();
$notification = '';
$notification_type = 'success';

// ─── Handle Admin Edit School Form Submissions ──────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } elseif (isset($_POST['update_staff'])) {
        $targetSemis = trim($_POST['target_semis'] ?? $semis);
        $tchrs  = max(0, (int)($_POST['teachers'] ?? 8));
        $nonTch = max(0, (int)($_POST['non_teaching'] ?? 2));
        ExcelDB::update('schools', 'semis_code', $targetSemis, [
            'teachers'     => (string)$tchrs,
            'non_teaching' => (string)$nonTch,
        ]);
        header('Location: ' . BASE_URL . '/admin/school-profile.php?semis=' . urlencode($targetSemis) . '&msg=staff_updated');
        exit;
    } elseif (isset($_POST['update_facilities'])) {
        $targetSemis = trim($_POST['target_semis'] ?? $semis);
        $rooms = max(0, (int)($_POST['classrooms'] ?? 6));
        $elec  = trim($_POST['facility_electricity'] ?? 'Solar + Grid');
        $water = trim($_POST['facility_water'] ?? 'Filtered Plant');
        $toil  = trim($_POST['facility_toilets'] ?? 'Functional Blocks');
        $wall  = trim($_POST['facility_boundary_wall'] ?? 'Secured & Complete');
        $net   = trim($_POST['facility_internet'] ?? 'Broadband / 4G');
        ExcelDB::update('schools', 'semis_code', $targetSemis, [
            'classrooms'             => (string)$rooms,
            'facility_electricity'   => $elec,
            'facility_water'         => $water,
            'facility_toilets'       => $toil,
            'facility_boundary_wall' => $wall,
            'facility_internet'      => $net,
        ]);
        header('Location: ' . BASE_URL . '/admin/school-profile.php?semis=' . urlencode($targetSemis) . '&msg=facilities_updated');
        exit;
    } elseif (isset($_POST['update_school_profile'])) {
        $targetSemis = trim($_POST['target_semis'] ?? $semis);
        $name    = trim($_POST['school_name'] ?? '');
        $hm      = trim($_POST['head_master'] ?? '');
        $cnic    = trim($_POST['cnic'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $addr    = trim($_POST['address'] ?? '');
        $taluka  = trim($_POST['taluka'] ?? 'Tando Allahyar');
        $level   = trim($_POST['level'] ?? 'Primary');
        $gender  = trim($_POST['gender'] ?? 'Co-education');
        $enroll  = (int)($_POST['enrollment'] ?? 0);
        $att     = trim($_POST['attendance_pct'] ?? '90');
        if (!str_ends_with($att, '%') && is_numeric($att)) $att .= '%';
        $status  = trim($_POST['status'] ?? 'Active');
        $rooms   = max(0, (int)($_POST['classrooms'] ?? 6));
        $tchrs   = max(0, (int)($_POST['teachers'] ?? 8));
        $nonTch  = max(0, (int)($_POST['non_teaching'] ?? 2));
        $elec    = trim($_POST['facility_electricity'] ?? 'Solar + Grid');
        $water   = trim($_POST['facility_water'] ?? 'Filtered Plant');
        $toil    = trim($_POST['facility_toilets'] ?? 'Functional Blocks');
        $wall    = trim($_POST['facility_boundary_wall'] ?? 'Secured & Complete');
        $net     = trim($_POST['facility_internet'] ?? 'Broadband / 4G');

        $badge = 'badge-active';
        if ($status === 'Good') $badge = 'badge-good';
        elseif ($status === 'Needs Attention') $badge = 'badge-attention';
        elseif ($status === 'Not Reporting') $badge = 'badge-not-rep';

        $updateData = [
            'school_name'            => $name,
            'head_master'            => $hm,
            'cnic'                   => $cnic,
            'phone'                  => $phone,
            'address'                => $addr,
            'taluka'                 => $taluka,
            'level'                  => $level,
            'gender'                 => $gender,
            'enrollment'             => (string)$enroll,
            'attendance_pct'         => $att,
            'status'                 => $status,
            'status_badge'           => $badge,
            'classrooms'             => (string)$rooms,
            'teachers'               => (string)$tchrs,
            'non_teaching'           => (string)$nonTch,
            'facility_electricity'   => $elec,
            'facility_water'         => $water,
            'facility_toilets'       => $toil,
            'facility_boundary_wall' => $wall,
            'facility_internet'      => $net,
        ];

        ExcelDB::update('schools', 'semis_code', $targetSemis, $updateData);
        header('Location: ' . BASE_URL . '/admin/school-profile.php?semis=' . urlencode($targetSemis) . '&msg=updated');
        exit;
    } elseif (isset($_POST['update_hm_password'])) {
        $targetSemis = trim($_POST['target_semis'] ?? $semis);
        $newPass     = trim($_POST['new_password'] ?? '');
        if (!empty($targetSemis) && !empty($newPass)) {
            ExcelDB::updateHeadMasterPassword($targetSemis, $newPass);
            header('Location: ' . BASE_URL . '/admin/school-profile.php?semis=' . urlencode($targetSemis) . '&msg=pass_updated');
            exit;
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') {
        $notification = 'School profile information successfully updated and saved!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'pass_updated') {
        $notification = 'Head Master School Portal login password successfully updated!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'staff_updated') {
        $notification = 'Teaching and non-teaching staff counts successfully updated!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'facilities_updated') {
        $notification = 'Facilities and infrastructure status successfully updated!';
        $notification_type = 'success';
    }
}

$hm_credentials = ExcelDB::getHeadMasterCredentials($semis);

// Re-read latest data in case it was updated
$school = ExcelDB::find('schools', 'semis_code', $semis);
$totalEnrollment = (int)($school['enrollment'] ?? 0);
$boysEst = (int)round($totalEnrollment * 0.52);
$girlsEst = max(0, $totalEnrollment - $boysEst);
if (($school['gender'] ?? '') === 'Boys') {
    $boysEst = $totalEnrollment;
    $girlsEst = 0;
} elseif (($school['gender'] ?? '') === 'Girls') {
    $boysEst = 0;
    $girlsEst = $totalEnrollment;
}

$cleanAtt = (int)preg_replace('/[^0-9]/', '', $school['attendance_pct'] ?? '90');
if ($cleanAtt <= 0) $cleanAtt = 90;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($school['school_name'] ?? 'School Profile') ?> — <?= APP_NAME ?></title>
<meta name="description" content="Detailed school profile for District RSU monitoring portal"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s;}.sidebar-link:hover{background:rgba(255,255,255,.08);}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E;}
.sidebar-group-title{font-size:10px;letter-spacing:.1em;text-transform:uppercase;}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.btn-excel{background:#107C41;color:#fff;transition:background .15s;}.btn-excel:hover{background:#0b5c30;}
.status-badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px;}
.badge-active{background:#DCFCE7;color:#15803D;}.badge-good{background:#D1FAE5;color:#065F46;}.badge-attention{background:#FEF3C7;color:#92400E;}.badge-not-rep{background:#F1F5F9;color:#475569;}
.facility-available{background:#DCFCE7;color:#15803D;}.facility-partial{background:#FEF3C7;color:#92400E;}.facility-unavailable{background:#FEE2E2;color:#991B1B;}
.table-row:hover{background:#F8FAFC;}
#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1);}#overlay{transition:opacity .25s;}
</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span class="font-medium tracking-wide"><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="flex-1 overflow-y-auto p-4 md:p-6">
  <nav class="text-xs text-muted mb-4 flex items-center gap-1.5">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="hover:text-primary">Dashboard</a><span>/</span>
    <a href="<?= BASE_URL ?>/admin/schools.php" class="hover:text-primary">Schools</a><span>/</span>
    <span class="text-textMain font-medium"><?= e($school['semis_code'] ?? '') ?></span>
  </nav>

  <?php if (!empty($notification)): ?>
  <div class="mb-5 p-3.5 rounded-lg text-xs <?= $notification_type === 'danger' ? 'bg-rose-50 border border-rose-200 text-rose-800' : 'bg-emerald-50 border border-emerald-200 text-emerald-800' ?> flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      <span><?= e($notification) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">&times;</button>
  </div>
  <?php endif; ?>

  <!-- Profile Header -->
  <div class="bg-surface border border-border rounded-lg p-5 mb-5 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0 text-primary">
          <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div>
          <h1 class="text-lg font-bold text-textMain"><?= e($school['school_name'] ?? 'School Profile') ?></h1>
          <div class="flex flex-wrap items-center gap-2 mt-1">
            <span class="text-xs text-primary font-mono font-bold">SEMIS: <?= e($school['semis_code'] ?? '') ?></span>
            <span class="text-border">|</span>
            <span class="text-xs text-muted"><?= e($school['level'] ?? 'Primary') ?> &mdash; <?= e($school['gender'] ?? 'Co-education') ?></span>
            <span class="text-border">|</span>
            <span class="text-xs text-muted font-medium"><?= e($school['taluka'] ?? '') ?></span>
            <span class="text-border">|</span>
            <span class="status-badge <?= e($school['status_badge'] ?? 'badge-active') ?>"><?= e($school['status'] ?? 'Active') ?></span>
          </div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <!-- Edit School Button -->
        <button onclick="openEditSchoolModal()" class="btn-primary px-3.5 py-2 rounded text-xs font-semibold flex items-center gap-1.5 shadow-sm">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Edit School Info
        </button>
        <a href="<?= BASE_URL ?>/admin/schools.php" class="btn-secondary px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Directory
        </a>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Left Column -->
    <div class="lg:col-span-2 space-y-5">

      <!-- Basic Information & Leadership -->
      <section class="bg-surface border border-border rounded-lg shadow-sm">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
          <h2 class="text-sm font-semibold text-textMain">Basic Information &amp; Leadership</h2>
          <button onclick="openEditSchoolModal()" class="text-xs text-primary hover:text-primaryDark flex items-center gap-1 font-medium transition group">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="group-hover:scale-110 transition-transform"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Modify Details</span>
          </button>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div><div class="text-xs text-muted mb-0.5">School Name</div><div class="font-medium text-textMain"><?= e($school['school_name'] ?? '') ?></div></div>
          <div><div class="text-xs text-muted mb-0.5">SEMIS Code (Numerical ID)</div><div class="font-mono font-bold text-primary"><?= e($school['semis_code'] ?? '') ?></div></div>
          
          <div class="p-3 bg-slate-50 rounded border border-border">
            <div class="text-xs text-muted mb-0.5">Head Master / Principal Name</div>
            <div class="font-semibold text-textMain"><?= e(!empty($school['head_master']) ? $school['head_master'] : 'Not Assigned') ?></div>
          </div>
          <div class="p-3 bg-slate-50 rounded border border-border">
            <div class="text-xs text-muted mb-0.5">Head Master CNIC</div>
            <div class="font-mono font-semibold text-textMain"><?= e(!empty($school['cnic']) ? $school['cnic'] : 'N/A') ?></div>
          </div>

          <div><div class="text-xs text-muted mb-0.5">School Level</div><div class="font-medium"><?= e($school['level'] ?? 'Primary') ?></div></div>
          <div><div class="text-xs text-muted mb-0.5">Gender</div><div class="font-medium"><?= e($school['gender'] ?? 'Co-education') ?></div></div>
          <div><div class="text-xs text-muted mb-0.5">Taluka</div><div class="font-medium"><?= e($school['taluka'] ?? 'Tando Allahyar') ?></div></div>
          <div><div class="text-xs text-muted mb-0.5">District</div><div class="font-medium"><?= APP_DISTRICT ?></div></div>
          <div><div class="text-xs text-muted mb-0.5">Official Contact</div><div class="font-medium"><?= e(!empty($school['phone']) ? $school['phone'] : '+92 22 3892401') ?></div></div>
          <div><div class="text-xs text-muted mb-0.5">Operational Status</div><div><span class="status-badge <?= e($school['status_badge'] ?? 'badge-active') ?>"><?= e($school['status'] ?? 'Active') ?></span></div></div>
          <div class="sm:col-span-2"><div class="text-xs text-muted mb-0.5">Address / Location</div><div class="font-medium"><?= e(!empty($school['address']) ? $school['address'] : 'Main Station Road, Tando Allahyar') ?></div></div>
        </div>
      </section>

      <!-- Enrollment & Staff -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <section class="bg-surface border border-border rounded-lg shadow-sm">
          <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-textMain">Student Enrollment</h2>
            <span class="text-[11px] text-muted">Active Roster</span>
          </div>
          <div class="p-5 space-y-3">
            <div class="flex justify-between items-center"><span class="text-sm text-muted">Boys</span><span class="text-xl font-bold text-primary"><?= number_format($boysEst) ?></span></div>
            <div class="flex justify-between items-center"><span class="text-sm text-muted">Girls</span><span class="text-xl font-bold text-secondary"><?= number_format($girlsEst) ?></span></div>
            <div class="pt-3 border-t border-border flex justify-between items-center"><span class="text-sm font-semibold text-textMain">Total Enrolled</span><span class="text-2xl font-bold text-textMain"><?= number_format($totalEnrollment) ?></span></div>
          </div>
        </section>
        <section class="bg-surface border border-border rounded-lg shadow-sm">
          <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-textMain">Teaching &amp; Staff</h2>
            <button onclick="openEditStaffModal()" class="text-xs text-primary hover:text-primaryDark flex items-center gap-1 font-medium transition group">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="group-hover:scale-110 transition-transform"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              <span>Edit Staff</span>
            </button>
          </div>
          <div class="p-5 space-y-3">
            <div class="flex justify-between items-center"><span class="text-sm text-muted">Teaching Staff</span><span class="text-xl font-bold text-primary"><?= e(!empty($school['teachers']) ? $school['teachers'] : '8') ?></span></div>
            <div class="flex justify-between items-center"><span class="text-sm text-muted">Non-teaching Staff</span><span class="text-xl font-bold text-muted"><?= e(!empty($school['non_teaching']) ? $school['non_teaching'] : '2') ?></span></div>
            <div class="pt-3 border-t border-border flex justify-between items-center"><span class="text-sm font-semibold text-textMain">Student-Teacher Ratio</span><span class="text-lg font-bold text-textMain">1:<?= !empty($school['teachers']) && (int)$school['teachers'] > 0 ? round($totalEnrollment / (int)$school['teachers']) : '28' ?></span></div>
          </div>
        </section>
      </div>

      <!-- Facilities -->
      <section class="bg-surface border border-border rounded-lg shadow-sm">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-textMain">School Facilities &amp; Infrastructure</h2>
            <span class="text-[11px] text-muted hidden sm:inline">&bull; Real-time audit</span>
          </div>
          <button onclick="openEditFacilitiesModal()" class="text-xs text-primary hover:text-primaryDark flex items-center gap-1 font-medium transition group">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="group-hover:scale-110 transition-transform"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Edit Facilities</span>
          </button>
        </div>
        <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
          <?php
          $roomsCount = max(0, (int)($school['classrooms'] ?? 6));
          $roomsLabel = $roomsCount . ($roomsCount === 1 ? ' Room' : ' Rooms');
          $elec = !empty($school['facility_electricity']) ? $school['facility_electricity'] : 'Solar + Grid';
          $water = !empty($school['facility_water']) ? $school['facility_water'] : 'Filtered Plant';
          $toilets = !empty($school['facility_toilets']) ? $school['facility_toilets'] : 'Functional Blocks';
          $wall = !empty($school['facility_boundary_wall']) ? $school['facility_boundary_wall'] : 'Secured & Complete';
          $internet = !empty($school['facility_internet']) ? $school['facility_internet'] : 'Broadband / 4G';

          function getFacBadge($text) {
              $t = strtolower(trim((string)$text));
              if (str_contains($t, 'unavailable') || str_contains($t, 'none') || $t === '0') {
                  return ['status' => 'Unavailable', 'class' => 'facility-unavailable'];
              }
              if (str_contains($t, 'partial') || str_contains($t, 'repair') || str_contains($t, 'construction') || str_contains($t, 'mobile data')) {
                  return ['status' => 'Partial', 'class' => 'facility-partial'];
              }
              return ['status' => 'Available', 'class' => 'facility-available'];
          }

          $facCards = [
            ['Classrooms', $roomsLabel, $roomsCount > 0 ? ['status' => 'Available', 'class' => 'facility-available'] : ['status' => 'Unavailable', 'class' => 'facility-unavailable'], 'bg-blue-50', '#123B63', '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
            ['Electricity', $elec, getFacBadge($elec), 'bg-amber-50', '#D97706', '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'],
            ['Drinking Water', $water, getFacBadge($water), 'bg-sky-50', '#0284C7', '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>'],
            ['Toilets', $toilets, getFacBadge($toilets), 'bg-emerald-50', '#15803D', '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="3" x2="12" y2="21"/>'],
            ['Boundary Wall', $wall, getFacBadge($wall), 'bg-slate-100', '#64748B', '<rect x="2" y="5" width="20" height="14" rx="1"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="2" y1="15" x2="22" y2="15"/><line x1="7" y1="5" x2="7" y2="10"/><line x1="17" y1="5" x2="17" y2="10"/><line x1="12" y1="10" x2="12" y2="15"/><line x1="7" y1="15" x2="7" y2="19"/><line x1="17" y1="15" x2="17" y2="19"/>'],
            ['Internet', $internet, getFacBadge($internet), 'bg-purple-50', '#7E22CE', '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>']
          ];
          foreach ($facCards as $fc): ?>
          <div class="border border-border rounded-lg p-3 text-center bg-surface hover:shadow-sm transition">
            <div class="w-8 h-8 rounded-full <?= $fc[3] ?> flex items-center justify-center mx-auto mb-2">
              <svg width="16" height="16" fill="none" stroke="<?= $fc[4] ?>" stroke-width="2" viewBox="0 0 24 24"><?= $fc[5] ?></svg>
            </div>
            <div class="text-xs font-semibold text-textMain"><?= e($fc[0]) ?></div>
            <div class="text-xs text-muted mt-0.5 truncate font-medium" title="<?= e($fc[1]) ?>"><?= e($fc[1]) ?></div>
            <span class="status-badge <?= e($fc[2]['class']) ?> mt-1 inline-block"><?= e($fc[2]['status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Monitoring History -->
      <section class="bg-surface border border-border rounded-lg shadow-sm">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
          <h2 class="text-sm font-semibold text-textMain">Recent District Monitoring Visits</h2>
          <span class="text-xs text-muted">Academic Year 2026-2027</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead><tr class="border-b border-border bg-background text-muted uppercase tracking-wide text-left">
              <th class="px-5 py-3 font-semibold">Date</th><th class="px-4 py-3 font-semibold">Officer</th>
              <th class="px-4 py-3 font-semibold">Visit Type</th><th class="px-4 py-3 font-semibold text-right">Score</th>
              <th class="px-4 py-3 font-semibold">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-border text-textMain">
              <tr class="table-row"><td class="px-5 py-3">12 Sep 2026</td><td class="px-4 py-3">RSU Monitor A</td><td class="px-4 py-3">Routine Visit</td><td class="px-4 py-3 text-right font-semibold text-success">86/100</td><td class="px-4 py-3"><span class="status-badge badge-good">Good</span></td></tr>
              <tr class="table-row"><td class="px-5 py-3">10 Aug 2026</td><td class="px-4 py-3">RSU Monitor B</td><td class="px-4 py-3">Spot Check</td><td class="px-4 py-3 text-right font-semibold text-success">81/100</td><td class="px-4 py-3"><span class="status-badge badge-active">Active</span></td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Right Column -->
    <div class="space-y-5">
      <section class="bg-surface border border-border rounded-lg p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-textMain mb-4">Attendance Summary</h2>
        <div class="space-y-3">
          <div>
            <div class="flex justify-between text-xs mb-1"><span class="text-muted">Reported Attendance</span><span class="font-semibold text-success"><?= e($school['attendance_pct'] ?? '90%') ?></span></div>
            <div class="w-full bg-border rounded-full h-2"><div class="bg-success h-2 rounded-full" style="width:<?= min(100, $cleanAtt) ?>%"></div></div>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1"><span class="text-muted">Weekly Average</span><span class="font-semibold text-success"><?= max(50, $cleanAtt - 2) ?>%</span></div>
            <div class="w-full bg-border rounded-full h-2"><div class="bg-success h-2 rounded-full" style="width:<?= max(50, $cleanAtt - 2) ?>%"></div></div>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1"><span class="text-muted">Monthly Average</span><span class="font-semibold text-primary"><?= max(50, $cleanAtt - 4) ?>%</span></div>
            <div class="w-full bg-border rounded-full h-2"><div class="bg-primary h-2 rounded-full" style="width:<?= max(50, $cleanAtt - 4) ?>%"></div></div>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-border text-xs text-muted flex items-center justify-between">
          <span>District Target: 95%</span>
          <span class="<?= $cleanAtt >= 90 ? 'text-success' : 'text-warning' ?> font-semibold"><?= $cleanAtt >= 90 ? '✔ On Track' : '▲ Action Needed' ?></span>
        </div>
      </section>

      <!-- School Portal Access & HM Credentials Card -->
      <section class="bg-surface border border-border rounded-lg p-5 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xs">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h2 class="text-sm font-bold text-textMain">School Portal Access</h2>
          </div>
          <span class="status-badge badge-active text-[10px]">Active</span>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <span class="text-muted block text-[11px]">Head Master / Mistress</span>
            <span class="font-semibold text-textMain"><?= e($school['head_master'] ?? 'Not Assigned') ?></span>
          </div>

          <div>
            <span class="text-muted block text-[11px]">Login Username (CNIC)</span>
            <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded px-2.5 py-1.5 mt-0.5">
              <span class="font-mono font-bold text-primary" id="disp-hm-cnic"><?= e($hm_credentials['cnic'] ?? $school['cnic'] ?? 'N/A') ?></span>
              <button type="button" onclick="copyText('disp-hm-cnic', this)" class="text-slate-500 hover:text-primary text-[11px] font-semibold" title="Copy CNIC">Copy</button>
            </div>
          </div>

          <div>
            <span class="text-muted block text-[11px]">Current Password (visible to Admin)</span>
            <div class="flex items-center justify-between bg-emerald-50/60 border border-emerald-200 rounded px-2.5 py-1.5 mt-0.5">
              <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-emerald-950" id="disp-hm-pass"><?= e($hm_credentials['password_plain'] ?? '1122') ?></span>
              </div>
              <div class="flex items-center gap-2">
                <button type="button" onclick="togglePassVisibility('disp-hm-pass', this)" class="text-emerald-800 hover:text-emerald-950 text-[11px] font-semibold">Hide</button>
                <span class="text-emerald-300">|</span>
                <button type="button" onclick="copyText('disp-hm-pass', this)" class="text-emerald-800 hover:text-emerald-950 text-[11px] font-semibold" title="Copy Password">Copy</button>
              </div>
            </div>
          </div>

          <div class="pt-2 flex flex-col gap-2">
            <button onclick="openResetPasswordModal()" class="w-full text-xs font-semibold py-2 px-3 rounded border border-border bg-slate-50 hover:bg-slate-100 text-textMain transition flex items-center justify-center gap-1.5">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span>Change / Reset HM Password</span>
            </button>
            <a href= BASE_URL . '/school/dashboard.php?semis=<?= urlencode($school['semis_code'] ?? '') ?>" target="_blank" class="w-full text-xs font-semibold py-2 px-3 rounded bg-emerald-700 hover:bg-emerald-800 text-white transition text-center flex items-center justify-center gap-1.5 shadow-xs">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              <span>Launch School Portal</span>
            </a>
          </div>
        </div>
      </section>

      <section class="bg-surface border border-border rounded-lg p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-textMain mb-3">Quick Administrative Actions</h2>
        <div class="space-y-2">
          <button onclick="openEditSchoolModal()" class="flex items-center gap-2.5 p-2.5 rounded border border-border hover:bg-background text-sm text-textMain transition w-full text-left font-medium">
            <svg width="14" height="14" fill="none" stroke="#123B63" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit School Information
          </button>
          <a href="<?= BASE_URL ?>/admin/attendance.php" class="flex items-center gap-2.5 p-2.5 rounded border border-border hover:bg-background text-sm text-textMain transition">
            <svg width="14" height="14" fill="none" stroke="#0F766E" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
            View Daily Attendance
          </a>
          <a href="<?= BASE_URL ?>/admin/students.php" class="flex items-center gap-2.5 p-2.5 rounded border border-border hover:bg-background text-sm text-textMain transition">
            <svg width="14" height="14" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Student Roster
          </a>
        </div>
      </section>

      <section class="bg-surface border border-border rounded-lg p-5 shadow-sm text-center">
        <h2 class="text-xs uppercase tracking-wide text-muted mb-2">School Inspection Rating</h2>
        <div class="text-4xl font-bold text-primary"><?= $cleanAtt >= 90 ? 'A' : ($cleanAtt >= 80 ? 'B+' : 'C') ?></div>
        <div class="text-xs text-muted mt-1">SELD District Performance Index</div>
      </section>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>

<!-- Edit School Modal -->
<div id="edit-school-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-xl w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <div>
        <h3 class="text-sm font-bold text-textMain">Edit School Information</h3>
        <p class="text-xs text-muted">Update administrative records, headmaster, and infrastructure</p>
      </div>
      <button onclick="closeEditSchoolModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_school_profile" value="1"/>
      <input type="hidden" name="target_semis" value="<?= e($school['semis_code'] ?? '') ?>"/>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">SEMIS Code</label>
          <input type="text" value="<?= e($school['semis_code'] ?? '') ?>" disabled class="w-full text-xs font-mono border border-border rounded px-3 py-1.5 bg-slate-100 text-muted cursor-not-allowed"/>
          <span class="text-[10px] text-muted">SEMIS Code is fixed</span>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Taluka <span class="text-danger">*</span></label>
          <select name="taluka" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <?php foreach ($available_talukas as $t): ?>
            <option value="<?= e($t) ?>" <?= ($school['taluka'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">School Full Name <span class="text-danger">*</span></label>
        <input type="text" name="school_name" required value="<?= e($school['school_name'] ?? '') ?>" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Head Master Name</label>
          <input type="text" name="head_master" value="<?= e($school['head_master'] ?? '') ?>" placeholder="e.g. Ghulam Mustafa Kumbhar" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Head Master CNIC</label>
          <input type="text" name="cnic" value="<?= e($school['cnic'] ?? '') ?>" placeholder="e.g. 41302-1234567-1" class="w-full text-xs font-mono border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Level</label>
          <select name="level" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <?php foreach (['Primary', 'Middle', 'Secondary', 'Higher Secondary'] as $lvl): ?>
            <option <?= ($school['level'] ?? '') === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Gender</label>
          <select name="gender" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <?php foreach (['Co-education', 'Boys', 'Girls'] as $g): ?>
            <option <?= ($school['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Total Enrollment</label>
          <input type="number" name="enrollment" required value="<?= (int)($school['enrollment'] ?? 0) ?>" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Attendance Rate (%)</label>
          <input type="number" name="attendance_pct" min="0" max="100" value="<?= $cleanAtt ?>" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Status</label>
          <select name="status" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <?php foreach (['Active', 'Good', 'Needs Attention', 'Not Reporting'] as $st): ?>
            <option <?= ($school['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Official Phone</label>
          <input type="text" name="phone" value="<?= e($school['phone'] ?? '') ?>" placeholder="+92 300 0000000" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Classrooms</label>
          <input type="number" name="classrooms" min="0" value="<?= (int)($school['classrooms'] ?? 6) ?>" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Teaching Staff</label>
          <input type="number" name="teachers" min="0" value="<?= (int)($school['teachers'] ?? 8) ?>" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Non-teaching Staff</label>
          <input type="number" name="non_teaching" min="0" value="<?= (int)($school['non_teaching'] ?? 2) ?>" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div class="p-3 bg-slate-50 border border-border rounded space-y-3">
        <div class="text-xs font-bold text-primary">School Facilities &amp; Utilities</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Boundary Wall</label>
            <select name="facility_boundary_wall" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Secured & Complete', 'Partial / Damaged', 'Under Construction', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_boundary_wall'] ?? 'Secured & Complete') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Electricity Supply</label>
            <select name="facility_electricity" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Solar + Grid', 'Grid Only', 'Solar Only', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_electricity'] ?? 'Solar + Grid') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Drinking Water</label>
            <select name="facility_water" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Filtered Plant', 'Handpump / Tap', 'Water Supply Line', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_water'] ?? 'Filtered Plant') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Toilets</label>
            <select name="facility_toilets" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Functional Blocks', 'Needs Repair', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_toilets'] ?? 'Functional Blocks') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-[11px] font-semibold text-textMain mb-1">Internet Connectivity</label>
            <select name="facility_internet" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Broadband / 4G', 'Partial / Mobile Data', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_internet'] ?? 'Broadband / 4G') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">School Address</label>
        <input type="text" name="address" value="<?= e($school['address'] ?? '') ?>" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeEditSchoolModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Teaching & Staff Modal -->
<div id="edit-staff-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-textMain">Edit Teaching &amp; Staff</h3>
          <p class="text-[11px] text-muted">Update staff allocation for SEMIS: <?= e($school['semis_code'] ?? '') ?></p>
        </div>
      </div>
      <button onclick="closeEditStaffModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_staff" value="1"/>
      <input type="hidden" name="target_semis" value="<?= e($school['semis_code'] ?? '') ?>"/>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Teaching Staff <span class="text-danger">*</span></label>
          <input type="number" name="teachers" min="0" required value="<?= (int)($school['teachers'] ?? 8) ?>" class="w-full text-xs font-semibold border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          <span class="text-[10px] text-muted">Teachers count</span>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Non-teaching Staff <span class="text-danger">*</span></label>
          <input type="number" name="non_teaching" min="0" required value="<?= (int)($school['non_teaching'] ?? 2) ?>" class="w-full text-xs font-semibold border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          <span class="text-[10px] text-muted">Support staff count</span>
        </div>
      </div>

      <div class="p-3 bg-blue-50/70 border border-blue-100 rounded text-xs text-primary flex items-start gap-2">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="mt-0.5 flex-shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span>The <strong>Student-Teacher Ratio</strong> will be updated automatically based on current enrollment (<?= number_format($totalEnrollment) ?> students).</span>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeEditStaffModal()" class="btn-secondary px-3.5 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Save Staff Info</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Facilities Modal -->
<div id="edit-facilities-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-lg w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded bg-teal-50 flex items-center justify-center text-teal-700">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-textMain">Edit School Facilities &amp; Infrastructure</h3>
          <p class="text-[11px] text-muted">Update physical assets and amenities for SEMIS: <?= e($school['semis_code'] ?? '') ?></p>
        </div>
      </div>
      <button onclick="closeEditFacilitiesModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_facilities" value="1"/>
      <input type="hidden" name="target_semis" value="<?= e($school['semis_code'] ?? '') ?>"/>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Classrooms (Total Functioning Rooms) <span class="text-danger">*</span></label>
        <div class="relative">
          <input type="number" name="classrooms" min="0" required value="<?= (int)($school['classrooms'] ?? 6) ?>" class="w-full text-xs font-semibold border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          <span class="absolute right-3 top-2 text-xs text-muted">Rooms</span>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Boundary Wall <span class="text-danger">*</span></label>
          <select name="facility_boundary_wall" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
            <?php foreach (['Secured & Complete', 'Partial / Damaged', 'Under Construction', 'Unavailable / None'] as $opt): ?>
            <option value="<?= e($opt) ?>" <?= ($school['facility_boundary_wall'] ?? 'Secured & Complete') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="text-[10px] text-muted">Security perimeter condition</span>
        </div>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Electricity Supply <span class="text-danger">*</span></label>
          <select name="facility_electricity" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
            <?php foreach (['Solar + Grid', 'Grid Only', 'Solar Only', 'Unavailable / None'] as $opt): ?>
            <option value="<?= e($opt) ?>" <?= ($school['facility_electricity'] ?? 'Solar + Grid') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="text-[10px] text-muted">Power connection source</span>
        </div>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Drinking Water <span class="text-danger">*</span></label>
          <select name="facility_water" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
            <?php foreach (['Filtered Plant', 'Handpump / Tap', 'Water Supply Line', 'Unavailable / None'] as $opt): ?>
            <option value="<?= e($opt) ?>" <?= ($school['facility_water'] ?? 'Filtered Plant') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="text-[10px] text-muted">Clean drinking water provision</span>
        </div>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Toilets <span class="text-danger">*</span></label>
          <select name="facility_toilets" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
            <?php foreach (['Functional Blocks', 'Needs Repair', 'Unavailable / None'] as $opt): ?>
            <option value="<?= e($opt) ?>" <?= ($school['facility_toilets'] ?? 'Functional Blocks') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="text-[10px] text-muted">Sanitation blocks status</span>
        </div>

        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-textMain mb-1">Internet Connectivity <span class="text-danger">*</span></label>
          <select name="facility_internet" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
            <?php foreach (['Broadband / 4G', 'Partial / Mobile Data', 'Unavailable / None'] as $opt): ?>
            <option value="<?= e($opt) ?>" <?= ($school['facility_internet'] ?? 'Broadband / 4G') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="text-[10px] text-muted">Digital reporting &amp; learning network</span>
        </div>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeEditFacilitiesModal()" class="btn-secondary px-3.5 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Save Facilities</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset HM Password Modal -->
<div id="reset-password-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <div>
        <h3 class="text-sm font-bold text-textMain">Change / Reset HM Portal Password</h3>
        <p class="text-xs text-muted"><?= e($school['school_name'] ?? '') ?></p>
      </div>
      <button onclick="closeResetPasswordModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_hm_password" value="1"/>
      <input type="hidden" name="target_semis" value="<?= e($school['semis_code'] ?? '') ?>"/>

      <div class="bg-slate-50 border border-slate-200 rounded p-3 text-xs space-y-1">
        <div class="flex justify-between">
          <span class="text-muted">Head Master:</span>
          <span class="font-semibold text-textMain"><?= e($school['head_master'] ?? 'HM') ?></span>
        </div>
        <div class="flex justify-between">
          <span class="text-muted">CNIC (Username):</span>
          <span class="font-mono font-bold text-primary"><?= e($hm_credentials['cnic'] ?? $school['cnic'] ?? '') ?></span>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">New Password <span class="text-danger">*</span></label>
        <div class="flex items-center gap-2">
          <input type="text" id="modal-new-pass" name="new_password" required value="1122" class="w-full text-xs font-mono border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-bold"/>
          <button type="button" onclick="document.getElementById('modal-new-pass').value='1122'" class="text-xs bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold px-2.5 py-2 rounded hover:bg-emerald-100 whitespace-nowrap">
            Set to 1122
          </button>
        </div>
        <span class="text-[10px] text-muted mt-1 block">Default standard password for all schools is <strong>1122</strong></span>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeResetPasswordModal()" class="btn-secondary px-3.5 py-1.5 rounded text-xs font-semibold">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-semibold">Save New Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});

function openEditSchoolModal() {
  document.getElementById('edit-school-modal').classList.remove('hidden');
}
function closeEditSchoolModal() {
  document.getElementById('edit-school-modal').classList.add('hidden');
}

function openEditStaffModal() {
  document.getElementById('edit-staff-modal').classList.remove('hidden');
}
function closeEditStaffModal() {
  document.getElementById('edit-staff-modal').classList.add('hidden');
}

function openEditFacilitiesModal() {
  document.getElementById('edit-facilities-modal').classList.remove('hidden');
}
function closeEditFacilitiesModal() {
  document.getElementById('edit-facilities-modal').classList.add('hidden');
}

function openResetPasswordModal() {
  document.getElementById('reset-password-modal').classList.remove('hidden');
}
function closeResetPasswordModal() {
  document.getElementById('reset-password-modal').classList.add('hidden');
}

function copyText(elemId, btn) {
  const el = document.getElementById(elemId);
  if (!el) return;
  const text = el.innerText || el.textContent;
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerText;
    btn.innerText = 'Copied!';
    btn.classList.add('text-success');
    setTimeout(() => {
      btn.innerText = orig;
      btn.classList.remove('text-success');
    }, 1500);
  });
}

function togglePassVisibility(elemId, btn) {
  const el = document.getElementById(elemId);
  if (!el) return;
  if (!el.dataset.real) {
    el.dataset.real = el.innerText;
  }
  if (el.innerText === '••••••••') {
    el.innerText = el.dataset.real;
    btn.innerText = 'Hide';
  } else {
    el.innerText = '••••••••';
    btn.innerText = 'Show';
  }
}
</script>
</body>
</html>
