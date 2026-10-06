<?php
/**
 * admin/schools.php — School Directory & Management Hub
 * 
 * Powered by ExcelDB backend.
 * Features:
 * - Numerical SEMIS Code as primary unique identifier
 * - Head Master Name & CNIC tracking
 * - Interactive CSV Import with Column Matching, Numerical Validation & Duplicate Stats
 * - Downloadable CSV Template & Directory Export
 * - Safe School Deletion with CSRF-protected Confirmation Modal
 * - Live Search, Multi-criteria Filtering & Dynamic KPIs
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'schools';
$page_title  = 'School Directory — ' . APP_NAME;

// ─── 1. Download Sample CSV Template ─────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="schools_import_template.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['SEMIS CODE', 'School Name', 'Head Master', 'CNIC', 'Taluka', 'Level', 'Gender', 'Total Enrollment', 'Boys Enrollment', 'Girls Enrollment', 'Attendance %', 'Status', 'Phone', 'Address', 'Teaching Staff', 'Non-teaching Staff', 'Classrooms', 'Electricity', 'Drinking Water', 'Toilets', 'Boundary Wall', 'Internet']);
    fputcsv($fp, ['403010101', 'Government Primary School Model Sample', 'Muhammad Aslam Kumbhar', '41302-1234567-1', 'Tando Allahyar', 'Primary', 'Co-education', '250', '130', '120', '92%', 'Active', '+92 300 1234567', 'Main Station Road, Tando Allahyar', '7', '2', '5', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G']);
    fputcsv($fp, ['403010102', 'Government Girls Middle School Sample', 'Rasheeda Begum Laghari', '41302-7654321-2', 'Jhando Mari', 'Middle', 'Girls', '195', '0', '195', '88%', 'Good', '+92 301 2345678', 'Village School Mohalla, Jhando Mari', '5', '2', '4', 'Grid Only', 'Handpump / Tap', 'Functional Blocks', 'Partial / Damaged', 'Partial / Mobile Data']);
    fclose($fp);
    exit;
}

// ─── 2. Export to Excel Action ───────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    ExcelDB::exportCsv('schools');
    exit;
}

$notification = '';
$notification_type = 'success';

// ─── 3. Handle Update HM Password Action ─────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_hm_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } else {
        $targetSemis = trim($_POST['target_semis'] ?? '');
        $newPass     = trim($_POST['new_password'] ?? '');
        if (!empty($targetSemis) && !empty($newPass)) {
            ExcelDB::updateHeadMasterPassword($targetSemis, $newPass);
            header('Location: ' . BASE_URL . '/admin/schools.php?msg=pass_updated&semis=' . urlencode($targetSemis));
            exit;
        }
    }
}

// ─── 4. Handle Delete School Action ──────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['delete_school'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'Security token expired. Please try again.';
        $notification_type = 'danger';
    } else {
        $semis = trim($_POST['semis_code'] ?? '');
        if (!empty($semis)) {
            $existing = ExcelDB::find('schools', 'semis_code', $semis);
            $schName = $existing['school_name'] ?? $semis;
            ExcelDB::delete('schools', 'semis_code', $semis);
            header('Location: ' . BASE_URL . '/admin/schools.php?msg=deleted&sch=' . urlencode($schName) . '&semis=' . urlencode($semis));
            exit;
        }
    }
}

// ─── 5. Handle Add Single School Form Submission ─────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add_school'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } else {
        $semis  = trim($_POST['semis_code'] ?? '');
        $name   = trim($_POST['school_name'] ?? '');
        $hm     = trim($_POST['head_master'] ?? '');
        $cnic   = trim($_POST['cnic'] ?? '');
        $level  = trim($_POST['level'] ?? 'Primary');
        $gender = trim($_POST['gender'] ?? 'Co-education');
        $taluka = trim($_POST['taluka'] ?? 'Tando Allahyar');
        $boys   = max(0, (int)($_POST['enrollment_boys'] ?? 0));
        $girls  = max(0, (int)($_POST['enrollment_girls'] ?? 0));
        $enroll = $boys + $girls;
        if ($enroll === 0 && isset($_POST['enrollment'])) {
            $enroll = max(0, (int)$_POST['enrollment']);
        }
        $status = trim($_POST['status'] ?? 'Active');
        $attPct = trim($_POST['attendance_pct'] ?? '90') . '%';
        $phone  = trim($_POST['phone'] ?? '');
        $address= trim($_POST['address'] ?? '');

        // Strict validation: Numerical SEMIS code only
        if (empty($semis) || !preg_match('/^[0-9]+$/', $semis)) {
            $notification = 'Invalid SEMIS Code! SEMIS Code must be numerical digits only (e.g. 403010001).';
            $notification_type = 'danger';
        } elseif (empty($name)) {
            $notification = 'School Name is required.';
            $notification_type = 'danger';
        } elseif (ExcelDB::find('schools', 'semis_code', $semis)) {
            $notification = "A school with SEMIS Code '{$semis}' already exists in the district directory.";
            $notification_type = 'danger';
        } else {
            $badge = 'badge-active';
            if ($status === 'Good') $badge = 'badge-good';
            elseif ($status === 'Needs Attention') $badge = 'badge-attention';
            elseif ($status === 'Not Reporting') $badge = 'badge-not-rep';

            ExcelDB::insert('schools', [
                'semis_code'             => $semis,
                'school_name'            => $name,
                'head_master'            => $hm,
                'cnic'                   => $cnic,
                'phone'                  => $phone,
                'address'                => $address,
                'level'                  => $level,
                'gender'                 => $gender,
                'taluka'                 => $taluka,
                'enrollment'             => (string)$enroll,
                'enrollment_boys'        => (string)$boys,
                'enrollment_girls'       => (string)$girls,
                'attendance_pct'         => $attPct,
                'status'                 => $status,
                'status_badge'           => $badge,
                'classrooms'             => (string)(int)($_POST['classrooms'] ?? 6),
                'teachers'               => (string)(int)($_POST['teachers'] ?? 8),
                'non_teaching'           => (string)(int)($_POST['non_teaching'] ?? 2),
                'facility_electricity'   => trim($_POST['facility_electricity'] ?? 'Solar + Grid'),
                'facility_water'         => trim($_POST['facility_water'] ?? 'Filtered Plant'),
                'facility_toilets'       => trim($_POST['facility_toilets'] ?? 'Functional Blocks'),
                'facility_boundary_wall' => trim($_POST['facility_boundary_wall'] ?? 'Secured & Complete'),
                'facility_internet'      => trim($_POST['facility_internet'] ?? 'Broadband / 4G'),
            ]);
            header('Location: ' . BASE_URL . '/admin/schools.php?msg=added&semis=' . urlencode($semis));
            exit;
        }
    }
}

// ─── 5. Handle CSV Import Confirmation ───────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['import_schools_commit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } else {
        $rawJson = $_POST['import_data_json'] ?? '';
        $updateExisting = ($_POST['update_existing'] ?? '1') === '1';
        $records = json_decode($rawJson, true);

        if (!is_array($records) || empty($records)) {
            $notification = 'No records received to import.';
            $notification_type = 'danger';
        } else {
            $existingSchools = ExcelDB::all('schools');
            $existingMap = [];
            foreach ($existingSchools as $idx => $es) {
                if (!empty($es['semis_code'])) {
                    $existingMap[(string)$es['semis_code']] = $idx;
                }
            }

            $addedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $invalidCount = 0;

            foreach ($records as $r) {
                $semis = trim((string)($r['semis_code'] ?? ''));
                $name  = trim((string)($r['school_name'] ?? ''));

                // Validate numerical SEMIS code
                if (empty($semis) || !preg_match('/^[0-9]+$/', $semis) || empty($name)) {
                    $invalidCount++;
                    continue;
                }

                $status = !empty($r['status']) ? trim($r['status']) : 'Active';
                $badge = 'badge-active';
                if ($status === 'Good') $badge = 'badge-good';
                elseif ($status === 'Needs Attention') $badge = 'badge-attention';
                elseif ($status === 'Not Reporting') $badge = 'badge-not-rep';

                $att = trim((string)($r['attendance_pct'] ?? '90'));
                if (!str_ends_with($att, '%') && is_numeric($att)) {
                    $att .= '%';
                }

                $schoolData = [
                    'semis_code'             => $semis,
                    'school_name'            => $name,
                    'head_master'            => trim((string)($r['head_master'] ?? '')),
                    'cnic'                   => trim((string)($r['cnic'] ?? '')),
                    'phone'                  => trim((string)($r['phone'] ?? '')),
                    'address'                => trim((string)($r['address'] ?? '')),
                    'level'                  => trim((string)($r['level'] ?? 'Primary')),
                    'gender'                 => trim((string)($r['gender'] ?? 'Co-education')),
                    'taluka'                 => trim((string)($r['taluka'] ?? 'Tando Allahyar')),
                    'enrollment'             => (string)(int)($r['enrollment'] ?? 150),
                    'attendance_pct'         => !empty($att) ? $att : '90%',
                    'status'                 => $status,
                    'status_badge'           => $badge,
                    'classrooms'             => (string)(int)($r['classrooms'] ?? 6),
                    'teachers'               => (string)(int)($r['teachers'] ?? 8),
                    'non_teaching'           => (string)(int)($r['non_teaching'] ?? 2),
                    'facility_electricity'   => !empty($r['facility_electricity']) ? trim((string)$r['facility_electricity']) : 'Solar + Grid',
                    'facility_water'         => !empty($r['facility_water']) ? trim((string)$r['facility_water']) : 'Filtered Plant',
                    'facility_toilets'       => !empty($r['facility_toilets']) ? trim((string)$r['facility_toilets']) : 'Functional Blocks',
                    'facility_boundary_wall' => !empty($r['facility_boundary_wall']) ? trim((string)$r['facility_boundary_wall']) : 'Secured & Complete',
                    'facility_internet'      => !empty($r['facility_internet']) ? trim((string)$r['facility_internet']) : 'Broadband / 4G',
                ];

                if (isset($existingMap[$semis])) {
                    if ($updateExisting) {
                        $existingIdx = $existingMap[$semis];
                        $schoolData['id'] = $existingSchools[$existingIdx]['id'] ?? (string)($existingIdx + 1);
                        $existingSchools[$existingIdx] = array_merge($existingSchools[$existingIdx], $schoolData);
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }
                } else {
                    $maxId = 0;
                    foreach ($existingSchools as $ex) {
                        if (isset($ex['id']) && is_numeric($ex['id']) && (int)$ex['id'] > $maxId) {
                            $maxId = (int)$ex['id'];
                        }
                    }
                    $schoolData['id'] = (string)($maxId + 1);
                    $existingSchools[] = $schoolData;
                    $existingMap[$semis] = count($existingSchools) - 1;
                    $addedCount++;
                }
            }

            ExcelDB::writeTable('schools', $existingSchools);
            header('Location: ' . BASE_URL . '/admin/schools.php?msg=imported&added={$addedCount}&updated={$updatedCount}&skipped={$skippedCount}&invalid={$invalidCount}');
            exit;
        }
    }
}

// ─── Handle Notifications ───────────────────────────────────────────────────
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $notification = 'New school successfully registered with SEMIS Code ' . e($_GET['semis'] ?? '') . '!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'pass_updated') {
        $notification = 'Head Master portal login password successfully updated for SEMIS ' . e($_GET['semis'] ?? '') . '!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $notification = 'School "' . e($_GET['sch'] ?? '') . '" (SEMIS: ' . e($_GET['semis'] ?? '') . ') has been deleted successfully.';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'imported') {
        $added   = (int)($_GET['added'] ?? 0);
        $updated = (int)($_GET['updated'] ?? 0);
        $skipped = (int)($_GET['skipped'] ?? 0);
        $invalid = (int)($_GET['invalid'] ?? 0);
        $notification = "Import Complete! {$added} new school(s) added, {$updated} existing record(s) updated.";
        if ($skipped > 0) $notification .= " ({$skipped} duplicate(s) skipped).";
        if ($invalid > 0) $notification .= " [Warning: {$invalid} row(s) discarded due to invalid/non-numerical SEMIS code].";
        $notification_type = 'success';
    }
}

// ─── Read Schools from Excel Database ─────────────────────────────────────────
$schools_dir = ExcelDB::all('schools');
$available_talukas = ExcelDB::getTalukas();

// Calculate dynamic KPIs from Excel data
$total_schools = count($schools_dir);
$active_schools = 0;
$attention_schools = 0;
$not_reporting_schools = 0;

// Pre-calculate profile completion data for all schools
$completion_map = [];
$complete_count = 0;
$incomplete_count = 0;

foreach ($schools_dir as $s) {
    $semis = trim((string)($s['semis_code'] ?? ''));
    if (!empty($semis)) {
        $c = ExcelDB::calculateSchoolProfileCompletion($semis);
        $completion_map[$semis] = $c;
        if ((int)($c['percentage'] ?? 0) === 100) {
            $complete_count++;
        } else {
            $incomplete_count++;
        }
    }
}

foreach ($schools_dir as $s) {
    $st = $s['status'] ?? '';
    if ($st === 'Active' || $st === 'Good') {
        $active_schools++;
    } elseif ($st === 'Needs Attention') {
        $attention_schools++;
    } elseif ($st === 'Not Reporting') {
        $not_reporting_schools++;
    }
}

// Existing SEMIS codes for client-side duplicate checking
$existing_semis_list = array_column($schools_dir, 'semis_code');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<meta name="description" content="District RSU School Directory — manage and monitor all schools in the district"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif'],serif:['Cinzel','Georgia','serif']}}}}</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s,color .15s;}.sidebar-link:hover{background:rgba(255,255,255,.08);}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E;}
.sidebar-group-title{font-size:10px;letter-spacing:.1em;text-transform:uppercase;}
.kpi-card{transition:box-shadow .2s,transform .2s;}.kpi-card:hover{box-shadow:0 4px 16px rgba(18,59,99,.10);transform:translateY(-1px);}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.btn-excel{background:#107C41;color:#fff;transition:background .15s;}.btn-excel:hover{background:#0b5c30;}
.status-badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px;}
.badge-active{background:#DCFCE7;color:#15803D;}.badge-good{background:#D1FAE5;color:#065F46;}.badge-attention{background:#FEF3C7;color:#92400E;}.badge-not-rep{background:#F1F5F9;color:#475569;}
.table-row:hover{background:#F8FAFC;}
#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1);}#overlay{transition:opacity .25s;}

/* Print Rules for Bulk Documents */
@media print {
  @page {
    size: A4 portrait;
    margin: 8mm;
  }
  html, body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    color: #000 !important;
  }
  body * {
    visibility: hidden !important;
  }
  #bulk-print-modal, #bulk-print-modal * {
    visibility: visible !important;
  }
  #bulk-print-modal {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    height: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
    z-index: 9999999 !important;
    display: block !important;
  }
  #bulk-modal-wrapper {
    max-width: 100% !important;
    width: 100% !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    max-height: none !important;
  }
  #bulk-print-scroll-wrap {
    overflow: visible !important;
    max-height: none !important;
    padding: 0 !important;
  }
  .print-page-break {
    page-break-after: always !important;
    break-after: page !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    width: 100% !important;
    min-height: 98vh !important;
    padding: 24px !important;
    margin: 0 auto !important;
    box-sizing: border-box !important;
    background: #fff !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
  }
  .print-page-break:last-child {
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .no-print {
    display: none !important;
  }
}
</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative no-print">
  <span class="font-medium tracking-wide"><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="flex-1 overflow-y-auto p-4 md:p-6">
  <nav class="text-xs text-muted mb-4 flex items-center gap-1.5" aria-label="Breadcrumb">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="hover:text-primary">Dashboard</a><span>/</span>
    <span class="text-textMain font-medium">School Directory</span>
  </nav>

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div>
      <div class="flex items-center gap-2">
        <h1 class="text-xl font-bold text-textMain">School Directory</h1>
        <span class="text-xs bg-primary/10 text-primary font-semibold px-2 py-0.5 rounded"><?= APP_DISTRICT ?></span>
      </div>
      <p class="text-muted text-sm mt-0.5">Manage registered schools, headmasters, CNICs, and attendance records</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <!-- Import Button -->
      <button onclick="openImportModal()" class="btn-excel px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5 shadow-sm">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Import Schools (CSV)
      </button>

      <!-- Add School Button -->
      <button onclick="openAddSchoolModal()" class="btn-primary px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5 shadow-sm">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add School
      </button>

      <!-- Export Button -->
      <a href="?action=export_excel" class="btn-secondary px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5 hover:border-primary">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Directory
      </a>
    </div>
  </div>

  <?php if (!empty($notification)): ?>
  <div class="mb-5 p-3.5 rounded-lg text-xs <?= $notification_type === 'danger' ? 'bg-rose-50 border border-rose-200 text-rose-800' : 'bg-emerald-50 border border-emerald-200 text-emerald-800' ?> flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2">
      <?php if ($notification_type === 'danger'): ?>
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php else: ?>
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      <?php endif; ?>
      <span><?= e($notification) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">&times;</button>
  </div>
  <?php endif; ?>

  <!-- KPI Cards Computed from Excel -->
  <section class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="kpi-card bg-surface border border-border rounded-lg p-4">
      <div class="text-xs text-muted mb-1 uppercase tracking-wide">Total Schools</div>
      <div class="text-3xl font-bold text-primary"><?= $total_schools ?></div>
      <div class="text-xs text-muted mt-1">Active in district</div>
    </div>
    <div class="kpi-card bg-surface border border-border rounded-lg p-4">
      <div class="text-xs text-muted mb-1 uppercase tracking-wide">Active / Good</div>
      <div class="text-3xl font-bold text-success"><?= $active_schools ?></div>
      <div class="text-xs text-muted mt-1">Reporting today</div>
    </div>
    <div class="kpi-card bg-surface border border-border rounded-lg p-4">
      <div class="text-xs text-muted mb-1 uppercase tracking-wide">Need Attention</div>
      <div class="text-3xl font-bold text-warning"><?= $attention_schools ?></div>
      <div class="text-xs text-muted mt-1">Low attendance</div>
    </div>
    <div class="kpi-card bg-surface border border-border rounded-lg p-4">
      <div class="text-xs text-muted mb-1 uppercase tracking-wide">Not Reporting</div>
      <div class="text-3xl font-bold text-danger"><?= $not_reporting_schools ?></div>
      <div class="text-xs text-muted mt-1">Requires follow-up</div>
    </div>
  </section>

  <!-- School Table -->
  <div class="bg-surface border border-border rounded-lg shadow-sm">
    <div class="px-5 py-3 border-b border-border flex flex-wrap gap-2 items-center">
      <input type="text" id="sch-search" placeholder="Search by School Name, SEMIS, Head Master, or CNIC…" class="text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary flex-1 min-w-56" onkeyup="filterSch()"/>
      <select id="sch-taluka" class="text-xs border border-border rounded px-3 py-1.5 bg-background" onchange="filterSch()">
        <option value="">All Talukas</option>
        <?php foreach ($available_talukas as $t): ?>
        <option value="<?= e($t) ?>"><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="sch-level" class="text-xs border border-border rounded px-3 py-1.5 bg-background" onchange="filterSch()">
        <option value="">All Levels</option>
        <option>Primary</option><option>Middle</option><option>Secondary</option><option>Higher Secondary</option>
      </select>
      <select id="sch-gender" class="text-xs border border-border rounded px-3 py-1.5 bg-background" onchange="filterSch()">
        <option value="">All Gender</option>
        <option>Boys</option><option>Girls</option><option>Co-education</option>
      </select>
      <select id="sch-progress" class="text-xs border border-border rounded px-3 py-1.5 bg-background font-medium focus:outline-none focus:border-primary text-textMain" onchange="filterSch()">
        <option value="">All Progress</option>
        <option id="opt-progress-100" value="100">Complete (100%) (<?= $complete_count ?>)</option>
        <option id="opt-progress-below100" value="below100">Below 100% (<?= $incomplete_count ?>)</option>
      </select>

      <!-- Bulk Print Action Buttons (Shown conditionally based on Progress dropdown) -->
      <button id="btn-bulk-appreciation" type="button" onclick="openBulkAppreciationModal()" class="hidden text-xs bg-emerald-700 hover:bg-emerald-800 text-white font-semibold px-3 py-1.5 rounded flex items-center gap-1.5 shadow-sm transition animate-in fade-in duration-150">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15l-2 5l9-5l-9-5l2 5zm0 0v-8"/><circle cx="12" cy="8" r="7"/></svg>
        <span>Print Appreciation Certificates (<span id="count-appreciation"><?= $complete_count ?></span>)</span>
      </button>

      <button id="btn-bulk-warning" type="button" onclick="openBulkWarningModal()" class="hidden text-xs bg-red-600 hover:bg-red-700 text-white font-semibold px-3 py-1.5 rounded flex items-center gap-1.5 shadow-sm transition animate-in fade-in duration-150">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Print Show Cause Notices (<span id="count-warning"><?= $incomplete_count ?></span>)</span>
      </button>

      <button onclick="resetSch()" class="text-xs border border-border rounded px-3 py-1.5 bg-background text-muted hover:bg-border">Reset</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs" aria-label="Schools table">
        <thead><tr class="border-b border-border bg-background text-muted uppercase tracking-wide text-left">
          <th class="px-5 py-3 font-semibold">SEMIS CODE</th>
          <th class="px-4 py-3 font-semibold">School Name & Leadership</th>
          <th class="px-4 py-3 font-semibold">Level</th>
          <th class="px-4 py-3 font-semibold">Gender</th>
          <th class="px-4 py-3 font-semibold">Taluka</th>
          <th class="px-4 py-3 font-semibold text-right">Enrollment</th>
          <th class="px-4 py-3 font-semibold text-center">Progress</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold text-center">Actions</th>
        </tr></thead>
        <tbody id="sch-tbody" class="divide-y divide-border text-textMain">
          <?php foreach ($schools_dir as $s): ?>
          <?php 
            $sSemis = trim((string)($s['semis_code'] ?? ''));
            $cred = ExcelDB::getHeadMasterCredentials($sSemis);
            $comp = $completion_map[$sSemis] ?? ['percentage' => 0, 'missing_count' => 0];
            $pct = (int)($comp['percentage'] ?? 0);
            $isComplete = ($pct === 100);
          ?>
          <tr class="table-row cursor-pointer hover:bg-slate-100/70 transition-colors" 
              data-semis="<?= e($sSemis) ?>"
              data-progress="<?= $isComplete ? '100' : 'below100' ?>"
              data-progress-pct="<?= $pct ?>"
              onclick="handleRowClick(event, '<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($sSemis) ?>')" 
              title="Click to open <?= e($s['school_name'] ?? '') ?> Profile">
            <td class="px-5 py-3 font-mono font-semibold text-primary"><?= e($sSemis) ?></td>
            <td class="px-4 py-3">
              <div class="font-medium text-textMain"><?= e($s['school_name'] ?? '') ?></div>
              <div class="text-[11px] text-muted flex items-center gap-2 mt-0.5">
                <span class="flex items-center gap-1">
                  <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  HM: <?= e(!empty($s['head_master']) ? $s['head_master'] : 'Not Assigned') ?>
                </span>
                <span class="text-border">|</span>
                <span class="font-mono text-[10px] text-muted">CNIC: <?= e(!empty($s['cnic']) ? $s['cnic'] : 'N/A') ?></span>
              </div>
            </td>
            <td class="px-4 py-3 text-muted"><?= e($s['level'] ?? '') ?></td>
            <td class="px-4 py-3 text-muted"><?= e($s['gender'] ?? '') ?></td>
            <td class="px-4 py-3 text-muted"><?= e($s['taluka'] ?? '') ?></td>
            <td class="px-4 py-3 text-right">
              <?php 
                $b_cnt = isset($s['enrollment_boys']) && $s['enrollment_boys'] !== '' ? (int)$s['enrollment_boys'] : null;
                $g_cnt = isset($s['enrollment_girls']) && $s['enrollment_girls'] !== '' ? (int)$s['enrollment_girls'] : null;
                $tot_e = (int)($s['enrollment'] ?? 0);
                if ($b_cnt === null || $g_cnt === null) {
                  $gen = strtolower($s['gender'] ?? '');
                  if ($gen === 'girls') { $g_cnt = $tot_e; $b_cnt = 0; }
                  elseif ($gen === 'boys') { $b_cnt = $tot_e; $g_cnt = 0; }
                  else { $b_cnt = (int)round($tot_e * 0.52); $g_cnt = $tot_e - $b_cnt; }
                }
              ?>
              <div class="font-mono font-bold text-textMain"><?= number_format($tot_e) ?></div>
              <div class="text-[10px] text-muted font-mono"><?= number_format($b_cnt) ?>B / <?= number_format($g_cnt) ?>G</div>
            </td>
            <td class="px-4 py-3 text-center">
              <?php if ($isComplete): ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                  <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  100%
                </span>
              <?php else: ?>
                <div class="inline-flex flex-col items-center">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold <?= $pct < 60 ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?>">
                    <?= $pct ?>%
                  </span>
                  <span class="text-[10px] text-muted mt-0.5"><?= (int)($comp['missing_count'] ?? 0) ?> missing</span>
                </div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3"><span class="status-badge <?= e($s['status_badge'] ?? 'badge-active') ?>"><?= e($s['status'] ?? 'Active') ?></span></td>
            <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
              <div class="flex items-center justify-center gap-1.5">
                <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($sSemis) ?>" class="btn-primary px-2 py-1 rounded text-xs" title="View Full Profile">Profile</a>
                <button type="button" 
                        data-semis="<?= e($sSemis) ?>"
                        data-school="<?= e($s['school_name'] ?? '') ?>"
                        data-hm="<?= e($s['head_master'] ?? '') ?>"
                        data-cnic="<?= e($cred['cnic'] ?? $s['cnic'] ?? '') ?>"
                        data-pass="<?= e($cred['password_plain'] ?? '1122') ?>"
                        onclick="event.stopPropagation(); openCredentialsModalFromBtn(this)" 
                        class="p-1 rounded text-emerald-700 hover:text-emerald-900 hover:bg-emerald-50 transition border border-emerald-200" 
                        title="View HM Portal Login & Password">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </button>
                <button onclick="event.stopPropagation(); confirmDeleteSchool('<?= e($sSemis) ?>', '<?= e(addslashes($s['school_name'] ?? '')) ?>')" class="p-1 rounded text-muted hover:text-danger hover:bg-red-50 transition" title="Delete School">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted">
      <span>Showing <?= count($schools_dir) ?> registered schools</span>
      <div class="flex items-center gap-1">
        <button class="px-2.5 py-1.5 border border-border rounded disabled:opacity-40" disabled>Previous</button>
        <button class="px-2.5 py-1.5 border border-primary bg-primary text-white rounded">1</button>
        <button class="px-2.5 py-1.5 border border-border rounded disabled:opacity-40" disabled>Next</button>
      </div>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>

