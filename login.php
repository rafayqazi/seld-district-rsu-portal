<?php
/**
 * login.php — LSU Portal Authentication Page
 * 
 * Official Design based on Sindh Education & Literacy Department (SELD)
 * Reform Support Unit (RSU) Sindh — https://rsusindh.gov.pk/
 * District RSU Portal — Khairpur District
 */

require_once __DIR__ . '/config/config.php';

// ─── Session Setup ────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['lsu_logged_in']) && $_SESSION['lsu_logged_in'] === true) {
    if (isset($_SESSION['lsu_login_type']) && $_SESSION['lsu_login_type'] === 'school') {
        header('Location: ' . BASE_URL . '/school/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    }
    exit;
}

// ─── CSRF Token Generation ────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ─── Process Login Form ───────────────────────────────────────────────────────
$error_message = '';
$active_tab    = 'admin'; // default tab

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'سیکیورٹی تصدیق ناکام ہو گئی۔ براہ کرم صفحہ ریفریش کر کے دوبارہ کوشش کریں۔ (Security validation failed)';
    } else {
        $login_type = $_POST['login_type'] ?? 'admin';
        $active_tab = $login_type;

        if ($login_type === 'admin') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error_message = 'براہ کرم یوزر نیم اور پاس ورڈ درج کریں۔ (Please enter both username and password)';
            } else {
                $authenticated = false;
                $userRecord = null;

                // 1. Check ExcelDB users table
                $allUsers = ExcelDB::all('users');
                foreach ($allUsers as $u) {
                    if ($u['role'] === 'admin' && strtolower($u['username']) === strtolower($username)) {
                        if (password_verify($password, $u['password_hash'])) {
                            $authenticated = true;
                            $userRecord = $u;
                            break;
                        }
                    }
                }

                // 2. Fallback to hardcoded admin config if not matched above
                if (!$authenticated && $username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
                    $authenticated = true;
                    // Try to load saved profile from ExcelDB (full_name, avatar, etc.)
                    $savedAdmin = null;
                    foreach ($allUsers as $u) {
                        if (strtolower($u['username']) === strtolower(ADMIN_USERNAME)) {
                            $savedAdmin = $u;
                            break;
                        }
                    }
                    $userRecord = [
                        'username'   => ADMIN_USERNAME,
                        'full_name'  => $savedAdmin['full_name'] ?? 'District RSU Coordinator',
                        'avatar'     => $savedAdmin['avatar'] ?? '',
                        'role_title' => $savedAdmin['role_title'] ?? 'Administrator',
                        'district'   => APP_DISTRICT,
                        'role'       => 'admin',
                        'id'         => $savedAdmin['id'] ?? '1',
                    ];
                }

                if ($authenticated && $userRecord) {
                    session_regenerate_id(true);
                    $_SESSION['lsu_logged_in']   = true;
                    $_SESSION['lsu_role']        = $userRecord['role_title'] ?? 'Administrator';
                    $_SESSION['lsu_username']    = $userRecord['full_name'] ?? 'Admin User';
                    $_SESSION['lsu_user_id']     = $userRecord['id'] ?? '1';
                    $_SESSION['lsu_user_handle'] = $userRecord['username'] ?? 'admin';
                    $_SESSION['lsu_avatar']      = $userRecord['avatar'] ?? '';
                    $_SESSION['lsu_login_type']  = 'admin';
                    $_SESSION['last_activity']   = time();
                    $_SESSION['csrf_token']      = bin2hex(random_bytes(32));

                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    exit;
                } else {
                    sleep(1); // Anti-brute-force delay
                    $error_message = 'غلط یوزر نیم یا پاس ورڈ درج کیا گیا ہے۔ (Invalid username or password)';
                }
            }
        } elseif ($login_type === 'school') {
            $raw_cnic = trim($_POST['hm_cnic'] ?? $_POST['semis_code'] ?? '');
            $password = $_POST['school_password'] ?? '';

            if (empty($raw_cnic) || empty($password)) {
                $error_message = 'براہ کرم ہیڈ ماسٹر / مسٹریس کا شناختی کارڈ نمبر (CNIC) اور پاس ورڈ درج کریں۔ (Please enter HM CNIC and password)';
            } else {
                $authenticated = false;
                $userRecord = null;
                $schoolRecord = null;
                $cleanCnic = ExcelDB::normalizeCnic($raw_cnic);

                // 1. First find school in schools.csv by CNIC or SEMIS
                $allSchools = ExcelDB::all('schools');
                foreach ($allSchools as $s) {
                    $sCnic = ExcelDB::normalizeCnic($s['cnic'] ?? '');
                    if ((!empty($cleanCnic) && $sCnic === $cleanCnic) || strcasecmp($s['semis_code'] ?? '', $raw_cnic) === 0) {
                        $schoolRecord = $s;
                        break;
                    }
                }

                // 2. Check users.csv for credentials
                $allUsers = ExcelDB::all('users');
                foreach ($allUsers as $u) {
                    if ($u['role'] === 'school') {
                        $uCnic = ExcelDB::normalizeCnic($u['cnic'] ?? $u['username'] ?? '');
                        if ((!empty($cleanCnic) && $uCnic === $cleanCnic) || 
                            (!empty($schoolRecord) && ($u['school_semis'] === $schoolRecord['semis_code'] || $u['username'] === $schoolRecord['cnic']))) {
                            $userRecord = $u;
                            // Check bcrypt hash or plain password
                            if (password_verify($password, $u['password_hash']) || 
                                (!empty($u['password_plain']) && $u['password_plain'] === $password) ||
                                ($password === '1122' && (empty($u['password_plain']) || $u['password_plain'] === '1122'))) {
                                $authenticated = true;
                                break;
                            }
                        }
                    }
                }

                // 3. If school exists in schools.csv but not user, allow default 1122 password
                if (!$authenticated && $schoolRecord && $password === '1122') {
                    $authenticated = true;
                    // Auto-sync
                    ExcelDB::syncSchoolUsers();
                    $userRecord = ExcelDB::find('users', 'school_semis', $schoolRecord['semis_code']);
                }

                if ($authenticated && ($schoolRecord || $userRecord)) {
                    session_regenerate_id(true);
                    $_SESSION['lsu_logged_in']    = true;
                    $_SESSION['lsu_role']         = $userRecord['role_title'] ?? 'Head Master / Principal';
                    $_SESSION['lsu_username']     = $userRecord['full_name'] ?? $schoolRecord['head_master'] ?? 'School Headmaster';
                    $_SESSION['lsu_user_id']      = $userRecord['id'] ?? ($schoolRecord['id'] ?? '3');
                    $_SESSION['lsu_user_handle']  = $schoolRecord['cnic'] ?? $cleanCnic;
                    $_SESSION['lsu_cnic']         = $schoolRecord['cnic'] ?? $cleanCnic;
                    $_SESSION['lsu_avatar']       = $schoolRecord['logo'] ?? ($userRecord['avatar'] ?? '');
                    $_SESSION['lsu_login_type']   = 'school';
                    $_SESSION['lsu_school_id']    = $schoolRecord['id'] ?? '1';
                    $_SESSION['lsu_school_semis'] = $schoolRecord['semis_code'] ?? ($userRecord['school_semis'] ?? '');
                    $_SESSION['lsu_school_name']  = $schoolRecord['school_name'] ?? 'District School';
                    $_SESSION['last_activity']    = time();
                    $_SESSION['csrf_token']       = bin2hex(random_bytes(32));

                    header('Location: ' . BASE_URL . '/school/dashboard.php');
                    exit;
                } else {
                    sleep(1);
                    $error_message = 'غلط شناختی کارڈ نمبر (CNIC) یا پاس ورڈ۔ (Invalid Head Master CNIC or password. Note: Default password is "1122")';
                }
            }
        }

    }
}

