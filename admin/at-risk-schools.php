<?php
/**
 * admin/at-risk-schools.php — School Infrastructure Risk Registry
 *
 * Monitors and categorizes schools based on physical infrastructure risks:
 * dangerous buildings, missing boundary walls, WASH deficiencies, etc.
 * Powered by ExcelDB backend with Excel CSV export.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'at-risk-schools';
$page_title  = 'Schools At-Risk — ' . APP_NAME;

// ─── Export to Excel Action ──────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    ExcelDB::exportCsv('school_risks');
    exit;
}

// ─── Handle Risk Record Update ────────────────────────────────────────────────
$alert_message = '';
$alert_type    = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_risk'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $risk_id = trim($_POST['risk_id'] ?? '');
        $status  = trim($_POST['status'] ?? 'Pending');
        $notes   = trim($_POST['notes'] ?? '');

        $updated = ExcelDB::update('school_risks', 'id', $risk_id, [
            'status'         => $status,
            'notes'          => $notes,
            'last_inspected' => date('Y-m-d'),
        ]);

        if ($updated) {
            header('Location: ' . BASE_URL . '/admin/at-risk-schools.php?msg=updated');
            exit;
        } else {
            $alert_message = 'Unable to update risk record.';
            $alert_type    = 'danger';
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $alert_message = 'School risk record updated successfully!';
    $alert_type    = 'success';
}

// ─── Handle Add New Risk Record ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_risk'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $semis     = trim($_POST['semis_code'] ?? '');
        $school_nm = trim($_POST['school_name'] ?? '');
        $taluka    = trim($_POST['taluka'] ?? '');
        $category  = trim($_POST['risk_category'] ?? '');
        $severity  = trim($_POST['severity'] ?? 'Medium');
        $details   = trim($_POST['details'] ?? '');

        if ($semis && $school_nm && $category) {
            $allRisks = ExcelDB::all('school_risks');
            $newId    = count($allRisks) + 1;
            ExcelDB::insert('school_risks', [
                'id'             => $newId,
                'semis_code'     => $semis,
                'school_name'    => $school_nm,
                'taluka'         => $taluka,
                'risk_category'  => $category,
                'severity'       => $severity,
                'details'        => $details,
                'reported_date'  => date('Y-m-d'),
                'last_inspected' => date('Y-m-d'),
                'status'         => 'Pending',
                'notes'          => '',
            ]);
            header('Location: ' . BASE_URL . '/admin/at-risk-schools.php?msg=added');
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'added') {
    $alert_message = 'New school risk record added successfully!';
    $alert_type    = 'success';
}

// ─── Handle Delete Risk Record ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_risk'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $del_id = trim($_POST['risk_id'] ?? '');
        if ($del_id) {
            ExcelDB::delete('school_risks', 'id', $del_id);
            header('Location: ' . BASE_URL . '/admin/at-risk-schools.php?msg=deleted');
            exit;
        }
    }
}
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $alert_message = 'Risk record removed from registry.';
    $alert_type    = 'success';
}

// ─── Load Data ────────────────────────────────────────────────────────────────
$risk_records      = ExcelDB::all('school_risks');
$available_talukas = ExcelDB::getTalukas();

// ─── KPI Counts ──────────────────────────────────────────────────────────────
$critical_count = 0;
$high_count     = 0;
$medium_count   = 0;
$resolved_count = 0;

foreach ($risk_records as $r) {
    $sev = strtolower($r['severity'] ?? '');
    $sta = strtolower($r['status'] ?? '');
    if ($sta === 'resolved') {
        $resolved_count++;
    } elseif ($sev === 'critical') {
        $critical_count++;
    } elseif ($sev === 'high') {
        $high_count++;
    } elseif ($sev === 'medium') {
        $medium_count++;
    }
}

// ─── Category Breakdown ───────────────────────────────────────────────────────
$categories_map = [];
foreach ($risk_records as $r) {
    $cat = $r['risk_category'] ?? 'Unknown';
    $categories_map[$cat] = ($categories_map[$cat] ?? 0) + 1;
}
arsort($categories_map);

// Predefined risk categories (SELD/SEMIS/PSSF-aligned)
$risk_categories = [
    'Dangerous Building Structure'   => 'Building structurally unsafe, risk of collapse',
    'No Boundary Wall'               => 'School has no protective boundary wall',
    'Damaged / Incomplete Wall'      => 'Boundary wall partially damaged or incomplete',
    'No Drinking Water'              => 'No clean/safe drinking water available',
    'Non-functional Toilets'         => 'Toilets absent, broken, or unhygienic',
    'No Electricity'                 => 'School has no power supply of any kind',
    'Damaged Classrooms / Roof'      => 'Classrooms or roofs in dangerous disrepair',
    'Flood / Disaster Prone Area'    => 'School located in high flood or disaster zone',
    'No Furniture / Equipment'       => 'Acute shortage of desks, chairs, and furniture',
    'No Proper Drainage / Sewerage'  => 'Sewerage or drainage system missing or broken',
    'Overcrowded Classrooms'         => 'Extreme overcrowding, inadequate learning space',
    'Security Threat Area'           => 'School in area with significant security concerns',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<meta name="description" content="Monitor and track at-risk schools by infrastructure category in <?= APP_DISTRICT ?>"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
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
        danger: '#DC2626'
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif']
      }
    }
  }
}
</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s,color .15s;}.sidebar-link:hover{background:rgba(255,255,255,.08);}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E;}
.sidebar-group-title{font-size:10px;letter-spacing:.1em;text-transform:uppercase;}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.btn-excel{background:#107C41;color:#fff;transition:background .15s;}.btn-excel:hover{background:#0b5c30;}
.btn-danger{background:#DC2626;color:#fff;transition:background .15s;}.btn-danger:hover{background:#b91c1c;}
.severity-badge{font-size:11px;font-weight:600;padding:2px 9px;border-radius:9999px;display:inline-block;}
.badge-critical{background:#FEE2E2;color:#7F1D1D;}
.badge-high{background:#FFEDD5;color:#9A3412;}
.badge-medium{background:#FEF3C7;color:#92400E;}
.badge-low{background:#DCFCE7;color:#166534;}
.status-tag{font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;display:inline-block;}
.tag-pending{background:#FEF3C7;color:#92400E;}
.tag-progress{background:#DBEAFE;color:#1E40AF;}
.tag-resolved{background:#DCFCE7;color:#166534;}
.tag-escalated{background:#FEE2E2;color:#7F1D1D;}
.table-row:hover{background:#F8FAFC;}
.category-pill{font-size:11px;font-weight:500;padding:2px 8px;border-radius:4px;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;display:inline-block;}
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
  <!-- Breadcrumb -->
  <nav class="text-xs text-muted mb-4 flex items-center gap-1.5" aria-label="Breadcrumb">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="hover:text-primary">Dashboard</a><span>/</span>
    <a href="<?= BASE_URL ?>/admin/schools.php" class="hover:text-primary">Schools</a><span>/</span>
    <span class="text-textMain font-medium">At-Risk Schools</span>
  </nav>

  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
      <div class="flex items-center gap-2">
        <h1 class="text-xl font-bold text-textMain">Schools At-Risk Registry</h1>
        <span class="text-[11px] bg-red-100 text-red-800 font-semibold px-2 py-0.5 rounded-full flex items-center gap-1">
          <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
          Monitoring Active
        </span>
      </div>
      <p class="text-muted text-sm mt-0.5">Infrastructure risk registry — schools requiring immediate attention in <?= e(APP_DISTRICT) ?></p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a href="?action=export_excel" class="btn-excel px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Report (CSV)
      </a>
      <button onclick="openAddModal()" id="btn-flag-school" class="btn-primary px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Flag School At-Risk
      </button>
    </div>
  </div>

  <?php if (!empty($alert_message)): ?>
  <div class="mb-5 p-3.5 rounded-lg text-xs <?= $alert_type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?> flex items-center justify-between">
    <div class="flex items-center gap-2">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      <span><?= e($alert_message) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">&times;</button>
  </div>
  <?php endif; ?>

  <!-- ─── KPI Summary Cards ───────────────────────────────────────────── -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div id="kpi-card-critical" onclick="filterByKpi('severity', 'critical')" class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center gap-3 cursor-pointer transition-all duration-150 hover:shadow-md hover:-translate-y-0.5 select-none" title="Click to filter by Critical risk">
      <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
        <svg width="18" height="18" fill="none" stroke="#991B1B" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <div class="text-[10px] text-muted uppercase tracking-wide font-semibold">Critical</div>
        <div class="text-2xl font-bold" style="color:#7F1D1D"><?= $critical_count ?></div>
        <div class="text-[10px] text-muted">Immediate action</div>
      </div>
    </div>
    <div id="kpi-card-high" onclick="filterByKpi('severity', 'high')" class="bg-orange-50 border border-orange-200 rounded-lg p-4 flex items-center gap-3 cursor-pointer transition-all duration-150 hover:shadow-md hover:-translate-y-0.5 select-none" title="Click to filter by High risk">
      <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
        <svg width="18" height="18" fill="none" stroke="#C2410C" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      </div>
      <div>
        <div class="text-[10px] text-muted uppercase tracking-wide font-semibold">High Risk</div>
        <div class="text-2xl font-bold text-orange-700"><?= $high_count ?></div>
        <div class="text-[10px] text-muted">Priority repair</div>
      </div>
    </div>
    <div id="kpi-card-medium" onclick="filterByKpi('severity', 'medium')" class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center gap-3 cursor-pointer transition-all duration-150 hover:shadow-md hover:-translate-y-0.5 select-none" title="Click to filter by Medium risk">
      <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
        <svg width="18" height="18" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div>
        <div class="text-[10px] text-muted uppercase tracking-wide font-semibold">Medium</div>
        <div class="text-2xl font-bold text-warning"><?= $medium_count ?></div>
        <div class="text-[10px] text-muted">Scheduled repair</div>
      </div>
    </div>
    <div id="kpi-card-resolved" onclick="filterByKpi('status', 'resolved')" class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center gap-3 cursor-pointer transition-all duration-150 hover:shadow-md hover:-translate-y-0.5 select-none" title="Click to filter by Resolved records">
      <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
        <svg width="18" height="18" fill="none" stroke="#15803D" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      </div>
      <div>
        <div class="text-[10px] text-muted uppercase tracking-wide font-semibold">Resolved</div>
        <div class="text-2xl font-bold text-success"><?= $resolved_count ?></div>
        <div class="text-[10px] text-muted">Issue addressed</div>
      </div>
    </div>
  </div>

  <!-- ─── Category Breakdown ─────────────────────────────────────────── -->
  <?php if (!empty($categories_map)): ?>
  <div class="bg-surface border border-border rounded-lg p-4 mb-5">
    <div class="text-xs font-semibold text-textMain mb-3 flex items-center gap-2">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Risk Category Breakdown <span class="text-muted font-normal text-[11px]">(Click any category to filter table)</span>
    </div>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($categories_map as $cat => $cnt): ?>
      <button type="button" onclick="filterByCategory('<?= e(addslashes(strtolower($cat))) ?>')" class="category-pill flex items-center gap-1.5 cursor-pointer hover:bg-blue-100 hover:border-blue-400 transition-colors" title="Click to filter by <?= e($cat) ?>">
        <?= e($cat) ?>
        <span class="bg-primary/10 text-primary font-bold text-[10px] px-1.5 rounded-full"><?= $cnt ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ─── Critical Alert Banner ──────────────────────────────────────── -->
  <?php if ($critical_count > 0): ?>
  <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-5 flex items-start gap-3">
    <svg width="16" height="16" fill="none" stroke="#DC2626" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
      <div class="text-sm font-semibold text-danger">Immediate Action Required — <?= $critical_count ?> Critical School(s)</div>
      <div class="text-xs text-muted mt-0.5">These schools are flagged <strong>Critical</strong>. Escalate to DEO and Provincial Works Department immediately for structural inspection and emergency repair orders.</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ─── Main Table ─────────────────────────────────────────────────── -->
  <div class="bg-surface border border-border rounded-lg shadow-sm">
    <div class="px-5 py-3 border-b border-border flex flex-wrap gap-2 items-center">
      <input type="text" id="risk-search" oninput="filterRisks()" placeholder="Search school or SEMIS code…" class="text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary flex-1 min-w-36"/>
      <select id="risk-severity-filter" onchange="filterRisks()" class="text-xs border border-border rounded px-3 py-1.5 bg-background">
        <option value="">All Severities</option>
        <option value="critical">Critical</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
      <select id="risk-category-filter" onchange="filterRisks()" class="text-xs border border-border rounded px-3 py-1.5 bg-background">
        <option value="">All Categories</option>
        <?php foreach (array_keys($risk_categories) as $cat): ?>
        <option value="<?= e(strtolower($cat)) ?>"><?= e($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="risk-taluka-filter" onchange="filterRisks()" class="text-xs border border-border rounded px-3 py-1.5 bg-background">
        <option value="">All Talukas</option>
        <?php foreach ($available_talukas as $t): ?>
        <option value="<?= e(strtolower($t)) ?>"><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="risk-status-filter" onchange="filterRisks()" class="text-xs border border-border rounded px-3 py-1.5 bg-background">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="in progress">In Progress</option>
        <option value="resolved">Resolved</option>
        <option value="escalated">Escalated</option>
      </select>
      <button onclick="resetRisks()" class="text-xs text-muted hover:text-primary px-2 py-1">Reset</button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-xs" aria-label="At-risk schools infrastructure table">
        <thead>
          <tr class="border-b border-border bg-background text-muted uppercase tracking-wide text-left">
            <th class="px-5 py-3 font-semibold">School</th>
            <th class="px-4 py-3 font-semibold">Taluka</th>
            <th class="px-4 py-3 font-semibold">Risk Category</th>
            <th class="px-4 py-3 font-semibold">Severity</th>
            <th class="px-4 py-3 font-semibold">Reported</th>
            <th class="px-4 py-3 font-semibold">Last Inspected</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="risk-tbody" class="divide-y divide-border text-textMain">
          <?php foreach ($risk_records as $r):
            $sev      = strtolower($r['severity'] ?? 'medium');
            $stat     = strtolower($r['status'] ?? 'pending');
            $sevBadge = match($sev) {
                'critical' => 'badge-critical',
                'high'     => 'badge-high',
                'low'      => 'badge-low',
                default    => 'badge-medium',
            };
            $statTag  = match($stat) {
                'resolved'    => 'tag-resolved',
                'in progress' => 'tag-progress',
                'escalated'   => 'tag-escalated',
                default       => 'tag-pending',
            };
          ?>
          <tr class="table-row"
              data-severity="<?= e($sev) ?>"
              data-category="<?= e(strtolower($r['risk_category'] ?? '')) ?>"
              data-taluka="<?= e(strtolower($r['taluka'] ?? '')) ?>"
              data-status="<?= e($stat) ?>">
            <td class="px-5 py-3">
              <div class="font-medium"><?= e($r['school_name']) ?></div>
              <div class="text-muted font-mono text-[11px]"><?= e($r['semis_code'] ?? '') ?></div>
            </td>
            <td class="px-4 py-3 text-muted"><?= e($r['taluka'] ?? '—') ?></td>
            <td class="px-4 py-3"><span class="category-pill"><?= e($r['risk_category'] ?? '—') ?></span></td>
            <td class="px-4 py-3"><span class="severity-badge <?= $sevBadge ?>"><?= e(ucfirst($r['severity'] ?? '')) ?></span></td>
            <td class="px-4 py-3 text-muted"><?= e($r['reported_date'] ?? '—') ?></td>
            <td class="px-4 py-3 text-muted"><?= e($r['last_inspected'] ?? '—') ?></td>
            <td class="px-4 py-3"><span class="status-tag <?= $statTag ?>"><?= e(ucfirst($r['status'] ?? 'Pending')) ?></span></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1.5">
                <button onclick="openUpdateModal('<?= e($r['id']) ?>','<?= e(addslashes($r['school_name'])) ?>','<?= e($r['status']) ?>','<?= e(addslashes($r['notes'] ?? '')) ?>')"
                        class="btn-primary px-2.5 py-1 rounded text-xs">Update</button>
                <button onclick="confirmDeleteRisk('<?= e($r['id']) ?>','<?= e(addslashes($r['school_name'])) ?>')"
                        class="btn-danger px-2.5 py-1 rounded text-xs">Remove</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($risk_records)): ?>
          <tr><td colspan="8" class="px-5 py-10 text-center text-muted">
            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="mx-auto mb-2 opacity-30"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            No at-risk schools flagged yet. Click <strong>Flag School At-Risk</strong> to add a record.
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="px-5 py-3 border-t border-border flex items-center justify-between text-xs text-muted">
      <span>Total <strong><?= count($risk_records) ?></strong> school risk records in registry</span>
      <span id="visible-count"></span>
    </div>
  </div>

  <!-- ─── Risk Categories Reference Card ──────────────────────────────── -->
  <div class="mt-6 bg-surface border border-border rounded-lg p-5">
    <div class="text-xs font-semibold text-textMain mb-3 flex items-center gap-2">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Infrastructure Risk Categories — SELD / SEMIS / PSSF Reference
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
      <?php foreach ($risk_categories as $cat => $desc): ?>
      <div class="flex items-start gap-2 p-2.5 rounded bg-background border border-border">
        <span class="w-1.5 h-1.5 rounded-full bg-primary mt-1.5 flex-shrink-0"></span>
        <div>
          <div class="text-[11px] font-semibold text-textMain"><?= e($cat) ?></div>
          <div class="text-[10px] text-muted"><?= e($desc) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>

<!-- ─── Update Modal ────────────────────────────────────────────────────────── -->
<div id="update-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-md w-full p-6 shadow-xl">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <h3 class="text-sm font-bold text-textMain" id="update-modal-title">Update Risk Record</h3>
      <button onclick="closeUpdateModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>
    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_risk" value="1"/>
      <input type="hidden" name="risk_id" id="update-risk-id" value=""/>
      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Intervention Status</label>
        <select name="status" id="update-status" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
          <option value="Pending">Pending (No action taken yet)</option>
          <option value="In Progress">In Progress (Repair / inspection underway)</option>
          <option value="Resolved">Resolved (Issue fully addressed)</option>
          <option value="Escalated">Escalated (Referred to higher authority)</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Inspection Notes</label>
        <textarea name="notes" id="update-notes" rows="3" class="w-full text-xs border border-border rounded p-2.5 bg-background focus:outline-none focus:border-primary" placeholder="Contractor details, expected completion date, inspection findings…"></textarea>
      </div>
      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeUpdateModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Save to Registry</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── Add New Risk Modal ─────────────────────────────────────────────────── -->
<div id="add-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-lg w-full p-6 shadow-xl max-h-[92vh] overflow-y-auto">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <h3 class="text-sm font-bold text-textMain">Flag School At-Risk</h3>
      <button onclick="closeAddModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>
    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="add_risk" value="1"/>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">SEMIS Code *</label>
          <input type="text" name="semis_code" required placeholder="e.g. 403010001" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Taluka</label>
          <select name="taluka" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option value="">Select Taluka</option>
            <?php foreach ($available_talukas as $t): ?>
            <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">School Name *</label>
        <input type="text" name="school_name" required placeholder="Full official school name" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Risk Category *</label>
          <select name="risk_category" required class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option value="">Select Category</option>
            <?php foreach (array_keys($risk_categories) as $cat): ?>
            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Severity Level *</label>
          <select name="severity" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option value="Critical">🔴 Critical — Immediate danger</option>
            <option value="High">🟠 High — Priority repair needed</option>
            <option value="Medium" selected>🟡 Medium — Scheduled attention</option>
            <option value="Low">🟢 Low — Minor issue</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Issue Description</label>
        <textarea name="details" rows="3" class="w-full text-xs border border-border rounded p-2.5 bg-background focus:outline-none focus:border-primary" placeholder="Describe the specific risk condition observed (e.g., front wall collapsed, roof cracks visible, no functional toilets)…"></textarea>
      </div>
      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeAddModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Add to Registry</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── Delete Form (hidden) ──────────────────────────────────────────────── -->
<form id="delete-risk-form" method="POST" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
  <input type="hidden" name="delete_risk" value="1"/>
  <input type="hidden" name="risk_id" id="delete-risk-id" value=""/>
</form>

<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});

function filterRisks(){
  const s   = (document.getElementById('risk-search')?.value || '').toLowerCase().trim();
  const sev = (document.getElementById('risk-severity-filter')?.value || '').toLowerCase().trim();
  const cat = (document.getElementById('risk-category-filter')?.value || '').toLowerCase().trim();
  const tal = (document.getElementById('risk-taluka-filter')?.value || '').toLowerCase().trim();
  const sta = (document.getElementById('risk-status-filter')?.value || '').toLowerCase().trim();

  let visible = 0;
  document.querySelectorAll('#risk-tbody tr[data-severity]').forEach(r => {
    const tx = r.textContent.toLowerCase();
    const rSev = (r.getAttribute('data-severity') || '').toLowerCase();
    const rCat = (r.getAttribute('data-category') || '').toLowerCase();
    const rTal = (r.getAttribute('data-taluka') || '').toLowerCase();
    const rSta = (r.getAttribute('data-status') || '').toLowerCase();

    const show = (s === '' || tx.includes(s))
      && (sev === '' || rSev === sev)
      && (cat === '' || rCat.includes(cat))
      && (tal === '' || rTal.includes(tal))
      && (sta === '' || rSta.includes(sta));

    r.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  const vc = document.getElementById('visible-count');
  if (vc) vc.textContent = visible > 0 ? `${visible} shown` : '0 shown';

  // ── Active KPI Card Highlights ──
  const cardCrit = document.getElementById('kpi-card-critical');
  const cardHigh = document.getElementById('kpi-card-high');
  const cardMed  = document.getElementById('kpi-card-medium');
  const cardRes  = document.getElementById('kpi-card-resolved');

  const isCritActive = (sev === 'critical' && sta === '');
  const isHighActive = (sev === 'high' && sta === '');
  const isMedActive  = (sev === 'medium' && sta === '');
  const isResActive  = (sta === 'resolved');

  if (cardCrit) {
    cardCrit.classList.toggle('ring-2', isCritActive);
    cardCrit.classList.toggle('ring-red-600', isCritActive);
    cardCrit.classList.toggle('shadow-md', isCritActive);
    cardCrit.classList.toggle('bg-red-100', isCritActive);
  }
  if (cardHigh) {
    cardHigh.classList.toggle('ring-2', isHighActive);
    cardHigh.classList.toggle('ring-orange-600', isHighActive);
    cardHigh.classList.toggle('shadow-md', isHighActive);
    cardHigh.classList.toggle('bg-orange-100', isHighActive);
  }
  if (cardMed) {
    cardMed.classList.toggle('ring-2', isMedActive);
    cardMed.classList.toggle('ring-amber-500', isMedActive);
    cardMed.classList.toggle('shadow-md', isMedActive);
    cardMed.classList.toggle('bg-amber-100', isMedActive);
  }
  if (cardRes) {
    cardRes.classList.toggle('ring-2', isResActive);
    cardRes.classList.toggle('ring-emerald-600', isResActive);
    cardRes.classList.toggle('shadow-md', isResActive);
    cardRes.classList.toggle('bg-emerald-100', isResActive);
  }
}

function filterByKpi(type, val) {
  const sevEl = document.getElementById('risk-severity-filter');
  const staEl = document.getElementById('risk-status-filter');

  if (type === 'severity') {
    if (sevEl.value === val && staEl.value === '') {
      sevEl.value = ''; // toggle off
    } else {
      sevEl.value = val;
      staEl.value = ''; // clear status
    }
  } else if (type === 'status') {
    if (staEl.value === val && sevEl.value === '') {
      staEl.value = ''; // toggle off
    } else {
      staEl.value = val;
      sevEl.value = ''; // clear severity filter to show all resolved
    }
  }
  filterRisks();
}

function filterByCategory(cat) {
  const catEl = document.getElementById('risk-category-filter');
  if (catEl.value === cat) {
    catEl.value = '';
  } else {
    catEl.value = cat;
  }
  filterRisks();
}

function resetRisks(){
  ['risk-search','risk-severity-filter','risk-category-filter','risk-taluka-filter','risk-status-filter']
    .forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  filterRisks();
}
function openUpdateModal(id,name,status,notes){
  document.getElementById('update-risk-id').value=id;
  document.getElementById('update-modal-title').textContent='Update: '+name;
  document.getElementById('update-status').value=status||'Pending';
  document.getElementById('update-notes').value=notes||'';
  document.getElementById('update-modal').classList.remove('hidden');
}
function closeUpdateModal(){document.getElementById('update-modal').classList.add('hidden');}
function openAddModal(){document.getElementById('add-modal').classList.remove('hidden');}
function closeAddModal(){document.getElementById('add-modal').classList.add('hidden');}
function confirmDeleteRisk(id,name){
  customConfirm('Remove "'+name+'" from the risk registry?',function(){
    document.getElementById('delete-risk-id').value=id;
    document.getElementById('delete-risk-form').submit();
  },{title:'Remove Risk Record',okText:'Yes, Remove',isDanger:true});
}
['update-modal','add-modal'].forEach(id=>{
  document.getElementById(id).addEventListener('click',function(e){if(e.target===this)this.classList.add('hidden');});
});

// Auto-filter by URL parameter (e.g. ?severity=critical)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('severity')) {
  const sevEl = document.getElementById('risk-severity-filter');
  if (sevEl) sevEl.value = urlParams.get('severity').toLowerCase();
}

filterRisks();
</script>
</body>
</html>
