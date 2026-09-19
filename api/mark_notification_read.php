<?php
// api/mark_notification_read.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$action = $_POST['action'] ?? ($_GET['action'] ?? 'single'); // 'single' or 'all'

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($action === 'all') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
    } elseif ($id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
