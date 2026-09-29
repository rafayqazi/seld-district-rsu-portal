---
name: lsu-portal
description: Comprehensive standards, design system tokens, database preparation guidelines, and architectural rules for the LSU Portal. Reference this skill whenever modifying, extending, or building new features for the LSU District Education Portal.
---

# LSU District Education Portal — Developer & Agent Skill

This skill provides complete instructions, design tokens, security patterns, and UI/UX rules for the **District Reform Support Unit (RSU) Education Portal — Tando Allahyar District (SELD, Sindh)**.

> **Active District:** Tando Allahyar District (Sindh Education & Literacy Department)

---

## 1. Quick Technical Reference

- **Language:** Core PHP (Vanilla PHP 8.0+)
- **Styling:** Tailwind CSS via CDN + inline `tailwind.config`
- **Session Keys:**
  - `$_SESSION['logged_in']` (bool)
  - `$_SESSION['lsu_user_type']` ('admin' | 'school')
  - `$_SESSION['lsu_user_handle']` (string — username or SEMIS code)
  - `$_SESSION['lsu_username']` (string — display name)
  - `$_SESSION['lsu_user_id']` (string)
  - `$_SESSION['lsu_avatar']` (string — relative URL to avatar image)
  - `$_SESSION['csrf_token']` (string)
- **Base URL Route:** `/LSU-PORTAL/`

---

## 2. Page Creation Workflow

When adding a new page (e.g., `admin/teachers.php`), follow this exact standard layout sequence:

```php
<?php
/**
 * admin/teachers.php — Teacher Management
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'teachers';
$page_title  = 'Teacher Management — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}}</script>
<style>
body{font-family:'Inter',system-ui,sans-serif;}
.sidebar-link{transition:background .15s;}.sidebar-link:hover{background:rgba(255,255,255,.08);}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E;}
.btn-primary{background:#123B63;color:#fff;transition:background .15s;}.btn-primary:hover{background:#0B2946;}
.btn-secondary{background:#F5F7FA;color:#172033;border:1px solid #E2E8F0;transition:background .15s;}.btn-secondary:hover{background:#E2E8F0;}
.table-row:hover{background:#F8FAFC;}
#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1);}#overlay{transition:opacity .25s;}
</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative">
  <span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span>
  <span class="hidden sm:block opacity-75"><?= APP_NAME ?></span>
</div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">
<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>
<main class="flex-1 overflow-y-auto p-4 md:p-6">
  <!-- Page Content Here -->
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</div>
</div>
<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});
</script>
</body>
</html>
```

---

## 3. Sidebar Navigation Rules

When a new page is added, update the appropriate sidebar component (`includes/sidebar.php` for Admin or `school/includes/sidebar.php` for School):

```php
<a href="/LSU-PORTAL/admin/new-page.php" class="sidebar-link <?= ($active_page === 'new-page') ? 'active text-white font-medium' : 'text-white/80 hover:text-white' ?> flex items-center gap-3 px-3 py-2 rounded text-sm">
  <!-- SVG Icon -->
  <span>Label</span>
</a>
```

### Active Pages Registry
- **Admin Portal:** `'dashboard'`, `'schools'`, `'school-profile'`, `'students'`, `'at-risk'`, `'settings'`
- **School Portal:** `'dashboard'`, `'profile'`, `'students'`, `'at-risk'`, `'settings'`

---

## 4. Toast Notifications (MANDATORY — No native alert())

**Never use native `alert()`.** All feedback uses the global toast system in `includes/footer.php`.

### JavaScript
```js
showToast('Record saved successfully!', 'success');  // Green
showToast('Failed to save. Try again.', 'error');    // Red
```

### PHP Flash Variable (auto-toast on page load)
```php
$alert_message = 'Settings saved!';
$alert_type    = 'success'; // or 'danger'
// footer.php auto-reads these and fires showToast()
```

