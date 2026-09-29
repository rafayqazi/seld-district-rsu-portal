<?php
/**
 * school/profile.php — School Profile & Facilities Editor
 * 
 * Head Master can edit and update their own school details, facilities,
 * staff counts, contact information, and upload school logo.
 */

require_once __DIR__ . '/auth_guard.php';

$active_page = 'profile';
$page_title  = 'Edit School Profile & Facilities — ' . e($school_name);

$notification = '';
$notification_type = 'success';
$available_talukas = ExcelDB::getTalukas();

// ─── Handle Profile Update Form Submission ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notification = 'CSRF validation failed. Please try again.';
        $notification_type = 'danger';
    } elseif (isset($_POST['save_school_profile'])) {
        $name    = trim($_POST['school_name'] ?? '');
        $hm      = trim($_POST['head_master'] ?? '');
        $cnic    = trim($_POST['cnic'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $addr    = trim($_POST['address'] ?? '');
        $taluka  = trim($_POST['taluka'] ?? $school_taluka);
        $level   = trim($_POST['level'] ?? 'Primary');
        $gender  = trim($_POST['gender'] ?? 'Co-education');
        $enroll  = (int)($_POST['enrollment'] ?? 0);
        $status  = trim($_POST['status'] ?? 'Active');
        $rooms   = max(0, (int)($_POST['classrooms'] ?? 4));
        $tchrs   = max(0, (int)($_POST['teachers'] ?? 6));
        $nonTch  = max(0, (int)($_POST['non_teaching'] ?? 1));
        $elec    = trim($_POST['facility_electricity'] ?? 'Solar + Grid');
        $water   = trim($_POST['facility_water'] ?? 'Filtered Plant');
        $toil    = trim($_POST['facility_toilets'] ?? 'Functional Blocks');
        $wall    = trim($_POST['facility_boundary_wall'] ?? 'Secured & Complete');
        $net     = trim($_POST['facility_internet'] ?? 'Broadband / 4G');

        $badge = 'badge-active';
        if ($status === 'Good') $badge = 'badge-good';
        elseif ($status === 'Needs Attention') $badge = 'badge-attention';
        elseif ($status === 'Not Reporting') $badge = 'badge-not-rep';

        $updateData = [
            'school_name'            => $name,
            'head_master'            => $hm,
            'cnic'                   => $cnic,
            'phone'                  => $phone,
            'address'                => $addr,
            'taluka'                 => $taluka,
            'level'                  => $level,
            'gender'                 => $gender,
            'enrollment'             => (string)$enroll,
            'status'                 => $status,
            'status_badge'           => $badge,
            'classrooms'             => (string)$rooms,
            'teachers'               => (string)$tchrs,
            'non_teaching'           => (string)$nonTch,
            'facility_electricity'   => $elec,
            'facility_water'         => $water,
            'facility_toilets'       => $toil,
            'facility_boundary_wall' => $wall,
            'facility_internet'      => $net,
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
                    $logoUrl = '/LSU-PORTAL/assets/uploads/schools/' . $newFilename;
                    $updateData['logo'] = $logoUrl;
                    $_SESSION['lsu_avatar'] = $logoUrl;
                }
            }
        }

        // Update schools.csv
        ExcelDB::update('schools', 'semis_code', $school_semis, $updateData);

        // Update users.csv for this school
        $cleanCnic = ExcelDB::normalizeCnic($cnic);
        $userUpdate = ['full_name' => $hm, 'cnic' => $cnic, 'district' => $taluka];
        if (isset($updateData['logo'])) {
            $userUpdate['avatar'] = $updateData['logo'];
        }
        ExcelDB::update('users', 'school_semis', $school_semis, $userUpdate);

        $_SESSION['lsu_username'] = $hm;
        $_SESSION['lsu_school_name'] = $name;
        $_SESSION['lsu_cnic'] = $cnic;

        header('Location: /LSU-PORTAL/school/profile.php?msg=updated');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $notification = 'School profile, facilities, and leadership information updated successfully!';
    $notification_type = 'success';
}