<!-- Add Single School Modal -->
<div id="add-school-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-xl w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <div>
        <h3 class="text-sm font-bold text-textMain">Register New District School</h3>
        <p class="text-xs text-muted mt-0.5">SEMIS Code must be strictly numerical</p>
      </div>
      <button onclick="closeAddSchoolModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="add_school" value="1"/>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">SEMIS Code <span class="text-danger">* (Numerical Only)</span></label>
          <input type="text" name="semis_code" required pattern="[0-9]+" placeholder="e.g. 403010150" class="w-full text-xs font-mono border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
          <span class="text-[10px] text-muted">9-digit numerical code</span>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Taluka <span class="text-danger">*</span></label>
          <select name="taluka" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <?php foreach ($available_talukas as $t): ?>
            <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">School Full Name <span class="text-danger">*</span></label>
        <input type="text" name="school_name" required placeholder="e.g. Government Boys Primary School..." class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Head Master Name</label>
          <input type="text" name="head_master" placeholder="e.g. Ghulam Mustafa Kumbhar" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Head Master CNIC</label>
          <input type="text" name="cnic" placeholder="e.g. 41302-1234567-1" class="w-full text-xs font-mono border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Level</label>
          <select name="level" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option>Primary</option><option>Middle</option><option>Secondary</option><option>Higher Secondary</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Gender</label>
          <select name="gender" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option>Co-education</option><option>Boys</option><option>Girls</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1 flex items-center justify-between">
            <span>Boys</span>
            <span class="text-[10px] text-blue-700 font-bold">Boys</span>
          </label>
          <input type="number" id="add_enrollment_boys" name="enrollment_boys" value="95" min="0" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary font-mono" oninput="addCalcEnrollment()"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1 flex items-center justify-between">
            <span>Girls</span>
            <span class="text-[10px] text-pink-700 font-bold">Girls</span>
          </label>
          <input type="number" id="add_enrollment_girls" name="enrollment_girls" value="85" min="0" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary font-mono" oninput="addCalcEnrollment()"/>
        </div>
      </div>
      <input type="hidden" id="add_total_enrollment" name="enrollment" value="180"/>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Status</label>
          <select name="status" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option>Active</option><option>Good</option><option>Needs Attention</option><option>Not Reporting</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Contact Phone</label>
          <input type="text" name="phone" placeholder="+92 300 0000000" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">School Address</label>
        <input type="text" name="address" placeholder="e.g. Station Road, City Area, Tando Allahyar" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Classrooms</label>
          <input type="number" name="classrooms" min="0" value="6" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Teaching Staff</label>
          <input type="number" name="teachers" min="0" value="8" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Non-teaching Staff</label>
          <input type="number" name="non_teaching" min="0" value="2" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div class="p-3 bg-slate-50 border border-border rounded space-y-2.5">
        <div class="text-xs font-bold text-primary">School Facilities &amp; Utilities</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Boundary Wall</label>
            <select name="facility_boundary_wall" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <option>Secured &amp; Complete</option><option>Partial / Damaged</option><option>Under Construction</option><option>Unavailable / None</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Electricity</label>
            <select name="facility_electricity" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <option>Solar + Grid</option><option>Grid Only</option><option>Solar Only</option><option>Unavailable / None</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Drinking Water</label>
            <select name="facility_water" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <option>Filtered Plant</option><option>Handpump / Tap</option><option>Water Supply Line</option><option>Unavailable / None</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-textMain mb-1">Toilets</label>
            <select name="facility_toilets" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <option>Functional Blocks</option><option>Needs Repair</option><option>Unavailable / None</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-[11px] font-semibold text-textMain mb-1">Internet Connectivity</label>
            <select name="facility_internet" class="w-full text-xs border border-border rounded px-2.5 py-1.5 bg-surface focus:outline-none focus:border-primary">
              <option>Broadband / 4G</option><option>Partial / Mobile Data</option><option>Unavailable / None</option>
            </select>
          </div>
        </div>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeAddSchoolModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Save School</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-school-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
    <div class="w-12 h-12 rounded-full bg-red-100 text-danger flex items-center justify-center mx-auto mb-3">
      <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
    </div>
    <h3 class="text-sm font-bold text-center text-textMain">Delete School Record</h3>
    <p class="text-xs text-center text-muted mt-2">
      Are you sure you want to delete <span id="del-school-name" class="font-semibold text-textMain"></span> (<span id="del-semis-code" class="font-mono text-primary font-bold"></span>)?
    </p>
    <p class="text-[11px] text-danger bg-red-50 border border-red-200 rounded p-2 text-center mt-3">
      This action will remove the school from the district registry immediately.
    </p>

    <form method="POST" class="mt-4 flex justify-end gap-2">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="delete_school" value="1"/>
      <input type="hidden" name="semis_code" id="del-semis-input" value=""/>
      <button type="button" onclick="closeDeleteModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
      <button type="submit" class="bg-danger hover:bg-red-700 text-white px-4 py-1.5 rounded text-xs font-medium">Delete School</button>
    </form>
  </div>
