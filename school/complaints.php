<?php
/**
 * school/complaints.php — School Grievance & Complaints Portal
 * 
 * Allows Head Masters to lodge official grievance tickets to District RSU,
 * track status in real-time, and participate in direct conversation threads.
 * Note: Complaints cannot be deleted by schools as per SELD audit regulations.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'complaints';
$page_title  = 'Grievances & Complaints — ' . e($school_name);

$flash_success = '';
$flash_error   = '';

// Handle New Complaint Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $flash_error = 'Security validation failed (invalid CSRF token). Please refresh and try again.';
    } elseif ($action === 'lodge_complaint') {
        $category    = trim($_POST['category'] ?? '');
        $priority    = trim($_POST['priority'] ?? 'Normal');
        $subject     = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($category) || empty($subject) || empty($description)) {
            $flash_error = 'Please fill out all required fields (Category, Subject, Description).';
        } else {
            $ticketNo = ExcelDB::generateTicketNo();
            $now = date('Y-m-d H:i:s');

            // 1. Insert Complaint Ticket
            $complaintRecord = [
                'ticket_no'     => $ticketNo,
                'semis_code'    => $school_semis,
                'school_name'   => $school_name,
                'taluka'        => $school_taluka,
                'category'      => $category,
                'priority'      => $priority,
                'subject'       => $subject,
                'description'   => $description,
                'status'        => 'Pending',
                'created_at'    => $now,
                'updated_at'    => $now,
                'unread_admin'  => '1',
                'unread_school' => '0'
            ];
            ExcelDB::insert('complaints', $complaintRecord);

            // 2. Insert Initial Reply Record for Thread
            $replyRecord = [
                'ticket_no'   => $ticketNo,
                'sender_role' => 'school',
                'sender_name' => $hm_name . ' (HM)',
                'message'     => $description,
                'created_at'  => $now
            ];
            ExcelDB::insert('complaint_replies', $replyRecord);

            $flash_success = "Grievance Ticket <strong>" . e($ticketNo) . "</strong> has been lodged successfully and dispatched to District RSU.";
        }
    }
}

// Fetch all complaints for THIS school
$allComplaints = ExcelDB::all('complaints');
$schoolComplaints = array_values(array_filter($allComplaints, function($c) use ($school_semis, $school_name) {
    return ($c['semis_code'] ?? '') === $school_semis 
        || (!empty($c['school_name']) && (stripos($c['school_name'], $school_name) !== false || stripos($school_name, $c['school_name']) !== false));
}));

// Sort by updated_at descending
usort($schoolComplaints, function($a, $b) {
    return strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? '');
});

// Metrics
$totalSchoolTickets = count($schoolComplaints);
$pendingTickets     = 0;
$inProgressTickets  = 0;
$resolvedTickets    = 0;

foreach ($schoolComplaints as $c) {
    $st = strtolower($c['status'] ?? '');
    if ($st === 'pending') $pendingTickets++;
    elseif ($st === 'under review' || $st === 'in progress') $inProgressTickets++;
    elseif ($st === 'resolved' || $st === 'closed') $resolvedTickets++;
}

$categories = [
    'SMC Funds & Grants',
    'Textbooks & Free Supplies',
    'Teacher Shortage & Absenteeism',
    'Biometric & Salary Grievance',
    'Building & Infrastructure Breakdown',
    'Electricity & Water Outage',
    'Security / Boundary / Harassment Issue',
    'Examination & Enrolment Issues',
    'Other Departmental Matters'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#0F766E',
            primaryDark: '#0D625B',
            govNavy: '#123B63',
            'govNavy-dark': '#0B2946',
            background: '#F8FAFC',
            surface: '#FFFFFF',
            textMain: '#1E293B',
            muted: '#64748B',
            border: '#E2E8F0',
            success: '#16A34A',
            warning: '#D97706',
            danger: '#DC2626',
          },
          fontFamily: { sans: ['Inter', 'sans-serif'] },
        }
      }
    }
  </script>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col font-sans">
  
  <!-- Mobile Sidebar Backdrop Overlay -->
  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0 transition-opacity duration-200" onclick="closeSidebar()"></div>

  <div class="flex flex-1 min-h-screen">
    <!-- School Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">
      <!-- School Header -->
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <!-- Main Content Area -->
      <main class="flex-1 p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- Alerts -->
        <?php if (!empty($flash_success)): ?>
          <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-emerald-600"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <span><?= $flash_success ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">&times;</button>
          </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
          <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-red-600"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <span><?= $flash_error ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">&times;</button>
          </div>
        <?php endif; ?>

        <!-- District RSU Closure / Resolution Notification Banner -->
        <?php
        $closedNotifs = array_filter($schoolComplaints, function($c) {
            $st = strtolower($c['status'] ?? '');
            return ($c['unread_school'] ?? '0') === '1' && in_array($st, ['closed', 'resolved']);
        });
        if (!empty($closedNotifs)):
        ?>
          <div id="closureNotifBanner" class="p-4 rounded-xl border flex items-start justify-between gap-4 shadow-sm"
               style="background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);border-color:#10b981;">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
              </div>
              <div>
                <div class="font-bold text-emerald-900 text-sm flex items-center gap-2">
                  District RSU Update — Complaint Status Changed
                  <span class="text-[10px] bg-emerald-600 text-white px-2 py-0.5 rounded-full font-bold animate-pulse">
                    <?= count($closedNotifs) ?> Ticket<?= count($closedNotifs) > 1 ? 's' : '' ?>
                  </span>
                </div>
                <p class="text-xs text-emerald-800 mt-1 leading-relaxed">
                  District RSU has updated the status of the following ticket<?= count($closedNotifs) > 1 ? 's' : '' ?>:
                </p>
                <ul class="mt-2 space-y-1">
                  <?php foreach ($closedNotifs as $cn): ?>
                    <li class="flex items-center gap-2 text-xs">
                      <a href="<?= BASE_URL ?>/school/complaint-details.php?ticket=<?= urlencode($cn['ticket_no']) ?>"
                         class="font-mono font-bold text-emerald-800 hover:underline"><?= e($cn['ticket_no']) ?></a>
                      <span class="text-emerald-700">&mdash; <?= e($cn['subject'] ?? '') ?></span>
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border
                        <?= strtolower($cn['status']) === 'closed' ? 'bg-slate-200 text-slate-800 border-slate-300' : 'bg-emerald-100 text-emerald-800 border-emerald-200' ?>">
                        <?= e($cn['status']) ?>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
            <button onclick="document.getElementById('closureNotifBanner').remove()"
                    class="text-emerald-600 hover:text-emerald-900 text-lg leading-none flex-shrink-0">&times;</button>
          </div>
        <?php endif; ?>

        <!-- Page Header & Action -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-lg bg-emerald-700 text-white flex items-center justify-center font-bold shadow-xs">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
              </div>
              <h1 class="text-2xl font-bold text-textMain tracking-tight">School Grievance &amp; Complaints</h1>
            </div>
            <p class="text-sm text-muted mt-1">Lodge administrative complaints, track redressal status, and communicate directly with District RSU.</p>
          </div>

          <div class="flex items-center gap-3">
            <button onclick="openLodgeModal()" class="px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-2">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>Lodge New Complaint</span>
            </button>
          </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-surface border border-border p-4 rounded-xl shadow-xs">
            <div class="flex items-center justify-between text-muted text-xs font-medium">
              <span>Total Lodged</span>
              <span class="w-2 h-2 rounded-full bg-slate-400"></span>
            </div>
            <div class="text-2xl font-bold text-textMain mt-2"><?= $totalSchoolTickets ?></div>
            <div class="text-[11px] text-muted mt-1">Submitted by <?= e($school_name) ?></div>
          </div>

          <div class="bg-surface border border-red-200/80 p-4 rounded-xl shadow-xs">
            <div class="flex items-center justify-between text-red-600 text-xs font-medium">
              <span>Pending Review</span>
              <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
            </div>
            <div class="text-2xl font-bold text-red-600 mt-2"><?= $pendingTickets ?></div>
            <div class="text-[11px] text-muted mt-1">Awaiting District RSU response</div>
          </div>

          <div class="bg-surface border border-amber-200/80 p-4 rounded-xl shadow-xs">
            <div class="flex items-center justify-between text-amber-700 text-xs font-medium">
              <span>In Progress / Review</span>
              <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            </div>
            <div class="text-2xl font-bold text-amber-700 mt-2"><?= $inProgressTickets ?></div>
            <div class="text-[11px] text-muted mt-1">Under administrative action</div>
          </div>

          <div class="bg-surface border border-emerald-200/80 p-4 rounded-xl shadow-xs">
            <div class="flex items-center justify-between text-emerald-700 text-xs font-medium">
              <span>Resolved</span>
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            <div class="text-2xl font-bold text-emerald-700 mt-2"><?= $resolvedTickets ?></div>
            <div class="text-[11px] text-muted mt-1">Redressed &amp; closed</div>
          </div>
        </div>

        <!-- SELD Audit Notice -->
        <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl flex items-start gap-3 text-xs text-muted">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-emerald-700 flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <div class="leading-relaxed">
            <span class="font-bold text-textMain">SELD Transparency &amp; Grievance Protocol:</span>
            Head Masters can lodge official complaints and communicate continuously through the conversation thread. In compliance with SELD official records, lodged complaints are logged into the permanent district registry and cannot be deleted by schools.
          </div>
        </div>

        <!-- Complaints Table Card -->
        <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
          <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h2 class="font-bold text-textMain text-sm">Your School's Grievance Tickets</h2>
            <span class="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded-full font-semibold">
              <?= $totalSchoolTickets ?> Tickets
            </span>
          </div>

          <?php if (empty($schoolComplaints)): ?>
            <div class="p-12 text-center">
              <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center mx-auto mb-3">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              </div>
              <h3 class="text-sm font-bold text-textMain">No Complaints Lodged Yet</h3>
              <p class="text-xs text-muted mt-1 max-w-sm mx-auto">If your school is experiencing issues regarding SMC funds, teacher shortages, electricity/water, or textbooks, click the button below to lodge a ticket.</p>
              <button onclick="openLodgeModal()" class="mt-4 px-4 py-2 bg-emerald-700 text-white text-xs font-bold rounded-lg hover:bg-emerald-800 transition">
                + Lodge First Complaint
              </button>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-50/80 border-b border-border text-muted font-semibold uppercase tracking-wider text-[11px]">
                    <th class="py-3.5 px-4">Ticket #</th>
                    <th class="py-3.5 px-4">Category &amp; Subject</th>
                    <th class="py-3.5 px-4">Priority</th>
                    <th class="py-3.5 px-4">Status</th>
                    <th class="py-3.5 px-4">Timeline</th>
                    <th class="py-3.5 px-4 text-right">Conversation</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <?php foreach ($schoolComplaints as $c): 
                    $st = strtolower($c['status'] ?? '');
                    $pr = strtolower($c['priority'] ?? '');
                    $isUnread = ($c['unread_school'] ?? '0') === '1';

                    $statusClass = match($st) {
                      'pending'      => 'bg-red-100 text-red-800 border-red-200',
                      'under review', 'in progress' => 'bg-amber-100 text-amber-800 border-amber-200',
                      'resolved'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                      'closed'       => 'bg-slate-200 text-slate-800 border-slate-300',
                      default        => 'bg-slate-100 text-slate-700 border-slate-200'
                    };

                    $priorityClass = match($pr) {
                      'urgent' => 'bg-red-50 text-red-700 border-red-200 font-bold',
                      'high'   => 'bg-amber-50 text-amber-700 border-amber-200 font-semibold',
                      default  => 'bg-slate-100 text-slate-700 border-slate-200'
                    };
                  ?>
                    <tr class="hover:bg-slate-50/60 transition <?= $isUnread ? 'bg-emerald-50/30' : '' ?>">
                      
                      <!-- Ticket # -->
                      <td class="py-3.5 px-4">
                        <div class="flex items-center gap-1.5">
                          <?php if ($isUnread): ?>
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping" title="New official response from District RSU"></span>
                          <?php endif; ?>
                          <a href="<?= BASE_URL ?>/school/complaint-details.php?ticket=<?= urlencode($c['ticket_no'] ?? '') ?>" class="font-mono font-bold text-emerald-800 hover:underline">
                            <?= e($c['ticket_no'] ?? '') ?>
                          </a>
                        </div>
                        <?php if ($isUnread): ?>
                          <span class="inline-block mt-0.5 text-[9px] bg-emerald-100 text-emerald-800 font-bold px-1.5 rounded">NEW REPLY</span>
                        <?php endif; ?>
                      </td>

                      <!-- Category & Subject -->
                      <td class="py-3.5 px-4">
                        <span class="inline-block text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded mb-0.5">
                          <?= e($c['category'] ?? 'General') ?>
                        </span>
                        <div class="font-semibold text-textMain text-xs max-w-md truncate" title="<?= e($c['subject'] ?? '') ?>">
                          <?= e($c['subject'] ?? '') ?>
                        </div>
                      </td>

                      <!-- Priority -->
                      <td class="py-3.5 px-4">
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] border <?= $priorityClass ?>">
                          <?= e($c['priority'] ?? 'Normal') ?>
                        </span>
                      </td>

                      <!-- Status -->
                      <td class="py-3.5 px-4">
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $statusClass ?>">
                          <?= e($c['status'] ?? 'Pending') ?>
                        </span>
                      </td>

                      <!-- Timeline -->
                      <td class="py-3.5 px-4 text-[11px] text-muted whitespace-nowrap">
                        <div><?= date('M d, Y', strtotime($c['created_at'] ?? 'now')) ?></div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Active: <?= date('M d, H:i', strtotime($c['updated_at'] ?? $c['created_at'] ?? 'now')) ?></div>
                      </td>

                      <!-- Actions -->
                      <td class="py-3.5 px-4 text-right whitespace-nowrap">
                        <a href="<?= BASE_URL ?>/school/complaint-details.php?ticket=<?= urlencode($c['ticket_no'] ?? '') ?>" class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-xs font-semibold transition inline-flex items-center gap-1.5">
                          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                          <span>View Thread</span>
                        </a>
                      </td>

                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div>

      </main>

      <!-- Footer -->
      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <!-- Lodge Complaint Modal -->
  <div id="lodgeModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface rounded-2xl shadow-2xl max-w-lg w-full p-6 border border-border">
      <div class="flex items-center justify-between pb-3 border-b border-border">
        <div>
          <h3 class="font-bold text-textMain text-base">Lodge Grievance / Complaint Ticket</h3>
          <p class="text-xs text-muted mt-0.5">Direct Submission to District RSU Tando Allahyar</p>
        </div>
        <button type="button" onclick="closeLodgeModal()" class="text-muted hover:text-textMain p-1">&times;</button>
      </div>
      
      <form method="POST" action="<?= BASE_URL ?>/school/complaints.php" class="mt-4 space-y-4">
        <input type="hidden" name="action" value="lodge_complaint">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <!-- Category -->
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Grievance Category <span class="text-red-500">*</span></label>
          <select name="category" required class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-emerald-600">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Priority -->
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Priority Level <span class="text-red-500">*</span></label>
          <div class="grid grid-cols-3 gap-2">
            <label class="flex items-center gap-2 p-2 border border-border rounded-lg cursor-pointer hover:bg-slate-50 text-xs">
              <input type="radio" name="priority" value="Normal" checked class="text-emerald-600">
              <span>Normal</span>
            </label>
            <label class="flex items-center gap-2 p-2 border border-border rounded-lg cursor-pointer hover:bg-slate-50 text-xs">
              <input type="radio" name="priority" value="High" class="text-amber-600">
              <span class="text-amber-700 font-medium">High</span>
            </label>
            <label class="flex items-center gap-2 p-2 border border-border rounded-lg cursor-pointer hover:bg-slate-50 text-xs">
              <input type="radio" name="priority" value="Urgent" class="text-red-600">
              <span class="text-red-700 font-bold">Urgent</span>
            </label>
          </div>
        </div>

        <!-- Subject -->
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Subject / Summary <span class="text-red-500">*</span></label>
          <input type="text" name="subject" required placeholder="e.g. Non-disbursement of SMC 1st quarter grant" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-emerald-600">
        </div>

        <!-- Detailed Description -->
        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Detailed Description of Grievance <span class="text-red-500">*</span></label>
          <textarea name="description" rows="4" required placeholder="Provide full context, affected classes/teachers, attempts made, and requested assistance from District RSU..." class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-emerald-600 leading-relaxed"></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
          <button type="button" onclick="closeLodgeModal()" class="px-4 py-2 text-xs text-muted hover:text-textMain font-medium rounded-lg">Cancel</button>
          <button type="submit" class="px-5 py-2 text-xs bg-emerald-700 text-white font-bold rounded-lg hover:bg-emerald-800 shadow-xs">Submit Grievance Ticket</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.LSU_BASE_URL = '<?= BASE_URL ?>';

    function openSidebar() {
      const s = document.getElementById('sidebar');
      const o = document.getElementById('overlay');
      if (s) s.classList.remove('-translate-x-full');
      if (o) {
        o.classList.remove('hidden');
        setTimeout(() => o.classList.remove('opacity-0'), 10);
      }
    }

    function closeSidebar() {
      const s = document.getElementById('sidebar');
      const o = document.getElementById('overlay');
      if (s) s.classList.add('-translate-x-full');
      if (o) {
        o.classList.add('opacity-0');
        setTimeout(() => o.classList.add('hidden'), 200);
      }
    }

    function openLodgeModal() {
      document.getElementById('lodgeModal').classList.remove('hidden');
    }
    function closeLodgeModal() {
      document.getElementById('lodgeModal').classList.add('hidden');
    }
  </script>
  <script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
