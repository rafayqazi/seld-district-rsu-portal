<?php
/**
 * admin/messages.php
 *
 * District RSU — Admin Direct Messaging Center.
 * Admin can initiate a conversation with any school.
 * Conversation stays Open until Admin explicitly closes it.
 * School can only REPLY — cannot initiate a new thread.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'messages';
$page_title  = 'Direct Messages — District RSU';

$flash_success = '';
$flash_error   = '';

// ── Handle POST Actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $flash_error = 'Security validation failed (invalid CSRF token). Please refresh and try again.';
    } elseif ($action === 'new_message') {
        $semisCode  = trim($_POST['semis_code'] ?? '');
        $subject    = trim($_POST['subject'] ?? '');
        $message    = trim($_POST['message'] ?? '');

        if (empty($semisCode) || empty($subject) || empty($message)) {
            $flash_error = 'Please fill all required fields (School, Subject, Message).';
        } else {
            $school = ExcelDB::getSchoolBySemis($semisCode);
            if (!$school) {
                $flash_error = 'Selected school not found.';
            } else {
                $threadId  = ExcelDB::generateMessageThreadId();
                $now       = date('Y-m-d H:i:s');
                $adminName = $_SESSION['lsu_username'] ?? 'District RSU Coordinator';

                ExcelDB::insert('admin_messages', [
                    'thread_id'    => $threadId,
                    'semis_code'   => $semisCode,
                    'school_name'  => $school['school_name'],
                    'taluka'       => $school['taluka'],
                    'subject'      => $subject,
                    'status'       => 'Open',
                    'created_by'   => $adminName,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                    'unread_school'=> '1',
                    'unread_admin' => '0',
                ]);

                // Add first message as a reply in the thread
                $replies  = ExcelDB::all('admin_message_replies');
                $maxId    = 0;
                foreach ($replies as $r) {
                    if (isset($r['id']) && is_numeric($r['id']) && (int)$r['id'] > $maxId) $maxId = (int)$r['id'];
                }
                $replies[] = [
                    'id'          => (string)($maxId + 1),
                    'thread_id'   => $threadId,
                    'sender_role' => 'admin',
                    'sender_name' => $adminName . ' (District RSU)',
                    'message'     => $message,
                    'created_at'  => $now,
                ];
                ExcelDB::writeTable('admin_message_replies', $replies);

                $flash_success = 'Message thread <strong>' . e($threadId) . '</strong> started with <strong>' . e($school['school_name']) . '</strong>.';
            }
        }
    } elseif ($action === 'close_thread') {
        $threadId = trim($_POST['thread_id'] ?? '');
        if (!empty($threadId)) {
            ExcelDB::update('admin_messages', 'thread_id', $threadId, [
                'status'     => 'Closed',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $flash_success = 'Conversation <strong>' . e($threadId) . '</strong> has been closed.';
        }
    } elseif ($action === 'reopen_thread') {
        $threadId = trim($_POST['thread_id'] ?? '');
        if (!empty($threadId)) {
            ExcelDB::update('admin_messages', 'thread_id', $threadId, [
                'status'       => 'Open',
                'updated_at'   => date('Y-m-d H:i:s'),
                'unread_school'=> '1',
            ]);
            $flash_success = 'Conversation <strong>' . e($threadId) . '</strong> has been reopened.';
        }
    } elseif ($action === 'delete_thread') {
        $threadId = trim($_POST['thread_id'] ?? '');
        if (!empty($threadId)) {
            $deleted = ExcelDB::deleteMessageThread($threadId);
            if ($deleted) {
                $flash_success = 'Conversation <strong>' . e($threadId) . '</strong> has been permanently deleted.';
            } else {
                $flash_error = 'Failed to delete conversation. Please try again.';
            }
        }
    }
}

if (isset($_GET['deleted']) && !empty($_GET['thread'])) {
    $flash_success = 'Conversation <strong>' . e($_GET['thread']) . '</strong> has been permanently deleted.';
}

// ── Fetch & Filter ─────────────────────────────────────────────────────────
$allThreads = ExcelDB::all('admin_messages');

usort($allThreads, function($a, $b) {
    $uA = ($a['unread_admin'] ?? '0') === '1' ? 1 : 0;
    $uB = ($b['unread_admin'] ?? '0') === '1' ? 1 : 0;
    if ($uA !== $uB) return $uB <=> $uA;
    return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
});

$filterSearch = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$filteredThreads = array_filter($allThreads, function($t) use ($filterSearch, $filterStatus) {
    if (!empty($filterSearch)) {
        $s = strtolower($filterSearch);
        $match = str_contains(strtolower($t['school_name'] ?? ''), $s)
              || str_contains(strtolower($t['subject'] ?? ''), $s)
              || str_contains(strtolower($t['thread_id'] ?? ''), $s)
              || str_contains(strtolower($t['taluka'] ?? ''), $s);
        if (!$match) return false;
    }
    if (!empty($filterStatus) && strtolower($t['status'] ?? '') !== strtolower($filterStatus)) {
        return false;
    }
    return true;
});

$totalThreads  = count($allThreads);
$openThreads   = count(array_filter($allThreads, fn($t) => strtolower($t['status'] ?? '') === 'open'));
$closedThreads = count(array_filter($allThreads, fn($t) => strtolower($t['status'] ?? '') === 'closed'));
$unreadThreads = count(array_filter($allThreads, fn($t) => ($t['unread_admin'] ?? '0') === '1'));

$allSchools = ExcelDB::all('schools');
// Auto-open modal pre-filled from ?semis= param (e.g., from school-profile button)
$autoOpenSemis = trim($_GET['semis'] ?? '');
$autoOpenModal = !empty($autoOpenSemis) && !empty($_GET['compose']) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Admin Direct Messaging — Send messages to schools and track conversations.">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#123B63', primaryDark: '#0B2946', secondary: '#0F766E',
            surface: '#FFFFFF', background: '#F5F7FA', textMain: '#172033',
            muted: '#64748B', border: '#E2E8F0', success: '#15803D',
            warning: '#D97706', danger: '#DC2626',
          },
          fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .sidebar-link { transition: background .15s; }
    .sidebar-link:hover { background: rgba(255,255,255,.08); }
    .sidebar-link.active { background: rgba(255,255,255,.14); border-left: 3px solid #0F766E; }
    .btn-primary { background: #123B63; color: #fff; transition: background .15s; }
    .btn-primary:hover { background: #0B2946; }
    #sidebar { transition: transform .25s cubic-bezier(.4,0,.2,1); }
    #overlay { transition: opacity .25s; }
    .thread-row:hover { background: #f8fafc; }
    /* Searchable school picker */
    #schoolPickerDropdown { max-height: 220px; overflow-y: auto; scroll-behavior: smooth; }
    #schoolPickerDropdown::-webkit-scrollbar { width: 4px; }
    #schoolPickerDropdown::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
    .school-opt { cursor: pointer; transition: background .1s; }
    .school-opt:hover, .school-opt.highlighted { background: #EBF2FA; }
    .school-opt.selected { background: #DBEAFE; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>

<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

<div class="flex flex-1 overflow-hidden">
  <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold text-textMain flex items-center gap-2">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-primary">
              <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
              <line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="12" y2="13"/>
            </svg>
            Direct Messages
          </h1>
          <p class="text-xs text-muted mt-0.5">Send official messages to any school. Schools can reply but cannot start new conversations.</p>
        </div>
        <button onclick="openNewMessageModal()" id="btn-new-message" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primaryDark text-white text-sm font-semibold rounded-lg shadow-sm transition">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          New Message
        </button>
      </div>

      <!-- Flash -->
      <?php if (!empty($flash_success)): ?>
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-xs">
          <div class="flex items-center gap-2.5">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-emerald-600"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?= $flash_success ?></span>
          </div>
          <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 ml-3">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (!empty($flash_error)): ?>
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center justify-between shadow-xs">
          <div class="flex items-center gap-2.5">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-red-600"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= $flash_error ?></span>
          </div>
          <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 ml-3">&times;</button>
        </div>
      <?php endif; ?>

      <!-- Metrics -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-surface rounded-xl border border-border p-4 shadow-xs">
          <p class="text-xs text-muted mb-1">Total Conversations</p>
          <p class="text-2xl font-bold text-primary"><?= $totalThreads ?></p>
        </div>
        <div class="bg-surface rounded-xl border border-border p-4 shadow-xs">
          <p class="text-xs text-muted mb-1">Open</p>
          <p class="text-2xl font-bold text-emerald-600"><?= $openThreads ?></p>
        </div>
        <div class="bg-surface rounded-xl border border-border p-4 shadow-xs">
          <p class="text-xs text-muted mb-1">Closed</p>
          <p class="text-2xl font-bold text-slate-500"><?= $closedThreads ?></p>
        </div>
        <div class="bg-surface rounded-xl border border-border p-4 shadow-xs">
          <p class="text-xs text-muted mb-1">Unread Replies</p>
          <p class="text-2xl font-bold text-amber-600"><?= $unreadThreads ?></p>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
          <div class="flex-1 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="search" value="<?= e($filterSearch) ?>" placeholder="Search school, subject or thread ID…" class="w-full pl-8 pr-3 py-2 text-sm border border-border rounded-lg bg-background focus:outline-none focus:border-primary">
          </div>
          <select name="status" class="px-3 py-2 text-sm border border-border rounded-lg bg-background focus:outline-none focus:border-primary min-w-[130px]">
            <option value="">All Status</option>
            <option value="Open" <?= $filterStatus === 'Open' ? 'selected' : '' ?>>Open</option>
            <option value="Closed" <?= $filterStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
          </select>
          <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primaryDark transition">Filter</button>
          <?php if (!empty($filterSearch) || !empty($filterStatus)): ?>
            <a href="<?= BASE_URL ?>/admin/messages.php" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-200 transition flex items-center">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Threads List -->
      <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
        <?php if (empty($filteredThreads)): ?>
          <div class="p-12 text-center">
            <div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
              <svg width="24" height="24" fill="none" stroke="#123B63" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            </div>
            <p class="text-sm font-semibold text-textMain mb-1">No messages yet</p>
            <p class="text-xs text-muted mb-4">Click <strong>New Message</strong> to start a direct conversation with a school.</p>
            <button onclick="openNewMessageModal()" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primaryDark transition inline-flex items-center gap-2">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Start a Conversation
            </button>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-slate-50 border-b border-border text-xs text-muted uppercase tracking-wide">
                  <th class="text-left px-4 py-3 font-semibold">Thread ID</th>
                  <th class="text-left px-4 py-3 font-semibold">School</th>
                  <th class="text-left px-4 py-3 font-semibold">Subject</th>
                  <th class="text-left px-4 py-3 font-semibold">Status</th>
                  <th class="text-left px-4 py-3 font-semibold">Last Activity</th>
                  <th class="text-right px-4 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <?php foreach ($filteredThreads as $t):
                  $isUnread  = ($t['unread_admin'] ?? '0') === '1';
                  $statusLow = strtolower($t['status'] ?? 'open');
                ?>
                <tr class="thread-row <?= $isUnread ? 'bg-blue-50/40' : '' ?> transition-colors">
                  <td class="px-4 py-3.5">
                    <div class="flex items-center gap-2">
                      <?php if ($isUnread): ?>
                        <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 animate-pulse"></span>
                      <?php else: ?>
                        <span class="w-2 h-2 rounded-full bg-transparent flex-shrink-0"></span>
                      <?php endif; ?>
                      <span class="font-mono text-xs font-bold text-primary bg-primary/10 px-2 py-0.5 rounded"><?= e($t['thread_id']) ?></span>
                    </div>
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="font-semibold text-textMain text-xs leading-tight"><?= e($t['school_name']) ?></div>
                    <div class="text-[11px] text-muted mt-0.5">SEMIS: <?= e($t['semis_code']) ?> &bull; <?= e($t['taluka']) ?></div>
                  </td>
                  <td class="px-4 py-3.5 max-w-xs">
                    <div class="text-xs font-semibold text-textMain truncate"><?= e($t['subject']) ?></div>
                    <div class="text-[11px] text-muted mt-0.5">By <?= e($t['created_by']) ?></div>
                  </td>
                  <td class="px-4 py-3.5">
                    <?php if ($statusLow === 'open'): ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Open
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-slate-100 text-slate-600 border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Closed
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3.5 text-[11px] text-muted whitespace-nowrap">
                    <?= date('M d, Y h:i A', strtotime($t['updated_at'] ?? $t['created_at'] ?? 'now')) ?>
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                      <a href="<?= BASE_URL ?>/admin/message-thread.php?thread=<?= urlencode($t['thread_id']) ?>" class="px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary/20 text-[11px] font-semibold rounded-lg transition">
                        Open Thread
                      </a>
                      <?php if ($statusLow === 'open'): ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="action" value="close_thread">
                          <input type="hidden" name="thread_id" value="<?= e($t['thread_id']) ?>">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <button type="submit" class="px-3 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 text-[11px] font-semibold rounded-lg transition">Close</button>
                        </form>
                      <?php else: ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="action" value="reopen_thread">
                          <input type="hidden" name="thread_id" value="<?= e($t['thread_id']) ?>">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <button type="submit" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[11px] font-semibold rounded-lg transition">Reopen</button>
                        </form>
                      <?php endif; ?>
                      <button type="button"
                              id="del-btn-<?= e($t['thread_id']) ?>"
                              onclick="setDeleteTarget('<?= e($t['thread_id']) ?>')"
                              class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-semibold rounded-lg transition">Delete</button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </main>

    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>
<!-- ── Static Delete Form (pre-rendered, reused for all delete actions) ──── -->
<form id="deleteThreadForm" method="POST" style="display:none">
  <input type="hidden" name="action" value="delete_thread">
  <input type="hidden" name="thread_id" id="deleteThreadId" value="">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
</form>

<!-- ── New Message Modal ──────────────────────────────────────────────────── -->
<div id="newMsgModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true">
  <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-lg border border-border">
    <div class="flex items-center justify-between px-6 py-4 border-b border-border">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
          <svg width="18" height="18" fill="none" stroke="#123B63" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <div>
          <h2 class="font-bold text-textMain text-sm">Send Direct Message</h2>
          <p class="text-[11px] text-muted">Start a new official conversation with a school</p>
        </div>
      </div>
      <button onclick="closeNewMessageModal()" class="text-muted hover:text-textMain p-1.5 rounded-lg hover:bg-slate-100 transition">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form method="POST" class="px-6 py-5 space-y-4">
      <input type="hidden" name="action" value="new_message">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <!-- Hidden real input for form submission -->
      <input type="hidden" name="semis_code" id="schoolSelectHidden" required>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1.5">Select School <span class="text-red-500">*</span></label>
        <!-- Selected school pill preview -->
        <div id="selectedSchoolPill" class="hidden mb-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between gap-2">
          <div class="flex-1 min-w-0">
            <div id="selectedSchoolName" class="text-xs font-semibold text-primary truncate"></div>
            <div id="selectedSchoolCode" class="text-[10px] text-muted font-mono"></div>
          </div>
          <button type="button" onclick="clearSchoolSelection()" class="text-muted hover:text-danger flex-shrink-0" title="Clear selection">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <!-- Search input -->
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="schoolSearchInput" autocomplete="off"
                 placeholder="Search by school name or SEMIS code…"
                 class="w-full pl-8 pr-3 py-2.5 text-sm border border-border rounded-lg bg-background focus:outline-none focus:border-primary"
                 oninput="filterSchools(this.value)"
                 onfocus="openSchoolDropdown()"/>
        </div>
        <!-- Dropdown list -->
        <div id="schoolPickerDropdown" class="hidden mt-1 border border-border rounded-lg bg-surface shadow-lg z-10 relative">
          <?php foreach ($allSchools as $sc): ?>
          <div class="school-opt px-3 py-2.5 flex items-center justify-between gap-2 border-b border-border/50 last:border-0"
               data-semis="<?= e($sc['semis_code']) ?>"
               data-name="<?= e($sc['school_name']) ?>"
               data-search="<?= strtolower(e($sc['school_name']) . ' ' . e($sc['semis_code'])) ?>"
               onclick="selectSchool('<?= e($sc['semis_code']) ?>', '<?= addslashes(e($sc['school_name'])) ?>')">
            <span class="text-xs text-textMain leading-snug"><?= e($sc['school_name']) ?></span>
            <span class="text-[10px] font-mono text-primary bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded flex-shrink-0"><?= e($sc['semis_code']) ?></span>
          </div>
          <?php endforeach; ?>
          <div id="schoolNoResults" class="hidden px-4 py-4 text-center text-xs text-muted">
            No schools matched. Try a different name or SEMIS code.
          </div>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1.5" for="msgSubject">Subject <span class="text-red-500">*</span></label>
        <input type="text" name="subject" id="msgSubject" required maxlength="200" placeholder="e.g. Enrollment Data Verification — Q1 2026" class="w-full px-3 py-2.5 text-sm border border-border rounded-lg bg-background focus:outline-none focus:border-primary">
      </div>

      <div>
        <label class="block text-xs font-semibold text-textMain mb-1.5" for="msgBody">Message <span class="text-red-500">*</span></label>
        <textarea name="message" id="msgBody" required rows="5" placeholder="Type your official message to the school Head Master…" class="w-full px-3.5 py-2.5 text-sm border border-border rounded-lg bg-background focus:outline-none focus:border-primary resize-none leading-relaxed"></textarea>
      </div>

      <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 flex items-start gap-2">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>The school Head Master can read and reply to this message. The conversation stays open until you close it.</span>
      </div>

      <div class="flex gap-3 pt-1">
        <button type="button" onclick="closeNewMessageModal()" class="flex-1 px-4 py-2.5 bg-slate-100 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-200 transition">Cancel</button>
        <button type="submit" class="flex-1 px-4 py-2.5 bg-primary hover:bg-primaryDark text-white text-sm font-semibold rounded-lg shadow-sm transition flex items-center justify-center gap-2">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Send Message
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openSidebar() {
  document.getElementById('sidebar').classList.remove('-translate-x-full');
  const o = document.getElementById('overlay'); o.classList.remove('hidden'); setTimeout(() => o.classList.remove('opacity-0'), 10);
}
function closeSidebar() {
  document.getElementById('sidebar').classList.add('-translate-x-full');
  const o = document.getElementById('overlay'); o.classList.add('opacity-0'); setTimeout(() => o.classList.add('hidden'), 250);
}
function toggleNotif() { document.getElementById('notif-dropdown').classList.toggle('hidden'); }
document.addEventListener('click', function(e) {
  const b = document.getElementById('notif-btn'), d = document.getElementById('notif-dropdown');
  if (b && d && !b.contains(e.target) && !d.contains(e.target)) d.classList.add('hidden');
});
function openNewMessageModal(preSelectSemis, preSelectName) {
  document.getElementById('newMsgModal').classList.remove('hidden');
  if (preSelectSemis && preSelectName) {
    selectSchool(preSelectSemis, preSelectName);
  } else {
    setTimeout(() => document.getElementById('schoolSearchInput').focus(), 80);
  }
}
function closeNewMessageModal() {
  document.getElementById('newMsgModal').classList.add('hidden');
  closeSchoolDropdown();
}
document.getElementById('newMsgModal').addEventListener('click', function(e) {
  if (e.target === this) closeNewMessageModal();
});

// ── Searchable School Picker ──────────────────────────────────────────────────
function openSchoolDropdown() {
  document.getElementById('schoolPickerDropdown').classList.remove('hidden');
}
function closeSchoolDropdown() {
  document.getElementById('schoolPickerDropdown').classList.add('hidden');
}
function filterSchools(query) {
  openSchoolDropdown();
  const q = query.toLowerCase().trim();
  const opts = document.querySelectorAll('.school-opt');
  let visible = 0;
  opts.forEach(opt => {
    const match = !q || opt.getAttribute('data-search').includes(q);
    opt.classList.toggle('hidden', !match);
    if (match) visible++;
  });
  document.getElementById('schoolNoResults').classList.toggle('hidden', visible > 0);
}
function selectSchool(semis, name) {
  // Set hidden input
  document.getElementById('schoolSelectHidden').value = semis;
  // Show selected pill
  document.getElementById('selectedSchoolName').textContent = name;
  document.getElementById('selectedSchoolCode').textContent = 'SEMIS: ' + semis;
  document.getElementById('selectedSchoolPill').classList.remove('hidden');
  // Clear & close search
  document.getElementById('schoolSearchInput').value = '';
  filterSchools('');
  closeSchoolDropdown();
  // Highlight selected option
  document.querySelectorAll('.school-opt').forEach(o => {
    o.classList.toggle('selected', o.getAttribute('data-semis') === semis);
  });
  // Focus on subject
  setTimeout(() => document.getElementById('msgSubject').focus(), 50);
}
function clearSchoolSelection() {
  document.getElementById('schoolSelectHidden').value = '';
  document.getElementById('selectedSchoolPill').classList.add('hidden');
  document.getElementById('selectedSchoolName').textContent = '';
  document.getElementById('selectedSchoolCode').textContent = '';
  document.querySelectorAll('.school-opt').forEach(o => o.classList.remove('selected'));
  filterSchools('');
  setTimeout(() => document.getElementById('schoolSearchInput').focus(), 50);
}
// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  const picker = document.getElementById('schoolPickerDropdown');
  const searchInput = document.getElementById('schoolSearchInput');
  if (picker && searchInput && !picker.contains(e.target) && e.target !== searchInput) {
    closeSchoolDropdown();
  }
});

// Auto-open modal if ?compose=1&semis=... is in URL
(function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('compose') === '1') {
    const semis = params.get('semis') || '';
    if (semis) {
      // Find school name from the DOM options
      const opt = document.querySelector(`.school-opt[data-semis="${CSS.escape(semis)}"]`);
      const name = opt ? opt.getAttribute('data-name') : semis;
      openNewMessageModal(semis, name);
    } else {
      openNewMessageModal();
    }
  }
})();

// ── Delete Conversation ───────────────────────────────────────────────────────
// Uses a pre-rendered static form (#deleteThreadForm) — 100% reliable in all browsers.
function setDeleteTarget(threadId) {
  document.getElementById('deleteThreadId').value = threadId;
  customConfirm(
    'Permanently delete this conversation and all its messages? This cannot be undone.',
    function() {
      document.getElementById('deleteThreadForm').submit();
    },
    { title: 'Delete Conversation', okText: 'Yes, Delete', isDanger: true }
  );
}
</script>
</body>
</html>
