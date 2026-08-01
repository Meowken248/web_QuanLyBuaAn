<?php
// admin/users.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $target_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($target_id && $target_id !== (int)$_SESSION['user_id']) {
            $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'locked', 'active') WHERE id = :id AND role <> 'admin'");
            $stmt->execute([':id' => $target_id]);
            set_flash_message($stmt->rowCount() ? 'success' : 'warning', $stmt->rowCount() ? 'Đã cập nhật trạng thái tài khoản.' : 'Không thể thay đổi tài khoản này.');
        }
    }
    redirect('/admin/users.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $target_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($target_id && $target_id !== (int)$_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND role <> 'admin'");
            $stmt->execute([':id' => $target_id]);
            set_flash_message($stmt->rowCount() ? 'success' : 'warning', $stmt->rowCount() ? 'Đã xóa người dùng.' : 'Không thể xóa người dùng này (có thể là Admin).');
        } else {
            set_flash_message('danger', 'Không thể xóa tài khoản của chính mình.');
        }
    }
    redirect('/admin/users.php');
}

// Lấy danh sách users
$users = $conn->query("
    SELECT id, full_name, email, role, status, created_at
    FROM users
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Người dùng';
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
                <h3 class="fw-bold mb-0">Quản lý Người dùng</h3>
                <div>
                    <?php display_flash_message(); ?>
                    <a href="<?php echo BASE_URL; ?>/admin/user-edit.php" class="btn btn-success"><i class="bi bi-person-plus me-2"></i>Thêm người dùng</a>
                </div>
            </div>
            
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Họ Tên</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Quyền sử dụng</th>
                                    <th>Ngày đăng ký</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td><span class="fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Đầy đủ · Miễn phí</span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-info" title="Xem chi tiết" data-bs-toggle="modal" data-bs-target="#userModal<?php echo $u['id']; ?>"><i class="bi bi-eye"></i></button>
                                            <a href="<?php echo BASE_URL; ?>/admin/user-edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="bi bi-pencil"></i></a>
                                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Xác nhận thay đổi trạng thái tài khoản?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success'; ?>" title="<?php echo $u['status'] === 'active' ? 'Khóa' : 'Mở khóa'; ?>"><i class="bi <?php echo $u['status'] === 'active' ? 'bi-lock' : 'bi-unlock'; ?>"></i></button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa người dùng này không? Hành động này không thể hoàn tác.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Modal Chi Tiết Người Dùng -->
                                <div class="modal fade" id="userModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">Chi tiết người dùng</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 20px;">
                                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></h5>
                                                        <div class="text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                                    </div>
                                                </div>
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                                        <span>Vai trò</span>
                                                        <strong><?php echo $u['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng'; ?></strong>
                                                    </li>
                                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                                        <span>Quyền sử dụng</span>
                                                        <strong class="text-success">Toàn bộ tính năng · Miễn phí</strong>
                                                    </li>
                                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                                        <span>Ngày đăng ký</span>
                                                        <strong><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></strong>
                                                    </li>
                                                </ul>
                                                <div class="alert alert-info mt-3 mb-0 small">
                                                    <i class="bi bi-info-circle me-1"></i> Trạng thái tài khoản: <?php echo $u['status'] === 'active' ? 'Đang hoạt động' : 'Đã khóa'; ?>.
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
