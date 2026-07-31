<?php
// admin/subscription-plans.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Xử lý xóa gói đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_plan') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    
    $id = (int)$_POST['id'];
    
    // Kiểm tra xem gói có người dùng nào đang sử dụng không
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE plan_id = :id AND status = 'active'");
    $stmt->execute([':id' => $id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['error'] = 'Không thể xóa gói đăng ký này vì đang có ' . $count . ' người dùng sử dụng. Hãy chuyển trạng thái gói thành "Tạm ẩn" thay vì xóa.';
    } else {
        $stmt = $conn->prepare("DELETE FROM subscription_plans WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = 'Đã xóa gói đăng ký thành công.';
    }
    redirect('/admin/subscription-plans.php');
}

// Lấy danh sách gói đăng ký
$query = "SELECT p.*, (SELECT COUNT(*) FROM user_subscriptions WHERE plan_id = p.id AND status = 'active') as active_users FROM subscription_plans p ORDER BY p.price ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Gói Đăng ký';
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
                <h3 class="fw-bold mb-0">Quản lý Gói Đăng ký</h3>
                <a href="<?php echo BASE_URL; ?>/admin/subscription-plan-edit.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Thêm Gói mới
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Gói</th>
                                    <th>Giá (VNĐ)</th>
                                    <th>Thời hạn</th>
                                    <th>Trạng thái</th>
                                    <th>Người dùng đang dùng</th>
                                    <th class="text-end">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $plan): ?>
                                <tr>
                                    <td><?php echo $plan['id']; ?></td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($plan['name']); ?></td>
                                    <td class="fw-bold text-danger"><?php echo number_format($plan['price'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo $plan['duration_days']; ?> ngày</td>
                                    <td>
                                        <?php if ($plan['status'] === 'active'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tạm ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark rounded-pill"><?php echo $plan['active_users']; ?> user</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>/admin/subscription-plan-edit.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa gói này? Lưu ý: Không thể xóa gói đang có người dùng.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_plan">
                                            <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa" <?php echo ($plan['active_users'] > 0) ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($plans)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Chưa có gói đăng ký nào.</td>
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
