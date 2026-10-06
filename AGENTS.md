# LSU Portal — AI Agent Instructions & Architectural Rules

> **Project:** Sindh Education & Literacy Department (SELD) — District RSU Portal (Tando Allahyar District)  
> **Architecture:** Core PHP (Vanilla PHP 8.x) + Tailwind CSS + Vanilla JavaScript  
> **Last Updated:** October 2026

This document is mandatory reading for all AI agents, models, and developers contributing to this repository. All future modifications, features, and refactorings must adhere to these established patterns.

> **⚠️ MANDATORY RULE FOR ALL AI AGENTS:** Whenever you make any structural change — adding or removing a page, table/column, feature, or key behavior — you **MUST** immediately update both `AGENTS.md` (Revision History) and `.agents/skills/lsu-portal/SKILL.md` (schema/feature sections). Failure to do so leaves the codebase knowledge stale and forces future agents to search the entire codebase unnecessarily.

---

## 1. Architectural Philosophy & Technology Stack

- **Backend:** Pure Core PHP (No Laravel, Symfony, or heavy frameworks). Keep code clean, modular, and fast.
- **Frontend Styling:** Tailwind CSS via CDN with customized design tokens.
- **Interactivity:** Lightweight Vanilla JavaScript (DOM APIs only — no jQuery).
- **Icons:** Inline SVG icons matching the 24x24 or 16x16 standard stroke-width 2.0.
- **Modularity:** Reusable UI components are separated into PHP partials in `/includes/`.

---

## 2. Directory Structure

```
LSU-PORTAL/
├── index.php                      # Root router: redirects to admin/dashboard.php or login.php
├── login.php                      # Unified login portal (Admin & School login tabs)
├── logout.php                     # Secure session destruction and redirection
│
├── admin/                         # Admin role-protected pages
│   ├── dashboard.php              # District KPI overview, enrollment by level chart, risk indicators
│   ├── schools.php                # School directory, search, filters, CSV import, credentials modal
│   ├── school-profile.php         # Single school deep-dive profile + inline edit modals (auto-flags risks)
│   ├── at-risk-schools.php        # Infrastructure risk registry (auto-populated from school profiles)
│   ├── complaints.php             # GRM complaint inbox and reply management
│   ├── messages.php               # Admin Direct Messaging — initiate conversations with any school
│   ├── message-thread.php         # Admin message thread view — reply & close/reopen conversation
│   ├── settings.php               # Portal settings, district config, taluka management
│   ├── students.php               # REDIRECT STUB → schools.php (deprecated)
│   └── attendance.php             # REDIRECT STUB → dashboard.php (deprecated)
│
├── school/                        # School Head Master portal (CNIC + password login)
│   ├── auth_guard.php             # School role authentication guard
│   ├── dashboard.php              # School-level KPIs, enrollment, facilities overview
│   ├── profile.php                # School profile & infrastructure editor (HM editable; triggers auto-risk)
│   ├── at-risk.php                # School's own infrastructure risk view
│   ├── complaints.php             # HM GRM complaint submission & reply
│   ├── messages.php               # School inbox for admin-sent messages (read & reply only)
│   ├── message-thread.php         # School message thread view & reply
│   ├── settings.php               # HM password change
│   ├── students.php               # REDIRECT STUB → dashboard.php (deprecated)
│   └── attendance.php             # REDIRECT STUB → dashboard.php (deprecated)
│   └── includes/
│       ├── sidebar.php            # School portal navigation
│       └── header.php             # School portal top bar
│
├── includes/                      # Reusable PHP components & guards
│   ├── auth.php                   # Authentication check, session security, CSRF helpers
│   ├── excel_db.php               # ExcelDB class (CSV engine + autoFlagSchoolRisks)
│   ├── sidebar.php                # Admin navigation sidebar with $active_page indicator
│   ├── header.php                 # Top app bar (district badge, profile, notifications)
│   └── footer.php                 # Standard copyright and department footer
│
├── config/
│   └── config.php                 # App constants, environment settings, DB config placeholders
│
├── data/                          # CSV data files (protected by .htaccess: Deny from all)
│   ├── schools.csv                # School registry + full facility & infrastructure data
│   ├── school_risks.csv           # Infrastructure risk registry (auto-flagged + manual)
│   ├── users.csv                  # Admin & school head master credentials
│   ├── talukas.csv                # District taluka list
│   ├── complaints.csv             # GRM complaint tickets
│   ├── complaint_replies.csv      # GRM conversation threads
│   ├── admin_messages.csv         # Admin-initiated direct message threads (admin-only initiation)
│   ├── admin_message_replies.csv  # Message replies within each direct thread
│   └── settings.csv               # Dynamic portal configuration
│
├── assets/                        # Static assets (images, logos, custom CSS overrides)
│   └── css/
│
├── .agents/                       # AI Agent knowledge base & skills
│   └── skills/
│       └── lsu-portal/
│           └── SKILL.md           # Deep-dive skill guidelines for AI models
│
└── AGENTS.md                      # System rules & coding guidelines (this file)
```

