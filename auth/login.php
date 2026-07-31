<?php
// auth/login.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/UserModel.php';

// Nếu đã đăng nhập thì chuyển hướng
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        redirect('/admin/index.php');
    } else {
        redirect('/user/dashboard.php');
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } else {
        $userModel = new UserModel();
        $result = $userModel->login($email, $password);
        
        if ($result['status']) {
            set_flash_message('success', 'Đăng nhập thành công!');
            if ($_SESSION['user_role'] === 'admin') {
                redirect('/admin/index.php');
            } else {
                redirect('/user/dashboard.php');
            }
        } else {
            $error = $result['message'];
        }
    }
}

$page_title = 'Đăng nhập';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h2 class="text-center fw-bold mb-4">Đăng nhập</h2>
                    
                    <?php display_flash_message(); ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Ghi nhớ</label>
                            </div>
                            <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php" class="text-success text-decoration-none">Quên mật khẩu?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 mb-3">Đăng nhập</button>
                        
                        <div class="text-center">
                            Chưa có tài khoản? <a href="<?php echo BASE_URL; ?>/auth/register.php" class="text-success text-decoration-none">Đăng ký ngay</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
