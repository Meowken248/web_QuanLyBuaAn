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

$current_user_email = $_SESSION['user_email'] ?? '';
if (empty($current_user_email) && isset($_SESSION['user_id'])) {
    $stmtMe = $conn->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
    $stmtMe->execute([':id' => $_SESSION['user_id']]);
    $current_user_email = (string)$stmtMe->fetchColumn();
    $_SESSION['user_email'] = $current_user_email;
}
$is_root_admin = (strtolower(trim($current_user_email)) === strtolower(ROOT_ADMIN_EMAIL));

$is_target_root = $id && (strtolower($edit_user['email']) === strtolower(ROOT_ADMIN_EMAIL));
$is_target_peer_admin = $id && ($edit_user['role'] === 'admin') && ($id !== (int)$_SESSION['user_id']) && !$is_root_admin;

// Bảo vệ tài khoản Root: Quản trị viên thường không được phép vào chỉnh sửa tài khoản Root
if ($is_target_root && !$is_root_admin) {
    set_flash_message('danger', 'Chỉ tài khoản Root Admin (' . ROOT_ADMIN_EMAIL . ') mới có quyền chỉnh sửa tài khoản Root.');
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

    // Nếu sửa tài khoản Root Admin:
    if ($is_target_root) {
        $email = strtolower(ROOT_ADMIN_EMAIL);
        $role = 'admin';
        $status = 'active';
    }

    // Nếu sửa tài khoản Quản trị viên cùng cấp:
    if ($is_target_peer_admin) {
        $role = 'admin'; // Không cho phép hạ quyền
        $status = $edit_user['status']; // Không cho phép đổi trạng thái
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }
    if ($full_name === '') $field_errors['full_name'] = 'Vui lòng nhập họ tên.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $field_errors['email'] = 'Vui lòng nhập email hợp lệ.';

    // Không cho phép đặt trùng email của Root cho tài khoản khác
    if (!$is_target_root && $email === strtolower(ROOT_ADMIN_EMAIL)) {
        $field_errors['email'] = 'Email này được bảo lưu cho tài khoản Root Admin (' . ROOT_ADMIN_EMAIL . ').';
    }

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
                $_SESSION['user_email'] = $email;
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
<div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4"><div class="card-body p-4">
<h3 class="fw-bold mb-4"><?php echo htmlspecialchars($page_title); ?></h3>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($field_errors): ?><div class="alert alert-danger">Vui lòng kiểm tra lại các trường được đánh dấu bên dưới.</div><?php endif; ?>
<form method="POST" novalidate id="userEditForm">
<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="mb-3">
<label class="form-label fw-bold" for="full_name">Họ Tên <span class="text-danger">*</span></label>
<input id="full_name" class="form-control <?php echo isset($field_errors['full_name']) ? 'is-invalid' : ''; ?>" name="full_name" maxlength="150" value="<?php echo old('full_name', $edit_user['full_name'] ?? ''); ?>" required autocomplete="off">
<div class="invalid-feedback" id="full_name_error"><?php echo htmlspecialchars($field_errors['full_name'] ?? 'Vui lòng nhập họ tên.'); ?></div>
</div>
<div class="mb-3">
<label class="form-label fw-bold" for="email">Email <span class="text-danger">*</span></label>
<?php if ($is_target_root): ?>
    <input id="email" type="email" class="form-control" value="<?php echo htmlspecialchars(ROOT_ADMIN_EMAIL); ?>" readonly>
    <input type="hidden" name="email" value="<?php echo htmlspecialchars(ROOT_ADMIN_EMAIL); ?>">
    <small class="text-muted"><i class="bi bi-shield-check me-1"></i>Email tài khoản Root Admin được bảo vệ và cố định.</small>
<?php else: ?>
    <input id="email" type="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" name="email" maxlength="190" value="<?php echo old('email', $edit_user['email'] ?? ''); ?>" required autocomplete="off">
    <div class="invalid-feedback" id="email_error"><?php echo htmlspecialchars($field_errors['email'] ?? 'Vui lòng nhập email hợp lệ.'); ?></div>
<?php endif; ?>
</div>
<div class="mb-3">
<label class="form-label fw-bold" for="password">Mật khẩu <?php echo $id ? '<span class="text-muted small fw-normal">(Để trống nếu không muốn đổi)</span>' : '<span class="text-danger">*</span>'; ?></label>
<div class="input-group has-validation">
<input id="password" type="password" class="form-control <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" name="password" <?php echo $id ? '' : 'required'; ?> minlength="8" autocomplete="new-password">
<button class="btn btn-outline-secondary password-toggle" type="button" data-target="password" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
<div class="invalid-feedback" id="password_error"><?php echo htmlspecialchars($field_errors['password'] ?? 'Mật khẩu phải có ít nhất 8 ký tự và không chỉ chứa khoảng trắng.'); ?></div>
</div>
</div>
<div class="row g-3 mb-4">
<div class="col-md-6"><label class="form-label fw-bold">Vai trò</label>
<?php if ($is_target_root): ?>
    <input type="text" class="form-control bg-light" value="Root Admin (Toàn quyền hệ thống)" readonly>
    <input type="hidden" name="role" value="admin">
<?php elseif ($is_target_peer_admin): ?>
    <input type="text" class="form-control bg-light" value="Quản trị viên (Admin - Được bảo vệ)" readonly>
    <input type="hidden" name="role" value="admin">
    <small class="text-muted">Chỉ tài khoản Root mới có quyền thay đổi vai trò Admin này.</small>
<?php else: ?>
    <select class="form-select <?php echo isset($field_errors['role']) ? 'is-invalid' : ''; ?>" name="role">
    <option value="user" <?php echo ($_POST['role'] ?? $edit_user['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>Người dùng (User)</option>
    <option value="admin" <?php echo ($_POST['role'] ?? $edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Quản trị viên (Admin)</option>
    </select><div class="invalid-feedback" id="role_error"><?php echo htmlspecialchars($field_errors['role'] ?? ''); ?></div>
<?php endif; ?>
</div>
<div class="col-md-6"><label class="form-label fw-bold">Trạng thái</label>
<?php if ($is_target_root): ?>
    <input type="text" class="form-control bg-light" value="Hoạt động" readonly>
    <input type="hidden" name="status" value="active">
<?php elseif ($is_target_peer_admin): ?>
    <input type="text" class="form-control bg-light" value="<?php echo ($edit_user['status'] ?? 'active') === 'active' ? 'Hoạt động' : 'Đã khóa'; ?>" readonly>
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($edit_user['status'] ?? 'active'); ?>">
    <small class="text-muted">Chỉ tài khoản Root mới có quyền khóa/mở khóa Admin này.</small>
<?php else: ?>
    <select class="form-select" name="status">
    <option value="active" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
    <option value="inactive" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
    <option value="locked" <?php echo ($_POST['status'] ?? $edit_user['status'] ?? '') === 'locked' ? 'selected' : ''; ?>>Đã khóa</option>
    </select>
<?php endif; ?>
</div>
</div>
<div class="d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary rounded-pill" href="<?php echo BASE_URL; ?>/admin/users.php">Hủy</a><button class="btn btn-outline-primary rounded-pill px-4 shadow-sm"><?php echo $id ? 'Cập nhật' : 'Thêm mới'; ?></button></div>
</form>
</div></div></div></div></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.target);
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.innerHTML = '<i class="bi ' + (visible ? 'bi-eye' : 'bi-eye-slash') + '"></i>';
            button.setAttribute('aria-label', visible ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
        });
    });

    const isEdit = <?php echo $id ? 'true' : 'false'; ?>;
    const form = document.getElementById('userEditForm');
    const fullNameInput = document.getElementById('full_name');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    function validateFullName(trigger = 'blur') {
        if (!fullNameInput) return true;
        const val = fullNameInput.value.trim();
        const err = document.getElementById('full_name_error');
        if (!val) {
            if (trigger === 'submit' || trigger === 'blur') {
                fullNameInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập họ tên.';
                return false;
            }
            return true;
        }
        if (val.length < 2) {
            fullNameInput.classList.add('is-invalid');
            if (err) err.textContent = 'Họ và tên phải có ít nhất 2 ký tự.';
            return false;
        }
        fullNameInput.classList.remove('is-invalid');
        return true;
    }

    function validateEmail(trigger = 'blur') {
        if (!emailInput) return true;
        const val = emailInput.value.trim();
        const err = document.getElementById('email_error');
        if (!val) {
            if (trigger === 'submit' || trigger === 'blur') {
                emailInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập email hợp lệ.';
                return false;
            }
            return true;
        }
        if (/[^\x00-\x7F]/.test(val) || /\s/.test(val)) {
            emailInput.classList.add('is-invalid');
            if (err) err.textContent = 'Email không được chứa khoảng trắng hoặc dấu tiếng Việt (VD: nguoidung@gmail.com).';
            return false;
        }
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
        if (!emailRegex.test(val)) {
            if (trigger === 'submit' || trigger === 'blur' || val.includes('@')) {
                emailInput.classList.add('is-invalid');
                if (err) err.textContent = 'Định dạng email không hợp lệ (VD: nguoidung@gmail.com).';
                return false;
            }
            return true;
        }
        emailInput.classList.remove('is-invalid');
        return true;
    }

    function validatePassword(trigger = 'blur') {
        if (!passwordInput) return true;
        const val = passwordInput.value;
        const err = document.getElementById('password_error');
        if (!val) {
            if (!isEdit && (trigger === 'submit' || trigger === 'blur')) {
                passwordInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập mật khẩu.';
                return false;
            }
            passwordInput.classList.remove('is-invalid');
            return true;
        }
        if (val.trim() === '') {
            passwordInput.classList.add('is-invalid');
            if (err) err.textContent = 'Mật khẩu không được chỉ chứa khoảng trắng.';
            return false;
        }
        if (val.length < 8) {
            if (trigger === 'submit' || trigger === 'blur' || passwordInput.classList.contains('is-invalid')) {
                passwordInput.classList.add('is-invalid');
                if (err) err.textContent = 'Mật khẩu phải có ít nhất 8 ký tự.';
                return false;
            }
            return true;
        }
        passwordInput.classList.remove('is-invalid');
        return true;
    }

    // Real-time listeners
    if (fullNameInput) {
        fullNameInput.addEventListener('input', () => validateFullName('input'));
        fullNameInput.addEventListener('blur', () => validateFullName('blur'));
    }
    if (emailInput) {
        emailInput.addEventListener('input', () => validateEmail('input'));
        emailInput.addEventListener('blur', () => validateEmail('blur'));
    }
    if (passwordInput) {
        passwordInput.addEventListener('input', () => validatePassword('input'));
        passwordInput.addEventListener('blur', () => validatePassword('blur'));
    }

    // Submit handler - validates ALL fields simultaneously
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const isFullNameValid = validateFullName('submit');
            const isEmailValid = validateEmail('submit');
            const isPasswordValid = validatePassword('submit');

            if (isFullNameValid && isEmailValid && isPasswordValid) {
                form.submit();
            } else {
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.focus();
            }
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
