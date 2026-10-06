<?php
/**
 * school/includes/sidebar.php
 *
 * Dedicated sidebar navigation for School Portal (Head Master / Mistress role).
 *
 * Required variable:
 *   $active_page (string) — One of: 'dashboard', 'profile', 'students', 'attendance', 'at-risk', 'settings'
 */

$base = BASE_URL . '/school/';

function school_sidebar_link(string $page, string $active): string {
    if ($page === $active) {
        return 'sidebar-link active flex items-center gap-3 px-3 py-2.5 rounded-lg text-white text-sm font-semibold bg-emerald-600/30 border-l-4 border-emerald-400';
    }
    return 'sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/80 text-sm hover:text-white hover:bg-white/10 transition-colors';
}
?>
<aside id="sidebar" class="fixed md:relative top-0 left-0 h-full md:h-auto z-40 w-64 bg-govNavy flex flex-col min-h-screen -translate-x-full md:translate-x-0 shadow-xl" aria-label="School Navigation">

  <!-- School Profile / Branding Header -->
  <div class="px-4 py-4 border-b border-white/10 bg-govNavy-dark/60">
    <div class="flex items-start gap-3">
      <?php if (!empty($school_logo) && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $school_logo))): ?>
        <img src="<?= e($school_logo) ?>" alt="School Logo" class="w-10 h-10 rounded-lg object-cover border border-emerald-400/30 flex-shrink-0 bg-white shadow-sm"/>
      <?php else: ?>
        <div class="w-10 h-10 rounded-lg bg-emerald-700/80 border border-emerald-400/40 flex items-center justify-center text-white font-bold text-base flex-shrink-0 shadow-inner">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
          </svg>
        </div>
      <?php endif; ?>
      <div class="leading-tight overflow-hidden">
        <div class="text-white font-bold text-sm truncate" title="<?= e($school_name) ?>"><?= e($school_name) ?></div>
        <div class="flex items-center gap-1.5 mt-1">
          <span class="font-mono text-[11px] bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded border border-emerald-500/30">
            SEMIS: <?= e($school_semis) ?>
          </span>
        </div>
      </div>
      <button onclick="closeSidebar()" class="ml-auto md:hidden text-white/60 hover:text-white" aria-label="Close sidebar">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Role Badge -->
  <div class="px-4 py-2 bg-emerald-950/40 border-b border-white/5 flex items-center justify-between text-[11px]">
    <span class="text-emerald-400 font-medium flex items-center gap-1.5">
      <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
      School Head Portal
    </span>
    <span class="text-white/40"><?= e($school_taluka) ?></span>
  </div>

  <!-- Navigation Links -->
  <nav class="flex-1 overflow-y-auto py-3 px-3 space-y-1">

    <div class="px-2 pt-2 pb-1 text-[10px] tracking-wider font-bold uppercase text-white/40">Core Operations</div>

    <!-- 1. School Dashboard -->
    <a href="<?= $base ?>dashboard.php" class="<?= school_sidebar_link('dashboard', $active_page) ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
        <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
      </svg>
      <span>School Dashboard</span>
    </a>

    <!-- 2. School Profile & Facilities -->
    <a href="<?= $base ?>profile.php" class="<?= school_sidebar_link('profile', $active_page) ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      <span>School Profile &amp; Facilities</span>
    </a>

    <!-- 3. School Staff Details -->
    <a href="<?= $base ?>staff.php" class="<?= school_sidebar_link('staff', $active_page) ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
      <span>School Staff Details</span>
    </a>

    <!-- 5. Grievances & Complaints -->
    <?php $school_unread_complaints = ExcelDB::getUnreadComplaintsCount('school', $school_semis); ?>
    <a href="<?= $base ?>complaints.php" class="<?= school_sidebar_link('complaints', $active_page) ?> justify-between">
      <div class="flex items-center gap-3">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        <span>Grievances &amp; Complaints</span>
      </div>
      <span class="complaints-badge-count <?= ($school_unread_complaints > 0 ? '' : 'hidden') ?> px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white animate-pulse">
        <?= $school_unread_complaints ?>
      </span>
    </a>

    <!-- 6. Direct Messages from RSU -->
    <?php $school_unread_messages = ExcelDB::getUnreadMessagesCount('school', $school_semis); ?>
    <a href="<?= $base ?>messages.php" class="<?= school_sidebar_link('messages', $active_page) ?> justify-between">
      <div class="flex items-center gap-3">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
          <line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="12" y2="13"/>
        </svg>
        <span>Messages from RSU</span>
      </div>
      <span class="messages-badge-count <?= ($school_unread_messages > 0 ? '' : 'hidden') ?> px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500 text-white animate-pulse">
        <?= $school_unread_messages ?>
      </span>
    </a>


    <div class="px-2 pt-4 pb-1 text-[10px] tracking-wider font-bold uppercase text-white/40">Account &amp; Security</div>

    <!-- 6. Settings & Password -->
    <a href="<?= $base ?>settings.php" class="<?= school_sidebar_link('settings', $active_page) ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
      </svg>
      <span>Settings &amp; Change Password</span>
    </a>

    <?php if (is_admin()): ?>
      <div class="px-2 pt-3 pb-1 text-[10px] tracking-wider font-bold uppercase text-amber-300">Admin Control</div>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-amber-200 hover:bg-amber-400/20 text-xs font-semibold">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
        Return to District Admin
      </a>
    <?php endif; ?>

    <!-- 7. Logout -->
    <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:text-white hover:bg-red-900/30 text-sm transition-colors mt-2">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      <span>Logout</span>
    </a>

  </nav>

  <!-- Sidebar Footer -->
  <div class="px-4 py-3 border-t border-white/10 bg-govNavy-dark/80 text-white/50 text-[11px] flex items-center justify-between">
    <div>
      <div class="font-medium text-white/80 truncate max-w-[130px]"><?= e($hm_name) ?></div>
      <div class="text-[10px] font-mono text-emerald-400"><?= e($hm_cnic) ?></div>
    </div>
    <div class="flex items-center gap-1 text-emerald-400 text-[10px]">
      <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Online
    </div>
  </div>

</aside>
