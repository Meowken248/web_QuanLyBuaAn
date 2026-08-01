<?php
// admin/contact-messages.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Xử lý cập nhật trạng thái hoặc xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    
    $id = (int)$_POST['id'];
    
    if ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = 'Đã xóa tin nhắn liên hệ.';
    } elseif ($_POST['action'] === 'update_status') {
        $status = $_POST['status'];
        if (in_array($status, ['new', 'read', 'replied'])) {
            $stmt = $conn->prepare("UPDATE contact_messages SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            $_SESSION['success'] = 'Đã cập nhật trạng thái tin nhắn.';
        }
    }
    redirect('/admin/contact-messages.php');
}

// Lấy danh sách tin nhắn
$query = "SELECT * FROM contact_messages ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Liên hệ';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <h3 class="fw-bold mb-4">Hộp thư Liên hệ</h3>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Thời gian</th>
                                    <th>Người gửi</th>
                                    <th>Email</th>
                                    <th>Chủ đề</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $msg): ?>
                                <tr class="<?php echo $msg['status'] === 'new' ? 'table-warning' : ''; ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($msg['full_name']); ?></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>"><?php echo htmlspecialchars($msg['email']); ?></a></td>
                                    <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                    <td>
                                        <?php if ($msg['status'] === 'new'): ?>
                                            <span class="badge bg-danger">Chưa đọc</span>
                                        <?php elseif ($msg['status'] === 'read'): ?>
                                            <span class="badge bg-primary">Đã đọc</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Đã xử lý</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>/admin/contact-message-view.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="Xem chi tiết">
                                            <i class="bi bi-eye"></i> Xem
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tin nhắn này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Không có tin nhắn liên hệ nào.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (isset($hide_footer) && $hide_footer) {
    echo '</body></html>';
} else {
    require_once __DIR__ . '/../includes/footer.php'; 
}
?>