</div>

<!-- Head Master Login Credentials & Password Modal -->
<div id="credentials-school-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-xl max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-textMain">Head Master Portal Credentials</h3>
          <p class="text-xs text-muted" id="cred-school-title">School Access Details</p>
        </div>
      </div>
      <button onclick="closeCredentialsModal()" class="text-muted hover:text-textMain text-xl leading-none">&times;</button>
    </div>

    <!-- Credentials Display Cards -->
    <div class="space-y-3 mb-4">
      <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs space-y-2">
        <div class="flex justify-between items-center">
          <span class="text-muted">Head Master:</span>
          <span class="font-semibold text-textMain" id="cred-hm-name"></span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-muted">SEMIS Code:</span>
          <span class="font-mono font-bold text-primary" id="cred-semis-code"></span>
        </div>
      </div>

      <div>
        <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1">Login Username (HM CNIC)</label>
        <div class="flex items-center justify-between bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-xs">
          <span class="font-mono font-bold text-primary" id="cred-hm-cnic"></span>
          <button type="button" onclick="copyCredText('cred-hm-cnic', this)" class="text-xs font-semibold text-primary hover:underline">Copy</button>
        </div>
      </div>

      <div>
        <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1">Current Password (visible to Admin)</label>
        <div class="flex items-center justify-between bg-emerald-50/70 border border-emerald-200 rounded-lg px-3 py-2 text-xs">
          <span class="font-mono font-bold text-emerald-950 text-sm" id="cred-hm-pass"></span>
          <div class="flex items-center gap-2">
            <button type="button" onclick="toggleCredPass('cred-hm-pass', this)" class="text-xs font-semibold text-emerald-800 hover:underline">Hide</button>
            <span class="text-emerald-300">|</span>
            <button type="button" onclick="copyCredText('cred-hm-pass', this)" class="text-xs font-semibold text-emerald-800 hover:underline">Copy</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Update Password Form -->
    <form method="POST" action="<?= BASE_URL ?>/admin/schools.php" class="border-t border-border pt-3 space-y-3">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_hm_password" value="1"/>
      <input type="hidden" name="target_semis" id="cred-form-semis" value=""/>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Reset / Set New Password</label>
        <div class="flex items-center gap-2">
          <input type="text" id="cred-new-pass-input" name="new_password" required value="1122" class="w-full text-xs font-mono font-bold border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          <button type="button" onclick="document.getElementById('cred-new-pass-input').value='1122'" class="text-xs bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold px-2.5 py-2 rounded-lg hover:bg-emerald-100 whitespace-nowrap">
            Set 1122
          </button>
        </div>
      </div>

      <div class="flex items-center justify-between pt-2">
        <a id="cred-portal-link" href="#" target="_blank" class="text-xs text-emerald-700 hover:underline font-semibold flex items-center gap-1">
          <span>Open School Portal</span> &rarr;
        </a>
        <div class="flex items-center gap-2">
          <button type="button" onclick="closeCredentialsModal()" class="btn-secondary text-xs font-semibold px-3 py-1.5 rounded-lg">Cancel</button>
          <button type="submit" class="btn-primary text-xs font-semibold px-4 py-1.5 rounded-lg shadow-xs">Save Password</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- CSV Import Wizard Modal (Column Matching, Analysis & Unique Entries) -->
