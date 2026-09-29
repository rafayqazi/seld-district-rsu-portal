<?php
/**
 * admin/students.php — Student Overview
 * 
 * Powered by ExcelDB backend. Live reading and updating from /data/students.csv.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'students';
$page_title  = 'Student Overview — ' . APP_NAME;

// ─── Export to Excel Action ──────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    ExcelDB::exportCsv('students');
    exit;
}

// ─── Handle Add Student Form ─────────────────────────────────────────────────
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
    } else {
        $name    = trim($_POST['full_name'] ?? '');
        $code    = trim($_POST['student_code'] ?? '');
        $gender  = trim($_POST['gender'] ?? 'Male');
        $school  = trim($_POST['school_name'] ?? '');
        $grade   = trim($_POST['grade'] ?? 'Grade 1');
        $attPct  = (int)($_POST['attendance_pct'] ?? 90);

        // Determine risk
        $risk = 'Normal';
        $badge = 'badge-normal';
        $textClass = 'text-success';
        if ($attPct < 60) {
            $risk = 'At Risk';
            $badge = 'badge-risk';
            $textClass = 'text-danger';
        } elseif ($attPct < 80) {
            $risk = 'Monitor';
            $badge = 'badge-monitor';
            $textClass = 'text-warning';
        }

        if (!empty($name) && !empty($code)) {
            ExcelDB::insert('students', [
                'student_code'   => $code,
                'full_name'      => $name,
                'gender'         => $gender,
                'school_name'    => $school,
                'grade'          => $grade,
                'attendance_pct' => $attPct . '%',
                'risk_status'    => $risk,
                'status_badge'   => $badge,
                'text_class'     => $textClass
            ]);

            // Also seed into attendance table for today
            ExcelDB::insert('attendance', [
                'student_code' => $code,
                'full_name'    => $name,
                'grade'        => $grade,
                'gender'       => $gender,
                'status'       => 'Present',
                'status_badge' => 'badge-present',
                'monthly_pct'  => $attPct . '%',
                'date'         => date('Y-m-d'),
                'school_name'  => $school
            ]);

            // If at risk, also add to at_risk table with school's dynamic taluka
            if ($risk === 'At Risk') {
                $schRecord = ExcelDB::find('schools', 'school_name', $school);
                $schTaluka = $schRecord['taluka'] ?? 'Tando Allahyar';
                ExcelDB::insert('at_risk', [
                    'student_code'   => $code,
                    'full_name'      => $name,
                    'school_name'    => $school,
                    'grade'          => $grade,
                    'taluka'         => $schTaluka,
                    'attendance_pct' => $attPct . '%',
                    'risk_level'     => 'High',
                    'last_followup'  => 'Never',
                    'status'         => 'Unresolved',
                    'action_needed'  => 'Parental Consultation Needed'
                ]);
            }

            header('Location: /LSU-PORTAL/admin/students.php?msg=added');
            exit;
        } else {
            $notification = 'Please provide student name and ID.';
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'added') {
    $notification = 'Student successfully registered!';
}

// ─── Read Students from Excel Database ────────────────────────────────────────
$students = ExcelDB::all('students');
$schoolsList = ExcelDB::all('schools');

// Calculate KPIs
$total_students = count($students);
$boys_count = 0;
$girls_count = 0;
$active_count = 0;
$risk_count = 0;

foreach ($students as $s) {
    $g = $s['gender'] ?? '';
    if (strcasecmp($g, 'Male') === 0 || strcasecmp($g, 'Boy') === 0) {
        $boys_count++;
    } elseif (strcasecmp($g, 'Female') === 0 || strcasecmp($g, 'Girl') === 0) {
        $girls_count++;
    }

    $r = $s['risk_status'] ?? '';
    if ($r === 'At Risk') {
        $risk_count++;
    } else {
        $active_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<meta name="description" content="District RSU Student Roster &amp; Overview — School Education &amp; Literacy Department"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s,color .15s;}.sidebar-link:hover{background:rgba(255,255,255,.08);}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E;}
.sidebar-group-title{font-size:10px;letter-spacing:.1em;text-transform:uppercase;}
.kpi-card{transition:box-shadow .2s,transform .2s;}.kpi-card:hover{box-shadow:0 4px 16px rgba(18,59,99,.10);transform:translateY(-1px);}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.btn-excel{background:#107C41;color:#fff;transition:background .15s;}.btn-excel:hover{background:#0b5c30;}
.status-badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px;}
.badge-normal{background:#DCFCE7;color:#15803D;}
.badge-monitor{background:#FEF3C7;color:#92400E;}
.badge-risk{background:#FEE2E2;color:#991B1B;}
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
    <a href="/LSU-PORTAL/admin/dashboard.php" class="hover:text-primary">Dashboard</a><span>/</span>
    <span class="text-textMain font-medium">Students</span>
  </nav>

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div>
      <div class="flex items-center gap-2">
        <h1 class="text-xl font-bold text-textMain">Student Overview</h1>
      </div>
      <p class="text-muted text-sm mt-0.5">District-wide student records in <?= APP_DISTRICT ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
      <button onclick="openAddStudentModal()" class="btn-primary px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Register Student
      </button>
      <a href="?action=export_excel" class="btn-secondary px-3 py-2 rounded text-xs font-medium flex items-center gap-1.5 hover:border-primary">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Roster
      </a>
      <a href="/LSU-PORTAL/admin/attendance.php" class="btn-secondary px-3 py-2 rounded text-xs font-medium">Attendance</a>
      <a href="/LSU-PORTAL/admin/at-risk-students.php" class="btn-secondary px-3 py-2 rounded text-xs font-medium text-red-600">At-Risk</a>
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

  <!-- KPI Cards Computed Live -->
  <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
    <div class="kpi-card bg-surface border border-border rounded-lg p-4"><div class="text-xs text-muted mb-1 uppercase tracking-wide">Total Students</div><div class="text-3xl font-bold text-primary"><?= $total_students ?></div><div class="text-xs text-muted mt-1">Total in record</div></div>
    <div class="kpi-card bg-surface border border-border rounded-lg p-4"><div class="text-xs text-muted mb-1 uppercase tracking-wide">Boys</div><div class="text-3xl font-bold text-textMain"><?= $boys_count ?></div><div class="text-xs text-muted mt-1"><?= $total_students > 0 ? round(($boys_count/$total_students)*100, 1) : 0 ?>%</div></div>
    <div class="kpi-card bg-surface border border-border rounded-lg p-4"><div class="text-xs text-muted mb-1 uppercase tracking-wide">Girls</div><div class="text-3xl font-bold text-secondary"><?= $girls_count ?></div><div class="text-xs text-muted mt-1"><?= $total_students > 0 ? round(($girls_count/$total_students)*100, 1) : 0 ?>%</div></div>
    <div class="kpi-card bg-surface border border-border rounded-lg p-4"><div class="text-xs text-muted mb-1 uppercase tracking-wide">Normal / Good</div><div class="text-3xl font-bold text-success"><?= $active_count ?></div><div class="text-xs text-muted mt-1">Regular attendance</div></div>
    <div class="kpi-card bg-surface border border-border rounded-lg p-4"><div class="text-xs text-muted mb-1 uppercase tracking-wide">At-Risk</div><div class="text-3xl font-bold text-danger"><?= $risk_count ?></div><div class="text-xs text-warning mt-1">Needs follow-up</div></div>
  </section>

  <!-- Student Table -->
  <div class="bg-surface border border-border rounded-lg">
    <div class="px-5 py-3 border-b border-border flex flex-wrap gap-2 items-center">
      <input type="text" id="stu-search" placeholder="Search student name or ID…" class="text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary flex-1 min-w-40" onkeyup="filterStu()"/>
      <select id="stu-gender" class="text-xs border border-border rounded px-3 py-1.5 bg-background" onchange="filterStu()">
        <option value="">All Gender</option><option>Male</option><option>Female</option>
      </select>
      <select id="stu-risk" class="text-xs border border-border rounded px-3 py-1.5 bg-background" onchange="filterStu()">
        <option value="">All Risk</option><option>Normal</option><option>Monitor</option><option>At Risk</option>
      </select>
      <button onclick="resetStu()" class="text-xs border border-border rounded px-3 py-1.5 bg-background text-muted hover:bg-border">Reset</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs" aria-label="Student table">
        <thead><tr class="border-b border-border bg-background text-muted uppercase tracking-wide text-left">
          <th class="px-5 py-3 font-semibold">Student ID</th>
          <th class="px-4 py-3 font-semibold">Student Name</th>
          <th class="px-4 py-3 font-semibold">Gender</th>
          <th class="px-4 py-3 font-semibold">School</th>
          <th class="px-4 py-3 font-semibold">Class</th>
          <th class="px-4 py-3 font-semibold text-right">Attendance</th>
          <th class="px-4 py-3 font-semibold">Risk Status</th>
        </tr></thead>
        <tbody id="stu-tbody" class="divide-y divide-border text-textMain">
          <?php foreach ($students as $s): ?>
          <tr class="table-row">
            <td class="px-5 py-3 font-mono text-muted"><?= e($s['student_code'] ?? '') ?></td>
            <td class="px-4 py-3 font-medium"><?= e($s['full_name'] ?? '') ?></td>
            <td class="px-4 py-3 text-muted"><?= e($s['gender'] ?? '') ?></td>
            <td class="px-4 py-3 text-muted"><?= e($s['school_name'] ?? '') ?></td>
            <td class="px-4 py-3 text-muted"><?= e($s['grade'] ?? '') ?></td>
            <td class="px-4 py-3 text-right font-semibold <?= e($s['text_class'] ?? 'text-success') ?>"><?= e($s['attendance_pct'] ?? '0%') ?></td>
            <td class="px-4 py-3"><span class="status-badge <?= e($s['status_badge'] ?? 'badge-normal') ?>"><?= e($s['risk_status'] ?? 'Normal') ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted">
      <span>Showing <?= count($students) ?> registered students</span>
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

<!-- Add Student Modal -->
<div id="add-student-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-lg max-w-lg w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
      <h3 class="text-sm font-bold text-textMain">Register New Student</h3>
      <button onclick="closeAddStudentModal()" class="text-muted hover:text-textMain text-lg leading-none">&times;</button>
    </div>

    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="add_student" value="1"/>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Student ID / GR Number</label>
          <input type="text" name="student_code" required placeholder="e.g. STU-1013" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Gender</label>
          <select name="gender" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option>Male</option><option>Female</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Student Full Name</label>
        <input type="text" name="full_name" required placeholder="e.g. Tariq Hussain" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1">Assigned School</label>
        <select name="school_name" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
          <?php foreach ($schoolsList as $sch): ?>
          <option value="<?= e($sch['school_name']) ?>"><?= e($sch['school_name']) ?> (<?= e($sch['taluka']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Class / Grade</label>
          <select name="grade" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary">
            <option>Grade 1</option><option>Grade 2</option><option>Grade 3</option><option>Grade 4</option><option>Grade 5</option>
            <option>Grade 6</option><option>Grade 7</option><option>Grade 8</option><option>Grade 9</option><option>Grade 10</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Attendance Rate (%)</label>
          <input type="number" name="attendance_pct" min="0" max="100" value="95" class="w-full text-xs border border-border rounded px-3 py-1.5 bg-background focus:outline-none focus:border-primary"/>
        </div>
      </div>

      <div class="pt-3 border-t border-border flex justify-end gap-2">
        <button type="button" onclick="closeAddStudentModal()" class="btn-secondary px-3 py-1.5 rounded text-xs">Cancel</button>
        <button type="submit" class="btn-primary px-4 py-1.5 rounded text-xs font-medium">Register Student</button>
      </div>
    </form>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});

function filterStu(){
  const s=document.getElementById('stu-search').value.toLowerCase();
  const g=document.getElementById('stu-gender').value.toLowerCase();
  const r=document.getElementById('stu-risk').value.toLowerCase();
  document.querySelectorAll('#stu-tbody tr').forEach(tr=>{
    const t=tr.textContent.toLowerCase();
    tr.style.display=(t.includes(s)&&(g===''||t.includes(g))&&(r===''||t.includes(r)))?'':'none';
  });
}
function resetStu(){
  ['stu-search','stu-gender','stu-risk'].forEach(id=>document.getElementById(id).value='');
  filterStu();
}

function openAddStudentModal() {
  document.getElementById('add-student-modal').classList.remove('hidden');
}
function closeAddStudentModal() {
  document.getElementById('add-student-modal').classList.add('hidden');
}
</script>
</body>
</html>
