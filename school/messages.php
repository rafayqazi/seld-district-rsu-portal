<?php
/**
 * school/messages.php — School Portal: Received Messages Inbox
 *
 * Shows messages sent by District Admin to this school.
 * School can VIEW and REPLY only — cannot start new conversations.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'messages';
$page_title  = 'Messages — ' . e($school_name);

// Fetch threads addressed to this school
$allThreads = ExcelDB::all('admin_messages');
$myThreads  = array_values(array_filter($allThreads, fn($t) => ($t['semis_code'] ?? '') === $school_semis));

usort($myThreads, function($a, $b) {
    $uA = ($a['unread_school'] ?? '0') === '1' ? 1 : 0;
    $uB = ($b['unread_school'] ?? '0') === '1' ? 1 : 0;
    if ($uA !== $uB) return $uB <=> $uA;
    return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
});

$totalThreads  = count($myThreads);
$openThreads   = count(array_filter($myThreads, fn($t) => strtolower($t['status'] ?? '') === 'open'));
$unreadThreads = count(array_filter($myThreads, fn($t) => ($t['unread_school'] ?? '0') === '1'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Messages from District RSU — School Portal">
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
    .sidebar-link:hover { background: rgba(255,255,255,.08); }
    .sidebar-link.active { background: rgba(255,255,255,.14); border-left: 3px solid #34d399; }
    #sidebar { transition: transform .25s cubic-bezier(.4,0,.2,1); }
    #overlay { transition: opacity .25s; }
    .thread-row:hover { background: #f8fafc; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

<div class="bg-govNavy-dark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

<div class="flex flex-1 overflow-hidden">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">

      <!-- Page Header -->
      <div>
        <h1 class="text-xl font-bold text-textMain flex items-center gap-2">
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-primary">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
          </svg>
          Messages from District RSU
        </h1>
        <p class="text-xs text-muted mt-0.5">View and reply to official messages sent by the District RSU Coordinator. You cannot initiate new messages.</p>
      </div>

      <!-- Info Banner: Cannot initiate -->
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-800 flex items-start gap-2.5">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5 text-blue-500"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
          <strong>Information:</strong> Only the District RSU can start a new conversation. You may reply to any open thread. To raise a new issue, please use the <a href="<?= BASE_URL ?>/school/complaints.php" class="underline font-semibold">Complaints / Grievance</a> section.
        </div>
      </div>

      <!-- Metrics -->
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-surface rounded-xl border border-border p-4 shadow-xs text-center">
          <p class="text-xs text-muted mb-1">Total Messages</p>
          <p class="text-2xl font-bold text-govNavy"><?= $totalThreads ?></p>
        </div>
        <div class="bg-surface rounded-xl border border-border p-4 shadow-xs text-center">
          <p class="text-xs text-muted mb-1">Open</p>
          <p class="text-2xl font-bold text-emerald-600"><?= $openThreads ?></p>
        </div>
        <div class="bg-surface rounded-xl border border-border p-4 shadow-xs text-center">
          <p class="text-xs text-muted mb-1">Unread</p>
          <p class="text-2xl font-bold text-amber-600"><?= $unreadThreads ?></p>
        </div>
      </div>

      <!-- Thread List -->
      <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
        <?php if (empty($myThreads)): ?>
          <div class="p-12 text-center">
            <div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
              <svg width="24" height="24" fill="none" stroke="#0F766E" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            </div>
            <p class="text-sm font-semibold text-textMain mb-1">No messages yet</p>
            <p class="text-xs text-muted">When District RSU sends you a message, it will appear here.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-slate-50 border-b border-border text-xs text-muted uppercase tracking-wide">
                  <th class="text-left px-4 py-3 font-semibold">Thread</th>
                  <th class="text-left px-4 py-3 font-semibold">Subject</th>
                  <th class="text-left px-4 py-3 font-semibold">Status</th>
                  <th class="text-left px-4 py-3 font-semibold">Last Activity</th>
                  <th class="text-right px-4 py-3 font-semibold">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <?php foreach ($myThreads as $t):
                  $isUnread  = ($t['unread_school'] ?? '0') === '1';
                  $statusLow = strtolower($t['status'] ?? 'open');
                ?>
                <tr class="thread-row <?= $isUnread ? 'bg-amber-50/40' : '' ?> transition-colors">
                  <td class="px-4 py-3.5">
                    <div class="flex items-center gap-2">
                      <?php if ($isUnread): ?>
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse flex-shrink-0"></span>
                      <?php else: ?>
                        <span class="w-2 h-2 flex-shrink-0"></span>
                      <?php endif; ?>
                      <span class="font-mono text-xs font-bold text-govNavy bg-govNavy/10 px-2 py-0.5 rounded"><?= e($t['thread_id']) ?></span>
                    </div>
                  </td>
                  <td class="px-4 py-3.5 max-w-xs">
                    <div class="text-xs font-semibold text-textMain truncate"><?= e($t['subject']) ?></div>
                    <div class="text-[11px] text-muted mt-0.5">From: <?= e($t['created_by']) ?></div>
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
                  <td class="px-4 py-3.5 text-right">
                    <a href="<?= BASE_URL ?>/school/message-thread.php?thread=<?= urlencode($t['thread_id']) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary/20 text-[11px] font-semibold rounded-lg transition">
                      <?= $isUnread ? '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>' : '' ?>
                      <?= $statusLow === 'open' ? 'View & Reply' : 'View' ?>
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
</script>
</body>
</html>
