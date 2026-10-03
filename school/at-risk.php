<?php
/**
 * school/at-risk.php — School Facility Risk Monitor
 *
 * Allows Head Master/Mistress to view infrastructure risk flags
 * reported for their school (from the district school_risks registry),
 * and submit facility issue reports to the District RSU.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'at-risk';
$page_title  = 'School Facility Risks — ' . e($school_name);

$notification      = '';
$notification_type = 'success';

// --- Handle Submit New Facility Issue ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_issue'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification      = 'Security validation failed. Please try again.';
        $notification_type = 'danger';
    } else {
        $category = trim($_POST['risk_category'] ?? '');
        $severity = trim($_POST['severity'] ?? 'Medium');
        $details  = trim($_POST['details'] ?? '');

        if ($category && $details) {
            $allRisks = ExcelDB::all('school_risks');
            $newId    = count($allRisks) + 1;
            ExcelDB::insert('school_risks', [
                'id'             => $newId,
                'semis_code'     => $school_semis,
                'school_name'    => $school_name,
                'taluka'         => $school_taluka,
                'risk_category'  => $category,
                'severity'       => $severity,
                'details'        => $details,
                'reported_date'  => date('Y-m-d'),
                'last_inspected' => date('Y-m-d'),
                'status'         => 'Pending',
                'notes'          => 'Reported by Head Master via School Portal',
            ]);
            $notification      = 'Facility issue reported to District RSU successfully.';
            $notification_type = 'success';
        } else {
            $notification      = 'Please fill in all required fields.';
            $notification_type = 'danger';
        }
    }
}

// --- Load This School's Risk Records -----------------------------------------
$all_risks    = ExcelDB::all('school_risks');
$school_risks = array_filter($all_risks, function($r) use ($school_semis) {
    return ($r['semis_code'] ?? '') === $school_semis;
});
$school_risks = array_values($school_risks);

// KPI counts
$critical_count = 0;
$open_count     = 0;
$resolved_count = 0;
foreach ($school_risks as $r) {
    $stat = strtolower($r['status'] ?? '');
    $sev  = strtolower($r['severity'] ?? '');
    if ($stat === 'resolved') {
        $resolved_count++;
    } else {
        $open_count++;
        if ($sev === 'critical') $critical_count++;
    }
}

// Risk categories (same as admin)
$risk_categories = [
    'Dangerous Building Structure',
    'No Boundary Wall',
    'Damaged / Incomplete Wall',
    'No Drinking Water',
    'Non-functional Toilets',
    'No Electricity',
    'Damaged Classrooms / Roof',
    'Flood / Disaster Prone Area',
    'No Furniture / Equipment',
    'No Proper Drainage / Sewerage',
    'Overcrowded Classrooms',
    'Security Threat Area',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<meta name="description" content="School infrastructure risk monitor for <?= e($school_name) ?>"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{govNavy:'#0f2744',govNavyDark:'#0a1e35',primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s,color .15s;}
.sidebar-link:hover{background:rgba(255,255,255,.1);}
.sidebar-link.active{background:rgba(52,211,153,.18);border-left:4px solid #34d399;}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.btn-danger-outline{border:1px solid #DC2626;color:#DC2626;transition:all .15s;}.btn-danger-outline:hover{background:#DC2626;color:#fff;}
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
.category-pill{font-size:11px;font-weight:500;padding:2px 8px;border-radius:4px;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;display:inline-block;}
.table-row:hover{background:#F8FAFC;}
#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1);}#overlay{transition:opacity .25s;}
</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">
<div class="bg-govNavyDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span class="font-medium tracking-wide"><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
<?php require_once __DIR__ . '/includes/header.php'; ?>

<main class="flex-1 overflow-y-auto p-4 md:p-6">
  <!-- Breadcrumb -->
  <nav class="text-xs text-muted mb-4 flex items-center gap-1.5" aria-label="Breadcrumb">
    <a href="<?= BASE_URL ?>/school/dashboard.php" class="hover:text-primary">Dashboard</a><span>/</span>
    <span class="text-textMain font-medium">School Facility Risks</span>
  </nav>

  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
      <div class="flex items-center gap-2">
        <h1 class="text-xl font-bold text-textMain">School Facility Risks</h1>
        <?php if ($open_count > 0): ?>
        <span class="text-[11px] bg-red-100 text-red-800 font-semibold px-2 py-0.5 rounded-full flex items-center gap-1">
          <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
          <?= $open_count ?> Open
        </span>
        <?php else: ?>
        <span class="text-[11px] bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded-full flex items-center gap-1">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          All Clear
        </span>
        <?php endif; ?>
      </div>
      <p class="text-muted text-sm mt-0.5">Infrastructure risk flags for <?= e($school_name) ?> — SEMIS: <?= e($school_semis) ?></p>
    </div>
    <button onclick="openReportModal()" class="btn-primary px-4 py-2 rounded text-xs font-medium flex items-center gap-1.5 self-start">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Report Facility Issue
    </button>
  </div>

  <?php if (!empty($notification)): ?>
  <div class="mb-5 p-3.5 rounded-lg text-xs <?= $notification_type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?> flex items-center justify-between">
    <div class="flex items-center gap-2">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      <span><?= e($notification) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">&times;</button>
  </div>
  <?php endif; ?>

  <!-- --- KPI Cards -------------------------------------------------------- -->
  <div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
        <svg width="16" height="16" fill="none" stroke="#991B1B" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <div class="text-[10px] text-muted uppercase tracking-wide font-semibold">Critical</div>
        <div class="text-2xl font-bold" style="color:#7F1D1D"><?= $critical_count ?></div>
      </div>
    </div>
    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
        <svg width="16" height="16" fill="none" stroke="#C2410C" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div>
        <div class="text-[10px] text-muted uppercase tracking-wide font-semibold">Open Issues</div>
        <div class="text-2xl font-bold text-orange-700"><?= $open_count ?></div>
      </div>
    </div>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
        <svg width="16" height="16" fill="none" stroke="#15803D" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      </div>
      <div>
        <div class="text-[10px] text-muted uppercase tracking-wide font-semibold">Resolved</div>
        <div class="text-2xl font-bold text-success"><?= $resolved_count ?></div>
      </div>
    </div>
  </div>

  <!-- --- Critical Banner ------------------------------------------------- -->
  <?php if ($critical_count > 0): ?>
  <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-5 flex items-start gap-3">
    <svg width="16" height="16" fill="none" stroke="#DC2626" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    <div>
      <div class="text-sm font-semibold text-danger"><?= $critical_count ?> Critical Issue(s) Require Immediate Action</div>
      <div class="text-xs text-muted mt-0.5">These issues have been flagged <strong>Critical</strong> by the District RSU. Ensure immediate coordination with the District Education Officer and Works Department.</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- --- Risk Records Table ---------------------------------------------- -->
  <div class="bg-surface border border-border rounded-lg shadow-sm">
    <div class="px-5 py-3 border-b border-border flex items-center justify-between">
      <div class="text-xs font-semibold text-textMain">Flagged Infrastructure Issues</div>
      <span class="text-xs text-muted"><?= count($school_risks) ?> total records</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs" aria-label="School facility risk records">
        <thead>
          <tr class="border-b border-border bg-background text-muted uppercase tracking-wide text-left">
            <th class="px-5 py-3 font-semibold">Risk Category</th>
            <th class="px-4 py-3 font-semibold">Severity</th>
            <th class="px-4 py-3 font-semibold">Details</th>
            <th class="px-4 py-3 font-semibold">Reported</th>
            <th class="px-4 py-3 font-semibold">Last Inspected</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold">RSU Notes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <?php foreach ($school_risks as $r):
            $sev  = strtolower($r['severity'] ?? 'medium');
            $stat = strtolower($r['status'] ?? 'pending');
            $sevBadge = match($sev) {
                'critical' => 'badge-critical',
                'high'     => 'badge-high',
                'low'      => 'badge-low',
                default    => 'badge-medium',
            };
            $statTag = match($stat) {
                'resolved'    => 'tag-resolved',
                'in progress' => 'tag-progress',
                'escalated'   => 'tag-escalated',
                default       => 'tag-pending',
            };
          ?>
          <tr class="table-row">
            <td class="px-5 py-3"><span class="category-pill"><?= e($r['risk_category'] ?? '—') ?></span></td>
            <td class="px-4 py-3"><span class="severity-badge <?= $sevBadge ?>"><?= e(ucfirst($r['severity'] ?? '')) ?></span></td>
            <td class="px-4 py-3 text-muted max-w-xs">
              <div class="truncate" title="<?= e($r['details'] ?? '') ?>"><?= e($r['details'] ?? '—') ?></div>
            </td>
            <td class="px-4 py-3 text-muted whitespace-nowrap"><?= e($r['reported_date'] ?? '—') ?></td>
            <td class="px-4 py-3 text-muted whitespace-nowrap"><?= e($r['last_inspected'] ?? '—') ?></td>
            <td class="px-4 py-3"><span class="status-tag <?= $statTag ?>"><?= e(ucfirst($r['status'] ?? 'Pending')) ?></span></td>
            <td class="px-4 py-3 text-muted max-w-xs">
              <?php if (!empty($r['notes'])): ?>
              <span title="<?= e($r['notes']) ?>"><?= e(mb_strimwidth($r['notes'], 0, 50, '…')) ?></span>
              <?php else: ?>
              <span class="text-border">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($school_risks)): ?>
          <tr>
            <td colspan="7" class="px-5 py-10 text-center text-muted">
              <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="mx-auto mb-2 opacity-30"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
              No infrastructure risks currently flagged for this school.<br>
              <span class="text-xs">Use <strong>Report Facility Issue</strong> to submit a concern to the District RSU.</span>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- --- SELD Notice ----------------------------------------------------- -->
  <div class="mt-5 bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
    <svg width="16" height="16" fill="none" stroke="#1D4ED8" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div class="text-xs text-blue-900">
      <strong>SELD Guidelines:</strong> Per the Pakistan School Safety Framework (PSSF) and SELD directives, all Head Masters/Mistresses must promptly report infrastructure deficiencies. Critical structural issues (e.g., dangerous buildings, missing boundary walls) must be escalated to the <strong>District Education Officer (DEO)</strong> and Provincial Works Department within <strong>48 hours</strong> of identification.
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>

<!-- --- Report Issue Modal -------------------------------------------------- -->
<div id="report-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-lg w-full p-6 shadow-xl max-h-[92vh] overflow-y-auto">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <h3 class="text-sm font-bold text-textMain">Report Facility Issue to District RSU</h3>
      <button onclick="closeReportModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>
    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="report_issue" value="1"/>

      <div class="p-3 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 flex items-start gap-2">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        This report will be submitted directly to the District RSU under <strong>SEMIS <?= e($school_semis) ?></strong>. The DEO will be notified for Critical and High severity issues.
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Risk Category *</label>
          <select name="risk_category" required class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option value="">Select Category</option>
            <?php foreach ($risk_categories as $cat): ?>
            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Severity Level *</label>
          <select name="severity" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option value="Critical">?? Critical — Immediate danger</option>
            <option value="High">?? High — Priority repair needed</option>
            <option value="Medium" selected>?? Medium — Scheduled attention</option>
            <option value="Low">?? Low — Minor issue</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Issue Description *</label>
        <textarea name="details" rows="4" required class="w-full text-xs border border-border rounded p-2.5 bg-background focus:outline-none focus:border-primary" placeholder="Describe the exact condition observed: location, extent of damage, how long the issue has existed, and any immediate safety risk to students or staff…"></textarea>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeReportModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Submit to District RSU</button>
      </div>
    </form>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown')?.classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});
function openReportModal(){document.getElementById('report-modal').classList.remove('hidden');}
function closeReportModal(){document.getElementById('report-modal').classList.add('hidden');}
document.getElementById('report-modal').addEventListener('click',function(e){if(e.target===this)this.classList.add('hidden');});
</script>
</body>
</html>

