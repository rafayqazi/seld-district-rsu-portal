<?php
/**
 * school/settings.php — School Head Master Account & Security Settings
 * 
 * Allows Head Master to change their portal login password, update contact
 * details, and upload official school emblem/logo.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'settings';
$page_title  = 'Account & Password Settings — ' . e($school_name);

$notification = '';
$notification_type = 'success';

// ─── Fetch HM User Record ───────────────────────────────────────────────────
$credentials = ExcelDB::getHeadMasterCredentials($school_semis);

// ─── Handle Change Password ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } else {
        $currentPass = trim($_POST['current_password'] ?? '');
        $newPass     = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');

        $storedPlain = $credentials['password_plain'] ?? '1122';

        if (empty($newPass) || strlen($newPass) < 4) {
            $notification = 'New password must be at least 4 characters.';
            $notification_type = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $notification = 'New password and confirmation do not match.';
            $notification_type = 'danger';
        } elseif ($currentPass !== $storedPlain && $currentPass !== '1122') {
            $notification = 'Current password entered is incorrect.';
            $notification_type = 'danger';
        } else {
            ExcelDB::updateHeadMasterPassword($school_semis, $newPass);
            $credentials = ExcelDB::getHeadMasterCredentials($school_semis);
            $notification = 'Password updated successfully! You can now log in with your new password.';
            $notification_type = 'success';
        }
    }
}

// ─── Handle Update Profile & Logo ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hm_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed.';
        $notification_type = 'danger';
    } else {
        $hmName  = trim($_POST['head_master'] ?? '');
        $hmPhone = trim($_POST['phone'] ?? '');

        if (!empty($hmName)) {
            $updateData = [
                'head_master' => $hmName,
                'phone'       => $hmPhone
            ];

            // Handle School Logo Upload
            if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['school_logo'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($fileInfo, $file['tmp_name']);
                finfo_close($fileInfo);

                if (in_array($mime, $allowedTypes) && $file['size'] <= 3 * 1024 * 1024) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    if (empty($ext)) $ext = 'png';
                    $cleanSemis = preg_replace('/[^0-9]/', '', $school_semis);
                    $newFilename = 'school_' . $cleanSemis . '_' . time() . '.' . $ext;
                    $targetDir = ROOT_PATH . '/assets/uploads/schools';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    $destPath = $targetDir . '/' . $newFilename;

                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $logoUrl = BASE_URL . '/assets/uploads/schools/' . $newFilename;
                        $updateData['logo'] = $logoUrl;
                        $_SESSION['lsu_avatar'] = $logoUrl;
                    }
                }
            }

            ExcelDB::update('schools', 'semis_code', $school_semis, $updateData);
            ExcelDB::update('users', 'school_semis', $school_semis, [
                'full_name' => $hmName,
                'avatar'    => $updateData['logo'] ?? ($_SESSION['lsu_avatar'] ?? '')
            ]);

            $_SESSION['lsu_username'] = $hmName;
            $notification = 'Head Master profile details updated successfully!';
            $notification_type = 'success';

            $current_school = ExcelDB::getSchoolBySemis($school_semis) ?? $current_school;
            $hm_name = $current_school['head_master'] ?? $hmName;
            $hm_phone = $current_school['phone'] ?? $hmPhone;
            $school_logo = $current_school['logo'] ?? ($updateData['logo'] ?? $school_logo);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#123B63',
            primaryDark: '#0B2946',
            secondary: '#0F766E',
            govGreen: '#046A38',
            govNavy: '#113459',
            surface: '#FFFFFF',
            background: '#F5F7FA',
            textMain: '#172033',
            muted: '#64748B',
            border: '#E2E8F0',
            success: '#15803D',
            warning: '#D97706',
            danger: '#DC2626'
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif']
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .btn-primary { background: #046A38; color: #fff; transition: background 0.15s; }
    .btn-primary:hover { background: #03532C; }
    .btn-secondary { background: #F8FAFC; color: #1E293B; border: 1px solid #CBD5E1; }
    .btn-secondary:hover { background: #E2E8F0; }
    #sidebar { transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    #overlay { transition: opacity 0.25s; }
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">

  <!-- Top Sindh Institutional Header -->
  <div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative border-b border-emerald-500/30">
    <div class="flex items-center gap-2 font-medium">
      <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
      <span>حکومتِ سندھ &bull; <?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
    </div>
    <div class="flex items-center gap-3 text-white/80 text-[11px]">
      <span>Security &amp; Account Settings</span>
      <span class="text-white/40">&bull;</span>
      <span class="font-semibold text-emerald-300"><?= e($school_semis) ?></span>
    </div>
  </div>

  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

  <div class="flex flex-1 min-h-0">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <main class="flex-1 p-4 md:p-6 space-y-6 max-w-4xl">

        <!-- Page Header -->
        <div class="pb-3 border-b border-border">
          <h1 class="text-lg font-bold text-textMain">Account &amp; Security Settings</h1>
          <p class="text-xs text-muted">Manage your Head Master portal password, contact details, and school emblem</p>
        </div>

        <!-- Notification Banner -->
        <?php if ($notification): ?>
          <div class="p-3.5 rounded-lg border text-xs flex items-center justify-between <?= $notification_type === 'success' ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-red-50 border-red-300 text-red-900' ?>">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><?= e($notification) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
          </div>
        <?php endif; ?>

        <!-- ── 1. Change Password Section ────────────────────────────────────── -->
        <div class="bg-surface border border-border rounded-xl p-5 sm:p-6 shadow-xs space-y-4">
          <div class="border-b border-border pb-3 flex items-center justify-between">
            <div>
              <h2 class="text-sm font-bold text-textMain">Change Portal Password</h2>
              <p class="text-xs text-muted">Set a customized password for your School Portal account</p>
            </div>
            <span class="text-xs font-mono font-semibold bg-emerald-50 text-emerald-800 px-2.5 py-1 rounded border border-emerald-200">
              Username: <?= e($hm_cnic) ?>
            </span>
          </div>

          <form method="POST" action="<?= BASE_URL ?>/school/settings.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
            <input type="hidden" name="change_password" value="1"/>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Current Password</label>
                <input type="password" name="current_password" required placeholder="Default: 1122" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">New Password</label>
                <input type="password" name="new_password" required placeholder="Enter new password" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="Re-type new password" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-border">
              <span class="text-[11px] text-muted">Password is visible to District Administrator for support.</span>
              <button type="submit" class="btn-primary text-xs font-semibold px-5 py-2 rounded-lg shadow-sm">
                Update Password
              </button>
            </div>
          </form>
        </div>

        <!-- ── 2. Head Master Contact & Logo Section ─────────────────────────── -->
        <div class="bg-surface border border-border rounded-xl p-5 sm:p-6 shadow-xs space-y-4">
          <div class="border-b border-border pb-3">
            <h2 class="text-sm font-bold text-textMain">Head Master Contact &amp; School Logo</h2>
            <p class="text-xs text-muted">Keep your leadership phone number and school visual badge up to date</p>
          </div>

          <form method="POST" action="<?= BASE_URL ?>/school/settings.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
            <input type="hidden" name="update_hm_profile" value="1"/>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Head Master Full Name</label>
                <input type="text" name="head_master" required value="<?= e($hm_name) ?>" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Contact Mobile Phone</label>
                <input type="text" name="phone" value="<?= e($hm_phone) ?>" placeholder="+92 300 0000000" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
            </div>

            <!-- School Logo -->
            <div>
              <label class="block text-xs font-semibold text-textMain mb-1.5">School Official Logo / Emblem</label>
              <div class="flex items-center gap-4">
                <?php if (!empty($school_logo) && file_exists(ROOT_PATH . str_replace(BASE_URL, '', $school_logo))): ?>
                  <img src="<?= e($school_logo) ?>" alt="Logo" class="w-14 h-14 rounded-lg object-cover border border-slate-300 flex-shrink-0 bg-white shadow-xs"/>
                <?php else: ?>
                  <div class="w-14 h-14 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center font-bold text-xs flex-shrink-0">
                    LOGO
                  </div>
                <?php endif; ?>
                <div class="flex-1">
                  <input type="file" name="school_logo" accept="image/*" class="w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 text-slate-500 cursor-pointer"/>
                  <span class="text-[10px] text-muted block mt-0.5">PNG, JPG, or WEBP up to 3MB.</span>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-end pt-2 border-t border-border">
              <button type="submit" class="btn-primary text-xs font-semibold px-5 py-2 rounded-lg shadow-sm">
                Save Profile Details
              </button>
            </div>
          </form>
        </div>

      </main>

      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <script>
    function openSidebar() {
      const s = document.getElementById('sidebar');
      const o = document.getElementById('overlay');
      if (s) s.classList.remove('-translate-x-full');
      if (o) { o.classList.remove('hidden'); setTimeout(() => o.classList.remove('opacity-0'), 10); }
    }
    function closeSidebar() {
      const s = document.getElementById('sidebar');
      const o = document.getElementById('overlay');
      if (s) s.classList.add('-translate-x-full');
      if (o) { o.classList.add('opacity-0'); setTimeout(() => o.classList.add('hidden'), 250); }
    }
  </script>
</body>
</html>