<div id="import-school-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-2xl w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150 max-h-[92vh] overflow-y-auto">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded bg-emerald-100 text-emerald-700 flex items-center justify-center">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-textMain">Import Schools via CSV</h3>
          <p class="text-xs text-muted">Column mapping, numerical SEMIS verification & duplicate checks</p>
        </div>
      </div>
      <button onclick="closeImportModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <!-- Step 1: Upload File -->
    <div id="import-step-1" class="space-y-4">
      <div class="border-2 border-dashed border-border hover:border-primary/50 rounded-lg p-6 text-center cursor-pointer bg-background" onclick="document.getElementById('csv-file-input').click()">
        <input type="file" id="csv-file-input" accept=".csv" class="hidden" onchange="handleCsvFile(this.files[0])"/>
        <svg class="mx-auto h-10 w-10 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="text-xs font-medium text-textMain mt-2">Click to browse or drop your CSV file here</p>
        <p class="text-[11px] text-muted mt-1">Accepts standard UTF-8 CSV files</p>
      </div>

      <div class="flex items-center justify-between p-3 rounded bg-blue-50 border border-blue-200 text-blue-900 text-xs">
        <div>
          <span class="font-semibold">Need a sample format?</span>
          <p class="text-[11px] text-blue-800">Download our pre-structured template with CNIC &amp; Head Master columns.</p>
        </div>
        <a href="?action=download_template" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded text-xs whitespace-nowrap">Download Sample CSV</a>
      </div>
    </div>

    <!-- Step 2: Column Matching & Analysis Preview -->
    <div id="import-step-2" class="space-y-4 hidden">
      <!-- Analysis Summary Banner -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <div class="p-2.5 bg-background border border-border rounded text-center">
          <div class="text-[10px] uppercase tracking-wide text-muted">Total Rows</div>
          <div id="stat-total-rows" class="text-lg font-bold text-primary">0</div>
        </div>
        <div class="p-2.5 bg-emerald-50 border border-emerald-200 rounded text-center">
          <div class="text-[10px] uppercase tracking-wide text-emerald-800">Unique SEMIS</div>
          <div id="stat-unique-semis" class="text-lg font-bold text-emerald-700">0</div>
        </div>
        <div class="p-2.5 bg-blue-50 border border-blue-200 rounded text-center">
          <div class="text-[10px] uppercase tracking-wide text-blue-800">New Schools</div>
          <div id="stat-new-schools" class="text-lg font-bold text-blue-700">0</div>
        </div>
        <div class="p-2.5 bg-amber-50 border border-amber-200 rounded text-center">
          <div class="text-[10px] uppercase tracking-wide text-amber-800">Existing Records</div>
          <div id="stat-existing-schools" class="text-lg font-bold text-amber-700">0</div>
        </div>
      </div>

      <!-- Warning box for invalid/non-numerical SEMIS codes if any -->
      <div id="import-warning-box" class="p-3 bg-amber-50 border border-amber-300 text-amber-900 rounded text-xs hidden">
        <span class="font-bold flex items-center gap-1">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Non-Numerical SEMIS Notice:
        </span>
        <span id="import-warning-text"></span>
      </div>

      <!-- Column Matching Section -->
      <div class="border border-border rounded-lg p-3 bg-surface">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-xs font-bold text-textMain">Column Matching (CSV Headers &rarr; LSU Fields)</h4>
          <span class="text-[11px] text-muted">Verify automatically detected columns</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs" id="column-mapping-grid">
          <!-- Dynamically populated by JS -->
        </div>
      </div>

      <!-- Data Preview Table (First 5 Rows) -->
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <h4 class="text-xs font-bold text-textMain">Preview Sample Data (First 5 records)</h4>
          <span class="text-[11px] text-muted font-mono" id="file-name-label"></span>
        </div>
        <div class="border border-border rounded overflow-x-auto max-h-44">
          <table class="w-full text-left text-[11px]">
            <thead class="bg-background border-b border-border text-muted uppercase tracking-wider">
              <tr>
                <th class="px-2.5 py-1.5 font-semibold">SEMIS</th>
                <th class="px-2.5 py-1.5 font-semibold">School Name</th>
                <th class="px-2.5 py-1.5 font-semibold">Head Master</th>
                <th class="px-2.5 py-1.5 font-semibold">CNIC</th>
                <th class="px-2.5 py-1.5 font-semibold">Taluka</th>
                <th class="px-2.5 py-1.5 font-semibold text-right">Enrollment</th>
              </tr>
            </thead>
            <tbody id="preview-table-body" class="divide-y divide-border">
            </tbody>
          </table>
        </div>
      </div>

      <!-- Form Submission with JSON payload -->
      <form method="POST" id="import-commit-form" class="pt-2 border-t border-border">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="import_schools_commit" value="1"/>
        <input type="hidden" name="import_data_json" id="import-data-json"/>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-3">
          <label class="flex items-center gap-2 text-xs text-textMain cursor-pointer">
            <input type="checkbox" name="update_existing" value="1" checked class="rounded text-primary focus:ring-0"/>
            <span>Update existing school records if SEMIS Code already exists</span>
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <button type="button" onclick="resetImportModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Back</button>
          <button type="submit" id="btn-submit-import" class="btn-excel px-4 py-1.5 rounded text-xs font-semibold flex items-center gap-1.5">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            Confirm &amp; Import Schools
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Bulk Print Preview & Execution Modal ── -->
<div id="bulk-print-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-2 sm:p-4 backdrop-blur-xs">
  <div id="bulk-modal-wrapper" class="bg-surface border border-border rounded-xl max-w-5xl w-full p-4 sm:p-6 shadow-2xl relative max-h-[92vh] flex flex-col">
    <!-- Header / Toolbar (hidden in print) -->
    <div class="flex items-center justify-between pb-3.5 border-b border-border mb-4 no-print flex-shrink-0">
      <div class="flex items-center gap-2.5">
        <div id="bulk-modal-icon-wrap" class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15l-2 5l9-5l-9-5l2 5zm0 0v-8"/><circle cx="12" cy="8" r="7"/></svg>
        </div>
        <div>
          <h3 id="bulk-modal-title" class="text-sm font-bold text-textMain">Bulk Appreciation Certificates</h3>
          <p id="bulk-modal-subtitle" class="text-xs text-muted">Ready to print <span id="bulk-modal-count" class="font-bold text-textMain">0</span> document(s) &bull; 1 page per school</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button onclick="triggerBulkPrint()" class="btn-primary px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-sm hover:opacity-95 cursor-pointer">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          <span>Print All / Save PDF</span>
        </button>
        <button onclick="closeBulkPrintModal()" class="text-muted hover:text-textMain text-2xl leading-none px-2 py-1 rounded hover:bg-slate-100 cursor-pointer">&times;</button>
      </div>
    </div>

    <!-- Scrollable Printable Container -->
    <div class="flex-1 overflow-y-auto space-y-6 pr-1 bg-slate-100/60 p-3 sm:p-4 rounded-lg border border-slate-200" id="bulk-print-scroll-wrap">
      <div id="bulk-print-container" class="space-y-6">
        <!-- Rendered dynamically by JS -->
      </div>
    </div>
  </div>
