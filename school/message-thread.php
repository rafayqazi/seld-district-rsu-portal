<?php
/**
 * school/message-thread.php — School Portal: Message Thread View & Reply
 *
 * School can read and reply to an admin-initiated message thread.
 * Cannot reply to closed threads.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'messages';
$threadId    = trim($_GET['thread'] ?? '');

if (empty($threadId)) {
    header('Location: ' . BASE_URL . '/school/messages.php');
    exit;
}

$thread = ExcelDB::find('admin_messages', 'thread_id', $threadId);

// Security: thread must belong to this school
if (!$thread || ($thread['semis_code'] ?? '') !== $school_semis) {
    header('Location: ' . BASE_URL . '/school/messages.php?error=unauthorized');
    exit;
}

// Mark as read for school
if (($thread['unread_school'] ?? '0') === '1') {
    ExcelDB::update('admin_messages', 'thread_id', $threadId, ['unread_school' => '0']);
    $thread['unread_school'] = '0';
}

$flash_success = '';
$flash_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $flash_error = 'Security validation failed. Please try again.';
    } elseif ($action === 'post_reply') {
        $message = trim($_POST['message'] ?? '');

        if (strtolower($thread['status'] ?? '') === 'closed') {
            $flash_error = 'This conversation has been closed by District RSU. No further replies can be sent.';
        } elseif (empty($message)) {
            $flash_error = 'Please type a message before sending.';
        } else {
            $senderName = $hm_name . ' (HM)';
            $success = ExcelDB::addMessageReply($threadId, 'school', $senderName, $message);
            if ($success) {
                $flash_success = 'Your reply has been sent to District RSU.';
                $thread = ExcelDB::find('admin_messages', 'thread_id', $threadId);
            } else {
                $flash_error = 'Failed to send message. Please try again.';
            }
        }
    }
}

$replies   = ExcelDB::getRepliesForThread($threadId);
$statusLow = strtolower($thread['status'] ?? 'open');
$isClosed  = $statusLow === 'closed';

$page_title = 'Thread ' . e($threadId) . ' — ' . e($school_name);
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
            primary: '#0F766E', primaryDark: '#0D625B',
            govNavy: '#123B63', 'govNavy-dark': '#0B2946',
            background: '#F8FAFC', surface: '#FFFFFF',
            textMain: '#1E293B', muted: '#64748B', border: '#E2E8F0',
            success: '#16A34A', warning: '#D97706', danger: '#DC2626',
          },
          fontFamily: { sans: ['Inter', 'sans-serif'] },
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar-link { transition: background .15s; }
    .sidebar-link.active { background: rgba(255,255,255,.14); border-left: 3px solid #34d399; }
    #sidebar { transition: transform .25s cubic-bezier(.4,0,.2,1); }
    #overlay { transition: opacity .25s; }
    .msg-bubble-admin { background: #EBF2FA; border: 1px solid #C3D5E8; }
    .msg-bubble-school { background: #F0FFF4; border: 1px solid #A7F3D0; }
    #chatBox { scroll-behavior: smooth; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col font-sans">

<div class="bg-govNavy-dark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0 transition-opacity duration-200" onclick="closeSidebar()"></div>

<div class="flex flex-1 min-h-screen">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0">
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="flex-1 p-4 md:p-6 lg:p-8 space-y-5 max-w-4xl mx-auto w-full">

      <!-- Top Nav -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2">
          <a href="<?= BASE_URL ?>/school/messages.php" class="p-1.5 rounded-lg bg-surface border border-border text-muted hover:text-textMain hover:bg-slate-50 transition">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
          </a>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-mono text-sm font-bold text-govNavy bg-govNavy/10 px-2 py-0.5 rounded"><?= e($threadId) ?></span>
              <?php if ($isClosed): ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border bg-slate-100 text-slate-600 border-slate-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Closed by RSU
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
        <div class="text-xs text-muted">
          Started by <strong class="text-textMain"><?= e($thread['created_by'] ?? 'District RSU') ?></strong>
          on <?= date('M d, Y', strtotime($thread['created_at'] ?? 'now')) ?>
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

      <!-- Chat Container -->
      <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-border bg-slate-50">
          <h3 class="font-bold text-xs text-textMain flex items-center gap-2">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-govNavy"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Conversation Thread
          </h3>
          <span class="text-[11px] text-muted font-mono bg-slate-100 px-2 py-0.5 rounded-full"><?= count($replies) ?> message<?= count($replies) !== 1 ? 's' : '' ?></span>
        </div>

        <div id="chatBox" class="p-5 space-y-4 max-h-[520px] overflow-y-auto">
          <?php if (empty($replies)): ?>
            <div class="text-center text-xs text-muted py-6">No messages in this thread yet.</div>
          <?php else: ?>
            <?php foreach ($replies as $r):
              $isAdminMsg = ($r['sender_role'] ?? '') === 'admin';
            ?>
              <div class="flex <?= $isAdminMsg ? 'justify-start' : 'justify-end' ?>">
                <div class="max-w-[80%] space-y-1">
                  <div class="flex items-center gap-1.5 <?= !$isAdminMsg ? 'justify-end' : '' ?>">
                    <div class="w-5 h-5 rounded-full <?= $isAdminMsg ? 'bg-govNavy text-white' : 'bg-primary text-white order-last' ?> flex items-center justify-center text-[9px] font-bold flex-shrink-0">
                      <?= $isAdminMsg ? 'R' : 'S' ?>
                    </div>
                    <span class="text-[10px] font-semibold <?= $isAdminMsg ? 'text-govNavy' : 'text-primary' ?>"><?= e($r['sender_name'] ?? '') ?></span>
                    <span class="text-[10px] text-muted"><?= date('M d, h:i A', strtotime($r['created_at'] ?? 'now')) ?></span>
                  </div>
                  <div class="px-4 py-3 rounded-2xl text-xs leading-relaxed text-textMain whitespace-pre-line <?= $isAdminMsg ? 'msg-bubble-admin rounded-tl-sm' : 'msg-bubble-school rounded-tr-sm' ?>">
                    <?= e($r['message'] ?? '') ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Reply Box or Closed Notice -->
      <?php if (!$isClosed): ?>
        <div class="bg-surface border border-border rounded-xl p-5 shadow-xs">
          <h3 class="font-bold text-xs text-textMain mb-3 flex items-center gap-2">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-primary"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send a Reply
          </h3>
          <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="post_reply">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <textarea name="message" rows="4" required placeholder="Type your reply to District RSU…" class="w-full px-3.5 py-2.5 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary text-textMain leading-relaxed resize-none"></textarea>

            <div class="flex justify-between items-center">
              <p class="text-[11px] text-muted">Only District RSU can close this conversation.</p>
              <button type="submit" class="px-5 py-2 bg-primary hover:bg-primaryDark text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center gap-2">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Send Reply
              </button>
            </div>
          </form>
        </div>
      <?php else: ?>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center text-xs text-slate-600">
          <svg class="w-7 h-7 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          This conversation has been <strong>Closed</strong> by District RSU. You cannot send further replies.
          <br>If you have a new issue, please <a href="<?= BASE_URL ?>/school/complaints.php" class="text-primary font-semibold underline">Lodge a Complaint</a>.
        </div>
      <?php endif; ?>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
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
window.addEventListener('DOMContentLoaded', function() {
  const box = document.getElementById('chatBox');
  if (box) box.scrollTop = box.scrollHeight;
});
</script>
</body>
</html>
