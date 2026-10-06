<?php
/**
 * api/check-complaints.php
 * 
 * AJAX endpoint for real-time notification polling & badge synchronization.
 * Returns unread complaint and direct message counts for Admin and School roles.
 */

header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';

if (!isset($_SESSION['lsu_logged_in']) || $_SESSION['lsu_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$role = is_admin() ? 'admin' : 'school';
$semisCode = $_SESSION['lsu_school_semis'] ?? '';

$allComplaints = ExcelDB::all('complaints');
$allMessages   = ExcelDB::all('admin_messages');

$unreadComplaints = 0;
$unreadMessages   = 0;
$pendingCount     = 0;
$items            = [];

if ($role === 'admin') {
    // 1. Check Complaints for Admin
    foreach ($allComplaints as $c) {
        $status = strtolower($c['status'] ?? '');
        $isUnread = ($c['unread_admin'] ?? '0') === '1';
        $isPending = ($status === 'pending');

        if ($isPending) {
            $pendingCount++;
        }
        if ($isUnread || $isPending) {
            $unreadComplaints++;
            $items[] = [
                'type'        => 'complaint',
                'ticket_no'   => $c['ticket_no'] ?? '',
                'school_name' => $c['school_name'] ?? '',
                'category'    => $c['category'] ?? '',
                'subject'     => $c['subject'] ?? '',
                'priority'    => $c['priority'] ?? 'Normal',
                'status'      => $c['status'] ?? 'Pending',
                'updated_at'  => $c['updated_at'] ?? $c['created_at'] ?? '',
            ];
        }
    }

    // 2. Check Direct Messages for Admin
    foreach ($allMessages as $m) {
        $isUnread = ($m['unread_admin'] ?? '0') === '1';
        if ($isUnread) {
            $unreadMessages++;
            $items[] = [
                'type'        => 'message',
                'thread_id'   => $m['thread_id'] ?? '',
                'school_name' => $m['school_name'] ?? '',
                'subject'     => $m['subject'] ?? 'Direct Message',
                'created_by'  => $m['created_by'] ?? 'District RSU',
                'status'      => $m['status'] ?? 'Open',
                'updated_at'  => $m['updated_at'] ?? $m['created_at'] ?? '',
            ];
        }
    }
} else {
    // 1. Check Complaints for School
    foreach ($allComplaints as $c) {
        if (trim($c['semis_code'] ?? '') === trim($semisCode)) {
            $isUnread = ($c['unread_school'] ?? '0') === '1';
            if ($isUnread) {
                $unreadComplaints++;
                $items[] = [
                    'type'        => 'complaint',
                    'ticket_no'   => $c['ticket_no'] ?? '',
                    'school_name' => $c['school_name'] ?? '',
                    'category'    => $c['category'] ?? '',
                    'subject'     => $c['subject'] ?? '',
                    'priority'    => $c['priority'] ?? 'Normal',
                    'status'      => $c['status'] ?? 'Pending',
                    'updated_at'  => $c['updated_at'] ?? $c['created_at'] ?? '',
                ];
            }
        }
    }

    // 2. Check Direct Messages for School
    foreach ($allMessages as $m) {
        if (trim($m['semis_code'] ?? '') === trim($semisCode)) {
            $isUnread = ($m['unread_school'] ?? '0') === '1';
            if ($isUnread) {
                $unreadMessages++;
                $items[] = [
                    'type'        => 'message',
                    'thread_id'   => $m['thread_id'] ?? '',
                    'school_name' => $m['school_name'] ?? '',
                    'subject'     => $m['subject'] ?? 'Direct Message from District RSU',
                    'created_by'  => $m['created_by'] ?? 'District RSU',
                    'status'      => $m['status'] ?? 'Open',
                    'updated_at'  => $m['updated_at'] ?? $m['created_at'] ?? '',
                ];
            }
        }
    }
}

// Sort items by updated_at descending
usort($items, function($a, $b) {
    return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
});

$totalUnread = $unreadComplaints + $unreadMessages;

echo json_encode([
    'success'           => true,
    'role'              => $role,
    'unread_count'      => $totalUnread,
    'unread_complaints' => $unreadComplaints,
    'unread_messages'   => $unreadMessages,
    'pending_count'     => $pendingCount,
    'notifications'     => array_slice($items, 0, 8),
    'timestamp'         => time()
]);