</div>

<script>
const existingSemisCodes = <?= json_encode(array_map('strval', $existing_semis_list)) ?>;
const allSchoolsData = <?= json_encode($schools_dir) ?>;
const schoolCompletionData = <?= json_encode($completion_map) ?>;
const currentDistrictName = <?= json_encode(APP_DISTRICT) ?>;
const currentYearStr = <?= json_encode(date('Y')) ?>;
const currentDateStr = <?= json_encode(date('d F Y')) ?>;

let rawParsedRows = [];
let csvHeaders = [];

function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});

function filterSch(){
  const s = (document.getElementById('sch-search')?.value || '').toLowerCase().trim();
  const t = (document.getElementById('sch-taluka')?.value || '').toLowerCase().trim();
  const l = (document.getElementById('sch-level')?.value || '').toLowerCase().trim();
  const g = (document.getElementById('sch-gender')?.value || '').toLowerCase().trim();
  const p = (document.getElementById('sch-progress')?.value || '').trim();

  let totalMatchingComplete = 0;
  let totalMatchingIncomplete = 0;

  document.querySelectorAll('#sch-tbody tr').forEach(r => {
    const tx = r.textContent.toLowerCase();
    const rProgress = r.getAttribute('data-progress') || '';
    
    const matchesSearch = s === '' || tx.includes(s);
    const matchesTaluka = t === '' || tx.includes(t);
    const matchesLevel  = l === '' || tx.includes(l);
    const matchesGender = g === '' || tx.includes(g);

    if (matchesSearch && matchesTaluka && matchesLevel && matchesGender) {
      if (rProgress === '100') {
        totalMatchingComplete++;
      } else {
        totalMatchingIncomplete++;
      }
    }

    const matchesProg = (p === '') || (p === '100' && rProgress === '100') || (p === 'below100' && rProgress === 'below100');

    if (matchesSearch && matchesTaluka && matchesLevel && matchesGender && matchesProg) {
      r.style.display = '';
    } else {
      r.style.display = 'none';
    }
  });

  // Update dropdown option labels
  const opt100 = document.getElementById('opt-progress-100');
  const optBelow = document.getElementById('opt-progress-below100');
  if (opt100) opt100.textContent = `Complete (100%) (${totalMatchingComplete})`;
  if (optBelow) optBelow.textContent = `Below 100% (${totalMatchingIncomplete})`;

  // Toggle dynamic bulk action buttons
  const btnApprec = document.getElementById('btn-bulk-appreciation');
  const btnWarn = document.getElementById('btn-bulk-warning');
  const countApprec = document.getElementById('count-appreciation');
  const countWarn = document.getElementById('count-warning');

  if (p === '100') {
    if (btnApprec) {
      btnApprec.classList.remove('hidden');
      if (countApprec) countApprec.textContent = totalMatchingComplete;
      btnApprec.disabled = (totalMatchingComplete === 0);
      btnApprec.classList.toggle('opacity-50', totalMatchingComplete === 0);
    }
    if (btnWarn) btnWarn.classList.add('hidden');
  } else if (p === 'below100') {
    if (btnWarn) {
      btnWarn.classList.remove('hidden');
      if (countWarn) countWarn.textContent = totalMatchingIncomplete;
      btnWarn.disabled = (totalMatchingIncomplete === 0);
      btnWarn.classList.toggle('opacity-50', totalMatchingIncomplete === 0);
    }
    if (btnApprec) btnApprec.classList.add('hidden');
  } else {
    if (btnApprec) btnApprec.classList.add('hidden');
    if (btnWarn) btnWarn.classList.add('hidden');
  }
}

