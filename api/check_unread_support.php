<?php
// api/check_unread_support.php
// Trả về số thông báo, tin nhắn hỗ trợ chưa đọc và dữ liệu chi tiết cho Polling Realtime

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => false,
        'unread_support' => 0,
        'unread_notif' => 0,
        'total' => 0,
        'support_items' => [],
        'recent_notifications' => [],
        'latest_msg_id' => 0
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

    // 2. Chi tiết tin nhắn hỗ trợ trực tuyến chưa đọc
    $unread_support_count = 0;
    $support_items = [];
    $latest_msg_id = 0;

    if ($is_admin) {
        $stmtSupCount = $conn->query("SELECT COUNT(*) FROM support_messages WHERE sender_type = 'user' AND is_read = 0");
        $unread_support_count = (int)$stmtSupCount->fetchColumn();

        if ($unread_support_count > 0) {
            // Lấy danh sách hội thoại có tin nhắn chưa đọc từ user
            $stmtSupList = $conn->query("
                SELECT sc.user_id, u.full_name, sm.id as msg_id, sm.message, sm.created_at,
                       (SELECT COUNT(*) FROM support_messages sm2 WHERE sm2.chat_id = sc.id AND sm2.sender_type = 'user' AND sm2.is_read = 0) as unread_count
                FROM support_messages sm
                JOIN support_chats sc ON sm.chat_id = sc.id
                JOIN users u ON sc.user_id = u.id
                WHERE sm.sender_type = 'user' AND sm.is_read = 0
                AND sm.id = (SELECT MAX(sm3.id) FROM support_messages sm3 WHERE sm3.chat_id = sc.id AND sm3.sender_type = 'user' AND sm3.is_read = 0)
                ORDER BY sm.created_at DESC
                LIMIT 5
            ");
            $support_items = $stmtSupList->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($support_items)) {
                $latest_msg_id = (int)$support_items[0]['msg_id'];
            }
        }

        // Đếm số thư liên hệ mới chưa đọc & ID thư liên hệ mới nhất
        $unread_contact_count = 0;
        $latest_contact_id = 0;
        try {
            $stmtCtCount = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
            $unread_contact_count = (int)$stmtCtCount->fetchColumn();

            $stmtLastCt = $conn->query("SELECT id FROM contact_messages ORDER BY id DESC LIMIT 1");
            $latest_contact_id = (int)($stmtLastCt ? $stmtLastCt->fetchColumn() : 0);
        } catch (Exception $ctEx) {}
    } else {
        $stmtSupCount = $conn->prepare("SELECT COUNT(sm.id) FROM support_messages sm 
                                   JOIN support_chats sc ON sm.chat_id = sc.id 
                                   WHERE sc.user_id = :user_id AND sm.sender_type = 'admin' AND sm.is_read = 0");
        $stmtSupCount->execute([':user_id' => $user_id]);
        $unread_support_count = (int)$stmtSupCount->fetchColumn();

        if ($unread_support_count > 0) {
            $stmtSupList = $conn->prepare("
                SELECT sm.id as msg_id, sm.message, sm.created_at
                FROM support_messages sm
                JOIN support_chats sc ON sm.chat_id = sc.id
                WHERE sc.user_id = :user_id AND sm.sender_type = 'admin' AND sm.is_read = 0
                ORDER BY sm.created_at DESC
                LIMIT 1
            ");
            $stmtSupList->execute([':user_id' => $user_id]);
            $lastAdminMsg = $stmtSupList->fetch(PDO::FETCH_ASSOC);
            if ($lastAdminMsg) {
                $latest_msg_id = (int)$lastAdminMsg['msg_id'];
                $support_items[] = [
                    'user_id' => $user_id,
                    'full_name' => 'Ban quản trị',
                    'msg_id' => $lastAdminMsg['msg_id'],
                    'message' => $lastAdminMsg['message'],
                    'created_at' => $lastAdminMsg['created_at'],
                    'unread_count' => $unread_support_count
                ];
            }
        }
    }

    // 3. Lấy 5 thông báo hệ thống mới nhất cho dropdown
    $stmtNotifList = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
    $stmtNotifList->execute([':user_id' => $user_id]);
    $recent_notifications = $stmtNotifList->fetchAll(PDO::FETCH_ASSOC);

    $total_bell = $unread_notif_count + $unread_support_count;

    echo json_encode([
        'logged_in' => true,
        'is_admin' => $is_admin,
        'unread_support' => $unread_support_count,
        'unread_contacts' => $unread_contact_count ?? 0,
        'unread_notif' => $unread_notif_count,
        'total' => $total_bell,
        'latest_msg_id' => $latest_msg_id,
        'latest_contact_id' => $latest_contact_id ?? 0,
        'support_items' => $support_items,
        'recent_notifications' => $recent_notifications
    ]);
} catch (Exception $e) {
    echo json_encode([
        'logged_in' => true,
        'is_admin' => $is_admin ?? false,
        'unread_support' => 0,
        'unread_contacts' => 0,
        'unread_notif' => 0,
        'total' => 0,
        'support_items' => [],
        'recent_notifications' => [],
        'latest_msg_id' => 0,
        'latest_contact_id' => 0,
        'error' => $e->getMessage()
    ]);
}
