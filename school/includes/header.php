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