---

## 3. Security Guidelines (Mandatory)

All new endpoints and pages must implement the following security measures:

1. **Authentication Guard:**
   Every protected page in `admin/` (or future roles like `school/`) **MUST** include `auth.php` as the very first line:
   ```php
   <?php
   require_once dirname(__DIR__) . '/includes/auth.php';
   require_once dirname(__DIR__) . '/config/config.php';
   ```
2. **Session Security:**
   - Sessions are configured with `cookie_httponly = true`, `cookie_samesite = 'Strict'`.
   - `session_regenerate_id(true)` is executed upon login to prevent session fixation attacks.
3. **Cross-Site Request Forgery (CSRF):**
   - All `POST` forms must include a CSRF token: `<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">`.
   - Verify with `verify_csrf_token($_POST['csrf_token'] ?? '')` before processing mutations.
4. **Cross-Site Scripting (XSS):**
   - Never print raw user input or variable output directly. Always use the helper function `e($string)` defined in `config/config.php` (`htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`).
5. **Credentials & Database Migration:**
   - Temporary admin credentials:
     - **Username:** `admin`
     - **Password:** `admin` (verified via `password_verify` against bcrypt hash in `config/config.php`).
   - When connecting MySQL/MariaDB:
     - Use PDO with prepared statements: `PDO::ATTR_EMULATE_PREPARES => false`, `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`.
     - Never concatenate variables into SQL strings.

---

## 4. UI/UX & Design System

The portal follows the Sindh Government District Education aesthetic:

### Color Palette
- **Primary Navy:** `#123B63` (`bg-primary`, `text-primary`, borders)
- **Dark Navy:** `#0B2946` (`bg-primaryDark` for header strip & hover states)
- **Teal Accent:** `#0F766E` (`bg-secondary`, sidebar active borders, highlights)
- **Page Background:** `#F5F7FA` (`bg-background`)
- **Card Surface:** `#FFFFFF` (`bg-surface`)
- **Main Text:** `#172033` (`text-textMain`)
- **Muted Text:** `#64748B` (`text-muted`)
- **Borders:** `#E2E8F0` (`border-border`)
- **Semantic Colors:**
  - Success: `#15803D`
  - Warning / Moderate: `#D97706`
  - Danger / High Risk: `#DC2626`

### Navigation & Layout Rules
- **Sidebar Integration:** Include `sidebar.php` inside the layout wrapper.
- **Active Navigation State:** Define `$active_page` prior to loading the template:
  - `'dashboard'` → Dashboard
  - `'schools'` → Schools Directory
  - `'school-profile'` → School Profile
  - `'at-risk-schools'` → At-Risk Schools Registry
  - `'complaints'` → GRM Complaints
  - `'settings'` → Settings
- **Responsiveness:** Sidebar is collapsable on mobile with smooth transition and dark overlay backdrop (`#overlay`). Header contains the hamburger toggle button.

---

### Excel Database Architecture
- **Engine:** `includes/excel_db.php` (`ExcelDB` class)
- **File Storage:** `/data/` protected by Apache `.htaccess` (`Deny from all`)
- **Active Tables (6 core):**
  | Table | Description |
  |---|---|
  | `schools.csv` | School registry with full facility, infrastructure, and staffing data |
  | `school_risks.csv` | Infrastructure risk records (auto-flagged + admin-manual) |
  | `users.csv` | Admin and HM portal credentials |
  | `talukas.csv` | District taluka/sub-division registry |
  | `complaints.csv` | GRM grievance tickets |
  | `complaint_replies.csv` | Thread replies for each complaint ticket |
  | `settings.csv` | Dynamic portal configuration (district, academic year, thresholds) |
