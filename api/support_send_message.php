<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'user';
$message = trim($_POST['message'] ?? '');
$target_user_id = $_POST['target_user_id'] ?? null; // For admin replying to a specific user

if (!$user_id || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sender_type = ($user_role === 'admin') ? 'admin' : 'user';
    $chat_user_id = ($user_role === 'admin' && $target_user_id) ? (int)$target_user_id : $user_id;

    // Find or create chat session
    $stmt = $conn->prepare("SELECT id FROM support_chats WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $chat_user_id]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chat) {
        $stmt = $conn->prepare("INSERT INTO support_chats (user_id) VALUES (:user_id)");
        $stmt->execute([':user_id' => $chat_user_id]);
        $chat_id = $conn->lastInsertId();
    } else {
        $chat_id = $chat['id'];
        // Re-open chat if it was closed
        $conn->prepare("UPDATE support_chats SET status = 'open' WHERE id = :id")->execute([':id' => $chat_id]);
    }

    // Insert message
    $stmt = $conn->prepare("INSERT INTO support_messages (chat_id, sender_type, message) VALUES (:chat_id, :sender_type, :message)");
    $stmt->execute([
        ':chat_id' => $chat_id,
        ':sender_type' => $sender_type,
        ':message' => $message
    ]);

    // Tạo thông báo vào bảng notifications
    try {
        if ($sender_type === 'user') {
            // Lấy tên người gửi
            $sender_name = $_SESSION['full_name'] ?? 'Khách hàng';
            $notif_title = '💬 Tin nhắn mới từ ' . mb_substr($sender_name, 0, 80, 'UTF-8');
            $notif_message = '[UID:' . $chat_user_id . '] ' . mb_substr($message, 0, 250, 'UTF-8');

            // Gửi thông báo cho tất cả tài khoản Admin
            $stmtAdmins = $conn->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);

            $stmtInsertNotif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (:uid, :title, :message, 'info', 0)");
            foreach ($admins as $admin_id) {
                $stmtInsertNotif->execute([
                    ':uid' => $admin_id,
                    ':title' => $notif_title,
                    ':message' => $notif_message
                ]);
            }
        } else {
            // Admin trả lời -> gửi thông báo cho User
            $notif_title = '💬 Phản hồi từ Ban quản trị';
            $notif_message = mb_substr($message, 0, 250, 'UTF-8');

            $stmtInsertNotif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (:uid, :title, :message, 'info', 0)");
            $stmtInsertNotif->execute([
                ':uid' => $chat_user_id,
                ':title' => $notif_title,
                ':message' => $notif_message
            ]);
        }
    } catch (Exception $notifEx) {
        // Không để lỗi thông báo ảnh hưởng đến việc gửi tin nhắn
    }

    echo json_encode(['success' => true, 'message' => 'Sent']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
