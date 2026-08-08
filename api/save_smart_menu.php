<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$menu_data = $data['menu_data'] ?? null;

if (empty($menu_data)) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_data']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Đóng (cancel) tất cả các thực đơn đang active của người dùng này
    $stmtCancel = $conn->prepare("UPDATE user_smart_menus SET status = 'cancelled' WHERE user_id = :user_id AND status = 'active'");
    $stmtCancel->execute([':user_id' => $_SESSION['user_id']]);
    
    // Thêm thực đơn mới
    $stmtInsert = $conn->prepare("INSERT INTO user_smart_menus (user_id, menu_data, completed_days, status) VALUES (:user_id, :menu_data, '[]', 'active')");
    $stmtInsert->execute([
        ':user_id' => $_SESSION['user_id'],
        ':menu_data' => is_string($menu_data) ? $menu_data : json_encode($menu_data)
    ]);
    
    echo json_encode(['status' => 'success', 'message' => 'Menu saved successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
