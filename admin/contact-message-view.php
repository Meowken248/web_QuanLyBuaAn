<?php
// admin/contact-message-view.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy thông tin tin nhắn
$stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = :id");
$stmt->execute([':id' => $id]);
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) {
    $_SESSION['error'] = 'Tin nhắn không tồn tại.';
    redirect('/admin/contact-messages.php');
}

// Tự động đánh dấu là đã đọc nếu đang là 'new'
if ($msg['status'] === 'new') {
    $updateStmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = :id");
    $updateStmt->execute([':id' => $id]);
    $msg['status'] = 'read';
}

$page_title = 'Chi tiết Liên hệ';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Chi tiết Tin nhắn</h3>
                <a href="<?php echo BASE_URL; ?>/admin/contact-messages.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0 text-primary">
                        Chủ đề: <?php echo htmlspecialchars($msg['subject']); ?>
                    </h5>
                    <div>
                        <?php if ($msg['status'] === 'read'): ?>
                            <span class="badge bg-primary fs-6">Đã đọc</span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6">Đã xử lý</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted">Người gửi:</p>
                            <p class="fw-bold fs-5 mb-0"><i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($msg['full_name']); ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1 text-muted">Thời gian gửi:</p>
                            <p class="fw-bold mb-0"><i class="bi bi-clock me-2"></i><?php echo date('d/m/Y H:i:s', strtotime($msg['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <p class="mb-1 text-muted">Email liên hệ:</p>
                        <p class="fw-bold mb-0">
                            <i class="bi bi-envelope me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>"><?php echo htmlspecialchars($msg['email']); ?></a>
                        </p>
                    </div>

                    <div class="p-4 bg-light rounded-3 border">
                        <p class="mb-2 text-muted fw-bold">Nội dung tin nhắn:</p>
                        <div class="fs-5" style="white-space: pre-wrap;"><?php echo htmlspecialchars($msg['message']); ?></div>
                    </div>
                </div>
                <div class="card-footer bg-white py-3">
                    <form method="POST" action="<?php echo BASE_URL; ?>/admin/contact-messages.php" class="d-flex justify-content-end align-items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                        
                        <?php if ($msg['status'] !== 'resolved'): ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" value="resolved">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Đánh dấu Đã xử lý
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" value="read">
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Chuyển về Đã đọc
                            </button>
                        <?php endif; ?>
                        
                        <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>?subject=Re: <?php echo urlencode($msg['subject']); ?>" class="btn btn-primary">
                            <i class="bi bi-reply me-1"></i>Phản hồi qua Email
                        </a>
                    </form>
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
