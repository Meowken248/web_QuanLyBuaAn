<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'user';
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$target_user_id = $_GET['target_user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $chat_user_id = ($user_role === 'admin' && $target_user_id) ? (int)$target_user_id : $user_id;

    // Get chat id
    $stmt = $conn->prepare("SELECT id FROM support_chats WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $chat_user_id]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chat) {
        echo json_encode(['success' => true, 'messages' => []]);
        exit;
    }

    $chat_id = $chat['id'];

    // If admin is viewing, mark user messages as read
    if ($user_role === 'admin') {
        $conn->prepare("UPDATE support_messages SET is_read = 1 WHERE chat_id = :chat_id AND sender_type = 'user' AND is_read = 0")
             ->execute([':chat_id' => $chat_id]);
    } else {
        // If user is viewing, mark admin messages as read
        $conn->prepare("UPDATE support_messages SET is_read = 1 WHERE chat_id = :chat_id AND sender_type = 'admin' AND is_read = 0")
             ->execute([':chat_id' => $chat_id]);
    }

    $stmt = $conn->prepare("
        SELECT id, sender_type, message, created_at 
        FROM support_messages 
        WHERE chat_id = :chat_id AND id > :last_id 
        ORDER BY id ASC
    ");
    $stmt->execute([':chat_id' => $chat_id, ':last_id' => $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'messages' => []]);
}
