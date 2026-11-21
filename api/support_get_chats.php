<?php
// api/support_get_chats.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("
        SELECT sc.id, sc.user_id, sc.status, u.full_name, u.email,
               (SELECT message FROM support_messages WHERE chat_id = sc.id ORDER BY id DESC LIMIT 1) as last_message,
               (SELECT created_at FROM support_messages WHERE chat_id = sc.id ORDER BY id DESC LIMIT 1) as last_time,
               (SELECT COUNT(id) FROM support_messages WHERE chat_id = sc.id AND sender_type = 'user' AND is_read = 0) as unread_count
        FROM support_chats sc
        JOIN users u ON sc.user_id = u.id
        ORDER BY last_time DESC, sc.created_at DESC
    ");
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'chats' => $chats]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
