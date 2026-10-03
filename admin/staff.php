<?php
/**
 * admin/staff.php — District Staff Directory & Institutional HR Roster
 *
 * Provides District Administration overview of teaching & non-teaching staff
 * registered across all district schools.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/excel_db.php';
require_admin();

$active_page = 'staff';
$page_title  = 'District Staff Directory — ' . APP_NAME;

// ─── Export to CSV Action ─────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_staff_csv') {
    ExcelDB::exportCsv('school_staff');
    exit;
}

$allSchools   = ExcelDB::all('schools');
$allStaff     = ExcelDB::all('school_staff');
$talukas      = ExcelDB::getTalukas(true);

// ─── Metrics Calculation ──────────────────────────────────────────────────────
$totalDistrictStaff = count($allStaff);
$totalTeachingStaff = 0;
$totalNonTeaching   = 0;
$totalActiveStaff   = 0;

// Map staff by SEMIS code
$staffBySchool = [];
foreach ($allStaff as $s) {
    $semis = $s['semis_code'] ?? '';
    if (!isset($staffBySchool[$semis])) {
        $staffBySchool[$semis] = [];
    }
    $staffBySchool[$semis][] = $s;

    $type = strtolower(trim($s['staff_type'] ?? ''));
    if ($type === 'teaching' || str_contains($type, 'teach')) {
        $totalTeachingStaff++;
    } else {
        $totalNonTeaching++;
    }

    if (strtolower(trim($s['status'] ?? '')) === 'active') {
        $totalActiveStaff++;
    }
}

$totalSchoolsCount = count($allSchools);
$schoolsWithStaffCount = count($staffBySchool);
$registrationRate = $totalSchoolsCount > 0 ? round(($schoolsWithStaffCount / $totalSchoolsCount) * 100) : 0;

$selectedSemis = trim($_GET['semis'] ?? '');
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
            primary: '#123B63',
            primaryDark: '#0B2946',
            secondary: '#0F766E',
            surface: '#FFFFFF',
            background: '#F5F7FA',
            textMain: '#172033',
            muted: '#64748B',
            border: '#E2E8F0',
            success: '#15803D',
            warning: '#D97706',
            danger: '#DC2626',
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
    .sidebar-link { transition: background-color .15s; }
    .sidebar-link:hover { background: rgba(255,255,255,.08); }
    .sidebar-link.active { background: rgba(255,255,255,.14); border-left: 3px solid #0F766E; }
    .btn-primary { background: #123B63; color: #fff; transition: background-color .15s; }
    .btn-primary:hover { background: #0B2946; }
    .btn-secondary { background: #F5F7FA; color: #172033; border: 1px solid #E2E8F0; transition: background-color .15s; }
    .btn-secondary:hover { background: #E2E8F0; }
    .table-row:hover { background: #F8FAFC; }
    #sidebar { transition: transform .25s cubic-bezier(.4, 0, .2, 1); }
    #overlay { transition: opacity .25s; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col antialiased">

  <!-- Top Sindh Govt Strip -->
  <div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative border-b border-white/10">
    <div class="flex items-center gap-2">
      <span class="inline-block w-2 h-2 rounded-full bg-teal-400"></span>
      <span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
    </div>
    <div class="hidden sm:flex items-center gap-3 text-white/70">
      <span><?= APP_NAME ?></span>
      <span>&bull;</span>
      <span class="text-teal-300 font-medium">Tando Allahyar District</span>
    </div>
  </div>

  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0 transition-opacity" onclick="closeSidebar()"></div>

  <div class="flex flex-1 overflow-hidden">
    <!-- Admin Sidebar Navigation -->
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Admin Top Bar Header -->
      <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

      <!-- Main Scrollable Content -->
      <main class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">

        <!-- Page Title & Export Action -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-surface p-5 rounded-xl border border-border shadow-xs">
          <div class="flex items-start gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
              <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <div>
              <h1 class="text-xl font-bold text-textMain tracking-tight">District Staff Directory</h1>
              <p class="text-xs text-muted mt-0.5">School-by-School Human Resource Registry &bull; Teaching &amp; Non-Teaching Cadres</p>
            </div>
          </div>

          <div class="flex items-center gap-2.5">
            <a href="?action=export_staff_csv" class="btn-secondary px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center gap-2">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export Staff CSV
            </a>
          </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Total District Staff</div>
            <div class="text-2xl font-black text-textMain mt-1.5 font-mono"><?= number_format($totalDistrictStaff) ?></div>
            <div class="text-[11px] text-emerald-700 font-medium mt-1 flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <?= $totalActiveStaff ?> Active Personnel
            </div>
          </div>

          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Teaching Faculty</div>
            <div class="text-2xl font-black text-emerald-700 mt-1.5 font-mono"><?= number_format($totalTeachingStaff) ?></div>
            <div class="text-[11px] text-muted mt-1">PST, JEST, ECT, HST, SS</div>
          </div>

          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Non-Teaching Staff</div>
            <div class="text-2xl font-black text-indigo-700 mt-1.5 font-mono"><?= number_format($totalNonTeaching) ?></div>
            <div class="text-[11px] text-muted mt-1">Clerks, Lab, Peons, Support</div>
          </div>

          <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-muted uppercase tracking-wider">Roster Coverage Rate</div>
            <div class="text-2xl font-black text-primary mt-1.5 font-mono"><?= $registrationRate ?>%</div>
            <div class="text-[11px] text-muted mt-1 font-mono"><?= $schoolsWithStaffCount ?> of <?= $totalSchoolsCount ?> Schools Reporting</div>
          </div>
        </div>

        <!-- Filter and Search Controls -->
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
          <div class="relative w-full md:w-96">
            <input type="text" id="admin-staff-search" onkeyup="applyStaffFilters()" placeholder="Search by Staff Name, CNIC, Personal No, School..." class="w-full text-xs border border-border rounded-lg pl-9 pr-3 py-2 bg-background focus:outline-none focus:border-primary"/>
            <svg class="w-4 h-4 text-muted absolute left-3 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </div>

          <div class="flex items-center gap-2 w-full md:w-auto overflow-x-auto">
            <select id="taluka-filter" onchange="applyStaffFilters()" class="text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary">
              <option value="">All Talukas</option>
              <?php foreach ($talukas as $t): ?>
                <option value="<?= e($t) ?>"><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>

            <select id="type-filter" onchange="applyStaffFilters()" class="text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary">
              <option value="">All Cadres</option>
              <option value="Teaching">Teaching Only</option>
              <option value="Non-Teaching">Non-Teaching Only</option>
            </select>

            <div class="inline-flex rounded-lg border border-border p-0.5 bg-slate-100">
              <button type="button" id="btn-view-schools" onclick="switchView('schools')" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-white text-textMain shadow-xs transition-all">By School</button>
              <button type="button" id="btn-view-roster" onclick="switchView('roster')" class="px-3 py-1.5 rounded-md text-xs font-semibold text-muted hover:text-textMain transition-all">All Staff Roster</button>
            </div>
          </div>
        </div>

        <!-- ═══ VIEW 1: SCHOOLS ACCORDION LIST WITH EMBEDDED STAFF ═══ -->
        <div id="view-schools-container" class="space-y-4">
          <?php foreach ($allSchools as $sch): 
            $sCode = $sch['semis_code'] ?? '';
            $sStaff = $staffBySchool[$sCode] ?? [];
            $sStaffCount = count($sStaff);
            $sTeachers = 0;
            $sNonTeach = 0;
            foreach ($sStaff as $st) {
                if (strtolower($st['staff_type'] ?? '') === 'teaching' || str_contains(strtolower($st['staff_type'] ?? ''), 'teach')) {
                    $sTeachers++;
                } else {
                    $sNonTeach++;
                }
            }
            $isTarget = ($selectedSemis === $sCode);
            $completion = ExcelDB::calculateSchoolProfileCompletion($sCode);
          ?>
            <div class="school-card bg-surface border border-border rounded-xl shadow-xs overflow-hidden transition-all" data-semis="<?= e($sCode) ?>" data-school="<?= e(strtolower($sch['school_name'] ?? '')) ?>" data-taluka="<?= e($sch['taluka'] ?? '') ?>">
              <!-- School Card Header (Clickable Accordion) -->
              <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 cursor-pointer hover:bg-slate-50/70 border-b border-transparent transition-colors" onclick="toggleSchoolStaff('<?= e($sCode) ?>')">
                <div class="flex items-start gap-3.5">
                  <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-sm flex-shrink-0">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                  </div>
                  <div>
                    <div class="flex items-center gap-2 flex-wrap">
                      <h3 class="text-sm font-bold text-textMain"><?= e($sch['school_name'] ?? '') ?></h3>
                      <span class="font-mono text-[11px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-bold border border-slate-200">
                        SEMIS: <?= e($sCode) ?>
                      </span>
                      <span class="text-[11px] font-semibold text-teal-800 bg-teal-50 px-2 py-0.5 rounded border border-teal-200">
                        <?= e($sch['taluka'] ?? '') ?>
                      </span>
                    </div>
                    <div class="text-xs text-muted flex items-center gap-3 mt-1 flex-wrap">
                      <span>HM: <strong><?= e($sch['head_master'] ?? 'Not Assigned') ?></strong></span>
                      <span>&bull;</span>
                      <span>Level: <?= e($sch['level'] ?? 'Primary') ?></span>
                      <span>&bull;</span>
                      <span>Enrollment: <strong class="font-mono"><?= number_format((int)($sch['enrollment'] ?? 0)) ?></strong></span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-3 self-end sm:self-center">
                  <div class="flex items-center gap-2 text-right">
                    <div>
                      <div class="text-xs font-bold text-textMain font-mono">
                        <?= $sStaffCount ?> Staff Registered
                      </div>
                      <div class="text-[10px] text-muted">
                        <?= $sTeachers ?> Teachers &bull; <?= $sNonTeach ?> Non-Teaching
                      </div>
                    </div>
                  </div>

                  <!-- Missing info tag if any -->
                  <?php if ($completion['percentage'] < 100): ?>
                    <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($sCode) ?>" onclick="event.stopPropagation();" class="text-[10px] font-bold px-2 py-1 rounded-md bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition-colors" title="<?= $completion['missing_count'] ?> profile items missing">
                      <?= $completion['percentage'] ?>% Complete
                    </a>
                  <?php else: ?>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200">
                      100% Profile
                    </span>
                  <?php endif; ?>

                  <button type="button" class="w-8 h-8 rounded-lg border border-border flex items-center justify-center text-muted hover:text-textMain transition-transform" id="icon-<?= e($sCode) ?>" style="<?= $isTarget ? 'transform: rotate(180deg);' : '' ?>">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                  </button>
                </div>
              </div>

              <!-- Accordion Body: Staff Roster -->
              <div id="roster-<?= e($sCode) ?>" class="<?= $isTarget ? '' : 'hidden' ?> border-t border-border bg-slate-50/40">
                <?php if (empty($sStaff)): ?>
                  <div class="p-8 text-center">
                    <p class="text-xs text-muted">No individual staff members registered yet by this school.</p>
                    <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($sCode) ?>" class="text-xs text-primary font-semibold hover:underline mt-1.5 inline-block">
                      View School Profile &amp; Generate Missing Information Notice &rarr;
                    </a>
                  </div>
                <?php else: ?>
                  <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                      <thead>
                        <tr class="bg-slate-100 text-[10px] font-bold text-muted uppercase tracking-wider border-b border-border">
                          <th class="py-2.5 px-4">Staff Member</th>
                          <th class="py-2.5 px-4">Personal ID &amp; CNIC</th>
                          <th class="py-2.5 px-4">Designation &amp; Scale</th>
                          <th class="py-2.5 px-4">Category</th>
                          <th class="py-2.5 px-4">Qualifications</th>
                          <th class="py-2.5 px-4">Appointed</th>
                          <th class="py-2.5 px-4">Status</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-border bg-surface">
                        <?php foreach ($sStaff as $m): 
                          $isT = (strtolower(trim($m['staff_type'] ?? '')) === 'teaching' || str_contains(strtolower($m['staff_type'] ?? ''), 'teach'));
                          $st = trim($m['status'] ?? 'Active');
                        ?>
                          <tr class="table-row">
                            <td class="py-2.5 px-4">
                              <div class="font-bold text-textMain"><?= e($m['full_name'] ?? '') ?></div>
                              <div class="text-[10px] text-muted"><?= e($m['gender'] ?? 'Male') ?> &bull; <?= e($m['contact_phone'] ?? 'No Phone') ?></div>
                            </td>
                            <td class="py-2.5 px-4 font-mono">
                              <div class="font-bold text-slate-800"><?= !empty($m['personal_no']) ? e($m['personal_no']) : '<span class="text-muted font-normal italic">N/A</span>' ?></div>
                              <div class="text-[10px] text-muted"><?= e($m['cnic'] ?? '') ?></div>
                            </td>
                            <td class="py-2.5 px-4">
                              <div class="font-semibold text-textMain"><?= e($m['designation'] ?? '') ?></div>
                              <span class="inline-block font-mono text-[10px] px-1.5 py-0.5 rounded bg-slate-100 font-bold text-slate-700"><?= e($m['bps_scale'] ?? 'BPS-14') ?></span>
                            </td>
                            <td class="py-2.5 px-4">
                              <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full <?= $isT ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' ?>">
                                <?= $isT ? 'Teaching' : 'Non-Teaching' ?>
                              </span>
                            </td>
                            <td class="py-2.5 px-4">
                              <div class="font-medium text-slate-800"><?= e($m['qualification_academic'] ?? 'BA') ?></div>
                              <div class="text-[10px] text-muted"><?= e($m['qualification_professional'] ?? 'None') ?></div>
                            </td>
                            <td class="py-2.5 px-4 font-mono text-muted text-[11px]">
                              <?= !empty($m['appointment_date']) ? date('d M Y', strtotime($m['appointment_date'])) : '—' ?>
                            </td>
                            <td class="py-2.5 px-4">
                              <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <?= e($st) ?>
                              </span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <div class="p-3 bg-slate-50 border-t border-border flex justify-end">
                    <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($sCode) ?>" class="text-xs text-primary hover:underline font-semibold flex items-center gap-1">
                      <span>View Full School Infrastructure &amp; Profile &rarr;</span>
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- ═══ VIEW 2: DISTRICT ALL STAFF ROSTER FLAT TABLE ═══ -->
        <div id="view-roster-container" class="hidden bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
          <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-slate-50/50">
            <h3 class="text-xs font-bold text-textMain uppercase tracking-wider">All District Employees Roster</h3>
            <span class="font-mono text-xs font-bold bg-primary/10 text-primary px-2.5 py-0.5 rounded-full"><?= count($allStaff) ?> Records</span>
          </div>

          <div class="overflow-x-auto">
            <table id="all-staff-table" class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-100 text-[10px] font-bold text-muted uppercase tracking-wider border-b border-border">
                  <th class="py-3 px-4">Staff Member</th>
                  <th class="py-3 px-4">Personal ID &amp; CNIC</th>
                  <th class="py-3 px-4">School &amp; Taluka</th>
                  <th class="py-3 px-4">Designation &amp; Scale</th>
                  <th class="py-3 px-4">Category</th>
                  <th class="py-3 px-4">Qualifications</th>
                  <th class="py-3 px-4">Appointed</th>
                  <th class="py-3 px-4">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <?php 
                $schoolMap = [];
                foreach ($allSchools as $s) {
                    $schoolMap[$s['semis_code'] ?? ''] = $s;
                }
                foreach ($allStaff as $m): 
                  $sCode = $m['semis_code'] ?? '';
                  $sObj  = $schoolMap[$sCode] ?? [];
                  $isT   = (strtolower(trim($m['staff_type'] ?? '')) === 'teaching' || str_contains(strtolower($m['staff_type'] ?? ''), 'teach'));
                  $st    = trim($m['status'] ?? 'Active');
                ?>
                  <tr class="table-row flat-staff-row" data-name="<?= e(strtolower($m['full_name'] ?? '')) ?>" data-cnic="<?= e(strtolower($m['cnic'] ?? '')) ?>" data-personal="<?= e(strtolower($m['personal_no'] ?? '')) ?>" data-school="<?= e(strtolower($sObj['school_name'] ?? '')) ?>" data-taluka="<?= e($sObj['taluka'] ?? '') ?>" data-type="<?= $isT ? 'Teaching' : 'Non-Teaching' ?>">
                    <td class="py-3 px-4">
                      <div class="font-bold text-textMain"><?= e($m['full_name'] ?? '') ?></div>
                      <div class="text-[10px] text-muted"><?= e($m['gender'] ?? 'Male') ?> &bull; <?= e($m['contact_phone'] ?? '') ?></div>
                    </td>
                    <td class="py-3 px-4 font-mono">
                      <div class="font-bold text-slate-800"><?= !empty($m['personal_no']) ? e($m['personal_no']) : '<span class="text-muted font-normal italic">N/A</span>' ?></div>
                      <div class="text-[10px] text-muted"><?= e($m['cnic'] ?? '') ?></div>
                    </td>
                    <td class="py-3 px-4">
                      <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($sCode) ?>" class="font-semibold text-primary hover:underline block truncate max-w-[200px]">
                        <?= e($sObj['school_name'] ?? "SEMIS: {$sCode}") ?>
                      </a>
                      <span class="text-[10px] text-muted font-mono"><?= e($sObj['taluka'] ?? '') ?></span>
                    </td>
                    <td class="py-3 px-4">
                      <div class="font-semibold text-textMain"><?= e($m['designation'] ?? '') ?></div>
                      <span class="inline-block font-mono text-[10px] px-1.5 py-0.5 rounded bg-slate-100 font-bold text-slate-700"><?= e($m['bps_scale'] ?? 'BPS-14') ?></span>
                    </td>
                    <td class="py-3 px-4">
                      <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full <?= $isT ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' ?>">
                        <?= $isT ? 'Teaching' : 'Non-Teaching' ?>
                      </span>
                    </td>
                    <td class="py-3 px-4">
                      <div class="font-medium text-slate-800"><?= e($m['qualification_academic'] ?? 'BA') ?></div>
                      <div class="text-[10px] text-muted"><?= e($m['qualification_professional'] ?? 'None') ?></div>
                    </td>
                    <td class="py-3 px-4 font-mono text-muted text-[11px]">
                      <?= !empty($m['appointment_date']) ? date('d M Y', strtotime($m['appointment_date'])) : '—' ?>
                    </td>
                    <td class="py-3 px-4">
                      <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <?= e($st) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </main>

      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
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

    function toggleSchoolStaff(semis) {
      const el = document.getElementById('roster-' + semis);
      const icon = document.getElementById('icon-' + semis);
      if (!el) return;
      if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        if (icon) icon.style.transform = 'rotate(180deg)';
      } else {
        el.classList.add('hidden');
        if (icon) icon.style.transform = 'rotate(0deg)';
      }
    }

    function switchView(view) {
      const vSchools = document.getElementById('view-schools-container');
      const vRoster  = document.getElementById('view-roster-container');
      const bSchools = document.getElementById('btn-view-schools');
      const bRoster  = document.getElementById('btn-view-roster');

      if (view === 'schools') {
        vSchools.classList.remove('hidden');
        vRoster.classList.add('hidden');
        bSchools.classList.add('bg-white', 'text-textMain', 'shadow-xs');
        bSchools.classList.remove('text-muted');
        bRoster.classList.remove('bg-white', 'text-textMain', 'shadow-xs');
        bRoster.classList.add('text-muted');
      } else {
        vSchools.classList.add('hidden');
        vRoster.classList.remove('hidden');
        bRoster.classList.add('bg-white', 'text-textMain', 'shadow-xs');
        bRoster.classList.remove('text-muted');
        bSchools.classList.remove('bg-white', 'text-textMain', 'shadow-xs');
        bSchools.classList.add('text-muted');
      }
    }

    function applyStaffFilters() {
      const q = (document.getElementById('admin-staff-search')?.value || '').toLowerCase().trim();
      const talukaFilter = document.getElementById('taluka-filter')?.value || '';
      const typeFilter = document.getElementById('type-filter')?.value || '';

      // Filter School Cards
      const cards = document.querySelectorAll('.school-card');
      cards.forEach(card => {
        const schoolName = card.dataset.school || '';
        const semis = card.dataset.semis || '';
        const taluka = card.dataset.taluka || '';

        const matchesQ = !q || schoolName.includes(q) || semis.includes(q);
        const matchesT = !talukaFilter || taluka === talukaFilter;

        if (matchesQ && matchesT) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });

      // Filter Flat Roster Rows
      const rows = document.querySelectorAll('.flat-staff-row');
      rows.forEach(r => {
        const name = r.dataset.name || '';
        const cnic = r.dataset.cnic || '';
        const personal = r.dataset.personal || '';
        const school = r.dataset.school || '';
        const taluka = r.dataset.taluka || '';
        const type = r.dataset.type || '';

        const matchesQ = !q || name.includes(q) || cnic.includes(q) || personal.includes(q) || school.includes(q);
        const matchesT = !talukaFilter || taluka === talukaFilter;
        const matchesType = !typeFilter || type === typeFilter;

        if (matchesQ && matchesT && matchesType) {
          r.style.display = '';
        } else {
          r.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>
