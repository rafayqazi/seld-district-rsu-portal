<?php
/**
 * school/at-risk.php — School At-Risk & Dropout Prevention Tracker
 * 
 * Allows Head Master to monitor vulnerable students, record parental
 * contacts, request financial stipends, and log home visits.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'at-risk';
$page_title  = 'At-Risk & Dropout Prevention — ' . e($school_name);

$notification = '';
$notification_type = 'success';

// ─── Handle Add/Update Intervention Note ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_risk_action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed.';
        $notification_type = 'danger';
    } else {
        $stuCode = trim($_POST['student_code'] ?? '');
        $action  = trim($_POST['action_needed'] ?? '');
        $status  = trim($_POST['status'] ?? 'In Progress');

        if (!empty($stuCode)) {
            $existing = ExcelDB::find('at_risk', 'student_code', $stuCode);
            if ($existing) {
                ExcelDB::update('at_risk', 'student_code', $stuCode, [
                    'action_needed' => $action,
                    'status'        => $status,
                    'last_followup' => date('Y-m-d')
                ]);
            } else {
                $stu = ExcelDB::find('students', 'student_code', $stuCode);
                ExcelDB::insert('at_risk', [
                    'student_code'   => $stuCode,
                    'full_name'      => $stu['full_name'] ?? 'Student',
                    'school_name'    => $school_name,
                    'school_semis'   => $school_semis,
                    'grade'          => $stu['grade'] ?? 'Grade 1',
                    'taluka'         => $school_taluka,
                    'attendance_pct' => $stu['attendance_pct'] ?? '50%',
                    'risk_level'     => 'High',
                    'last_followup'  => date('Y-m-d'),
                    'status'         => $status,
                    'action_needed'  => $action
                ]);
            }
            header('Location: /LSU-PORTAL/school/at-risk.php?msg=updated');
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $notification = 'Intervention record updated successfully!';
    $notification_type = 'success';
}

// ─── Fetch At-Risk Students for this School ─────────────────────────────────
$allAtRisk = ExcelDB::all('at_risk');
$schoolAtRisk = array_values(array_filter($allAtRisk, function($r) use ($school_name, $school_semis) {
    return (!empty($r['school_name']) && (stripos($r['school_name'], $school_name) !== false || stripos($school_name, $r['school_name']) !== false))
        || (!empty($r['school_semis']) && $r['school_semis'] === $school_semis);
}));
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
    .status-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 9999px; }
    .risk-high { background: #FEE2E2; color: #991B1B; }
    .risk-medium { background: #FEF3C7; color: #92400E; }
    .risk-low { background: #E0E7FF; color: #3730A3; }
    #sidebar { transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    #overlay { transition: opacity 0.25s; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

  <!-- Top Sindh Institutional Header -->
  <div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative border-b border-emerald-500/30">
    <div class="flex items-center gap-2 font-medium">
      <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
      <span>حکومتِ سندھ &bull; <?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
    </div>
    <div class="flex items-center gap-3 text-white/80 text-[11px]">
      <span>Dropout Prevention Program</span>
      <span class="text-white/40">&bull;</span>
      <span class="font-semibold text-emerald-300"><?= e($school_semis) ?></span>
    </div>
  </div>

  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

  <div class="flex flex-1 min-h-0">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <main class="flex-1 p-4 md:p-6 space-y-6">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-border">
          <div>
            <h1 class="text-lg font-bold text-textMain">At-Risk &amp; Dropout Watch</h1>
            <p class="text-xs text-muted">Early intervention tracking for students with low attendance</p>
          </div>
          <a href="/LSU-PORTAL/school/students.php" class="btn-secondary text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 self-start">
            <span>View All Students &rarr;</span>
          </a>
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

        <!-- At-Risk Table -->
        <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
          <div class="p-4 border-b border-border flex items-center justify-between">
            <h2 class="text-sm font-bold text-textMain">Identified Students Under Watch</h2>
            <span class="text-xs font-mono font-bold bg-red-50 text-red-700 px-2.5 py-0.5 rounded border border-red-200">
              <?= count($schoolAtRisk) ?> High Risk Flagged
            </span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-xs" aria-label="At Risk Students">
              <thead>
                <tr class="border-b border-border bg-slate-50 text-muted uppercase text-left tracking-wide">
                  <th class="px-5 py-3 font-semibold">Student Code</th>
                  <th class="px-4 py-3 font-semibold">Student Name</th>
                  <th class="px-4 py-3 font-semibold">Grade</th>
                  <th class="px-4 py-3 font-semibold text-right">Attendance %</th>
                  <th class="px-4 py-3 font-semibold">Risk Level</th>
                  <th class="px-4 py-3 font-semibold">Required Action / Intervention</th>
                  <th class="px-4 py-3 font-semibold">Status</th>
                  <th class="px-4 py-3 font-semibold text-center">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border text-textMain">
                <?php if (!empty($schoolAtRisk)): ?>
                  <?php foreach ($schoolAtRisk as $r): ?>
                  <?php 
                    $lvl = $r['risk_level'] ?? 'High';
                    $lvlClass = $lvl === 'High' ? 'risk-high' : ($lvl === 'Medium' ? 'risk-medium' : 'risk-low');
                  ?>
                  <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-5 py-3 font-mono font-semibold text-primary"><?= e($r['student_code'] ?? '') ?></td>
                    <td class="px-4 py-3 font-medium"><?= e($r['full_name'] ?? '') ?></td>
                    <td class="px-4 py-3 text-muted"><?= e($r['grade'] ?? '') ?></td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-danger"><?= e($r['attendance_pct'] ?? '50%') ?></td>
                    <td class="px-4 py-3">
                      <span class="status-badge <?= $lvlClass ?>"><?= e($lvl) ?></span>
                    </td>
                    <td class="px-4 py-3 font-medium text-textMain"><?= e($r['action_needed'] ?? 'Parental Contact Required') ?></td>
                    <td class="px-4 py-3">
                      <span class="text-[11px] font-semibold <?= ($r['status'] ?? '') === 'Resolved' ? 'text-success' : 'text-amber-700' ?>">
                        <?= e($r['status'] ?? 'Unresolved') ?>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <button onclick="openInterventionModal('<?= e($r['student_code'] ?? '') ?>', '<?= e(addslashes($r['full_name'] ?? '')) ?>', '<?= e(addslashes($r['action_needed'] ?? '')) ?>', '<?= e($r['status'] ?? 'In Progress') ?>')" class="btn-primary text-[11px] font-semibold px-2.5 py-1 rounded">
                        Intervene
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center py-10 text-muted">
                      No severe at-risk student flags currently detected for <?= e($school_name) ?>.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </main>

      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <!-- ── Intervention Modal ──────────────────────────────────────────────────── -->
  <div id="action-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-surface border border-border rounded-xl max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
      <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
        <div>
          <h3 class="text-sm font-bold text-textMain">Record Intervention &amp; Action</h3>
          <p class="text-xs text-muted" id="modal-stu-title"></p>
        </div>
        <button onclick="closeInterventionModal()" class="text-muted hover:text-textMain text-xl leading-none">&times;</button>
      </div>

      <form method="POST" action="/LSU-PORTAL/school/at-risk.php" class="space-y-3.5">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="update_risk_action" value="1"/>
        <input type="hidden" id="modal-stu-code" name="student_code" value=""/>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Intervention / Follow-up Action</label>
          <select id="modal-action-select" name="action_needed" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
            <option value="Parental Contact Required">Parental Contact (Phone / Letter)</option>
            <option value="Home Visit Scheduled">Teacher Home Visit Scheduled</option>
            <option value="Financial/Stipend Assessment">Financial Aid / Girls Stipend Assessment</option>
            <option value="Health / Sickness Check">Health &amp; Medical Assessment</option>
            <option value="Teacher Guidance Session">Remedial Class / Counseling Session</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Resolution Status</label>
          <select id="modal-status-select" name="status" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
            <option value="In Progress">In Progress</option>
            <option value="Follow-up Due">Follow-up Due</option>
            <option value="Resolved">Resolved (Attendance Normalized)</option>
            <option value="Unresolved">Unresolved / Pending Action</option>
          </select>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-border mt-4">
          <button type="button" onclick="closeInterventionModal()" class="btn-secondary text-xs font-semibold px-3.5 py-2 rounded-lg">Cancel</button>
          <button type="submit" class="btn-primary text-xs font-semibold px-5 py-2 rounded-lg">
            <span>Save Action</span>
          </button>
        </div>
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

    function openInterventionModal(code, name, action, status) {
      document.getElementById('modal-stu-code').value = code;
      document.getElementById('modal-stu-title').innerText = `${name} (${code})`;
      if (action) document.getElementById('modal-action-select').value = action;
      if (status) document.getElementById('modal-status-select').value = status;
      document.getElementById('action-modal').classList.remove('hidden');
    }
    function closeInterventionModal() {
      document.getElementById('action-modal').classList.add('hidden');
    }
  </script>
</body>
</html>