- **Deprecated Tables (no longer used, files may exist but are never read/written):** `students.csv`, `attendance.csv`, `at_risk.csv`.
- **Excel Compatibility:** Written with UTF-8 BOM (`\xEF\xBB\xBF`) and standard CSV delimiter for seamless double-click opening in Microsoft Excel.
- **Safety:** Atomic writes using `flock(LOCK_EX)`.

### Schools Table Schema (`schools.csv`)
| Column | Type | Description |
|---|---|---|
| `id` | int | Auto-increment row ID |
| `semis_code` | string | Unique numerical SEMIS Code (editable by HM, immutable by admin override) |
| `school_name` | string | Official school name |
| `head_master` | string | HM/HMistress full name |
| `cnic` | string | HM CNIC — immutable unique identifier, login username for school portal |
| `phone` | string | Official contact phone |
| `address` | string | Physical address |
| `level` | enum | Primary / Middle / Secondary / Higher Secondary |
| `gender` | enum | Co-education / Boys / Girls |
| `taluka` | string | Administrative sub-division |
| `enrollment` | int | Total enrolled students (auto-sum = `enrollment_boys + enrollment_girls`) |
| `enrollment_boys` | int | **NEW** Boys enrolled count |
| `enrollment_girls` | int | **NEW** Girls enrolled count |
| `attendance_pct` | string | Stored but not actively tracked after 2026-09-29 |
| `status` | enum | Active / Good / Needs Attention / Not Reporting |
| `status_badge` | string | CSS badge class |
| `classrooms` | int | Functional classroom count |
| `teachers` | int | Teaching staff count |
| `non_teaching` | int | Non-teaching staff count |
| `facility_electricity` | enum | Solar + Grid / Grid Only / Solar Only / Unavailable / None |
| `facility_water` | enum | Filtered Plant / Handpump / Water Supply Line / Unavailable / None |
| `facility_toilets` | enum | Functional Blocks / Needs Repair / Unavailable / None |
| `facility_boundary_wall` | enum | Secured & Complete / Partial / Damaged / Under Construction / Unavailable / None |
| `facility_internet` | enum | Broadband / 4G / Partial / Mobile Data / Unavailable / None |
| `building_structure` | enum | **NEW** Good Condition / Needs Repair / Dangerous / Unsafe / Condemned / Closed |
| `drainage_sewerage` | enum | **NEW** Functional Drainage / Partial Drainage / Broken / None |
| `flood_prone` | enum | **NEW** No / Yes |
| `furniture_condition` | enum | **NEW** Adequate / Shortage / None Available |

### Auto-Risk Flagging Engine
- **Method:** `ExcelDB::autoFlagSchoolRisks(string $semisCode): int`
- **Trigger:** Called automatically after every facility save from both Admin (`admin/school-profile.php`) and School HM (`school/profile.php`).
- **Behavior:** Evaluates 10 infrastructure rules against current school data. Inserts new `school_risks` records for newly-detected issues. Does **not** duplicate existing unresolved risks for the same school+category. Admin manually marks risks as 'Resolved'.
- **Risk Rules:** Dangerous building → Critical; No boundary wall → High; Partial wall → Medium; No water → High; No toilets → High; No electricity → High; No drainage → Medium; Flood prone → Critical; No furniture → Medium; Needs repair building → High.

### School Risks Table Schema (`school_risks.csv`)
| Column | Description |
|---|---|
| `id` | Auto-increment |
| `semis_code` | Link to school |
| `school_name` | Denormalized school name |
| `taluka` | Denormalized taluka |
| `risk_category` | One of 12 SELD-aligned categories |
| `severity` | Critical / High / Medium |
| `details` | Description of the risk |
| `reported_date` | Date flagged |
| `last_inspected` | Date last updated |
| `status` | Pending / In Progress / Escalated / Resolved |
| `notes` | Admin notes |

