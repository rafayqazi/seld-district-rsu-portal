<?php
/**
 * school/complaint-details.php — School Grievance Conversation Thread
 * 
 * Allows Head Masters to view conversation history with District RSU,
 * track status changes, and send official replies.
 * Note: School cannot delete complaint tickets.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'complaints';
$ticketNo    = trim($_GET['ticket'] ?? '');

if (empty($ticketNo)) {
    header('Location: ' . BASE_URL . '/school/complaints.php');
    exit;
}

$complaint = ExcelDB::find('complaints', 'ticket_no', $ticketNo);

// Security check: ensure ticket belongs to this school
if (!$complaint || ($complaint['semis_code'] ?? '') !== $school_semis) {
    // If admin is browsing school portal or name matches, allow fallback
    if (!is_admin() && ($complaint['school_name'] ?? '') !== $school_name) {
        header('Location: ' . BASE_URL . '/school/complaints.php?error=unauthorized');
        exit;
    }
}

// Mark ticket as read for school
if (($complaint['unread_school'] ?? '0') === '1') {
    ExcelDB::update('complaints', 'ticket_no', $ticketNo, ['unread_school' => '0']);
    $complaint['unread_school'] = '0';
}

$flash_success = '';
$flash_error   = '';

// Handle School Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $flash_error = 'Security validation failed (invalid CSRF token). Please try again.';
    } elseif ($action === 'post_school_reply') {
        $message = trim($_POST['message'] ?? '');
        $currentStatus = strtolower($complaint['status'] ?? '');

        if ($currentStatus === 'closed') {
            $flash_error = 'This ticket has been closed by District RSU. No further messages can be submitted.';
        } elseif (empty($message)) {
            $flash_error = 'Please type a message before sending.';
        } else {
            $senderTitle = $hm_name . ' (HM)';
            $success = ExcelDB::addReply($ticketNo, 'school', $senderTitle, $message);
            if ($success) {
                $flash_success = 'Your reply has been dispatched to District RSU.';
                $complaint = ExcelDB::find('complaints', 'ticket_no', $ticketNo);
            } else {
                $flash_error = 'Failed to submit message. Please try again.';
            }
        }
    }
}

// Fetch all conversation replies
$replies = ExcelDB::getRepliesForTicket($ticketNo);

$page_title = "Ticket " . e($ticketNo) . " — " . e($school_name);

$status   = strtolower($complaint['status'] ?? '');
$priority = strtolower($complaint['priority'] ?? '');

$statusBadgeClass = match($status) {
    'pending'      => 'bg-red-100 text-red-800 border-red-200',
    'under review', 'in progress' => 'bg-amber-100 text-amber-800 border-amber-200',
    'resolved'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'closed'       => 'bg-slate-200 text-slate-800 border-slate-300',
    default        => 'bg-slate-100 text-slate-700 border-slate-200'
};

$priorityBadgeClass = match($priority) {
    'urgent' => 'bg-red-50 text-red-700 border-red-200 font-bold',
    'high'   => 'bg-amber-50 text-amber-700 border-amber-200 font-semibold',
    default  => 'bg-slate-100 text-slate-700 border-slate-200'
};
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
  
  <div class="flex flex-1 min-h-screen">
    <!-- School Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">
      <!-- School Header -->
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <!-- Main Content Area -->
      <main class="flex-1 p-4 md:p-6 lg:p-8 space-y-6 max-w-6xl mx-auto w-full">

        <!-- Top Navigation / Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/school/complaints.php" class="p-1.5 rounded-lg bg-surface border border-border text-muted hover:text-textMain hover:bg-slate-50 transition" title="Back to Grievances">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-mono text-sm font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                  <?= e($complaint['ticket_no']) ?>
                </span>
                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $statusBadgeClass ?>">
                  <?= e($complaint['status'] ?? 'Pending') ?>
                </span>
                <span class="inline-block px-2 py-0.5 rounded text-xs border <?= $priorityBadgeClass ?>">
                  <?= e($complaint['priority'] ?? 'Normal') ?> Priority
                </span>
              </div>
              <h1 class="text-xl font-bold text-textMain mt-1"><?= e($complaint['subject'] ?? 'Untitled Grievance') ?></h1>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs text-muted font-medium bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
              Assigned to: <strong class="text-slate-700">District RSU Tando Allahyar</strong>
            </span>
          </div>
        </div>

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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Left 2 Cols: Conversation History & Reply Box -->
          <div class="lg:col-span-2 space-y-5">
            
            <!-- Original Grievance Details -->
            <div class="bg-surface border border-border rounded-xl p-5 shadow-xs">
              <div class="flex items-start justify-between gap-3 border-b border-border pb-3 mb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <?= strtoupper(substr($school_name, 0, 1)) ?>
                  </div>
                  <div>
                    <div class="font-bold text-textMain text-sm"><?= e($school_name) ?></div>
                    <div class="text-xs text-muted">LODGED ON <?= date('F d, Y \a\t h:i A', strtotime($complaint['created_at'] ?? 'now')) ?></div>
                  </div>
                </div>
                <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded">
                  <?= e($complaint['category'] ?? 'General') ?>
                </span>
              </div>

              <div class="text-sm text-textMain leading-relaxed whitespace-pre-line bg-slate-50/70 p-4 rounded-lg border border-slate-100">
                <?= e($complaint['description'] ?? '') ?>
              </div>
            </div>

            <!-- Conversation Thread -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="font-bold text-sm text-textMain flex items-center gap-2">
                  <span>Conversation with District RSU</span>
                  <span class="text-xs font-mono bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">
                    <?= count($replies) ?> <?= count($replies) === 1 ? 'Message' : 'Messages' ?>
                  </span>
                </h3>
              </div>

              <?php if (empty($replies)): ?>
                <div class="p-6 bg-surface border border-dashed border-border rounded-xl text-center text-xs text-muted">
                  Ticket has been registered. Awaiting official acknowledgement from District RSU Coordinator.
                </div>
              <?php else: ?>
                <div class="space-y-4">
                  <?php foreach ($replies as $r): 
                    $isAdmin = ($r['sender_role'] ?? '') === 'admin';
                  ?>
                    <div class="bg-surface border <?= $isAdmin ? 'border-primary/30 bg-emerald-50/10' : 'border-border' ?> rounded-xl p-4 shadow-xs">
                      <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2.5">
                          <div class="w-7 h-7 rounded-full <?= $isAdmin ? 'bg-primary text-white' : 'bg-emerald-700 text-white' ?> flex items-center justify-center text-xs font-bold shadow-xs">
                            <?= $isAdmin ? 'RSU' : 'HM' ?>
                          </div>
                          <div>
                            <span class="font-bold text-xs <?= $isAdmin ? 'text-primary' : 'text-textMain' ?>">
                              <?= e($r['sender_name'] ?? '') ?>
                            </span>
                            <span class="text-[10px] ml-1 px-1.5 py-0.2 rounded font-semibold <?= $isAdmin ? 'bg-primary/10 text-primary' : 'bg-slate-100 text-slate-700' ?>">
                              <?= $isAdmin ? 'District RSU Official' : 'School' ?>
                            </span>
                          </div>
                        </div>
                        <span class="text-[11px] text-muted">
                          <?= date('M d, Y &bull; h:i A', strtotime($r['created_at'] ?? 'now')) ?>
                        </span>
                      </div>
                      <div class="text-xs text-textMain leading-relaxed pl-9 whitespace-pre-line">
                        <?= e($r['message'] ?? '') ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Reply Box for School HM -->
            <?php if ($status !== 'closed'): ?>
              <div class="bg-surface border border-border rounded-xl p-5 shadow-xs">
                <h3 class="font-bold text-sm text-textMain mb-3 flex items-center gap-2">
                  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-emerald-700"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                  <span>Reply / Send Follow-up to District RSU</span>
                </h3>

                <form method="POST" action="<?= BASE_URL ?>/school/complaint-details.php?ticket=<?= urlencode($ticketNo) ?>" class="space-y-3">
                  <input type="hidden" name="action" value="post_school_reply">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                  <div>
                    <textarea name="message" rows="3" required placeholder="Type additional remarks, required clarification, or verification updates for District RSU team..." class="w-full px-3.5 py-2.5 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-emerald-600 text-textMain placeholder-muted leading-relaxed"></textarea>
                  </div>

                  <div class="flex items-center justify-end pt-1">
                    <button type="submit" class="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-lg shadow-xs transition flex items-center gap-2">
                      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                      <span>Send Follow-up Message</span>
                    </button>
                  </div>
                </form>
              </div>
            <?php else: ?>
              <div class="bg-slate-100 border border-slate-200 rounded-xl p-5 text-center text-xs text-muted">
                <div class="font-bold text-slate-700 mb-1">Ticket Closed</div>
                This grievance ticket has been closed by District RSU. If you require further assistance on this issue, please lodge a new complaint ticket.
              </div>
            <?php endif; ?>

          </div>

          <!-- Right Col: Ticket Details & Protocols -->
          <div class="space-y-5">
            
            <div class="bg-surface border border-border rounded-xl p-5 shadow-xs space-y-3 text-xs">
              <h3 class="font-bold text-muted uppercase tracking-wider text-[11px]">Ticket Overview</h3>
              
              <div class="flex justify-between py-1 border-b border-border/60">
                <span class="text-muted">Ticket Number</span>
                <span class="font-mono font-bold text-emerald-800"><?= e($complaint['ticket_no'] ?? '') ?></span>
              </div>
              <div class="flex justify-between py-1 border-b border-border/60">
                <span class="text-muted">Grievance Category</span>
                <span class="font-medium text-textMain"><?= e($complaint['category'] ?? '') ?></span>
              </div>
              <div class="flex justify-between py-1 border-b border-border/60">
                <span class="text-muted">Priority</span>
                <span class="font-bold <?= $priority === 'urgent' ? 'text-red-600' : ($priority === 'high' ? 'text-amber-600' : 'text-slate-700') ?>">
                  <?= e($complaint['priority'] ?? 'Normal') ?>
                </span>
              </div>
              <div class="flex justify-between py-1 border-b border-border/60">
                <span class="text-muted">Current Status</span>
                <span class="font-bold text-textMain"><?= e($complaint['status'] ?? 'Pending') ?></span>
              </div>
              <div class="flex justify-between py-1 border-b border-border/60">
                <span class="text-muted">Reported Date</span>
                <span class="text-textMain"><?= date('M d, Y h:i A', strtotime($complaint['created_at'] ?? 'now')) ?></span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-muted">Last Activity</span>
                <span class="text-textMain"><?= date('M d, Y h:i A', strtotime($complaint['updated_at'] ?? $complaint['created_at'] ?? 'now')) ?></span>
              </div>
            </div>

            <!-- SELD Policy Note -->
            <div class="bg-emerald-50/50 border border-emerald-200 rounded-xl p-4 text-xs text-emerald-950 space-y-2">
              <div class="font-bold text-emerald-900 flex items-center gap-1.5">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-emerald-700"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Department Assistance</span>
              </div>
              <p class="leading-relaxed text-[11px] text-emerald-800">
                For emergency issues requiring immediate physical inspection, contact the District Education Office Helpdesk directly at <strong class="text-emerald-900"><?= CONTACT_PHONE ?></strong>.
              </p>
            </div>

          </div>

        </div>

      </main>

      <!-- Footer -->
      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <script>
    window.LSU_BASE_URL = '<?= BASE_URL ?>';
  </script>
  <script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
