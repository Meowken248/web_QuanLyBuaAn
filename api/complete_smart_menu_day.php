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
$menuId = $data['menu_id'] ?? '';

if (empty($day)) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_data']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!empty($menuId)) {
        $stmtMenu = $conn->prepare("SELECT completed_days FROM user_smart_menus WHERE id = :id AND user_id = :user_id");
        $stmtMenu->execute([':id' => $menuId, ':user_id' => $_SESSION['user_id']]);
        if ($menuRow = $stmtMenu->fetch(PDO::FETCH_ASSOC)) {
            $completed = json_decode($menuRow['completed_days'] ?? '[]', true);
            if (!in_array($day, $completed)) {
                $completed[] = (int)$day;
                $stmtUpdate = $conn->prepare("UPDATE user_smart_menus SET completed_days = :completed_days WHERE id = :id");
                $stmtUpdate->execute([
                    ':completed_days' => json_encode($completed),
                    ':id' => $menuId
                ]);
            }
        }
    }
    
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