**Auto-flag note source:** When auto-flagged, `notes` = `'Auto-flagged from school infrastructure profile data.'`

---

---

### CI/CD Deployment Architecture (GitHub Actions)
- **Workflow File:** `.github/workflows/deploy.yml`
- **Trigger:** On `push` to `master` or `main` branches, and manual `workflow_dispatch`.
- **Target Host:** InfinityFree FTP (`ftpupload.net`) to remote `/htdocs/`.
- **Secrets Configured on GitHub:**
  - `FTP_SERVER`: `ftpupload.net` (optional, defaults to ftpupload.net)
  - `FTP_USERNAME`: `if0_43042000` (optional, defaults to if0_43042000)
  - `FTP_PASSWORD`: Secret FTP password (stored in GitHub Repository Secrets)
- **Exclusions:** `.git*`, `.github*`, `.agents*`, `scratch*`, `task.md` are excluded from FTP deployment to keep production lightweight.

---

## 5. Development & Contribution Rules for AI Models

1. **Do not create `.html` files in this repository.** All view files must be `.php`.
2. **Never break existing links.** All hyperlinks must point to valid `.php` routes within `/LSU-PORTAL/`.
3. **Keep includes DRY.** If adding a new page, reuse `includes/sidebar.php`, `includes/header.php`, and `includes/footer.php`.
4. **Log Changes:** Whenever a new feature, table, or page is introduced, document it in this file under **Revision History** below.
5. **⚠️ MANDATORY — Structural Change Logging:** Any change to page routes, database table schemas (add/remove columns), portal features, or key behaviors MUST be documented immediately in BOTH this file (Revision History) AND `.agents/skills/lsu-portal/SKILL.md`. This ensures future AI agents understand the exact architecture without searching the entire codebase.
6. **Never restore deprecated features.** `attendance.php`, `students.php`, individual student tracking, and per-student attendance data are permanently deprecated. Do not re-introduce any of these.
7. **New infrastructure fields go through ExcelDB.** Any new school data field must be added to `$schemas['schools']['headers']` in `excel_db.php` AND the corresponding seed data rows.

---

## 6. Revision History

