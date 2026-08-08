<?php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';

$conn = (new Database())->getConnection();
$userModel = new UserModel();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$edit_user = $id ? $userModel->getUserById($id) : null;
if ($id && !$edit_user) {
    set_flash_message('danger', 'Không tìm thấy người dùng.');
    redirect('/admin/users.php');
}

$error = '';
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'locked'], true) ? $_POST['status'] : 'active';
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }
    if ($full_name === '') $field_errors['full_name'] = 'Vui lòng nhập họ tên.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $field_errors['email'] = 'Vui lòng nhập email hợp lệ.';
    if (!$id && trim($password) === '') $field_errors['password'] = 'Vui lòng nhập mật khẩu.';
    if ($password !== '' && trim($password) === '') $field_errors['password'] = 'Mật khẩu không được chỉ chứa khoảng trắng.';
    if (trim($password) !== '' && strlen($password) < 8) $field_errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    if ($id === (int)$_SESSION['user_id'] && ($role !== 'admin' || $status !== 'active')) {
        $field_errors['role'] = 'Không thể tự hạ quyền hoặc khóa tài khoản quản trị đang đăng nhập.';
    }

    if (!$error && !$field_errors) {
        $check = $conn->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $check->execute([':email' => $email, ':id' => $id]);
        if ($check->fetchColumn()) {
            $field_errors['email'] = 'Email này đã được sử dụng bởi người dùng khác.';
        } else {
            $params = [':full_name' => $full_name, ':email' => $email, ':role' => $role, ':status' => $status];
            if ($id) {
                $params[':id'] = $id;
                if (trim($password) !== '') {
                    $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                    $sql = 'UPDATE users SET full_name=:full_name,email=:email,role=:role,status=:status,password=:password WHERE id=:id';
                } else {
                    $sql = 'UPDATE users SET full_name=:full_name,email=:email,role=:role,status=:status WHERE id=:id';
                }
            } else {
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                $sql = 'INSERT INTO users (full_name,email,password,role,status) VALUES (:full_name,:email,:password,:role,:status)';
            }
            $conn->prepare($sql)->execute($params);
            if ($id === (int)$_SESSION['user_id']) {
                $_SESSION['user_name'] = $full_name;
                $_SESSION['full_name'] = $full_name;
            }
            set_flash_message('success', $id ? 'Đã cập nhật đầy đủ thông tin người dùng.' : 'Đã thêm người dùng mới.');
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
<div class="col-md-2"><?php require __DIR__ . '/includes/sidebar.php'; ?></div>
<div class="col-md-10"><div class="row justify-content-center mt-3"><div class="col-lg-8">
<div class="card shadow-sm border-0"><div class="card-body p-4">
<h3 class="fw-bold mb-4"><?php echo htmlspecialchars($page_title); ?></h3>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($field_errors): ?><div class="alert alert-danger">Vui lòng kiểm tra lại các trường được đánh dấu bên dưới.</div><?php endif; ?>
<form method="POST" novalidate>
<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="mb-3">
<label class="form-label" for="full_name">Họ Tên <span class="text-danger">*</span></label>
<input id="full_name" class="form-control <?php echo isset($field_errors['full_name']) ? 'is-invalid' : ''; ?>" name="full_name" maxlength="150" value="<?php echo old('full_name', $edit_user['full_name'] ?? ''); ?>" required>
<div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['full_name'] ?? 'Vui lòng nhập họ tên.'); ?></div>
</div>
<div class="mb-3">
<label class="form-label" for="email">Email <span class="text-danger">*</span></label>
<input id="email" type="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" name="email" maxlength="190" value="<?php echo old('email', $edit_user['email'] ?? ''); ?>" required>
<div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['email'] ?? 'Vui lòng nhập email hợp lệ.'); ?></div>
</div>
<div class="mb-3">
<label class="form-label" for="password">Mật khẩu <?php echo $id ? '<span class="text-muted small">(Để trống nếu không muốn đổi)</span>' : '<span class="text-danger">*</span>'; ?></label>
<div class="input-group has-validation">
<input id="password" type="password" class="form-control <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" name="password" <?php echo $id ? '' : 'required'; ?> minlength="8" autocomplete="new-password">
<button class="btn btn-outline-secondary password-toggle" type="button" data-target="password" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
<div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['password'] ?? 'Mật khẩu phải có ít nhất 8 ký tự và không chỉ chứa khoảng trắng.'); ?></div>
</div>
</div>
<div class="row g-3 mb-4">
<div class="col-md-6"><label class="form-label">Vai trò</label>
<select class="form-select <?php echo isset($field_errors['role']) ? 'is-invalid' : ''; ?>" name="role">
<option value="user" <?php echo ($_POST['role'] ?? $edit_user['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>Người dùng (User)</option>
<option value="admin" <?php echo ($_POST['role'] ?? $edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Quản trị viên (Admin)</option>
</select><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['role'] ?? ''); ?></div></div>
<div class="col-md-6"><label class="form-label">Trạng thái</label>
<select class="form-select" name="status">
<option value="active" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
<option value="inactive" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
<option value="locked" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? '') === 'locked' ? 'selected' : ''; ?>>Đã khóa</option>
</select></div>
</div>
<div class="d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/admin/users.php">Hủy</a><button class="btn btn-success"><?php echo $id ? 'Cập nhật' : 'Thêm mới'; ?></button></div>
</form>
</div></div></div></div></div></div></div>
<script>
document.querySelectorAll('.password-toggle').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.innerHTML = '<i class="bi ' + (visible ? 'bi-eye' : 'bi-eye-slash') + '"></i>';
        button.setAttribute('aria-label', visible ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
