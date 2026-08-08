<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$plain_token = $_SESSION['password_reset_token'] ?? '';
if ($plain_token === '') {
    set_flash_message('warning', 'Yêu cầu đặt lại mật khẩu không tồn tại hoặc đã hết hạn.');
    redirect('/auth/forgot-password.php');
}
$conn = (new Database())->getConnection();
$token_hash = hash('sha256', $plain_token);
$stmt = $conn->prepare("SELECT id, email FROM password_resets WHERE token = :token AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
$stmt->execute([':token' => $token_hash]);
$reset = $stmt->fetch();
if (!$reset) {
    unset($_SESSION['password_reset_token']);
    set_flash_message('warning', 'Yêu cầu đặt lại mật khẩu không tồn tại hoặc đã hết hạn.');
    redirect('/auth/forgot-password.php');
}

$error = '';
$field_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ.';
    } else {
        if (empty($password)) {
            $field_errors['password'] = 'Vui lòng nhập mật khẩu.';
        } elseif (strlen($password) < 8) {
            $field_errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';
        }
        
        if ($password !== $password_confirm) {
            $field_errors['password_confirm'] = 'Mật khẩu xác nhận không khớp.';
        }

        if (empty($field_errors)) {
            $conn->beginTransaction();
            try {
                $conn->prepare("UPDATE users SET password = :password WHERE email = :email")->execute([':password' => password_hash($password, PASSWORD_DEFAULT), ':email' => $reset['email']]);
                $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id")->execute([':id' => $reset['id']]);
                $conn->commit();
                unset($_SESSION['password_reset_token']);
                set_flash_message('success', 'Mật khẩu đã được cập nhật. Bạn có thể đăng nhập.');
                redirect('/auth/login.php');
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = 'Không thể cập nhật mật khẩu. Vui lòng thử lại.';
            }
        } else {
            $error = 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.';
        }
    }
}
$page_title = 'Đặt lại mật khẩu';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="card shadow"><div class="card-body p-5">
<h2 class="text-center fw-bold mb-4">Đặt mật khẩu mới</h2>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="mb-3">
    <label class="form-label">Mật khẩu mới</label>
    <input type="password" name="password" class="form-control <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" minlength="8" required>
    <?php if(isset($field_errors['password'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['password']; ?></div><?php endif; ?>
</div>
<div class="mb-4">
    <label class="form-label">Xác nhận mật khẩu</label>
    <input type="password" name="password_confirm" class="form-control <?php echo isset($field_errors['password_confirm']) ? 'is-invalid' : ''; ?>" minlength="8" required>
    <?php if(isset($field_errors['password_confirm'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['password_confirm']; ?></div><?php endif; ?>
</div>
<button class="btn btn-success w-100" type="submit">Cập nhật mật khẩu</button></form>
</div></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
