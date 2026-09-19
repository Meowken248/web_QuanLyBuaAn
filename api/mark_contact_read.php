<?php
// api/mark_contact_read.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Cập nhật trạng thái tin nhắn thành 'read' nếu đang là 'new'
    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = :id AND status = 'new'");
    $stmt->execute([':id' => $id]);

    // Đánh dấu thông báo tương ứng của admin là đã đọc
    $stmtNotif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :admin_id AND message LIKE :pat");
    $stmtNotif->execute([
        ':admin_id' => $admin_id,
        ':pat' => '%[MID:' . $id . ']%'
    ]);

    // Lấy số lượng thư chưa đọc còn lại
    $stmtCount = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    $unread_contacts = (int)$stmtCount->fetchColumn();

    echo json_encode(['success' => true, 'unread_contacts' => $unread_contacts]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
