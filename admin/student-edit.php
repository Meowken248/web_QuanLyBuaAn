<?php
// admin/student-edit.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$conn = (new Database())->getConnection();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if (!$id) {
    set_flash_message('danger', 'Không tìm thấy sinh viên.');
    redirect('/admin/classes.php');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'user' AND class_id IS NOT NULL");
$stmt->execute([':id' => $id]);
$edit_student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$edit_student) {
    set_flash_message('danger', 'Không tìm thấy sinh viên.');
    redirect('/admin/classes.php');
}

$class_id = $edit_student['class_id'];
$error = '';
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mssv = trim($_POST['mssv'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }
    
    if ($mssv === '') $field_errors['mssv'] = 'Vui lòng nhập MSSV.';
    if ($full_name === '') $field_errors['full_name'] = 'Vui lòng nhập họ tên.';
    
    if ($birth_date === '') {
        $field_errors['birth_date'] = 'Vui lòng nhập ngày sinh.';
    } else {
        try {
            $dobDate = new DateTime($birth_date);
            $today = new DateTime();
            if ($dobDate > $today) {
                $field_errors['birth_date'] = "Sinh viên phải từ 18 tuổi trở lên.";
            } else {
                $age = $today->diff($dobDate)->y;
                if ($age < 18) {
                    $field_errors['birth_date'] = "Sinh viên phải từ 18 tuổi trở lên.";
                } elseif ($age > 100) {
                    $field_errors['birth_date'] = "Ngày sinh không hợp lệ (quá 100 tuổi).";
                }
            }
        } catch (Exception $e) {
            $field_errors['birth_date'] = "Định dạng ngày sinh không hợp lệ.";
        }
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $field_errors['email'] = 'Vui lòng nhập email hợp lệ.';
    if ($password !== '' && trim($password) === '') $field_errors['password'] = 'Mật khẩu không được chỉ chứa khoảng trắng.';
    if (trim($password) !== '' && strlen($password) < 8) $field_errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';

    if (!$error && !$field_errors) {
        // Kiểm tra email và mssv bị trùng
        $check = $conn->prepare('SELECT id, email, mssv FROM users WHERE (email = :email OR mssv = :mssv) AND id <> :id');
        $check->execute([':email' => $email, ':mssv' => $mssv, ':id' => $id]);
        $duplicates = $check->fetchAll(PDO::FETCH_ASSOC);
        
        if ($duplicates) {
            foreach ($duplicates as $dup) {
                if ($dup['email'] === $email) $field_errors['email'] = "Email này đã được sử dụng.";
                if ($dup['mssv'] === $mssv) $field_errors['mssv'] = "MSSV này đã tồn tại.";
            }
        } else {
            $params = [
                ':mssv' => $mssv,
                ':full_name' => $full_name,
                ':birth_date' => $birth_date,
                ':email' => $email,
                ':id' => $id
            ];
            
            if (trim($password) !== '') {
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                $sql = 'UPDATE users SET mssv=:mssv, full_name=:full_name, birth_date=:birth_date, email=:email, password=:password WHERE id=:id';
            } else {
                $sql = 'UPDATE users SET mssv=:mssv, full_name=:full_name, birth_date=:birth_date, email=:email WHERE id=:id';
            }
            
            $conn->prepare($sql)->execute($params);
            
            set_flash_message('success', 'Đã cập nhật thông tin sinh viên.');
            redirect('/admin/class-detail.php?id=' . $class_id);
        }
    }
}

$page_title = 'Sửa Thông Tin Sinh Viên';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2"><?php require __DIR__ . '/includes/sidebar.php'; ?></div>
        <div class="col-md-10">
            <div class="row justify-content-center mt-3">
                <div class="col-lg-8">
                    <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($page_title); ?></h3>
                                <a href="<?php echo BASE_URL; ?>/admin/class-detail.php?id=<?= $class_id ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                                    <i class="bi bi-arrow-left me-1"></i> Quay lại lớp học
                                </a>
                            </div>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <?php if ($field_errors): ?>
                                <div class="alert alert-danger">Vui lòng kiểm tra lại các trường được đánh dấu bên dưới.</div>
                            <?php endif; ?>
                            
                            <form method="POST" novalidate id="editStudentForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="mssv">MSSV <span class="text-danger">*</span></label>
                                    <input id="mssv" class="form-control <?php echo isset($field_errors['mssv']) ? 'is-invalid' : ''; ?>" name="mssv" maxlength="50" value="<?php echo old('mssv', $edit_student['mssv'] ?? ''); ?>" required autocomplete="off">
                                    <div class="invalid-feedback" id="mssv_error"><?php echo htmlspecialchars($field_errors['mssv'] ?? 'Vui lòng nhập MSSV.'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="full_name">Họ Và Tên <span class="text-danger">*</span></label>
                                    <input id="full_name" class="form-control <?php echo isset($field_errors['full_name']) ? 'is-invalid' : ''; ?>" name="full_name" maxlength="150" value="<?php echo old('full_name', $edit_student['full_name'] ?? ''); ?>" required autocomplete="off">
                                    <div class="invalid-feedback" id="full_name_error"><?php echo htmlspecialchars($field_errors['full_name'] ?? 'Vui lòng nhập họ tên.'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="birth_date">Ngày Sinh <span class="text-danger">*</span></label>
                                    <input id="birth_date" type="date" class="form-control <?php echo isset($field_errors['birth_date']) ? 'is-invalid' : ''; ?>" name="birth_date" value="<?php echo old('birth_date', $edit_student['birth_date'] ?? ''); ?>" required>
                                    <div class="invalid-feedback" id="birth_date_error"><?php echo htmlspecialchars($field_errors['birth_date'] ?? 'Vui lòng nhập ngày sinh.'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="email">Email <span class="text-danger">*</span></label>
                                    <input id="email" type="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" name="email" maxlength="190" value="<?php echo old('email', $edit_student['email'] ?? ''); ?>" required autocomplete="off">
                                    <div class="invalid-feedback" id="email_error"><?php echo htmlspecialchars($field_errors['email'] ?? 'Vui lòng nhập email hợp lệ.'); ?></div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold" for="password">Mật khẩu <span class="text-muted small fw-normal">(Để trống nếu không muốn đổi)</span></label>
                                    <div class="input-group has-validation">
                                        <input id="password" type="password" class="form-control <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" name="password" minlength="8" autocomplete="new-password">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="password" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                                        <div class="invalid-feedback" id="password_error"><?php echo htmlspecialchars($field_errors['password'] ?? 'Mật khẩu phải có ít nhất 8 ký tự và không chỉ chứa khoảng trắng.'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <a class="btn btn-outline-secondary rounded-pill" href="<?php echo BASE_URL; ?>/admin/class-detail.php?id=<?= $class_id ?>">Hủy</a>
                                    <button type="submit" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">Cập nhật</button>
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

    const form = document.getElementById('editStudentForm');
    const mssvInput = document.getElementById('mssv');
    const fullNameInput = document.getElementById('full_name');
    const birthDateInput = document.getElementById('birth_date');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Date range restrictions
    if (birthDateInput) {
        const today = new Date();
        const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate()).toISOString().split('T')[0];
        const minDate = new Date(today.getFullYear() - 100, today.getMonth(), today.getDate()).toISOString().split('T')[0];
        birthDateInput.setAttribute('max', maxDate);
        birthDateInput.setAttribute('min', minDate);
    }

    function validateMssv(trigger = 'blur') {
        if (!mssvInput) return true;
        const val = mssvInput.value.trim();
        const err = document.getElementById('mssv_error');
        if (!val) {
            if (trigger === 'submit' || trigger === 'blur') {
                mssvInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập MSSV.';
                return false;
            }
            return true;
        }
        if (/\s/.test(val) || /[^\x00-\x7F]/.test(val) || !/^[a-zA-Z0-9_-]+$/.test(val)) {
            mssvInput.classList.add('is-invalid');
            if (err) err.textContent = 'MSSV không được chứa khoảng trắng, dấu tiếng Việt hoặc ký tự đặc biệt.';
            return false;
        }
        mssvInput.classList.remove('is-invalid');
        return true;
    }

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

    function validateBirthDate(trigger = 'blur') {
        if (!birthDateInput) return true;
        const val = birthDateInput.value.trim();
        const err = document.getElementById('birth_date_error');
        if (!val) {
            if (trigger === 'submit' || trigger === 'blur') {
                birthDateInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập ngày sinh.';
                return false;
            }
            return true;
        }
        const dob = new Date(val);
        const today = new Date();
        if (isNaN(dob.getTime())) {
            birthDateInput.classList.add('is-invalid');
            if (err) err.textContent = 'Định dạng ngày sinh không hợp lệ.';
            return false;
        }
        if (dob > today) {
            birthDateInput.classList.add('is-invalid');
            if (err) err.textContent = 'Ngày sinh không được ở tương lai.';
            return false;
        }
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        if (age < 18) {
            birthDateInput.classList.add('is-invalid');
            if (err) err.textContent = 'Sinh viên phải từ 18 tuổi trở lên.';
            return false;
        }
        if (age > 100) {
            birthDateInput.classList.add('is-invalid');
            if (err) err.textContent = 'Ngày sinh không hợp lệ (quá 100 tuổi).';
            return false;
        }
        birthDateInput.classList.remove('is-invalid');
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
            if (err) err.textContent = 'Email không được chứa khoảng trắng hoặc dấu tiếng Việt (VD: sinhvien@gmail.com).';
            return false;
        }
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
        if (!emailRegex.test(val)) {
            if (trigger === 'submit' || trigger === 'blur' || val.includes('@')) {
                emailInput.classList.add('is-invalid');
                if (err) err.textContent = 'Định dạng email không hợp lệ (VD: sinhvien@gmail.com).';
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
        // In edit mode, password is optional
        if (!val) {
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

    // Real-time input listeners
    if (mssvInput) {
        mssvInput.addEventListener('input', () => validateMssv('input'));
        mssvInput.addEventListener('blur', () => validateMssv('blur'));
    }
    if (fullNameInput) {
        fullNameInput.addEventListener('input', () => validateFullName('input'));
        fullNameInput.addEventListener('blur', () => validateFullName('blur'));
    }
    if (birthDateInput) {
        birthDateInput.addEventListener('change', () => validateBirthDate('blur'));
        birthDateInput.addEventListener('blur', () => validateBirthDate('blur'));
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

            const isMssvValid = validateMssv('submit');
            const isFullNameValid = validateFullName('submit');
            const isBirthDateValid = validateBirthDate('submit');
            const isEmailValid = validateEmail('submit');
            const isPasswordValid = validatePassword('submit');

            if (isMssvValid && isFullNameValid && isBirthDateValid && isEmailValid && isPasswordValid) {
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
