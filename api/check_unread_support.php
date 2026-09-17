<?php
// api/check_unread_support.php
// Trả về số thông báo và tin nhắn hỗ trợ chưa đọc cho Polling Realtime

require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => false,
        'unread_support' => 0,
        'unread_notif' => 0,
        'total' => 0
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // 1. Đếm số thông báo hệ thống chưa đọc
    $stmtNotif = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmtNotif->execute([':user_id' => $user_id]);
    $unread_notif_count = (int)$stmtNotif->fetchColumn();

    // 2. Đếm số tin nhắn hỗ trợ trực tuyến chưa đọc (BUG-02)
    $unread_support_count = 0;
    if ($is_admin) {
        $stmtSup = $conn->query("SELECT COUNT(*) FROM support_messages WHERE sender_type = 'user' AND is_read = 0");
        $unread_support_count = (int)$stmtSup->fetchColumn();
    } else {
        $stmtSup = $conn->prepare("SELECT COUNT(sm.id) FROM support_messages sm 
                                   JOIN support_chats sc ON sm.chat_id = sc.id 
                                   WHERE sc.user_id = :user_id AND sm.sender_type = 'admin' AND sm.is_read = 0");
        $stmtSup->execute([':user_id' => $user_id]);
        $unread_support_count = (int)$stmtSup->fetchColumn();
    }

    $total_bell = $unread_notif_count + $unread_support_count;

    echo json_encode([
        'logged_in' => true,
        'is_admin' => $is_admin,
        'unread_support' => $unread_support_count,
        'unread_notif' => $unread_notif_count,
        'total' => $total_bell
    ]);
} catch (Exception $e) {
    echo json_encode([
        'logged_in' => true,
        'is_admin' => $is_admin,
        'unread_support' => 0,
        'unread_notif' => 0,
        'total' => 0,
        'error' => $e->getMessage()
    ]);
}