// Refresh current school record
$current_school = ExcelDB::getSchoolBySemis($school_semis) ?? $current_school;
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

  <!-- Top Sindh Government Institutional Header -->
  <div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative border-b border-emerald-500/30">
    <div class="flex items-center gap-2 font-medium">
      <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
      <span>حکومتِ سندھ &bull; <?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
    </div>
    <div class="flex items-center gap-3 text-white/80 text-[11px]">
      <span>School Management Portal</span>
      <span class="text-white/40">&bull;</span>
      <span class="font-semibold text-emerald-300"><?= APP_DISTRICT ?></span>
    </div>
  </div>

  <div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>

  <div class="flex flex-1 min-h-0">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <?php require_once __DIR__ . '/includes/header.php'; ?>

      <main class="flex-1 p-4 md:p-6 space-y-6">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-border">
          <div>
            <h1 class="text-lg font-bold text-textMain">School Profile &amp; Infrastructure Editor</h1>
            <p class="text-xs text-muted">Update institutional data, facilities checklist, leadership details, and upload school logo</p>
          </div>
          <a href="/LSU-PORTAL/school/dashboard.php" class="btn-secondary text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 self-start">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
            <span>Back to Dashboard</span>
          </a>
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

        <!-- ── Form Container ──────────────────────────────────────────────── -->
        <form method="POST" action="/LSU-PORTAL/school/profile.php" enctype="multipart/form-data" class="space-y-6">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
          <input type="hidden" name="save_school_profile" value="1"/>

          <!-- 1. School Basic Information -->
          <div class="bg-surface border border-border rounded-xl p-5 sm:p-6 shadow-xs space-y-4">
            <div class="border-b border-border pb-3 flex items-center justify-between">
              <div>
                <h2 class="text-sm font-bold text-textMain">1. Institutional Information</h2>
                <p class="text-xs text-muted">Core identity and administrative classification</p>
              </div>
              <span class="font-mono text-xs bg-slate-100 text-slate-700 px-2.5 py-1 rounded font-bold">
                SEMIS: <?= e($school_semis) ?>
              </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-textMain mb-1.5">School Full Official Name <span class="text-danger">*</span></label>
                <input type="text" name="school_name" required value="<?= e($current_school['school_name'] ?? '') ?>" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">SEMIS Code (Permanent ID)</label>
                <input type="text" readonly value="<?= e($school_semis) ?>" class="w-full text-xs font-mono border border-border rounded-lg px-3.5 py-2 bg-slate-100 text-slate-600 cursor-not-allowed"/>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Taluka <span class="text-danger">*</span></label>
                <select name="taluka" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach ($available_talukas as $t): ?>
                    <option value="<?= e($t) ?>" <?= (strcasecmp($current_school['taluka'] ?? '', $t) === 0) ? 'selected' : '' ?>><?= e($t) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">School Level</label>
                <select name="level" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Primary', 'Elementary', 'Middle', 'Secondary', 'Higher Secondary'] as $lvl): ?>
                    <option value="<?= $lvl ?>" <?= ($current_school['level'] ?? '') === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Gender Classification</label>
                <select name="gender" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Co-education', 'Boys', 'Girls'] as $g): ?>
                    <option value="<?= $g ?>" <?= ($current_school['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Total Enrolled Students</label>
                <input type="number" name="enrollment" min="0" value="<?= (int)($current_school['enrollment'] ?? 0) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Operational Status</label>
                <select name="status" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Active', 'Good', 'Needs Attention', 'Not Reporting'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($current_school['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-textMain mb-1.5">School Physical Address / Location</label>
              <input type="text" name="address" value="<?= e($current_school['address'] ?? '') ?>" placeholder="e.g. Station Road, City Area, Tando Allahyar" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
            </div>

            <!-- School Logo Upload -->
            <div class="pt-2 border-t border-border">
              <label class="block text-xs font-semibold text-textMain mb-1.5">School Logo / Emblem / Photo</label>
              <div class="flex items-center gap-4">
                <?php if (!empty($school_logo) && file_exists(ROOT_PATH . str_replace('/LSU-PORTAL', '', $school_logo))): ?>
                  <img src="<?= e($school_logo) ?>" alt="Logo" class="w-12 h-12 rounded-lg object-cover border border-slate-300 flex-shrink-0 bg-white shadow-xs"/>
                <?php else: ?>
                  <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-300 flex items-center justify-center text-slate-400 font-bold text-xs flex-shrink-0">
                    LOGO
                  </div>
                <?php endif; ?>
                <div class="flex-1">
                  <input type="file" name="school_logo" accept="image/*" class="w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 text-slate-500 cursor-pointer"/>
                  <span class="text-[10px] text-muted block mt-0.5">Recommended: Square PNG/JPG/WEBP image up to 3MB.</span>
                </div>
              </div>
            </div>

          </div>

          <!-- 2. Leadership & Head Master Contact -->
          <div class="bg-surface border border-border rounded-xl p-5 sm:p-6 shadow-xs space-y-4">
            <div class="border-b border-border pb-3">
              <h2 class="text-sm font-bold text-textMain">2. Head Master / Head Mistress Leadership</h2>
              <p class="text-xs text-muted">CNIC is strictly tied to the School Portal Login ID</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Head Master Full Name <span class="text-danger">*</span></label>
                <input type="text" name="head_master" required value="<?= e($current_school['head_master'] ?? '') ?>" placeholder="e.g. Muhammad Ishaq Memon" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">HM CNIC Number <span class="text-danger">* (Login ID)</span></label>
                <input type="text" name="cnic" required value="<?= e($current_school['cnic'] ?? '') ?>" placeholder="e.g. 41302-1849201-3" class="w-full text-xs font-mono border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Contact Mobile Phone</label>
                <input type="text" name="phone" value="<?= e($current_school['phone'] ?? '') ?>" placeholder="+92 300 0000000" class="w-full text-xs border border-border rounded-lg px-3.5 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
            </div>
          </div>

          <!-- 3. Infrastructure & Facilities Checklist -->
          <div class="bg-surface border border-border rounded-xl p-5 sm:p-6 shadow-xs space-y-4">
            <div class="border-b border-border pb-3">
              <h2 class="text-sm font-bold text-textMain">3. Infrastructure &amp; Basic Facilities</h2>
              <p class="text-xs text-muted">Official Sindh Education School Census compliance fields</p>
            </div>

            <!-- Staff & Classrooms -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Total Classrooms (Functional)</label>
                <input type="number" name="classrooms" min="0" value="<?= (int)($current_school['classrooms'] ?? 4) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Teaching Staff (Working)</label>
                <input type="number" name="teachers" min="0" value="<?= (int)($current_school['teachers'] ?? 6) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Non-Teaching Staff</label>
                <input type="number" name="non_teaching" min="0" value="<?= (int)($current_school['non_teaching'] ?? 1) ?>" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen"/>
              </div>
            </div>

            <!-- Facilities Dropdowns -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Electricity Source</label>
                <select name="facility_electricity" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Solar + Grid', 'Solar Only', 'Grid Only', 'Unavailable / None'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($current_school['facility_electricity'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Clean Drinking Water</label>
                <select name="facility_water" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Filtered Plant', 'Water Supply Line', 'Handpump / Tap', 'Unavailable / None'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($current_school['facility_water'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Toilets / Washroom Blocks</label>
                <select name="facility_toilets" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Functional Blocks', 'Needs Repair', 'Under Construction', 'Unavailable / None'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($current_school['facility_toilets'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-textMain mb-1.5">Boundary Wall</label>
                <select name="facility_boundary_wall" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Secured & Complete', 'Partial / Damaged', 'Under Construction', 'Unavailable / None'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($current_school['facility_boundary_wall'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="sm:col-span-2 lg:col-span-2">
                <label class="block text-xs font-semibold text-textMain mb-1.5">Internet &amp; Digital Connectivity</label>
                <select name="facility_internet" class="w-full text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-govGreen">
                  <?php foreach (['Broadband / 4G', 'Partial / Mobile Data', 'Unavailable / None'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($current_school['facility_internet'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/LSU-PORTAL/school/dashboard.php" class="btn-secondary text-xs font-semibold px-4 py-2.5 rounded-lg">
              Cancel
            </a>
            <button type="submit" class="btn-primary text-xs font-semibold px-6 py-2.5 rounded-lg flex items-center gap-2 shadow-sm">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              <span>Save &amp; Update School Profile</span>
            </button>
          </div>

        </form>

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
