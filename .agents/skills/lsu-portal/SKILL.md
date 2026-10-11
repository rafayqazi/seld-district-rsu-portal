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
- **Admin Portal:** `'dashboard'`, `'schools'`, `'school-profile'`, `'at-risk-schools'`, `'staff'`, `'complaints'`, `'messages'`, `'forms'`, `'announcements'`, `'settings'`
- **School Portal:** `'dashboard'`, `'profile'`, `'staff'`, `'at-risk'`, `'complaints'`, `'messages'`, `'forms'`, `'settings'`

> **⚠️ MANDATORY:** Whenever you add/remove a page, column, or feature, update BOTH this file AND `AGENTS.md` (Revision History). This is enforced to keep future AI agents in sync with the codebase.

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
- **Active Tables:** `schools`, `school_staff`, `school_risks`, `users`, `settings`, `talukas`, `complaints`, `complaint_replies`, `admin_messages`, `admin_message_replies`, `forms`, `form_assignments`, `form_responses`, `announcements`
- **Deprecated (never read/write):** `students`, `attendance`, `at_risk`

### School Staff Table — Key Columns (`school_staff.csv`)
| Column | Description |
|---|---|
| `id` | Unique auto-increment record ID |
| `semis_code` | Linked school SEMIS code |
| `personal_no` | 8-digit official SELD Employee ID (e.g. 10482910) |
| `full_name` | Full official employee name |
| `cnic` | 13-digit verified CNIC (e.g. 41302-XXXXXXX-X) |
| `gender` | Male / Female |
| `staff_type` | Teaching / Non-Teaching |
| `designation` | Official cadre (PST, JEST, ECT, HST, SS, HM, Junior Clerk, Lab Assistant, Peon, etc.) |
| `bps_scale` | Pay scale (BPS-01 through BPS-19) |
| `qualification_academic` | Matriculation through PhD |
| `qualification_professional` | B.Ed, M.Ed, PTC, CT, IT Diploma, None |
| `contact_phone` | Mobile phone number |
| `appointment_date` | Date of government appointment |
| `status` | Active / On Leave / Transferred / Deputation |

### Schools Table — Key Columns
| Column | Notes |
|---|---|
| `semis_code` | Unique numerical string — primary lookup key (immutable by HM in School Portal, editable only by District Admin in Admin Portal) |
| `cnic` | HM CNIC — immutable unique identifier, login username for school portal (editable only by District Admin) |
| `enrollment` | Total student enrollment (auto-sum: `enrollment_boys + enrollment_girls`) |
| `enrollment_boys` | Boys enrolled count |
| `enrollment_girls` | Girls enrolled count |
| `building_structure` | Good Condition / Needs Repair / Dangerous / Unsafe / Condemned / Closed |
| `drainage_sewerage` | Functional Drainage / Partial Drainage / Broken / None |
| `flood_prone` | No / Yes |
| `furniture_condition` | Adequate / Shortage / None Available |

### Auto-Risk Flagging
Call `ExcelDB::autoFlagSchoolRisks(string $semisCode): int` after any facility/profile save.
Evaluates 10 infrastructure rules and inserts new `school_risks` records automatically.
Does NOT duplicate unresolved risks for the same school+category.
Admin resolves risks manually via `admin/at-risk-schools.php`.

### Common Operations
```php
ExcelDB::all('schools');
ExcelDB::find('users', 'username', $username);
ExcelDB::insert('schools', $rowArray);
ExcelDB::update('schools', 'id', $id, $data);
ExcelDB::delete('schools', 'id', $id);
ExcelDB::getSetting('district');
ExcelDB::updateSettings(['key' => 'value']);
ExcelDB::autoFlagSchoolRisks('403010004'); // Call after every facility save
ExcelDB::calculateSchoolProfileCompletion('403010001'); // Returns percentage, missing_fields, etc.
```

### Profile Progress Filter & Bulk Printing (`admin/schools.php`)
- **Filter Dropdown:** `sch-progress` options: `Complete (100%)` and `Below 100%`.
- **Dynamic Bulk Actions:**
  - `100%` selection enables **Print Appreciation Certificates** (1 page per school, official SELD Sanad-e-Tahseen format with HM Name, CNIC, SEMIS, Taluka, and dual signatures).
  - `Below 100%` selection enables **Print Show Cause Notices** (1 page per school, SELD letterhead, deficit audit checklist table, 7-day compliance directive, and dual signatures).
- **Print Optimization:** Formatted with CSS `@page { size: A4 portrait; }`, `page-break-after: always`, and print container isolation.