function resetSch(){
  ['sch-search','sch-taluka','sch-level','sch-gender','sch-progress'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  filterSch();
}

function openAddSchoolModal() {
  document.getElementById('add-school-modal').classList.remove('hidden');
}
function closeAddSchoolModal() {
  document.getElementById('add-school-modal').classList.add('hidden');
}

function confirmDeleteSchool(semis, name) {
  document.getElementById('del-semis-code').textContent = semis;
  document.getElementById('del-school-name').textContent = name;
  document.getElementById('del-semis-input').value = semis;
  document.getElementById('delete-school-modal').classList.remove('hidden');
}
function closeDeleteModal() {
  document.getElementById('delete-school-modal').classList.add('hidden');
}

function openImportModal() {
  resetImportModal();
  document.getElementById('import-school-modal').classList.remove('hidden');
}
function closeImportModal() {
  document.getElementById('import-school-modal').classList.add('hidden');
}
function resetImportModal() {
  document.getElementById('csv-file-input').value = '';
  document.getElementById('import-step-1').classList.remove('hidden');
  document.getElementById('import-step-2').classList.add('hidden');
  rawParsedRows = [];
  csvHeaders = [];
}

// ─── CSV Parser & Column Matcher ──────────────────────────────────────────────
function parseCsvLine(text) {
  let p = '', row = [''], i = 0, r = 0, q = false;
  for (let c of text) {
    if (c === '"') {
      if (q && text[i+1] === '"') { row[r] += '"'; i++; }
      else { q = !q; }
    } else if (c === ',' && !q) {
      row[++r] = '';
    } else if ((c === '\r' || c === '\n') && !q) {
      // end of row
    } else {
      row[r] += c;
    }
    i++;
  }
  return row.map(s => s.trim());
}

function handleCsvFile(file) {
  if (!file) return;
  document.getElementById('file-name-label').textContent = file.name;

  const reader = new FileReader();
  reader.onload = function(e) {
    const text = e.target.result;
    const lines = text.split(/\r\n|\n/).filter(l => l.trim().length > 0);
    if (lines.length < 2) {
      alert('The CSV file does not have enough rows.');
      return;
    }

    csvHeaders = parseCsvLine(lines[0]);
    rawParsedRows = [];

    for (let idx = 1; idx < lines.length; idx++) {
      const vals = parseCsvLine(lines[idx]);
      if (vals.length === 1 && !vals[0]) continue;
      const obj = {};
      csvHeaders.forEach((h, i) => {
        obj[h] = vals[i] || '';
      });
      rawParsedRows.push(obj);
    }

    renderColumnMapping();
    runImportAnalysis();

    document.getElementById('import-step-1').classList.add('hidden');
    document.getElementById('import-step-2').classList.remove('hidden');
  };
  reader.readAsText(file);
}

// LSU Target Fields for column matching
const targetFields = [
  { key: 'semis_code', label: 'SEMIS Code (Numerical)', required: true, matchPatterns: ['semis', 'semis_code', 'code', 'school_id', 'id'] },
  { key: 'school_name', label: 'School Name', required: true, matchPatterns: ['school', 'school_name', 'name'] },
  { key: 'head_master', label: 'Head Master Name', required: false, matchPatterns: ['head', 'head_master', 'hm', 'principal'] },
  { key: 'cnic', label: 'Head Master CNIC', required: false, matchPatterns: ['cnic', 'nic', 'head_cnic'] },
  { key: 'taluka', label: 'Taluka', required: false, matchPatterns: ['taluka', 'tehsil'] },
  { key: 'level', label: 'Level', required: false, matchPatterns: ['level', 'school_level'] },
  { key: 'gender', label: 'Gender', required: false, matchPatterns: ['gender'] },
  { key: 'enrollment', label: 'Enrollment', required: false, matchPatterns: ['enrollment', 'students', 'total_students'] },
  { key: 'attendance_pct', label: 'Attendance %', required: false, matchPatterns: ['attendance', 'att', 'attendance_pct'] },
  { key: 'status', label: 'Status', required: false, matchPatterns: ['status'] },
  { key: 'phone', label: 'Phone', required: false, matchPatterns: ['phone', 'contact', 'mobile'] },
  { key: 'address', label: 'Address', required: false, matchPatterns: ['address', 'location'] },
  { key: 'teachers', label: 'Teaching Staff', required: false, matchPatterns: ['teachers', 'teaching_staff', 'teaching staff'] },
  { key: 'non_teaching', label: 'Non-teaching Staff', required: false, matchPatterns: ['non_teaching', 'nonteaching', 'non teaching', 'support_staff'] },
  { key: 'classrooms', label: 'Classrooms', required: false, matchPatterns: ['classrooms', 'rooms', 'classroom'] },
  { key: 'facility_electricity', label: 'Electricity Supply', required: false, matchPatterns: ['electricity', 'facility_electricity', 'power'] },
  { key: 'facility_water', label: 'Drinking Water', required: false, matchPatterns: ['water', 'drinking_water', 'facility_water'] },
  { key: 'facility_toilets', label: 'Toilets', required: false, matchPatterns: ['toilets', 'sanitation', 'facility_toilets'] },
  { key: 'facility_boundary_wall', label: 'Boundary Wall', required: false, matchPatterns: ['boundary', 'wall', 'boundary_wall', 'facility_boundary_wall'] },
  { key: 'facility_internet', label: 'Internet Connectivity', required: false, matchPatterns: ['internet', 'connectivity', 'facility_internet'] }
];

let columnMapping = {};

function renderColumnMapping() {
  const container = document.getElementById('column-mapping-grid');
  container.innerHTML = '';
  columnMapping = {};

  targetFields.forEach(tf => {
    let bestMatch = '';
    const tfPatterns = tf.matchPatterns;

    // Auto-match header
    for (const h of csvHeaders) {
      const cleanH = h.toLowerCase().replace(/[^a-z0-9]/g, '');
      for (const p of tfPatterns) {
        if (cleanH.includes(p.replace(/[^a-z0-9]/g, '')) || cleanH === p) {
          bestMatch = h;
          break;
        }
      }
      if (bestMatch) break;
    }

    columnMapping[tf.key] = bestMatch;

    const div = document.createElement('div');
    div.className = 'flex items-center justify-between gap-2 p-1.5 bg-background rounded border border-border';
    div.innerHTML = `
      <span class="font-medium text-textMain text-[11px] truncate ${tf.required ? 'font-bold text-primary' : ''}">
        ${tf.label} ${tf.required ? '<span class="text-danger">*</span>' : ''}
      </span>
      <select class="text-xs border border-border rounded px-2 py-1 bg-surface max-w-44 focus:outline-none focus:border-primary" onchange="updateColumnMapping('${tf.key}', this.value)">
        <option value="">-- Ignore --</option>
        ${csvHeaders.map(h => `<option value="${h}" ${h === bestMatch ? 'selected' : ''}>${h}</option>`).join('')}
      </select>
    `;
    container.appendChild(div);
  });
}

function updateColumnMapping(targetKey, csvCol) {
  columnMapping[targetKey] = csvCol;
  runImportAnalysis();
}

function runImportAnalysis() {
  const semisCol = columnMapping['semis_code'];
  const nameCol  = columnMapping['school_name'];

  if (!semisCol || !nameCol) {
    document.getElementById('stat-total-rows').textContent = rawParsedRows.length;
    document.getElementById('stat-unique-semis').textContent = '0';
    document.getElementById('stat-new-schools').textContent = '0';
    document.getElementById('stat-existing-schools').textContent = '0';
    document.getElementById('preview-table-body').innerHTML = '<tr><td colspan="6" class="p-3 text-center text-danger">Please map both SEMIS Code and School Name columns above.</td></tr>';
    document.getElementById('btn-submit-import').disabled = true;
    return;
  }

  document.getElementById('btn-submit-import').disabled = false;

  const mappedRecords = [];
  const uniqueSemisSet = new Set();
  let nonNumericalCount = 0;
  let newSchoolsCount = 0;
  let existingSchoolsCount = 0;

  rawParsedRows.forEach(row => {
    let semisVal = (row[semisCol] || '').trim();
    let nameVal  = (row[nameCol] || '').trim();

    if (!semisVal && !nameVal) return;

    // Check numerical validity
    const isNumerical = /^[0-9]+$/.test(semisVal);
    if (!isNumerical) {
      nonNumericalCount++;
    }

    if (uniqueSemisSet.has(semisVal)) {
      // duplicate within CSV
    } else {
      uniqueSemisSet.add(semisVal);
    }

    if (existingSemisCodes.includes(semisVal)) {
      existingSchoolsCount++;
    } else {
      newSchoolsCount++;
    }

    const rec = {
      semis_code: semisVal,
      school_name: nameVal,
      head_master: columnMapping['head_master'] ? (row[columnMapping['head_master']] || '').trim() : '',
      cnic: columnMapping['cnic'] ? (row[columnMapping['cnic']] || '').trim() : '',
      taluka: columnMapping['taluka'] ? (row[columnMapping['taluka']] || '').trim() : 'Tando Allahyar',
      level: columnMapping['level'] ? (row[columnMapping['level']] || '').trim() : 'Primary',
      gender: columnMapping['gender'] ? (row[columnMapping['gender']] || '').trim() : 'Co-education',
      enrollment: columnMapping['enrollment'] ? (row[columnMapping['enrollment']] || '').trim() : '150',
      attendance_pct: columnMapping['attendance_pct'] ? (row[columnMapping['attendance_pct']] || '').trim() : '90%',
      status: columnMapping['status'] ? (row[columnMapping['status']] || '').trim() : 'Active',
      phone: columnMapping['phone'] ? (row[columnMapping['phone']] || '').trim() : '',
      address: columnMapping['address'] ? (row[columnMapping['address']] || '').trim() : '',
      teachers: columnMapping['teachers'] ? (row[columnMapping['teachers']] || '').trim() : '',
      non_teaching: columnMapping['non_teaching'] ? (row[columnMapping['non_teaching']] || '').trim() : '',
      classrooms: columnMapping['classrooms'] ? (row[columnMapping['classrooms']] || '').trim() : '',
      facility_electricity: columnMapping['facility_electricity'] ? (row[columnMapping['facility_electricity']] || '').trim() : '',
      facility_water: columnMapping['facility_water'] ? (row[columnMapping['facility_water']] || '').trim() : '',
      facility_toilets: columnMapping['facility_toilets'] ? (row[columnMapping['facility_toilets']] || '').trim() : '',
      facility_boundary_wall: columnMapping['facility_boundary_wall'] ? (row[columnMapping['facility_boundary_wall']] || '').trim() : '',
      facility_internet: columnMapping['facility_internet'] ? (row[columnMapping['facility_internet']] || '').trim() : ''
    };
    mappedRecords.push(rec);
  });

  // Update Stats
  document.getElementById('stat-total-rows').textContent = mappedRecords.length;
  document.getElementById('stat-unique-semis').textContent = uniqueSemisSet.size;
  document.getElementById('stat-new-schools').textContent = newSchoolsCount;
  document.getElementById('stat-existing-schools').textContent = existingSchoolsCount;

  // Warning text
  const warningBox = document.getElementById('import-warning-box');
  const warningText = document.getElementById('import-warning-text');
  if (nonNumericalCount > 0) {
    warningText.textContent = `Found ${nonNumericalCount} row(s) with non-numerical SEMIS codes. LSU Portal requires purely numerical SEMIS codes; these invalid rows will be discarded during import.`;
    warningBox.classList.remove('hidden');
  } else {
    warningBox.classList.add('hidden');
  }

  // Render Preview Table (Top 5)
  const tbody = document.getElementById('preview-table-body');
  tbody.innerHTML = '';
  mappedRecords.slice(0, 5).forEach(r => {
    const isNum = /^[0-9]+$/.test(r.semis_code);
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-background';
    tr.innerHTML = `
      <td class="px-2.5 py-1.5 font-mono ${isNum ? 'text-primary font-semibold' : 'text-danger font-bold line-through'}">
        ${r.semis_code || '<em class="text-danger">Missing</em>'}
      </td>
      <td class="px-2.5 py-1.5 font-medium">${r.school_name || '<em class="text-danger">Missing</em>'}</td>
      <td class="px-2.5 py-1.5 text-muted">${r.head_master || '<span class="text-slate-400">&mdash;</span>'}</td>
      <td class="px-2.5 py-1.5 font-mono text-muted text-[10px]">${r.cnic || '<span class="text-slate-400">&mdash;</span>'}</td>
      <td class="px-2.5 py-1.5 text-muted">${r.taluka || 'Tando Allahyar'}</td>
      <td class="px-2.5 py-1.5 text-right font-mono">${r.enrollment || '0'}</td>
    `;
    tbody.appendChild(tr);
  });

  // Store JSON for form submission
  document.getElementById('import-data-json').value = JSON.stringify(mappedRecords);
}

// Head Master Credentials Modal Handlers
function openCredentialsModalFromBtn(btn) {
  if (!btn) return;
  const semis = btn.getAttribute('data-semis') || '';
  const schoolName = btn.getAttribute('data-school') || '';
  const hmName = btn.getAttribute('data-hm') || '';
  const cnic = btn.getAttribute('data-cnic') || '';
  const pass = btn.getAttribute('data-pass') || '1122';
  openCredentialsModal(semis, schoolName, hmName, cnic, pass);
}

function openCredentialsModal(semis, schoolName, hmName, cnic, pass) {
  const formSemis = document.getElementById('cred-form-semis');
  const title = document.getElementById('cred-school-title');
  const hmNameEl = document.getElementById('cred-hm-name');
  const semisCodeEl = document.getElementById('cred-semis-code');
  const hmCnicEl = document.getElementById('cred-hm-cnic');
  const hmPassEl = document.getElementById('cred-hm-pass');
  const newPassInput = document.getElementById('cred-new-pass-input');
  const portalLink = document.getElementById('cred-portal-link');
  const modal = document.getElementById('credentials-school-modal');

  if (formSemis) formSemis.value = semis;
  if (title) title.innerText = `${schoolName} (SEMIS: ${semis})`;
  if (hmNameEl) hmNameEl.innerText = hmName || 'Not Assigned';
  if (semisCodeEl) semisCodeEl.innerText = semis;
  if (hmCnicEl) hmCnicEl.innerText = cnic || 'N/A';
  if (hmPassEl) {
    hmPassEl.innerText = pass || '1122';
    hmPassEl.dataset.real = pass || '1122';
  }
  if (newPassInput) newPassInput.value = pass || '1122';
  
  const baseUrl = (typeof window.LSU_BASE_URL !== 'undefined' ? window.LSU_BASE_URL : '<?= BASE_URL ?>');
  if (portalLink) {
    portalLink.href = `${baseUrl}/school/dashboard.php?semis=${encodeURIComponent(semis)}`;
  }
  if (modal) {
    modal.classList.remove('hidden');
  }
}

function addCalcEnrollment() {
  const b = parseInt(document.getElementById('add_enrollment_boys')?.value) || 0;
  const g = parseInt(document.getElementById('add_enrollment_girls')?.value) || 0;
  const tot = document.getElementById('add_total_enrollment');
  if (tot) tot.value = b + g;
}

function closeCredentialsModal() {
  const modal = document.getElementById('credentials-school-modal');
  if (modal) modal.classList.add('hidden');
}

function copyCredText(elemId, btn) {
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

function toggleCredPass(elemId, btn) {
  const el = document.getElementById(elemId);
  if (!el) return;
  if (!el.dataset.real) el.dataset.real = el.innerText;
  if (el.innerText === '••••••••') {
    el.innerText = el.dataset.real;
    btn.innerText = 'Hide';
  } else {
    el.innerText = '••••••••';
    btn.innerText = 'Show';
  }
}

function handleRowClick(event, url) {
  if (event.target.closest('button, a, input, select, textarea')) {
    return;
  }
  window.location.href = url;
}

// ─── Bulk Appreciation & Show Cause Notice Print Engine ────────────────────────
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function getMatchingFilteredSchools(targetProgress) {
  const matching = [];
  const rows = document.querySelectorAll('#sch-tbody tr');

  rows.forEach(r => {
    if (r.style.display !== 'none') {
      const semis = r.getAttribute('data-semis') || '';
      const prog = r.getAttribute('data-progress') || '';
      if (!targetProgress || prog === targetProgress) {
        const sch = allSchoolsData.find(s => String(s.semis_code) === String(semis));
        if (sch) {
          matching.push(sch);
        }
      }
    }
  });

  return matching;
}

function generateAppreciationCertificateHtml(school, comp) {
  const semis = escapeHtml(school.semis_code || '');
  const schoolName = escapeHtml(school.school_name || '');
  const hmName = escapeHtml(school.head_master || 'Head Master / Head Mistress');
  const cnic = escapeHtml(school.cnic || 'N/A');
  const taluka = escapeHtml(school.taluka || currentDistrictName);

  return `
    <div class="print-page-break bg-white text-slate-900 border-4 border-double border-primary p-6 sm:p-10 rounded-2xl shadow-md font-sans relative overflow-hidden">
      <!-- Watermark Background -->
      <div class="absolute inset-0 flex items-center justify-center opacity-[0.03] pointer-events-none select-none">
        <svg width="450" height="450" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zm0 9l2.5-1.25L12 8.5l-2.5 1.25L12 11zm0 2.5l-5-2.5-5 2.5 10 5 10-5-5-2.5-5 2.5z"/></svg>
      </div>

      <!-- Top Ornate Header -->
      <div class="text-center pb-4 border-b-2 border-primary/40 relative z-10">
        <div class="text-[11px] tracking-[0.25em] uppercase font-bold text-slate-700">GOVERNMENT OF SINDH</div>
        <div class="text-lg sm:text-2xl font-black text-primary uppercase tracking-tight mt-0.5">SCHOOL EDUCATION &amp; LITERACY DEPARTMENT</div>
        <div class="text-xs sm:text-sm font-bold text-secondary tracking-wide mt-0.5">DISTRICT REFORM SUPPORT UNIT (RSU) &bull; ${escapeHtml(currentDistrictName).toUpperCase()}</div>
        <div class="text-[11px] text-slate-500 font-mono mt-1">LSU/RSU Education Portal &bull; Institutional Quality &amp; Governance Wing</div>
      </div>

      <!-- Certificate Title Header -->
      <div class="text-center my-6 relative z-10">
        <div class="inline-block px-4 py-1 rounded-full bg-emerald-50 border border-emerald-300 text-emerald-800 text-[11px] font-bold uppercase tracking-widest mb-2">
          &star; 100% Institutional Compliance Award &star;
        </div>
        <h1 class="text-2xl sm:text-3xl font-serif font-black text-primary tracking-wide uppercase">
          Certificate of Appreciation
        </h1>
        <div class="text-sm font-semibold text-slate-600 font-serif italic mt-0.5">
          سندِ تحسین و اعترافِ خدمت
        </div>
        <div class="w-24 h-1 bg-gradient-to-r from-secondary to-primary mx-auto mt-2 rounded-full"></div>
      </div>

      <!-- Presentation Statement -->
      <div class="space-y-4 text-center max-w-3xl mx-auto text-xs sm:text-sm text-slate-800 leading-relaxed relative z-10">
        <p class="text-slate-600 font-medium">This certificate of high merit and distinction is proudly presented to:</p>
        
        <div class="bg-slate-50/90 border-2 border-dashed border-primary/30 p-4 rounded-xl shadow-xs">
          <div class="text-lg sm:text-xl font-extrabold text-primary">${hmName}</div>
          <div class="text-xs text-slate-700 font-mono mt-1">
            <strong>CNIC:</strong> ${cnic} &nbsp;&bull;&nbsp; <strong>Designation:</strong> Head Master / Head Mistress
          </div>
          <div class="text-sm sm:text-base font-bold text-slate-900 mt-2">${schoolName}</div>
          <div class="text-xs text-slate-600 font-mono mt-0.5">
            SEMIS Code: <strong>${semis}</strong> &nbsp;|&nbsp; Taluka: <strong>${taluka}</strong> &nbsp;|&nbsp; District: <strong>${escapeHtml(currentDistrictName)}</strong>
          </div>
        </div>

        <p class="text-justify sm:text-center text-xs leading-relaxed text-slate-700 pt-1">
          In official recognition of outstanding leadership, institutional diligence, and timely achievement of <strong>100% data completion &amp; electronic verification</strong> of school infrastructure, facilities assessment, and teaching staff roster on the <strong>Sindh District LSU/RSU Portal</strong> for the Academic Year <strong>${escapeHtml(currentYearStr)}</strong>.
        </p>
      </div>

      <!-- Reference & Date Footer -->
      <div class="flex justify-between items-center text-[11px] font-mono text-slate-600 pt-6 border-t border-slate-200 mt-4 relative z-10">
        <div><strong>Ref No:</strong> SELD/RSU-TAY/AC/${escapeHtml(currentYearStr)}/${semis}</div>
        <div><strong>Date of Issue:</strong> ${escapeHtml(currentDateStr)}</div>
      </div>

      <!-- Signatures -->
      <div class="pt-8 grid grid-cols-2 gap-12 text-center text-xs relative z-10">
        <div class="border-t-2 border-slate-700 pt-2">
          <div class="font-bold text-slate-900">District RSU Coordinator</div>
          <div class="text-[11px] text-slate-600">Reform Support Unit (RSU), ${escapeHtml(currentDistrictName)}</div>
          <div class="text-[10px] text-slate-500 font-mono mt-0.5">SELD &bull; Government of Sindh</div>
        </div>
        <div class="border-t-2 border-slate-700 pt-2">
          <div class="font-bold text-slate-900">District Education Officer (DEO)</div>
          <div class="text-[11px] text-slate-600">School Education &amp; Literacy Department</div>
          <div class="text-[10px] text-slate-500 font-mono mt-0.5">District ${escapeHtml(currentDistrictName)}</div>
        </div>
      </div>
    </div>
  `;
}

function generateWarningNoticeHtml(school, comp) {
  const semis = escapeHtml(school.semis_code || '');
  const schoolName = escapeHtml(school.school_name || '');
  const hmName = escapeHtml(school.head_master || 'Head Master / Head Mistress');
  const cnic = escapeHtml(school.cnic || 'N/A');
  const taluka = escapeHtml(school.taluka || currentDistrictName);
  const pct = comp ? (comp.percentage || 0) : 0;
  const missingCount = comp ? (comp.missing_count || 0) : 0;
  const missingFields = comp && comp.missing_fields ? comp.missing_fields : [];

  let missingRowsHtml = '';
  if (missingFields.length > 0) {
    missingFields.forEach((item, idx) => {
      const imp = escapeHtml(item.importance || 'High');
      const isCrit = imp.toLowerCase() === 'critical';
      missingRowsHtml += `
        <tr>
          <td class="py-1.5 px-2 border-r border-slate-300 text-center font-mono font-bold">${idx + 1}</td>
          <td class="py-1.5 px-2.5 border-r border-slate-300 font-semibold text-slate-800">${escapeHtml(item.category || '')}</td>
          <td class="py-1.5 px-2.5 border-r border-slate-300">
            <div class="font-bold text-slate-900">${escapeHtml(item.label || '')}</div>
            <div class="text-[10px] text-slate-600">${escapeHtml(item.description || '')}</div>
          </td>
          <td class="py-1.5 px-2 border-r border-slate-300 text-center font-bold">
            <span class="px-1.5 py-0.5 rounded text-[10px] ${isCrit ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'}">
              ${imp}
            </span>
          </td>
          <td class="py-1.5 px-2.5 text-slate-700">
            Log in to School Portal &bull; update &amp; save required information immediately.
          </td>
        </tr>
      `;
    });
  } else {
    missingRowsHtml = `<tr><td colspan="5" class="py-2 text-center text-muted">No specific deficit items registered.</td></tr>`;
  }

  return `
    <div class="print-page-break bg-white text-slate-900 p-6 sm:p-8 rounded-xl border border-slate-300 space-y-4 font-sans shadow-md">
      <!-- Official Header -->
      <div class="text-center pb-3 border-b-2 border-slate-800">
        <div class="text-[11px] tracking-widest uppercase font-bold text-slate-700">GOVERNMENT OF SINDH</div>
        <div class="text-base sm:text-lg font-extrabold text-slate-900 uppercase tracking-tight mt-0.5">SCHOOL EDUCATION &amp; LITERACY DEPARTMENT</div>
        <div class="text-xs font-semibold text-slate-700">DISTRICT REFORM SUPPORT UNIT (RSU) &bull; ${escapeHtml(currentDistrictName).toUpperCase()}</div>
        <div class="text-[11px] text-slate-500 mt-0.5">LSU/RSU Education Portal &bull; Institutional Quality &amp; Compliance Wing</div>
      </div>

      <!-- Reference & Date -->
      <div class="flex justify-between items-center text-xs font-mono pt-1">
        <div><strong>Ref No:</strong> SELD/RSU-TAY/SC-DEF/${escapeHtml(currentYearStr)}/${semis}</div>
        <div><strong>Date:</strong> ${escapeHtml(currentDateStr)}</div>
      </div>

      <!-- Addressee -->
      <div class="text-xs space-y-1 bg-slate-50 p-3 rounded-lg border border-slate-200">
        <div><strong>To:</strong> The Head Master / Head Mistress,</div>
        <div class="text-sm font-bold text-slate-900">${schoolName}</div>
        <div class="flex flex-wrap items-center gap-3 text-slate-600 font-mono text-[11px]">
          <span>SEMIS Code: <strong>${semis}</strong></span>
          <span>&bull;</span>
          <span>Taluka: <strong>${taluka}</strong></span>
          <span>&bull;</span>
          <span>Head: <strong>${hmName}</strong></span>
          <span>&bull;</span>
          <span>CNIC: <strong>${cnic}</strong></span>
        </div>
      </div>

      <!-- Subject -->
      <div class="text-xs border-b border-slate-300 pb-1.5">
        <span class="font-bold uppercase tracking-wide text-slate-900">SUBJECT: </span>
        <strong class="text-red-700 uppercase">
          SHOW CAUSE NOTICE &amp; URGENT DIRECTIVE: SUBMISSION OF DEFICIT INSTITUTIONAL &amp; STAFF PROFILE DATA
        </strong>
      </div>

      <!-- Letter Body -->
      <div class="text-xs leading-relaxed space-y-2 text-slate-800 text-justify">
        <p>
          In accordance with the mandatory digital governance and monitoring directives of the School Education &amp; Literacy Department (SELD), Government of Sindh, an official institutional data compliance audit was performed for your school on the <strong>District RSU Portal</strong>.
        </p>
        <p>
          The audit results indicate an overall profile completeness rate of <strong class="text-red-700 text-sm font-bold">${pct}%</strong>. The following <strong class="text-red-700">${missingCount} mandatory information items</strong> remain incomplete or unverified in the portal database:
        </p>
      </div>

      <!-- Checklist Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-[11px] border border-slate-300">
          <thead>
            <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-300">
              <th class="py-1.5 px-2 border-r border-slate-300 text-center w-8">#</th>
              <th class="py-1.5 px-2.5 border-r border-slate-300">Category</th>
              <th class="py-1.5 px-2.5 border-r border-slate-300">Deficit Information</th>
              <th class="py-1.5 px-2 border-r border-slate-300 text-center w-20">Priority</th>
              <th class="py-1.5 px-2.5">Directive for Head Master</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            ${missingRowsHtml}
          </tbody>
        </table>
      </div>

      <!-- Compliance Directive Warning -->
      <div class="p-2.5 bg-red-50 border border-red-200 rounded-lg text-xs text-red-950 font-medium leading-relaxed">
        <strong>COMPLIANCE INSTRUCTION:</strong> You are hereby instructed to log in to the School Head Portal using your official CNIC number (<strong>${cnic}</strong>) and update the missing information within <strong>seven (07) calendar days</strong>. Non-compliance will result in notice escalation to TEVO / DEO office for administrative inquiry.
      </div>

      <!-- Signature Blocks -->
      <div class="pt-6 grid grid-cols-2 gap-8 text-center text-xs">
        <div class="border-t border-slate-400 pt-2">
          <div class="font-bold text-slate-900">District RSU Coordinator</div>
          <div class="text-[11px] text-slate-600">Reform Support Unit (RSU), ${escapeHtml(currentDistrictName)}</div>
          <div class="text-[10px] text-slate-500 font-mono mt-0.5">SELD &bull; Government of Sindh</div>
        </div>
        <div class="border-t border-slate-400 pt-2">
          <div class="font-bold text-slate-900">District Education Officer (DEO)</div>
          <div class="text-[11px] text-slate-600">School Education &amp; Literacy Department</div>
          <div class="text-[10px] text-slate-500 font-mono mt-0.5">District ${escapeHtml(currentDistrictName)}</div>
        </div>
      </div>
    </div>
  `;
}

function openBulkAppreciationModal() {
  const schools = getMatchingFilteredSchools('100');
  if (schools.length === 0) {
    alert('No schools with 100% profile completion are currently visible in the filtered list.');
    return;
  }

  const container = document.getElementById('bulk-print-container');
  container.innerHTML = '';

  schools.forEach(sch => {
    const sCode = String(sch.semis_code || '');
    const comp = schoolCompletionData[sCode] || { percentage: 100, missing_count: 0 };
    container.innerHTML += generateAppreciationCertificateHtml(sch, comp);
  });

  const iconWrap = document.getElementById('bulk-modal-icon-wrap');
  if (iconWrap) {
    iconWrap.className = 'w-9 h-9 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold';
    iconWrap.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15l-2 5l9-5l-9-5l2 5zm0 0v-8"/><circle cx="12" cy="8" r="7"/></svg>';
  }

  document.getElementById('bulk-modal-title').textContent = 'Bulk Appreciation Certificates';
  document.getElementById('bulk-modal-subtitle').innerHTML = `Ready to print <span id="bulk-modal-count" class="font-bold text-emerald-800">${schools.length}</span> certificate(s) &bull; 1 page per school`;

  document.getElementById('bulk-print-modal').classList.remove('hidden');
}

function openBulkWarningModal() {
  const schools = getMatchingFilteredSchools('below100');
  if (schools.length === 0) {
    alert('No schools with incomplete profile records are currently visible in the filtered list.');
    return;
  }

  const container = document.getElementById('bulk-print-container');
  container.innerHTML = '';

  schools.forEach(sch => {
    const sCode = String(sch.semis_code || '');
    const comp = schoolCompletionData[sCode] || { percentage: 0, missing_count: 0, missing_fields: [] };
    container.innerHTML += generateWarningNoticeHtml(sch, comp);
  });

  const iconWrap = document.getElementById('bulk-modal-icon-wrap');
  if (iconWrap) {
    iconWrap.className = 'w-9 h-9 rounded-lg bg-red-100 text-red-800 flex items-center justify-center font-bold';
    iconWrap.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
  }

  document.getElementById('bulk-modal-title').textContent = 'Bulk Show Cause & Deficit Notices';
  document.getElementById('bulk-modal-subtitle').innerHTML = `Ready to print <span id="bulk-modal-count" class="font-bold text-red-800">${schools.length}</span> notice(s) &bull; 1 page per school`;

  document.getElementById('bulk-print-modal').classList.remove('hidden');
}

function triggerBulkPrint() {
  window.print();
}

function closeBulkPrintModal() {
  const modal = document.getElementById('bulk-print-modal');
  if (modal) modal.classList.add('hidden');
}

// Check if progress parameter was passed in URL query
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const progParam = urlParams.get('progress');
  if (progParam && document.getElementById('sch-progress')) {
    document.getElementById('sch-progress').value = progParam;
  }
  filterSch();
});
</script>
</body>
</html>
