<?php
/**
 * api/thread-messages.php
 * 
 * AJAX Endpoint for Real-Time Direct Message Thread Streaming & Sending.
 * Supports polling for new replies and asynchronous reply posting without page reload.
 */

header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';

if (!isset($_SESSION['lsu_logged_in']) || $_SESSION['lsu_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$isAdmin = is_admin();
$schoolSemis = $_SESSION['lsu_school_semis'] ?? '';

$threadId = trim($_REQUEST['thread_id'] ?? $_REQUEST['thread'] ?? '');
if (empty($threadId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Thread ID is required']);
    exit;
}

$thread = ExcelDB::find('admin_messages', 'thread_id', $threadId);
if (!$thread) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Thread not found']);
    exit;
}

// Security authorization check for school role
if (!$isAdmin && trim($thread['semis_code'] ?? '') !== trim($schoolSemis)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied to this message thread']);
    exit;
}

// ── Handle POST (Send Message) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'post_reply';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF security token']);
        exit;
    }

    if ($action === 'post_reply') {
        $message = trim($_POST['message'] ?? '');
        $statusChange = trim($_POST['status_change'] ?? '');

        if (strtolower($thread['status'] ?? 'open') === 'closed') {
            echo json_encode(['success' => false, 'error' => 'This conversation is closed. No messages can be sent.']);
            exit;
        }

        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
            exit;
        }

        if ($isAdmin) {
            $senderRole = 'admin';
            $senderName = ($_SESSION['lsu_username'] ?? 'District RSU Coordinator') . ' (District RSU)';
        } else {
            $senderRole = 'school';
            $senderName = ($_SESSION['lsu_username'] ?? 'Head Master') . ' (HM)';
        }

        $replies = ExcelDB::all('admin_message_replies');
        $maxId = 0;
        foreach ($replies as $r) {
            if (isset($r['id']) && is_numeric($r['id']) && (int)$r['id'] > $maxId) {
                $maxId = (int)$r['id'];
            }
        }
        $newId = (string)($maxId + 1);
        $now = date('Y-m-d H:i:s');

        $replies[] = [
            'id'          => $newId,
            'thread_id'   => $threadId,
            'sender_role' => $senderRole,
            'sender_name' => $senderName,
            'message'     => $message,
            'created_at'  => $now
        ];
        ExcelDB::writeTable('admin_message_replies', $replies);

        $updateData = ['updated_at' => $now];
        if ($isAdmin) {
            $updateData['unread_school'] = '1';
            $updateData['unread_admin']  = '0';
            if (!empty($statusChange) && in_array($statusChange, ['Open', 'Closed'], true)) {
                $updateData['status'] = $statusChange;
            }
        } else {
            $updateData['unread_admin']  = '1';
            $updateData['unread_school'] = '0';
        }

        ExcelDB::update('admin_messages', 'thread_id', $threadId, $updateData);
        $updatedThread = ExcelDB::find('admin_messages', 'thread_id', $threadId);

        echo json_encode([
            'success'       => true,
            'message_id'    => $newId,
            'sender_role'   => $senderRole,
            'sender_name'   => $senderName,
            'message'       => $message,
            'created_at'    => $now,
            'time_fmt'      => date('M d, h:i A', strtotime($now)),
            'thread_status' => $updatedThread['status'] ?? 'Open'
        ]);
        exit;
    }
}

// ── Handle GET (Fetch Messages / Live Polling) ───────────────────────────────
$afterId = isset($_GET['after_id']) && is_numeric($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

// Auto-mark as read when inside thread view
if ($isAdmin && ($thread['unread_admin'] ?? '0') === '1') {
    ExcelDB::update('admin_messages', 'thread_id', $threadId, ['unread_admin' => '0']);
} elseif (!$isAdmin && ($thread['unread_school'] ?? '0') === '1') {
    ExcelDB::update('admin_messages', 'thread_id', $threadId, ['unread_school' => '0']);
}

$allReplies = ExcelDB::getRepliesForThread($threadId);
$newReplies = [];
$latestId = 0;

foreach ($allReplies as $r) {
    $rId = isset($r['id']) && is_numeric($r['id']) ? (int)$r['id'] : 0;
    if ($rId > $latestId) {
        $latestId = $rId;
    }
    if ($rId > $afterId) {
        $newReplies[] = [
            'id'          => $rId,
            'thread_id'   => $r['thread_id'] ?? $threadId,
            'sender_role' => $r['sender_role'] ?? 'admin',
            'sender_name' => $r['sender_name'] ?? '',
            'message'     => $r['message'] ?? '',
            'created_at'  => $r['created_at'] ?? '',
            'time_fmt'    => date('M d, h:i A', strtotime($r['created_at'] ?? 'now'))
        ];
    }
}

echo json_encode([
    'success'       => true,
    'thread_id'     => $threadId,
    'thread_status' => $thread['status'] ?? 'Open',
    'total_count'   => count($allReplies),
    'latest_id'     => $latestId,
    'messages'      => $newReplies,
    'timestamp'     => time()
]);
