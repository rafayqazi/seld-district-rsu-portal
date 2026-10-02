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
$schoolsData       = ExcelDB::all('schools');
$atRiskData        = ExcelDB::all('school_risks');
$complaintsData    = ExcelDB::all('complaints');
$available_talukas = ExcelDB::getTalukas();

$total_schools_count         = count($schoolsData);
$active_schools_count        = 0;
$attention_schools_count     = 0;
$not_reporting_schools_count = 0;

$total_enrollment   = 0;
$total_boys         = 0;
$total_girls        = 0;
$total_teachers     = 0;
$total_non_teaching = 0;
$total_classrooms   = 0;
$schools_with_water = 0;

// Enrollment breakdown by school level & totals
$enrollment_by_level = [];
foreach ($schoolsData as $s) {
    $enr = (int)($s['enrollment'] ?? 0);
    $total_enrollment += $enr;
    
    $b  = isset($s['enrollment_boys']) && $s['enrollment_boys'] !== '' ? (int)$s['enrollment_boys'] : null;
    $gl = isset($s['enrollment_girls']) && $s['enrollment_girls'] !== '' ? (int)$s['enrollment_girls'] : null;
    if ($b === null || $gl === null) {
        $g = strtolower(trim($s['gender'] ?? ''));
        if ($g === 'girls') {
            $gl = $enr;
            $b  = 0;
        } elseif ($g === 'boys') {
            $b  = $enr;
            $gl = 0;
        } else {
            $b  = (int)round($enr * 0.52);
            $gl = $enr - $b;
        }
    }
    $total_boys  += $b;
    $total_girls += $gl;
    
    $total_teachers     += (int)($s['teachers'] ?? 0);
    $total_non_teaching += (int)($s['non_teaching'] ?? 0);
    $total_classrooms   += (int)($s['classrooms'] ?? 0);

    $w = strtolower(trim($s['facility_water'] ?? ''));
    if (!empty($w) && !in_array($w, ['none', 'unavailable'])) {
        $schools_with_water++;
    }

    $st = $s['status'] ?? '';
    if ($st === 'Active' || $st === 'Good') {
        $active_schools_count++;
    } elseif ($st === 'Needs Attention') {
        $attention_schools_count++;
    } elseif ($st === 'Not Reporting') {
        $not_reporting_schools_count++;
    } else {
        $active_schools_count++;
    }

    $lvl = $s['level'] ?? 'Other';
    $enrollment_by_level[$lvl] = ($enrollment_by_level[$lvl] ?? 0) + $enr;
}

$total_sc_safe       = max(1, $total_schools_count);
$reporting_pct       = round(($active_schools_count / $total_sc_safe) * 100);
$attention_pct       = round(($attention_schools_count / $total_sc_safe) * 100);
$not_reporting_pct   = max(0, 100 - $reporting_pct - $attention_pct);
$water_pct           = round(($schools_with_water / $total_sc_safe) * 100);

// School Infrastructure Risk stats
$high_risk_count     = 0;
$total_at_risk_count = 0;
$resolved_count      = 0;
foreach ($atRiskData as $ar) {
    $stat = strtolower(trim($ar['status'] ?? ''));
    if ($stat === 'resolved') {
        $resolved_count++;
    } else {
        $total_at_risk_count++;
        $sev = strtolower(trim($ar['severity'] ?? ''));
        if ($sev === 'critical') {
            $high_risk_count++;
        }
    }
}
$risk_resolution_pct = ($total_at_risk_count + $resolved_count) > 0 
    ? round(($resolved_count / ($total_at_risk_count + $resolved_count)) * 100) 
    : 100;

// Grievance & Complaints stats
$total_complaints_count    = count($complaintsData);
$pending_complaints_count  = 0;
$resolved_complaints_count = 0;
foreach ($complaintsData as $c) {
    $c_st = strtolower(trim($c['status'] ?? ''));
    if ($c_st === 'resolved' || $c_st === 'closed') {
        $resolved_complaints_count++;
    }
    if ($c_st === 'pending' || ($c['unread_admin'] ?? '0') === '1') {
        $pending_complaints_count++;
    }
}
$complaint_resolution_pct = $total_complaints_count > 0 
    ? round(($resolved_complaints_count / $total_complaints_count) * 100) 
    : 100;

