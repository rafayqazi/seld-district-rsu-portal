<?php
/**
 * admin/dashboard.php — Main Dashboard
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'dashboard';
$page_title  = 'Dashboard — ' . APP_NAME;

// Retrieve live metrics from Excel database
$schoolsData    = ExcelDB::all('schools');
$studentsData   = ExcelDB::all('students');
$atRiskData     = ExcelDB::all('school_risks');
$available_talukas = ExcelDB::getTalukas();

$total_schools_count  = count($schoolsData);
$active_schools_count = 0;
$total_enrollment     = 0;

foreach ($schoolsData as $s) {
    $total_enrollment += (int)($s['enrollment'] ?? 0);
    $st = $s['status'] ?? '';
    if ($st === 'Active' || $st === 'Good') {
        $active_schools_count++;
    }
}
$reporting_pct = $total_schools_count > 0 ? round(($active_schools_count / $total_schools_count) * 100) : 100;

$total_students_count = count($studentsData);
$boys_count  = 0;
$girls_count = 0;
foreach ($studentsData as $st) {
    $g = $st['gender'] ?? '';
    if (strcasecmp($g, 'Male') === 0 || strcasecmp($g, 'Boy') === 0) {
        $boys_count++;
    } elseif (strcasecmp($g, 'Female') === 0 || strcasecmp($g, 'Girl') === 0) {
        $girls_count++;
    }
}

// School Infrastructure Risk stats
$high_risk_count     = 0;
$total_at_risk_count = 0;
foreach ($atRiskData as $ar) {
    $stat = strtolower($ar['status'] ?? '');
    if ($stat !== 'resolved') {
        $total_at_risk_count++;
        $sev = strtolower($ar['severity'] ?? '');
        if ($sev === 'critical') {
            $high_risk_count++;
        }
    }
}

// Grievance & Complaints stats
$complaintsData = ExcelDB::all('complaints');
$total_complaints_count = count($complaintsData);
$pending_complaints_count = 0;
foreach ($complaintsData as $c) {
    if (strtolower($c['status'] ?? '') === 'pending' || ($c['unread_admin'] ?? '0') === '1') {
        $pending_complaints_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<meta name="description" content="District RSU Coordinator Education Monitoring Dashboard — School Education &amp; Literacy Department, Government of Sindh"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#123B63', primaryDark: '#0B2946', secondary: '#0F766E',
        surface: '#FFFFFF', background: '#F5F7FA', textMain: '#172033',
        muted: '#64748B', border: '#E2E8F0', success: '#15803D',
        warning: '#D97706', danger: '#DC2626'
      },
      fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
    }
  }
}
</script>
<style>
  body { font-family: 'Inter', system-ui, sans-serif; }
  .sidebar-link { transition: background 0.15s, color 0.15s; }
  .sidebar-link:hover { background: rgba(255,255,255,0.08); }
  .sidebar-link.active { background: rgba(255,255,255,0.14); border-left: 3px solid #0F766E; }
  .sidebar-group-title { font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; }
  .kpi-card { transition: box-shadow 0.2s, transform 0.2s; }
  .kpi-card:hover { box-shadow: 0 4px 16px rgba(18,59,99,0.10); transform: translateY(-1px); }
  .btn-primary { background: #123B63; color: #fff; transition: background 0.15s; }
  .btn-primary:hover { background: #0B2946; }
  .btn-secondary { background: #F5F7FA; color: #172033; border: 1px solid #E2E8F0; transition: background 0.15s; }
  .btn-secondary:hover { background: #E2E8F0; }
  .status-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 9999px; }
  .badge-active   { background: #DCFCE7; color: #15803D; }
  .badge-good     { background: #D1FAE5; color: #065F46; }
  .badge-attention{ background: #FEF3C7; color: #92400E; }
  .badge-critical { background: #FEE2E2; color: #991B1B; }
  .badge-not-rep  { background: #F1F5F9; color: #475569; }
  .table-row:hover { background: #F8FAFC; }
  #sidebar { transition: transform 0.25s cubic-bezier(.4,0,.2,1); }
  #overlay { transition: opacity 0.25s; }
  .progress-bar { transition: width 1s ease; }
  input[type=search]:focus { outline: none; box-shadow: 0 0 0 2px rgba(18,59,99,0.2); }
</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

<!-- Government Bar -->
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span class="font-medium tracking-wide"><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>

<!-- Mobile Overlay -->
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

<div class="flex flex-1 overflow-hidden">

  <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

  <!-- Main Area -->
  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

    <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-4 md:p-6">

      <!-- Breadcrumb -->
      <nav class="text-xs text-muted mb-4 flex items-center gap-1.5" aria-label="Breadcrumb">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        <span>Dashboard</span><span>/</span>
        <span class="text-textMain font-medium">Overview</span>
      </nav>

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-bold text-textMain">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, District Coordinator</h1>
          <p class="text-muted text-sm mt-0.5">District Education Monitoring Dashboard &mdash; <?= APP_DISTRICT ?></p>
        </div>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 text-xs">
          <span class="flex items-center gap-1.5 font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded">
            <span class="inline-block w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
            District Registry: Online
          </span>
          <span class="flex items-center gap-1.5 text-muted">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Last sync: <?= date('d M Y, h:i A') ?>
          </span>
        </div>
      </div>

      <!-- KPI Cards -->
      <section aria-label="Key Performance Indicators" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3">
          <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="#123B63" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide">Total Schools</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= $total_schools_count ?></div>
            <div class="text-xs text-success mt-1 flex items-center gap-1">
              <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
              Official SELD Census
            </div>
          </div>
        </div>
        <div class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3">
          <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="#0F766E" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide">Total Students</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= number_format($total_enrollment > 0 ? $total_enrollment : $total_students_count) ?></div>
            <div class="text-xs text-success mt-1 flex items-center gap-1">
              <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
              <?= $boys_count ?> Boys / <?= $girls_count ?> Girls
            </div>
          </div>
        </div>
        <div class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3">
          <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="#15803D" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide">Active Schools</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= $active_schools_count ?></div>
            <div class="text-xs text-success mt-1"><?= $reporting_pct ?>% of <?= $total_schools_count ?> schools active</div>
          </div>
        </div>
        <div class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3">
          <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="#DC2626" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide">Critical Schools</div>
            <div class="text-3xl font-bold text-danger leading-tight mt-0.5"><?= $high_risk_count ?></div>
            <div class="text-xs text-danger mt-1"><?= $total_at_risk_count ?> schools flagged at-risk</div>
          </div>
        </div>
        <div class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3">
          <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="#0284C7" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide">Schools Reporting</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= $reporting_pct ?>%</div>
            <div class="text-xs text-muted mt-1"><?= $active_schools_count ?> of <?= $total_schools_count ?> schools active</div>
          </div>
        </div>

        <div class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3">
          <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="#4F46E5" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide">Teachers &amp; Staff</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5">2,184</div>
            <div class="text-xs text-muted mt-1">1,876 teachers, 308 non-teaching</div>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/complaints.php" class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3 hover:border-primary transition group">
          <div class="w-10 h-10 rounded-lg bg-teal-50 text-secondary flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide group-hover:text-primary transition-colors">School Complaints</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= $total_complaints_count ?></div>
            <div class="text-xs text-danger mt-1 flex items-center gap-1 font-semibold">
              <span class="w-2 h-2 rounded-full bg-red-500 <?= $pending_complaints_count > 0 ? 'animate-pulse' : '' ?>"></span>
              <?= $pending_complaints_count ?> Pending Action
            </div>
          </div>
        </a>
        <div class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3">
          <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" fill="none" stroke="#EA580C" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide">At-Risk Schools</div>
            <div class="text-3xl font-bold text-orange-700 leading-tight mt-0.5"><?= $total_at_risk_count ?></div>
            <div class="text-xs text-warning mt-1">Infrastructure issues flagged</div>
          </div>
        </div>
      </section>

      <!-- Analytics Row -->
      <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6" aria-label="Analytics">
        <!-- Attendance Trend Chart -->
        <div class="lg:col-span-2 bg-surface border border-border rounded-lg p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-semibold text-textMain">Weekly Attendance Trend</h2>
              <p class="text-xs text-muted mt-0.5">Mon&ndash;Fri &mdash; This week</p>
            </div>
            <div class="flex items-center gap-4 text-xs">
              <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-0.5 bg-primary rounded"></span>Actual</span>
              <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-0.5 bg-border border-dashed border"></span>Target</span>
            </div>
          </div>
          <svg viewBox="0 0 500 200" class="w-full" aria-label="Attendance trend line chart">
            <line x1="50" y1="20" x2="490" y2="20" stroke="#E2E8F0" stroke-width="1"/>
            <line x1="50" y1="57" x2="490" y2="57" stroke="#E2E8F0" stroke-width="1"/>
            <line x1="50" y1="94" x2="490" y2="94" stroke="#E2E8F0" stroke-width="1"/>
            <line x1="50" y1="131" x2="490" y2="131" stroke="#E2E8F0" stroke-width="1"/>
            <line x1="50" y1="168" x2="490" y2="168" stroke="#E2E8F0" stroke-width="1"/>
            <text x="42" y="24" text-anchor="end" fill="#64748B" font-size="10">100%</text>
            <text x="42" y="61" text-anchor="end" fill="#64748B" font-size="10">95%</text>
            <text x="42" y="98" text-anchor="end" fill="#64748B" font-size="10">90%</text>
            <text x="42" y="135" text-anchor="end" fill="#64748B" font-size="10">85%</text>
            <text x="42" y="172" text-anchor="end" fill="#64748B" font-size="10">80%</text>
            <line x1="50" y1="57" x2="490" y2="57" stroke="#0F766E" stroke-width="1.5" stroke-dasharray="5,3" opacity="0.5"/>
            <path d="M110,120 L198,108 L286,90 L374,100 L462,96 L462,168 L110,168 Z" fill="#123B63" opacity="0.06"/>
            <polyline points="110,120 198,108 286,90 374,100 462,96" fill="none" stroke="#123B63" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            <circle cx="110" cy="120" r="4" fill="#123B63" stroke="white" stroke-width="2"/>
            <circle cx="198" cy="108" r="4" fill="#123B63" stroke="white" stroke-width="2"/>
            <circle cx="286" cy="90" r="4" fill="#0F766E" stroke="white" stroke-width="2"/>
            <circle cx="374" cy="100" r="4" fill="#123B63" stroke="white" stroke-width="2"/>
            <circle cx="462" cy="96" r="4" fill="#123B63" stroke="white" stroke-width="2"/>
            <text x="110" y="188" text-anchor="middle" fill="#64748B" font-size="10">Monday</text>
            <text x="198" y="188" text-anchor="middle" fill="#64748B" font-size="10">Tuesday</text>
            <text x="286" y="188" text-anchor="middle" fill="#64748B" font-size="10">Wednesday</text>
            <text x="374" y="188" text-anchor="middle" fill="#64748B" font-size="10">Thursday</text>
            <text x="462" y="188" text-anchor="middle" fill="#64748B" font-size="10">Friday</text>
            <text x="110" y="113" text-anchor="middle" fill="#123B63" font-size="9" font-weight="600">89%</text>
            <text x="198" y="101" text-anchor="middle" fill="#123B63" font-size="9" font-weight="600">91%</text>
            <text x="286" y="83" text-anchor="middle" fill="#0F766E" font-size="9" font-weight="600">93%</text>
            <text x="374" y="93" text-anchor="middle" fill="#123B63" font-size="9" font-weight="600">91%</text>
            <text x="462" y="89" text-anchor="middle" fill="#123B63" font-size="9" font-weight="600">92.5%</text>
          </svg>
          <div class="mt-3 flex gap-4 text-xs text-muted pt-3 border-t border-border">
            <div><span class="font-medium text-textMain">Avg: 91.4%</span> this week</div>
            <div><span class="font-medium text-textMain">Target: 95%</span></div>
            <div class="text-warning font-medium">▼ 3.6% below target</div>
          </div>
        </div>

        <!-- Reporting Status Donut -->
        <div class="bg-surface border border-border rounded-lg p-5">
          <h2 class="text-sm font-semibold text-textMain mb-1">School Reporting Status</h2>
          <p class="text-xs text-muted mb-4">As of today, <?= date('d M Y') ?></p>
          <div class="flex items-center justify-center">
            <svg viewBox="0 0 160 160" class="w-36 h-36" aria-label="School reporting donut chart">
              <circle cx="80" cy="80" r="50" fill="none" stroke="#E2E8F0" stroke-width="20"/>
              <circle cx="80" cy="80" r="50" fill="none" stroke="#DC2626" stroke-width="20" stroke-dasharray="3.14 311.02" stroke-dashoffset="-304.7" transform="rotate(-90 80 80)"/>
              <circle cx="80" cy="80" r="50" fill="none" stroke="#D97706" stroke-width="20" stroke-dasharray="6.28 307.88" stroke-dashoffset="-298.42" transform="rotate(-90 80 80)"/>
              <circle cx="80" cy="80" r="50" fill="none" stroke="#15803D" stroke-width="20" stroke-dasharray="304.74 9.42" stroke-dashoffset="0" transform="rotate(-90 80 80)"/>
              <text x="80" y="75" text-anchor="middle" fill="#172033" font-size="20" font-weight="700">97%</text>
              <text x="80" y="91" text-anchor="middle" fill="#64748B" font-size="9">Reporting</text>
            </svg>
          </div>
          <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-success inline-block"></span>Reporting</span>
              <span class="font-semibold text-textMain">97% <span class="text-muted font-normal">(415)</span></span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-warning inline-block"></span>Pending</span>
              <span class="font-semibold text-textMain">2% <span class="text-muted font-normal">(9)</span></span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-danger inline-block"></span>Not Reporting</span>
              <span class="font-semibold text-textMain">1% <span class="text-muted font-normal">(4)</span></span>
            </div>
          </div>
        </div>
      </section>

      <!-- Performance + Activity -->
      <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6" aria-label="Performance and Activity">
        <!-- District Performance -->
        <div class="bg-surface border border-border rounded-lg p-5">
          <h2 class="text-sm font-semibold text-textMain mb-4">District Performance Indicators</h2>
          <div class="space-y-4">
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">School Reporting</span>
                <span class="font-semibold text-success">97% <span class="text-muted font-normal">/ 100% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-success h-2 rounded-full" style="width:97%"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">Monitoring Completion</span>
                <span class="font-semibold text-warning">78% <span class="text-muted font-normal">/ 100% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-warning h-2 rounded-full" style="width:78%"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">Complaint Resolution</span>
                <span class="font-semibold text-success">86% <span class="text-muted font-normal">/ 90% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-success h-2 rounded-full" style="width:86%"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">Teacher Attendance</span>
                <span class="font-semibold text-success">94% <span class="text-muted font-normal">/ 95% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-success h-2 rounded-full" style="width:94%"></div></div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="lg:col-span-2 bg-surface border border-border rounded-lg p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-textMain">Recent Activity</h2>
            <span class="text-xs text-primary font-medium cursor-pointer hover:underline">View all</span>
          </div>
          <div class="space-y-0">
            <?php
            $activities = [
              ['icon_color' => '#123B63', 'bg' => 'bg-blue-50', 'title' => 'Monitoring report submitted', 'sub' => 'Government Primary School Ranipur', 'time' => '10 min ago'],
              ['icon_color' => '#15803D', 'bg' => 'bg-green-50', 'title' => 'Attendance record updated', 'sub' => 'Government Girls Elementary School B', 'time' => '25 min ago'],
              ['icon_color' => '#15803D', 'bg' => 'bg-green-50', 'title' => 'Complaint resolved', 'sub' => 'Complaint #GRM-1024 &mdash; Infrastructure concern', 'time' => '1 hr ago'],
              ['icon_color' => '#D97706', 'bg' => 'bg-amber-50', 'title' => 'School visit completed', 'sub' => 'Government High School C, Kot Diji', 'time' => '2 hr ago'],
              ['icon_color' => '#DC2626', 'bg' => 'bg-red-50', 'title' => 'At-risk student flagged', 'sub' => 'Student ID STU-4421 — Attendance below 60%', 'time' => '3 hr ago'],
              ['icon_color' => '#0284C7', 'bg' => 'bg-blue-50', 'title' => 'New circular uploaded', 'sub' => 'SELD Circular No. 14/2026 — Academic calendar', 'time' => 'Yesterday'],
            ];
            foreach ($activities as $i => $a):
            $border = ($i < count($activities) - 1) ? 'border-b border-border' : '';
            ?>
            <div class="flex gap-3 py-3 <?= $border ?>">
              <div class="flex-shrink-0 w-8 h-8 rounded-full <?= $a['bg'] ?> flex items-center justify-center mt-0.5">
                <svg width="14" height="14" fill="none" stroke="<?= $a['icon_color'] ?>" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-textMain"><?= $a['title'] ?></div>
                <div class="text-xs text-muted mt-0.5"><?= $a['sub'] ?></div>
              </div>
              <div class="text-xs text-muted flex-shrink-0"><?= $a['time'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <!-- School Monitoring Table -->
      <section class="bg-surface border border-border rounded-lg" aria-label="School Monitoring Table">
        <div class="px-5 py-4 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-semibold text-textMain">School Monitoring Status</h2>
            <p class="text-xs text-muted mt-0.5">Overview of all schools in the district</p>
          </div>
          <a href="<?= BASE_URL ?>/admin/schools.php" class="btn-secondary px-3 py-1.5 rounded text-xs font-medium flex items-center gap-1.5 w-fit">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            View All Schools
          </a>
        </div>
        <div class="px-5 py-3 border-b border-border flex flex-wrap gap-2">
          <input type="text" id="school-search" placeholder="Search school…" class="text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary" onkeyup="filterTable()"/>
          <select id="taluka-filter" class="text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary" onchange="filterTable()">
            <option value="">All Talukas</option>
            <?php foreach ($available_talukas as $t): ?>
            <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="status-filter" class="text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary" onchange="filterTable()">
            <option value="">All Statuses</option>
            <option>Active</option><option>Good</option><option>Needs Attention</option><option>Not Reporting</option>
          </select>
          <button onclick="resetFilters()" class="text-xs border border-border rounded px-3 py-1.5 bg-background text-muted hover:bg-border transition">Reset</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs" id="school-table" aria-label="School monitoring table">
            <thead>
              <tr class="border-b border-border bg-background text-muted uppercase tracking-wide text-left">
                <th class="px-5 py-3 font-semibold">SEMIS CODE</th>
                <th class="px-4 py-3 font-semibold">School Name</th>
                <th class="px-4 py-3 font-semibold">Level</th>
                <th class="px-4 py-3 font-semibold">Taluka</th>
                <th class="px-4 py-3 font-semibold text-right">Enrollment</th>
                <th class="px-4 py-3 font-semibold text-right">Attendance</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold">Action</th>
              </tr>
            </thead>
            <tbody id="school-tbody" class="divide-y divide-border text-textMain">
              <?php
              foreach (array_slice($schoolsData, 0, 7) as $s):
              ?>
              <tr class="table-row">
                <td class="px-5 py-3 font-mono font-semibold text-primary"><?= e($s['semis_code'] ?? '') ?></td>
                <td class="px-4 py-3 font-medium"><?= e($s['school_name'] ?? '') ?></td>
                <td class="px-4 py-3 text-muted"><?= e($s['level'] ?? '') ?></td>
                <td class="px-4 py-3 text-muted"><?= e($s['taluka'] ?? '') ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((int)($s['enrollment'] ?? 0)) ?></td>
                <td class="px-4 py-3 text-right font-medium"><?= e($s['attendance_pct'] ?? '0%') ?></td>
                <td class="px-4 py-3"><span class="status-badge <?= e($s['status_badge'] ?? 'badge-good') ?>"><?= e($s['status'] ?? 'Active') ?></span></td>
                <td class="px-4 py-3">
                  <div class="flex gap-1.5">
                    <a href= BASE_URL . '/admin/school-profile.php?semis=<?= urlencode($s['semis_code'] ?? '') ?>" class="btn-secondary px-2.5 py-1 rounded text-xs">Profile</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted">
          <span>Showing 5 of 428 schools</span>
          <div class="flex items-center gap-1">
            <button class="px-2.5 py-1.5 border border-border rounded hover:bg-background disabled:opacity-40" disabled>Previous</button>
            <button class="px-2.5 py-1.5 border border-primary bg-primary text-white rounded">1</button>
            <button class="px-2.5 py-1.5 border border-border rounded hover:bg-background">2</button>
            <button class="px-2.5 py-1.5 border border-border rounded hover:bg-background">3</button>
            <span class="px-1">…</span>
            <button class="px-2.5 py-1.5 border border-border rounded hover:bg-background">86</button>
            <button class="px-2.5 py-1.5 border border-border rounded hover:bg-background">Next</button>
          </div>
        </div>
      </section>

    </main>

    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>

<!-- School Detail Modal -->
<div id="school-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div class="absolute inset-0 bg-black/40" onclick="closeModal('school-modal')"></div>
  <div class="relative bg-surface rounded-lg shadow-xl w-full max-w-md border border-border z-10">
    <div class="flex items-center justify-between px-5 py-4 border-b border-border">
      <h3 id="modal-title" class="font-semibold text-textMain text-sm">School Details</h3>
      <button onclick="closeModal('school-modal')" class="text-muted hover:text-textMain">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="p-5 space-y-3 text-sm">
      <div class="flex justify-between"><span class="text-muted">School ID</span><span class="font-medium">SCH-001</span></div>
      <div class="flex justify-between"><span class="text-muted">School Name</span><span class="font-medium text-right">GPS Model City, Tando Allahyar</span></div>
      <div class="flex justify-between"><span class="text-muted">Level</span><span class="font-medium">Primary</span></div>
      <div class="flex justify-between"><span class="text-muted">Gender</span><span class="font-medium">Co-education</span></div>
      <div class="flex justify-between"><span class="text-muted">Taluka</span><span class="font-medium">Tando Allahyar</span></div>
      <div class="flex justify-between"><span class="text-muted">Enrollment</span><span class="font-medium">342</span></div>
      <div class="flex justify-between"><span class="text-muted">Today's Attendance</span><span class="font-semibold text-success">93%</span></div>
      <div class="flex justify-between"><span class="text-muted">Status</span><span class="status-badge badge-good">Good</span></div>
    </div>
    <div class="px-5 py-4 border-t border-border flex gap-2 justify-end">
      <button onclick="closeModal('school-modal')" class="btn-secondary px-4 py-2 rounded text-xs font-medium">Close</button>
      <a href="<?= BASE_URL ?>/admin/school-profile.php" class="btn-primary px-4 py-2 rounded text-xs font-medium">Full Profile</a>
    </div>
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
function toggleNotif() { document.getElementById('notif-dropdown').classList.toggle('hidden'); }
document.addEventListener('click', function(e) {
  const btn = document.getElementById('notif-btn');
  const dd  = document.getElementById('notif-dropdown');
  if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) dd.classList.add('hidden');
});
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function filterTable() {
  const s = document.getElementById('school-search').value.toLowerCase();
  const t = document.getElementById('taluka-filter').value.toLowerCase();
  const st= document.getElementById('status-filter').value.toLowerCase();
  document.querySelectorAll('#school-tbody tr').forEach(row => {
    const txt = row.textContent.toLowerCase();
    row.style.display = (txt.includes(s) && (t===''||txt.includes(t)) && (st===''||txt.includes(st))) ? '' : 'none';
  });
}
function resetFilters() {
  document.getElementById('school-search').value='';
  document.getElementById('taluka-filter').value='';
  document.getElementById('status-filter').value='';
  filterTable();
}
window.addEventListener('load', () => {
  document.querySelectorAll('.progress-bar').forEach(bar => {
    const w = bar.style.width; bar.style.width='0';
    setTimeout(() => bar.style.width=w, 100);
  });
});
</script>
</body>
</html>
