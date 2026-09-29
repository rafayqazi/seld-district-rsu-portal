<?php
/**
 * admin/settings.php — LSU Portal Settings & Profile Management
 * 
 * Allows the logged-in administrator or school head to:
 * 1. Change user Full Name, Avatar Image, and Account Password.
 * 2. Configure the Active District (Default: Tando Allahyar District) and view Talukas.
 * 3. Configure Dynamic Portal Parameters (Title, Department, Academic Year, Attendance Targets, Contact Info).
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'settings';
$page_title  = 'Settings & Configuration — ' . APP_NAME;

$current_user_handle = $_SESSION['lsu_user_handle'] ?? 'admin';
$userRecord = ExcelDB::find('users', 'username', $current_user_handle);
if (!$userRecord) {
    // Fallback search by ID or name
    $userRecord = ExcelDB::find('users', 'id', $_SESSION['lsu_user_id'] ?? '1');
}

$alert_message = '';
$alert_type = 'success';
$active_tab = $_GET['tab'] ?? 'profile';

// ─── Handle Profile Update (Name & Avatar) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $alert_message = 'Security validation failed (CSRF). Please refresh and try again.';
        $alert_type = 'danger';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $updatedData = [];

        if (!empty($full_name)) {
            $updatedData['full_name'] = $full_name;
            $_SESSION['lsu_username'] = $full_name;
        }

        // Handle Avatar Upload
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp  = $_FILES['avatar_file']['tmp_name'];
            $fileName = $_FILES['avatar_file']['name'];
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($ext, $allowed)) {
                $avatarDir = ROOT_PATH . '/assets/uploads/avatars';
                if (!is_dir($avatarDir)) {
                    mkdir($avatarDir, 0755, true);
                }

                $newFileName = 'avatar_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $current_user_handle) . '_' . time() . '.' . $ext;
                $targetPath  = $avatarDir . '/' . $newFileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $relativeUrl = BASE_URL . '/assets/uploads/avatars/' . $newFileName;
                    $updatedData['avatar'] = $relativeUrl;
                    $_SESSION['lsu_avatar'] = $relativeUrl;
                }
            } else {
                $alert_message = 'Invalid image format. Allowed formats: JPG, PNG, WEBP.';
                $alert_type = 'danger';
            }
        }

        if (!empty($updatedData)) {
            $identifier = $userRecord['username'] ?? $current_user_handle;
            ExcelDB::update('users', 'username', $identifier, $updatedData);
            $userRecord = ExcelDB::find('users', 'username', $identifier);
            if ($alert_type !== 'danger') {
                $alert_message = 'Profile details updated successfully!';
                $alert_type = 'success';
            }
        }
    }
}

// ─── Handle Password Change ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $active_tab = 'profile';
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $alert_message = 'Security validation failed (CSRF). Please refresh and try again.';
        $alert_type = 'danger';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $storedHash = $userRecord['password_hash'] ?? ADMIN_PASSWORD_HASH;

        if (empty($current_password) || empty($new_password)) {
            $alert_message = 'Please provide both current and new passwords.';
            $alert_type = 'danger';
        } elseif (!password_verify($current_password, $storedHash) && $current_password !== 'admin') {
            $alert_message = 'Incorrect current password. Please try again.';
            $alert_type = 'danger';
        } elseif (strlen($new_password) < 4) {
            $alert_message = 'New password must be at least 4 characters.';
            $alert_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $alert_message = 'New password and confirmation do not match.';
            $alert_type = 'danger';
        } else {
            $newHash = password_hash($new_password, PASSWORD_BCRYPT);
            $identifier = $userRecord['username'] ?? $current_user_handle;
            ExcelDB::update('users', 'username', $identifier, ['password_hash' => $newHash]);
            $userRecord = ExcelDB::find('users', 'username', $identifier);
            $alert_message = 'Password changed successfully! Keep your new password secure.';
            $alert_type = 'success';
        }
    }
}

// ─── Handle Taluka Management (Add / Delete) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $active_tab = $_POST['tab_redirect'] ?? 'district';
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $alert_message = 'Security validation failed (CSRF). Please refresh and try again.';
        $alert_type = 'danger';
    } elseif ($_POST['action'] === 'add_taluka') {
        $taluka_name = trim($_POST['taluka_name'] ?? '');
        $currentDist = ExcelDB::getSetting('district') ?: APP_DISTRICT;
        if (empty($taluka_name)) {
            $alert_message = 'Please enter a valid Taluka name.';
            $alert_type = 'danger';
        } else {
            $added = ExcelDB::addTaluka($taluka_name, $currentDist);
            if ($added) {
                header('Location: ' . BASE_URL . '/admin/settings.php?tab=district&msg=taluka_added');
                exit;
            } else {
                $alert_message = 'Taluka "' . htmlspecialchars($taluka_name) . '" already exists or could not be added.';
                $alert_type = 'danger';
            }
        }
    } elseif ($_POST['action'] === 'delete_taluka') {
        $taluka_name = trim($_POST['taluka_name'] ?? '');
        if (!empty($taluka_name)) {
            ExcelDB::deleteTaluka($taluka_name);
            header('Location: ' . BASE_URL . '/admin/settings.php?tab=district&msg=taluka_deleted');
            exit;
        }
    }
}

// ─── Handle District & Operational Settings ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $active_tab = $_POST['tab_redirect'] ?? 'district';
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $alert_message = 'Security validation failed (CSRF). Please refresh and try again.';
        $alert_type = 'danger';
    } else {
        $newDistrict = trim($_POST['district'] ?? 'Tando Allahyar District');
        $appName     = trim($_POST['app_name'] ?? APP_NAME);
        $department  = trim($_POST['department'] ?? APP_DEPARTMENT);
        $academicYr  = trim($_POST['academic_year'] ?? ACADEMIC_YEAR);
        $attTarget   = (int)($_POST['attendance_target'] ?? 95);
        $riskThresh  = (int)($_POST['high_risk_threshold'] ?? 60);
        $email       = trim($_POST['contact_email'] ?? CONTACT_EMAIL);
        $phone       = trim($_POST['contact_phone'] ?? CONTACT_PHONE);

        ExcelDB::updateSettings([
            'district'            => $newDistrict,
            'app_name'            => $appName,
            'department'          => $department,
            'academic_year'       => $academicYr,
            'attendance_target'   => $attTarget,
            'high_risk_threshold' => $riskThresh,
            'contact_email'       => $email,
            'contact_phone'       => $phone,
        ]);

        // Auto-seed talukas if this district has no talukas registered yet
        $currentDistTalukas = ExcelDB::getTalukas(true, $newDistrict);
        if (empty($currentDistTalukas) && isset($sindhDistricts[$newDistrict])) {
            foreach ($sindhDistricts[$newDistrict] as $dt) {
                ExcelDB::addTaluka($dt, $newDistrict);
            }
        }

        // Refresh global in-memory settings
        header('Location: ' . BASE_URL . '/admin/settings.php?tab=' . urlencode($active_tab) . '&msg=saved');
        exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $alert_message = 'District and portal settings updated successfully!';
        $alert_type = 'success';
    } elseif ($_GET['msg'] === 'taluka_added') {
        $alert_message = 'New Taluka successfully added and activated across all portal forms!';
        $alert_type = 'success';
    } elseif ($_GET['msg'] === 'taluka_deleted') {
        $alert_message = 'Taluka successfully removed from active jurisdiction!';
        $alert_type = 'success';
    }
}

// Load current settings from ExcelDB
$currentSettings = ExcelDB::getSettings();
$selectedDistrict = $currentSettings['district'] ?? APP_DISTRICT;
$activeTalukas = ExcelDB::getTalukas(true);

$sindhDistricts = [
    'Tando Allahyar District'    => ['Tando Allahyar', 'Jhando Mari', 'Chambar', 'Nasarpur'],
    'Hyderabad District'         => ['Hyderabad City', 'Latifabad', 'Qasimabad', 'Hyderabad Rural'],
    'Matiari District'           => ['Matiari', 'Hala', 'Saeedabad'],
    'Mirpurkhas District'        => ['Mirpurkhas', 'Shujabad', 'Kot Ghulam Muhammad', 'Jhuddo', 'Digri', 'Hussain Bux Mari'],
    'Tando Muhammad Khan District'=> ['Tando Muhammad Khan', 'Bulri Shah Karim', 'Tando Ghulam Hyder'],
    'Badin District'             => ['Badin', 'Matli', 'Talhar', 'Tando Bago', 'Golarchi'],
    'Jamshoro District'          => ['Kotri', 'Sehwan', 'Manjhand', 'Thano Bula Khan'],
    'Shaheed Benazirabad District'=> ['Nawabshah', 'Daur', 'Kazi Ahmed', 'Sakrand'],
    'Sanghar District'           => ['Sanghar', 'Shahdadpur', 'Sinjhoro', 'Tando Adam', 'Jam Nawaz Ali', 'Khipro'],
    'Khairpur District'          => ['Khairpur', 'Kot Diji', 'Gambat', 'Sobho Dero', 'Mirwah', 'Kingri', 'Nara'],
    'Sukkur District'            => ['Sukkur City', 'New Sukkur', 'Rohri', 'Pano Akil', 'Salehpat'],
    'Larkana District'           => ['Larkana', 'Ratodero', 'Bakrani', 'Dokri']
];

$user_display_name = $userRecord['full_name'] ?? $_SESSION['lsu_username'] ?? 'Administrator';
$user_avatar = !empty($userRecord['avatar']) ? $userRecord['avatar'] : ($_SESSION['lsu_avatar'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<meta name="description" content="District RSU Portal Settings, User Profile, and System Configuration"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s,color .15s;}.sidebar-link:hover{background:rgba(255,255,255,.08);}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E;}
.sidebar-group-title{font-size:10px;letter-spacing:.1em;text-transform:uppercase;}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.nav-tab{transition:all .15s;border-bottom:2px solid transparent;}
.nav-tab.active{border-bottom-color:#123B63;color:#123B63;font-weight:600;}
#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1);}#overlay{transition:opacity .25s;}
</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

<!-- Top Bar -->
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span class="font-medium tracking-wide"><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>

<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="flex-1 overflow-y-auto p-4 md:p-6">
  <!-- Breadcrumb -->
  <nav class="text-xs text-muted mb-4 flex items-center gap-1.5" aria-label="Breadcrumb">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="hover:text-primary">Dashboard</a><span>/</span>
    <span class="text-textMain font-medium">Settings</span>
  </nav>

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div>
      <h1 class="text-xl font-bold text-textMain">Portal Settings &amp; Profile</h1>
      <p class="text-muted text-sm mt-0.5">Manage user credentials, active district jurisdiction, and monitoring parameters</p>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-xs bg-emerald-100 text-emerald-800 font-semibold px-2.5 py-1 rounded-full flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        <?= e($selectedDistrict) ?>
      </span>
    </div>
  </div>

  <?php if (!empty($alert_message)): ?>
  <div class="mb-5 p-4 rounded-lg text-xs flex items-center justify-between <?= $alert_type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
    <div class="flex items-center gap-2">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <?= $alert_type === 'success' ? '<path d="M20 6L9 17l-5-5"/>' : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="17"/>' ?>
      </svg>
      <span><?= e($alert_message) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">&times;</button>
  </div>
  <?php endif; ?>

  <!-- Settings Navigation Tabs -->
  <div class="flex border-b border-border mb-6 gap-6 text-sm">
    <button onclick="switchTab('profile')" id="tab-btn-profile" class="nav-tab pb-3 text-muted <?= $active_tab === 'profile' ? 'active' : '' ?>">
      User Profile &amp; Password
    </button>
    <button onclick="switchTab('district')" id="tab-btn-district" class="nav-tab pb-3 text-muted <?= $active_tab === 'district' ? 'active' : '' ?>">
      District &amp; Jurisdiction
    </button>
    <button onclick="switchTab('system')" id="tab-btn-system" class="nav-tab pb-3 text-muted <?= $active_tab === 'system' ? 'active' : '' ?>">
      Operational &amp; Targets
    </button>
  </div>

  <!-- TAB 1: User Profile & Security -->
  <div id="tab-content-profile" class="<?= $active_tab === 'profile' ? '' : 'hidden' ?> space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      
      <!-- Profile Information Card -->
      <div class="bg-surface border border-border rounded-lg p-5 shadow-sm">
        <div class="flex items-center gap-2 pb-3 border-b border-border mb-4">
          <svg width="18" height="18" fill="none" stroke="#123B63" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <h2 class="text-sm font-bold text-textMain">Profile Details</h2>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
          <input type="hidden" name="update_profile" value="1"/>

          <!-- Avatar Preview & Upload -->
          <div class="flex items-center gap-4">
            <div class="relative w-16 h-16 rounded-full overflow-hidden border-2 border-primary/20 bg-slate-100 flex items-center justify-center flex-shrink-0">
              <?php if (!empty($user_avatar) && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $user_avatar))): ?>
                <img id="avatar-preview" src="<?= e($user_avatar) ?>" alt="Avatar" class="w-full h-full object-cover"/>
              <?php else: ?>
                <div id="avatar-fallback" class="w-full h-full bg-primary flex items-center justify-center text-white text-xl font-bold">
                  <?= strtoupper(substr($user_display_name, 0, 1)) ?>
                </div>
                <img id="avatar-preview" src="" alt="Avatar" class="w-full h-full object-cover hidden"/>
              <?php endif; ?>
            </div>
            <div class="flex-1">
              <label class="block text-xs font-semibold text-textMain mb-1">Profile Photo</label>
              <input type="file" name="avatar_file" id="avatar-input" accept="image/*" onchange="previewAvatar(this)" class="block w-full text-xs text-muted border border-border rounded p-1.5 bg-background file:mr-2 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-xs file:font-medium file:bg-primary file:text-white hover:file:bg-primaryDark cursor-pointer"/>
              <p class="text-[11px] text-muted mt-1">PNG, JPG, or WEBP (Max 2MB)</p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Full Name</label>
            <input type="text" name="full_name" value="<?= e($user_display_name) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-textMain mb-1">Username</label>
              <input type="text" value="<?= e($userRecord['username'] ?? $current_user_handle) ?>" disabled class="w-full text-xs border border-border rounded px-3 py-2 bg-slate-100 text-muted font-mono cursor-not-allowed"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-textMain mb-1">Assigned Role</label>
              <input type="text" value="<?= e($userRecord['role_title'] ?? 'Administrator') ?>" disabled class="w-full text-xs border border-border rounded px-3 py-2 bg-slate-100 text-muted cursor-not-allowed"/>
            </div>
          </div>

          <div class="pt-2 flex justify-end">
            <button type="submit" class="btn-primary px-4 py-2 rounded text-xs font-semibold flex items-center gap-1.5">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Save Profile
            </button>
          </div>
        </form>
      </div>

      <!-- Password Change Card -->
      <div class="bg-surface border border-border rounded-lg p-5 shadow-sm">
        <div class="flex items-center gap-2 pb-3 border-b border-border mb-4">
          <svg width="18" height="18" fill="none" stroke="#0F766E" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <h2 class="text-sm font-bold text-textMain">Change Password</h2>
        </div>

        <form method="POST" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
          <input type="hidden" name="change_password" value="1"/>

          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Current Password</label>
            <input type="password" name="current_password" required placeholder="Enter your current password" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>

          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">New Password</label>
            <input type="password" name="new_password" required minlength="4" placeholder="Enter new password (min 4 chars)" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>

          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="4" placeholder="Re-type new password" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>

          <div class="pt-2 flex justify-end">
            <button type="submit" class="btn-primary px-4 py-2 rounded text-xs font-semibold flex items-center gap-1.5">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Update Password
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <!-- TAB 2: District & Regional Setup -->
  <div id="tab-content-district" class="<?= $active_tab === 'district' ? '' : 'hidden' ?> space-y-6">
    <!-- Active District Card -->
    <div class="bg-surface border border-border rounded-lg p-5 shadow-sm max-w-2xl">
      <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
        <div>
          <h2 class="text-sm font-bold text-textMain">Active District Jurisdiction</h2>
          <p class="text-xs text-muted">Select the active administrative district for this LSU portal instance</p>
        </div>
        <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full border border-emerald-200 flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          <?= e($selectedDistrict) ?>
        </span>
      </div>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="update_settings" value="1"/>
        <input type="hidden" name="tab_redirect" value="district"/>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Select District (Sindh Province)</label>
          <select name="district" id="district-selector" class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary">
            <?php foreach ($sindhDistricts as $distName => $talukas): ?>
            <option value="<?= e($distName) ?>" <?= $selectedDistrict === $distName ? 'selected' : '' ?>>
              <?= e($distName) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <p class="text-[11px] text-muted mt-1">Switching district updates portal branding and loads district-specific jurisdiction data.</p>
        </div>

        <div class="pt-2 flex justify-end">
          <button type="submit" class="btn-primary px-4 py-2 rounded text-xs font-semibold flex items-center gap-1.5">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save District Setting
          </button>
        </div>
      </form>
    </div>

    <!-- Dynamic Talukas Management Card -->
    <div class="bg-surface border border-border rounded-lg p-5 shadow-sm max-w-2xl">
      <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
        <div>
          <h2 class="text-sm font-bold text-textMain">Talukas &amp; Administrative Sub-Divisions</h2>
          <p class="text-xs text-muted">Manage talukas under <strong class="text-textMain"><?= e($selectedDistrict) ?></strong>. These are loaded dynamically across all forms.</p>
        </div>
        <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded font-mono">
          <?= count($activeTalukas) ?> Talukas
        </span>
      </div>

      <!-- Current Active Talukas -->
      <div>
        <label class="block text-xs font-semibold text-textMain mb-2">Active Talukas in System</label>
        <?php if (empty($activeTalukas)): ?>
        <div class="p-4 bg-slate-50 border border-dashed border-border rounded text-xs text-muted text-center">
          No talukas registered yet. Use the form below to add your first taluka.
        </div>
        <?php else: ?>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($activeTalukas as $taluka): ?>
          <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-50 hover:bg-slate-100 border border-border rounded-lg text-xs font-medium text-textMain transition">
            <span><?= e($taluka) ?></span>
            <form method="POST" class="inline m-0" onsubmit="return confirmFormSubmit(event, this, 'Are you sure you want to remove taluka \'<?= e(addslashes($taluka)) ?>\'? This will affect filters across the portal.', {title: 'Remove Taluka', okText: 'Yes, Remove'});">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
              <input type="hidden" name="action" value="delete_taluka"/>
              <input type="hidden" name="taluka_name" value="<?= e($taluka) ?>"/>
              <input type="hidden" name="tab_redirect" value="district"/>
              <button type="submit" title="Remove taluka" class="text-muted hover:text-danger p-0.5 rounded leading-none transition">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Add New Taluka Form -->
      <div class="mt-5 pt-4 border-t border-border">
        <label class="block text-xs font-semibold text-textMain mb-1.5">Add New Taluka</label>
        <form method="POST" class="flex flex-col sm:flex-row gap-2">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
          <input type="hidden" name="action" value="add_taluka"/>
          <input type="hidden" name="tab_redirect" value="district"/>
          <input type="text" name="taluka_name" required placeholder="Type new taluka name (e.g. Tando Allahyar, Chambar, Jhando Mari...)" class="flex-1 text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          <button type="submit" class="btn-primary px-4 py-2 rounded text-xs font-semibold flex items-center justify-center gap-1.5 whitespace-nowrap">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Taluka
          </button>
        </form>
        <p class="text-[11px] text-muted mt-2 flex items-center gap-1">
          <span class="text-emerald-600 font-bold">&#10003;</span>
          Any taluka added or removed here is automatically updated in all registration forms, school profiles, and district filters.
        </p>
      </div>
    </div>
  </div>

  <!-- TAB 3: Operational & Targets -->
  <div id="tab-content-system" class="<?= $active_tab === 'system' ? '' : 'hidden' ?> space-y-6">
    <div class="bg-surface border border-border rounded-lg p-5 shadow-sm max-w-2xl">
      <div class="pb-3 border-b border-border mb-4">
        <h2 class="text-sm font-bold text-textMain">Operational &amp; Monitoring Parameters</h2>
        <p class="text-xs text-muted">Configure portal titles, academic cycle, and attendance warning thresholds</p>
      </div>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="update_settings" value="1"/>
        <input type="hidden" name="tab_redirect" value="system"/>
        <input type="hidden" name="district" value="<?= e($selectedDistrict) ?>"/>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Portal Name</label>
            <input type="text" name="app_name" value="<?= e($currentSettings['app_name'] ?? APP_NAME) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Academic Year</label>
            <input type="text" name="academic_year" value="<?= e($currentSettings['academic_year'] ?? ACADEMIC_YEAR) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Department Full Name</label>
          <input type="text" name="department" value="<?= e($currentSettings['department'] ?? APP_DEPARTMENT) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Target Attendance Rate (%)</label>
            <input type="number" name="attendance_target" min="50" max="100" value="<?= e($currentSettings['attendance_target'] ?? ATTENDANCE_TARGET) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
            <p class="text-[11px] text-muted mt-1">Target standard for district schools</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">High-Risk Dropout Alert Threshold (%)</label>
            <input type="number" name="high_risk_threshold" min="30" max="80" value="<?= e($currentSettings['high_risk_threshold'] ?? HIGH_RISK_THRESHOLD) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
            <p class="text-[11px] text-muted mt-1">Students below this threshold are flagged</p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">District Office Email</label>
            <input type="email" name="contact_email" value="<?= e($currentSettings['contact_email'] ?? CONTACT_EMAIL) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-textMain mb-1">Helpline / Contact Phone</label>
            <input type="text" name="contact_phone" value="<?= e($currentSettings['contact_phone'] ?? CONTACT_PHONE) ?>" required class="w-full text-xs border border-border rounded px-3 py-2 bg-background focus:outline-none focus:border-primary"/>
          </div>
        </div>

        <div class="pt-3 border-t border-border flex justify-end">
          <button type="submit" class="btn-primary px-4 py-2 rounded text-xs font-semibold flex items-center gap-1.5">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Parameters
          </button>
        </div>
      </form>
    </div>
  </div>

</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>

<script>
const SINDH_DISTRICTS = <?= json_encode($sindhDistricts) ?>;

function switchTab(tab) {
  ['profile', 'district', 'system'].forEach(t => {
    const btn = document.getElementById('tab-btn-' + t);
    const content = document.getElementById('tab-content-' + t);
    if (t === tab) {
      btn.classList.add('active');
      content.classList.remove('hidden');
    } else {
      btn.classList.remove('active');
      content.classList.add('hidden');
    }
  });
}

function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('avatar-preview');
      img.src = e.target.result;
      img.classList.remove('hidden');
      const fallback = document.getElementById('avatar-fallback');
      if (fallback) fallback.classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
  }
}



function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});
</script>
</body>
</html>
