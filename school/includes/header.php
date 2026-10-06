<?php
/**
 * school/includes/header.php
 *
 * Top navigation bar partial for the School Portal.
 */

$session_avatar = $school_logo ?? ($_SESSION['lsu_avatar'] ?? '');
$has_avatar = !empty($session_avatar) && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $session_avatar));
$hm_initial = strtoupper(substr($hm_name, 0, 1));
?>
<header class="bg-surface border-b border-border px-4 md:px-6 py-3 flex items-center justify-between sticky top-0 z-20 shadow-xs">

  <!-- Mobile menu toggle -->
  <div class="flex items-center gap-3">
    <button onclick="openSidebar()" class="md:hidden text-muted hover:text-primary p-1 rounded-md" aria-label="Open navigation menu">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <!-- School Identification -->
    <div class="flex items-center gap-3">
      <div class="hidden sm:flex w-9 h-9 rounded-lg bg-emerald-700 items-center justify-center text-white shadow-xs">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </div>
      <div>
        <div class="font-bold text-textMain text-sm leading-tight flex items-center gap-2">
          <span><?= e($school_name) ?></span>
          <span class="font-mono text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded font-semibold">
            SEMIS: <?= e($school_semis) ?>
          </span>
        </div>
        <div class="text-xs text-muted mt-0.5 flex items-center gap-2">
          <span><?= e($school_taluka) ?> Taluka</span>
          <span class="text-border">&bull;</span>
          <span><?= e($school_level) ?> (<?= e($school_gender) ?>)</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Actions: Head Master Profile & Actions -->
  <div class="flex items-center gap-3">

    <!-- District RSU SELD Badge -->
    <div class="hidden lg:flex items-center gap-2 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-md text-[11px] text-slate-600">
      <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
      <span>SELD Sindh &bull; <?= APP_DISTRICT ?></span>
    </div>

    <!-- Notification Bell -->
    <?php 
      $school_unread_complaints = ExcelDB::getUnreadComplaintsCount('school', $school_semis);
      $school_unread_messages   = ExcelDB::getUnreadMessagesCount('school', $school_semis);
      $school_total_unread      = $school_unread_complaints + $school_unread_messages;

      $allMyMessages = array_filter(ExcelDB::all('admin_messages'), fn($m) => trim($m['semis_code'] ?? '') === trim($school_semis));
      $unreadMsgList = array_values(array_filter($allMyMessages, fn($m) => ($m['unread_school'] ?? '0') === '1'));
      usort($unreadMsgList, fn($a, $b) => strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? ''));

      $allMyComplaints = array_filter(ExcelDB::all('complaints'), fn($c) => trim($c['semis_code'] ?? '') === trim($school_semis));
      $unreadCmpList   = array_values(array_filter($allMyComplaints, fn($c) => ($c['unread_school'] ?? '0') === '1'));
      usort($unreadCmpList, fn($a, $b) => strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? ''));
    ?>
    <div class="relative" id="notif-wrapper">
      <button id="notif-btn" onclick="toggleNotif()" class="relative text-muted hover:text-emerald-700 p-1.5 rounded-lg hover:bg-emerald-50 transition block" title="Notifications">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <span class="header-unread-dot complaints-header-dot absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center border-2 border-surface <?= $school_total_unread > 0 ? '' : 'hidden' ?>">
          <span class="header-unread-count complaints-header-count"><?= $school_total_unread ?></span>
        </span>
      </button>

      <!-- Notification Dropdown -->
      <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-surface border border-border rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="px-4 py-3 border-b border-border bg-slate-50 flex items-center justify-between">
          <span class="font-bold text-xs text-textMain">Notifications</span>
          <span class="header-unread-count complaints-header-count bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full <?= $school_total_unread > 0 ? '' : 'hidden' ?>">
            <?= $school_total_unread ?> New
          </span>
        </div>
        <div class="divide-y divide-border max-h-72 overflow-y-auto">

          <?php if (!empty($unreadMsgList)): ?>
            <?php foreach ($unreadMsgList as $um): ?>
              <a href="<?= BASE_URL ?>/school/message-thread.php?thread=<?= urlencode($um['thread_id'] ?? '') ?>" class="flex items-start gap-3 px-4 py-3 bg-blue-50/40 hover:bg-blue-50 transition group">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5 text-blue-600">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="12" y2="13"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wide">Direct Message</span>
                    <span class="font-mono text-[9px] text-blue-600 bg-blue-100 px-1 py-0.2 rounded"><?= e($um['thread_id'] ?? '') ?></span>
                  </div>
                  <div class="text-xs font-semibold text-textMain truncate mt-0.5 group-hover:text-blue-800"><?= e($um['subject'] ?? '') ?></div>
                  <div class="text-[10px] text-muted mt-0.5">From <?= e($um['created_by'] ?? 'District RSU') ?> &bull; <?= date('M d, h:i A', strtotime($um['updated_at'] ?? $um['created_at'] ?? 'now')) ?></div>
                </div>
                <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-1.5 animate-pulse"></span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php if (!empty($unreadCmpList)): ?>
            <?php foreach ($unreadCmpList as $uc): ?>
              <a href="<?= BASE_URL ?>/school/complaint-details.php?ticket=<?= urlencode($uc['ticket_no'] ?? '') ?>" class="flex items-start gap-3 px-4 py-3 bg-emerald-50/40 hover:bg-emerald-50 transition group">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 mt-0.5 text-emerald-600">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wide">Complaint Update</span>
                    <span class="font-mono text-[9px] text-emerald-600 bg-emerald-100 px-1 py-0.2 rounded"><?= e($uc['ticket_no'] ?? '') ?></span>
                  </div>
                  <div class="text-xs font-semibold text-textMain truncate mt-0.5 group-hover:text-emerald-800"><?= e($uc['subject'] ?? '') ?></div>
                  <div class="text-[10px] text-muted mt-0.5">Status: <strong class="text-emerald-700"><?= e($uc['status'] ?? '') ?></strong> &bull; <?= date('M d, h:i A', strtotime($uc['updated_at'] ?? $uc['created_at'] ?? 'now')) ?></div>
                </div>
                <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0 mt-1.5 animate-pulse"></span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php if (empty($unreadMsgList) && empty($unreadCmpList)): ?>
            <div class="px-4 py-6 text-center text-xs text-muted">
              <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
              No unread notifications
            </div>
          <?php endif; ?>

        </div>
        <div class="px-4 py-2.5 border-t border-border bg-slate-50 flex items-center justify-between text-xs font-semibold">
          <a href="<?= BASE_URL ?>/school/messages.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <span>Direct Messages</span>
            <span class="messages-badge-count <?= ($school_unread_messages > 0 ? '' : 'hidden') ?> bg-blue-100 text-blue-700 text-[10px] px-1.5 py-0.2 rounded-full"><?= $school_unread_messages ?></span>
          </a>
          <span class="text-border">|</span>
          <a href="<?= BASE_URL ?>/school/complaints.php" class="text-emerald-600 hover:text-emerald-800 flex items-center gap-1">
            <span>Complaints</span>
            <span class="complaints-badge-count <?= ($school_unread_complaints > 0 ? '' : 'hidden') ?> bg-emerald-100 text-emerald-700 text-[10px] px-1.5 py-0.2 rounded-full"><?= $school_unread_complaints ?></span>
          </a>
        </div>
      </div>
    </div>


    <!-- Head Master Profile link -->
    <a href="<?= BASE_URL ?>/school/settings.php" class="flex items-center gap-2.5 pl-2 border-l border-border hover:opacity-85 transition" title="School Account & Password Settings">
      <?php if ($has_avatar): ?>
        <img src="<?= e($session_avatar) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-emerald-600/30 flex-shrink-0 shadow-xs"/>
      <?php else: ?>
        <div class="w-8 h-8 rounded-full bg-emerald-700 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-xs">
          <?= $hm_initial ?>
        </div>
      <?php endif; ?>
      <div class="hidden sm:block leading-tight text-left">
        <div class="text-xs font-bold text-textMain hover:text-emerald-700 transition-colors truncate max-w-[140px]"><?= e($hm_name) ?></div>
        <div class="text-[10px] text-muted font-mono">CNIC: <?= e($hm_cnic) ?></div>
      </div>
    </a>

    <!-- Logout -->
    <a href="<?= BASE_URL ?>/logout.php" class="text-muted hover:text-danger p-1.5 rounded-md hover:bg-red-50 transition text-xs flex items-center gap-1" title="Logout">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      <span class="hidden md:inline">Logout</span>
    </a>

  </div>

</header>

<script>
  window.LSU_BASE_URL = '<?= BASE_URL ?>';
  window.LSU_SCHOOL_SEMIS = '<?= e($school_semis) ?>';

  function toggleNotif() {
    const d = document.getElementById('notif-dropdown');
    if (d) d.classList.toggle('hidden');
  }

  document.addEventListener('click', function(e) {
    const btn = document.getElementById('notif-btn');
    const dd  = document.getElementById('notif-dropdown');
    if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) {
      dd.classList.add('hidden');
    }
  });
</script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
