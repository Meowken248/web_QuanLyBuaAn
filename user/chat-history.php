<?php
// user/chat-history.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("DELETE FROM chat_conversations WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => $id, ':uid' => $user_id]);
    $_SESSION['success'] = 'Đã xóa đoạn chat.';
    redirect('/user/chat-history.php');
}

$stmt = $conn->prepare("SELECT * FROM chat_conversations WHERE user_id = :uid ORDER BY updated_at DESC");
$stmt->execute([':uid' => $user_id]);
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Lịch sử Chatbot AI';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3">
            <div class="list-group shadow-sm mb-4">
                <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="list-group-item list-group-item-action"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/user/profile.php" class="list-group-item list-group-item-action"><i class="bi bi-person-circle me-2"></i>Hồ sơ sức khỏe</a>
                <a href="<?php echo BASE_URL; ?>/user/meals.php" class="list-group-item list-group-item-action"><i class="bi bi-journal-text me-2"></i>Nhật ký bữa ăn</a>
                <a href="<?php echo BASE_URL; ?>/user/chatbot.php" class="list-group-item list-group-item-action"><i class="bi bi-robot me-2"></i>Chatbot AI</a>
                <a href="<?php echo BASE_URL; ?>/user/chat-history.php" class="list-group-item list-group-item-action active bg-success border-success"><i class="bi bi-clock-history me-2"></i>Lịch sử Chat</a>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0 text-success"><i class="bi bi-clock-history me-2"></i>Lịch sử trò chuyện với AI</h4>
                <a href="<?php echo BASE_URL; ?>/user/chatbot.php" class="btn btn-success"><i class="bi bi-plus-circle me-2"></i>Tạo chat mới</a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <?php foreach ($chats as $chat): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm border-0 h-100 card-hover">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold text-truncate pe-2" title="<?php echo htmlspecialchars($chat['title']); ?>">
                                        <a href="<?php echo BASE_URL; ?>/user/chatbot.php?id=<?php echo $chat['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($chat['title']); ?>
                                        </a>
                                    </h5>
                                </div>
                                <div class="text-muted small mb-3">
                                    <i class="bi bi-calendar2-check me-1"></i>
                                    Cập nhật lần cuối: <?php echo date('H:i - d/m/Y', strtotime($chat['updated_at'])); ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <a href="<?php echo BASE_URL; ?>/user/chatbot.php?id=<?php echo $chat['id']; ?>" class="btn btn-sm btn-outline-success">
                                        Tiếp tục chat <i class="bi bi-arrow-right"></i>
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Xóa đoạn hội thoại này? Mọi tin nhắn sẽ bị mất.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $chat['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($chats)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-chat-left-dots text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">Bạn chưa có cuộc trò chuyện nào với AI.</h5>
                        <a href="<?php echo BASE_URL; ?>/user/chatbot.php" class="btn btn-outline-success mt-3">Bắt đầu trò chuyện ngay</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
