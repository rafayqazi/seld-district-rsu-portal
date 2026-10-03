<?php
/**
 * admin/complaint-details.php
 *
 * Detailed Complaint Conversation & Grievance Resolution Hub.
 * Allows District Admins to converse with Head Masters, provide official updates,
 * resolve issues, or close tickets.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'complaints';
$ticketNo    = trim($_GET['ticket'] ?? '');

if (empty($ticketNo)) {
    header('Location: ' . BASE_URL . '/admin/complaints.php');
    exit;
}

$complaint = ExcelDB::find('complaints', 'ticket_no', $ticketNo);
if (!$complaint) {
    header('Location: ' . BASE_URL . '/admin/complaints.php?error=not_found');
    exit;
}

// Mark ticket as read for admin
if (($complaint['unread_admin'] ?? '0') === '1') {
    ExcelDB::update('complaints', 'ticket_no', $ticketNo, ['unread_admin' => '0']);
    $complaint['unread_admin'] = '0';
}

$school = ExcelDB::getSchoolBySemis($complaint['semis_code'] ?? '');

$flash_success = '';
$flash_error   = '';

// Handle Admin Actions (Reply, Change Status, Close, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $flash_error = 'Security validation failed (invalid CSRF token). Please try again.';
    } elseif ($action === 'post_reply') {
        $message = trim($_POST['message'] ?? '');
        $newStatus = trim($_POST['status_update'] ?? '');

        if (empty($message)) {
            $flash_error = 'Please type your response message before sending.';
        } else {
            $adminName = $_SESSION['lsu_username'] ?? 'District RSU Coordinator';
            $success = ExcelDB::addReply($ticketNo, 'admin', $adminName . ' (District RSU)', $message);

            if (!empty($newStatus) && in_array($newStatus, ['Pending', 'Under Review', 'In Progress', 'Resolved', 'Closed'], true)) {
                $statusUpdate = [
                    'status'     => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                // Notify school when ticket is closed or resolved
                if (in_array($newStatus, ['Closed', 'Resolved'], true)) {
                    $statusUpdate['unread_school'] = '1';
                }
                ExcelDB::update('complaints', 'ticket_no', $ticketNo, $statusUpdate);
                $complaint['status'] = $newStatus;
            }

            if ($success) {
                $flash_success = 'Official response sent successfully to the school.';
                // Re-fetch complaint
                $complaint = ExcelDB::find('complaints', 'ticket_no', $ticketNo);
            } else {
                $flash_error = 'Failed to submit response. Please try again.';
            }
        }
    } elseif ($action === 'update_status') {
        $newStatus = trim($_POST['new_status'] ?? '');
        if (in_array($newStatus, ['Pending', 'Under Review', 'In Progress', 'Resolved', 'Closed'], true)) {
            $statusUpdate = [
                'status'     => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            // Notify school when ticket is closed or resolved
            if (in_array($newStatus, ['Closed', 'Resolved'], true)) {
                $statusUpdate['unread_school'] = '1';
            }
            ExcelDB::update('complaints', 'ticket_no', $ticketNo, $statusUpdate);
            $complaint['status'] = $newStatus;
            $flash_success = "Ticket status updated to <strong>" . e($newStatus) . "</strong>.";
        }
    } elseif ($action === 'delete_ticket') {
        ExcelDB::delete('complaints', 'ticket_no', $ticketNo);
        $replies = ExcelDB::all('complaint_replies');
        $filteredReplies = array_filter($replies, fn($r) => ($r['ticket_no'] ?? '') !== $ticketNo);
        ExcelDB::writeTable('complaint_replies', array_values($filteredReplies));
        header('Location: ' . BASE_URL . '/admin/complaints.php?deleted=1');
        exit;
    }
}

// Fetch all conversation replies for this ticket
$replies = ExcelDB::getRepliesForTicket($ticketNo);

$page_title = "Ticket " . e($ticketNo) . " — Grievance Redressal";

$status = strtolower($complaint['status'] ?? '');
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
            primary: '#123B63',
            primaryDark: '#0B2946',
            secondary: '#0F766E',
            background: '#F5F7FA',
            surface: '#FFFFFF',
            textMain: '#172033',
            muted: '#64748B',
            border: '#E2E8F0',
            success: '#15803D',
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
    <!-- Sidebar -->
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

      <!-- Main Content Area -->
      <main class="flex-1 p-4 md:p-6 lg:p-8 space-y-6 max-w-6xl mx-auto w-full">

        <!-- Top Navigation / Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/admin/complaints.php" class="p-1.5 rounded-lg bg-surface border border-border text-muted hover:text-textMain hover:bg-slate-50 transition" title="Back to Complaints">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-mono text-sm font-bold text-primary bg-primary/10 px-2 py-0.5 rounded">
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

          <!-- Quick Action Controls -->
          <div class="flex items-center gap-2">
            <!-- Quick Status Change Form -->
            <form method="POST" action="<?= BASE_URL ?>/admin/complaint-details.php?ticket=<?= urlencode($ticketNo) ?>" class="flex items-center gap-2">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <select name="new_status" onchange="this.form.submit()" class="px-3 py-1.5 text-xs font-semibold border border-border rounded-lg bg-surface text-textMain focus:outline-none focus:border-primary shadow-xs">
                <option value="Pending" <?= $status === 'pending' ? 'selected' : '' ?>>Status: Pending</option>
                <option value="Under Review" <?= $status === 'under review' ? 'selected' : '' ?>>Status: Under Review</option>
                <option value="In Progress" <?= $status === 'in progress' ? 'selected' : '' ?>>Status: In Progress</option>
                <option value="Resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Status: Resolved</option>
                <option value="Closed" <?= $status === 'closed' ? 'selected' : '' ?>>Status: Closed</option>
              </select>
            </form>

            <button onclick="confirmDeleteTicket()" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 text-xs font-semibold rounded-lg shadow-xs transition flex items-center gap-1">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              <span>Delete</span>
            </button>
          </div>
        </div>

        <!-- Flash messages -->
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
          
          <!-- Left 2 Cols: Conversation & Replies -->
          <div class="lg:col-span-2 space-y-5">
            
            <!-- Original Complaint Statement Box -->
            <div class="bg-surface border border-border rounded-xl p-5 shadow-xs">
              <div class="flex items-start justify-between gap-3 border-b border-border pb-3 mb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <?= strtoupper(substr($complaint['school_name'] ?? 'S', 0, 1)) ?>
                  </div>
                  <div>
                    <div class="font-bold text-textMain text-sm"><?= e($complaint['school_name'] ?? '') ?></div>
                    <div class="text-xs text-muted">LODGED BY HEAD MASTER &bull; <?= date('F d, Y \a\t h:i A', strtotime($complaint['created_at'] ?? 'now')) ?></div>
                  </div>
                </div>
                <span class="text-xs font-semibold bg-secondary/10 text-secondary px-2.5 py-1 rounded">
                  <?= e($complaint['category'] ?? 'General') ?>
                </span>
              </div>

              <div class="text-sm text-textMain leading-relaxed whitespace-pre-line bg-slate-50/60 p-4 rounded-lg border border-slate-100">
                <?= e($complaint['description'] ?? '') ?>
              </div>
            </div>

            <!-- Threaded Conversation History -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="font-bold text-sm text-textMain flex items-center gap-2">
                  <span>Conversation &amp; Redressal Updates</span>
                  <span class="text-xs font-mono bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">
                    <?= count($replies) ?> <?= count($replies) === 1 ? 'Message' : 'Messages' ?>
                  </span>
                </h3>
              </div>

              <?php if (empty($replies)): ?>
                <div class="p-6 bg-surface border border-dashed border-border rounded-xl text-center text-xs text-muted">
                  No conversation replies recorded yet. Post an official response below to begin communication with the school.
                </div>
              <?php else: ?>
                <div class="space-y-4">
                  <?php foreach ($replies as $r): 
                    $isAdmin = ($r['sender_role'] ?? '') === 'admin';
                  ?>
                    <div class="bg-surface border <?= $isAdmin ? 'border-primary/20 bg-primary/[0.02]' : 'border-border' ?> rounded-xl p-4 shadow-xs">
                      <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2.5">
                          <div class="w-7 h-7 rounded-full <?= $isAdmin ? 'bg-primary text-white' : 'bg-emerald-700 text-white' ?> flex items-center justify-center text-xs font-bold shadow-xs">
                            <?= $isAdmin ? 'A' : 'S' ?>
                          </div>
                          <div>
                            <span class="font-bold text-xs <?= $isAdmin ? 'text-primary' : 'text-textMain' ?>">
                              <?= e($r['sender_name'] ?? '') ?>
                            </span>
                            <span class="text-[10px] ml-1 px-1.5 py-0.2 rounded font-semibold <?= $isAdmin ? 'bg-primary/10 text-primary' : 'bg-emerald-100 text-emerald-800' ?>">
                              <?= $isAdmin ? 'District RSU' : 'School HM' ?>
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

            <!-- Post Reply Box (District Admin) -->
            <div class="bg-surface border border-border rounded-xl p-5 shadow-xs">
              <h3 class="font-bold text-sm text-textMain mb-3 flex items-center gap-2">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-primary"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                <span>Write Official Response to School</span>
              </h3>

              <form method="POST" action="<?= BASE_URL ?>/admin/complaint-details.php?ticket=<?= urlencode($ticketNo) ?>" class="space-y-3">
                <input type="hidden" name="action" value="post_reply">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div>
                  <textarea name="message" rows="4" required placeholder="Type official instructions, verification status, or resolution remarks for the Head Master..." class="w-full px-3.5 py-2.5 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary text-textMain placeholder-muted leading-relaxed"></textarea>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                  <div class="flex items-center gap-2 text-xs">
                    <label class="font-semibold text-muted">Update Status upon sending:</label>
                    <select name="status_update" class="px-2.5 py-1.5 border border-border rounded-md text-xs bg-background focus:outline-none focus:border-primary">
                      <option value="">Keep current (<?= e($complaint['status'] ?? 'Pending') ?>)</option>
                      <option value="Under Review">Set to Under Review</option>
                      <option value="In Progress">Set to In Progress</option>
                      <option value="Resolved">Mark as Resolved</option>
                      <option value="Closed">Close Ticket</option>
                    </select>
                  </div>

                  <button type="submit" class="px-5 py-2 bg-primary hover:bg-primaryDark text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center justify-center gap-2">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <span>Send Response</span>
                  </button>
                </div>
              </form>
            </div>

          </div>

          <!-- Right Col: School Details & Metadata Sidebar -->
          <div class="space-y-5">
            
            <!-- School Overview Card -->
            <div class="bg-surface border border-border rounded-xl p-5 shadow-xs space-y-4">
              <h3 class="font-bold text-xs text-muted uppercase tracking-wider">School Information</h3>
              
              <div>
                <div class="font-bold text-textMain text-sm"><?= e($complaint['school_name'] ?? '') ?></div>
                <div class="text-xs text-muted mt-0.5 font-mono">SEMIS: <?= e($complaint['semis_code'] ?? '') ?></div>
              </div>

              <div class="grid grid-cols-2 gap-3 text-xs pt-2 border-t border-border">
                <div>
                  <span class="text-muted block text-[11px]">Taluka</span>
                  <span class="font-semibold text-textMain"><?= e($complaint['taluka'] ?? 'N/A') ?></span>
                </div>
                <div>
                  <span class="text-muted block text-[11px]">School Level</span>
                  <span class="font-semibold text-textMain"><?= e($school['level'] ?? 'Primary') ?></span>
                </div>
                <div>
                  <span class="text-muted block text-[11px]">Head Master</span>
                  <span class="font-semibold text-textMain"><?= e($school['head_master'] ?? 'N/A') ?></span>
                </div>
                <div>
                  <span class="text-muted block text-[11px]">HM Contact</span>
                  <span class="font-semibold text-textMain"><?= e($school['phone'] ?? 'N/A') ?></span>
                </div>
              </div>

              <?php if (!empty($school['semis_code'])): ?>
                <div class="pt-2 border-t border-border">
                  <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($school['semis_code']) ?>" class="text-xs text-primary font-semibold hover:underline flex items-center gap-1">
                    <span>View Full School Profile</span>
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                  </a>
                </div>
              <?php endif; ?>
            </div>

            <!-- Ticket Metadata Card -->
            <div class="bg-surface border border-border rounded-xl p-5 shadow-xs space-y-3 text-xs">
              <h3 class="font-bold text-muted uppercase tracking-wider text-[11px]">Ticket Metadata</h3>
              
              <div class="flex justify-between py-1 border-b border-border/60">
                <span class="text-muted">Ticket Number</span>
                <span class="font-mono font-bold text-primary"><?= e($complaint['ticket_no'] ?? '') ?></span>
              </div>
              <div class="flex justify-between py-1 border-b border-border/60">
                <span class="text-muted">Category</span>
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

            <!-- SELD Redressal Guidance Box -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-muted space-y-2">
              <div class="font-bold text-textMain flex items-center gap-1.5">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-secondary"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>SELD Redressal Protocol</span>
              </div>
              <p class="leading-relaxed text-[11px]">
                Urgent grievances (teacher shortage, security, critical infrastructure) must be acknowledged within 24 hours. Normal grievances are expected to be reviewed within 3 business days.
              </p>
            </div>

          </div>

        </div>

      </main>

      <!-- Footer -->
      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface rounded-xl shadow-xl max-w-sm w-full p-5 border border-border">
      <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-3">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      </div>
      <h3 class="font-bold text-textMain text-sm">Delete Ticket <?= e($complaint['ticket_no'] ?? '') ?>?</h3>
      <p class="text-xs text-muted mt-1">This will permanently delete this complaint and all conversation messages. This action cannot be undone.</p>
      
      <form method="POST" action="<?= BASE_URL ?>/admin/complaint-details.php?ticket=<?= urlencode($ticketNo) ?>" class="mt-4">
        <input type="hidden" name="action" value="delete_ticket">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="flex items-center justify-end gap-2">
          <button type="button" onclick="closeDeleteModal()" class="px-3 py-1.5 text-xs text-muted hover:text-textMain font-medium rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-1.5 text-xs bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700">Yes, Permanently Delete</button>
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

    function confirmDeleteTicket() {
      document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeDeleteModal() {
      document.getElementById('deleteModal').classList.add('hidden');
    }
  </script>
  <script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
