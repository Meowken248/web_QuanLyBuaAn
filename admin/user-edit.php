<?php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';

$conn = (new Database())->getConnection();
$userModel = new UserModel();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$edit_user = null;
if ($id) {
    $edit_user = $userModel->getUserById($id);
    if (!$edit_user) {
        set_flash_message('danger', 'Không tìm thấy người dùng.');
        redirect('/admin/users.php');
    }
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $status = $_POST['status'] === 'locked' ? 'locked' : 'active';
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ.';
    } elseif ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vui lòng nhập Họ Tên và Email hợp lệ.';
    } elseif (!$id && empty($password)) {
        $error = 'Vui lòng nhập mật khẩu cho người dùng mới.';
    } else {
        // Kiểm tra email trùng
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $check_stmt->execute([':email' => $email, ':id' => $id]);
        if ($check_stmt->fetch()) {
            $error = 'Email này đã được sử dụng bởi người dùng khác.';
        } else {
            if ($id) {
                // Update
                if (!empty($password)) {
                    $sql = 'UPDATE users SET full_name=:full_name, email=:email, role=:role, status=:status, password=:password WHERE id=:id';
                    $params = [
                        ':full_name' => $full_name, ':email' => $email, ':role' => $role, ':status' => $status, 
                        ':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id
                    ];
                } else {
                    $sql = 'UPDATE users SET full_name=:full_name, email=:email, role=:role, status=:status WHERE id=:id';
                    $params = [
                        ':full_name' => $full_name, ':email' => $email, ':role' => $role, ':status' => $status, ':id' => $id
                    ];
                }
            } else {
                // Insert
                $sql = 'INSERT INTO users (full_name, email, password, role, status) VALUES (:full_name, :email, :password, :role, :status)';
                $params = [
                    ':full_name' => $full_name, ':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':role' => $role, ':status' => $status
                ];
            }
            $conn->prepare($sql)->execute($params);
            set_flash_message('success', $id ? 'Đã cập nhật thông tin người dùng.' : 'Đã thêm người dùng mới.');
            redirect('/admin/users.php');
        }
    }
}
$page_title = $id ? 'Sửa Người Dùng' : 'Thêm Người Dùng';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="row justify-content-center mt-3">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h3 class="fw-bold mb-4"><?php echo htmlspecialchars($page_title); ?></h3>
                            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Họ Tên <span class="text-danger">*</span></label>
                                    <input class="form-control" name="full_name" value="<?php echo old('full_name', $edit_user['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" value="<?php echo old('email', $edit_user['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu <?php echo $id ? '<span class="text-muted small">(Để trống nếu không muốn đổi)</span>' : '<span class="text-danger">*</span>'; ?></label>
                                    <input type="password" class="form-control" name="password" <?php echo $id ? '' : 'required'; ?> minlength="6">
                                </div>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Vai trò</label>
                                        <select class="form-select" name="role">
                                            <option value="user" <?php echo ($_POST['role'] ?? $edit_user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>Người dùng (User)</option>
                                            <option value="admin" <?php echo ($_POST['role'] ?? $edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Quản trị viên (Admin)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Trạng thái</label>
                                        <select class="form-select" name="status">
                                            <option value="active" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                            <option value="locked" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? '') === 'locked' ? 'selected' : ''; ?>>Đã khóa</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/admin/users.php">Hủy</a>
                                    <button class="btn btn-success"><?php echo $id ? 'Cập nhật' : 'Thêm mới'; ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
