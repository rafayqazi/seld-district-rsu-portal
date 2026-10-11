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
$global_search_schools = array_values(array_map(static fn($school) => [
    'semis_code' => (string)($school['semis_code'] ?? ''),
    'school_name' => (string)($school['school_name'] ?? ''),
    'level' => (string)($school['level'] ?? ''),
    'taluka' => (string)($school['taluka'] ?? ''),
], ExcelDB::all('schools')));
$global_search_schools_json = json_encode(
    $global_search_schools,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($global_search_schools_json === false) {
    $global_search_schools_json = '[]';
}
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
  <div id="global-school-search-wrap" class="flex-1 max-w-sm ml-2 hidden sm:block relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input id="global-search" type="search" placeholder="Search schools by name or SEMIS…"
           class="w-full pl-9 pr-4 py-2 text-sm border border-border rounded bg-background text-textMain placeholder-muted focus:border-primary focus:outline-none"
           aria-label="Search schools by name or SEMIS code" aria-autocomplete="list"
           aria-controls="global-search-results" aria-expanded="false" autocomplete="off"/>
    <div id="global-search-results" role="listbox" class="hidden absolute left-0 right-0 top-full mt-1 max-h-80 overflow-y-auto rounded-lg border border-border bg-surface shadow-xl z-50"></div>
  </div>
  <script type="application/json" id="global-search-school-data"><?= $global_search_schools_json ?></script>
  <script>
  (() => {
    const input = document.getElementById('global-search');
    const container = document.getElementById('global-school-search-wrap');
    const results = document.getElementById('global-search-results');
    const dataElement = document.getElementById('global-search-school-data');
    if (!input || !container || !results || !dataElement) return;

    const schools = JSON.parse(dataElement.textContent);
    const profileUrl = <?= json_encode(BASE_URL . '/admin/school-profile.php?semis=', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let activeIndex = -1;

    function closeResults() {
      results.classList.add('hidden');
      results.replaceChildren();
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    }

    function setActive(index) {
      const options = results.querySelectorAll('[role="option"]');
      if (!options.length) return;
      activeIndex = (index + options.length) % options.length;
      options.forEach((option, optionIndex) => {
        const active = optionIndex === activeIndex;
        option.classList.toggle('bg-slate-100', active);
        option.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      input.setAttribute('aria-activedescendant', options[activeIndex].id);
      options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function renderResults() {
      const query = input.value.trim().toLocaleLowerCase();
      results.replaceChildren();
      activeIndex = -1;
      input.removeAttribute('aria-activedescendant');
      if (!query) {
        closeResults();
        return;
      }

      const matches = schools
        .filter(school => `${school.school_name} ${school.semis_code}`.toLocaleLowerCase().includes(query))
        .sort((a, b) => {
          const aCode = a.semis_code.toLocaleLowerCase();
          const bCode = b.semis_code.toLocaleLowerCase();
          const aName = a.school_name.toLocaleLowerCase();
          const bName = b.school_name.toLocaleLowerCase();
          const aRank = aCode === query || aName === query ? 0 : (aCode.startsWith(query) || aName.startsWith(query) ? 1 : 2);
          const bRank = bCode === query || bName === query ? 0 : (bCode.startsWith(query) || bName.startsWith(query) ? 1 : 2);
          return aRank - bRank || aName.localeCompare(bName);
        })
        .slice(0, 8);

      if (!matches.length) {
        const emptyState = document.createElement('div');
        emptyState.className = 'px-3 py-3 text-xs text-muted';
        emptyState.textContent = 'No matching schools found.';
        results.append(emptyState);
      } else {
        matches.forEach((school, index) => {
          const option = document.createElement('button');
          option.type = 'button';
          option.id = `global-school-result-${index}`;
          option.setAttribute('role', 'option');
          option.setAttribute('aria-selected', 'false');
          option.className = 'w-full px-3 py-2.5 text-left hover:bg-slate-100 focus:bg-slate-100 focus:outline-none border-b border-border last:border-0';
          const name = document.createElement('span');
          name.className = 'block text-sm font-medium text-textMain';
          name.textContent = school.school_name || 'Unnamed school';
          const details = document.createElement('span');
          details.className = 'block mt-0.5 text-[11px] text-muted';
          details.textContent = `SEMIS ${school.semis_code}${school.level ? ` · ${school.level}` : ''}${school.taluka ? ` · ${school.taluka}` : ''}`;
          option.append(name, details);
          option.addEventListener('click', () => {
            window.location.href = profileUrl + encodeURIComponent(school.semis_code);
          });
          results.append(option);
        });
      }

      results.classList.remove('hidden');
      input.setAttribute('aria-expanded', 'true');
    }

    input.addEventListener('input', renderResults);
    input.addEventListener('keydown', event => {
      const options = results.querySelectorAll('[role="option"]');
      if (event.key === 'ArrowDown' && options.length) {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp' && options.length) {
        event.preventDefault();
        setActive(activeIndex < 0 ? options.length - 1 : activeIndex - 1);
      } else if (event.key === 'Enter' && options.length) {
        event.preventDefault();
        options[activeIndex < 0 ? 0 : activeIndex].click();
      } else if (event.key === 'Escape') {
        closeResults();
      }
    });
    document.addEventListener('click', event => {
      if (!container.contains(event.target)) closeResults();
    });
  })();
  </script>

  <div class="flex-1"></div>

  <!-- Notifications -->
  <?php 
    $header_unread_complaints = ExcelDB::getUnreadComplaintsCount('admin');
    $header_unread_messages   = ExcelDB::getUnreadMessagesCount('admin');
    $header_total_unread      = $header_unread_complaints + $header_unread_messages;

    $allComplaintsList = ExcelDB::all('complaints');
    $allMessagesList   = ExcelDB::all('admin_messages');

    $adminNotifItems = [];
    foreach ($allMessagesList as $m) {
        if (($m['unread_admin'] ?? '0') === '1') {
            $adminNotifItems[] = [
                'type'        => 'message',
                'id'          => $m['thread_id'] ?? '',
                'title'       => $m['school_name'] ?? 'School Reply',
                'subtitle'    => $m['subject'] ?? 'Direct Message',
                'url'         => BASE_URL . '/admin/message-thread.php?thread=' . urlencode($m['thread_id'] ?? ''),
                'date'        => $m['updated_at'] ?? $m['created_at'] ?? 'now',
                'is_unread'   => true
            ];
        }
    }
    foreach ($allComplaintsList as $c) {
        $isUnread = ($c['unread_admin'] ?? '0') === '1' || strtolower($c['status'] ?? '') === 'pending';
        if ($isUnread) {
            $adminNotifItems[] = [
                'type'        => 'complaint',
                'id'          => $c['ticket_no'] ?? '',
                'title'       => $c['school_name'] ?? 'School',
                'subtitle'    => $c['subject'] ?? '',
                'url'         => BASE_URL . '/admin/complaint-details.php?ticket=' . urlencode($c['ticket_no'] ?? ''),
                'date'        => $c['updated_at'] ?? $c['created_at'] ?? 'now',
                'is_unread'   => true
            ];
        }
    }
    usort($adminNotifItems, fn($a, $b) => strcmp($b['date'], $a['date']));
    $recentNotifs = array_slice($adminNotifItems, 0, 5);
  ?>
  <div class="relative">
    <button id="notif-btn" onclick="toggleNotif()" class="relative text-muted hover:text-primary p-1.5 rounded hover:bg-background" aria-label="Notifications">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 01-3.46 0"/>
      </svg>
      <span class="notification-count-badge header-unread-dot absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-600 text-white text-[10px] leading-none font-bold rounded-full border-2 border-surface inline-flex items-center justify-center <?= $header_total_unread > 0 ? '' : 'hidden' ?>" style="<?= $header_total_unread > 0 ? '' : 'display:none' ?>">
        <span class="notification-count-number"><?= $header_total_unread ?></span>
      </span>
    </button>
    <!-- Notification Dropdown -->
    <div id="notif-dropdown" class="hidden absolute right-0 top-10 w-80 bg-surface border border-border rounded-xl shadow-xl z-50 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-border bg-slate-50/50">
        <div class="flex items-center gap-1.5">
          <span class="font-bold text-xs text-textMain">Alerts &amp; Updates</span>
          <span class="header-unread-label text-[10px] bg-red-100 text-red-700 font-bold px-1.5 py-0.5 rounded-full <?= $header_total_unread > 0 ? '' : 'hidden' ?>" style="<?= $header_total_unread > 0 ? '' : 'display:none' ?>">
            <?= $header_total_unread ?> New
          </span>
        </div>
        <button onclick="window.playNotificationChime()" class="text-[11px] text-secondary hover:underline flex items-center gap-1">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
          <span>Test Sound</span>
        </button>
      </div>
      <div class="divide-y divide-border max-h-72 overflow-y-auto text-xs">
        <?php if (empty($recentNotifs)): ?>
          <div class="p-5 text-center text-muted text-xs">No pending alerts or unread messages.</div>
        <?php else: ?>
          <?php foreach ($recentNotifs as $notif): 
            $isMsg = ($notif['type'] === 'message');
          ?>
            <a href="<?= $notif['url'] ?>" class="px-4 py-3 hover:bg-background flex gap-3 cursor-pointer block transition <?= $isMsg ? 'bg-blue-50/30' : 'bg-amber-50/30' ?>">
              <div class="w-2 h-2 mt-1.5 rounded-full <?= $isMsg ? 'bg-blue-500' : 'bg-red-500' ?> flex-shrink-0 animate-pulse"></div>
              <div class="overflow-hidden flex-1">
                <div class="flex items-center justify-between gap-1">
                  <span class="text-[10px] font-bold <?= $isMsg ? 'text-blue-700' : 'text-emerald-700' ?> uppercase tracking-wider"><?= $isMsg ? 'Direct Message Reply' : 'Grievance Alert' ?></span>
                  <span class="font-mono text-[9px] text-slate-500"><?= e($notif['id']) ?></span>
                </div>
                <div class="text-xs font-semibold text-textMain truncate mt-0.5"><?= e($notif['title']) ?></div>
                <div class="text-[11px] text-muted truncate mt-0.5"><?= e($notif['subtitle']) ?></div>
                <div class="text-[10px] text-slate-400 mt-1">
                  <?= date('M d, H:i A', strtotime($notif['date'])) ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="px-4 py-2.5 border-t border-border flex items-center justify-between bg-slate-50/50 text-xs font-semibold">
        <a href="<?= BASE_URL ?>/admin/messages.php" class="text-blue-600 hover:underline">Direct Messages</a>
        <span class="text-border">|</span>
        <a href="<?= BASE_URL ?>/admin/complaints.php" class="text-primary hover:underline">Grievances</a>
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