// Dynamic Recent Activities
$recent_activities = [];
$sorted_complaints = $complaintsData;
usort($sorted_complaints, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
foreach (array_slice($sorted_complaints, 0, 3) as $c) {
    $is_res = in_array(strtolower($c['status'] ?? ''), ['resolved', 'closed']);
    $recent_activities[] = [
        'icon_color' => $is_res ? '#15803D' : '#0F766E',
        'bg'         => $is_res ? 'bg-green-50' : 'bg-teal-50',
        'title'      => $is_res ? 'Complaint resolved' : 'New complaint submitted',
        'sub'        => 'Complaint #' . ($c['ticket_no'] ?? '') . ' — ' . ($c['subject'] ?? ''),
        'time'       => !empty($c['date']) ? date('d M Y', strtotime($c['date'])) : 'Recent',
        'link'       => BASE_URL . '/admin/complaints.php'
    ];
}
$sorted_risks = $atRiskData;
usort($sorted_risks, fn($a, $b) => strcmp($b['reported_date'] ?? '', $a['reported_date'] ?? ''));
foreach (array_slice($sorted_risks, 0, 3) as $r) {
    $is_res  = strtolower($r['status'] ?? '') === 'resolved';
    $is_crit = strtolower($r['severity'] ?? '') === 'critical';
    $recent_activities[] = [
        'icon_color' => $is_res ? '#15803D' : ($is_crit ? '#DC2626' : '#D97706'),
        'bg'         => $is_res ? 'bg-green-50' : ($is_crit ? 'bg-red-50' : 'bg-amber-50'),
        'title'      => $is_res ? 'Risk issue addressed' : 'Infrastructure risk flagged',
        'sub'        => ($r['risk_category'] ?? '') . ' — ' . ($r['school_name'] ?? ('SEMIS ' . ($r['semis_code'] ?? ''))),
        'time'       => !empty($r['reported_date']) ? date('d M Y', strtotime($r['reported_date'])) : 'Recent',
        'link'       => BASE_URL . '/admin/at-risk-schools.php'
    ];
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
  .kpi-card { transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s; }
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

      <!-- KPI Cards (6 Key Metric Cards with Direct Navigation) -->
      <section aria-label="Key Performance Indicators" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <!-- 1. Total Schools -->
        <a href="<?= BASE_URL ?>/admin/schools.php" class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3 hover:border-primary transition group block">
          <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
            <svg width="20" height="20" fill="none" stroke="#123B63" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide group-hover:text-primary transition-colors">Total Schools</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= $total_schools_count ?></div>
            <div class="text-xs text-success mt-1 flex items-center gap-1">
              <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <?= $active_schools_count ?> Active in District
            </div>
          </div>
        </a>

        <!-- 2. Total Students (Live Enrollment Sum) -->
        <a href="<?= BASE_URL ?>/admin/schools.php" class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3 hover:border-teal-600 transition group block">
          <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
            <svg width="20" height="20" fill="none" stroke="#0F766E" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide group-hover:text-secondary transition-colors">Total Students</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= number_format($total_enrollment) ?></div>
            <div class="text-xs text-secondary mt-1 flex items-center gap-1 font-medium">
              <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
              <?= number_format($total_boys) ?> Boys / <?= number_format($total_girls) ?> Girls
            </div>
          </div>
        </a>

        <!-- 3. Teachers & Staff (Live Staff Sum) -->
        <a href="<?= BASE_URL ?>/admin/schools.php" class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3 hover:border-indigo-600 transition group block">
          <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
            <svg width="20" height="20" fill="none" stroke="#4F46E5" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide group-hover:text-indigo-600 transition-colors">Teachers &amp; Staff</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= number_format($total_teachers + $total_non_teaching) ?></div>
            <div class="text-xs text-muted mt-1"><?= number_format($total_teachers) ?> teachers, <?= number_format($total_non_teaching) ?> non-teaching</div>
          </div>
        </a>

        <!-- 4. Critical Risk Schools -->
        <a href="<?= BASE_URL ?>/admin/at-risk-schools.php?severity=critical" class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3 hover:border-red-500 transition group block">
          <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
            <svg width="20" height="20" fill="none" stroke="#DC2626" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide group-hover:text-danger transition-colors">Critical Schools</div>
            <div class="text-3xl font-bold text-danger leading-tight mt-0.5"><?= $high_risk_count ?></div>
            <div class="text-xs text-danger mt-1 flex items-center gap-1 font-medium">
              <span class="w-1.5 h-1.5 rounded-full bg-red-500 <?= $high_risk_count > 0 ? 'animate-pulse' : '' ?>"></span>
              <?= $high_risk_count ?> Critical Hazard(s) Flagged
            </div>
          </div>
        </a>

        <!-- 5. Infrastructure At-Risk Registry -->
        <a href="<?= BASE_URL ?>/admin/at-risk-schools.php" class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3 hover:border-orange-500 transition group block">
          <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
            <svg width="20" height="20" fill="none" stroke="#EA580C" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide group-hover:text-warning transition-colors">At-Risk Registry</div>
            <div class="text-3xl font-bold text-orange-700 leading-tight mt-0.5"><?= $total_at_risk_count ?></div>
            <div class="text-xs text-warning mt-1 font-medium"><?= $resolved_count ?> issues resolved</div>
          </div>
        </a>

        <!-- 6. Grievances & Complaints -->
        <a href="<?= BASE_URL ?>/admin/complaints.php" class="kpi-card bg-surface border border-border rounded-lg p-4 flex gap-3 hover:border-teal-600 transition group block">
          <div class="w-10 h-10 rounded-lg bg-teal-50 text-secondary flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-muted font-medium uppercase tracking-wide group-hover:text-secondary transition-colors">School Complaints</div>
            <div class="text-3xl font-bold text-textMain leading-tight mt-0.5"><?= $total_complaints_count ?></div>
            <div class="text-xs text-danger mt-1 flex items-center gap-1 font-semibold">
              <span class="w-2 h-2 rounded-full bg-red-500 <?= $pending_complaints_count > 0 ? 'animate-pulse' : '' ?>"></span>
              <?= $pending_complaints_count ?> Pending Action
            </div>
          </div>
        </a>
      </section>

      <!-- Analytics Row -->
      <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6" aria-label="Analytics">
        <!-- District Enrollment by School Level Bar Chart (Live Data) -->
        <div class="lg:col-span-2 bg-surface border border-border rounded-lg p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-semibold text-textMain">District Enrollment by School Level</h2>
              <p class="text-xs text-muted mt-0.5">Total enrolled students per school level &mdash; SELD Live Registry</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/schools.php" class="text-xs text-primary font-medium hover:underline">View Schools</a>
          </div>
          <?php
          $level_order = ['Primary', 'Middle', 'Secondary', 'Higher Secondary', 'Other'];
          $level_colors = [
              'Primary'         => ['bar' => '#123B63', 'bg' => '#EFF6FF'],
              'Middle'          => ['bar' => '#0F766E', 'bg' => '#F0FDF9'],
              'Secondary'       => ['bar' => '#D97706', 'bg' => '#FFFBEB'],
              'Higher Secondary'=> ['bar' => '#7C3AED', 'bg' => '#FAF5FF'],
              'Other'           => ['bar' => '#64748B', 'bg' => '#F8FAFC'],
          ];
          $max_enroll = max(array_values($enrollment_by_level) ?: [1]);
          ?>
          <div class="space-y-3">
            <?php foreach ($level_order as $lvl):
              if (!isset($enrollment_by_level[$lvl]) || $enrollment_by_level[$lvl] === 0) continue;
              $cnt = $enrollment_by_level[$lvl];
              $pct = round(($cnt / max($max_enroll, 1)) * 100);
              $col = $level_colors[$lvl] ?? $level_colors['Other'];
              $schools_at_level = count(array_filter($schoolsData, fn($s) => ($s['level'] ?? '') === $lvl));
            ?>
            <div>
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="font-medium text-textMain flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:<?= $col['bar'] ?>"></span>
                  <?= e($lvl) ?> <span class="text-muted font-normal">(<?= $schools_at_level ?> schools)</span>
                </span>
                <span class="font-semibold text-textMain"><?= number_format($cnt) ?> students</span>
              </div>
              <div class="w-full rounded-full h-3" style="background:<?= $col['bg'] ?>">
                <div class="h-3 rounded-full transition-all duration-700" style="width:<?= $pct ?>%;background:<?= $col['bar'] ?>"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-4 pt-3 border-t border-border flex flex-wrap gap-6 text-xs text-muted">
            <div><span class="font-semibold text-textMain"><?= number_format($total_enrollment) ?></span> total enrolled students</div>
            <div><span class="font-semibold text-textMain"><?= $total_schools_count ?></span> registered schools</div>
            <div><span class="font-semibold text-textMain"><?= number_format($total_classrooms) ?></span> functional classrooms</div>
          </div>
        </div>

        <!-- Reporting Status Donut (Fully Dynamic from Live School Status) -->
        <div class="bg-surface border border-border rounded-lg p-5">
          <h2 class="text-sm font-semibold text-textMain mb-1">School Operational Status</h2>
          <p class="text-xs text-muted mb-4">Live status distribution &mdash; <?= date('d M Y') ?></p>
          <?php
          // SVG Circle perimeter = 2 * PI * 50 = 314.16
          $c_active = round(($active_schools_count / $total_sc_safe) * 314.16, 2);
          $c_att    = round(($attention_schools_count / $total_sc_safe) * 314.16, 2);
          $c_not    = max(0, round(314.16 - $c_active - $c_att, 2));

          $dash_not = $c_not . ' ' . round(314.16 - $c_not, 2);
          $dash_att = $c_att . ' ' . round(314.16 - $c_att, 2);
          $dash_act = $c_active . ' ' . round(314.16 - $c_active, 2);

          $offset_not = 0;
          $offset_att = -$c_not;
          $offset_act = -($c_not + $c_att);
          ?>
          <div class="flex items-center justify-center">
            <svg viewBox="0 0 160 160" class="w-36 h-36" aria-label="School reporting donut chart">
              <circle cx="80" cy="80" r="50" fill="none" stroke="#E2E8F0" stroke-width="20"/>
              <?php if ($not_reporting_schools_count > 0): ?>
              <circle cx="80" cy="80" r="50" fill="none" stroke="#DC2626" stroke-width="20" stroke-dasharray="<?= $dash_not ?>" stroke-dashoffset="<?= $offset_not ?>" transform="rotate(-90 80 80)"/>
              <?php endif; ?>
              <?php if ($attention_schools_count > 0): ?>
              <circle cx="80" cy="80" r="50" fill="none" stroke="#D97706" stroke-width="20" stroke-dasharray="<?= $dash_att ?>" stroke-dashoffset="<?= $offset_att ?>" transform="rotate(-90 80 80)"/>
              <?php endif; ?>
              <?php if ($active_schools_count > 0): ?>
              <circle cx="80" cy="80" r="50" fill="none" stroke="#15803D" stroke-width="20" stroke-dasharray="<?= $dash_act ?>" stroke-dashoffset="<?= $offset_act ?>" transform="rotate(-90 80 80)"/>
              <?php endif; ?>
              <text x="80" y="75" text-anchor="middle" fill="#172033" font-size="20" font-weight="700"><?= $reporting_pct ?>%</text>
              <text x="80" y="91" text-anchor="middle" fill="#64748B" font-size="9">Active / Good</text>
            </svg>
          </div>
          <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-success inline-block"></span>Active / Good</span>
              <span class="font-semibold text-textMain"><?= $reporting_pct ?>% <span class="text-muted font-normal">(<?= $active_schools_count ?>)</span></span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-warning inline-block"></span>Needs Attention</span>
              <span class="font-semibold text-textMain"><?= $attention_pct ?>% <span class="text-muted font-normal">(<?= $attention_schools_count ?>)</span></span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-danger inline-block"></span>Not Reporting</span>
              <span class="font-semibold text-textMain"><?= $not_reporting_pct ?>% <span class="text-muted font-normal">(<?= $not_reporting_schools_count ?>)</span></span>
            </div>
          </div>
        </div>
      </section>

      <!-- Performance + Activity -->
      <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6" aria-label="Performance and Activity">
        <!-- District Performance (Live Calculated Indicators) -->
        <div class="bg-surface border border-border rounded-lg p-5">
          <h2 class="text-sm font-semibold text-textMain mb-4">District Performance Indicators</h2>
          <div class="space-y-4">
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">Active Operational Rate</span>
                <span class="font-semibold text-success"><?= $reporting_pct ?>% <span class="text-muted font-normal">/ 100% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-success h-2 rounded-full" style="width:<?= $reporting_pct ?>%"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">Safe Water Facility Coverage</span>
                <span class="font-semibold text-teal-700"><?= $water_pct ?>% <span class="text-muted font-normal">/ 100% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-secondary h-2 rounded-full" style="width:<?= $water_pct ?>%"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">Grievance Resolution</span>
                <span class="font-semibold text-success"><?= $complaint_resolution_pct ?>% <span class="text-muted font-normal">/ 90% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-success h-2 rounded-full" style="width:<?= $complaint_resolution_pct ?>%"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs mb-1">
                <span class="font-medium text-textMain">Infrastructure Risk Resolution</span>
                <span class="font-semibold text-warning"><?= $risk_resolution_pct ?>% <span class="text-muted font-normal">/ 100% target</span></span>
              </div>
              <div class="w-full bg-border rounded-full h-2"><div class="progress-bar bg-warning h-2 rounded-full" style="width:<?= $risk_resolution_pct ?>%"></div></div>
            </div>
          </div>
        </div>

        <!-- Recent Activity (Live Dynamic Logs from Grievances & Risk Registry) -->
        <div class="lg:col-span-2 bg-surface border border-border rounded-lg p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-textMain">Recent Activity &amp; Grievance Feed</h2>
            <a href="<?= BASE_URL ?>/admin/complaints.php" class="text-xs text-primary font-medium hover:underline">View all</a>
          </div>
          <div class="space-y-0">
            <?php if (empty($recent_activities)): ?>
            <div class="py-8 text-center text-xs text-muted">No recent activity logged.</div>
            <?php else: ?>
              <?php foreach ($recent_activities as $i => $a):
                $border = ($i < count($recent_activities) - 1) ? 'border-b border-border' : '';
              ?>
              <a href="<?= e($a['link']) ?>" class="flex gap-3 py-3 <?= $border ?> hover:bg-slate-50 transition px-2 rounded -mx-2 block">
                <div class="flex-shrink-0 w-8 h-8 rounded-full <?= $a['bg'] ?> flex items-center justify-center mt-0.5">
                  <svg width="14" height="14" fill="none" stroke="<?= $a['icon_color'] ?>" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-textMain"><?= e($a['title']) ?></div>
                  <div class="text-xs text-muted mt-0.5 truncate"><?= e($a['sub']) ?></div>
                </div>
                <div class="text-xs text-muted flex-shrink-0"><?= e($a['time']) ?></div>
              </a>
              <?php endforeach; ?>
            <?php endif; ?>
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
                <td class="px-4 py-3"><span class="status-badge <?= e($s['status_badge'] ?? 'badge-good') ?>"><?= e($s['status'] ?? 'Active') ?></span></td>
                <td class="px-4 py-3">
                  <div class="flex gap-1.5">
                    <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($s['semis_code'] ?? '') ?>" class="btn-secondary px-2.5 py-1 rounded text-xs">Profile</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted">
          <span>Showing <?= min(7, $total_schools_count) ?> of <?= $total_schools_count ?> registered schools</span>
          <a href="<?= BASE_URL ?>/admin/schools.php" class="text-xs text-primary font-medium hover:underline flex items-center gap-1">
            <span>View Full Directory (<?= $total_schools_count ?> schools)</span>
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
        </div>
      </section>

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
