<?php
/**
 * api/check-complaints.php
 * 
 * AJAX endpoint for real-time notification polling & badge synchronization.
 * Returns unread complaint and reply counts for Admin and School roles.
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
$unreadCount = 0;
$pendingCount = 0;
$items = [];
$latestId = '';
$latestMessage = '';

if ($role === 'admin') {
    foreach ($allComplaints as $c) {
        $status = strtolower($c['status'] ?? '');
        $isUnread = ($c['unread_admin'] ?? '0') === '1';
        $isPending = ($status === 'pending');

        if ($isPending) {
            $pendingCount++;
        }
        if ($isUnread || $isPending) {
            $unreadCount++;
            $items[] = [
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
} else {
    foreach ($allComplaints as $c) {
        if (($c['semis_code'] ?? '') === $semisCode) {
            $status = strtolower($c['status'] ?? '');
            $isUnread = ($c['unread_school'] ?? '0') === '1';

            if ($isUnread) {
                $unreadCount++;
                $items[] = [
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
}

// Sort items by updated_at descending
usort($items, function($a, $b) {
    return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
});

echo json_encode([
    'success'       => true,
    'role'          => $role,
    'unread_count'  => $unreadCount,
    'pending_count' => $pendingCount,
    'latest_ticket' => $items[0]['ticket_no'] ?? '',
    'latest_title'  => $items[0]['subject'] ?? '',
    'latest_school' => $items[0]['school_name'] ?? '',
    'notifications' => array_slice($items, 0, 5),
    'timestamp'     => time()
]);
