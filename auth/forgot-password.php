<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) redirect('/user/dashboard.php');
$error = '';
$sent = false;

$field_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ.';
    } else {
        if (empty($email)) {
            $field_errors['email'] = 'Vui lòng nhập email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = 'Email không hợp lệ.';
        }
        
        if (empty($field_errors)) {
            $conn = (new Database())->getConnection();
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND status = 'active' LIMIT 1");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $token = bin2hex(random_bytes(32));
                $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE email = :email AND used_at IS NULL")->execute([':email' => $email]);
                $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")->execute([':email' => $email, ':token' => hash('sha256', $token)]);
                $_SESSION['password_reset_token'] = $token;
                redirect('/auth/reset-password.php');
            }
            $sent = true;
        } else {
            $error = 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.';
        }
    }
}
$page_title = 'Quên mật khẩu';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="card shadow"><div class="card-body p-5">
<h2 class="text-center fw-bold mb-3">Khôi phục mật khẩu</h2>
<p class="text-muted text-center">Nhập email đã đăng ký để tạo yêu cầu đặt lại mật khẩu.</p>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($sent): ?><div class="alert alert-info">Nếu email tồn tại, yêu cầu đặt lại mật khẩu đã được tạo.</div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="mb-4">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo old('email'); ?>" required>
    <?php if(isset($field_errors['email'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['email']; ?></div><?php endif; ?>
</div>
<button class="btn btn-success w-100" type="submit">Tiếp tục</button></form>
<div class="text-center mt-3"><a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-success">Quay lại đăng nhập</a></div>
</div></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
