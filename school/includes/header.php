<?php
/**
 * school/includes/header.php
 *
 * Top navigation bar partial for the School Portal.
 */

$session_avatar = $school_logo ?? ($_SESSION['lsu_avatar'] ?? '');
$has_avatar = !empty($session_avatar) && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $session_avatar));
$hm_initial = strtoupper(substr($hm_name, 0, 1));
$school_announcements = [];
if (($_SESSION['lsu_login_type'] ?? '') === 'school' && !empty($_SESSION['lsu_login_instance'])) {
  $school_announcements = array_values(array_filter(ExcelDB::all('announcements'), fn($announcement) =>
    ($announcement['status'] ?? '') === 'Active'
    && (($announcement['target_type'] ?? '') === 'school'
      ? trim((string)($announcement['target_value'] ?? '')) === trim((string)$school_semis)
      : (($announcement['target_type'] ?? '') === 'level'
        && trim((string)($announcement['target_value'] ?? '')) === trim((string)$school_level)))
  ));
}
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
      $allSchoolFormAssignments = array_values(array_filter(ExcelDB::all('form_assignments'), fn($a) => trim((string)($a['semis_code'] ?? '')) === trim($school_semis)));
      $school_unread_forms = count(array_filter($allSchoolFormAssignments, fn($a) => ($a['unread_school'] ?? '0') === '1'));
      $school_total_unread      = $school_unread_complaints + $school_unread_messages + $school_unread_forms;

      $formRecordsById = [];
      foreach (ExcelDB::all('forms') as $formRecord) $formRecordsById[$formRecord['form_id'] ?? ''] = $formRecord;
      $unreadFormList = array_values(array_filter($allSchoolFormAssignments, fn($a) =>
        ($a['unread_school'] ?? '0') === '1' && isset($formRecordsById[$a['form_id'] ?? ''])
      ));

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
        <span class="notification-count-badge header-unread-dot absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-600 text-white text-[10px] leading-none font-bold rounded-full border-2 border-surface inline-flex items-center justify-center <?= $school_total_unread > 0 ? '' : 'hidden' ?>" style="<?= $school_total_unread > 0 ? '' : 'display:none' ?>">
          <span class="notification-count-number"><?= $school_total_unread ?></span>
        </span>
      </button>

      <!-- Notification Dropdown -->
      <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-surface border border-border rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="px-4 py-3 border-b border-border bg-slate-50 flex items-center justify-between">
          <span class="font-bold text-xs text-textMain">Notifications</span>
          <span class="header-unread-label bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full <?= $school_total_unread > 0 ? '' : 'hidden' ?>" style="<?= $school_total_unread > 0 ? '' : 'display:none' ?>">
            <?= $school_total_unread ?> New
          </span>
        </div>
        <div class="divide-y divide-border max-h-72 overflow-y-auto">

          <?php foreach ($unreadFormList as $uf):
            $formRecord = $formRecordsById[$uf['form_id'] ?? ''];
          ?>
            <a href="<?= BASE_URL ?>/school/forms.php?form=<?= urlencode($uf['form_id'] ?? '') ?>" class="flex items-start gap-3 px-4 py-3 bg-amber-50/50 hover:bg-amber-50 transition group">
              <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5 text-amber-700">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1"><span class="text-[10px] font-bold text-amber-700 uppercase tracking-wide">New District Form</span><span class="font-mono text-[9px] text-amber-700"><?= e($uf['form_id'] ?? '') ?></span></div>
                <div class="text-xs font-semibold text-textMain truncate mt-0.5 group-hover:text-amber-800"><?= e($formRecord['title'] ?? 'School Form') ?></div>
                <div class="text-[10px] text-muted mt-0.5">Please respond by <?= !empty($formRecord['closes_at']) ? e(date('M d, Y h:i A', strtotime($formRecord['closes_at']))) : 'the closing date' ?></div>
              </div>
              <span class="w-2 h-2 rounded-full bg-amber-500 flex-shrink-0 mt-1.5 animate-pulse"></span>
            </a>
          <?php endforeach; ?>

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

          <?php if (empty($unreadFormList) && empty($unreadMsgList) && empty($unreadCmpList)): ?>
            <div class="px-4 py-6 text-center text-xs text-muted">
              <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
              No unread notifications
            </div>
          <?php endif; ?>

        </div>
        <div class="px-4 py-2.5 border-t border-border bg-slate-50 flex items-center justify-between text-xs font-semibold">
          <a href="<?= BASE_URL ?>/school/forms.php" class="text-amber-700 hover:text-amber-900">District Forms</a>
          <span class="text-border">|</span>
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

<?php if ($school_announcements): ?>
<div id="school-announcement-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="school-announcement-heading">
  <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
    <div class="bg-primary px-6 py-5 text-white">
      <div class="flex items-center gap-3">
        <img src="<?= BASE_URL ?>/assets/images/rsu-favicon.png" alt="<?= e(APP_NAME) ?> logo" class="h-10 w-10 rounded bg-white p-1 object-contain">
        <div>
          <div class="text-xs font-bold uppercase tracking-[.18em] text-white/75">District announcement</div>
          <h2 id="school-announcement-heading" class="mt-1 text-xl font-bold">Important information for your school</h2>
        </div>
      </div>
    </div>
    <div class="divide-y divide-slate-200">
      <?php foreach ($school_announcements as $announcement): ?>
      <article class="school-announcement-item px-6 py-5" data-announcement-id="<?= e($announcement['announcement_id'] ?? '') ?>">
        <h3 class="text-lg font-bold text-slate-900"><?= e($announcement['title'] ?? '') ?></h3>
        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700"><?= e($announcement['message'] ?? '') ?></p>
        <?php if (!empty($announcement['image_url'])): ?>
        <img src="<?= e($announcement['image_url']) ?>" alt="<?= e($announcement['title'] ?? 'Announcement image') ?>" class="mt-4 max-h-80 w-full rounded-xl border border-slate-200 bg-slate-50 object-contain">
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-500">Issued <?= e($announcement['created_at'] ?? '') ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="flex justify-end border-t border-slate-200 px-6 py-4">
      <button id="dismiss-school-announcement" type="button" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primaryDark">Close for now</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  window.LSU_BASE_URL = '<?= BASE_URL ?>';
  window.LSU_SCHOOL_SEMIS = '<?= e($school_semis) ?>';

  const schoolAnnouncementModal = document.getElementById('school-announcement-modal');
  if (schoolAnnouncementModal) {
    const announcementSessionKey = 'lsu-announcement-dismissed-' + <?= json_encode($_SESSION['lsu_login_instance'] . '-' . $school_semis, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> + '-';
    const announcementItems = Array.from(schoolAnnouncementModal.querySelectorAll('.school-announcement-item'));
    announcementItems.forEach(function(item) {
      item.dataset.dismissKey = announcementSessionKey + item.dataset.announcementId;
      if (sessionStorage.getItem(item.dataset.dismissKey) === '1') item.classList.add('hidden');
    });
    if (announcementItems.some(function(item) { return !item.classList.contains('hidden'); })) {
      schoolAnnouncementModal.classList.remove('hidden');
      schoolAnnouncementModal.classList.add('flex');
    }
    document.getElementById('dismiss-school-announcement').addEventListener('click', function() {
      announcementItems.forEach(function(item) {
        if (!item.classList.contains('hidden')) sessionStorage.setItem(item.dataset.dismissKey, '1');
      });
      schoolAnnouncementModal.classList.add('hidden');
      schoolAnnouncementModal.classList.remove('flex');
    });
  }

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
