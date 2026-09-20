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
$field_errors = [];

$remembered_email = $_COOKIE['remember_email'] ?? '';
$is_remembered = !empty($remembered_email);
$input_email = $_POST['email'] ?? $remembered_email;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if (empty($email)) $field_errors['email'] = 'Vui lòng nhập email.';
        if (empty($password)) $field_errors['password'] = 'Vui lòng nhập mật khẩu.';
        
        if (empty($field_errors)) {
            $userModel = new UserModel();
            $result = $userModel->login($email, $password);
            
            if ($result['status']) {
                if ($remember) {
                    setcookie('remember_email', $email, time() + (86400 * 30), '/', '', false, true);
                } else {
                    setcookie('remember_email', '', time() - 3600, '/');
                }
                
                set_flash_message('success', 'Đăng nhập thành công!');
                if ($_SESSION['user_role'] === 'admin') {
                    redirect('/admin/index.php');
                } else {
                    redirect('/user/dashboard.php');
                }
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.';
        }
    }
}

$page_title = 'Đăng nhập';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center bg-light" style="background: url('<?php echo BASE_URL; ?>/img/bg1.jpg') no-repeat center center fixed; background-size: cover; position: relative;">
    <!-- Overlay -->
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(4, 120, 87, 0.85) 0%, rgba(52, 211, 153, 0.75) 100%); z-index: 0;"></div>
    
    <div class="container position-relative z-index-1 py-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8 col-xl-6">
                <div class="card glass-card border-0 overflow-hidden" data-aos="zoom-in" data-aos-duration="1000">
                    <div class="row g-0">
                        <!-- Left side with welcome message (Hidden on small screens) -->
                        <div class="col-md-5 bg-health text-white d-none d-md-flex flex-column justify-content-center p-5 position-relative overflow-hidden">
                            <div class="position-absolute" style="top: -50px; left: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(20px);"></div>
                            <div class="position-relative z-index-1">
                                <h3 class="fw-bold mb-3">Chào mừng trở lại!</h3>
                                <p class="small opacity-75">Hãy đăng nhập để tiếp tục theo dõi tiến trình sức khỏe và nhận lời khuyên từ Trợ lý AI của chúng tôi.</p>
                            </div>
                        </div>
                        
                        <!-- Right side with form -->
                        <div class="col-md-7 p-4 p-md-5 bg-white bg-opacity-75">
                            <h2 class="text-center fw-bold mb-4 text-dark">Đăng nhập</h2>
                            
                            <?php display_flash_message(); ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger shadow-sm border-0 rounded-3 text-sm" data-aos="fade-in"><i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="loginForm" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <div class="form-floating">
                                        <input type="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" id="floatingInput" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($input_email); ?>" autocomplete="email" required>
                                        <label for="floatingInput" class="text-muted"><i class="bi bi-envelope me-2"></i>Email</label>
                                        <div class="invalid-feedback" id="loginEmailError"><?php echo $field_errors['email'] ?? 'Vui lòng nhập địa chỉ email hợp lệ.'; ?></div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-floating position-relative">
                                        <input type="password" class="form-control pe-5 <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" id="floatingPassword" name="password" placeholder="Password" autocomplete="current-password" required>
                                        <label for="floatingPassword" class="text-muted"><i class="bi bi-lock me-2"></i>Mật khẩu</label>
                                        <button type="button" class="btn btn-link text-secondary position-absolute top-50 end-0 translate-middle-y me-2 p-2 password-toggle" data-password-toggle="floatingPassword" aria-label="Hiện mật khẩu" aria-pressed="false">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                        <div class="invalid-feedback" id="loginPasswordError"><?php echo $field_errors['password'] ?? 'Vui lòng nhập mật khẩu.'; ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="remember" name="remember" <?php echo ($is_remembered || isset($_POST['remember'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-muted small" for="remember">Ghi nhớ đăng nhập</label>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/auth/forgot-password.php" class="text-success text-decoration-none small fw-bold">Quên mật khẩu?</a>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-glow w-100 mb-3 py-2 fw-bold text-uppercase rounded-pill shadow-sm">Đăng nhập</button>
                                
                                <div class="text-center mt-4">
                                    <span class="text-muted small">Chưa có tài khoản?</span> <a href="<?php echo BASE_URL; ?>/auth/register.php" class="text-success text-decoration-none fw-bold">Đăng ký ngay</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const emailInput = document.getElementById('floatingInput');
    const passInput = document.getElementById('floatingPassword');
    const emailError = document.getElementById('loginEmailError');
    const passError = document.getElementById('loginPasswordError');

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    function validateEmail() {
        const val = emailInput.value.trim();
        if (!val) {
            emailInput.classList.add('is-invalid');
            emailInput.classList.remove('is-valid');
            if (emailError) emailError.textContent = 'Vui lòng nhập địa chỉ email.';
            return false;
        }
        const hasNonAscii = /[^\x00-\x7F]/.test(val);
        const hasWhitespace = /\s/.test(val);
        if (hasNonAscii || hasWhitespace || !emailRegex.test(val)) {
            emailInput.classList.add('is-invalid');
            emailInput.classList.remove('is-valid');
            if (emailError) emailError.textContent = 'Email không hợp lệ (không chứa dấu tiếng Việt hoặc khoảng trắng).';
            return false;
        }
        emailInput.classList.remove('is-invalid');
        emailInput.classList.add('is-valid');
        return true;
    }

    function validatePass() {
        const val = passInput.value;
        if (!val) {
            passInput.classList.add('is-invalid');
            passInput.classList.remove('is-valid');
            if (passError) passError.textContent = 'Vui lòng nhập mật khẩu.';
            return false;
        }
        passInput.classList.remove('is-invalid');
        passInput.classList.remove('is-valid');
        return true;
    }

    emailInput.addEventListener('input', validateEmail);
    emailInput.addEventListener('blur', validateEmail);

    passInput.addEventListener('input', validatePass);
    passInput.addEventListener('blur', validatePass);

    form.addEventListener('submit', function(e) {
        const isEmailOk = validateEmail();
        const isPassOk = validatePass();

        if (!isEmailOk || !isPassOk) {
            e.preventDefault();
            e.stopPropagation();

            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