// Session timeout / unauthorized notifications
$reason_message = '';
if (isset($_GET['reason'])) {
    if ($_GET['reason'] === 'timeout') {
        $reason_message = 'آپ کا سیشن ختم ہو گیا ہے۔ براہ کرم دوبارہ لاگ ان کریں۔ (Your session has expired)';
    } elseif ($_GET['reason'] === 'unauthorized') {
        $reason_message = 'اس صفحے تک رسائی کے لیے پہلے لاگ ان کرنا ضروری ہے۔ (Authentication required)';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login &mdash; District RSU Portal | School Education &amp; Literacy Department</title>
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/rsu-favicon.png" type="image/png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            // Authentic Pakistan & Sindh Government Theme
            govGreen: {
              DEFAULT: '#046A38', // Flag & Sindh Emblem Green
              dark:    '#03532C',
              light:   '#0A8347',
              50:      '#F0FDF4',
              100:     '#DCFCE7',
            },
            govNavy: {
              DEFAULT: '#113459', // Official SELD Navy
              dark:    '#0B2540',
              light:   '#184573',
            },
            govGold: {
              DEFAULT: '#D4AF37', // Official emblem gold
              dark:    '#B89327',
            },
            govSlate: {
              50:  '#F8FAFC',
              100: '#F1F5F9',
              200: '#E2E8F0',
              300: '#CBD5E1',
              400: '#94A3B8',
              500: '#64748B',
              700: '#334155',
              800: '#1E293B',
              900: '#0F172A',
            }
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
            urdu: ['Noto Nastaliq Urdu', 'Arial', 'serif']
          }
        }
      }
    }
  </script>
  <style>
    body {
      font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
      background-color: #F1F5F9;
      color: #1E293B;
    }

    /* Government Institutional Card */
    .gov-card {
      background: #FFFFFF;
      border: 1px solid #D1D5DB;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
      border-radius: 8px;
    }

    /* Tab styles matching official portals */
    .gov-tab-btn {
      transition: all 0.15s ease-in-out;
      border-bottom: 3px solid transparent;
      color: #64748B;
      font-weight: 600;
    }
    .gov-tab-btn.active {
      color: #046A38;
      border-bottom-color: #046A38;
      background-color: #F0FDF4;
    }
    .gov-tab-btn:not(.active):hover {
      color: #1E293B;
      background-color: #F8FAFC;
    }

    /* Form input styling typical of official portals */
    .gov-input {
      width: 100%;
      padding: 0.65rem 0.85rem 0.65rem 2.6rem;
      border: 1.5px solid #CBD5E1;
      border-radius: 6px;
      font-size: 0.875rem;
      background-color: #FFFFFF;
      color: #0F172A;
      transition: border-color 0.15s, box-shadow 0.15s;
      outline: none;
    }
    .gov-input:focus {
      border-color: #046A38;
      box-shadow: 0 0 0 3px rgba(4, 106, 56, 0.15);
    }
    .gov-input::placeholder {
      color: #94A3B8;
    }

    /* Primary submit button */
    .gov-btn {
      background-color: #046A38;
      color: #FFFFFF;
      font-weight: 600;
      border-radius: 6px;
      padding: 0.7rem 1.25rem;
      font-size: 0.925rem;
      transition: background-color 0.15s, box-shadow 0.15s;
    }
    .gov-btn:hover {
      background-color: #03532C;
      box-shadow: 0 2px 8px rgba(4, 106, 56, 0.3);
    }

    /* Tab visibility */
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Watermark background seal */
    .bg-gov-pattern {
      background-color: #F1F5F9;
      background-image: radial-gradient(#CBD5E1 0.8px, transparent 0.8px);
      background-size: 20px 20px;
    }
  </style>
</head>
<body class="bg-gov-pattern min-h-screen flex flex-col justify-between">

  <!-- ── 1. Top Flag Green Strip (Government of Sindh Header) ────────────────── -->
  <div class="bg-govGreen text-white text-xs py-1.5 px-4 sm:px-8 border-b-2 border-govGold">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2 font-medium">
        <!-- Pakistan / Sindh Star & Crescent SVG -->
        <svg class="w-4 h-4 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14.5c-2.48 0-4.5-2.02-4.5-4.5s2.02-4.5 4.5-4.5c.84 0 1.63.24 2.3.64-.78.43-1.3 1.27-1.3 2.24 0 1.38 1.12 2.5 2.5 2.5.45 0 .87-.12 1.24-.33-.67 2.29-2.77 3.95-4.74 3.95zm3.5-5.5l-1.12-.81.43-1.32-1.12.81-1.12-.81.43 1.32-1.12.81 1.39.01.43 1.32.43-1.32 1.39-.01z"/>
        </svg>
        <span>حکومتِ سندھ &nbsp;|&nbsp; Government of Sindh</span>
      </div>
      <div class="hidden sm:flex items-center gap-4 text-white/90 text-[11px]">
        <span>School Education &amp; Literacy Department (SELD)</span>
        <span class="text-white/40">&bull;</span>
        <span class="font-semibold text-govGold">District RSU <?= APP_DISTRICT ?></span>
      </div>
    </div>
  </div>

  <!-- ── 2. Official SELD / RSU Branding Header ──────────────────────────────── -->
  <header class="bg-govNavy text-white shadow-md border-b border-govNavy-light">
    <div class="max-w-6xl mx-auto px-4 sm:px-8 py-3 sm:py-4 flex items-center justify-between gap-4">
      
      <!-- Official RSU Wide Logo from rsusindh.gov.pk -->
      <div class="flex items-center gap-3 sm:gap-4">
        <a href="<?= BASE_URL ?>/" class="flex items-center gap-3">
          <img src="<?= BASE_URL ?>/assets/images/rsu-logo-wide.png"
               alt="RSU Sindh Logo"
               class="h-10 sm:h-12 w-auto object-contain"
               onerror="this.style.display='none'; document.getElementById('fallback-logo').style.display='flex';"/>
          
          <!-- Fallback if image not loaded -->
          <div id="fallback-logo" style="display:none;" class="items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-govGreen flex items-center justify-center text-white font-bold text-base border border-govGold">
              RSU
            </div>
            <div>
              <div class="text-base font-bold tracking-tight text-white leading-tight">REFORM SUPPORT UNIT</div>
              <div class="text-xs text-govGold">School Education &amp; Literacy Department</div>
            </div>
          </div>
        </a>
      </div>

      <!-- District Badge -->
      <div class="text-right">
        <div class="inline-flex items-center gap-2 bg-govNavy-dark/80 border border-white/10 rounded-md px-3 py-1.5 shadow-inner">
          <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
          <div>
            <div class="text-xs font-bold text-white tracking-wide uppercase">District RSU Portal</div>
            <div class="text-[10px] text-govGold font-medium"><?= APP_DISTRICT ?></div>
          </div>
        </div>
      </div>

    </div>
  </header>

  <!-- ── 3. Main Login Form Section ──────────────────────────────────────────── -->
  <main class="flex-1 flex items-center justify-center p-4 sm:p-8">
    <div class="w-full max-w-md">

      <!-- Official Portal Card -->
      <div class="gov-card overflow-hidden">
        
        <!-- Top Green & Gold Accent Stripe -->
        <div class="h-1.5 bg-govGreen w-full"></div>
        <div class="h-0.5 bg-govGold w-full"></div>

        <!-- Card Header with Emblem -->
        <div class="p-6 pb-4 border-b border-slate-200 text-center bg-slate-50/70">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white border border-slate-200 shadow-sm mb-3">
            <img src="<?= BASE_URL ?>/assets/images/rsu-favicon.png"
                 alt="Sindh Seal"
                 class="w-8 h-8 object-contain"
                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' fill=\'%23046A38\' viewBox=\'0 0 24 24\'><path d=\'M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z\'/></svg>'"/>
          </div>
          <h1 class="text-lg font-bold text-govNavy-dark tracking-tight">
            District Portal Authentication
          </h1>
          <p class="text-xs text-slate-500 mt-1">
            School Education &amp; Literacy Department &bull; <?= APP_DISTRICT ?>
          </p>
        </div>

        <!-- Role Tabs (Admin vs School) -->
        <div class="flex border-b border-slate-200 bg-slate-100/50">
          <button id="tab-admin" type="button" onclick="switchLoginTab('admin')"
                  class="gov-tab-btn flex-1 py-3 px-3 text-xs sm:text-sm flex items-center justify-center gap-2 <?= ($active_tab === 'admin') ? 'active' : '' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <span>District Admin</span>
          </button>
          <button id="tab-school" type="button" onclick="switchLoginTab('school')"
                  class="gov-tab-btn flex-1 py-3 px-3 text-xs sm:text-sm flex items-center justify-center gap-2 <?= ($active_tab === 'school') ? 'active' : '' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            <span>School Portal (ہیڈ ماسٹر لاگ ان)</span>
          </button>
        </div>

        <!-- Card Body -->
        <div class="p-6">

          <!-- Notice / Timeout Alert -->
          <?php if ($reason_message): ?>
            <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-900 p-3 rounded-r text-xs mb-4">
              <div class="font-semibold">تنبہیہ (Notice)</div>
              <div><?= htmlspecialchars($reason_message, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endif; ?>

          <!-- Error Alert -->
          <?php if ($error_message && $error_message !== 'school_db_pending'): ?>
            <div class="bg-red-50 border-l-4 border-red-600 text-red-800 p-3 rounded-r text-xs mb-4">
              <div class="font-semibold">لاگ ان ناکام (Authentication Failed)</div>
              <div><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endif; ?>

          <!-- ── TAB 1: DISTRICT ADMIN LOGIN ────────────────────────────────── -->
          <div id="content-admin" class="tab-content <?= ($active_tab === 'admin') ? 'active' : '' ?>">
            <form method="POST" action="<?= BASE_URL ?>/login.php" onsubmit="handleLoginSubmit(this)">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"/>
              <input type="hidden" name="login_type" value="admin"/>

              <!-- Username Field -->
              <div class="mb-4">
                <label for="admin-user" class="block text-xs font-semibold text-slate-700 mb-1.5">
                  Admin Username (یوزر نیم)
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                  </span>
                  <input type="text" id="admin-user" name="username"
                         class="gov-input"
                         placeholder="Enter your username"
                         value="<?= ($active_tab === 'admin' && isset($_POST['username'])) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
                         autocomplete="username"
                         required autofocus/>
                </div>
              </div>

              <!-- Password Field -->
              <div class="mb-5">
                <div class="flex items-center justify-between mb-1.5">
                  <label for="admin-pass" class="block text-xs font-semibold text-slate-700">
                    Password (پاس ورڈ)
                  </label>
                </div>
                <div class="relative">
                  <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                    </svg>
                  </span>
                  <input type="password" id="admin-pass" name="password"
                         class="gov-input pr-10"
                         placeholder="Enter account password"
                         autocomplete="current-password"
                         required/>
                  <button type="button" onclick="togglePasswordView('admin-pass', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600" aria-label="Toggle password">
                    <svg class="w-4 h-4 eye-show" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="w-4 h-4 eye-hide hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07L1 1l22 22"/>
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Submit Button -->
              <button type="submit" class="gov-btn w-full flex items-center justify-center gap-2">
                <span id="btn-label">Sign In to Dashboard &rarr;</span>
                <span id="btn-spin" class="hidden">براہ کرم انتظار کریں... (Signing in...)</span>
              </button>
            </form>
          </div>

          <!-- ── TAB 2: SCHOOL LOGIN (HEAD MASTER CNIC) ─────────────────────── -->
          <div id="content-school" class="tab-content <?= ($active_tab === 'school') ? 'active' : '' ?>">
            <div class="bg-emerald-50 border-l-4 border-govGreen p-3 rounded-r mb-4 text-xs text-emerald-950">
              <div class="font-bold flex items-center gap-1.5 text-govGreen-dark">
                <svg class="w-4 h-4 text-govGreen flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>ہیڈ ماسٹر / مسٹریس پورٹل لاگ ان</span>
              </div>
              <div class="mt-1 text-[11px] leading-relaxed text-emerald-900">
                ہر اسکول کا ہیڈ ماسٹر اپنے <strong>شناختی کارڈ نمبر (CNIC)</strong> یا اسکول کے <strong>SEMIS Code</strong> سے لاگ ان کر سکتا ہے — دونوں قابل قبول ہیں۔
              </div>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/login.php" onsubmit="handleLoginSubmit(this)">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"/>
              <input type="hidden" name="login_type" value="school"/>

              <!-- Head Master CNIC Field -->
              <div class="mb-4">
                <div class="flex items-center justify-between mb-1.5">
                  <label for="school-cnic" class="block text-xs font-semibold text-slate-700">
                    CNIC / SEMIS Code (شناختی کارڈ یا سیمس کوڈ)
                  </label>
                  <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Either Accepted</span>
                </div>
                <div class="relative">
                  <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><line x1="15" y1="8" x2="17" y2="8"/><line x1="15" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="17" y2="16"/>
                    </svg>
                  </span>
                  <input type="text" id="school-cnic" name="hm_cnic"
                         class="gov-input font-mono"
                         placeholder="CNIC: 41302-1849201-3  یا  SEMIS: 403010001"
                         value="<?= ($active_tab === 'school' && isset($_POST['hm_cnic'])) ? htmlspecialchars($_POST['hm_cnic'], ENT_QUOTES, 'UTF-8') : '' ?>"
                         autocomplete="username"
                         required/>
                </div>
                <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-3">
                  <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> HM CNIC (13 digits)</span>
                  <span class="text-slate-300">|</span>
                  <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-500 inline-block"></span> School SEMIS Code (numeric)</span>
                </div>
              </div>

              <!-- Password Field -->
              <div class="mb-5">
                <div class="flex items-center justify-between mb-1.5">
                  <label for="school-pass" class="block text-xs font-semibold text-slate-700">
                    Password (پاس ورڈ)
                  </label>
                </div>
                <div class="relative">
                  <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                    </svg>
                  </span>
                  <input type="password" id="school-pass" name="school_password"
                         class="gov-input pr-10"
                         placeholder="Enter your password"
                         autocomplete="current-password"
                         required/>
                  <button type="button" onclick="togglePasswordView('school-pass', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600" aria-label="Toggle password">
                    <svg class="w-4 h-4 eye-show" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="w-4 h-4 eye-hide hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07L1 1l22 22"/>
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Submit Button -->
              <button type="submit" class="gov-btn w-full flex items-center justify-center gap-2">
                <span id="school-btn-label">Sign In to School Portal &rarr;</span>
                <span id="school-btn-spin" class="hidden">براہ کرم انتظار کریں... (Opening School Portal...)</span>
              </button>
            </form>
          </div>

        </div><!-- /card body -->

        <!-- Official Portal Footer inside Card -->
        <div class="bg-slate-50 border-t border-slate-200 px-6 py-2.5 text-[11px] text-slate-500 text-center">
          <span>Official SELD District Portal &bull; Government of Sindh</span>
        </div>

      </div><!-- /gov-card -->

      <!-- Official Department & Developer Notice Below Card -->
      <div class="mt-4 text-center text-xs text-slate-500 leading-relaxed">
        A Project of <strong>Reform Support Unit (RSU)</strong>, School Education &amp; Literacy Department, Government of Sindh.<br/>
        Maintained &amp; Developed by <a href="https://rafayqazi.github.io/ar-portfolio/" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-700 hover:text-govNavy underline underline-offset-2">Developer Abdul Rafay</a>
      </div>

    </div>
  </main>

  <!-- ── 4. Official Departmental Footer ────────────────────────────────────── -->
  <footer class="bg-govNavy-dark text-white/75 text-xs border-t-2 border-govGreen py-4 px-4 sm:px-8">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
      <div>
        &copy; <?= date('Y') ?> <strong>School Education &amp; Literacy Department</strong>, Government of Sindh.
        <div class="text-[11px] text-white/50 mt-0.5">Reform Support Unit (RSU) &bull; <?= APP_DISTRICT ?> Office</div>
      </div>
      <div class="flex items-center gap-4 text-[11px] text-white/70">
        <a href="https://rsusindh.gov.pk/" target="_blank" class="hover:text-govGold transition-colors">Official RSU Portal</a>
        <span>&bull;</span>
        <a href="https://seld.sindh.gov.pk/" target="_blank" class="hover:text-govGold transition-colors">SELD Sindh</a>
      </div>
    </div>
  </footer>

  <!-- ─── Developer Branding Strip ───────────────────────────────────────── -->
  <div class="bg-[#061528] text-white/50 text-[11px] py-2.5 px-4 flex flex-col sm:flex-row items-center justify-center gap-1.5 sm:gap-3 border-t border-white/5">
    <span class="flex items-center gap-1.5">
      <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="opacity-60"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
      <span>Developed by</span>
      <a href="https://rafayqazi.github.io/ar-portfolio/" target="_blank" rel="noopener noreferrer"
         class="font-semibold text-white/80 hover:text-white transition-colors underline underline-offset-2 decoration-white/20 hover:decoration-white/60">
        Abdul Rafay Qazi
      </a>
    </span>
    <span class="hidden sm:inline text-white/20">&bull;</span>
    <a href="tel:03710273699" class="flex items-center gap-1 hover:text-white/80 transition-colors">
      <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .03h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
      0371-0273699
    </a>
  </div>

  <!-- ── JavaScript Handlers ────────────────────────────────────────────────── -->
  <script>
    // Tab switcher between Admin and School
    function switchLoginTab(tab) {
      const tabAdmin = document.getElementById('tab-admin');
      const tabSchool = document.getElementById('tab-school');
      const contentAdmin = document.getElementById('content-admin');
      const contentSchool = document.getElementById('content-school');

      if (tab === 'admin') {
        tabAdmin.classList.add('active');
        tabSchool.classList.remove('active');
        contentAdmin.classList.add('active');
        contentSchool.classList.remove('active');
        const userInp = document.getElementById('admin-user');
        if (userInp) userInp.focus();
      } else {
        tabSchool.classList.add('active');
        tabAdmin.classList.remove('active');
        contentSchool.classList.add('active');
        contentAdmin.classList.remove('active');
        const cnicInp = document.getElementById('school-cnic');
        if (cnicInp) cnicInp.focus();
      }
    }

    // Toggle password visibility
    function togglePasswordView(inputId, btn) {
      const input = document.getElementById(inputId);
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      
      const showIcon = btn.querySelector('.eye-show');
      const hideIcon = btn.querySelector('.eye-hide');
      if (showIcon && hideIcon) {
        showIcon.classList.toggle('hidden', isPassword);
        hideIcon.classList.toggle('hidden', !isPassword);
      }
    }

    // Form submission state
    function handleLoginSubmit(form) {
      const btn = form.querySelector('button[type="submit"]');
      if (!btn) return;
      const labels = btn.querySelectorAll('#btn-label, #school-btn-label');
      const spins = btn.querySelectorAll('#btn-spin, #school-btn-spin');
      labels.forEach(l => l.classList.add('hidden'));
      spins.forEach(s => s.classList.remove('hidden'));
      btn.disabled = true;
      btn.classList.add('opacity-75', 'cursor-not-allowed');
    }
  </script>
</body>
</html>
