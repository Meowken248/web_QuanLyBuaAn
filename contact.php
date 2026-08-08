<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Liên hệ';
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $field_errors = [];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        if ($name === '') {
            $field_errors['name'] = 'Vui lòng nhập họ tên.';
        } elseif (mb_strlen($name) > 150) {
            $field_errors['name'] = 'Họ tên không được vượt quá 150 ký tự.';
        }
        
        if ($email === '') {
            $field_errors['email'] = 'Vui lòng nhập email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = 'Email không hợp lệ.';
        }
        
        if ($subject === '') {
            $field_errors['subject'] = 'Vui lòng nhập chủ đề.';
        } elseif (mb_strlen($subject) > 200) {
            $field_errors['subject'] = 'Chủ đề không được vượt quá 200 ký tự.';
        }
        
        if ($message === '') {
            $field_errors['message'] = 'Vui lòng nhập nội dung.';
        } elseif (mb_strlen($message) > 5000) {
            $field_errors['message'] = 'Nội dung không được vượt quá 5000 ký tự.';
        }

        if (empty($field_errors)) {
            $conn = (new Database())->getConnection();
            $stmt = $conn->prepare("INSERT INTO contact_messages (full_name, email, subject, message, status) VALUES (:name, :email, :subject, :message, 'new')");
            $stmt->execute([':name' => $name, ':email' => $email, ':subject' => $subject, ':message' => $message]);
            $success_msg = 'Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi trong thời gian sớm nhất.';
            $_POST = [];
        } else {
            $error_msg = 'Vui lòng kiểm tra lại thông tin nhập liệu.';
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5"><div class="row justify-content-center"><div class="col-md-8">
<div class="card shadow-sm border-0"><div class="card-body p-5">
<div class="text-center mb-4"><h2 class="fw-bold">Liên hệ với chúng tôi</h2><p class="text-muted">Gửi bất kỳ câu hỏi hoặc góp ý nào cho đội ngũ phát triển</p></div>
<?php if ($success_msg): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="mb-3">
    <label class="form-label">Họ và tên</label>
    <input type="text" class="form-control <?php echo isset($field_errors['name']) ? 'is-invalid' : ''; ?>" name="name" maxlength="150" value="<?php echo old('name'); ?>" required>
    <?php if(isset($field_errors['name'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['name']; ?></div><?php endif; ?>
</div>
<div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" name="email" value="<?php echo old('email'); ?>" required>
    <?php if(isset($field_errors['email'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['email']; ?></div><?php endif; ?>
</div>
<div class="mb-3">
    <label class="form-label">Chủ đề</label>
    <input type="text" class="form-control <?php echo isset($field_errors['subject']) ? 'is-invalid' : ''; ?>" name="subject" maxlength="200" value="<?php echo old('subject'); ?>" required>
    <?php if(isset($field_errors['subject'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['subject']; ?></div><?php endif; ?>
</div>
<div class="mb-4">
    <label class="form-label">Nội dung tin nhắn</label>
    <textarea class="form-control <?php echo isset($field_errors['message']) ? 'is-invalid' : ''; ?>" name="message" rows="5" maxlength="5000" required><?php echo old('message'); ?></textarea>
    <?php if(isset($field_errors['message'])): ?><div class="invalid-feedback d-block"><?php echo $field_errors['message']; ?></div><?php endif; ?>
</div>
<button type="submit" class="btn btn-success w-100 fw-bold py-2">Gửi tin nhắn</button>
</form></div></div>
<div class="row mt-5 text-center">
<div class="col-md-4 mb-3"><i class="bi bi-envelope fs-1 text-success mb-2"></i><h5>Email</h5><p class="text-muted small">support@mealmanager.com</p></div>
<div class="col-md-4 mb-3"><i class="bi bi-telephone fs-1 text-success mb-2"></i><h5>Điện thoại</h5><p class="text-muted small">1900 1000</p></div>
<div class="col-md-4 mb-3"><i class="bi bi-geo-alt fs-1 text-success mb-2"></i><h5>Địa chỉ</h5><p class="text-muted small">Quận 1, TP. Hồ Chí Minh</p></div>
</div></div></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
