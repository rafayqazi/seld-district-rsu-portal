<?php
/**
 * includes/header.php
 *
 * Top navigation bar partial.
 * Displays portal logo, search bar, notifications, and user info from session.
 *
 * Required: session must be active (auth.php already starts it)
 * Optional variable: $page_title (string) — used in <title> tag
 */

$session_username = isset($_SESSION['lsu_username']) ? e($_SESSION['lsu_username']) : 'Admin';
$session_role     = isset($_SESSION['lsu_role'])     ? e($_SESSION['lsu_role'])     : 'Administrator';
$session_initial  = strtoupper(substr($session_username, 0, 1));
?>
<header class="bg-surface border-b border-border px-4 md:px-6 py-3 flex items-center gap-3 sticky top-0 z-20 shadow-sm">

  <!-- Mobile menu toggle -->
  <button onclick="openSidebar()" class="md:hidden text-muted hover:text-primary flex-shrink-0" aria-label="Open navigation menu">
    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <line x1="3" y1="6" x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>

  <!-- Logo + Portal Title -->
  <div class="flex items-center gap-2.5 flex-shrink-0">
    <div class="w-8 h-8 rounded bg-primary flex items-center justify-center">
      <svg width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
      </svg>
    </div>
    <div class="hidden sm:block leading-tight">
      <div class="font-semibold text-primary text-sm">DISTRICT RSU</div>
      <div class="text-muted text-xs"><?= APP_DISTRICT ?></div>
    </div>
  </div>

  <!-- Global Search -->
  <div class="flex-1 max-w-sm ml-2 hidden sm:block relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input id="global-search" type="search" placeholder="Search schools, students, staff…"
           class="w-full pl-9 pr-4 py-2 text-sm border border-border rounded bg-background text-textMain placeholder-muted focus:border-primary focus:outline-none"
           aria-label="Global search"/>
  </div>

  <div class="flex-1"></div>

  <!-- Notifications -->
  <?php 
    $header_unread_complaints = ExcelDB::getUnreadComplaintsCount('admin');
    $allComplaintsList = ExcelDB::all('complaints');
    usort($allComplaintsList, fn($a, $b) => strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? ''));
    $recentNotifs = array_slice($allComplaintsList, 0, 4);
  ?>
  <div class="relative">
    <button id="notif-btn" onclick="toggleNotif()" class="relative text-muted hover:text-primary p-1.5 rounded hover:bg-background" aria-label="Notifications">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 01-3.46 0"/>
      </svg>
      <span class="complaints-header-dot absolute top-0.5 right-0.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-surface <?= $header_unread_complaints > 0 ? '' : 'hidden' ?>"></span>
    </button>
    <!-- Notification Dropdown -->
    <div id="notif-dropdown" class="hidden absolute right-0 top-10 w-80 bg-surface border border-border rounded-xl shadow-xl z-50 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-border bg-slate-50/50">
        <div class="flex items-center gap-1.5">
          <span class="font-bold text-xs text-textMain">Complaints &amp; Alerts</span>
          <span class="complaints-header-count text-[10px] bg-red-100 text-red-700 font-bold px-1.5 py-0.5 rounded-full <?= $header_unread_complaints > 0 ? '' : 'hidden' ?>">
            <?= $header_unread_complaints ?> New
          </span>
        </div>
        <button onclick="window.playNotificationChime()" class="text-[11px] text-secondary hover:underline flex items-center gap-1">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
          <span>Test Sound</span>
        </button>
      </div>
      <div class="divide-y divide-border max-h-72 overflow-y-auto text-xs">
        <?php if (empty($recentNotifs)): ?>
          <div class="p-4 text-center text-muted text-xs">No notifications recorded yet.</div>
        <?php else: ?>
          <?php foreach ($recentNotifs as $notif): 
            $isUnreadNotif = ($notif['unread_admin'] ?? '0') === '1' || strtolower($notif['status'] ?? '') === 'pending';
          ?>
            <a href="<?= BASE_URL ?>/admin/complaint-details.php?ticket=<?= urlencode($notif['ticket_no'] ?? '') ?>" class="px-4 py-3 hover:bg-background flex gap-3 cursor-pointer block transition <?= $isUnreadNotif ? 'bg-amber-50/30' : '' ?>">
              <div class="w-2 h-2 mt-1.5 rounded-full <?= $isUnreadNotif ? 'bg-red-500' : 'bg-slate-300' ?> flex-shrink-0"></div>
              <div class="overflow-hidden">
                <div class="text-xs font-semibold text-textMain truncate"><?= e($notif['school_name'] ?? 'School') ?></div>
                <div class="text-[11px] text-muted truncate mt-0.5"><?= e($notif['subject'] ?? '') ?></div>
                <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-2">
                  <span class="font-mono"><?= e($notif['ticket_no'] ?? '') ?></span>
                  <span>&bull;</span>
                  <span><?= date('M d, H:i', strtotime($notif['updated_at'] ?? $notif['created_at'] ?? 'now')) ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="px-4 py-2.5 border-t border-border text-center bg-slate-50/50">
        <a href="<?= BASE_URL ?>/admin/complaints.php" class="text-xs text-primary font-bold hover:underline">View All Grievances &rarr;</a>
      </div>
    </div>
  </div>

  <!-- User Profile (Links to Settings) -->
  <?php
    $session_avatar = $_SESSION['lsu_avatar'] ?? '';
    $has_avatar = !empty($session_avatar) && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $session_avatar));
  ?>
  <div class="flex items-center gap-2.5 pl-2 border-l border-border ml-1">
    <a href="<?= BASE_URL ?>/admin/settings.php" class="flex items-center gap-2 hover:opacity-85 transition" title="Edit Profile &amp; Settings">
      <?php if ($has_avatar): ?>
        <img src="<?= e($session_avatar) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-primary/20 flex-shrink-0 shadow-xs"/>
      <?php else: ?>
        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
          <?= $session_initial ?>
        </div>
      <?php endif; ?>
      <div class="hidden md:block leading-tight text-left">
        <div class="text-sm font-semibold text-textMain hover:text-primary transition-colors"><?= $session_username ?></div>
        <div class="text-xs text-muted"><?= $session_role ?></div>
      </div>
    </a>
    <a href="<?= BASE_URL ?>/logout.php" class="ml-1 hidden md:flex items-center text-muted hover:text-danger text-xs gap-1 px-2 py-1 rounded hover:bg-red-50 transition" title="Logout">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Logout
    </a>
  </div>

</header>

<script>
  window.LSU_BASE_URL = '<?= BASE_URL ?>';
  function toggleNotif() {
    const dropdown = document.getElementById('notif-dropdown');
    if (dropdown) dropdown.classList.toggle('hidden');
  }
  document.addEventListener('click', function(e) {
    const btn = document.getElementById('notif-btn');
    const dropdown = document.getElementById('notif-dropdown');
    if (btn && dropdown && !btn.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.add('hidden');
    }
  });
</script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>