### POST-Redirect-GET Pattern
```php
header('Location: /LSU-PORTAL/admin/settings.php?msg=saved');
exit;
// Then at page top:
if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $alert_message = 'Settings updated!'; $alert_type = 'success';
}
```

---

## 5. Custom Confirmation Dialogs (No native confirm())

Use `confirmFormSubmit()` or `customConfirm()` from `includes/footer.php`.

### Form onsubmit (most common)
```html
<form method="POST"
  onsubmit="return confirmFormSubmit(event, this, 'Delete this record?', {title: 'Delete Record', okText: 'Yes, Delete'});">
```

### Inline JS trigger
```js
customConfirm('Reset all data?', function() {
    document.getElementById('my-form').submit();
}, { title: 'Confirm Reset', okText: 'Yes, Reset', isDanger: true });
```

---

## 6. Dynamic Taluka System

Talukas are stored in `/data/talukas.csv` via `ExcelDB` and are district-aware.

```php
$talukas = ExcelDB::getTalukas(true); // active only
```

### Dropdown
```html
<select name="taluka">
  <option value="">Select Taluka</option>
  <?php foreach ($talukas as $t): ?>
  <option value="<?= e($t) ?>"><?= e($t) ?></option>
  <?php endforeach; ?>
</select>
```

---

## 7. ExcelDB Data Layer

- **Engine:** `includes/excel_db.php` (`ExcelDB` static class)
- **Storage:** `/data/*.csv` protected by `.htaccess`
- **Tables:** `schools.csv`, `students.csv`, `at_risk.csv`, `users.csv`, `settings.csv`, `talukas.csv`

### Common Operations
```php
ExcelDB::all('schools');
ExcelDB::find('users', 'username', $username);
ExcelDB::insert('schools', $rowArray);
ExcelDB::update('schools', 'id', $id, $data);
ExcelDB::delete('schools', 'id', $id);
ExcelDB::getSetting('district');
ExcelDB::updateSettings(['key' => 'value']);
```

---

## 8. Dynamic Settings (`data/settings.csv`)

| Key | Description |
|-----|-------------|
| `district` | Active district (e.g., "Tando Allahyar District") |
| `app_name` | Portal name |
| `department` | Department full name |
| `academic_year` | Current academic year |
| `high_risk_threshold` | Dropout risk threshold % (default 60) |
| `contact_email` | District office email |
| `contact_phone` | Helpline phone |

---

## 9. Security Checklist

- [x] Session regeneration on login (`session_regenerate_id(true)`)
- [x] `HttpOnly` and `SameSite=Strict` cookie flags
- [x] CSRF token on all POST forms: `e(csrf_token())`
- [x] CSRF verification before processing: `verify_csrf_token($_POST['csrf_token'] ?? '')`
- [x] HTML-escape ALL output: `e($var)`
- [x] Password hashing with `password_hash()` & verification with `password_verify()`
- [x] Protected routes redirect to login if unauthenticated
- [x] Zero hardcoded demo passwords or credentials shown in UI
- [x] Internal data tools (`excel-manager.php`) restricted via redirect stubs
- [x] NO native `alert()` or `confirm()` — use `showToast()` and `customConfirm()`

---

## 10. Automated CI/CD & Deployment (GitHub Actions)

- **Workflow:** `.github/workflows/deploy.yml` triggers on every `git push` to `master` or `main`.
- **Target:** InfinityFree FTP (`ftpupload.net`) synced to `/htdocs/`.
- **Action:** `SamKirkland/FTP-Deploy-Action@v4.3.5`
- **Secrets:**
  - `FTP_SERVER`: `ftpupload.net`
  - `FTP_USERNAME`: `if0_43042000`
  - `FTP_PASSWORD`: InfinityFree FTP password
- **Exclusions:** `.git`, `.github`, `.agents`, `scratch`, `task.md`
- **Local Fallback:** Run `C:\xampp\php\php.exe scratch/deploy_live.php` for direct one-command local deploy.

