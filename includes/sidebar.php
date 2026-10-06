<?php
/**
 * includes/sidebar.php
 *
 * Reusable sidebar navigation partial.
 *
 * Required variables (set before including):
 *   $active_page (string) — One of: 'dashboard', 'schools', 'school-profile',
 *                           'at-risk-schools', 'students', 'settings'
 *
 * Usage:
 *   $active_page = 'dashboard';
 *   require_once dirname(__DIR__) . '/includes/sidebar.php';
 */

// Base path for admin pages (relative to web root)
$base = BASE_URL . '/admin/';

// Helper: returns CSS classes for active/inactive sidebar links
function sidebar_link_class(string $page, string $active): string {
    if ($page === $active) {
        return 'sidebar-link active flex items-center gap-3 px-3 py-2 rounded text-white text-sm font-medium';
    }
    return 'sidebar-link flex items-center gap-3 px-3 py-2 rounded text-white/80 text-sm hover:text-white';
}
?>
<aside id="sidebar" class="fixed md:relative top-0 left-0 h-full md:h-auto z-40 w-64 bg-primary flex flex-col min-h-screen -translate-x-full md:translate-x-0" aria-label="Sidebar Navigation">

  <!-- Sidebar Header -->
  <div class="flex items-center gap-3 px-4 py-4 border-b border-white/10">
    <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
    </div>
    <div class="leading-tight">
      <div class="text-white font-semibold text-sm">District RSU</div>
      <div class="text-white/60 text-xs">Education Portal</div>
    </div>
    <button onclick="closeSidebar()" class="ml-auto md:hidden text-white/60 hover:text-white" aria-label="Close sidebar">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">

    <!-- Dashboard -->
    <div class="px-2 pt-2 pb-1 sidebar-group-title text-white/40">Dashboard</div>
    <a href="<?= $base ?>dashboard.php" class="<?= sidebar_link_class('dashboard', $active_page) ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
        <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
      </svg>
      Overview
    </a>

    <!-- Schools -->
    <div class="px-2 pt-3 pb-1 sidebar-group-title text-white/40">Schools</div>
    <a href="<?= $base ?>schools.php" class="<?= sidebar_link_class('schools', $active_page) ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      School Directory
    </a>
    <a href="<?= $base ?>at-risk-schools.php" class="<?= sidebar_link_class('at-risk-schools', $active_page) ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      At-Risk Schools
    </a>

    <!-- Teachers & Staff -->
    <div class="px-2 pt-3 pb-1 sidebar-group-title text-white/40">Teachers &amp; Staff</div>
    <a href="<?= $base ?>staff.php" class="<?= sidebar_link_class('staff', $active_page) ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
      Staff Directory
    </a>

    <!-- Monitoring -->
    <div class="px-2 pt-3 pb-1 sidebar-group-title text-white/40">Monitoring</div>
    <a href="#" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded text-white/80 text-sm hover:text-white opacity-60 cursor-not-allowed">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      Field Visits
    </a>
    <a href="#" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded text-white/80 text-sm hover:text-white opacity-60 cursor-not-allowed">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
      </svg>
      Classroom Observation
    </a>

    <!-- Complaints / Redress -->
    <?php $admin_unread_complaints = ExcelDB::getUnreadComplaintsCount('admin'); ?>
    <?php $admin_unread_messages  = ExcelDB::getUnreadMessagesCount('admin'); ?>
    <div class="px-2 pt-3 pb-1 sidebar-group-title text-white/40">Complaints / Redress</div>
    <a href="<?= $base ?>complaints.php" class="<?= sidebar_link_class('complaints', $active_page) ?> justify-between">
      <div class="flex items-center gap-3">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        <span>Complaints</span>
      </div>
      <span class="complaints-badge-count <?= ($admin_unread_complaints > 0 ? '' : 'hidden') ?> px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white">
        <?= $admin_unread_complaints ?>
      </span>
    </a>
    <a href="<?= $base ?>messages.php" class="<?= sidebar_link_class('messages', $active_page) ?> justify-between">
      <div class="flex items-center gap-3">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
          <line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="12" y2="13"/>
        </svg>
        <span>Direct Messages</span>
      </div>
      <span class="messages-badge-count <?= ($admin_unread_messages > 0 ? '' : 'hidden') ?> px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500 text-white">
        <?= $admin_unread_messages ?>
      </span>
    </a>

    <!-- Reports -->
    <div class="px-2 pt-3 pb-1 sidebar-group-title text-white/40">Reports</div>
    <a href="#" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded text-white/80 text-sm hover:text-white opacity-60 cursor-not-allowed">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
      </svg>
      District Reports
    </a>

    <!-- Documents -->
    <div class="px-2 pt-3 pb-1 sidebar-group-title text-white/40">Documents</div>
    <a href="#" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded text-white/80 text-sm hover:text-white opacity-60 cursor-not-allowed">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
      </svg>
      Documents
    </a>

    <!-- Account -->
    <div class="px-2 pt-3 pb-1 sidebar-group-title text-white/40">Account &amp; Setup</div>
    <a href="<?= $base ?>settings.php" class="<?= sidebar_link_class('settings', $active_page) ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
      </svg>
      Settings &amp; Profile
    </a>
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded text-white/80 text-sm hover:text-white hover:bg-red-900/20">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Logout
    </a>

  </nav>

  <!-- Sidebar Footer (Clean, clutter-free) -->
  <div class="px-4 py-3 border-t border-white/10 text-white/40 text-xs flex items-center justify-between">
    <div class="font-medium text-white/70"><?= APP_NAME ?></div>
    <div class="text-[11px] text-emerald-400 font-medium flex items-center gap-1">
      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online
    </div>
  </div>


</aside>
