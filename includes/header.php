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
  <div class="relative">
    <button id="notif-btn" onclick="toggleNotif()" class="relative text-muted hover:text-primary p-1.5 rounded hover:bg-background" aria-label="Notifications">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 01-3.46 0"/>
      </svg>
      <span class="absolute top-0.5 right-0.5 w-2 h-2 bg-danger rounded-full"></span>
    </button>
    <!-- Notification Dropdown -->
    <div id="notif-dropdown" class="hidden absolute right-0 top-10 w-80 bg-surface border border-border rounded-lg shadow-lg z-50 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-border">
        <span class="font-semibold text-sm text-textMain">Notifications</span>
        <span class="text-xs text-secondary font-medium cursor-pointer hover:underline">Mark all read</span>
      </div>
      <div class="divide-y divide-border max-h-72 overflow-y-auto">
        <div class="px-4 py-3 hover:bg-background flex gap-3 cursor-pointer">
          <div class="w-2 h-2 mt-1.5 rounded-full bg-primary flex-shrink-0"></div>
          <div>
            <div class="text-sm font-medium text-textMain">New Monitoring Report</div>
            <div class="text-xs text-muted mt-0.5">GPS Ranipur submitted a monitoring report.</div>
            <div class="text-xs text-muted mt-1">10 minutes ago</div>
          </div>
        </div>
        <div class="px-4 py-3 hover:bg-background flex gap-3 cursor-pointer">
          <div class="w-2 h-2 mt-1.5 rounded-full bg-warning flex-shrink-0"></div>
          <div>
            <div class="text-sm font-medium text-textMain">Attendance Alert</div>
            <div class="text-xs text-muted mt-0.5">12 students marked absent — GGSS Tando Allahyar.</div>
            <div class="text-xs text-muted mt-1">25 minutes ago</div>
          </div>
        </div>
        <div class="px-4 py-3 hover:bg-background flex gap-3 cursor-pointer">
          <div class="w-2 h-2 mt-1.5 rounded-full bg-success flex-shrink-0"></div>
          <div>
            <div class="text-sm font-medium text-textMain">Complaint Resolved</div>
            <div class="text-xs text-muted mt-0.5">Complaint #GRM-1024 was resolved.</div>
            <div class="text-xs text-muted mt-1">1 hour ago</div>
          </div>
        </div>
      </div>
      <div class="px-4 py-2.5 border-t border-border text-center">
        <span class="text-xs text-primary font-medium cursor-pointer hover:underline">View all notifications</span>
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

