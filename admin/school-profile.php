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
        $rooms       = max(0, (int)($_POST['classrooms'] ?? 6));
        $elec        = trim($_POST['facility_electricity'] ?? 'Solar + Grid');
        $water       = trim($_POST['facility_water'] ?? 'Filtered Plant');
        $toil        = trim($_POST['facility_toilets'] ?? 'Functional Blocks');
        $wall        = trim($_POST['facility_boundary_wall'] ?? 'Secured & Complete');
        $net         = trim($_POST['facility_internet'] ?? 'Broadband / 4G');
        $bldg        = trim($_POST['building_structure'] ?? 'Good Condition');
        $drain       = trim($_POST['drainage_sewerage'] ?? 'Functional Drainage');
        $flood       = trim($_POST['flood_prone'] ?? 'No');
        $furniture   = trim($_POST['furniture_condition'] ?? 'Adequate');

        // Classroom Assets & Equipment
        $boards_func     = max(0, (int)($_POST['boards_functional'] ?? 0));
        $boards_non_func = max(0, (int)($_POST['boards_non_functional'] ?? 0));
        $boards_tot      = $boards_func + $boards_non_func;
        if ($boards_tot === 0 && isset($_POST['boards_total'])) {
            $boards_tot = max(0, (int)$_POST['boards_total']);
        }

        $benches_func     = max(0, (int)($_POST['benches_functional'] ?? 0));
        $benches_non_func = max(0, (int)($_POST['benches_non_functional'] ?? 0));
        $benches_tot      = $benches_func + $benches_non_func;
        if ($benches_tot === 0 && isset($_POST['benches_total'])) {
            $benches_tot = max(0, (int)$_POST['benches_total']);
        }

        $fans_func     = max(0, (int)($_POST['fans_functional'] ?? 0));
        $fans_non_func = max(0, (int)($_POST['fans_non_functional'] ?? 0));
        $fans_tot      = $fans_func + $fans_non_func;
        if ($fans_tot === 0 && isset($_POST['fans_total'])) {
            $fans_tot = max(0, (int)$_POST['fans_total']);
        }

        ExcelDB::update('schools', 'semis_code', $targetSemis, [
            'classrooms'             => (string)$rooms,
            'facility_electricity'   => $elec,
            'facility_water'         => $water,
            'facility_toilets'       => $toil,
            'facility_boundary_wall' => $wall,
            'facility_internet'      => $net,
            'building_structure'     => $bldg,
            'drainage_sewerage'      => $drain,
            'flood_prone'            => $flood,
            'furniture_condition'    => $furniture,
            'boards_total'           => (string)$boards_tot,
            'boards_functional'      => (string)$boards_func,
            'boards_non_functional'  => (string)$boards_non_func,
            'benches_total'          => (string)$benches_tot,
            'benches_functional'     => (string)$benches_func,
            'benches_non_functional' => (string)$benches_non_func,
            'fans_total'             => (string)$fans_tot,
            'fans_functional'        => (string)$fans_func,
            'fans_non_functional'    => (string)$fans_non_func,
        ]);
        ExcelDB::autoFlagSchoolRisks($targetSemis);
        header('Location: ' . BASE_URL . '/admin/school-profile.php?semis=' . urlencode($targetSemis) . '&msg=facilities_updated');
        exit;
    } elseif (isset($_POST['update_school_profile'])) {
        $targetSemis = trim($_POST['target_semis'] ?? $semis);
        $newSemis    = trim($_POST['semis_code'] ?? $targetSemis);
        $name    = trim($_POST['school_name'] ?? '');
        $hm      = trim($_POST['head_master'] ?? '');
        $cnic    = trim($_POST['cnic'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $addr    = trim($_POST['address'] ?? '');
        $taluka  = trim($_POST['taluka'] ?? 'Tando Allahyar');
        $level   = trim($_POST['level'] ?? 'Primary');
        $gender  = trim($_POST['gender'] ?? 'Co-education');
        $boys    = max(0, (int)($_POST['enrollment_boys'] ?? 0));
        $girls   = max(0, (int)($_POST['enrollment_girls'] ?? 0));
        $enroll  = $boys + $girls;
        if ($enroll === 0 && isset($_POST['enrollment'])) {
            $enroll = max(0, (int)$_POST['enrollment']);
        }
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
        $bldg    = trim($_POST['building_structure'] ?? 'Good Condition');
        $drain   = trim($_POST['drainage_sewerage'] ?? 'Functional Drainage');
        $flood   = trim($_POST['flood_prone'] ?? 'No');
        $furn    = trim($_POST['furniture_condition'] ?? 'Adequate');

        // Classroom Assets & Equipment
        $boards_func     = max(0, (int)($_POST['boards_functional'] ?? 0));
        $boards_non_func = max(0, (int)($_POST['boards_non_functional'] ?? 0));
        $boards_tot      = $boards_func + $boards_non_func;
        if ($boards_tot === 0 && isset($_POST['boards_total'])) {
            $boards_tot = max(0, (int)$_POST['boards_total']);
        }

        $benches_func     = max(0, (int)($_POST['benches_functional'] ?? 0));
        $benches_non_func = max(0, (int)($_POST['benches_non_functional'] ?? 0));
        $benches_tot      = $benches_func + $benches_non_func;
        if ($benches_tot === 0 && isset($_POST['benches_total'])) {
            $benches_tot = max(0, (int)$_POST['benches_total']);
        }

        $fans_func     = max(0, (int)($_POST['fans_functional'] ?? 0));
        $fans_non_func = max(0, (int)($_POST['fans_non_functional'] ?? 0));
        $fans_tot      = $fans_func + $fans_non_func;
        if ($fans_tot === 0 && isset($_POST['fans_total'])) {
            $fans_tot = max(0, (int)$_POST['fans_total']);
        }

        // Validate SEMIS Code if changed by Admin
        if ($newSemis !== $targetSemis) {
            if (empty($newSemis) || !ctype_digit($newSemis)) {
                $notification = 'Invalid SEMIS Code. SEMIS Code must contain numerical digits only.';
                $notification_type = 'danger';
            } else {
                $existing = ExcelDB::find('schools', 'semis_code', $newSemis);
                if ($existing && ($existing['semis_code'] ?? '') !== $targetSemis) {
                    $notification = 'SEMIS Code ' . htmlspecialchars($newSemis) . ' is already registered for another school (' . htmlspecialchars($existing['school_name'] ?? '') . ').';
                    $notification_type = 'danger';
                }
            }
        }

        if (empty($notification)) {
            $badge = 'badge-active';
            if ($status === 'Good') $badge = 'badge-good';
            elseif ($status === 'Needs Attention') $badge = 'badge-attention';
            elseif ($status === 'Not Reporting') $badge = 'badge-not-rep';

            $updateData = [
                'semis_code'             => $newSemis,
                'school_name'            => $name,
                'head_master'            => $hm,
                'cnic'                   => $cnic,
                'phone'                  => $phone,
                'address'                => $addr,
                'taluka'                 => $taluka,
                'level'                  => $level,
                'gender'                 => $gender,
                'enrollment'             => (string)$enroll,
                'enrollment_boys'        => (string)$boys,
                'enrollment_girls'       => (string)$girls,
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
                'building_structure'     => $bldg,
                'drainage_sewerage'      => $drain,
                'flood_prone'            => $flood,
                'furniture_condition'    => $furn,
                'boards_total'           => (string)$boards_tot,
                'boards_functional'      => (string)$boards_func,
                'boards_non_functional'  => (string)$boards_non_func,
                'benches_total'          => (string)$benches_tot,
                'benches_functional'     => (string)$benches_func,
                'benches_non_functional' => (string)$benches_non_func,
                'fans_total'             => (string)$fans_tot,
                'fans_functional'        => (string)$fans_func,
                'fans_non_functional'    => (string)$fans_non_func,
            ];

            ExcelDB::update('schools', 'semis_code', $targetSemis, $updateData);

            // If SEMIS Code changed, cascade to related tables
            if ($newSemis !== $targetSemis) {
                // 1. Update users.csv
                $allUsers = ExcelDB::all('users');
                $usersUpdated = false;
                foreach ($allUsers as $idx => $u) {
                    if (($u['school_semis'] ?? '') === $targetSemis) {
                        $allUsers[$idx]['school_semis'] = $newSemis;
                        $usersUpdated = true;
                    }
                }
                if ($usersUpdated) {
                    ExcelDB::writeTable('users', $allUsers);
                }

                // 2. Update school_risks.csv
                $allRisks = ExcelDB::all('school_risks');
                $risksUpdated = false;
                foreach ($allRisks as $idx => $r) {
                    if (($r['semis_code'] ?? '') === $targetSemis) {
                        $allRisks[$idx]['semis_code'] = $newSemis;
                        $risksUpdated = true;
                    }
                }
                if ($risksUpdated) {
                    ExcelDB::writeTable('school_risks', $allRisks);
                }

                // 3. Update complaints.csv
                $allComplaints = ExcelDB::all('complaints');
                $complaintsUpdated = false;
                foreach ($allComplaints as $idx => $c) {
                    if (($c['semis_code'] ?? '') === $targetSemis) {
                        $allComplaints[$idx]['semis_code'] = $newSemis;
                        $complaintsUpdated = true;
                    }
                }
                if ($complaintsUpdated) {
                    ExcelDB::writeTable('complaints', $allComplaints);
                }
            }

            ExcelDB::autoFlagSchoolRisks($newSemis);
            header('Location: ' . BASE_URL . '/admin/school-profile.php?semis=' . urlencode($newSemis) . '&msg=updated');
            exit;
        }
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
        $notification = 'Facilities and infrastructure status updated! Schools at-risk registry has been automatically reviewed and updated.';
        $notification_type = 'success';
    }
}

$hm_credentials = ExcelDB::getHeadMasterCredentials($semis);

// Re-read latest data in case it was updated
$school = ExcelDB::find('schools', 'semis_code', $semis);
$totalEnrollment = (int)($school['enrollment'] ?? 0);
$boysEst  = isset($school['enrollment_boys']) && $school['enrollment_boys'] !== '' ? (int)$school['enrollment_boys'] : null;
$girlsEst = isset($school['enrollment_girls']) && $school['enrollment_girls'] !== '' ? (int)$school['enrollment_girls'] : null;

if ($boysEst === null || $girlsEst === null) {
    if (($school['gender'] ?? '') === 'Boys') {
        $boysEst  = $totalEnrollment;
        $girlsEst = 0;
    } elseif (($school['gender'] ?? '') === 'Girls') {
        $boysEst  = 0;
        $girlsEst = $totalEnrollment;
    } else {
        $boysEst  = (int)round($totalEnrollment * 0.52);
        $girlsEst = max(0, $totalEnrollment - $boysEst);
    }
}
$totalEnrollment = $boysEst + $girlsEst;

$cleanAtt = (int)preg_replace('/[^0-9]/', '', $school['attendance_pct'] ?? '90');
if ($cleanAtt <= 0) $cleanAtt = 90;

// ─── Classroom Assets & Equipment ───────────────────────────────────────────
$boards_func     = max(0, (int)($school['boards_functional'] ?? 0));
$boards_non_func = max(0, (int)($school['boards_non_functional'] ?? 0));
$boards_tot      = isset($school['boards_total']) && $school['boards_total'] !== '' ? max(0, (int)$school['boards_total']) : ($boards_func + $boards_non_func);

$benches_func     = max(0, (int)($school['benches_functional'] ?? 0));
$benches_non_func = max(0, (int)($school['benches_non_functional'] ?? 0));
$benches_tot      = isset($school['benches_total']) && $school['benches_total'] !== '' ? max(0, (int)$school['benches_total']) : ($benches_func + $benches_non_func);

$fans_func     = max(0, (int)($school['fans_functional'] ?? 0));
$fans_non_func = max(0, (int)($school['fans_non_functional'] ?? 0));
$fans_tot      = isset($school['fans_total']) && $school['fans_total'] !== '' ? max(0, (int)$school['fans_total']) : ($fans_func + $fans_non_func);

// ─── School-specific Complaints Summary ──────────────────────────────────────
$allComplaints = ExcelDB::all('complaints');
$schoolComplaints = array_values(array_filter($allComplaints, function($c) use ($semis, $school) {
    return ($c['semis_code'] ?? '') === $semis
        || ($c['school_name'] ?? '') === ($school['school_name'] ?? '');
}));

// Sort by updated_at desc
usort($schoolComplaints, function($a, $b) {
    return strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? '');
});