| Date | Author | Description |
|---|---|---|
| 2026-09-19 | Antigravity AI | Converted static HTML templates to Core PHP with session-based authentication, modular includes, temporary bcrypt admin login, at-risk student monitoring, and security controls. |
| 2026-09-19 | Antigravity AI | Integrated Excel Database Engine (`ExcelDB`) on backend using UTF-8 BOM CSV storage in `/data/` with atomic locking. Added Excel DB Hub (`admin/excel-manager.php`), live export/import features, and connected live stats across Dashboard, Schools, Students, Attendance, and At-Risk pages. |
| 2026-09-20 | Antigravity AI | Standardized School ID to numerical SEMIS Code. Added Head Master Name & CNIC tracking with dynamic realistic data across all schools. Built interactive CSV Import wizard in `admin/schools.php` with column mapping, numerical SEMIS validation, and duplicate entry analysis. Added CSRF-protected School Deletion modal. Transformed `admin/school-profile.php` into a dynamic profile with full in-place administrative editing. |
| 2026-09-29 | Antigravity AI | Implemented dedicated School Portal for Head Masters/Mistresses accessed via CNIC and default password `1122`. Built full `/school/` portal module (Dashboard, Profile & Facilities, Student Roster, Daily Attendance, At-Risk & Dropout Prevention, Settings & Password). Added password visibility and direct reset/update capabilities for District Admin across School Directory and Profile pages. Synced credentials in `users.csv`. |
| 2026-09-29 | Antigravity AI | **Removed Daily Student Attendance feature** from both Admin and School portals. Deleted Attendance nav links from `includes/sidebar.php` and `school/includes/sidebar.php`. Replaced "Today's Attendance" KPI card on admin dashboard with "Active Schools". Converted `admin/attendance.php` and `school/attendance.php` into redirect stubs pointing to their respective dashboards. |
| 2026-09-29 | Antigravity AI | **Production UI & Credential Security Cleanup:** Removed demo credential boxes, autofill JS buttons, and password helper text from `login.php`. Removed "Data Management → Data Records" from Admin sidebar and converted `admin/excel-manager.php` into a security redirect stub. |
| 2026-09-29 | Antigravity AI | **Automated CI/CD Deployment via GitHub Actions:** Created `.github/workflows/deploy.yml` using `SamKirkland/FTP-Deploy-Action` to automatically deploy on `git push` to `master`/`main` to InfinityFree FTP (`ftpupload.net`). |
| 2026-09-30 | Antigravity AI | **Replaced "Students At-Risk" with "Schools At-Risk" Infrastructure Registry:** Removed student dropout monitoring. Created `admin/at-risk-schools.php` with SELD/SEMIS/PSSF-aligned school infrastructure risk categories. Updated Admin and School portals. |
| 2026-09-30 | Antigravity AI | **Developer Branding Integration:** Added interactive "Developed By: Abdul Rafay Qazi | Contact: 0371-0273699" branding in portal footers, header bar, and login page. |
| 2026-10-02 | Antigravity AI | **Login Screen Attribution & Disclaimer Update:** Updated login footer notice. Removed "256-Bit SSL Secured" badge. |
| 2026-10-02 | Antigravity AI | **Bug Fix (School Profile Links):** Fixed malformed `href` attributes across `admin/schools.php`, `admin/dashboard.php`, and `admin/school-profile.php`. |
| 2026-10-02 | Antigravity AI | **Students Overview & Attendance Deprecation + Credentials Lock Fix:** Removed individual Student Overview & Roster modules from Admin and School sidebars. Removed Attendance column from school directories. Fixed lock icon action button JS bug in `admin/schools.php`. |
| 2026-10-02 | Antigravity AI | **School Dashboard Metrics Fix & HM CNIC Locking:** Fixed School Dashboard metrics. Locked Head Master CNIC in School Portal as permanent, immutable unique identifier editable strictly by District Admin. |
| 2026-10-02 | Antigravity AI | **Editable SEMIS Code for Head Masters:** Made SEMIS Code editable by HM in `school/profile.php` with numerical validation, duplicate detection, and cascading sync across `schools.csv`, `users.csv`, `complaints.csv`, and active sessions. |
| 2026-10-02 | Antigravity AI | **Full Attendance & Student Data Removal:** Removed `$studentsData` from `admin/dashboard.php`. Replaced "Weekly Attendance Trend" chart with live "District Enrollment by School Level" bar chart (real data from schools). Replaced "Teacher Attendance" KPI bar with "Infrastructure Risk Resolution" live KPI. Updated Recent Activity to remove attendance/student mentions. Removed `students` and `attendance` schemas from `ExcelDB::$schemas`. Deleted `data/students.csv`, `data/attendance.csv`, `data/at_risk.csv` permanently. |
| 2026-10-02 | Antigravity AI | **Auto-Risk Flagging Engine & Extended Infrastructure Fields:** Added 4 new infrastructure columns to `schools` schema: `building_structure`, `drainage_sewerage`, `flood_prone`, `furniture_condition`. Added `ExcelDB::autoFlagSchoolRisks()` method that evaluates 10 SELD-aligned risk rules and auto-inserts `school_risks` records. Called automatically from `admin/school-profile.php` and `school/profile.php` on every facility save. Updated both edit modals (Admin & HM) with new Extended Infrastructure Assessment section. AGENTS.md and SKILL.md updated with full schema documentation and mandatory structural-change logging rule. |
| 2026-10-02 | Antigravity AI | **Full Dynamic Dashboard Refactor & Interactive KPI Navigation:** Made all District Dashboard metrics 100% dynamic calculated directly from `schools.csv`, `school_risks.csv`, and `complaints.csv`. Removed redundant "Active Schools" card, fixed undefined variable errors, linked Total Students and Total Schools cards to `admin/schools.php`, linked Critical Schools and At-Risk cards to `admin/at-risk-schools.php` with URL severity auto-filter support. Converted School Operational Status SVG Donut, District Performance Indicators, and Recent Activity / Grievance feed to live computed data. |
| 2026-10-02 | Antigravity AI | **Gender-Categorized Enrollment (Boys & Girls Split):** Added `enrollment_boys` and `enrollment_girls` columns to `schools` schema. Updated School Profile Editor (`school/profile.php`), Admin School Profile (`admin/school-profile.php`), and School Directory (`admin/schools.php`) with separate Boys & Girls input fields and live auto-sum total calculation. Connected exact stored Boys & Girls metrics across School Dashboard, Admin Dashboard KPI cards, and Directory table. |
| 2026-10-03 | Antigravity AI | **Mobile Sidebar Toggle Fix on Complaints Pages:** Resolved mobile hamburger menu (3 lines) button unresponsive issue across `school/complaints.php`, `school/complaint-details.php`, `admin/complaints.php`, and `admin/complaint-details.php` by adding missing backdrop `#overlay` element and `openSidebar()` / `closeSidebar()` JavaScript handlers. |
| 2026-10-03 | Antigravity AI | **Ticket Closure/Resolution Notification for School Portal:** When District Admin sets a complaint status to `Closed` or `Resolved` (via `admin/complaint-details.php` or quick-update in `admin/complaints.php`), `unread_school` is now set to `'1'`. School portal (`school/complaints.php`, `school/complaint-details.php`) shows a prominent notification banner listing affected tickets with status badge. Banner is dismissable and automatically disappears once the school reads the ticket detail page (existing `unread_school → 0` read logic). |
| 2026-10-03 | Antigravity AI | **School Complaints Summary on Admin School Profile:** Added a live complaints summary panel in the right column of `admin/school-profile.php`. Shows 4 stat cards (Active, Resolved, Closed, Urgent) computed from `complaints.csv` filtered by SEMIS code. Each card is a clickable link to `admin/complaints.php` pre-filtered by status/priority for that school. Also shows a "Recent Tickets" mini-list of the 3 most recent tickets with status badges and a direct link to the complaint thread. |
| 2026-10-03 | Antigravity AI | **SEMIS Code Access Permissions (Locked for HM, Editable by Admin):** Made SEMIS Code strictly read-only and immutable for Head Masters in `school/profile.php`. Enabled District Admin to update school SEMIS Code directly in `admin/school-profile.php` with numerical validation, duplicate detection, and cascading synchronization across `schools.csv`, `users.csv`, `school_risks.csv`, and `complaints.csv`. |
| 2026-10-03 | Antigravity AI | **School Profile Edit Modal Layout Fix:** Resolved broken nested grid layout inside the "Edit School Information" modal on `admin/school-profile.php` by properly closing parent grid containers, restructuring form sections into responsive 2-column & 3-column rows, and adding clean category containers for basic identity, leadership, enrollment breakdown, facilities, and extended infrastructure assessment. |
| 2026-10-03 | Antigravity AI | **School Staff Management & Institutional Profile Data Completion Audit:** Added dedicated School Staff Details module in School Portal (`school/staff.php`) and activated District Staff Directory in Admin Portal (`admin/staff.php`). Added `school_staff.csv` table with comprehensive SELD HR fields (Personal No, CNIC, Scale, Qualifications, Cadre). Implemented automatic synchronization with `schools.csv` teachers and non_teaching columns. Added live Profile Data Completion Audit widget and Official Deficit Notice / PDF Compliance Report generator on `admin/school-profile.php`. |
| 2026-10-03 | Antigravity AI | **Removed Standalone School Profile Sidebar Link:** Removed generic `school-profile.php` link from the Admin sidebar (`includes/sidebar.php`) under the Schools group, ensuring school profiles are accessed directly and contextually with a specific SEMIS code via the School Directory (`admin/schools.php`) or At-Risk list. |
| 2026-10-03 | Antigravity AI | **Clickable School Directory Rows:** Enhanced `admin/schools.php` so clicking anywhere on a school's table row navigates directly to that school's Profile (`admin/school-profile.php?semis=...`), while preserving discrete click actions for Credentials modal and Delete buttons via `event.stopPropagation()`. |
| 2026-10-04 | Antigravity AI | **Admin Direct Messaging System:** Added a dedicated Admin-to-School Direct Messaging feature. Admin can initiate threaded conversations with any school from `admin/messages.php`. Conversations remain Open until Admin explicitly closes them. School Head Masters can read messages and reply from `school/messages.php` and `school/message-thread.php`, but cannot initiate new conversations. Added 2 new ExcelDB tables: `admin_messages` and `admin_message_replies`. Added `ExcelDB::generateMessageThreadId()`, `getRepliesForThread()`, `addMessageReply()`, and `getUnreadMessagesCount()` helpers. Both Admin and School sidebars show a blue unread message badge. |
| 2026-10-06 | Antigravity AI | **Direct Message Notifications & Delete Conversation Fix:** (1) Enhanced `api/check-complaints.php` and `assets/js/notifications.js` to poll and trigger audio chimes & toast alerts for direct messages in real time alongside complaints. (2) Upgraded notification dropdown in `school/includes/header.php` to list individual unread direct messages with direct thread links. (3) Added unread direct message alert banner and Quick Action button on `school/dashboard.php`. (4) Added `ExcelDB::deleteMessageThread()` and fixed conversation deletion across `admin/messages.php` and `admin/message-thread.php`. (5) Fixed footer partial include paths in `school/messages.php` and `school/message-thread.php`. |
| 2026-10-06 | Antigravity AI | **Real-Time Direct Messaging Chat Engine:** Built asynchronous real-time chat streaming engine in `api/thread-messages.php`. Both `admin/message-thread.php` and `school/message-thread.php` now send messages via AJAX without page reloads, append chat bubbles instantly, auto-scroll smoothly to bottom, and poll for incoming messages every 2.5s with instant audio chime notifications. Added Enter-key quick send support. |
| 2026-10-07 | Antigravity AI | **Searchable School Picker in Direct Message Modal:** Replaced the plain `<select>` dropdown in `admin/messages.php` "Send Direct Message" modal with a live-search custom picker. Admins can now type any part of the school name or SEMIS code to instantly filter the school list. Selected school is shown as a removable pill. Hidden input carries the SEMIS code for form submission. Also added support for `?compose=1&semis=XXXXXX` URL params to auto-open the modal with the school pre-selected (used by School Profile button). |
| 2026-10-07 | Antigravity AI | **Direct Message Button on School Profile:** Added a green "Direct Message" action button in the header action bar of `admin/school-profile.php`. Clicking it redirects to `admin/messages.php?compose=1&semis=<SEMIS>`, which auto-opens the Send Message modal with that school pre-selected via JS URL param detection. |
| 2026-10-07 | Antigravity AI | **Admin Portal Audio Chime Fix:** Fixed Web Audio API not playing notification sound on the Admin portal. Root cause: `AudioContext` was created before any user interaction, putting it in `suspended` state, and the old `click`-only unlock listener never fired if the admin interacted via keyboard or touch. Fixed in `assets/js/notifications.js` by: (1) adding `initAudioContext()` function that pre-creates and unlocks AudioContext, (2) attaching it to `click`, `pointerdown`, `keydown`, `touchstart` events (not one-shot), (3) restructuring `playNotificationChime()` to call `audioContext.resume().then(playWhenReady)` so sound always fires after context is resumed. Polling interval also tightened to 8 seconds. |
| 2026-10-07 | Antigravity AI | **Profile Progress Filter & Bulk Appreciation / Show Cause Notice Print Engine:** Added "Progress" filter dropdown next to Gender in `admin/schools.php` with 2 options: "Complete (100%)" and "Below 100%". Pre-computes profile completion for all schools via `ExcelDB::calculateSchoolProfileCompletion()`. Dynamically displays green "Print Appreciation Certificates" button when 100% is selected, and red "Print Show Cause Notices" button when Below 100% is selected, updating live with matching visible counts. Built bulk printable modal and print-ready 1-page A4 templates (`@media print`, `page-break-after: always`) containing School Name, HM Name, HM CNIC, SEMIS Code, Taluka, Ref No, deficit checklist table (for notices), compliance directives, and dual official signatures (District RSU Coordinator & DEO). |
| 2026-10-07 | Antigravity AI | **Interactive Clickable KPI Cards on At-Risk Schools Registry:** Made all 4 summary cards (Critical, High Risk, Medium, Resolved) on `admin/at-risk-schools.php` clickable with smooth hover animations and active ring highlights. Clicking any card instantly filters the registry table by that severity or status. Clicking the active card again toggles/resets the filter back to All. Also made Risk Category Breakdown pills clickable buttons to filter by specific risk categories. |
