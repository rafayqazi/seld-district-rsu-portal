<?php
/**
 * school/staff.php — School Staff & Teacher Management Portal
 *
 * Dedicated institutional staff roster editor for Head Masters & Mistresses.
 * Aligned with official Sindh Education & Literacy Department (SELD) & RSU HRMIS standards.
 */
require_once __DIR__ . '/auth_guard.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/excel_db.php';

$active_page = 'staff';
$page_title  = 'School Staff Details — ' . $school_name;

$notification = '';
$notification_type = 'success';

// ─── Handle Form Submissions ──────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } elseif (isset($_POST['add_staff'])) {
        $fullName       = trim($_POST['full_name'] ?? '');
        $personalNo     = trim($_POST['personal_no'] ?? '');
        $rawCnic        = trim($_POST['cnic'] ?? '');
        $gender         = trim($_POST['gender'] ?? 'Male');
        $staffType      = trim($_POST['staff_type'] ?? 'Teaching');
        $designation    = trim($_POST['designation'] ?? 'PST (Primary School Teacher)');
        $bpsScale       = trim($_POST['bps_scale'] ?? 'BPS-14');
        $qualAcademic   = trim($_POST['qualification_academic'] ?? 'BA / B.Sc');
        $qualProf       = trim($_POST['qualification_professional'] ?? 'B.Ed');
        $phone          = trim($_POST['contact_phone'] ?? '');
        $appointDate    = trim($_POST['appointment_date'] ?? date('Y-m-d'));
        $status         = trim($_POST['status'] ?? 'Active');

        if (empty($fullName)) {
            $notification = 'Staff member full name is required.';
            $notification_type = 'danger';
        } else {
            $formattedCnic = ExcelDB::formatCnic($rawCnic);

            // Generate unique auto-increment ID
            $allStaff = ExcelDB::all('school_staff');
            $maxId = 0;
            foreach ($allStaff as $s) {
                if (isset($s['id']) && is_numeric($s['id']) && (int)$s['id'] > $maxId) {
                    $maxId = (int)$s['id'];
                }
            }
            $newId = (string)($maxId + 1);

            ExcelDB::insert('school_staff', [
                'id'                         => $newId,
                'semis_code'                 => $school_semis,
                'personal_no'                => $personalNo,
                'full_name'                  => $fullName,
                'cnic'                       => $formattedCnic,
                'gender'                     => $gender,
                'staff_type'                 => $staffType,
                'designation'                => $designation,
                'bps_scale'                  => $bpsScale,
                'qualification_academic'     => $qualAcademic,
                'qualification_professional' => $qualProf,
                'contact_phone'              => $phone,
                'appointment_date'           => $appointDate,
                'status'                     => $status,
                'created_at'                 => date('Y-m-d H:i:s'),
                'updated_at'                 => date('Y-m-d H:i:s'),
            ]);

            // Synchronize teacher & non-teacher counts to schools.csv
            ExcelDB::syncSchoolStaffCounts($school_semis);

            header('Location: ' . BASE_URL . '/school/staff.php?msg=added');
            exit;
        }
    } elseif (isset($_POST['edit_staff'])) {
        $staffId        = trim($_POST['staff_id'] ?? '');
        $fullName       = trim($_POST['full_name'] ?? '');
        $personalNo     = trim($_POST['personal_no'] ?? '');
        $rawCnic        = trim($_POST['cnic'] ?? '');
        $gender         = trim($_POST['gender'] ?? 'Male');
        $staffType      = trim($_POST['staff_type'] ?? 'Teaching');
        $designation    = trim($_POST['designation'] ?? 'PST (Primary School Teacher)');
        $bpsScale       = trim($_POST['bps_scale'] ?? 'BPS-14');
        $qualAcademic   = trim($_POST['qualification_academic'] ?? 'BA / B.Sc');
        $qualProf       = trim($_POST['qualification_professional'] ?? 'B.Ed');
        $phone          = trim($_POST['contact_phone'] ?? '');
        $appointDate    = trim($_POST['appointment_date'] ?? date('Y-m-d'));
        $status         = trim($_POST['status'] ?? 'Active');

        if (!empty($staffId) && !empty($fullName)) {
            $formattedCnic = ExcelDB::formatCnic($rawCnic);

            ExcelDB::update('school_staff', 'id', $staffId, [
                'personal_no'                => $personalNo,
                'full_name'                  => $fullName,
                'cnic'                       => $formattedCnic,
                'gender'                     => $gender,
                'staff_type'                 => $staffType,
                'designation'                => $designation,
                'bps_scale'                  => $bpsScale,
                'qualification_academic'     => $qualAcademic,
                'qualification_professional' => $qualProf,
                'contact_phone'              => $phone,
                'appointment_date'           => $appointDate,
                'status'                     => $status,
                'updated_at'                 => date('Y-m-d H:i:s'),
            ]);

            ExcelDB::syncSchoolStaffCounts($school_semis);

            header('Location: ' . BASE_URL . '/school/staff.php?msg=updated');
            exit;
        }
    } elseif (isset($_POST['delete_staff'])) {
        $staffId = trim($_POST['staff_id'] ?? '');
        if (!empty($staffId)) {
            // Verify staff belongs to this school
            $record = ExcelDB::find('school_staff', 'id', $staffId);
            if ($record && ($record['semis_code'] ?? '') === $school_semis) {
                ExcelDB::delete('school_staff', 'id', $staffId);
                ExcelDB::syncSchoolStaffCounts($school_semis);
                header('Location: ' . BASE_URL . '/school/staff.php?msg=deleted');
                exit;
            }
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $notification = 'Staff member successfully registered and added to school roster!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'updated') {
        $notification = 'Staff member profile updated successfully!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $notification = 'Staff member record removed from roster.';
        $notification_type = 'success';
    }
}