$cmp_total    = count($schoolComplaints);
$cmp_active   = 0; // Pending + Under Review + In Progress
$cmp_resolved = 0;
$cmp_closed   = 0;
$cmp_urgent   = 0;

foreach ($schoolComplaints as $cmp) {
    $st = strtolower($cmp['status'] ?? '');
    if (in_array($st, ['pending', 'under review', 'in progress'])) $cmp_active++;
    elseif ($st === 'resolved') $cmp_resolved++;
    elseif ($st === 'closed')   $cmp_closed++;
    if (strtolower($cmp['priority'] ?? '') === 'urgent') $cmp_urgent++;
}
// Recent 3 tickets for preview
$cmp_recent = array_slice($schoolComplaints, 0, 3);

// ─── Profile Data Completion & Missing Information Audit ─────────────────────
$completion = ExcelDB::calculateSchoolProfileCompletion($semis);
$schoolStaff = ExcelDB::getStaffBySemis($semis);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($school['school_name'] ?? 'School Profile') ?> — <?= APP_NAME ?></title>
<meta name="description" content="Detailed school profile for District RSU monitoring portal"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif'],mono:['JetBrains Mono','monospace']}}}}</script>
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

@media print {
  body * { visibility: hidden !important; }
  #deficit-notice-printable, #deficit-notice-printable * { visibility: visible !important; }
  #deficit-notice-printable { position: fixed; left: 0; top: 0; width: 100%; height: 100%; margin: 0; padding: 24px; background: #fff !important; z-index: 999999; }
  .no-print { display: none !important; }
}
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
        <!-- Direct Message Button -->
        <a href="<?= BASE_URL ?>/admin/messages.php?compose=1&semis=<?= urlencode($semis) ?>" 
           id="btnDirectMessage"
           class="flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded shadow-sm transition">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="12" y2="13"/></svg>
          Direct Message
        </a>
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
            <div class="flex items-center gap-2">
              <a href="<?= BASE_URL ?>/admin/staff.php?semis=<?= urlencode($semis) ?>" class="text-[11px] text-primary font-bold hover:underline">
                Roster (<?= count($schoolStaff) ?>) &rarr;
              </a>
              <button onclick="openEditStaffModal()" class="text-xs text-primary hover:text-primaryDark flex items-center gap-1 font-medium transition group">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="group-hover:scale-110 transition-transform"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span>Edit</span>
              </button>
            </div>
          </div>
          <div class="p-5 space-y-3">
            <div class="flex justify-between items-center"><span class="text-sm text-muted">Teaching Staff</span><span class="text-xl font-bold text-primary"><?= e(!empty($school['teachers']) ? $school['teachers'] : '8') ?></span></div>
            <div class="flex justify-between items-center"><span class="text-sm text-muted">Non-teaching Staff</span><span class="text-xl font-bold text-muted"><?= e(!empty($school['non_teaching']) ? $school['non_teaching'] : '2') ?></span></div>
            <div class="pt-3 border-t border-border flex justify-between items-center"><span class="text-sm font-semibold text-textMain">Student-Teacher Ratio</span><span class="text-lg font-bold text-textMain">1:<?= !empty($school['teachers']) && (int)$school['teachers'] > 0 ? round($totalEnrollment / (int)$school['teachers']) : '28' ?></span></div>
            <a href="<?= BASE_URL ?>/admin/staff.php?semis=<?= urlencode($semis) ?>" class="block text-center text-xs font-semibold py-1.5 px-2.5 rounded bg-slate-50 border border-border hover:bg-slate-100 text-primary transition mt-1">
              View All <?= count($schoolStaff) ?> Registered Staff in HR Directory &rarr;
            </a>
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

      <!-- Classroom Equipment & Assets Inventory -->
      <section class="bg-surface border border-border rounded-lg shadow-sm">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </div>
            <h2 class="text-sm font-semibold text-textMain">Classroom Equipment &amp; Assets</h2>
            <span class="text-[11px] text-muted hidden sm:inline">&bull; Physical inventory audit</span>
          </div>
          <button onclick="openEditFacilitiesModal()" class="text-xs text-primary hover:text-primaryDark flex items-center gap-1 font-medium transition group">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="group-hover:scale-110 transition-transform"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Update Inventory</span>
          </button>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Writing Boards -->
          <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 space-y-2.5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <div>
                  <div class="text-xs font-bold text-textMain">Writing Boards</div>
                  <div class="text-[10px] text-muted">Black / White Boards</div>
                </div>
              </div>
              <span class="text-sm font-bold font-mono text-govNavy"><?= $boards_tot ?> <span class="text-[10px] font-normal text-muted">Total</span></span>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 text-xs">
              <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-emerald-200">
                <span class="text-[11px] text-emerald-700 font-medium">Functional:</span>
                <span class="font-bold font-mono text-emerald-800"><?= $boards_func ?></span>
              </div>
              <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-rose-200">
                <span class="text-[11px] text-rose-700 font-medium">Non-Func:</span>
                <span class="font-bold font-mono text-rose-800"><?= $boards_non_func ?></span>
              </div>
            </div>
          </div>

          <!-- Student Benches & Desks -->
          <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 space-y-2.5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center font-bold">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 18v3"/><path d="M20 18v3"/><path d="M4 14h16"/><path d="M4 10h16"/><path d="M6 6h12"/></svg>
                </div>
                <div>
                  <div class="text-xs font-bold text-textMain">Student Benches</div>
                  <div class="text-[10px] text-muted">Desks &amp; Benches</div>
                </div>
              </div>
              <span class="text-sm font-bold font-mono text-govNavy"><?= $benches_tot ?> <span class="text-[10px] font-normal text-muted">Total</span></span>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 text-xs">
              <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-emerald-200">
                <span class="text-[11px] text-emerald-700 font-medium">Functional:</span>
                <span class="font-bold font-mono text-emerald-800"><?= $benches_func ?></span>
              </div>
              <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-rose-200">
                <span class="text-[11px] text-rose-700 font-medium">Non-Func:</span>
                <span class="font-bold font-mono text-rose-800"><?= $benches_non_func ?></span>
              </div>
            </div>
          </div>

          <!-- Electric Fans -->
          <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 space-y-2.5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M12 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z"/><path d="M12 12a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/><path d="M12 12a3 3 0 1 0-6 0 3 3 0 0 0 6 0Z"/><circle cx="12" cy="12" r="9"/></svg>
                </div>
                <div>
                  <div class="text-xs font-bold text-textMain">Electric Fans</div>
                  <div class="text-[10px] text-muted">Ceiling / Bracket Fans</div>
                </div>
              </div>
              <span class="text-sm font-bold font-mono text-govNavy"><?= $fans_tot ?> <span class="text-[10px] font-normal text-muted">Total</span></span>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 text-xs">
              <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-emerald-200">
                <span class="text-[11px] text-emerald-700 font-medium">Functional:</span>
                <span class="font-bold font-mono text-emerald-800"><?= $fans_func ?></span>
              </div>
              <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-rose-200">
                <span class="text-[11px] text-rose-700 font-medium">Non-Func:</span>
                <span class="font-bold font-mono text-rose-800"><?= $fans_non_func ?></span>
              </div>
            </div>
          </div>
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

      <!-- ── Profile Data Completion & Missing Information Progress Card ──── -->
      <section class="bg-surface border border-border rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border bg-gradient-to-r from-slate-50 to-surface flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div>
              <h2 class="text-sm font-bold text-textMain">Profile Data Audit</h2>
              <p class="text-[10px] text-muted">SELD Compliance Score</p>
            </div>
          </div>
          <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-full <?= $completion['percentage'] === 100 ? 'bg-emerald-100 text-emerald-800' : ($completion['percentage'] >= 75 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') ?>">
            <?= $completion['percentage'] ?>% Complete
          </span>
        </div>

        <div class="p-5 space-y-4 text-xs">
          <!-- Visual Progress Bar -->
          <div>
            <div class="flex justify-between items-center mb-1.5 text-[11px]">
              <span class="font-semibold text-textMain">Record Verification</span>
              <span class="font-mono text-muted"><?= $completion['completed_fields'] ?> of <?= $completion['total_fields'] ?> Fields</span>
            </div>
            <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
              <div class="h-full rounded-full transition-all duration-500 <?= $completion['percentage'] === 100 ? 'bg-emerald-600' : ($completion['percentage'] >= 75 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= $completion['percentage'] ?>%;"></div>
            </div>
          </div>

          <!-- Missing Fields Status Section -->
          <?php if ($completion['missing_count'] > 0): ?>
            <div class="p-3.5 bg-amber-50/60 border border-amber-200/80 rounded-lg space-y-2">
              <div class="flex items-center justify-between text-amber-900 font-bold text-xs">
                <span class="flex items-center gap-1.5">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                  <?= $completion['missing_count'] ?> Incomplete Data Points
                </span>
                <span class="text-[10px] font-normal text-amber-700 font-mono">Action Required</span>
              </div>
              <ul class="space-y-1.5 mt-2">
                <?php foreach (array_slice($completion['missing_fields'], 0, 4) as $mf): ?>
                  <li class="flex items-start gap-1.5 text-[11px] text-amber-950">
                    <span class="text-danger mt-0.5">&bull;</span>
                    <span class="font-medium"><?= e($mf['label']) ?></span>
                    <span class="text-[9px] px-1 py-0.2 rounded bg-amber-200/70 text-amber-900 font-semibold ml-auto"><?= e($mf['category']) ?></span>
                  </li>
                <?php endforeach; ?>
                <?php if ($completion['missing_count'] > 4): ?>
                  <li class="text-[10px] text-amber-700 italic pt-0.5">+ <?= $completion['missing_count'] - 4 ?> more missing fields...</li>
                <?php endif; ?>
              </ul>
            </div>

            <!-- PDF Deficit Notice Button -->
            <button onclick="openDeficitNoticeModal()" class="w-full text-xs font-semibold py-2.5 px-3 rounded-lg bg-red-600 hover:bg-red-700 text-white transition flex items-center justify-center gap-2 shadow-xs">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              <span>Generate Official Deficit Notice (PDF)</span>
            </button>
          <?php else: ?>
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-900 flex items-center gap-2.5">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-emerald-700 flex-shrink-0"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <div>
                <div class="font-bold text-xs">All Profile Data Complete &amp; Verified</div>
                <div class="text-[10px] text-emerald-700">Identity, facilities, staff roster &amp; demographics fully recorded.</div>
              </div>
            </div>
            <button onclick="openDeficitNoticeModal()" class="w-full text-xs font-semibold py-2 px-3 rounded-lg border border-border bg-slate-50 hover:bg-slate-100 text-slate-700 transition flex items-center justify-center gap-1.5">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <span>View Official Institutional Profile Report</span>
            </button>
          <?php endif; ?>
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
            <a href="<?= BASE_URL ?>/school/dashboard.php?semis=<?= urlencode($school['semis_code'] ?? '') ?>" target="_blank" class="w-full text-xs font-semibold py-2 px-3 rounded bg-emerald-700 hover:bg-emerald-800 text-white transition text-center flex items-center justify-center gap-1.5 shadow-xs">
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
          <a href="<?= BASE_URL ?>/admin/at-risk-schools.php" class="flex items-center gap-2.5 p-2.5 rounded border border-border hover:bg-background text-sm text-textMain transition">
            <svg width="14" height="14" fill="none" stroke="#DC2626" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Infrastructure Risks
          </a>
          <a href="<?= BASE_URL ?>/admin/complaints.php" class="flex items-center gap-2.5 p-2.5 rounded border border-border hover:bg-background text-sm text-textMain transition">
            <svg width="14" height="14" fill="none" stroke="#0F766E" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Complaints &amp; Grievances
          </a>
        </div>
      </section>

      <!-- ── School Grievances & Complaints Summary ──────────────────────── -->
      <section class="bg-surface border border-border rounded-lg shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-gradient-to-r from-slate-50 to-surface">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-sm font-bold text-textMain leading-tight">Grievance & Complaints</h2>
              <p class="text-[10px] text-muted">GRM Ticket Registry for this School</p>
            </div>
          </div>
          <?php if ($cmp_total > 0): ?>
            <span class="text-[11px] font-bold text-white bg-primary px-2 py-0.5 rounded-full">
              <?= $cmp_total ?> Total
            </span>
          <?php else: ?>
            <span class="text-[11px] text-muted bg-slate-100 px-2 py-0.5 rounded-full">No Tickets</span>
          <?php endif; ?>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 gap-0 border-b border-border">
          <!-- Active -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?search=<?= urlencode($semis) ?>&status=Pending"
             class="group flex flex-col items-center justify-center p-4 border-r border-border hover:bg-red-50 transition-colors cursor-pointer">
            <div class="text-3xl font-black <?= $cmp_active > 0 ? 'text-red-600' : 'text-slate-300' ?> group-hover:scale-105 transition-transform">
              <?= $cmp_active ?>
            </div>
            <div class="text-[11px] font-semibold mt-1 <?= $cmp_active > 0 ? 'text-red-500' : 'text-muted' ?>">Active</div>
            <?php if ($cmp_active > 0): ?>
              <span class="text-[9px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full mt-1 font-bold animate-pulse">Needs Action</span>
            <?php else: ?>
              <span class="text-[9px] text-slate-300 mt-1">All clear</span>
            <?php endif; ?>
          </a>

          <!-- Resolved -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?search=<?= urlencode($semis) ?>&status=Resolved"
             class="group flex flex-col items-center justify-center p-4 hover:bg-emerald-50 transition-colors cursor-pointer">
            <div class="text-3xl font-black <?= $cmp_resolved > 0 ? 'text-emerald-600' : 'text-slate-300' ?> group-hover:scale-105 transition-transform">
              <?= $cmp_resolved ?>
            </div>
            <div class="text-[11px] font-semibold mt-1 <?= $cmp_resolved > 0 ? 'text-emerald-600' : 'text-muted' ?>">Resolved</div>
            <span class="text-[9px] text-emerald-400 mt-1">Redressed</span>
          </a>

          <!-- Closed -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?search=<?= urlencode($semis) ?>&status=Closed"
             class="group flex flex-col items-center justify-center p-4 border-r border-t border-border hover:bg-slate-50 transition-colors cursor-pointer">
            <div class="text-3xl font-black <?= $cmp_closed > 0 ? 'text-slate-600' : 'text-slate-300' ?> group-hover:scale-105 transition-transform">
              <?= $cmp_closed ?>
            </div>
            <div class="text-[11px] font-semibold mt-1 text-muted">Closed</div>
            <span class="text-[9px] text-slate-400 mt-1">Archived</span>
          </a>

          <!-- Urgent -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?search=<?= urlencode($semis) ?>&priority=Urgent"
             class="group flex flex-col items-center justify-center p-4 border-t border-border hover:bg-purple-50 transition-colors cursor-pointer">
            <div class="text-3xl font-black <?= $cmp_urgent > 0 ? 'text-purple-700' : 'text-slate-300' ?> group-hover:scale-105 transition-transform">
              <?= $cmp_urgent ?>
            </div>
            <div class="text-[11px] font-semibold mt-1 <?= $cmp_urgent > 0 ? 'text-purple-700' : 'text-muted' ?>">Urgent</div>
            <?php if ($cmp_urgent > 0): ?>
              <span class="text-[9px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full mt-1 font-bold">Priority!</span>
            <?php else: ?>
              <span class="text-[9px] text-slate-300 mt-1">None</span>
            <?php endif; ?>
          </a>
        </div>

        <!-- Recent Tickets Mini List -->
        <?php if (!empty($cmp_recent)): ?>
          <div class="p-3 space-y-2">
            <div class="text-[10px] font-bold text-muted uppercase tracking-wider px-1">Recent Tickets</div>
            <?php foreach ($cmp_recent as $tk):
              $tkSt = strtolower($tk['status'] ?? '');
              $tkBadge = match($tkSt) {
                'pending'      => 'bg-red-100 text-red-700',
                'under review','in progress' => 'bg-amber-100 text-amber-700',
                'resolved'     => 'bg-emerald-100 text-emerald-700',
                'closed'       => 'bg-slate-200 text-slate-600',
                default        => 'bg-slate-100 text-slate-600'
              };
              $tkPr = strtolower($tk['priority'] ?? '');
              $tkPrBadge = $tkPr === 'urgent' ? 'text-red-600 font-bold' : ($tkPr === 'high' ? 'text-amber-600 font-semibold' : 'text-slate-400');
            ?>
              <a href="<?= BASE_URL ?>/admin/complaint-details.php?ticket=<?= urlencode($tk['ticket_no'] ?? '') ?>"
                 class="flex items-center gap-2.5 p-2.5 rounded-lg border border-border hover:border-primary hover:bg-primary/5 transition group">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="font-mono text-[10px] font-bold text-primary group-hover:underline"><?= e($tk['ticket_no'] ?? '') ?></span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded-full font-semibold <?= $tkBadge ?>"><?= e($tk['status'] ?? '') ?></span>
                    <?php if ($tkPr === 'urgent' || $tkPr === 'high'): ?>
                      <span class="text-[9px] <?= $tkPrBadge ?>">● <?= ucfirst($tkPr) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="text-[10px] text-muted truncate mt-0.5"><?= e($tk['subject'] ?? 'No subject') ?></div>
                </div>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-muted group-hover:text-primary flex-shrink-0 transition-colors">
                  <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
              </a>
            <?php endforeach; ?>

            <a href="<?= BASE_URL ?>/admin/complaints.php?search=<?= urlencode($semis) ?>"
               class="flex items-center justify-center gap-1.5 text-[11px] font-semibold text-primary hover:text-primaryDark py-2 mt-1 border border-dashed border-primary/30 hover:border-primary rounded-lg transition">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              View All <?= $cmp_total ?> Tickets for this School
            </a>
          </div>
        <?php else: ?>
          <div class="p-5 text-center">
            <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
              </svg>
            </div>
            <p class="text-[11px] text-muted">No complaints filed by this school yet.</p>
            <a href="<?= BASE_URL ?>/admin/complaints.php" class="text-[11px] text-primary hover:underline font-semibold mt-1 inline-block">Go to Complaints</a>
          </div>
        <?php endif; ?>
      </section>

    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>

<!-- Edit School Modal -->
<div id="edit-school-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-xl max-w-2xl w-full p-6 shadow-2xl relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between pb-3.5 border-b border-border mb-4 sticky top-0 bg-surface z-10">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-textMain">Edit School Information</h3>
          <p class="text-xs text-muted">Update administrative records, headmaster, enrollment & infrastructure</p>
        </div>
      </div>
      <button onclick="closeEditSchoolModal()" class="text-muted hover:text-textMain text-xl leading-none px-1.5 py-0.5 rounded hover:bg-slate-100">&times;</button>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_school_profile" value="1"/>
      <input type="hidden" name="target_semis" value="<?= e($school['semis_code'] ?? '') ?>"/>

      <!-- Basic Identity: SEMIS & Taluka -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">SEMIS Code <span class="text-danger">*</span></label>
          <input type="text" name="semis_code" required pattern="[0-9]+" value="<?= e($school['semis_code'] ?? '') ?>" class="w-full text-xs font-mono font-bold border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          <span class="text-[10px] text-muted">Authorized District Admin can modify SEMIS Code</span>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Taluka <span class="text-danger">*</span></label>
          <select name="taluka" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary">
            <?php foreach ($available_talukas as $t): ?>
            <option value="<?= e($t) ?>" <?= ($school['taluka'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- School Name -->
      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">School Full Name <span class="text-danger">*</span></label>
        <input type="text" name="school_name" required value="<?= e($school['school_name'] ?? '') ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
      </div>

      <!-- Head Master Leadership -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Head Master Name</label>
          <input type="text" name="head_master" value="<?= e($school['head_master'] ?? '') ?>" placeholder="e.g. Ghulam Mustafa Kumbhar" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Head Master CNIC</label>
          <input type="text" name="cnic" value="<?= e($school['cnic'] ?? '') ?>" placeholder="e.g. 41302-1234567-1" class="w-full text-xs font-mono border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <!-- Classification: Level & Gender -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">School Level</label>
          <select name="level" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary">
            <?php foreach (['Primary', 'Middle', 'Secondary', 'Higher Secondary'] as $lvl): ?>
            <option <?= ($school['level'] ?? '') === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Gender Classification</label>
          <select name="gender" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary">
            <?php foreach (['Co-education', 'Boys', 'Girls'] as $g): ?>
            <option <?= ($school['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Enrollment Breakdown (Boys / Girls / Total) -->
      <div class="p-3 bg-slate-50 border border-border rounded-lg space-y-2">
        <div class="text-xs font-bold text-primary flex items-center justify-between">
          <span>Student Enrollment Breakdown</span>
          <span class="text-[10px] text-muted font-normal">Auto-calculates total</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1 flex items-center justify-between">
              <span>Boys</span>
              <span class="text-[10px] text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded font-bold">Boys</span>
            </label>
            <input type="number" id="admin_enrollment_boys" name="enrollment_boys" min="0" value="<?= (int)($school['enrollment_boys'] ?? $boysEst) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-1.5 bg-background focus:outline-none focus:border-primary font-mono" oninput="adminCalcEnrollment()"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1 flex items-center justify-between">
              <span>Girls</span>
              <span class="text-[10px] text-pink-700 bg-pink-50 px-1.5 py-0.5 rounded font-bold">Girls</span>
            </label>
            <input type="number" id="admin_enrollment_girls" name="enrollment_girls" min="0" value="<?= (int)($school['enrollment_girls'] ?? $girlsEst) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-1.5 bg-background focus:outline-none focus:border-primary font-mono" oninput="adminCalcEnrollment()"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1 flex items-center justify-between">
              <span>Total Enrolled</span>
              <span class="text-[10px] text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded font-bold">Auto</span>
            </label>
            <input type="number" id="admin_total_enrollment" name="enrollment" min="0" readonly value="<?= (int)($school['enrollment'] ?? $totalEnrollment) ?>" class="w-full text-xs font-bold text-emerald-800 border border-emerald-300 rounded-lg px-3 py-1.5 bg-emerald-50/50 cursor-not-allowed font-mono"/>
          </div>
        </div>
      </div>

      <!-- Status & Contact -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Operational Status</label>
          <select name="status" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary">
            <?php foreach (['Active', 'Good', 'Needs Attention', 'Not Reporting'] as $st): ?>
            <option <?= ($school['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Official Contact Phone</label>
          <input type="text" name="phone" value="<?= e($school['phone'] ?? '') ?>" placeholder="+92 300 0000000" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <!-- Staff Allocation & Rooms -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Classrooms</label>
          <input type="number" name="classrooms" min="0" value="<?= (int)($school['classrooms'] ?? 6) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary font-mono"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Teaching Staff</label>
          <input type="number" name="teachers" min="0" value="<?= (int)($school['teachers'] ?? 8) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary font-mono"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Non-teaching Staff</label>
          <input type="number" name="non_teaching" min="0" value="<?= (int)($school['non_teaching'] ?? 2) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary font-mono"/>
        </div>
      </div>

      <!-- Basic Facilities & Utilities -->
      <div class="p-3.5 bg-slate-50 border border-border rounded-lg space-y-3">
        <div class="text-xs font-bold text-primary flex items-center gap-1.5">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          School Facilities &amp; Basic Utilities
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Boundary Wall</label>
            <select name="facility_boundary_wall" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Secured & Complete', 'Partial / Damaged', 'Under Construction', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_boundary_wall'] ?? 'Secured & Complete') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Electricity Supply</label>
            <select name="facility_electricity" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Solar + Grid', 'Grid Only', 'Solar Only', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_electricity'] ?? 'Solar + Grid') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Drinking Water</label>
            <select name="facility_water" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Filtered Plant', 'Handpump / Tap', 'Water Supply Line', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_water'] ?? 'Filtered Plant') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Toilets</label>
            <select name="facility_toilets" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Functional Blocks', 'Needs Repair', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_toilets'] ?? 'Functional Blocks') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-[11px] font-semibold text-textMain mb-1">Internet Connectivity</label>
            <select name="facility_internet" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Broadband / 4G', 'Partial / Mobile Data', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['facility_internet'] ?? 'Broadband / 4G') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Extended Infrastructure Assessment -->
      <div class="p-3.5 bg-amber-50/50 border border-amber-200/60 rounded-lg space-y-3">
        <div class="text-xs font-bold text-amber-900 flex items-center justify-between">
          <span class="flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Extended Infrastructure &amp; Risk Assessment
          </span>
          <span class="text-[10px] text-amber-700 font-normal">Auto-flags to At-Risk Registry</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Building Structure Condition</label>
            <select name="building_structure" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Good Condition', 'Needs Repair', 'Dangerous / Unsafe', 'Condemned / Closed'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['building_structure'] ?? 'Good Condition') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Drainage &amp; Sewerage</label>
            <select name="drainage_sewerage" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Functional Drainage', 'Partial Drainage', 'Broken / Blocked', 'Unavailable / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['drainage_sewerage'] ?? 'Functional Drainage') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Flood / Rain Prone Area?</label>
            <select name="flood_prone" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['No', 'Yes'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['flood_prone'] ?? 'No') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Student Furniture Condition</label>
            <select name="furniture_condition" class="w-full text-xs border border-border rounded-lg px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <?php foreach (['Adequate', 'Shortage', 'None Available'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['furniture_condition'] ?? 'Adequate') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Classroom Equipment Inventory (Boards, Benches, Fans) -->
      <div class="p-3.5 bg-slate-50 border border-border rounded-lg space-y-3">
        <div class="text-xs font-bold text-primary flex items-center justify-between">
          <span class="flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            Classroom Equipment &amp; Assets Inventory
          </span>
          <span class="text-[10px] text-muted font-normal">Functional &amp; Non-Functional counts</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          
          <!-- Boards -->
          <div class="p-2.5 bg-white border border-border rounded-lg space-y-2">
            <div class="text-[11px] font-bold text-textMain flex items-center justify-between">
              <span>Writing Boards</span>
              <span class="text-[10px] font-mono text-muted">Total: <strong id="admin_boards_total_disp"><?= $boards_tot ?></strong></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] font-semibold text-emerald-800 mb-0.5">Functional</label>
                <input type="number" id="admin_boards_functional" name="boards_functional" min="0" value="<?= $boards_func ?>" class="w-full text-xs font-mono font-bold border border-emerald-300 rounded px-2 py-1 bg-emerald-50/40 text-emerald-900 focus:outline-none" oninput="adminCalcEquipment()"/>
              </div>
              <div>
                <label class="block text-[10px] font-semibold text-rose-800 mb-0.5">Non-Func</label>
                <input type="number" id="admin_boards_non_functional" name="boards_non_functional" min="0" value="<?= $boards_non_func ?>" class="w-full text-xs font-mono font-bold border border-rose-300 rounded px-2 py-1 bg-rose-50/40 text-rose-900 focus:outline-none" oninput="adminCalcEquipment()"/>
              </div>
            </div>
            <input type="hidden" id="admin_boards_total" name="boards_total" value="<?= $boards_tot ?>"/>
          </div>

          <!-- Benches -->
          <div class="p-2.5 bg-white border border-border rounded-lg space-y-2">
            <div class="text-[11px] font-bold text-textMain flex items-center justify-between">
              <span>Student Benches</span>
              <span class="text-[10px] font-mono text-muted">Total: <strong id="admin_benches_total_disp"><?= $benches_tot ?></strong></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] font-semibold text-emerald-800 mb-0.5">Functional</label>
                <input type="number" id="admin_benches_functional" name="benches_functional" min="0" value="<?= $benches_func ?>" class="w-full text-xs font-mono font-bold border border-emerald-300 rounded px-2 py-1 bg-emerald-50/40 text-emerald-900 focus:outline-none" oninput="adminCalcEquipment()"/>
              </div>
              <div>
                <label class="block text-[10px] font-semibold text-rose-800 mb-0.5">Non-Func</label>
                <input type="number" id="admin_benches_non_functional" name="benches_non_functional" min="0" value="<?= $benches_non_func ?>" class="w-full text-xs font-mono font-bold border border-rose-300 rounded px-2 py-1 bg-rose-50/40 text-rose-900 focus:outline-none" oninput="adminCalcEquipment()"/>
              </div>
            </div>
            <input type="hidden" id="admin_benches_total" name="benches_total" value="<?= $benches_tot ?>"/>
          </div>

          <!-- Fans -->
          <div class="p-2.5 bg-white border border-border rounded-lg space-y-2">
            <div class="text-[11px] font-bold text-textMain flex items-center justify-between">
              <span>Electric Fans</span>
              <span class="text-[10px] font-mono text-muted">Total: <strong id="admin_fans_total_disp"><?= $fans_tot ?></strong></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] font-semibold text-emerald-800 mb-0.5">Functional</label>
                <input type="number" id="admin_fans_functional" name="fans_functional" min="0" value="<?= $fans_func ?>" class="w-full text-xs font-mono font-bold border border-emerald-300 rounded px-2 py-1 bg-emerald-50/40 text-emerald-900 focus:outline-none" oninput="adminCalcEquipment()"/>
              </div>
              <div>
                <label class="block text-[10px] font-semibold text-rose-800 mb-0.5">Non-Func</label>
                <input type="number" id="admin_fans_non_functional" name="fans_non_functional" min="0" value="<?= $fans_non_func ?>" class="w-full text-xs font-mono font-bold border border-rose-300 rounded px-2 py-1 bg-rose-50/40 text-rose-900 focus:outline-none" oninput="adminCalcEquipment()"/>
              </div>
            </div>
            <input type="hidden" id="admin_fans_total" name="fans_total" value="<?= $fans_tot ?>"/>
          </div>

        </div>
      </div>

      <!-- Address -->
      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">School Physical Address</label>
        <input type="text" name="address" value="<?= e($school['address'] ?? '') ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
      </div>

      <!-- Modal Footer -->
      <div class="pt-3.5 border-t border-border flex justify-end gap-2.5 sticky bottom-0 bg-surface pb-1">
        <button type="button" onclick="closeEditSchoolModal()" class="btn-secondary px-4 py-2 rounded-lg text-xs font-medium">Cancel</button>
        <button type="submit" class="btn-primary px-5 py-2 rounded-lg text-xs font-medium flex items-center gap-1.5 shadow-sm">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save Changes
        </button>
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

      <!-- Additional Infrastructure Fields -->
      <div class="pt-3 border-t border-slate-200">
        <div class="text-xs font-bold text-primary mb-3 flex items-center gap-2">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
          Extended Infrastructure Assessment
          <span class="text-[10px] font-normal text-danger bg-red-50 px-1.5 py-0.5 rounded border border-red-200">Triggers Auto-Risk Flagging</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Building Structure Condition <span class="text-danger">*</span></label>
            <select name="building_structure" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
              <?php foreach (['Good Condition', 'Needs Repair', 'Dangerous / Unsafe', 'Condemned / Closed'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['building_structure'] ?? 'Good Condition') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="text-[10px] text-muted">Structural safety of main classroom block</span>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Drainage / Sewerage System</label>
            <select name="drainage_sewerage" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
              <?php foreach (['Functional Drainage', 'Partial Drainage', 'Broken / None'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['drainage_sewerage'] ?? 'Functional Drainage') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="text-[10px] text-muted">Sewerage and drainage availability</span>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Flood / Disaster Prone Area?</label>
            <select name="flood_prone" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
              <?php foreach (['No', 'Yes'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['flood_prone'] ?? 'No') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="text-[10px] text-muted">High-risk zones get escalated automatically</span>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Furniture &amp; Equipment</label>
            <select name="furniture_condition" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary font-medium">
              <?php foreach (['Adequate', 'Shortage', 'None Available'] as $opt): ?>
              <option value="<?= e($opt) ?>" <?= ($school['furniture_condition'] ?? 'Adequate') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="text-[10px] text-muted">Desks, chairs, and teaching equipment</span>
          </div>
        </div>

        <!-- Classroom Equipment Inventory (Boards, Benches, Fans) -->
        <div class="mt-3 pt-3 border-t border-slate-200 space-y-3">
          <div class="text-xs font-bold text-primary flex items-center justify-between">
            <span class="flex items-center gap-1.5">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
              Classroom Equipment &amp; Assets Inventory
            </span>
            <span class="text-[10px] text-muted">Functional &amp; Non-Functional</span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            
            <!-- Boards -->
            <div class="p-2.5 bg-slate-50 border border-border rounded-lg space-y-2">
              <div class="text-[11px] font-bold text-textMain flex items-center justify-between">
                <span>Boards</span>
                <span class="text-[10px] font-mono text-muted">Total: <strong id="fac_boards_total_disp"><?= $boards_tot ?></strong></span>
              </div>
              <div class="grid grid-cols-2 gap-1.5">
                <div>
                  <label class="block text-[10px] font-semibold text-emerald-800 mb-0.5">Func</label>
                  <input type="number" id="fac_boards_functional" name="boards_functional" min="0" value="<?= $boards_func ?>" class="w-full text-xs font-mono font-bold border border-emerald-300 rounded px-1.5 py-1 bg-white text-emerald-900 focus:outline-none" oninput="facCalcEquipment()"/>
                </div>
                <div>
                  <label class="block text-[10px] font-semibold text-rose-800 mb-0.5">Non-Func</label>
                  <input type="number" id="fac_boards_non_functional" name="boards_non_functional" min="0" value="<?= $boards_non_func ?>" class="w-full text-xs font-mono font-bold border border-rose-300 rounded px-1.5 py-1 bg-white text-rose-900 focus:outline-none" oninput="facCalcEquipment()"/>
                </div>
              </div>
              <input type="hidden" id="fac_boards_total" name="boards_total" value="<?= $boards_tot ?>"/>
            </div>

            <!-- Benches -->
            <div class="p-2.5 bg-slate-50 border border-border rounded-lg space-y-2">
              <div class="text-[11px] font-bold text-textMain flex items-center justify-between">
                <span>Benches</span>
                <span class="text-[10px] font-mono text-muted">Total: <strong id="fac_benches_total_disp"><?= $benches_tot ?></strong></span>
              </div>
              <div class="grid grid-cols-2 gap-1.5">
                <div>
                  <label class="block text-[10px] font-semibold text-emerald-800 mb-0.5">Func</label>
                  <input type="number" id="fac_benches_functional" name="benches_functional" min="0" value="<?= $benches_func ?>" class="w-full text-xs font-mono font-bold border border-emerald-300 rounded px-1.5 py-1 bg-white text-emerald-900 focus:outline-none" oninput="facCalcEquipment()"/>
                </div>
                <div>
                  <label class="block text-[10px] font-semibold text-rose-800 mb-0.5">Non-Func</label>
                  <input type="number" id="fac_benches_non_functional" name="benches_non_functional" min="0" value="<?= $benches_non_func ?>" class="w-full text-xs font-mono font-bold border border-rose-300 rounded px-1.5 py-1 bg-white text-rose-900 focus:outline-none" oninput="facCalcEquipment()"/>
                </div>
              </div>
              <input type="hidden" id="fac_benches_total" name="benches_total" value="<?= $benches_tot ?>"/>
            </div>

            <!-- Fans -->
            <div class="p-2.5 bg-slate-50 border border-border rounded-lg space-y-2">
              <div class="text-[11px] font-bold text-textMain flex items-center justify-between">
                <span>Fans</span>
                <span class="text-[10px] font-mono text-muted">Total: <strong id="fac_fans_total_disp"><?= $fans_tot ?></strong></span>
              </div>
              <div class="grid grid-cols-2 gap-1.5">
                <div>
                  <label class="block text-[10px] font-semibold text-emerald-800 mb-0.5">Func</label>
                  <input type="number" id="fac_fans_functional" name="fans_functional" min="0" value="<?= $fans_func ?>" class="w-full text-xs font-mono font-bold border border-emerald-300 rounded px-1.5 py-1 bg-white text-emerald-900 focus:outline-none" oninput="facCalcEquipment()"/>
                </div>
                <div>
                  <label class="block text-[10px] font-semibold text-rose-800 mb-0.5">Non-Func</label>
                  <input type="number" id="fac_fans_non_functional" name="fans_non_functional" min="0" value="<?= $fans_non_func ?>" class="w-full text-xs font-mono font-bold border border-rose-300 rounded px-1.5 py-1 bg-white text-rose-900 focus:outline-none" oninput="facCalcEquipment()"/>
                </div>
              </div>
              <input type="hidden" id="fac_fans_total" name="fans_total" value="<?= $fans_tot ?>"/>
            </div>

          </div>
        </div>

        <div class="mt-3 p-2.5 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 flex items-start gap-2">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="mt-0.5 flex-shrink-0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Saving these fields will <strong>automatically flag this school at-risk</strong> in the At-Risk Schools Registry based on the reported conditions.
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

<!-- ═══════════════════════════════════════════════════════════════════════════
     OFFICIAL SELD DEFICIT NOTICE / PDF COMPLIANCE REPORT MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="deficit-notice-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-3 sm:p-6 backdrop-blur-xs">
  <div class="bg-surface border border-border rounded-2xl max-w-3xl w-full p-6 shadow-2xl relative animate-in fade-in zoom-in duration-150 max-h-[95vh] overflow-y-auto">
    <!-- Modal Toolbar (Hidden in Print) -->
    <div class="flex items-center justify-between pb-3.5 border-b border-border mb-4 no-print sticky top-0 bg-surface z-10">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-red-100 text-red-800 flex items-center justify-center font-bold">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-textMain">Official Data Deficit Notice &amp; Compliance Report</h3>
          <p class="text-xs text-muted">Print-ready SELD official notice for Head Master compliance</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button onclick="window.print()" class="btn-primary px-4 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-sm">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          <span>Print / Save PDF</span>
        </button>
        <button onclick="closeDeficitNoticeModal()" class="text-muted hover:text-textMain text-xl leading-none px-2 py-1 rounded hover:bg-slate-100">&times;</button>
      </div>
    </div>

    <!-- ── Print-Ready Official Letterhead Document ── -->
    <div id="deficit-notice-printable" class="bg-white text-slate-900 p-6 sm:p-8 rounded-xl border border-slate-200 space-y-6 font-sans">
      <!-- Official Header -->
      <div class="text-center pb-4 border-b-2 border-slate-800">
        <div class="text-xs tracking-widest uppercase font-bold text-slate-700">GOVERNMENT OF SINDH</div>
        <div class="text-base sm:text-lg font-extrabold text-slate-900 uppercase tracking-tight mt-0.5">SCHOOL EDUCATION &amp; LITERACY DEPARTMENT</div>
        <div class="text-xs font-semibold text-slate-700">DISTRICT REFORM SUPPORT UNIT (RSU) &bull; TANDO ALLAHYAR</div>
        <div class="text-[11px] text-slate-500 mt-1">LSU/RSU Education Portal &bull; Institutional Quality &amp; Compliance Wing</div>
      </div>

      <!-- Reference & Date -->
      <div class="flex justify-between items-center text-xs font-mono pt-1">
        <div>
          <strong>Ref No:</strong> SELD/RSU-TAY/DEF-AUDIT/<?= date('Y') ?>/<?= e($semis) ?>
        </div>
        <div>
          <strong>Date:</strong> <?= date('d F Y') ?>
        </div>
      </div>

      <!-- Addressee -->
      <div class="text-xs space-y-1 bg-slate-50 p-3.5 rounded-lg border border-slate-200">
        <div><strong>To:</strong> The Head Master / Head Mistress,</div>
        <div class="text-sm font-bold text-slate-900"><?= e($school['school_name'] ?? '') ?></div>
        <div class="flex items-center gap-4 text-slate-600 font-mono text-[11px]">
          <span>SEMIS Code: <strong><?= e($semis) ?></strong></span>
          <span>&bull;</span>
          <span>Taluka: <strong><?= e($school['taluka'] ?? '') ?></strong></span>
          <span>&bull;</span>
          <span>Head: <strong><?= e($school['head_master'] ?? 'HM') ?></strong></span>
        </div>
      </div>

      <!-- Subject -->
      <div class="text-xs border-b border-slate-300 pb-2">
        <span class="font-bold uppercase tracking-wide text-slate-900">SUBJECT: </span>
        <strong class="text-slate-900">
          <?= $completion['percentage'] < 100 ? 'URGENT DIRECTIVE: SUBMISSION OF MISSING INSTITUTIONAL & STAFF PROFILE INFORMATION' : 'INSTITUTIONAL RECORD VERIFICATION CERTIFICATE' ?>
        </strong>
      </div>

      <!-- Letter Body -->
      <div class="text-xs leading-relaxed space-y-3 text-slate-800 text-justify">
        <p>
          In accordance with the mandatory digital monitoring and reporting directives of the School Education &amp; Literacy Department (SELD), Government of Sindh, an official institutional data audit was performed for your school on the <strong>District RSU Portal</strong>.
        </p>
        <p>
          The official audit results indicate an overall profile completeness rate of <strong class="text-slate-900 text-sm"><?= $completion['percentage'] ?>%</strong> (<?= $completion['completed_fields'] ?> of <?= $completion['total_fields'] ?> audit criteria verified).
          <?php if ($completion['missing_count'] > 0): ?>
            The following <strong class="text-red-700"><?= $completion['missing_count'] ?> critical information items</strong> remain missing or incomplete in the portal database:
          <?php else: ?>
            All mandatory identity, facility, infrastructure assessment, and staff roster data points have been successfully filed and verified.
          <?php endif; ?>
        </p>
      </div>

      <!-- Missing Fields Checklist Table -->
      <?php if ($completion['missing_count'] > 0): ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-[11px] border border-slate-300">
            <thead>
              <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-300">
                <th class="py-2 px-2.5 border-r border-slate-300 text-center w-8">#</th>
                <th class="py-2 px-3 border-r border-slate-300">Audit Category</th>
                <th class="py-2 px-3 border-r border-slate-300">Missing / Deficit Information</th>
                <th class="py-2 px-2.5 border-r border-slate-300 text-center w-20">Priority</th>
                <th class="py-2 px-3">Directive for Head Master</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <?php foreach ($completion['missing_fields'] as $idx => $item): ?>
                <tr>
                  <td class="py-2 px-2.5 border-r border-slate-300 text-center font-mono font-bold"><?= $idx + 1 ?></td>
                  <td class="py-2 px-3 border-r border-slate-300 font-semibold text-slate-800"><?= e($item['category']) ?></td>
                  <td class="py-2 px-3 border-r border-slate-300">
                    <div class="font-bold text-slate-900"><?= e($item['label']) ?></div>
                    <div class="text-[10px] text-slate-600"><?= e($item['description']) ?></div>
                  </td>
                  <td class="py-2 px-2.5 border-r border-slate-300 text-center font-bold">
                    <span class="px-1.5 py-0.5 rounded text-[10px] <?= $item['importance'] === 'Critical' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' ?>">
                      <?= e($item['importance']) ?>
                    </span>
                  </td>
                  <td class="py-2 px-3 text-slate-700">
                    Log in to School Portal &bull; update &amp; save required information immediately.
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Compliance Directive -->
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-950 font-medium leading-relaxed">
          <strong>COMPLIANCE INSTRUCTION:</strong> You are hereby instructed to log in to the School Head Portal using your official CNIC number and update the above missing information within <strong>seven (07) calendar days</strong> from the receipt of this notice. Non-compliance will result in notice escalation to TEVO / DEO office.
        </div>
      <?php endif; ?>

      <!-- Signature Blocks -->
      <div class="pt-8 grid grid-cols-2 gap-8 text-center text-xs">
        <div class="border-t border-slate-400 pt-2">
          <div class="font-bold text-slate-900">District RSU Coordinator</div>
          <div class="text-[11px] text-slate-600">Reform Support Unit (RSU), Tando Allahyar</div>
          <div class="text-[10px] text-slate-500 font-mono mt-0.5">SELD &bull; Government of Sindh</div>
        </div>
        <div class="border-t border-slate-400 pt-2">
          <div class="font-bold text-slate-900">District Education Officer (DEO)</div>
          <div class="text-[11px] text-slate-600">School Education &amp; Literacy Department</div>
          <div class="text-[10px] text-slate-500 font-mono mt-0.5">District Tando Allahyar</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});

function openDeficitNoticeModal() {
  document.getElementById('deficit-notice-modal').classList.remove('hidden');
}
function closeDeficitNoticeModal() {
  document.getElementById('deficit-notice-modal').classList.add('hidden');
}

function openEditSchoolModal() {
  document.getElementById('edit-school-modal').classList.remove('hidden');
}
function closeEditSchoolModal() {
  document.getElementById('edit-school-modal').classList.add('hidden');
}

function adminCalcEnrollment() {
  const b = parseInt(document.getElementById('admin_enrollment_boys')?.value) || 0;
  const g = parseInt(document.getElementById('admin_enrollment_girls')?.value) || 0;
  const tot = document.getElementById('admin_total_enrollment');
  if (tot) tot.value = b + g;
}

function adminCalcEquipment() {
  // Boards
  const bf = parseInt(document.getElementById('admin_boards_functional')?.value) || 0;
  const bnf = parseInt(document.getElementById('admin_boards_non_functional')?.value) || 0;
  const btot = bf + bnf;
  const bInput = document.getElementById('admin_boards_total');
  const bDisp = document.getElementById('admin_boards_total_disp');
  if (bInput) bInput.value = btot;
  if (bDisp) bDisp.innerText = btot;

  // Benches
  const dnf = parseInt(document.getElementById('admin_benches_functional')?.value) || 0;
  const dnnf = parseInt(document.getElementById('admin_benches_non_functional')?.value) || 0;
  const dtot = dnf + dnnf;
  const dInput = document.getElementById('admin_benches_total');
  const dDisp = document.getElementById('admin_benches_total_disp');
  if (dInput) dInput.value = dtot;
  if (dDisp) dDisp.innerText = dtot;

  // Fans
  const ff = parseInt(document.getElementById('admin_fans_functional')?.value) || 0;
  const fnf = parseInt(document.getElementById('admin_fans_non_functional')?.value) || 0;
  const ftot = ff + fnf;
  const fInput = document.getElementById('admin_fans_total');
  const fDisp = document.getElementById('admin_fans_total_disp');
  if (fInput) fInput.value = ftot;
  if (fDisp) fDisp.innerText = ftot;
}

function facCalcEquipment() {
  // Boards
  const bf = parseInt(document.getElementById('fac_boards_functional')?.value) || 0;
  const bnf = parseInt(document.getElementById('fac_boards_non_functional')?.value) || 0;
  const btot = bf + bnf;
  const bInput = document.getElementById('fac_boards_total');
  const bDisp = document.getElementById('fac_boards_total_disp');
  if (bInput) bInput.value = btot;
  if (bDisp) bDisp.innerText = btot;

  // Benches
  const dnf = parseInt(document.getElementById('fac_benches_functional')?.value) || 0;
  const dnnf = parseInt(document.getElementById('fac_benches_non_functional')?.value) || 0;
  const dtot = dnf + dnnf;
  const dInput = document.getElementById('fac_benches_total');
  const dDisp = document.getElementById('fac_benches_total_disp');
  if (dInput) dInput.value = dtot;
  if (dDisp) dDisp.innerText = dtot;

  // Fans
  const ff = parseInt(document.getElementById('fac_fans_functional')?.value) || 0;
  const fnf = parseInt(document.getElementById('fac_fans_non_functional')?.value) || 0;
  const ftot = ff + fnf;
  const fInput = document.getElementById('fac_fans_total');
  const fDisp = document.getElementById('fac_fans_total_disp');
  if (fInput) fInput.value = ftot;
  if (fDisp) fDisp.innerText = ftot;
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
