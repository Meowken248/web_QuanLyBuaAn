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
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2"><?php require __DIR__ . '/includes/sidebar.php'; ?></div>
        <div class="col-md-10">
            <div class="row justify-content-center mt-3">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
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
                                    <input id="mssv" class="form-control <?php echo isset($field_errors['mssv']) ? 'is-invalid' : ''; ?>" name="mssv" maxlength="50" value="<?php echo old('mssv', $edit_student['mssv'] ?? ''); ?>" required>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['mssv'] ?? 'Vui lòng nhập MSSV.'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="full_name">Họ Và Tên <span class="text-danger">*</span></label>
                                    <input id="full_name" class="form-control <?php echo isset($field_errors['full_name']) ? 'is-invalid' : ''; ?>" name="full_name" maxlength="150" value="<?php echo old('full_name', $edit_student['full_name'] ?? ''); ?>" required>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['full_name'] ?? 'Vui lòng nhập họ tên.'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="birth_date">Ngày Sinh <span class="text-danger">*</span></label>
                                    <input type="date" id="birth_date" class="form-control <?php echo isset($field_errors['birth_date']) ? 'is-invalid' : ''; ?>" name="birth_date" value="<?php echo old('birth_date', $edit_student['birth_date'] ?? ''); ?>" required>
                                    <div class="invalid-feedback" id="birth_date_error"><?php echo htmlspecialchars($field_errors['birth_date'] ?? 'Vui lòng nhập ngày sinh.'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="email">Email <span class="text-danger">*</span></label>
                                    <input id="email" type="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" name="email" maxlength="190" value="<?php echo old('email', $edit_student['email'] ?? ''); ?>" required>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['email'] ?? 'Vui lòng nhập email hợp lệ.'); ?></div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold" for="password">Mật khẩu <span class="text-muted small fw-normal">(Để trống nếu không muốn đổi)</span></label>
                                    <div class="input-group has-validation">
                                        <input id="password" type="password" class="form-control <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" name="password" minlength="8" autocomplete="new-password">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="password" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['password'] ?? 'Mật khẩu phải có ít nhất 8 ký tự và không chỉ chứa khoảng trắng.'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <a class="btn btn-light rounded-pill" href="<?php echo BASE_URL; ?>/admin/class-detail.php?id=<?= $class_id ?>">Hủy</a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Cập nhật</button>
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

    // Client-side validation
    const form = document.getElementById('editStudentForm');
    const birthDateInput = document.getElementById('birth_date');
    const birthDateError = document.getElementById('birth_date_error');

    if (birthDateInput) {
        const today = new Date();
        const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate()).toISOString().split('T')[0];
        const minDate = new Date(today.getFullYear() - 100, today.getMonth(), today.getDate()).toISOString().split('T')[0];
        
        birthDateInput.setAttribute('max', maxDate);
        birthDateInput.setAttribute('min', minDate);
    }

    if (form) {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            const dobValue = birthDateInput.value;
            if (dobValue) {
                const dobDate = new Date(dobValue);
                const maxAllowedDate = new Date(birthDateInput.getAttribute('max'));
                const minAllowedDate = new Date(birthDateInput.getAttribute('min'));
                
                if (dobDate > maxAllowedDate) {
                    birthDateInput.classList.add('is-invalid');
                    if (birthDateError) birthDateError.textContent = "Sinh viên phải từ 18 tuổi trở lên.";
                    isValid = false;
                } else if (dobDate < minAllowedDate) {
                    birthDateInput.classList.add('is-invalid');
                    if (birthDateError) birthDateError.textContent = "Ngày sinh không hợp lệ (quá 100 tuổi).";
                    isValid = false;
                } else {
                    birthDateInput.classList.remove('is-invalid');
                }
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
        
        const inputs = form.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
