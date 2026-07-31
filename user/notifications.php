<?php
// user/notifications.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Xử lý đánh dấu đã đọc tất cả
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    
    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã đánh dấu tất cả thông báo là đã đọc.';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => (int)$_POST['id'], ':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa thông báo.';
    }
    redirect('/user/notifications.php');
}

// Nếu có tham số ?read=id trên URL
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    redirect('/user/notifications.php');
}

// Lấy danh sách thông báo (phân trang)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmtTotal = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id");
$stmtTotal->execute([':user_id' => $user_id]);
$total_notifs = $stmtTotal->fetchColumn();
$total_pages = ceil($total_notifs / $limit);

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Thông báo của bạn';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Thông báo của bạn</h2>
        <?php if ($total_notifs > 0): ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-check-all me-1"></i>Đánh dấu tất cả là đã đọc
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (count($notifications) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <div class="list-group-item py-3 <?php echo $n['is_read'] ? '' : 'bg-light border-start border-primary border-4'; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-start mb-1">
                                <h5 class="mb-1 fw-bold <?php echo $n['is_read'] ? 'text-dark' : 'text-primary'; ?>">
                                    <?php 
                                        $icon = 'bi-info-circle text-info';
                                        if ($n['type'] == 'success') $icon = 'bi-check-circle text-success';
                                        if ($n['type'] == 'warning') $icon = 'bi-exclamation-triangle text-warning';
                                        if ($n['type'] == 'danger') $icon = 'bi-x-circle text-danger';
                                    ?>
                                    <i class="bi <?php echo $icon; ?> me-2"></i>
                                    <?php echo htmlspecialchars($n['title']); ?>
                                </h5>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></small>
                            </div>
                            <p class="mb-2 ms-4"><?php echo nl2br(htmlspecialchars($n['message'])); ?></p>
                            <div class="d-flex justify-content-end gap-2">
                                <?php if (!$n['is_read']): ?>
                                    <a href="?read=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-primary">Đánh dấu đã đọc</a>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Xóa thông báo này?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                    <h5>Bạn không có thông báo nào.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Phân trang -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4" aria-label="Page navigation">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