// ─── Fetch School Staff Records & Analytics ──────────────────────────────────
$staffList = ExcelDB::getStaffBySemis($school_semis);

$totalStaff       = count($staffList);
$teachingStaff    = 0;
$nonTeachingStaff = 0;
$activeStaff      = 0;

foreach ($staffList as $m) {
    $t = strtolower(trim($m['staff_type'] ?? ''));
    if ($t === 'teaching' || str_contains($t, 'teach')) {
        $teachingStaff++;
    } else {
        $nonTeachingStaff++;
    }
    if (strtolower(trim($m['status'] ?? '')) === 'active') {
        $activeStaff++;
    }
}

$current_school = ExcelDB::getSchoolBySemis($school_semis) ?? [];
$totalEnrollment = (int)($current_school['enrollment'] ?? 0);
$strRatio = ($teachingStaff > 0 && $totalEnrollment > 0) ? round($totalEnrollment / $teachingStaff) : 0;

$completionData = ExcelDB::calculateSchoolProfileCompletion($school_semis);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            govNavy: '#0F2744',
            'govNavy-dark': '#091A2F',
            govGreen: '#006644',
            'govGreen-light': '#00875A',
            surface: '#FFFFFF',
            background: '#F4F6F9',
            textMain: '#1A202C',
            muted: '#718096',
            border: '#E2E8F0',
            danger: '#DC2626',
            warning: '#D97706',
            success: '#15803D',
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
            mono: ['JetBrains Mono', 'monospace'],
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .sidebar-link { transition: background-color .15s, border-color .15s; }
    .btn-primary { background: #006644; color: #fff; transition: background-color .15s, transform .1s; }
    .btn-primary:hover { background: #004D34; }
    .btn-secondary { background: #F8FAFC; color: #1E293B; border: 1px solid #CBD5E1; transition: background-color .15s; }
    .btn-secondary:hover { background: #E2E8F0; }
    .table-row:hover { background: #F8FAFC; }
    #sidebar { transition: transform .25s cubic-bezier(.4, 0, .2, 1); }
    #overlay { transition: opacity .25s; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col antialiased">

  <!-- Top Sindh Govt Identity Strip -->
  <div class="bg-govNavy-dark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative border-b border-white/10">
    <div class="flex items-center gap-2">
      <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
      <span class="font-semibold tracking-wide uppercase text-[11px]"><?= APP_GOVT ?> &bull; <?= APP_DEPARTMENT ?></span>
    </div>
    <div class="hidden sm:flex items-center gap-3 text-[11px] text-white/70">
      <span>School Management Portal</span>
      <span>&bull;</span>
      <span class="text-emerald-300 font-semibold"><?= e($school_taluka) ?> Taluka</span>
    </div>
  </div>

  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0 transition-opacity" onclick="closeSidebar()"></div>

  <div class="flex flex-1 overflow-hidden">
    <!-- School Navigation Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Top App Bar -->
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <!-- Main Content -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <!-- Flash Alert -->
        <?php if (!empty($notification)): ?>
          <div class="p-4 rounded-xl border flex items-center justify-between gap-3 shadow-xs animate-in fade-in duration-200 <?= $notification_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900' ?>">
            <div class="flex items-center gap-2.5 text-xs font-semibold">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
              <?= e($notification) ?>
            </div>
            <button onclick="this.parentElement.remove()" class="text-xs opacity-60 hover:opacity-100 font-bold">&times;</button>
          </div>
        <?php endif; ?>

        <!-- Page Header & Action -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-surface p-5 rounded-2xl border border-border shadow-xs">
          <div class="flex items-start gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center flex-shrink-0 shadow-xs">
              <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-lg sm:text-xl font-extrabold text-textMain tracking-tight">School Staff &amp; Faculty Roster</h1>
                <span class="font-mono text-xs bg-slate-100 text-slate-700 font-bold px-2 py-0.5 rounded border border-slate-200">SEMIS: <?= e($school_semis) ?></span>
              </div>
              <p class="text-xs text-muted mt-0.5">SELD Official Employee Roster &bull; Teaching &amp; Non-Teaching Staff Roster</p>
            </div>
          </div>

          <div class="flex items-center gap-2.5">
            <button onclick="openAddStaffModal()" class="btn-primary px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2 shadow-sm hover:shadow transition-all">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Staff Member
            </button>
          </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 sm:gap-4">
          <!-- Total Staff -->
          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Total Staff Registered</div>
            <div class="text-2xl font-black text-textMain mt-1.5 font-mono"><?= number_format($totalStaff) ?></div>
            <div class="text-[11px] text-emerald-700 font-medium mt-1 flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <?= $activeStaff ?> Active on Duty
            </div>
          </div>

          <!-- Teaching Staff -->
          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Teaching Staff</div>
            <div class="text-2xl font-black text-emerald-700 mt-1.5 font-mono"><?= number_format($teachingStaff) ?></div>
            <div class="text-[11px] text-muted mt-1">PST, JEST, ECT, HST, SS</div>
          </div>

          <!-- Non-Teaching Staff -->
          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Non-Teaching Staff</div>
            <div class="text-2xl font-black text-indigo-700 mt-1.5 font-mono"><?= number_format($nonTeachingStaff) ?></div>
            <div class="text-[11px] text-muted mt-1">Clerks, Lab, Peons, Support</div>
          </div>

          <!-- Student-Teacher Ratio -->
          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Student-Teacher Ratio</div>
            <div class="text-2xl font-black text-blue-700 mt-1.5 font-mono">
              <?= $teachingStaff > 0 ? "1:{$strRatio}" : 'N/A' ?>
            </div>
            <div class="text-[11px] text-muted mt-1 font-mono"><?= number_format($totalEnrollment) ?> Students Enrolled</div>
          </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
          <div class="relative w-full sm:w-80">
            <input type="text" id="staff-search-input" onkeyup="filterStaffTable()" placeholder="Search staff by Name, CNIC, Personal No..." class="w-full text-xs border border-border rounded-lg pl-9 pr-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
            <svg class="w-4 h-4 text-muted absolute left-3 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto overflow-x-auto">
            <select id="staff-type-filter" onchange="filterStaffTable()" class="text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="">All Categories</option>
              <option value="Teaching">Teaching Only</option>
              <option value="Non-Teaching">Non-Teaching Only</option>
            </select>

            <select id="staff-status-filter" onchange="filterStaffTable()" class="text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="">All Statuses</option>
              <option value="Active">Active</option>
              <option value="On Leave">On Leave</option>
              <option value="Transferred">Transferred</option>
              <option value="Deputation">Deputation</option>
            </select>
          </div>
        </div>

        <!-- Staff Records Table -->
        <div class="bg-surface border border-border rounded-2xl shadow-xs overflow-hidden">
          <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2">
              <h3 class="text-xs font-bold text-textMain uppercase tracking-wider">Registered Staff Members</h3>
              <span id="staff-count-badge" class="font-mono text-xs bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full"><?= count($staffList) ?></span>
            </div>
            <button onclick="openAddStaffModal()" class="text-xs text-govGreen font-semibold hover:underline flex items-center gap-1">
              <span>+ Add New Member</span>
            </button>
          </div>

          <?php if (empty($staffList)): ?>
            <div class="text-center py-16 px-4">
              <div class="w-16 h-16 rounded-full bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <h4 class="text-sm font-bold text-textMain">No Staff Members Registered Yet</h4>
              <p class="text-xs text-muted max-w-sm mx-auto mt-1">Please register your teaching and support staff members to complete your school institutional profile.</p>
              <button onclick="openAddStaffModal()" class="btn-primary mt-4 px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2">
                + Register First Staff Member
              </button>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table id="staff-table" class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="border-b border-border bg-slate-50 text-[11px] font-bold text-muted uppercase tracking-wider">
                    <th class="py-3 px-4">Staff Member</th>
                    <th class="py-3 px-4">Personal No / CNIC</th>
                    <th class="py-3 px-4">Designation &amp; Scale</th>
                    <th class="py-3 px-4">Category</th>
                    <th class="py-3 px-4">Qualifications</th>
                    <th class="py-3 px-4">Appointed</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <?php foreach ($staffList as $m): 
                    $isTeaching = (strtolower(trim($m['staff_type'] ?? '')) === 'teaching' || str_contains(strtolower($m['staff_type'] ?? ''), 'teach'));
                    $st = trim($m['status'] ?? 'Active');
                    $stBadge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    if ($st === 'On Leave') $stBadge = 'bg-amber-50 text-amber-700 border-amber-200';
                    elseif ($st === 'Transferred') $stBadge = 'bg-slate-100 text-slate-700 border-slate-200';
                    elseif ($st === 'Deputation') $stBadge = 'bg-purple-50 text-purple-700 border-purple-200';
                  ?>
                    <tr class="table-row transition-colors" data-name="<?= e(strtolower($m['full_name'] ?? '')) ?>" data-cnic="<?= e(strtolower($m['cnic'] ?? '')) ?>" data-personal="<?= e(strtolower($m['personal_no'] ?? '')) ?>" data-type="<?= $isTeaching ? 'Teaching' : 'Non-Teaching' ?>" data-status="<?= e($st) ?>">
                      <td class="py-3 px-4">
                        <div class="flex items-center gap-2.5">
                          <div class="w-8 h-8 rounded-full <?= $isTeaching ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800' ?> flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                            <?= strtoupper(substr(trim($m['full_name'] ?? 'S'), 0, 1)) ?>
                          </div>
                          <div>
                            <div class="font-bold text-textMain text-xs"><?= e($m['full_name'] ?? '') ?></div>
                            <div class="text-[10px] text-muted flex items-center gap-1.5 mt-0.5">
                              <span><?= e($m['gender'] ?? 'Male') ?></span>
                              <?php if (!empty($m['contact_phone'])): ?>
                                <span>&bull;</span>
                                <span class="font-mono"><?= e($m['contact_phone']) ?></span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="py-3 px-4 font-mono">
                        <div class="font-bold text-slate-800"><?= !empty($m['personal_no']) ? e($m['personal_no']) : '<span class="text-muted font-normal italic">N/A</span>' ?></div>
                        <div class="text-[11px] text-muted"><?= e($m['cnic'] ?? '') ?></div>
                      </td>
                      <td class="py-3 px-4">
                        <div class="font-semibold text-textMain"><?= e($m['designation'] ?? '') ?></div>
                        <span class="inline-block font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 mt-0.5">
                          <?= e($m['bps_scale'] ?? 'BPS-14') ?>
                        </span>
                      </td>
                      <td class="py-3 px-4">
                        <?php if ($isTeaching): ?>
                          <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Teaching
                          </span>
                        <?php else: ?>
                          <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            Non-Teaching
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3 px-4">
                        <div class="font-medium text-slate-800"><?= e($m['qualification_academic'] ?? 'BA') ?></div>
                        <div class="text-[10px] text-muted"><?= e($m['qualification_professional'] ?? 'None') ?></div>
                      </td>
                      <td class="py-3 px-4 font-mono text-muted text-[11px]">
                        <?= !empty($m['appointment_date']) ? date('d M Y', strtotime($m['appointment_date'])) : '—' ?>
                      </td>
                      <td class="py-3 px-4">
                        <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full border <?= $stBadge ?>">
                          <?= e($st) ?>
                        </span>
                      </td>
                      <td class="py-3 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                          <button onclick='openEditStaffModal(<?= json_encode($m) ?>)' class="p-1.5 rounded-lg border border-border text-slate-600 hover:text-govGreen hover:border-govGreen hover:bg-emerald-50/50 transition-colors" title="Edit Staff Member">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          </button>
                          <button onclick="confirmDeleteStaff('<?= e($m['id']) ?>', '<?= e(addslashes($m['full_name'] ?? 'Staff Member')) ?>')" class="p-1.5 rounded-lg border border-border text-slate-600 hover:text-danger hover:border-red-300 hover:bg-red-50/50 transition-colors" title="Delete Staff Member">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </main>

      <?php require_once __DIR__ . '/includes/footer.php'; ?>
    </div>
  </div>

  <!-- Add Staff Modal -->
  <div id="add-staff-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl max-w-xl w-full p-6 shadow-2xl relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between pb-3.5 border-b border-border mb-4 sticky top-0 bg-surface z-10">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-textMain">Register New Staff Member</h3>
            <p class="text-xs text-muted">SELD Official HR Information &bull; SEMIS: <?= e($school_semis) ?></p>
          </div>
        </div>
        <button onclick="closeAddStaffModal()" class="text-muted hover:text-textMain text-xl leading-none px-1.5 py-0.5 rounded hover:bg-slate-100">&times;</button>
      </div>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="add_staff" value="1"/>

        <!-- Row 1: Name & Personal No -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-textMain mb-1">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" required placeholder="e.g. Ghulam Qadir Soomro" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Personal ID No</label>
            <input type="text" name="personal_no" placeholder="e.g. 10482911" class="w-full text-xs font-mono border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
            <span class="text-[10px] text-muted">SELD Employee ID</span>
          </div>
        </div>

        <!-- Row 2: CNIC & Gender -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">CNIC Number <span class="text-danger">*</span></label>
            <input type="text" name="cnic" required placeholder="41302-XXXXXXX-X" class="w-full text-xs font-mono border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Gender</label>
            <select name="gender" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
        </div>

        <!-- Row 3: Staff Category & Designation -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Staff Category <span class="text-danger">*</span></label>
            <select name="staff_type" id="add_staff_type" onchange="updateDesignationOptions('add')" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen font-medium">
              <option value="Teaching">Teaching Faculty</option>
              <option value="Non-Teaching">Non-Teaching Staff</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Designation <span class="text-danger">*</span></label>
            <select name="designation" id="add_designation" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="PST (Primary School Teacher)">PST (Primary School Teacher)</option>
              <option value="JEST (Junior Elementary)">JEST (Junior Elementary)</option>
              <option value="ECT (Early Childhood Teacher)">ECT (Early Childhood Teacher)</option>
              <option value="HST (High School Teacher)">HST (High School Teacher)</option>
              <option value="Subject Specialist (SS)">Subject Specialist (SS)</option>
              <option value="Head Master / Head Mistress">Head Master / Head Mistress</option>
              <option value="Physical Training Instructor (PTI)">PTI (Physical Training)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">BPS Scale</label>
            <select name="bps_scale" class="w-full text-xs font-mono border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <?php foreach (['BPS-01', 'BPS-02', 'BPS-04', 'BPS-05', 'BPS-07', 'BPS-09', 'BPS-11', 'BPS-14', 'BPS-15', 'BPS-16', 'BPS-17', 'BPS-18', 'BPS-19'] as $b): ?>
                <option value="<?= $b ?>" <?= $b === 'BPS-14' ? 'selected' : '' ?>><?= $b ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Row 4: Qualifications -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Academic Qualification</label>
            <select name="qualification_academic" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="Matriculation">Matriculation (SSC)</option>
              <option value="Intermediate">Intermediate (HSSC)</option>
              <option value="BA / B.Sc">BA / B.Sc (14-Years)</option>
              <option value="BS (4-Years)">BS / B.Sc (Hons) (16-Years)</option>
              <option value="MA / M.Sc">MA / M.Sc (16-Years)</option>
              <option value="MS / M.Phil">MS / M.Phil (18-Years)</option>
              <option value="PhD">PhD</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Professional Qualification</label>
            <select name="qualification_professional" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="B.Ed (1.5 Years)">B.Ed (1.5 Years)</option>
              <option value="B.Ed (2.5 Years)">B.Ed (2.5 Years)</option>
              <option value="B.Ed (Hons 4-Years)">B.Ed (Hons 4-Years)</option>
              <option value="M.Ed / B.Ed">M.Ed / B.Ed</option>
              <option value="PTC / CT">PTC / CT</option>
              <option value="IT / Office Diploma">IT / Office Diploma</option>
              <option value="None / Not Applicable">None / Not Applicable</option>
            </select>
          </div>
        </div>

        <!-- Row 5: Contact, Appointment Date & Status -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Mobile Phone</label>
            <input type="text" name="contact_phone" placeholder="+92 300 0000000" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Appointment Date</label>
            <input type="date" name="appointment_date" value="<?= date('Y-m-d') ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen font-mono"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Duty Status</label>
            <select name="status" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="Active">Active on Duty</option>
              <option value="On Leave">On Leave</option>
              <option value="Transferred">Transferred</option>
              <option value="Deputation">Deputation</option>
            </select>
          </div>
        </div>

        <div class="pt-3.5 border-t border-border flex justify-end gap-2.5 sticky bottom-0 bg-surface">
          <button type="button" onclick="closeAddStaffModal()" class="btn-secondary px-4 py-2 rounded-xl text-xs font-medium">Cancel</button>
          <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm">
            Save Staff Member
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Staff Modal -->
  <div id="edit-staff-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl max-w-xl w-full p-6 shadow-2xl relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between pb-3.5 border-b border-border mb-4 sticky top-0 bg-surface z-10">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-textMain">Edit Staff Member Details</h3>
            <p class="text-xs text-muted">Update official credentials and service scale</p>
          </div>
        </div>
        <button onclick="closeEditStaffModal()" class="text-muted hover:text-textMain text-xl leading-none px-1.5 py-0.5 rounded hover:bg-slate-100">&times;</button>
      </div>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="edit_staff" value="1"/>
        <input type="hidden" name="staff_id" id="edit_staff_id" value=""/>

        <!-- Row 1: Name & Personal No -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-textMain mb-1">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" id="edit_full_name" required class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Personal ID No</label>
            <input type="text" name="personal_no" id="edit_personal_no" class="w-full text-xs font-mono border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
        </div>

        <!-- Row 2: CNIC & Gender -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">CNIC Number <span class="text-danger">*</span></label>
            <input type="text" name="cnic" id="edit_cnic" required class="w-full text-xs font-mono border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Gender</label>
            <select name="gender" id="edit_gender" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
        </div>

        <!-- Row 3: Staff Category & Designation -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Staff Category <span class="text-danger">*</span></label>
            <select name="staff_type" id="edit_staff_type" onchange="updateDesignationOptions('edit')" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen font-medium">
              <option value="Teaching">Teaching Faculty</option>
              <option value="Non-Teaching">Non-Teaching Staff</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Designation <span class="text-danger">*</span></label>
            <input type="text" name="designation" id="edit_designation" required class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">BPS Scale</label>
            <select name="bps_scale" id="edit_bps_scale" class="w-full text-xs font-mono border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <?php foreach (['BPS-01', 'BPS-02', 'BPS-04', 'BPS-05', 'BPS-07', 'BPS-09', 'BPS-11', 'BPS-14', 'BPS-15', 'BPS-16', 'BPS-17', 'BPS-18', 'BPS-19'] as $b): ?>
                <option value="<?= $b ?>"><?= $b ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Row 4: Qualifications -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Academic Qualification</label>
            <input type="text" name="qualification_academic" id="edit_qualification_academic" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Professional Qualification</label>
            <input type="text" name="qualification_professional" id="edit_qualification_professional" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
        </div>

        <!-- Row 5: Contact, Appointment Date & Status -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Mobile Phone</label>
            <input type="text" name="contact_phone" id="edit_contact_phone" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Appointment Date</label>
            <input type="date" name="appointment_date" id="edit_appointment_date" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen font-mono"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Duty Status</label>
            <select name="status" id="edit_status" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="Active">Active on Duty</option>
              <option value="On Leave">On Leave</option>
              <option value="Transferred">Transferred</option>
              <option value="Deputation">Deputation</option>
            </select>
          </div>
        </div>

        <div class="pt-3.5 border-t border-border flex justify-end gap-2.5 sticky bottom-0 bg-surface">
          <button type="button" onclick="closeEditStaffModal()" class="btn-secondary px-4 py-2 rounded-xl text-xs font-medium">Cancel</button>
          <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm">
            Save Updates
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Staff Confirmation Modal -->
  <div id="delete-staff-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-2xl max-w-sm w-full p-6 shadow-2xl relative animate-in fade-in zoom-in duration-150">
      <div class="w-12 h-12 rounded-full bg-red-100 text-danger flex items-center justify-center mx-auto mb-3">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
      </div>
      <h3 class="text-sm font-bold text-center text-textMain">Remove Staff Member</h3>
      <p class="text-xs text-center text-muted mt-1.5">
        Are you sure you want to remove <span id="delete-staff-name" class="font-bold text-textMain"></span> from the school staff roster?
      </p>

      <form method="POST" class="mt-5 flex items-center justify-center gap-2.5">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="delete_staff" value="1"/>
        <input type="hidden" name="staff_id" id="delete-staff-id" value=""/>
        <button type="button" onclick="closeDeleteStaffModal()" class="btn-secondary px-4 py-2 rounded-xl text-xs font-medium">Cancel</button>
        <button type="submit" class="bg-danger hover:bg-red-700 text-white px-4 py-2 rounded-xl text-xs font-semibold">Delete Member</button>
      </form>
    </div>
  </div>

  <script>
    function openSidebar() {
      document.getElementById('sidebar').classList.remove('-translate-x-full');
      const o = document.getElementById('overlay');
      o.classList.remove('hidden');
      setTimeout(() => o.classList.remove('opacity-0'), 10);
    }
    function closeSidebar() {
      document.getElementById('sidebar').classList.add('-translate-x-full');
      const o = document.getElementById('overlay');
      o.classList.add('opacity-0');
      setTimeout(() => o.classList.add('hidden'), 250);
    }

    function openAddStaffModal() {
      document.getElementById('add-staff-modal').classList.remove('hidden');
    }
    function closeAddStaffModal() {
      document.getElementById('add-staff-modal').classList.add('hidden');
    }

    function openEditStaffModal(staff) {
      document.getElementById('edit_staff_id').value = staff.id || '';
      document.getElementById('edit_full_name').value = staff.full_name || '';
      document.getElementById('edit_personal_no').value = staff.personal_no || '';
      document.getElementById('edit_cnic').value = staff.cnic || '';
      document.getElementById('edit_gender').value = staff.gender || 'Male';
      document.getElementById('edit_staff_type').value = staff.staff_type || 'Teaching';
      document.getElementById('edit_designation').value = staff.designation || '';
      document.getElementById('edit_bps_scale').value = staff.bps_scale || 'BPS-14';
      document.getElementById('edit_qualification_academic').value = staff.qualification_academic || '';
      document.getElementById('edit_qualification_professional').value = staff.qualification_professional || '';
      document.getElementById('edit_contact_phone').value = staff.contact_phone || '';
      document.getElementById('edit_appointment_date').value = staff.appointment_date || '';
      document.getElementById('edit_status').value = staff.status || 'Active';

      document.getElementById('edit-staff-modal').classList.remove('hidden');
    }
    function closeEditStaffModal() {
      document.getElementById('edit-staff-modal').classList.add('hidden');
    }

    function confirmDeleteStaff(id, name) {
      document.getElementById('delete-staff-id').value = id;
      document.getElementById('delete-staff-name').innerText = name;
      document.getElementById('delete-staff-modal').classList.remove('hidden');
    }
    function closeDeleteStaffModal() {
      document.getElementById('delete-staff-modal').classList.add('hidden');
    }

    const teachingDesignations = [
      'PST (Primary School Teacher)',
      'JEST (Junior Elementary)',
      'ECT (Early Childhood Teacher)',
      'HST (High School Teacher)',
      'Subject Specialist (SS)',
      'Head Master / Head Mistress',
      'Physical Training Instructor (PTI)',
      'Drawing Teacher (DT)',
      'Arabic / Theology Teacher',
    ];

    const nonTeachingDesignations = [
      'Junior Clerk',
      'Senior Clerk / Head Clerk',
      'Lab Assistant',
      'Lab Attendant',
      'Naib Qasid / Peon',
      'Chowkidar / Security Guard',
      'Mali',
      'Sanitary Worker / Sweeper',
      'Water Carrier',
    ];

    function updateDesignationOptions(prefix) {
      const typeSelect = document.getElementById(prefix + '_staff_type');
      const desSelect  = document.getElementById(prefix + '_designation');
      if (!typeSelect || !desSelect) return;

      const isTeaching = typeSelect.value === 'Teaching';
      const list = isTeaching ? teachingDesignations : nonTeachingDesignations;

      if (desSelect.tagName.toLowerCase() === 'select') {
        desSelect.innerHTML = '';
        list.forEach(d => {
          const opt = document.createElement('option');
          opt.value = d;
          opt.textContent = d;
          desSelect.appendChild(opt);
        });
      }
    }

    function filterStaffTable() {
      const q = (document.getElementById('staff-search-input')?.value || '').toLowerCase().trim();
      const typeFilter = document.getElementById('staff-type-filter')?.value || '';
      const statusFilter = document.getElementById('staff-status-filter')?.value || '';

      const rows = document.querySelectorAll('#staff-table tbody tr');
      let visible = 0;

      rows.forEach(r => {
        const name = r.dataset.name || '';
        const cnic = r.dataset.cnic || '';
        const personal = r.dataset.personal || '';
        const type = r.dataset.type || '';
        const status = r.dataset.status || '';

        const matchesQuery = !q || name.includes(q) || cnic.includes(q) || personal.includes(q);
        const matchesType = !typeFilter || type === typeFilter;
        const matchesStatus = !statusFilter || status === statusFilter;

        if (matchesQuery && matchesType && matchesStatus) {
          r.style.display = '';
          visible++;
        } else {
          r.style.display = 'none';
        }
      });

      const badge = document.getElementById('staff-count-badge');
      if (badge) badge.innerText = visible;
    }
  </script>
</body>
</html>