### Interactive KPI Filter Cards (`admin/at-risk-schools.php`)
- **KPI Summary Cards:** Critical, High Risk, Medium, Resolved cards are interactive buttons with hover animations and active ring highlights.
- **Click Behavior:** Clicking any card filters the registry table by that severity/status; clicking the active card again resets to All.
- **Category Pills:** Risk Category Breakdown badges are clickable buttons to instantly filter by that specific category.

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

---

## 11. District Dashboard Metrics & KPI Navigation Standards

- All Admin Dashboard KPIs must be **strictly dynamic** calculated directly from active CSV records (`schools.csv`, `school_risks.csv`, `complaints.csv`):
  - **Total Schools:** count of `schools.csv` records (links to `admin/schools.php`).
  - **Total Students:** exact sum of `enrollment` across all registered schools with real gender breakdown (links to `admin/schools.php`).
  - **Teachers & Staff:** exact sum of `teachers` and `non_teaching` columns (links to `admin/schools.php`).
  - **Critical Schools:** non-resolved `critical` severity risk records (links to `admin/at-risk-schools.php?severity=critical`).
  - **At-Risk Registry:** total unresolved risks vs resolved count (links to `admin/at-risk-schools.php`).
  - **Complaints:** total grievance count and pending ticket badge (links to `admin/complaints.php`).
- All donut charts and performance indicator progress bars must compute their values and SVG stroke coordinates dynamically on page render.
- The admin header's global school search matches school names and SEMIS codes, shows up to eight ranked matches, supports keyboard selection, and opens the selected school profile.
- Admin and School notification bells display the current numeric unread count; the shared polling script synchronizes bell, dropdown, and sidebar badges and logs request failures for diagnosis.

- **Secrets:**
  - `FTP_SERVER`: `ftpupload.net`
  - `FTP_USERNAME`: `if0_43042000`
  - `FTP_PASSWORD`: InfinityFree FTP password
- **Exclusions:** `.git`, `.github`, `.agents`, `scratch`, `task.md`
- **Local Fallback:** Run `C:\xampp\php\php.exe scratch/deploy_live.php` for direct one-command local deploy.

---

## 12. Direct Messaging System Architecture

### Pages
| Page | Role | Description |
|---|---|---|
| `admin/messages.php` | Admin | Inbox, metrics, filter, initiate new conversations |
| `admin/message-thread.php?thread=MSG-XXXX` | Admin | Real-time chat thread view & reply |
| `school/messages.php` | School HM | Read-only inbox of admin-initiated threads |
| `school/message-thread.php?thread=MSG-XXXX` | School HM | Real-time reply in admin thread |

### CSV Tables
- **`admin_messages`**: Thread records (`thread_id`, `semis_code`, `school_name`, `taluka`, `subject`, `status`, `created_by`, `created_at`, `updated_at`, `unread_school`, `unread_admin`)
- **`admin_message_replies`**: Individual messages (`id`, `thread_id`, `sender_role`, `sender_name`, `message`, `created_at`)

### Key Rules
- **Only Admin can initiate** a new message thread — schools can only reply.
- Thread status: `Open` (replies allowed) | `Closed` (no replies until reopened by admin).
- `unread_admin = '1'` when school replies. `unread_school = '1'` when admin sends/replies.
- Marked as read when thread-view page loads (before rendering).
- Admin can permanently delete a conversation from the inbox or thread view; confirmation dialogs must preserve and invoke their callback after closing so the protected deletion POST is submitted.

### Searchable School Picker (admin/messages.php modal)
- The "Select School" field is a **custom JS picker** with a search input + scrollable list.
- Each option renders as a `div.school-opt` with `data-semis`, `data-name`, `data-search` attributes.
- `filterSchools(query)` hides non-matching `.school-opt` rows.
- `selectSchool(semis, name)` fills the hidden `<input name="semis_code">` and shows a selection pill.
- **Auto-open from URL:** Pass `?compose=1&semis=XXXXXX` to auto-open the modal with school pre-selected.

### Direct Message from School Profile
- `admin/school-profile.php` header has a green **"Direct Message"** button (`#btnDirectMessage`).
- It links to `admin/messages.php?compose=1&semis=<SEMIS>`.
- On page load the JS in `messages.php` reads URL params and calls `openNewMessageModal(semis, name)`.

### Real-Time Chat (api/thread-messages.php)
- **GET** `?thread=MSG-XXXX&after_id=N` → returns new messages since ID N as JSON.
- **POST** with `action=post_reply` → appends message, marks unread for other party, returns bubble data.
- Both thread pages poll every **2.5 seconds** via `setInterval(pollNewMessages, 2500)`.
- New incoming messages append chat bubbles via `appendMessageBubble(msg)` and trigger `playNotificationChime()`.

