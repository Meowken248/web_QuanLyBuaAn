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
    
    $field_errors = [];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if (empty($full_name)) $field_errors['full_name'] = 'Vui lòng nhập họ và tên.';
        if (empty($email)) {
            $field_errors['email'] = 'Vui lòng nhập email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = 'Email không hợp lệ.';
        }
        
        if (empty($password)) {
            $field_errors['password'] = 'Vui lòng nhập mật khẩu.';
        } elseif (strlen($password) < 8) {
            $field_errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';
        }
        
        if ($password !== $password_confirm) {
            $field_errors['password_confirm'] = 'Mật khẩu xác nhận không khớp.';
        }
        
        if (!$terms) {
            $field_errors['terms'] = 'Bạn phải đồng ý với điều khoản sử dụng.';
        }

        if (empty($field_errors)) {
            $userModel = new UserModel();
            if ($userModel->emailExists($email)) {
                $field_errors['email'] = 'Email này đã được đăng ký.';
                $error = 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.';
            } else {
                $user_id = $userModel->register($full_name, $email, $password);
                if ($user_id) {
                    set_flash_message('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
                    redirect('/auth/login.php');
                } else {
                    $error = 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
                }
            }
        } else {
            $error = 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.';
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
                            
                            <form method="POST" action="" id="registerForm" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <div class="form-floating">
                                        <input type="text" class="form-control <?php echo isset($field_errors['full_name']) ? 'is-invalid' : ''; ?>" id="floatingName" name="full_name" placeholder="Họ và tên" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                        <label for="floatingName" class="text-muted"><i class="bi bi-person me-2"></i>Họ và tên</label>
                                        <div class="invalid-feedback" id="nameError"><?php echo $field_errors['full_name'] ?? 'Vui lòng nhập họ và tên (tối thiểu 2 ký tự).'; ?></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-floating">
                                        <input type="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" id="floatingEmail" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        <label for="floatingEmail" class="text-muted"><i class="bi bi-envelope me-2"></i>Email</label>
                                        <div class="invalid-feedback" id="emailError"><?php echo $field_errors['email'] ?? 'Email không hợp lệ (không chứa dấu tiếng Việt hoặc khoảng trắng).'; ?></div>
                                    </div>
                                </div>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <div class="form-floating position-relative">
                                            <input type="password" class="form-control pe-5 <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" id="floatingPassword" name="password" placeholder="Mật khẩu" required minlength="8">
                                            <label for="floatingPassword" class="text-muted"><i class="bi bi-lock me-2"></i>Mật khẩu</label>
                                            <button type="button" class="btn btn-link text-secondary position-absolute top-50 end-0 translate-middle-y me-2 p-2 password-toggle" data-password-toggle="floatingPassword" aria-label="Hiện mật khẩu" aria-pressed="false">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>
                                            <div class="invalid-feedback" id="passwordError"><?php echo $field_errors['password'] ?? 'Mật khẩu phải có ít nhất 8 ký tự.'; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-floating position-relative">
                                            <input type="password" class="form-control pe-5 <?php echo isset($field_errors['password_confirm']) ? 'is-invalid' : ''; ?>" id="floatingPasswordConfirm" name="password_confirm" placeholder="Nhập lại" required minlength="8">
                                            <label for="floatingPasswordConfirm" class="text-muted"><i class="bi bi-check-circle me-2"></i>Nhập lại mật khẩu</label>
                                            <button type="button" class="btn btn-link text-secondary position-absolute top-50 end-0 translate-middle-y me-2 p-2 password-toggle" data-password-toggle="floatingPasswordConfirm" aria-label="Hiện mật khẩu xác nhận" aria-pressed="false">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>
                                            <div class="invalid-feedback" id="passwordConfirmError"><?php echo $field_errors['password_confirm'] ?? 'Mật khẩu xác nhận không khớp.'; ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input <?php echo isset($field_errors['terms']) ? 'is-invalid' : ''; ?>" id="terms" name="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?> required>
                                        <label class="form-check-label text-muted small" for="terms">Tôi đồng ý với <a href="#" class="text-success text-decoration-none fw-bold">điều khoản sử dụng</a></label>
                                        <div class="invalid-feedback" id="termsError"><?php echo $field_errors['terms'] ?? 'Bạn phải đồng ý với điều khoản sử dụng.'; ?></div>
                                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    if (!form) return;

    const nameInput = document.getElementById('floatingName');
    const emailInput = document.getElementById('floatingEmail');
    const passInput = document.getElementById('floatingPassword');
    const passConfirmInput = document.getElementById('floatingPasswordConfirm');
    const termsInput = document.getElementById('terms');

    const nameError = document.getElementById('nameError');
    const emailError = document.getElementById('emailError');
    const passError = document.getElementById('passwordError');
    const passConfirmError = document.getElementById('passwordConfirmError');
    const termsError = document.getElementById('termsError');

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    function validateName() {
        const val = nameInput.value.trim();
        if (!val) {
            nameInput.classList.add('is-invalid');
            nameInput.classList.remove('is-valid');
            if (nameError) nameError.textContent = 'Vui lòng nhập họ và tên.';
            return false;
        }
        if (val.length < 2) {
            nameInput.classList.add('is-invalid');
            nameInput.classList.remove('is-valid');
            if (nameError) nameError.textContent = 'Họ và tên phải có ít nhất 2 ký tự.';
            return false;
        }
        nameInput.classList.remove('is-invalid');
        nameInput.classList.add('is-valid');
        return true;
    }

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
        if (val.length < 8) {
            passInput.classList.add('is-invalid');
            passInput.classList.remove('is-valid');
            if (passError) passError.textContent = 'Mật khẩu phải có ít nhất 8 ký tự.';
            return false;
        }
        passInput.classList.remove('is-invalid');
        passInput.classList.add('is-valid');
        return true;
    }

    function validatePassConfirm() {
        const passVal = passInput.value;
        const confirmVal = passConfirmInput.value;
        if (!confirmVal) {
            passConfirmInput.classList.add('is-invalid');
            passConfirmInput.classList.remove('is-valid');
            if (passConfirmError) passConfirmError.textContent = 'Vui lòng nhập lại mật khẩu.';
            return false;
        }
        if (confirmVal !== passVal) {
            passConfirmInput.classList.add('is-invalid');
            passConfirmInput.classList.remove('is-valid');
            if (passConfirmError) passConfirmError.textContent = 'Mật khẩu xác nhận không khớp.';
            return false;
        }
        passConfirmInput.classList.remove('is-invalid');
        passConfirmInput.classList.add('is-valid');
        return true;
    }

    function validateTerms() {
        if (!termsInput.checked) {
            termsInput.classList.add('is-invalid');
            termsInput.classList.remove('is-valid');
            if (termsError) termsError.textContent = 'Bạn phải đồng ý với điều khoản sử dụng.';
            return false;
        }
        termsInput.classList.remove('is-invalid');
        termsInput.classList.add('is-valid');
        return true;
    }

    // Real-time listeners
    nameInput.addEventListener('input', validateName);
    nameInput.addEventListener('blur', validateName);

    emailInput.addEventListener('input', validateEmail);
    emailInput.addEventListener('blur', validateEmail);

    passInput.addEventListener('input', function() {
        validatePass();
        if (passConfirmInput.value) {
            validatePassConfirm();
        }
    });
    passInput.addEventListener('blur', validatePass);

    passConfirmInput.addEventListener('input', validatePassConfirm);
    passConfirmInput.addEventListener('blur', validatePassConfirm);

    termsInput.addEventListener('change', validateTerms);

    // Simultaneous validation on submit
    form.addEventListener('submit', function(e) {
        const isNameOk = validateName();
        const isEmailOk = validateEmail();
        const isPassOk = validatePass();
        const isPassConfirmOk = validatePassConfirm();
        const isTermsOk = validateTerms();

        if (!isNameOk || !isEmailOk || !isPassOk || !isPassConfirmOk || !isTermsOk) {
            e.preventDefault();
            e.stopPropagation();

            // Focus the first invalid element
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
