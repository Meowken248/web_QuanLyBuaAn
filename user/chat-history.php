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

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$stmtTotal = $conn->prepare("SELECT COUNT(*) FROM chat_conversations WHERE user_id = :uid");
$stmtTotal->execute([':uid' => $user_id]);
$total_chats = (int)$stmtTotal->fetchColumn();
$total_pages = ceil($total_chats / $limit);

$stmt = $conn->prepare("SELECT * FROM chat_conversations WHERE user_id = :uid ORDER BY updated_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Lịch sử Chatbot AI';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
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

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Tiếp</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
