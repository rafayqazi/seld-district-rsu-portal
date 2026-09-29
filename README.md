# 🏫 LSU District Education & Literacy Portal

> **Sindh Education & Literacy Department (SELD) — District Reform Support Unit (RSU)**  
> **Target District:** Tando Allahyar District, Sindh, Pakistan

![PHP](https://img.shields.io/badge/PHP-8.x-123B63?style=for-the-badge&logo=php&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-0F766E?style=for-the-badge&logo=tailwindcss&logoColor=white)
![Architecture](https://img.shields.io/badge/Architecture-Core_PHP_--_Zero_Framework-0B2946?style=for-the-badge)
![Security](https://img.shields.io/badge/Security-256--Bit_SSL_--_CSRF_--_XSS_--_Bcrypt-15803D?style=for-the-badge)

---

## 📌 Project Overview

The **LSU District Education Portal** is a high-performance, enterprise-grade administrative management platform engineered specifically for the **District Reform Support Unit (RSU)** under the **Sindh Education & Literacy Department (SELD)**. 

Designed for low-latency operation, offline reliability, and intuitive field usability, the portal provides real-time monitoring across district schools, headmaster accountability tracking, enrollment metrics, taluka-level breakdowns, and dropout prevention analytics.

---

## ✨ Key Features & Capability Matrix

### 🏢 1. District Administrative Portal (`/admin/`)
- **District KPI Dashboard:** Real-time visibility into active school counts, enrollment numbers, headmaster coverage, and dropout risk alerts.
- **Schools Directory & Directory Wizard:** Comprehensive directory of district public schools with search, filtering by Tehsil/Taluka, gender classification, and functional status.
- **Dynamic School Profiles (`school-profile.php`):** In-place administrative editing of school information, infrastructure status, SEMIS codes, and Head Master credentials.
- **Head Master Credential & Password Management:** Direct reset and visibility control over school login accounts across the district.
- **Student Roster & Tracking (`students.php`):** District-wide student directory with filterable academic risk markers and enrollment details.
- **Dropout & At-Risk Risk Monitoring (`at-risk-students.php`):** Algorithmic tracking of students identified at risk of dropping out, equipped with custom intervention logging.
- **Dynamic System Settings (`settings.php`):** Configurable district parameters, academic calendar, contact handles, and multi-taluka management.

### 🏫 2. Head Master / School Portal (`/school/`)
- **Dedicated HM Dashboard:** Role-tailored dashboard tailored for individual Head Masters/Mistresses accessed via official CNIC credentials.
- **School Profile & Facilities Management:** Live self-updating portal for updating facility stats (classrooms, toilets, electricity, boundary walls, drinking water).
- **Student Directory:** Class-wise student roster management.
- **At-Risk Interventions:** School-level tracking and intervention recording for vulnerable students.
- **Account Settings:** Self-service password updates with dynamic security validation.

### 🔒 3. Enterprise Security & Architecture
- **Zero-Framework Speed:** Built using pure **Core PHP (8.x)** with zero dependency bloat for maximum performance on regional servers.
- **Bcrypt Authentication & Session Regeneration:** Secure hashing using PHP's native `password_hash()` and strict `session_regenerate_id(true)` policies.
- **CSRF & XSS Hardening:** Synchronizer Token Pattern on all state-changing `POST` mutations and automatic output escaping via HTML entity encoding.
- **Atomic File Database Engine (`ExcelDB`):** Custom high-reliability CSV storage engine with `flock(LOCK_EX)` file locking, UTF-8 BOM encoding for native Microsoft Excel compatibility, and `.htaccess` file shielding.

---

## 📁 Repository Directory Layout

```
LSU-PORTAL/
├── index.php                      # Root router (redirects to login or active session dashboard)
├── login.php                      # Unified login portal (District Admin & School Headmaster tabs)
├── logout.php                     # Secure session destruction & cookie clearing
│
├── admin/                         # Admin role-protected module
│   ├── dashboard.php              # District KPI overview, trends, taluka breakdown
│   ├── schools.php                # School directory, search, filters, SEMIS management
│   ├── school-profile.php         # Single school deep-dive profile & administrative editor
│   ├── students.php               # District student roster and enrollment overview
│   ├── at-risk-students.php       # Dropout risk monitoring and intervention log
│   ├── settings.php               # System settings & dynamic taluka jurisdiction setup
│   └── excel-manager.php          # Internal data security redirect stub
│
├── school/                        # School Head Master role-protected module
│   ├── dashboard.php              # School dashboard & key metrics
│   ├── profile.php                # School profile, facilities & Head Master info editor
│   ├── students.php               # School student roster
│   ├── at-risk.php                # School dropout monitoring & student risk logs
│   ├── settings.php               # Head master account settings & password management
│   └── includes/                  # School-specific partials (sidebar, header, footer)
│
├── includes/                      # Reusable core PHP partials & security guards
│   ├── auth.php                   # Authentication guards, session security, CSRF helpers
│   ├── excel_db.php               # Atomic CSV database engine (ExcelDB static class)
│   ├── sidebar.php                # District admin navigation sidebar
│   ├── header.php                 # Top application header bar
│   └── footer.php                 # Footer, Toast notification system & Modal dialogs
│
├── config/
│   └── config.php                 # System constants, district settings, and environment helpers
│
├── data/                          # Protected CSV database storage (.htaccess restricted)
│   ├── schools.csv                # School records & infrastructure data
│   ├── students.csv               # Student roster & risk indicators
│   ├── at_risk.csv                # Dropout risk logs & intervention records
│   ├── users.csv                  # System user accounts & hashed credentials
│   ├── settings.csv               # System configuration parameters
│   └── talukas.csv                # District taluka/tehsil jurisdiction registry
│
└── .agents/                       # AI agent knowledge base & skill definitions
    └── skills/
        └── lsu-portal/
            └── SKILL.md           # Developer skill guide & architecture specifications
```

---

## 🛠️ System Requirements & Installation

### Requirements
- **Web Server:** Apache 2.4+ / Nginx (configured with PHP-FPM)
- **PHP Version:** PHP 8.0 or higher
- **PHP Extensions:** `mbstring`, `json`, `fileinfo`
- **Database:** Standard installation uses the built-in atomic `ExcelDB` engine (No MySQL server required out of the box).

### Local Setup Instructions (XAMPP / WAMP)

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/rafayqazi/seld-district-rsu-portal.git
   ```

2. **Deploy to Document Root:**
   Move or copy the project folder into your XAMPP web root (`c:\xampp\htdocs\LSU-PORTAL`).

3. **Configure File Permissions:**
   Ensure the `data/` directory has write permissions for the web server process (`www-data` / `apache` / `IUSR`).

4. **Access the Application:**
   Open your browser and navigate to:
   ```
   http://localhost/LSU-PORTAL/
   ```

---

## 🎨 Design System & Color Palette

The portal implements the official **Government of Sindh Education Department** visual design system:

| Token Name | Hex Code | Purpose |
|------------|----------|---------|
| **Primary Navy** | `#123B63` | Top bars, primary buttons, branding |
| **Dark Navy** | `#0B2946` | Header strip, active states |
| **Teal Accent** | `#0F766E` | Active sidebar indicators, highlights |
| **Background** | `#F5F7FA` | Content area background |
| **Surface** | `#FFFFFF` | Cards, tables, modal dialogs |
| **Text Main** | `#172033` | Body text, titles |
| **Muted Text** | `#64748B` | Subtitles, labels |

---

## 🛡️ Security Best Practices Enforced

- **Zero Hardcoded Passwords:** No plaintext passwords or demo account hints are displayed in production UI.
- **CSRF Protection:** Every state-changing form includes a cryptographically unique CSRF token validated server-side.
- **XSS Prevention:** All output rendered to the DOM is strictly sanitized via the `e()` escaping utility.
- **Native Custom Dialogs:** JavaScript `alert()` and `confirm()` calls are completely replaced with styled, asynchronous accessible toast notifications and custom modal dialogs.

---

## 📜 License & Department Notice

This repository contains official software architecture designed for the **Sindh Education & Literacy Department (SELD), Government of Sindh**. All rights reserved.

---

<p center>
  Developed with ❤️ for Education Reform in District Tando Allahyar, Sindh.
</p>
