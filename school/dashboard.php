<?php
/**
 * school/dashboard.php — School Head Master Dashboard
 * 
 * District RSU Portal (Tando Allahyar District, SELD Sindh)
 * Displays school-specific metrics, student enrollment, today's attendance,
 * infrastructure facilities, and quick operational workflows for the Head Master.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'dashboard';
$page_title  = 'School Dashboard — ' . e($school_name) . ' (' . e($school_semis) . ')';

// ─── Fetch Fresh School-Specific Data from Excel DB ─────────────────────────
$current_school = ExcelDB::getSchoolBySemis($school_semis) ?? $current_school;

$boys_count         = isset($current_school['enrollment_boys']) && $current_school['enrollment_boys'] !== '' ? max(0, (int)$current_school['enrollment_boys']) : null;
$girls_count        = isset($current_school['enrollment_girls']) && $current_school['enrollment_girls'] !== '' ? max(0, (int)$current_school['enrollment_girls']) : null;

// Fallback if not explicitly set
if ($boys_count === null || $girls_count === null) {
    $raw_enroll = max(0, (int)($current_school['enrollment'] ?? 0));
    if (strcasecmp($school_gender, 'girls') === 0) {
        $girls_count = $raw_enroll;
        $boys_count  = 0;
    } elseif (strcasecmp($school_gender, 'boys') === 0) {
        $boys_count  = $raw_enroll;
        $girls_count = 0;
    } else {
        $boys_count  = (int)round($raw_enroll * 0.52);
        $girls_count = $raw_enroll - $boys_count;
    }
}
$total_enrolled     = $boys_count + $girls_count;
$teachers_count     = max(0, (int)($current_school['teachers'] ?? 0));
$classrooms_count   = max(0, (int)($current_school['classrooms'] ?? 0));
$non_teaching_count = max(0, (int)($current_school['non_teaching'] ?? 0));

// Facilities status
$fac_elec    = $current_school['facility_electricity'] ?? 'Solar + Grid';
$fac_water   = $current_school['facility_water'] ?? 'Filtered Plant';
$fac_toilets = $current_school['facility_toilets'] ?? 'Functional Blocks';
$fac_wall    = $current_school['facility_boundary_wall'] ?? 'Secured & Complete';
$fac_net     = $current_school['facility_internet'] ?? 'Broadband / 4G';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#123B63',
            primaryDark: '#0B2946',
            secondary: '#0F766E',
            govGreen: '#046A38',
            govNavy: '#113459',
            surface: '#FFFFFF',
            background: '#F5F7FA',
            textMain: '#172033',
            muted: '#64748B',
            border: '#E2E8F0',
            success: '#15803D',
            warning: '#D97706',
            danger: '#DC2626'
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
            urdu: ['Noto Nastaliq Urdu', 'Arial', 'serif']
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .kpi-card { transition: all 0.2s ease-in-out; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -4px rgba(18, 59, 99, 0.08); }
    .btn-primary { background: #046A38; color: #fff; transition: background 0.15s; }
    .btn-primary:hover { background: #03532C; }
    .btn-navy { background: #113459; color: #fff; transition: background 0.15s; }
    .btn-navy:hover { background: #0B2540; }
    .btn-secondary { background: #F8FAFC; color: #1E293B; border: 1px solid #CBD5E1; }
    .btn-secondary:hover { background: #E2E8F0; }
    .status-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 9999px; }
    .badge-good, .badge-active { background: #DCFCE7; color: #15803D; }
    .badge-attention { background: #FEF3C7; color: #92400E; }
    .badge-not-rep { background: #FEE2E2; color: #991B1B; }
    #sidebar { transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    #overlay { transition: opacity 0.25s; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

  <!-- Top Sindh Government Institutional Header -->
  <div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative border-b border-emerald-500/30">
    <div class="flex items-center gap-2 font-medium">
      <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
      <span>حکومتِ سندھ &bull; <?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
    </div>
    <div class="flex items-center gap-3 text-white/80 text-[11px]">
      <span class="hidden sm:inline">District RSU Portal</span>
      <span class="text-white/40">&bull;</span>
      <span class="font-semibold text-emerald-300"><?= APP_DISTRICT ?></span>
    </div>
  </div>

  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

  <div class="flex flex-1 min-h-0">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <!-- School Main Dashboard Area -->
      <main class="flex-1 p-4 md:p-6 space-y-6">

        <!-- ── 1. School Header Card ─────────────────────────────────────────── -->
        <div class="bg-surface rounded-xl border border-border p-5 sm:p-6 shadow-xs relative overflow-hidden">
          <div class="absolute top-0 right-0 h-full w-48 bg-gradient-to-l from-emerald-50 to-transparent pointer-events-none"></div>

          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
            <div class="flex items-start gap-4">
              <?php if (!empty($school_logo) && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $school_logo))): ?>
                <img src="<?= e($school_logo) ?>" alt="School Logo" class="w-16 h-16 rounded-xl object-cover border-2 border-emerald-600/30 bg-white shadow-sm flex-shrink-0"/>
              <?php else: ?>
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-emerald-700 to-govNavy flex items-center justify-center text-white font-bold text-2xl shadow-sm flex-shrink-0">
                  <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                  </svg>
                </div>
              <?php endif; ?>

              <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                  <h1 class="text-xl font-bold text-textMain tracking-tight leading-snug">
                    <?= e($school_name) ?>
                  </h1>
                  <span class="status-badge <?= e($current_school['status_badge'] ?? 'badge-good') ?>">
                    <?= e($current_school['status'] ?? 'Active') ?>
                  </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted">
                  <div class="flex items-center gap-1 font-mono font-bold text-primary">
                    <span>SEMIS:</span>
                    <span><?= e($school_semis) ?></span>
                  </div>
                  <span class="text-border">&bull;</span>
                  <div>Taluka: <span class="font-medium text-textMain"><?= e($school_taluka) ?></span></div>
                  <span class="text-border">&bull;</span>
                  <div>Level: <span class="font-medium text-textMain"><?= e($school_level) ?> (<?= e($school_gender) ?>)</span></div>
                  <span class="text-border">&bull;</span>
                  <div>HM: <span class="font-medium text-textMain"><?= e($hm_name) ?></span> (CNIC: <span class="font-mono"><?= e($hm_cnic) ?></span>)</div>
                </div>

                <?php if (!empty($school_address)): ?>
                  <div class="text-[11px] text-muted mt-1.5 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                    <span><?= e($school_address) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex flex-wrap items-center gap-2 pt-2 md:pt-0">
              <a href="<?= BASE_URL ?>/school/complaints.php" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold px-3.5 py-2 rounded-lg flex items-center gap-1.5 shadow-xs transition">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                <span>Lodge Grievance</span>
              </a>
              <a href="<?= BASE_URL ?>/school/profile.php" class="btn-primary text-xs font-semibold px-3.5 py-2 rounded-lg flex items-center gap-1.5 shadow-xs">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>School Profile</span>
              </a>
              <a href="<?= BASE_URL ?>/school/settings.php" class="btn-secondary text-xs font-semibold px-3 py-2 rounded-lg flex items-center gap-1.5" title="Change Password & Security">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <span>Password</span>
              </a>
            </div>
          </div>
        </div>

        <!-- ── 2. Real-Time School KPI Cards ─────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

          <!-- Total Enrolled Students -->
          <div class="kpi-card bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-muted text-xs font-semibold uppercase tracking-wider mb-2">
              <span>Total Enrollment</span>
              <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-textMain font-mono"><?= number_format($total_enrolled) ?></div>
            <div class="flex items-center justify-between text-[11px] text-muted mt-2 pt-2 border-t border-border">
              <span>Boys: <strong class="text-textMain"><?= number_format($boys_count) ?></strong></span>
              <span>Girls: <strong class="text-emerald-700"><?= number_format($girls_count) ?></strong></span>
            </div>
          </div>

          <!-- Grievance & Complaints KPI -->
          <?php 
            $allSchoolComplaints = ExcelDB::all('complaints');
            $myComplaints = array_filter($allSchoolComplaints, fn($c) => ($c['semis_code'] ?? '') === $school_semis);
            $myUnreadCount = ExcelDB::getUnreadComplaintsCount('school', $school_semis);
          ?>
          <div class="kpi-card bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-muted text-xs font-semibold uppercase tracking-wider mb-2">
              <span>Grievances / Tickets</span>
              <div class="w-8 h-8 rounded-lg bg-teal-50 text-emerald-700 flex items-center justify-center">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-textMain font-mono flex items-center gap-2">
              <span><?= count($myComplaints) ?></span>
              <?php if ($myUnreadCount > 0): ?>
                <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full animate-pulse"><?= $myUnreadCount ?> New Reply</span>
              <?php endif; ?>
            </div>
            <div class="flex items-center justify-between text-[11px] text-muted mt-2 pt-2 border-t border-border">
              <span>District RSU Desk</span>
              <a href="<?= BASE_URL ?>/school/complaints.php" class="text-emerald-700 hover:underline font-semibold">View Tickets &rarr;</a>
            </div>
          </div>

          <!-- Teaching & Non-Teaching Staff -->
          <div class="kpi-card bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-muted text-xs font-semibold uppercase tracking-wider mb-2">
              <span>Teaching Staff</span>
              <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-textMain font-mono"><?= $teachers_count ?> <span class="text-xs font-normal text-muted">Teachers</span></div>
            <div class="flex items-center justify-between text-[11px] text-muted mt-2 pt-2 border-t border-border">
              <span>Non-Teaching: <strong class="text-textMain"><?= $non_teaching_count ?></strong></span>
              <span>Ratio: <strong class="text-purple-700">1:<?= $teachers_count > 0 ? round($total_enrolled / $teachers_count) : 'N/A' ?></strong></span>
            </div>
          </div>

          <!-- Classrooms & Facilities -->
          <div class="kpi-card bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-muted text-xs font-semibold uppercase tracking-wider mb-2">
              <span>Classrooms &amp; Rooms</span>
              <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-textMain font-mono"><?= $classrooms_count ?> <span class="text-xs font-normal text-muted">Rooms</span></div>
            <div class="flex items-center justify-between text-[11px] text-muted mt-2 pt-2 border-t border-border">
              <span>School Facility Risks</span>
              <a href="<?= BASE_URL ?>/school/at-risk.php" class="text-primary hover:underline font-semibold">View &rarr;</a>
            </div>
          </div>

        </div>

        <!-- ── 3. Basic Facilities Status Grid ───────────────────────────────── -->
        <div class="bg-surface rounded-xl border border-border p-5 shadow-xs">
          <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
            <div>
              <h2 class="text-sm font-bold text-textMain">Basic Infrastructure &amp; Facilities Status</h2>
              <p class="text-xs text-muted">SELD Sindh compliance indicators for <?= e($school_name) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/school/profile.php" class="text-xs text-emerald-700 hover:text-emerald-900 font-semibold flex items-center gap-1">
              Update Facilities &rarr;
            </a>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">

            <!-- Electricity -->
            <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50">
              <div class="text-xs font-semibold text-muted mb-1 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= (stripos($fac_elec, 'None') !== false) ? 'bg-danger' : 'bg-success' ?>"></span>
                <span>Electricity</span>
              </div>
              <div class="text-xs font-bold text-textMain"><?= e($fac_elec) ?></div>
            </div>

            <!-- Drinking Water -->
            <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50">
              <div class="text-xs font-semibold text-muted mb-1 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= (stripos($fac_water, 'None') !== false) ? 'bg-danger' : 'bg-success' ?>"></span>
                <span>Drinking Water</span>
              </div>
              <div class="text-xs font-bold text-textMain"><?= e($fac_water) ?></div>
            </div>

            <!-- Toilets / Washrooms -->
            <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50">
              <div class="text-xs font-semibold text-muted mb-1 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= (stripos($fac_toilets, 'Repair') !== false || stripos($fac_toilets, 'None') !== false) ? 'bg-warning' : 'bg-success' ?>"></span>
                <span>Washrooms</span>
              </div>
              <div class="text-xs font-bold text-textMain"><?= e($fac_toilets) ?></div>
            </div>

            <!-- Boundary Wall -->
            <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50">
              <div class="text-xs font-semibold text-muted mb-1 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= (stripos($fac_wall, 'Secured') !== false) ? 'bg-success' : 'bg-warning' ?>"></span>
                <span>Boundary Wall</span>
              </div>
              <div class="text-xs font-bold text-textMain"><?= e($fac_wall) ?></div>
            </div>

            <!-- Internet / IT -->
            <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50">
              <div class="text-xs font-semibold text-muted mb-1 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= (stripos($fac_net, 'None') !== false) ? 'bg-slate-400' : 'bg-emerald-500' ?>"></span>
                <span>Internet / IT</span>
              </div>
              <div class="text-xs font-bold text-textMain"><?= e($fac_net) ?></div>
            </div>

          </div>
        </div>

        <!-- ── 4. Two-Column Layout: Students Roster Summary & HM Information ──── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- Left Column (2 Cols): School Infrastructure & Enrollment Overview -->
          <div class="lg:col-span-2 bg-surface rounded-xl border border-border p-5 shadow-xs">
            <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
              <div>
                <h2 class="text-sm font-bold text-textMain">School Statistics &amp; Infrastructure</h2>
                <p class="text-xs text-muted">Core enrollment, staffing, and registered facilities for <?= e($school_name) ?></p>
              </div>
              <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/school/profile.php" class="btn-primary text-xs font-semibold px-3 py-1.5 rounded flex items-center gap-1">
                  <span>Manage Profile</span>
                  <span>&rarr;</span>
                </a>
              </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
              <div class="bg-slate-50 border border-border rounded-lg p-3 text-center">
                <div class="text-[11px] text-muted font-medium">Total Enrollment</div>
                <div class="text-lg font-bold font-mono text-primary mt-0.5"><?= number_format($total_enrolled) ?></div>
              </div>
              <div class="bg-slate-50 border border-border rounded-lg p-3 text-center">
                <div class="text-[11px] text-muted font-medium">Classrooms</div>
                <div class="text-lg font-bold font-mono text-textMain mt-0.5"><?= $classrooms_count ?></div>
              </div>
              <div class="bg-slate-50 border border-border rounded-lg p-3 text-center">
                <div class="text-[11px] text-muted font-medium">Teaching Staff</div>
                <div class="text-lg font-bold font-mono text-emerald-700 mt-0.5"><?= $teachers_count ?></div>
              </div>
              <div class="bg-slate-50 border border-border rounded-lg p-3 text-center">
                <div class="text-[11px] text-muted font-medium">Non-Teaching</div>
                <div class="text-lg font-bold font-mono text-textMain mt-0.5"><?= $non_teaching_count ?></div>
              </div>
            </div>

            <!-- Facility Badges -->
            <div class="border-t border-border pt-4">
              <div class="text-xs font-bold text-textMain mb-2.5">Key School Utilities</div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-border">
                  <span class="text-muted flex items-center gap-2">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Boundary Wall
                  </span>
                  <span class="font-semibold text-textMain"><?= e($fac_wall) ?></span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-border">
                  <span class="text-muted flex items-center gap-2">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    Electricity
                  </span>
                  <span class="font-semibold text-textMain"><?= e($fac_elec) ?></span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-border">
                  <span class="text-muted flex items-center gap-2">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    Drinking Water
                  </span>
                  <span class="font-semibold text-textMain"><?= e($fac_water) ?></span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 border border-border">
                  <span class="text-muted flex items-center gap-2">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                    Toilets
                  </span>
                  <span class="font-semibold text-textMain"><?= e($fac_toilets) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column (1 Col): Head Master Profile & SELD Info -->
          <div class="space-y-4">

            <!-- HM Profile Card -->
            <div class="bg-surface rounded-xl border border-border p-5 shadow-xs">
              <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Head Master Profile</h3>
                <a href="<?= BASE_URL ?>/school/settings.php" class="text-xs text-primary hover:underline font-semibold">Edit</a>
              </div>

              <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full bg-govNavy flex items-center justify-center text-white font-bold text-lg shadow-sm">
                  <?= strtoupper(substr($hm_name, 0, 1)) ?>
                </div>
                <div>
                  <div class="font-bold text-sm text-textMain"><?= e($hm_name) ?></div>
                  <div class="text-xs text-muted">Head Master / Mistress</div>
                </div>
              </div>

              <div class="space-y-2 text-xs border-t border-border pt-3">
                <div class="flex items-center justify-between">
                  <span class="text-muted">CNIC (Login ID):</span>
                  <span class="font-mono font-semibold text-textMain"><?= e($hm_cnic) ?></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-muted">Contact Phone:</span>
                  <span class="font-medium text-textMain"><?= e(!empty($hm_phone) ? $hm_phone : 'Not Set') ?></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-muted">Portal Password:</span>
                  <span class="text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Active</span>
                </div>
              </div>

              <a href="<?= BASE_URL ?>/school/settings.php" class="mt-4 w-full block text-center btn-secondary text-xs font-semibold py-2 rounded-lg">
                Change Password / Account Settings
              </a>
            </div>

            <!-- Departmental Notices / Guidelines -->
            <div class="bg-emerald-900 text-white rounded-xl p-5 shadow-sm">
              <div class="flex items-center gap-2 text-emerald-300 text-xs font-bold uppercase tracking-wider mb-2">
                <svg class="w-4 h-4 text-emerald-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                <span>SELD RSU Guidelines</span>
              </div>
              <p class="text-xs text-white/80 leading-relaxed">
                Keep student enrollment records and facility information up to date. Report at-risk students to the District RSU promptly for timely intervention.
              </p>
              <div class="mt-3 pt-3 border-t border-white/10 text-[11px] text-white/60">
                District RSU &bull; Tando Allahyar
              </div>
            </div>

          </div>

        </div>

      </main>

      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <script>
    function openSidebar() {
      const s = document.getElementById('sidebar');
      const o = document.getElementById('overlay');
      if (s) s.classList.remove('-translate-x-full');
      if (o) { o.classList.remove('hidden'); setTimeout(() => o.classList.remove('opacity-0'), 10); }
    }
    function closeSidebar() {
      const s = document.getElementById('sidebar');
      const o = document.getElementById('overlay');
      if (s) s.classList.add('-translate-x-full');
      if (o) { o.classList.add('opacity-0'); setTimeout(() => o.classList.add('hidden'), 250); }
    }
  </script>
</body>
</html>
