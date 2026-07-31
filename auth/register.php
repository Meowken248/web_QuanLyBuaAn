<?php
// auth/register.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/UserModel.php';

// Nếu đã đăng nhập thì chuyển hướng
if (isset($_SESSION['user_id'])) {
    redirect('/user/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $terms = isset($_POST['terms']) ? true : false;
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } elseif (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ($password !== $password_confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!$terms) {
        $error = 'Bạn phải đồng ý với điều khoản sử dụng.';
    } else {
        $userModel = new UserModel();
        if ($userModel->emailExists($email)) {
            $error = 'Email này đã được đăng ký.';
        } else {
            $user_id = $userModel->register($full_name, $email, $password);
            if ($user_id) {
                set_flash_message('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
                redirect('/auth/login.php');
            } else {
                $error = 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
            }
        }
    }
}

$page_title = 'Đăng ký tài khoản';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h2 class="text-center fw-bold mb-4">Đăng ký</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Họ và tên</label>
                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                            <div class="form-text">Tối thiểu 8 ký tự.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nhập lại mật khẩu</label>
                            <input type="password" class="form-control" name="password_confirm" required minlength="8">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">Tôi đồng ý với điều khoản sử dụng</label>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 mb-3">Tạo tài khoản</button>
                        
                        <div class="text-center">
                            Đã có tài khoản? <a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-success text-decoration-none">Đăng nhập ngay</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
