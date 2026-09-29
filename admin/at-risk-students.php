<?php
/**
 * admin/at-risk-students.php — At-Risk Students Monitoring
 * 
 * Powered by ExcelDB backend. Live tracking, follow-up logging,
 * and Microsoft Excel CSV export.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'at-risk-students';
$page_title  = 'At-Risk Students — ' . APP_NAME;

// ─── Export to Excel Action ──────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    ExcelDB::exportCsv('at_risk');
    exit;
}

// ─── Handle Record Follow-up Submission ──────────────────────────────────────
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_followup'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $stu_code = trim($_POST['student_code'] ?? '');
        $status   = trim($_POST['status'] ?? 'In Progress');
        $note     = trim($_POST['action_needed'] ?? 'Follow-up conducted');

        $updated = ExcelDB::update('at_risk', 'student_code', $stu_code, [
            'last_followup' => date('Y-m-d'),
            'status'        => $status,
            'action_needed' => $note
        ]);

        if ($updated) {
            header('Location: ' . BASE_URL . '/admin/at-risk-students.php?msg=updated');
            exit;
        } else {
            $notification = 'Unable to update record.';
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $notification = 'Student follow-up intervention recorded successfully!';
}

// ─── Load from Database ───────────────────────────────────────────────────────
$risk_records = ExcelDB::all('at_risk');
$available_talukas = ExcelDB::getTalukas();

// Calculate KPIs
$high_count = 0;
$med_count = 0;
$low_count = 0;

foreach ($risk_records as $r) {
    $lvl = strtolower($r['risk_level'] ?? '');
    if ($lvl === 'high') {
        $high_count++;
    } elseif ($lvl === 'medium') {
        $med_count++;
    } elseif ($lvl === 'low') {
        $low_count++;
    }
}
$total_at_risk = count($risk_records);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<meta name="description" content="Identify, monitor, and intervene for at-risk students in <?= APP_DISTRICT ?>"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s,color .15s;}.sidebar-link:hover{background:rgba(255,255,255,.08);}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E;}
.sidebar-group-title{font-size:10px;letter-spacing:.1em;text-transform:uppercase;}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.btn-excel{background:#107C41;color:#fff;transition:background .15s;}.btn-excel:hover{background:#0b5c30;}
.status-badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px;}
.badge-high{background:#FEE2E2;color:#991B1B;}
.badge-medium{background:#FFEDD5;color:#9A3412;}
.badge-low{background:#FEF3C7;color:#92400E;}
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
  <nav class="text-xs text-muted mb-4 flex items-center gap-1.5" aria-label="Breadcrumb">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="hover:text-primary">Dashboard</a><span>/</span>
    <a href="<?= BASE_URL ?>/admin/students.php" class="hover:text-primary">Students</a><span>/</span>
    <span class="text-textMain font-medium">At-Risk Students</span>
  </nav>

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
      <div class="flex items-center gap-2">
        <h1 class="text-xl font-bold text-textMain">At-Risk Students</h1>
        <span class="text-[11px] bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded-full flex items-center gap-1">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          Registry: Active
        </span>
      </div>
      <p class="text-muted text-sm mt-0.5">Students requiring immediate academic &amp; attendance intervention</p>
    </div>
    <div class="flex gap-2">
      <a href="?action=export_excel" class="btn-excel px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Report (CSV)
      </a>
    </div>
  </div>

  <?php if (!empty($notification)): ?>
  <div class="mb-5 p-3.5 rounded-lg text-xs bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      <span><?= e($notification) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">&times;</button>
  </div>
  <?php endif; ?>

  <!-- Risk Summary KPI Cards Computed Live -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center gap-4">
      <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
        <svg width="18" height="18" fill="none" stroke="#DC2626" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div><div class="text-xs text-muted uppercase tracking-wide">High Risk</div><div class="text-2xl font-bold text-danger"><?= $high_count ?></div><div class="text-xs text-muted">Attendance &lt;60%</div></div>
    </div>
    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 flex items-center gap-4">
      <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
        <svg width="18" height="18" fill="none" stroke="#EA580C" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      </div>
      <div><div class="text-xs text-muted uppercase tracking-wide">Medium Risk</div><div class="text-2xl font-bold" style="color:#EA580C"><?= $med_count ?></div><div class="text-xs text-muted">Attendance 60-75%</div></div>
    </div>
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center gap-4">
      <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
        <svg width="18" height="18" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
      </div>
      <div><div class="text-xs text-muted uppercase tracking-wide">Low Risk</div><div class="text-2xl font-bold text-warning"><?= $low_count ?></div><div class="text-xs text-muted">Attendance 75-80%</div></div>
    </div>
  </div>

  <?php if ($high_count > 0): ?>
  <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5 flex items-start gap-3">
    <svg width="16" height="16" fill="none" stroke="#DC2626" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    <div>
      <div class="text-sm font-semibold text-danger">Action Required (<?= $high_count ?> Students)</div>
      <div class="text-xs text-muted mt-0.5">Students are below <?= HIGH_RISK_THRESHOLD ?>% attendance threshold. Use the <strong>Follow Up</strong> button to log interventions directly into the district system.</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Table Card -->
  <div class="bg-surface border border-border rounded-lg shadow-sm">
    <div class="px-5 py-3 border-b border-border flex flex-wrap gap-2 items-center">
      <input type="text" id="risk-search" oninput="filterRisk()" placeholder="Search student or school…" class="text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary flex-1 min-w-36"/>
      <select id="risk-level-filter" onchange="filterRisk()" class="text-xs border border-border rounded px-3 py-1.5 bg-background">
        <option value="">All Risk Levels</option>
        <option value="high">High Risk (&lt;<?= HIGH_RISK_THRESHOLD ?>%)</option>
        <option value="medium">Medium Risk (<?= HIGH_RISK_THRESHOLD ?>-75%)</option>
        <option value="low">Low Risk (75-80%)</option>
      </select>
      <select id="risk-taluka-filter" onchange="filterRisk()" class="text-xs border border-border rounded px-3 py-1.5 bg-background">
        <option value="">All Talukas</option>
        <?php foreach ($available_talukas as $t): ?>
        <option value="<?= e(strtolower($t)) ?>"><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button onclick="resetRisk()" class="text-xs text-muted hover:text-primary px-2 py-1">Reset</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs" aria-label="At-risk students table">
        <thead>
          <tr class="border-b border-border bg-background text-muted uppercase tracking-wide text-left">
            <th class="px-5 py-3 font-semibold">Student</th>
            <th class="px-4 py-3 font-semibold">School</th>
            <th class="px-4 py-3 font-semibold">Class</th>
            <th class="px-4 py-3 font-semibold text-right">Attendance</th>
            <th class="px-4 py-3 font-semibold">Risk Level</th>
            <th class="px-4 py-3 font-semibold">Last Follow-up</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold text-right">Action</th>
          </tr>
        </thead>
        <tbody id="risk-tbody" class="divide-y divide-border text-textMain">
          <?php foreach ($risk_records as $r): 
            $lvlLower = strtolower($r['risk_level'] ?? 'low');
            $badgeClass = 'badge-low';
            $textClass = 'text-warning';
            if ($lvlLower === 'high') {
                $badgeClass = 'badge-high';
                $textClass = 'text-danger font-bold';
            } elseif ($lvlLower === 'medium') {
                $badgeClass = 'badge-medium';
                $textClass = 'font-bold text-orange-600';
            }
          ?>
          <tr class="table-row" data-risk="<?= e($lvlLower) ?>" data-taluka="<?= e(strtolower($r['taluka'] ?? '')) ?>">
            <td class="px-5 py-3">
              <div class="font-medium"><?= e($r['full_name']) ?></div>
              <div class="text-muted font-mono"><?= e($r['student_code']) ?></div>
            </td>
            <td class="px-4 py-3 text-muted"><?= e($r['school_name']) ?></td>
            <td class="px-4 py-3 text-muted"><?= e($r['grade']) ?></td>
            <td class="px-4 py-3 text-right <?= $textClass ?>"><?= e($r['attendance_pct']) ?></td>
            <td class="px-4 py-3"><span class="status-badge <?= $badgeClass ?>"><?= e($r['risk_level']) ?></span></td>
            <td class="px-4 py-3 text-muted"><?= e($r['last_followup']) ?></td>
            <td class="px-4 py-3">
              <span class="text-xs font-medium <?= $r['status'] === 'Resolved' ? 'text-success' : 'text-danger' ?>">
                <?= e($r['status']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button onclick="openFollowupModal('<?= e($r['student_code']) ?>', '<?= e($r['full_name']) ?>', '<?= e($r['status']) ?>', '<?= e($r['action_needed']) ?>')" class="btn-primary px-2.5 py-1 rounded text-xs">
                Follow Up
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-border flex items-center justify-between text-xs text-muted">
      <span>Total <?= count($risk_records) ?> students currently monitored</span>
      <span>Monitoring synchronization active</span>
    </div>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>

<!-- Follow Up Modal -->
<div id="followup-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <h3 class="text-sm font-bold text-textMain" id="modal-student-title">Intervention Follow-Up</h3>
      <button onclick="closeFollowupModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="log_followup" value="1"/>
      <input type="hidden" name="student_code" id="modal-student-code" value=""/>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Intervention Status</label>
        <select name="status" id="modal-status" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
          <option value="In Progress">In Progress (Parent Contacted / Visit Scheduled)</option>
          <option value="Resolved">Resolved (Regular Attendance Resumed)</option>
          <option value="Unresolved">Unresolved (No Response / Escalated)</option>
          <option value="Follow-up Due">Follow-up Due</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Action Details / Notes</label>
        <textarea name="action_needed" id="modal-action" rows="3" required class="w-full text-xs border border-border rounded p-2.5 bg-background focus:outline-none focus:border-primary"></textarea>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeFollowupModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Save to Excel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});

function filterRisk(){
  const s=document.getElementById('risk-search').value.toLowerCase();
  const lvl=document.getElementById('risk-level-filter').value.toLowerCase();
  const tal=document.getElementById('risk-taluka-filter').value.toLowerCase();

  document.querySelectorAll('#risk-tbody tr').forEach(r=>{
    const tx=r.textContent.toLowerCase();
    const rLvl=r.getAttribute('data-risk')||'';
    const rTal=r.getAttribute('data-taluka')||'';
    const matchS=(tx.includes(s));
    const matchL=(lvl===''||rLvl===lvl);
    const matchT=(tal===''||rTal.includes(tal));
    r.style.display=(matchS&&matchL&&matchT)?'':'none';
  });
}
function resetRisk(){
  document.getElementById('risk-search').value='';
  document.getElementById('risk-level-filter').value='';
  document.getElementById('risk-taluka-filter').value='';
  filterRisk();
}

function openFollowupModal(code, name, status, action) {
  document.getElementById('modal-student-code').value = code;
  document.getElementById('modal-student-title').textContent = 'Follow-Up: ' + name + ' (' + code + ')';
  document.getElementById('modal-status').value = status;
  document.getElementById('modal-action').value = action || '';
  document.getElementById('followup-modal').classList.remove('hidden');
}
function closeFollowupModal() {
  document.getElementById('followup-modal').classList.add('hidden');
}
</script>
</body>
</html>
