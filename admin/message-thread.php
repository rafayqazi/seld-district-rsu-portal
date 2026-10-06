<?php
/**
 * admin/message-thread.php
 *
 * View and reply within an admin-initiated direct message thread.
 * Admin can send messages and close/reopen the conversation.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'messages';
$threadId    = trim($_GET['thread'] ?? '');

if (empty($threadId)) {
    header('Location: ' . BASE_URL . '/admin/messages.php');
    exit;
}

$thread = ExcelDB::find('admin_messages', 'thread_id', $threadId);
if (!$thread) {
    header('Location: ' . BASE_URL . '/admin/messages.php?error=not_found');
    exit;
}

// Mark as read for admin
if (($thread['unread_admin'] ?? '0') === '1') {
    ExcelDB::update('admin_messages', 'thread_id', $threadId, ['unread_admin' => '0']);
    $thread['unread_admin'] = '0';
}

$school = ExcelDB::getSchoolBySemis($thread['semis_code'] ?? '');

$flash_success = '';
$flash_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $flash_error = 'Security validation failed. Please try again.';
    } elseif ($action === 'post_reply') {
        $message = trim($_POST['message'] ?? '');
        $statusChange = trim($_POST['status_change'] ?? '');

        if (strtolower($thread['status'] ?? '') === 'closed') {
            $flash_error = 'This conversation is closed. Reopen it before sending a reply.';
        } elseif (empty($message)) {
            $flash_error = 'Please type a message before sending.';
        } else {
            $adminName = $_SESSION['lsu_username'] ?? 'District RSU Coordinator';
            ExcelDB::addMessageReply($threadId, 'admin', $adminName . ' (District RSU)', $message);

            if (!empty($statusChange) && in_array($statusChange, ['Open', 'Closed'], true)) {
                ExcelDB::update('admin_messages', 'thread_id', $threadId, [
                    'status'     => $statusChange,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $thread['status'] = $statusChange;
            }

            $thread = ExcelDB::find('admin_messages', 'thread_id', $threadId);
            $flash_success = 'Your message has been sent to the school.';
        }
    } elseif ($action === 'close_thread') {
        ExcelDB::update('admin_messages', 'thread_id', $threadId, [
            'status'     => 'Closed',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $thread['status'] = 'Closed';
        $flash_success = 'Conversation has been closed.';
    } elseif ($action === 'reopen_thread') {
        ExcelDB::update('admin_messages', 'thread_id', $threadId, [
            'status'       => 'Open',
            'updated_at'   => date('Y-m-d H:i:s'),
            'unread_school'=> '1',
        ]);
        $thread['status'] = 'Open';
        $flash_success = 'Conversation has been reopened.';
    } elseif ($action === 'delete_thread') {
        ExcelDB::deleteMessageThread($threadId);
        header('Location: ' . BASE_URL . '/admin/messages.php?deleted=1&thread=' . urlencode($threadId));
        exit;
    }
}

$replies  = ExcelDB::getRepliesForThread($threadId);
$statusLow = strtolower($thread['status'] ?? 'open');
$isClosed  = $statusLow === 'closed';

$page_title = 'Thread ' . e($threadId) . ' — ' . e($thread['school_name'] ?? 'School');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
          fontFamily: { sans: ['Inter', 'sans-serif'] }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar-link { transition: background .15s; }
    .sidebar-link:hover { background: rgba(255,255,255,.08); }
    .sidebar-link.active { background: rgba(255,255,255,.14); border-left: 3px solid #0F766E; }
    #sidebar { transition: transform .25s cubic-bezier(.4,0,.2,1); }
    #overlay { transition: opacity .25s; }
    .msg-bubble-admin { background: #EBF2FA; border: 1px solid #C3D5E8; }
    .msg-bubble-school { background: #F0FFF4; border: 1px solid #A7F3D0; }
    #chatBox { scroll-behavior: smooth; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col font-sans">

<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>

<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0 transition-opacity duration-200" onclick="closeSidebar()"></div>

<div class="flex flex-1 min-h-screen">
  <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0">
    <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

    <main class="flex-1 p-4 md:p-6 lg:p-8 space-y-5 max-w-5xl mx-auto w-full">

      <!-- Breadcrumb / Top Nav -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2">
          <a href="<?= BASE_URL ?>/admin/messages.php" class="p-1.5 rounded-lg bg-surface border border-border text-muted hover:text-textMain hover:bg-slate-50 transition">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
          </a>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-mono text-sm font-bold text-primary bg-primary/10 px-2 py-0.5 rounded"><?= e($threadId) ?></span>
              <?php if ($isClosed): ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border bg-slate-100 text-slate-600 border-slate-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Closed
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Open
                </span>
              <?php endif; ?>
            </div>
            <h1 class="text-lg font-bold text-textMain mt-0.5"><?= e($thread['subject'] ?? '') ?></h1>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="flex items-center gap-2">
          <?php if ($isClosed): ?>
            <form method="POST">
              <input type="hidden" name="action" value="reopen_thread">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <button type="submit" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-semibold rounded-lg border border-emerald-200 transition flex items-center gap-1.5">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.09"/></svg>
                Reopen
              </button>
            </form>
          <?php else: ?>
            <form method="POST">
              <input type="hidden" name="action" value="close_thread">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <button type="submit" class="px-3 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-semibold rounded-lg border border-slate-200 transition flex items-center gap-1.5">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                Close Conversation
              </button>
            </form>
          <?php endif; ?>
          <form method="POST" class="inline" onsubmit="return confirmFormSubmit(event, this, 'Permanently delete this conversation and all its messages?', {title:'Delete Conversation', okText:'Yes, Delete', isDanger:true})">
            <input type="hidden" name="action" value="delete_thread">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 text-xs font-semibold rounded-lg border border-red-200 transition flex items-center gap-1.5">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Delete
            </button>
          </form>
          <?php if (!empty($school['semis_code'])): ?>
            <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($school['semis_code']) ?>" class="px-3 py-1.5 bg-surface border border-border text-muted hover:text-primary text-xs font-semibold rounded-lg transition">
              School Profile
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Flash -->
      <?php if (!empty($flash_success)): ?>
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
          <span><?= $flash_success ?></span>
          <button onclick="this.parentElement.remove()" class="text-emerald-600 ml-3">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (!empty($flash_error)): ?>
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center justify-between">
          <span><?= $flash_error ?></span>
          <button onclick="this.parentElement.remove()" class="text-red-600 ml-3">&times;</button>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Conversation Column -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Chat Bubbles -->
          <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="flex items-center justify-between px-5 py-3 border-b border-border bg-slate-50">
              <h3 class="font-bold text-xs text-textMain flex items-center gap-2">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-primary"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Conversation History
              </h3>
              <span class="text-[11px] text-muted font-mono bg-slate-100 px-2 py-0.5 rounded-full"><?= count($replies) ?> message<?= count($replies) !== 1 ? 's' : '' ?></span>
            </div>

            <div id="chatBox" class="p-5 space-y-4 max-h-[520px] overflow-y-auto">
              <?php if (empty($replies)): ?>
                <div id="emptyChatNotice" class="text-center text-xs text-muted py-6">No messages yet. Start the conversation below.</div>
              <?php else: ?>
                <?php foreach ($replies as $r):
                  $isAdminMsg = ($r['sender_role'] ?? '') === 'admin';
                ?>
                  <div class="flex <?= $isAdminMsg ? 'justify-end' : 'justify-start' ?> msg-row" data-reply-id="<?= e($r['id'] ?? '0') ?>">
                    <div class="max-w-[80%] space-y-1">
                      <div class="flex items-center gap-1.5 <?= $isAdminMsg ? 'justify-end' : '' ?>">
                        <div class="w-5 h-5 rounded-full <?= $isAdminMsg ? 'bg-primary text-white order-last' : 'bg-emerald-700 text-white' ?> flex items-center justify-center text-[9px] font-bold flex-shrink-0">
                          <?= $isAdminMsg ? 'A' : 'S' ?>
                        </div>
                        <span class="text-[10px] font-semibold <?= $isAdminMsg ? 'text-primary' : 'text-emerald-700' ?>"><?= e($r['sender_name'] ?? '') ?></span>
                        <span class="text-[10px] text-muted"><?= date('M d, h:i A', strtotime($r['created_at'] ?? 'now')) ?></span>
                      </div>
                      <div class="px-4 py-3 rounded-2xl text-xs leading-relaxed text-textMain whitespace-pre-line shadow-xs <?= $isAdminMsg ? 'msg-bubble-admin rounded-tr-sm' : 'msg-bubble-school rounded-tl-sm' ?>">
                        <?= e($r['message'] ?? '') ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Reply Box -->
          <div id="replySection">
            <?php if (!$isClosed): ?>
              <div id="replyBoxWrap" class="bg-surface border border-border rounded-xl p-5 shadow-xs">
                <h3 class="font-bold text-xs text-textMain mb-3 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-primary"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <span>Send a Message</span>
                  </div>
                  <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Live Sync Active
                  </span>
                </h3>
                <form id="replyForm" method="POST" class="space-y-3">
                  <input type="hidden" name="action" value="post_reply">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                  <textarea name="message" id="messageInput" rows="3" required placeholder="Type your official message to the school Head Master… (Press Enter to send, Shift+Enter for new line)" class="w-full px-3.5 py-2.5 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary text-textMain leading-relaxed resize-none"></textarea>

                  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs">
                      <label class="font-semibold text-muted text-[11px]">After sending:</label>
                      <select name="status_change" id="statusChangeSelect" class="px-2.5 py-1.5 border border-border rounded-md text-xs bg-background focus:outline-none focus:border-primary">
                        <option value="">Keep Open</option>
                        <option value="Closed">Send & Close Conversation</option>
                      </select>
                    </div>
                    <button type="submit" id="sendBtn" class="px-5 py-2 bg-primary hover:bg-primaryDark text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center justify-center gap-2">
                      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                      <span>Send</span>
                    </button>
                  </div>
                </form>
              </div>
            <?php else: ?>
              <div id="closedNoticeWrap" class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center text-xs text-slate-500 shadow-xs">
                <svg class="w-8 h-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                This conversation is <strong>Closed</strong>. No further messages can be sent until it is reopened.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Sidebar: Thread Info -->
        <div class="space-y-4">

          <!-- School Info Card -->
          <div class="bg-surface border border-border rounded-xl p-5 shadow-xs space-y-3">
            <h3 class="font-bold text-xs text-muted uppercase tracking-wider">School Information</h3>
            <div>
              <div class="font-bold text-textMain text-sm"><?= e($thread['school_name'] ?? '') ?></div>
              <div class="text-xs text-muted font-mono mt-0.5">SEMIS: <?= e($thread['semis_code'] ?? '') ?></div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs pt-2 border-t border-border">
              <div>
                <span class="text-muted block text-[11px]">Taluka</span>
                <span class="font-semibold text-textMain"><?= e($thread['taluka'] ?? 'N/A') ?></span>
              </div>
              <div>
                <span class="text-muted block text-[11px]">Level</span>
                <span class="font-semibold text-textMain"><?= e($school['level'] ?? 'N/A') ?></span>
              </div>
              <div>
                <span class="text-muted block text-[11px]">Head Master</span>
                <span class="font-semibold text-textMain"><?= e($school['head_master'] ?? 'N/A') ?></span>
              </div>
              <div>
                <span class="text-muted block text-[11px]">Contact</span>
                <span class="font-semibold text-textMain"><?= e($school['phone'] ?? 'N/A') ?></span>
              </div>
            </div>
            <?php if (!empty($school['semis_code'])): ?>
              <div class="pt-2 border-t border-border">
                <a href="<?= BASE_URL ?>/admin/school-profile.php?semis=<?= urlencode($school['semis_code']) ?>" class="text-xs text-primary font-semibold hover:underline flex items-center gap-1">
                  <span>View School Profile</span>
                  <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
              </div>
            <?php endif; ?>
          </div>

          <!-- Thread Metadata -->
          <div class="bg-surface border border-border rounded-xl p-5 shadow-xs space-y-3 text-xs">
            <h3 class="font-bold text-muted uppercase tracking-wider text-[11px]">Thread Info</h3>
            <div class="flex justify-between py-1 border-b border-border/60">
              <span class="text-muted">Thread ID</span>
              <span class="font-mono font-bold text-primary"><?= e($threadId) ?></span>
            </div>
            <div class="flex justify-between py-1 border-b border-border/60">
              <span class="text-muted">Status</span>
              <span id="threadMetaStatus" class="font-bold <?= $isClosed ? 'text-slate-500' : 'text-emerald-600' ?>"><?= e($thread['status'] ?? 'Open') ?></span>
            </div>
            <div class="flex justify-between py-1 border-b border-border/60">
              <span class="text-muted">Started By</span>
              <span class="font-medium text-textMain"><?= e($thread['created_by'] ?? 'Admin') ?></span>
            </div>
            <div class="flex justify-between py-1 border-b border-border/60">
              <span class="text-muted">Created</span>
              <span class="text-textMain"><?= date('M d, Y h:i A', strtotime($thread['created_at'] ?? 'now')) ?></span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-muted">Last Activity</span>
              <span id="threadMetaActivity" class="text-textMain"><?= date('M d, Y h:i A', strtotime($thread['updated_at'] ?? $thread['created_at'] ?? 'now')) ?></span>
            </div>
          </div>

          <!-- Info Box -->
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-800 space-y-1.5">
            <div class="font-bold flex items-center gap-1.5">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              Direct Messaging Policy
            </div>
            <p class="leading-relaxed text-[11px]">Only the District RSU Admin can initiate a message thread. Schools may reply in real-time. Close the thread to stop further school replies.</p>
          </div>

        </div>
      </div>

    </main>

    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>

<script>
const LSU_THREAD_ID = <?= json_encode($threadId) ?>;
const LSU_CURRENT_ROLE = 'admin';
const LSU_BASE = <?= json_encode(BASE_URL) ?>;

let maxReplyId = 0;
document.querySelectorAll('.msg-row').forEach(el => {
  const rid = parseInt(el.getAttribute('data-reply-id'), 10) || 0;
  if (rid > maxReplyId) maxReplyId = rid;
});

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

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
  });
}

function scrollToBottom() {
  const box = document.getElementById('chatBox');
  if (box) {
    box.scrollTop = box.scrollHeight;
  }
}

function appendMessageBubble(msg) {
  const box = document.getElementById('chatBox');
  if (!box) return;

  const emptyNotice = document.getElementById('emptyChatNotice');
  if (emptyNotice) emptyNotice.remove();

  const msgId = parseInt(msg.id || msg.message_id, 10) || 0;
  if (msgId > maxReplyId) maxReplyId = msgId;

  // Avoid duplicate bubble
  if (msgId && document.querySelector(`[data-reply-id="${msgId}"]`)) return;

  const isAdminMsg = (msg.sender_role === 'admin');
  const div = document.createElement('div');
  div.className = `flex ${isAdminMsg ? 'justify-end' : 'justify-start'} msg-row animate-in fade-in slide-in-from-bottom-2 duration-200`;
  if (msgId) div.setAttribute('data-reply-id', msgId);

  div.innerHTML = `
    <div class="max-w-[80%] space-y-1">
      <div class="flex items-center gap-1.5 ${isAdminMsg ? 'justify-end' : ''}">
        <div class="w-5 h-5 rounded-full ${isAdminMsg ? 'bg-primary text-white order-last' : 'bg-emerald-700 text-white'} flex items-center justify-center text-[9px] font-bold flex-shrink-0">
          ${isAdminMsg ? 'A' : 'S'}
        </div>
        <span class="text-[10px] font-semibold ${isAdminMsg ? 'text-primary' : 'text-emerald-700'}">${escapeHtml(msg.sender_name || '')}</span>
        <span class="text-[10px] text-muted">${escapeHtml(msg.time_fmt || 'Just now')}</span>
      </div>
      <div class="px-4 py-3 rounded-2xl text-xs leading-relaxed text-textMain whitespace-pre-line shadow-xs ${isAdminMsg ? 'msg-bubble-admin rounded-tr-sm' : 'msg-bubble-school rounded-tl-sm'}">
        ${escapeHtml(msg.message || '')}
      </div>
    </div>
  `;

  box.appendChild(div);
  scrollToBottom();
}

function setThreadClosedUI() {
  const sec = document.getElementById('replySection');
  if (sec && !document.getElementById('closedNoticeWrap')) {
    sec.innerHTML = `
      <div id="closedNoticeWrap" class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center text-xs text-slate-500 shadow-xs">
        <svg class="w-8 h-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        This conversation is <strong>Closed</strong>. No further messages can be sent until it is reopened.
      </div>
    `;
  }
  const statusMeta = document.getElementById('threadMetaStatus');
  if (statusMeta) {
    statusMeta.textContent = 'Closed';
    statusMeta.className = 'font-bold text-slate-500';
  }
}

// ── AJAX Real-Time Message Sending ──────────────────────────────────────────
const replyForm = document.getElementById('replyForm');
if (replyForm) {
  const msgInput = document.getElementById('messageInput');

  // Enter to send (Shift+Enter for new line)
  if (msgInput) {
    msgInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        replyForm.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });
  }

  replyForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const msgText = msgInput.value.trim();
    if (!msgText) return;

    const btn = document.getElementById('sendBtn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Sending…</span>`;

    const statusChange = document.getElementById('statusChangeSelect')?.value || '';
    const csrf = replyForm.querySelector('input[name="csrf_token"]').value;

    const fd = new FormData();
    fd.append('action', 'post_reply');
    fd.append('thread_id', LSU_THREAD_ID);
    fd.append('message', msgText);
    fd.append('status_change', statusChange);
    fd.append('csrf_token', csrf);

    fetch(`${LSU_BASE}/api/thread-messages.php`, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = origHtml;

      if (data.success) {
        msgInput.value = '';
        appendMessageBubble(data);
        if (data.thread_status === 'Closed') {
          setThreadClosedUI();
        }
      } else {
        showToast(data.error || 'Failed to send message', 'danger');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      showToast('Network error while sending message', 'danger');
    });
  });
}

// ── Real-Time Polling for Incoming Replies ──────────────────────────────────
function pollNewMessages() {
  fetch(`${LSU_BASE}/api/thread-messages.php?thread=${encodeURIComponent(LSU_THREAD_ID)}&after_id=${maxReplyId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (!data.success) return;

    if (data.messages && data.messages.length > 0) {
      let incomingCount = 0;
      data.messages.forEach(m => {
        const mId = parseInt(m.id, 10) || 0;
        if (mId && !document.querySelector(`[data-reply-id="${mId}"]`)) {
          appendMessageBubble(m);
          if (m.sender_role !== LSU_CURRENT_ROLE) {
            incomingCount++;
          }
        }
      });

      if (incomingCount > 0 && window.playNotificationChime) {
        window.playNotificationChime();
      }
    }

    if (data.thread_status === 'Closed') {
      setThreadClosedUI();
    }
  })
  .catch(() => {});
}

window.addEventListener('DOMContentLoaded', function() {
  scrollToBottom();
  // Poll every 2.5 seconds
  setInterval(pollNewMessages, 2500);
});
</script>
</body>
</html>
