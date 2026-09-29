<?php
/**
 * school/students.php — School Student Roster Management
 * 
 * Allows Head Master to manage students enrolled in their school,
 * add new students, edit details, and track individual attendance status.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'students';
$page_title  = 'Students Roster — ' . e($school_name);

$notification = '';
$notification_type = 'success';

// ─── Export School Students to Excel CSV ────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_students') {
    $allStudents = ExcelDB::all('students');
    $schoolStudents = array_filter($allStudents, function($stu) use ($school_name, $school_semis) {
        return (!empty($stu['school_name']) && (stripos($stu['school_name'], $school_name) !== false || stripos($school_name, $stu['school_name']) !== false))
            || (!empty($stu['school_semis']) && $stu['school_semis'] === $school_semis);
    });

    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $school_name);
    $filename = "Students_{$school_semis}_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['Student Code', 'Full Name', 'Gender', 'Grade/Class', 'School Name', 'SEMIS Code', 'Attendance %', 'Risk Status']);

    foreach ($schoolStudents as $s) {
        fputcsv($fp, [
            $s['student_code'] ?? '',
            $s['full_name'] ?? '',
            $s['gender'] ?? '',
            $s['grade'] ?? '',
            $school_name,
            $school_semis,
            $s['attendance_pct'] ?? '90%',
            $s['risk_status'] ?? 'Normal'
        ]);
    }
    fclose($fp);
    exit;
}

// ─── Handle Add Student Action ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } else {
        $name    = trim($_POST['full_name'] ?? '');
        $gender  = trim($_POST['gender'] ?? 'Male');
        $grade   = trim($_POST['grade'] ?? 'Grade 1');
        $attPct  = trim($_POST['attendance_pct'] ?? '95');
        if (!str_ends_with($attPct, '%')) $attPct .= '%';
        $risk    = trim($_POST['risk_status'] ?? 'Normal');

        if (empty($name)) {
            $notification = 'Student Full Name is required.';
            $notification_type = 'danger';
        } else {
            $allStudents = ExcelDB::all('students');
            $maxCode = 1000;
            foreach ($allStudents as $st) {
                if (!empty($st['student_code']) && preg_match('/STU-([0-9]+)/', $st['student_code'], $m)) {
                    if ((int)$m[1] > $maxCode) $maxCode = (int)$m[1];
                }
            }
            $newCode = 'STU-' . ($maxCode + 1);

            $badge = 'badge-normal';
            $textCls = 'text-success';
            if ($risk === 'At Risk' || (int)$attPct < 60) {
                $risk = 'At Risk';
                $badge = 'badge-risk';
                $textCls = 'text-danger';
            } elseif ($risk === 'Monitor' || (int)$attPct < 80) {
                $risk = 'Monitor';
                $badge = 'badge-monitor';
                $textCls = 'text-warning';
            }

            ExcelDB::insert('students', [
                'student_code'   => $newCode,
                'full_name'      => $name,
                'gender'         => $gender,
                'school_name'    => $school_name,
                'school_semis'   => $school_semis,
                'grade'          => $grade,
                'attendance_pct' => $attPct,
                'risk_status'    => $risk,
                'status_badge'   => $badge,
                'text_class'     => $textCls
            ]);

            // If at-risk, also add to at_risk.csv
            if ($risk === 'At Risk') {
                ExcelDB::insert('at_risk', [
                    'student_code'   => $newCode,
                    'full_name'      => $name,
                    'school_name'    => $school_name,
                    'school_semis'   => $school_semis,
                    'grade'          => $grade,
                    'taluka'         => $school_taluka,
                    'attendance_pct' => $attPct,
                    'risk_level'     => 'High',
                    'last_followup'  => 'Never',
                    'status'         => 'Unresolved',
                    'action_needed'  => 'Parental Contact Required'
                ]);
            }

            // Update enrollment count in schools.csv
            $allForSchool = array_filter(ExcelDB::all('students'), function($stu) use ($school_name, $school_semis) {
                return (!empty($stu['school_name']) && (stripos($stu['school_name'], $school_name) !== false || stripos($school_name, $stu['school_name']) !== false))
                    || (!empty($stu['school_semis']) && $stu['school_semis'] === $school_semis);
            });
            ExcelDB::update('schools', 'semis_code', $school_semis, [
                'enrollment' => (string)count($allForSchool)
            ]);

            header('Location: ' . BASE_URL . '/school/students.php?msg=added&name=' . urlencode($name));
            exit;
        }
    }
}

// ─── Handle Delete Student Action ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed.';
        $notification_type = 'danger';
    } else {
        $stuCode = trim($_POST['student_code'] ?? '');
        if (!empty($stuCode)) {
            ExcelDB::delete('students', 'student_code', $stuCode);
            ExcelDB::delete('at_risk', 'student_code', $stuCode);
            ExcelDB::delete('attendance', 'student_code', $stuCode);

            // Update enrollment
            $allForSchool = array_filter(ExcelDB::all('students'), function($stu) use ($school_name, $school_semis) {
                return (!empty($stu['school_name']) && (stripos($stu['school_name'], $school_name) !== false || stripos($school_name, $stu['school_name']) !== false))
                    || (!empty($stu['school_semis']) && $stu['school_semis'] === $school_semis);
            });
            ExcelDB::update('schools', 'semis_code', $school_semis, [
                'enrollment' => (string)count($allForSchool)
            ]);

            header('Location: ' . BASE_URL . '/school/students.php?msg=deleted');
            exit;
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $notification = 'Student ' . e($_GET['name'] ?? '') . ' enrolled successfully!';
        $notification_type = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $notification = 'Student record removed successfully.';
        $notification_type = 'success';
    }
}

// ─── Load Students for this School ──────────────────────────────────────────
$allStudents = ExcelDB::all('students');
$schoolStudents = array_values(array_filter($allStudents, function($stu) use ($school_name, $school_semis) {
    return (!empty($stu['school_name']) && (stripos($stu['school_name'], $school_name) !== false || stripos($school_name, $stu['school_name']) !== false))
        || (!empty($stu['school_semis']) && $stu['school_semis'] === $school_semis);
}));

$total_stu = count($schoolStudents);
$normal_count = 0;
$monitor_count = 0;
$risk_count = 0;

foreach ($schoolStudents as $s) {
    $r = $s['risk_status'] ?? 'Normal';
    if ($r === 'At Risk') $risk_count++;
    elseif ($r === 'Monitor') $monitor_count++;
    else $normal_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
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
            sans: ['Inter', 'system-ui', 'sans-serif']
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .btn-primary { background: #046A38; color: #fff; transition: background 0.15s; }
    .btn-primary:hover { background: #03532C; }
    .btn-secondary { background: #F8FAFC; color: #1E293B; border: 1px solid #CBD5E1; }
    .btn-secondary:hover { background: #E2E8F0; }
    .btn-excel { background: #107C41; color: #fff; }
    .btn-excel:hover { background: #0b5c30; }
    .status-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 9999px; }
    .badge-normal { background: #DCFCE7; color: #15803D; }
    .badge-monitor { background: #FEF3C7; color: #92400E; }
    .badge-risk { background: #FEE2E2; color: #991B1B; }
    #sidebar { transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    #overlay { transition: opacity 0.25s; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

  <!-- Top Institutional Header -->
  <div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative border-b border-emerald-500/30">
    <div class="flex items-center gap-2 font-medium">
      <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
      <span>حکومتِ سندھ &bull; <?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
    </div>
    <div class="flex items-center gap-3 text-white/80 text-[11px]">
      <span>School Student Roster</span>
      <span class="text-white/40">&bull;</span>
      <span class="font-semibold text-emerald-300"><?= e($school_semis) ?></span>
    </div>
  </div>

  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

  <div class="flex flex-1 min-h-0">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <main class="flex-1 p-4 md:p-6 space-y-6">

        <!-- Page Header & Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-3 border-b border-border">
          <div>
            <h1 class="text-lg font-bold text-textMain">Enrolled Students Roster</h1>
            <p class="text-xs text-muted">Manage individual student enrollment and attendance for <?= e($school_name) ?></p>
          </div>
          <div class="flex items-center gap-2.5">
            <a href="<?= BASE_URL ?>/school/students.php?action=export_students" class="btn-excel text-xs font-semibold px-3 py-2 rounded-lg flex items-center gap-1.5 shadow-xs">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span>Export CSV</span>
            </a>
            <button onclick="openAddModal()" class="btn-primary text-xs font-semibold px-3.5 py-2 rounded-lg flex items-center gap-1.5 shadow-xs">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>Enroll New Student</span>
            </button>
          </div>
        </div>

        <!-- Notification Banner -->
        <?php if ($notification): ?>
          <div class="p-3.5 rounded-lg border text-xs flex items-center justify-between <?= $notification_type === 'success' ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-red-50 border-red-300 text-red-900' ?>">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><?= e($notification) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
          </div>
        <?php endif; ?>

        <!-- KPI Mini Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-surface border border-border rounded-lg p-3">
            <div class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Registered</div>
            <div class="text-xl font-bold text-textMain font-mono mt-0.5"><?= number_format($total_stu) ?></div>
          </div>
          <div class="bg-surface border border-border rounded-lg p-3">
            <div class="text-[11px] font-semibold text-success uppercase tracking-wider">Regular Attendance</div>
            <div class="text-xl font-bold text-success font-mono mt-0.5"><?= number_format($normal_count) ?></div>
          </div>
          <div class="bg-surface border border-border rounded-lg p-3">
            <div class="text-[11px] font-semibold text-warning uppercase tracking-wider">Under Monitoring</div>
            <div class="text-xl font-bold text-warning font-mono mt-0.5"><?= number_format($monitor_count) ?></div>
          </div>
          <div class="bg-surface border border-border rounded-lg p-3">
            <div class="text-[11px] font-semibold text-danger uppercase tracking-wider">High Risk / Dropout</div>
            <div class="text-xl font-bold text-danger font-mono mt-0.5"><?= number_format($risk_count) ?></div>
          </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
          <div class="w-full sm:w-72 relative">
            <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="stu-search" oninput="filterStudents()" placeholder="Search student name or code..." class="w-full pl-9 pr-3 py-1.5 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-govGreen"/>
          </div>

          <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <select id="filter-grade" onchange="filterStudents()" class="text-xs border border-border rounded-lg px-2.5 py-1.5 bg-background focus:outline-none focus:border-govGreen">
              <option value="">All Grades</option>
              <?php foreach (['ECE / K.G', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'] as $gr): ?>
                <option value="<?= $gr ?>"><?= $gr ?></option>
              <?php endforeach; ?>
            </select>
            <select id="filter-gender" onchange="filterStudents()" class="text-xs border border-border rounded-lg px-2.5 py-1.5 bg-background focus:outline-none focus:border-govGreen">
              <option value="">All Genders</option>
              <option value="Male">Boys / Male</option>
              <option value="Female">Girls / Female</option>
            </select>
            <select id="filter-status" onchange="filterStudents()" class="text-xs border border-border rounded-lg px-2.5 py-1.5 bg-background focus:outline-none focus:border-govGreen">
              <option value="">All Statuses</option>
              <option value="Normal">Normal</option>
              <option value="Monitor">Monitor</option>
              <option value="At Risk">At Risk</option>
            </select>
          </div>
        </div>

        <!-- Student Records Table -->
        <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-xs" aria-label="Student Table">
              <thead>
                <tr class="border-b border-border bg-slate-50 text-muted uppercase text-left tracking-wide">
                  <th class="px-5 py-3 font-semibold">Student Code</th>
                  <th class="px-4 py-3 font-semibold">Full Name</th>
                  <th class="px-4 py-3 font-semibold">Gender</th>
                  <th class="px-4 py-3 font-semibold">Grade / Class</th>
                  <th class="px-4 py-3 font-semibold text-right">Attendance %</th>
                  <th class="px-4 py-3 font-semibold text-center">Risk Status</th>
                  <th class="px-4 py-3 font-semibold text-center">Actions</th>
                </tr>
              </thead>
              <tbody id="stu-tbody" class="divide-y divide-border text-textMain">
                <?php if (!empty($schoolStudents)): ?>
                  <?php foreach ($schoolStudents as $st): ?>
                  <tr class="stu-row hover:bg-slate-50/70 transition"
                      data-name="<?= e(strtolower($st['full_name'] ?? '')) ?>"
                      data-code="<?= e(strtolower($st['student_code'] ?? '')) ?>"
                      data-grade="<?= e($st['grade'] ?? '') ?>"
                      data-gender="<?= e($st['gender'] ?? '') ?>"
                      data-status="<?= e($st['risk_status'] ?? '') ?>">
                    <td class="px-5 py-3 font-mono font-semibold text-primary"><?= e($st['student_code'] ?? '') ?></td>
                    <td class="px-4 py-3 font-medium"><?= e($st['full_name'] ?? '') ?></td>
                    <td class="px-4 py-3 text-muted"><?= e($st['gender'] ?? '') ?></td>
                    <td class="px-4 py-3 text-muted"><?= e($st['grade'] ?? '') ?></td>
                    <td class="px-4 py-3 text-right font-mono font-bold <?= (int)($st['attendance_pct'] ?? 90) < 60 ? 'text-danger' : ((int)($st['attendance_pct'] ?? 90) < 80 ? 'text-warning' : 'text-success') ?>">
                      <?= e($st['attendance_pct'] ?? '90%') ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="status-badge <?= e($st['status_badge'] ?? 'badge-normal') ?>">
                        <?= e($st['risk_status'] ?? 'Normal') ?>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <button onclick="confirmDeleteStudent('<?= e($st['student_code'] ?? '') ?>', '<?= e(addslashes($st['full_name'] ?? '')) ?>')" class="p-1 rounded text-muted hover:text-danger hover:bg-red-50 transition" title="Delete Student Record">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center py-10 text-muted">
                      No student records found in this school directory. Click <strong>Enroll New Student</strong> above to register students.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="px-5 py-3 border-t border-border text-xs text-muted flex items-center justify-between">
            <span>Showing <?= count($schoolStudents) ?> enrolled students</span>
            <span class="font-mono text-[11px]"><?= e($school_name) ?></span>
          </div>
        </div>

      </main>

      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <!-- ── Add Student Modal ───────────────────────────────────────────────────── -->
  <div id="add-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-xl max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
      <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
        <div>
          <h3 class="text-sm font-bold text-textMain">Enroll New Student</h3>
          <p class="text-xs text-muted"><?= e($school_name) ?></p>
        </div>
        <button onclick="closeAddModal()" class="text-muted hover:text-textMain text-xl leading-none">&times;</button>
      </div>

      <form method="POST" action="<?= BASE_URL ?>/school/students.php" class="space-y-3.5">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="add_student" value="1"/>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Student Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" required placeholder="e.g. Ali Raza Lund" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Gender</label>
            <select name="gender" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="Male">Male / Boy</option>
              <option value="Female">Female / Girl</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Grade / Class</label>
            <select name="grade" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <?php foreach (['ECE / K.G', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'] as $gr): ?>
                <option value="<?= $gr ?>"><?= $gr ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Initial Attendance %</label>
            <input type="number" name="attendance_pct" min="0" max="100" value="95" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Monitoring Status</label>
            <select name="risk_status" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
              <option value="Normal">Normal</option>
              <option value="Monitor">Monitor</option>
              <option value="At Risk">At Risk</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-border mt-4">
          <button type="button" onclick="closeAddModal()" class="btn-secondary text-xs font-semibold px-3.5 py-2 rounded-lg">Cancel</button>
          <button type="submit" class="btn-primary text-xs font-semibold px-5 py-2 rounded-lg flex items-center gap-1.5 shadow-sm">
            <span>Save Student Record</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Delete Student Modal ────────────────────────────────────────────────── -->
  <div id="del-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-xl max-w-sm w-full p-5 shadow-xl relative animate-in fade-in zoom-in duration-150">
      <h3 class="text-sm font-bold text-textMain mb-2">Confirm Student Deletion</h3>
      <p class="text-xs text-muted mb-4">
        Are you sure you want to remove <strong id="del-stu-name" class="text-textMain"></strong> (<span id="del-stu-code" class="font-mono text-primary font-semibold"></span>) from your school roster?
      </p>
      <form method="POST" action="<?= BASE_URL ?>/school/students.php" class="flex items-center justify-end gap-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="delete_student" value="1"/>
        <input type="hidden" id="del-stu-input" name="student_code" value=""/>
        <button type="button" onclick="closeDelModal()" class="btn-secondary text-xs font-semibold px-3 py-1.5 rounded-lg">Cancel</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-4 py-1.5 rounded-lg shadow-sm">Delete</button>
      </form>
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

    function openAddModal() { document.getElementById('add-modal').classList.remove('hidden'); }
    function closeAddModal() { document.getElementById('add-modal').classList.add('hidden'); }

    function confirmDeleteStudent(code, name) {
      document.getElementById('del-stu-input').value = code;
      document.getElementById('del-stu-code').innerText = code;
      document.getElementById('del-stu-name').innerText = name;
      document.getElementById('del-modal').classList.remove('hidden');
    }
    function closeDelModal() { document.getElementById('del-modal').classList.add('hidden'); }

    function filterStudents() {
      const query = (document.getElementById('stu-search').value || '').toLowerCase().trim();
      const grade = (document.getElementById('filter-grade').value || '').toLowerCase();
      const gender = (document.getElementById('filter-gender').value || '').toLowerCase();
      const status = (document.getElementById('filter-status').value || '').toLowerCase();

      document.querySelectorAll('.stu-row').forEach(row => {
        const name = row.dataset.name || '';
        const code = row.dataset.code || '';
        const g = (row.dataset.grade || '').toLowerCase();
        const gen = (row.dataset.gender || '').toLowerCase();
        const st = (row.dataset.status || '').toLowerCase();

        const matchQuery = !query || name.includes(query) || code.includes(query);
        const matchGrade = !grade || g === grade;
        const matchGen = !gender || gen.includes(gender);
        const matchStatus = !status || st.includes(status);

        row.style.display = (matchQuery && matchGrade && matchGen && matchStatus) ? '' : 'none';
      });
    }
  </script>
</body>
</html>
