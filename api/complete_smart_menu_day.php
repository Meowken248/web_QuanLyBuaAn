<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$day = $data['day'] ?? '';

if (empty($day)) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_data']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $title = "🎉 Hoàn thành Thực đơn AI (Ngày " . $day . ")";
    $message = "Chúc mừng! Bạn đã hoàn thành xuất sắc thực đơn dinh dưỡng của ngày " . $day . ". Hãy tiếp tục duy trì thói quen ăn uống lành mạnh này nhé!";
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, :title, :message, 'success')");
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':title' => $title,
        ':message' => $message
    ]);
    
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
