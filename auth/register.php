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

<div class="min-vh-100 d-flex align-items-center bg-light" style="background: url('<?php echo BASE_URL; ?>/img/bg1.jpg') no-repeat center center fixed; background-size: cover; position: relative;">
    <!-- Overlay -->
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(4, 120, 87, 0.85) 0%, rgba(52, 211, 153, 0.75) 100%); z-index: 0;"></div>
    
    <div class="container position-relative z-index-1 py-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8 col-xl-7">
                <div class="card glass-card border-0 overflow-hidden" data-aos="zoom-in" data-aos-duration="1000">
                    <div class="row g-0">
                        <!-- Left side with welcome message (Hidden on small screens) -->
                        <div class="col-md-5 bg-health text-white d-none d-md-flex flex-column justify-content-center p-5 position-relative overflow-hidden">
                            <div class="position-absolute" style="top: -50px; left: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(20px);"></div>
                            <div class="position-absolute" style="bottom: -50px; right: -50px; width: 100px; height: 100px; background: rgba(255,255,255,0.15); border-radius: 50%; filter: blur(15px);"></div>
                            <div class="position-relative z-index-1">
                                <h3 class="fw-bold mb-3">Tham gia cộng đồng!</h3>
                                <p class="small opacity-75">Bắt đầu hành trình chăm sóc sức khỏe của bạn ngay hôm nay cùng hệ thống quản lý dinh dưỡng chuyên nghiệp.</p>
                                <ul class="list-unstyled mt-4 small opacity-75">
                                    <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-warning"></i>Ghi chép bữa ăn dễ dàng</li>
                                    <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-warning"></i>Tự động tính Calories</li>
                                    <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-warning"></i>Hỗ trợ từ Trợ lý AI</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Right side with form -->
                        <div class="col-md-7 p-4 p-md-5 bg-white bg-opacity-75">
                            <h2 class="text-center fw-bold mb-4 text-dark">Tạo tài khoản</h2>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger shadow-sm border-0 rounded-3 text-sm" data-aos="fade-in"><i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="floatingName" name="full_name" placeholder="Họ và tên" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                    <label for="floatingName" class="text-muted"><i class="bi bi-person me-2"></i>Họ và tên</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="floatingEmail" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    <label for="floatingEmail" class="text-muted"><i class="bi bi-envelope me-2"></i>Email</label>
                                </div>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <div class="form-floating position-relative">
                                            <input type="password" class="form-control pe-5" id="floatingPassword" name="password" placeholder="Mật khẩu" required minlength="8">
                                            <label for="floatingPassword" class="text-muted"><i class="bi bi-lock me-2"></i>Mật khẩu</label>
                                            <button type="button" class="btn btn-link text-secondary position-absolute top-50 end-0 translate-middle-y me-2 p-2 password-toggle" data-password-toggle="floatingPassword" aria-label="Hiện mật khẩu" aria-pressed="false">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-floating position-relative">
                                            <input type="password" class="form-control pe-5" id="floatingPasswordConfirm" name="password_confirm" placeholder="Nhập lại" required minlength="8">
                                            <label for="floatingPasswordConfirm" class="text-muted"><i class="bi bi-check-circle me-2"></i>Nhập lại mật khẩu</label>
                                            <button type="button" class="btn btn-link text-secondary position-absolute top-50 end-0 translate-middle-y me-2 p-2 password-toggle" data-password-toggle="floatingPasswordConfirm" aria-label="Hiện mật khẩu xác nhận" aria-pressed="false">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4 form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                    <label class="form-check-label text-muted small" for="terms">Tôi đồng ý với <a href="#" class="text-success text-decoration-none fw-bold">điều khoản sử dụng</a></label>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-glow w-100 mb-3 py-2 fw-bold text-uppercase rounded-pill shadow-sm">Đăng ký ngay</button>
                                
                                <div class="text-center mt-4">
                                    <span class="text-muted small">Đã có tài khoản?</span> <a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-success text-decoration-none fw-bold">Đăng nhập ngay</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