### Audio Chime Architecture (assets/js/notifications.js)
- Uses **Web Audio API** to synthesize a two-tone "tling" bell — no audio files needed.
- `initAudioContext()` pre-creates and unlocks `AudioContext` on `click`, `pointerdown`, `keydown`, `touchstart`.
- `playNotificationChime()` always calls `audioContext.resume().then(playWhenReady)` before oscillating.
- This is critical: browsers suspend AudioContext if created without a user gesture. Admin portals are especially affected since page load precedes interaction.
- `check-complaints.php` polling runs every **8 seconds** and triggers chime + toast when unread count increases.

### ExcelDB Helpers
```php
ExcelDB::getUnreadMessagesCount('admin');                  // count admin unread message threads
ExcelDB::getUnreadMessagesCount('school', $semisCode);     // count school unread threads
ExcelDB::getRepliesForThread($threadId);                   // get all replies for a thread
ExcelDB::addMessageReply($threadId, $role, $name, $msg);   // append a reply + mark unread
ExcelDB::deleteMessageThread($threadId);                   // delete thread + all replies
ExcelDB::generateMessageThreadId();                        // generate MSG-XXXXXXXX ID
```

## 13. District School Forms

### Pages
| Page | Role | Description |
|---|---|---|
| `admin/forms.php` | Admin | Draft/published form list, deadline controls and progress overview |
| `admin/form-builder.php` | Admin | Create/edit drafts and publish to selected schools or one school level |
| `admin/form-report.php?id=FORM-...` | Admin | Per-school submission status, answer review and CSV export |
| `school/forms.php` | School HM | View assigned forms and submit/update the school's response |

### CSV Tables
- **`forms`**: One record per form (`form_id`, `title`, `description`, `questions_json`, `target_type`, `target_value`, `status`, `created_by`, `created_at`, `updated_at`, `published_at`, `closes_at`).
- **`form_assignments`**: Snapshot of each recipient school (`id`, `form_id`, `semis_code`, `school_name`, `assigned_at`, `unread_school`).
- **`form_responses`**: One response per form and school (`id`, `form_id`, `semis_code`, `school_name`, `answers_json`, `submitted_at`, `updated_at`).

### Rules
- Drafts can be edited. Publishing requires a non-empty title, 1–50 valid questions, at least one recipient, and a duration of 1–365 days.
- Supported question types: short text, paragraph, number, date, radio, checkbox and dropdown. Choice questions require 2–30 options; required/optional validation is enforced server-side.
- Recipients are snapshotted at publish time: either all schools in one level or one/more selected SEMIS codes. School accounts can access only their own assigned forms.
- Form questions and recipients are locked after publishing. Admin can close a published form immediately or extend its deadline by 1–365 days; extending reopens a closed/expired form.
- Deadlines are enforced at response time as well as in the UI. One response per school is retained and can be updated until the form closes.
- School sidebar/header badges and live notification polling use each assignment's `unread_school` flag. Opening an assigned form marks its notification as read.
- Admin reports show submitted and outstanding schools, answer details, filters and an Excel-compatible CSV export. CSV formula-leading values are escaped.
- The form report can print one show-cause notice per non-submitting school in the currently filtered results. Each A4 notice includes school leadership and SEMIS details, form title and portal deadline, seven-day compliance instructions, and district signature blocks. Notices print in an isolated A4 document with one page per school so portal overflow/layout styles do not clip output.

## 14. District Announcements & Popups

### Page and Data
- `admin/announcements.php` replaces the inactive Documents link in the Admin sidebar. Admins create and publish an announcement with a title and message, targeting either one school by SEMIS code or all schools at one level.
- The `announcements` CSV table stores `announcement_id`, `title`, `message`, `image_url`, `target_type`, `target_value`, `status`, `created_by`, `created_at`, and `closed_at`.

### Rules
- Only active announcements are shown, and recipients are matched against their current SEMIS code or exact school level.
- The shared School Portal header displays matching announcements in a prominent popup across all school pages. Dismissal lasts for the current school login; a fresh login displays still-active announcements again.
- The popup header displays the portal logo. Admins may optionally upload a JPG, PNG, WEBP, or GIF image (maximum 5 MB); uploaded files use randomized filenames under `assets/uploads/announcements/` and are MIME- and image-validated before storage.
- Admins close announcements with a CSRF-protected action. Closed announcements stop appearing on subsequent page requests and can then be permanently deleted with a separate CSRF-protected, confirmation-guarded action. Deleting a closed announcement also removes its generated upload image when the stored path matches the announcement upload naming scheme.
