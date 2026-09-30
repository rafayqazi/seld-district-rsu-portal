<?php
/**
 * admin/complaints.php
 *
 * SELD District RSU — Grievance & Complaints Redressal Management Hub.
 * Allows District Admins to oversee, filter, respond to, update status, and resolve school complaints.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'complaints';
$page_title  = 'Grievance Redressal & Complaints — District RSU';

// Handle Actions (Delete, Status Update, etc.)
$flash_success = '';
$flash_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $flash_error = 'Security validation failed (invalid CSRF token). Please refresh and try again.';
    } elseif ($action === 'delete_complaint') {
        $ticketNo = trim($_POST['ticket_no'] ?? '');
        if (!empty($ticketNo)) {
            // Delete complaint and its replies
            $deleted = ExcelDB::delete('complaints', 'ticket_no', $ticketNo);
            if ($deleted) {
                // Delete related replies
                $replies = ExcelDB::all('complaint_replies');
                $filteredReplies = array_filter($replies, fn($r) => ($r['ticket_no'] ?? '') !== $ticketNo);
                ExcelDB::writeTable('complaint_replies', array_values($filteredReplies));
                $flash_success = "Complaint Ticket <strong>" . e($ticketNo) . "</strong> has been permanently deleted.";
            } else {
                $flash_error = "Could not find ticket to delete.";
            }
        }
    } elseif ($action === 'quick_status_update') {
        $ticketNo = trim($_POST['ticket_no'] ?? '');
        $newStatus = trim($_POST['new_status'] ?? '');
        $allowedStatuses = ['Pending', 'Under Review', 'In Progress', 'Resolved', 'Closed'];
        
        if (!empty($ticketNo) && in_array($newStatus, $allowedStatuses, true)) {
            ExcelDB::update('complaints', 'ticket_no', $ticketNo, [
                'status'     => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $flash_success = "Status for Ticket <strong>" . e($ticketNo) . "</strong> updated to <strong>" . e($newStatus) . "</strong>.";
        }
    }
}

// Fetch all complaints
$allComplaints = ExcelDB::all('complaints');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    ExcelDB::exportCsv('complaints');
    exit;
}

// Calculate metrics
$totalCount    = count($allComplaints);
$pendingCount  = 0;
$inReviewCount = 0;
$resolvedCount = 0;
$closedCount   = 0;
$urgentCount   = 0;

foreach ($allComplaints as $c) {
    $st = strtolower($c['status'] ?? '');
    $pr = strtolower($c['priority'] ?? '');

    if ($st === 'pending') {
        $pendingCount++;
    } elseif ($st === 'under review' || $st === 'in progress') {
        $inReviewCount++;
    } elseif ($st === 'resolved') {
        $resolvedCount++;
    } elseif ($st === 'closed') {
        $closedCount++;
    }

    if ($pr === 'urgent') {
        $urgentCount++;
    }
}

// Filter values from query
$filterSearch   = trim($_GET['search'] ?? '');
$filterTaluka   = trim($_GET['taluka'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus   = trim($_GET['status'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');

// Filter list
$filteredComplaints = array_filter($allComplaints, function($c) use ($filterSearch, $filterTaluka, $filterCategory, $filterStatus, $filterPriority) {
    if (!empty($filterSearch)) {
        $searchLower = strtolower($filterSearch);
        $match = str_contains(strtolower($c['ticket_no'] ?? ''), $searchLower)
              || str_contains(strtolower($c['school_name'] ?? ''), $searchLower)
              || str_contains(strtolower($c['semis_code'] ?? ''), $searchLower)
              || str_contains(strtolower($c['subject'] ?? ''), $searchLower)
              || str_contains(strtolower($c['description'] ?? ''), $searchLower);
        if (!$match) return false;
    }
    if (!empty($filterTaluka) && ($c['taluka'] ?? '') !== $filterTaluka) {
        return false;
    }
    if (!empty($filterCategory) && ($c['category'] ?? '') !== $filterCategory) {
        return false;
    }
    if (!empty($filterStatus) && strtolower($c['status'] ?? '') !== strtolower($filterStatus)) {
        return false;
    }
    if (!empty($filterPriority) && strtolower($c['priority'] ?? '') !== strtolower($filterPriority)) {
        return false;
    }
    return true;
});

// Sort: Pending & Unread first, then by updated_at descending
usort($filteredComplaints, function($a, $b) {
    $unreadA = ($a['unread_admin'] ?? '0') === '1' ? 1 : 0;
    $unreadB = ($b['unread_admin'] ?? '0') === '1' ? 1 : 0;
    if ($unreadA !== $unreadB) {
        return $unreadB <=> $unreadA;
    }
    $pendingA = strtolower($a['status'] ?? '') === 'pending' ? 1 : 0;
    $pendingB = strtolower($b['status'] ?? '') === 'pending' ? 1 : 0;
    if ($pendingA !== $pendingB) {
        return $pendingB <=> $pendingA;
    }
    return strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? '');
});

// Extract unique categories & talukas for filters
$categories = [
    'SMC Funds & Grants',
    'Textbooks & Free Supplies',
    'Teacher Shortage & Absenteeism',
    'Biometric & Salary Grievance',
    'Building & Infrastructure Breakdown',
    'Electricity & Water Outage',
    'Security / Boundary / Harassment Issue',
    'Examination & Enrolment Issues',
    'Other Departmental Matters'
];

$talukas = ['Tando Allahyar', 'Jhando Mari', 'Chambar', 'Nasarpur'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#123B63',
            primaryDark: '#0B2946',
            secondary: '#0F766E',
            background: '#F5F7FA',
            surface: '#FFFFFF',
            textMain: '#172033',
            muted: '#64748B',
            border: '#E2E8F0',
            success: '#15803D',
            warning: '#D97706',
            danger: '#DC2626',
          },
          fontFamily: { sans: ['Inter', 'sans-serif'] },
        }
      }
    }
  </script>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col font-sans">
  
  <div class="flex flex-1 min-h-screen">
    <!-- Sidebar -->
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

      <!-- Main Content Area -->
      <main class="flex-1 p-4 md:p-6 lg:p-8 space-y-6">

        <!-- Alerts -->
        <?php if (!empty($flash_success)): ?>
          <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-emerald-600"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <span><?= $flash_success ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">&times;</button>
          </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
          <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-red-600"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <span><?= $flash_error ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">&times;</button>
          </div>
        <?php endif; ?>

        <!-- Page Title & Header Actions -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
              </div>
              <h1 class="text-2xl font-bold text-textMain tracking-tight">Grievance &amp; Complaints Redressal</h1>
            </div>
            <p class="text-sm text-muted mt-1">SELD Grievance Redressal Mechanism (GRM) &bull; District RSU Tando Allahyar</p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <button onclick="window.playNotificationChime()" title="Test Alert Sound" class="inline-flex items-center gap-2 px-3 py-2 bg-surface border border-border hover:bg-slate-50 text-textMain text-xs font-semibold rounded-lg shadow-xs transition">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-amber-600"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
              <span>Test Tling Sound</span>
            </button>
            <a href="<?= BASE_URL ?>/admin/complaints.php?export=csv" class="inline-flex items-center gap-2 px-3 py-2 bg-surface border border-border hover:bg-slate-50 text-textMain text-xs font-semibold rounded-lg shadow-xs transition">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span>Export CSV</span>
            </a>
          </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <!-- Total -->
          <div class="bg-surface border border-border p-4 rounded-xl shadow-xs">
            <div class="flex items-center justify-between text-muted text-xs font-medium">
              <span>Total Lodged</span>
              <div class="w-7 h-7 rounded bg-slate-100 text-slate-600 flex items-center justify-center">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-textMain mt-2"><?= $totalCount ?></div>
            <div class="text-[11px] text-muted mt-1">All registered tickets</div>
          </div>

          <!-- Pending -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?status=Pending" class="bg-surface border border-red-200/80 p-4 rounded-xl shadow-xs hover:border-red-400 transition block">
            <div class="flex items-center justify-between text-red-600 text-xs font-medium">
              <span>Pending Action</span>
              <div class="w-7 h-7 rounded bg-red-100 text-red-600 flex items-center justify-center">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-red-600 mt-2 flex items-center gap-2">
              <span><?= $pendingCount ?></span>
              <?php if ($pendingCount > 0): ?>
                <span class="text-[10px] font-semibold bg-red-100 text-red-700 px-2 py-0.5 rounded-full animate-pulse">Needs Reply</span>
              <?php endif; ?>
            </div>
            <div class="text-[11px] text-muted mt-1">Awaiting admin review</div>
          </a>

          <!-- Under Review / In Progress -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?status=Under+Review" class="bg-surface border border-amber-200/80 p-4 rounded-xl shadow-xs hover:border-amber-400 transition block">
            <div class="flex items-center justify-between text-amber-700 text-xs font-medium">
              <span>In Progress</span>
              <div class="w-7 h-7 rounded bg-amber-100 text-amber-700 flex items-center justify-center">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-amber-700 mt-2"><?= $inReviewCount ?></div>
            <div class="text-[11px] text-muted mt-1">Under investigation</div>
          </a>

          <!-- Resolved -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?status=Resolved" class="bg-surface border border-emerald-200/80 p-4 rounded-xl shadow-xs hover:border-emerald-400 transition block">
            <div class="flex items-center justify-between text-emerald-700 text-xs font-medium">
              <span>Resolved</span>
              <div class="w-7 h-7 rounded bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-emerald-700 mt-2"><?= $resolvedCount ?></div>
            <div class="text-[11px] text-muted mt-1">Successfully redressed</div>
          </a>

          <!-- Urgent Priority -->
          <a href="<?= BASE_URL ?>/admin/complaints.php?priority=Urgent" class="bg-surface border border-border p-4 rounded-xl shadow-xs hover:border-primary transition block">
            <div class="flex items-center justify-between text-muted text-xs font-medium">
              <span>Urgent Priority</span>
              <div class="w-7 h-7 rounded bg-purple-100 text-purple-700 flex items-center justify-center">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-purple-700 mt-2"><?= $urgentCount ?></div>
            <div class="text-[11px] text-muted mt-1">Critical redressal queue</div>
          </a>
        </div>

        <!-- Filter Bar -->
        <div class="bg-surface border border-border p-4 rounded-xl shadow-xs">
          <form method="GET" action="<?= BASE_URL ?>/admin/complaints.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            
            <!-- Search -->
            <div class="lg:col-span-2">
              <label class="block text-xs font-semibold text-muted mb-1">Search Tickets</label>
              <input type="text" name="search" value="<?= e($filterSearch) ?>" placeholder="Ticket #, School, SEMIS, Subject..." class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary">
            </div>

            <!-- Taluka -->
            <div>
              <label class="block text-xs font-semibold text-muted mb-1">Taluka</label>
              <select name="taluka" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary">
                <option value="">All Talukas</option>
                <?php foreach ($talukas as $tal): ?>
                  <option value="<?= e($tal) ?>" <?= $filterTaluka === $tal ? 'selected' : '' ?>><?= e($tal) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Category -->
            <div>
              <label class="block text-xs font-semibold text-muted mb-1">Category</label>
              <select name="category" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Status -->
            <div>
              <label class="block text-xs font-semibold text-muted mb-1">Status</label>
              <select name="status" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary">
                <option value="">All Statuses</option>
                <option value="Pending" <?= strtolower($filterStatus) === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Under Review" <?= strtolower($filterStatus) === 'under review' ? 'selected' : '' ?>>Under Review</option>
                <option value="In Progress" <?= strtolower($filterStatus) === 'in progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Resolved" <?= strtolower($filterStatus) === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Closed" <?= strtolower($filterStatus) === 'closed' ? 'selected' : '' ?>>Closed</option>
              </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-end gap-2">
              <button type="submit" class="flex-1 px-4 py-2 bg-primary hover:bg-primaryDark text-white text-xs font-semibold rounded-lg shadow-xs transition">
                Filter
              </button>
              <?php if (!empty($filterSearch) || !empty($filterTaluka) || !empty($filterCategory) || !empty($filterStatus) || !empty($filterPriority)): ?>
                <a href="<?= BASE_URL ?>/admin/complaints.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition" title="Reset Filters">
                  Reset
                </a>
              <?php endif; ?>
            </div>

          </form>
        </div>

        <!-- Complaints Table Card -->
        <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
          <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <div class="flex items-center gap-2">
              <h2 class="font-bold text-textMain text-sm">Active Complaints Queue</h2>
              <span class="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded-full font-semibold">
                Showing <?= count($filteredComplaints) ?> of <?= $totalCount ?>
              </span>
            </div>
          </div>

          <?php if (empty($filteredComplaints)): ?>
            <div class="p-12 text-center">
              <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              </div>
              <h3 class="text-sm font-bold text-textMain">No Complaints Found</h3>
              <p class="text-xs text-muted mt-1 max-w-sm mx-auto">No complaints match your active filter criteria. Clear filters to view all records.</p>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-50/80 border-b border-border text-muted font-semibold uppercase tracking-wider text-[11px]">
                    <th class="py-3.5 px-4">Ticket / Status</th>
                    <th class="py-3.5 px-4">School &amp; Taluka</th>
                    <th class="py-3.5 px-4">Category &amp; Subject</th>
                    <th class="py-3.5 px-4">Priority</th>
                    <th class="py-3.5 px-4">Timeline</th>
                    <th class="py-3.5 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <?php foreach ($filteredComplaints as $c): 
                    $st = strtolower($c['status'] ?? '');
                    $pr = strtolower($c['priority'] ?? '');
                    $isUnread = ($c['unread_admin'] ?? '0') === '1';

                    // Priority Badge
                    $priorityClass = match($pr) {
                      'urgent' => 'bg-red-50 text-red-700 border-red-200 font-bold',
                      'high'   => 'bg-amber-50 text-amber-700 border-amber-200 font-semibold',
                      default  => 'bg-slate-100 text-slate-700 border-slate-200'
                    };

                    // Status Badge
                    $statusClass = match($st) {
                      'pending'      => 'bg-red-100 text-red-800 border-red-200',
                      'under review', 'in progress' => 'bg-amber-100 text-amber-800 border-amber-200',
                      'resolved'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                      'closed'       => 'bg-slate-200 text-slate-800 border-slate-300',
                      default        => 'bg-slate-100 text-slate-700 border-slate-200'
                    };
                  ?>
                    <tr class="hover:bg-slate-50/60 transition <?= $isUnread ? 'bg-amber-50/30 font-medium' : '' ?>">
                      
                      <!-- Ticket / Status -->
                      <td class="py-3.5 px-4">
                        <div class="flex items-center gap-2">
                          <?php if ($isUnread): ?>
                            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse" title="Unread response from school"></span>
                          <?php endif; ?>
                          <a href="<?= BASE_URL ?>/admin/complaint-details.php?ticket=<?= urlencode($c['ticket_no'] ?? '') ?>" class="font-mono font-bold text-primary hover:underline text-xs">
                            <?= e($c['ticket_no'] ?? '') ?>
                          </a>
                        </div>
                        <div class="mt-1">
                          <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $statusClass ?>">
                            <?= e($c['status'] ?? 'Pending') ?>
                          </span>
                        </div>
                      </td>

                      <!-- School & Taluka -->
                      <td class="py-3.5 px-4">
                        <div class="font-semibold text-textMain max-w-xs truncate" title="<?= e($c['school_name'] ?? '') ?>">
                          <?= e($c['school_name'] ?? '') ?>
                        </div>
                        <div class="text-[11px] text-muted flex items-center gap-2 mt-0.5">
                          <span class="font-mono bg-slate-100 px-1 rounded text-[10px]">SEMIS: <?= e($c['semis_code'] ?? '') ?></span>
                          <span>&bull;</span>
                          <span><?= e($c['taluka'] ?? '') ?></span>
                        </div>
                      </td>

                      <!-- Category & Subject -->
                      <td class="py-3.5 px-4">
                        <span class="inline-block text-[10px] font-semibold text-secondary bg-secondary/10 px-2 py-0.5 rounded mb-1">
                          <?= e($c['category'] ?? 'General') ?>
                        </span>
                        <div class="font-medium text-textMain text-xs max-w-sm truncate" title="<?= e($c['subject'] ?? '') ?>">
                          <?= e($c['subject'] ?? '') ?>
                        </div>
                      </td>

                      <!-- Priority -->
                      <td class="py-3.5 px-4">
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] border <?= $priorityClass ?>">
                          <?= e($c['priority'] ?? 'Normal') ?>
                        </span>
                      </td>

                      <!-- Timeline -->
                      <td class="py-3.5 px-4 text-[11px] text-muted whitespace-nowrap">
                        <div>Reported: <span class="text-textMain"><?= date('M d, Y', strtotime($c['created_at'] ?? 'now')) ?></span></div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Last active: <?= date('M d, H:i', strtotime($c['updated_at'] ?? $c['created_at'] ?? 'now')) ?></div>
                      </td>

                      <!-- Actions -->
                      <td class="py-3.5 px-4 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1.5">
                          
                          <!-- View & Reply -->
                          <a href="<?= BASE_URL ?>/admin/complaint-details.php?ticket=<?= urlencode($c['ticket_no'] ?? '') ?>" class="px-2.5 py-1.5 bg-primary/10 hover:bg-primary text-primary hover:text-white rounded text-xs font-semibold transition flex items-center gap-1">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                            <span>Respond</span>
                          </a>

                          <!-- Quick Status dropdown -->
                          <button onclick="openStatusModal('<?= e($c['ticket_no'] ?? '') ?>', '<?= e($c['status'] ?? 'Pending') ?>')" class="p-1.5 text-slate-500 hover:text-textMain hover:bg-slate-100 rounded" title="Quick Status Change">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                          </button>

                          <!-- Delete button -->
                          <button onclick="confirmDeleteTicket('<?= e($c['ticket_no'] ?? '') ?>', '<?= e($c['school_name'] ?? '') ?>')" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded" title="Delete Ticket">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                          </button>

                        </div>
                      </td>

                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div>

      </main>

      <!-- Footer -->
      <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
  </div>

  <!-- Quick Status Modal -->
  <div id="statusModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface rounded-xl shadow-xl max-w-sm w-full p-5 border border-border">
      <h3 class="font-bold text-textMain text-sm">Update Ticket Status</h3>
      <p class="text-xs text-muted mt-1">Change current status for Ticket <span id="statusTicketNo" class="font-mono font-bold text-primary"></span></p>
      
      <form method="POST" action="<?= BASE_URL ?>/admin/complaints.php" class="mt-4 space-y-3">
        <input type="hidden" name="action" value="quick_status_update">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="ticket_no" id="modalTicketInput">

        <div>
          <label class="block text-xs font-semibold text-textMain mb-1">Select New Status</label>
          <select name="new_status" id="modalStatusSelect" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-background focus:outline-none focus:border-primary">
            <option value="Pending">Pending (Awaiting Action)</option>
            <option value="Under Review">Under Review</option>
            <option value="In Progress">In Progress (Action Initiated)</option>
            <option value="Resolved">Resolved</option>
            <option value="Closed">Closed</option>
          </select>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" onclick="closeStatusModal()" class="px-3 py-1.5 text-xs text-muted hover:text-textMain font-medium rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-1.5 text-xs bg-primary text-white font-semibold rounded-lg hover:bg-primaryDark">Save Status</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Ticket Modal -->
  <div id="deleteModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface rounded-xl shadow-xl max-w-sm w-full p-5 border border-border">
      <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-3">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      </div>
      <h3 class="font-bold text-textMain text-sm">Delete Complaint Ticket?</h3>
      <p class="text-xs text-muted mt-1">Are you sure you want to delete ticket <span id="deleteTicketNo" class="font-mono font-bold text-red-600"></span> from <span id="deleteSchoolName" class="font-semibold text-textMain"></span>? This will also remove all conversation records.</p>
      
      <form method="POST" action="<?= BASE_URL ?>/admin/complaints.php" class="mt-4">
        <input type="hidden" name="action" value="delete_complaint">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="ticket_no" id="deleteTicketInput">

        <div class="flex items-center justify-end gap-2">
          <button type="button" onclick="closeDeleteModal()" class="px-3 py-1.5 text-xs text-muted hover:text-textMain font-medium rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-1.5 text-xs bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.LSU_BASE_URL = '<?= BASE_URL ?>';

    function openStatusModal(ticketNo, currentStatus) {
      document.getElementById('statusTicketNo').textContent = ticketNo;
      document.getElementById('modalTicketInput').value = ticketNo;
      document.getElementById('modalStatusSelect').value = currentStatus;
      document.getElementById('statusModal').classList.remove('hidden');
    }
    function closeStatusModal() {
      document.getElementById('statusModal').classList.add('hidden');
    }

    function confirmDeleteTicket(ticketNo, schoolName) {
      document.getElementById('deleteTicketNo').textContent = ticketNo;
      document.getElementById('deleteSchoolName').textContent = schoolName;
      document.getElementById('deleteTicketInput').value = ticketNo;
      document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeDeleteModal() {
      document.getElementById('deleteModal').classList.add('hidden');
    }
  </script>
  <script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
