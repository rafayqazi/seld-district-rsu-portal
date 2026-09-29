# LSU Portal — AI Agent Instructions & Architectural Rules

> **Project:** Sindh Education & Literacy Department (SELD) — District RSU Portal (Tando Allahyar District)  
> **Architecture:** Core PHP (Vanilla PHP 8.x) + Tailwind CSS + Vanilla JavaScript  
> **Last Updated:** September 2026

This document is mandatory reading for all AI agents, models, and developers contributing to this repository. All future modifications, features, and refactorings must adhere to these established patterns.

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
│   ├── dashboard.php              # District KPI overview, trends, taluka breakdown
│   ├── schools.php                # School directory, search, filters, metrics
│   ├── school-profile.php         # Single school deep-dive profile
│   ├── students.php               # Student roster and overview
│   ├── attendance.php             # Daily student attendance tracking
│   └── at-risk-students.php       # Dropout risk monitoring and interventions
│
├── includes/                      # Reusable PHP components & guards
│   ├── auth.php                   # Authentication check, session security, CSRF helpers
│   ├── sidebar.php                # Main navigation sidebar with $active_page indicator
│   ├── header.php                 # Top app bar (district badge, profile, notifications)
│   └── footer.php                 # Standard copyright and department footer
│
├── config/
│   └── config.php                 # App constants, environment settings, DB config placeholders
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
  - `'dashboard'` → Overview
  - `'schools'` → Schools Directory
  - `'school-profile'` → School Profile
  - `'students'` → Student Overview
  - `'attendance'` → Attendance
  - `'at-risk'` → At-Risk Students
- **Responsiveness:** Sidebar is collapsable on mobile with smooth transition and dark overlay backdrop (`#overlay`). Header contains the hamburger toggle button.

---

### Excel Database Architecture
- **Engine:** `includes/excel_db.php` (`ExcelDB` class)
- **File Storage:** `/data/` protected by Apache `.htaccess` (`Deny from all`)
- **Tables:** `schools.csv`, `students.csv`, `attendance.csv`, `at_risk.csv`, `users.csv`
- **Excel Compatibility:** Written with UTF-8 BOM (`\xEF\xBB\xBF`) and standard CSV delimiter for seamless double-click opening in Microsoft Excel.
- **Safety:** Atomic writes using `flock(LOCK_EX)`.

---

## 5. Development & Contribution Rules for AI Models

1. **Do not create `.html` files in this repository.** All view files must be `.php`.
2. **Never break existing links.** All hyperlinks must point to valid `.php` routes within `/LSU-PORTAL/`.
3. **Keep includes DRY.** If adding a new page, reuse `includes/sidebar.php`, `includes/header.php`, and `includes/footer.php`.
4. **Log Changes:** Whenever a new feature, table, or page is introduced, document it in this file under **Revision History** below.

---

## 6. Revision History

| Date | Author | Description |
|---|---|---|
| 2026-09-19 | Antigravity AI | Converted static HTML templates to Core PHP with session-based authentication, modular includes, temporary bcrypt admin login, at-risk student monitoring, and security controls. |
| 2026-09-19 | Antigravity AI | Integrated Excel Database Engine (`ExcelDB`) on backend using UTF-8 BOM CSV storage in `/data/` with atomic locking. Added Excel DB Hub (`admin/excel-manager.php`), live export/import features, and connected live stats across Dashboard, Schools, Students, Attendance, and At-Risk pages. |
| 2026-09-20 | Antigravity AI | Standardized School ID to numerical SEMIS Code. Added Head Master Name & CNIC tracking with dynamic realistic data across all schools. Built interactive CSV Import wizard in `admin/schools.php` with column mapping, numerical SEMIS validation, and duplicate entry analysis. Added CSRF-protected School Deletion modal. Transformed `admin/school-profile.php` into a dynamic profile with full in-place administrative editing. |
| 2026-09-29 | Antigravity AI | Implemented dedicated School Portal for Head Masters/Mistresses accessed via CNIC and default password `1122`. Built full `/school/` portal module (Dashboard, Profile & Facilities, Student Roster, Daily Attendance, At-Risk & Dropout Prevention, Settings & Password). Added password visibility and direct reset/update capabilities for District Admin across School Directory and Profile pages. Synced credentials in `users.csv`. |
| 2026-09-29 | Antigravity AI | **Removed Daily Student Attendance feature** from both Admin and School portals. Deleted Attendance nav links from `includes/sidebar.php` and `school/includes/sidebar.php`. Replaced "Today's Attendance" KPI card on admin dashboard with "Active Schools". Replaced "Today's Attendance" KPI card on school dashboard with "At-Risk Students". Removed `Mark Attendance` quick-action button from school dashboard. Converted `admin/attendance.php` and `school/attendance.php` into redirect stubs pointing to their respective dashboards. Updated SELD guidelines notice on school dashboard. No data is deleted — `attendance.csv` remains on disk but is no longer read or written by any page. |
| 2026-09-29 | Antigravity AI | **Production UI & Credential Security Cleanup:** Removed demo credential boxes, autofill JS buttons, and password helper text from `login.php`. Removed "Data Management -> Data Records" from Admin sidebar and converted `admin/excel-manager.php` into a security redirect stub to protect internal data tools. Updated `admin/dashboard.php` Excel DB badge to a static non-clickable indicator. Cleaned and synchronized developer skill (`lsu-portal/SKILL.md`) removing duplicate rules and obsolete credential hints. |



