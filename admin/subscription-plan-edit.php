<?php
// admin/subscription-plan-edit.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$plan = null;

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        $_SESSION['error'] = 'Gói đăng ký không tồn tại.';
        redirect('/admin/subscription-plans.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 0);
    $features = trim($_POST['features'] ?? '');
    $chatbot_limit_per_day = (int)($_POST['chatbot_limit_per_day'] ?? 5);
    $status = $_POST['status'] ?? 'active';

    if (empty($code)) {
        // Tự động tạo code nếu trống
        $code = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
    }

    if (empty($name) || $price < 0 || $duration_days <= 0 || empty($code)) {
        $_SESSION['error'] = 'Vui lòng điền đầy đủ và chính xác tên gói, mã gói, giá tiền và thời hạn (lớn hơn 0).';
    } else {
        try {
            if ($is_edit) {
                $stmt = $conn->prepare("UPDATE subscription_plans SET name = :name, code = :code, description = :description, price = :price, duration_days = :duration_days, features = :features, chatbot_limit_per_day = :chatbot_limit_per_day, status = :status WHERE id = :id");
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':description' => $description,
                    ':price' => $price,
                    ':duration_days' => $duration_days,
                    ':features' => $features,
                    ':chatbot_limit_per_day' => $chatbot_limit_per_day,
                    ':status' => $status,
                    ':id' => $id
                ]);
                $_SESSION['success'] = 'Cập nhật Gói Đăng ký thành công.';
            } else {
                $stmt = $conn->prepare("INSERT INTO subscription_plans (name, code, description, price, duration_days, features, chatbot_limit_per_day, status) VALUES (:name, :code, :description, :price, :duration_days, :features, :chatbot_limit_per_day, :status)");
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':description' => $description,
                    ':price' => $price,
                    ':duration_days' => $duration_days,
                    ':features' => $features,
                    ':chatbot_limit_per_day' => $chatbot_limit_per_day,
                    ':status' => $status
                ]);
                $_SESSION['success'] = 'Thêm Gói Đăng ký mới thành công.';
            }
            redirect('/admin/subscription-plans.php');
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'Sửa Gói Đăng ký' : 'Thêm Gói Đăng ký mới';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0"><?php echo $page_title; ?></h3>
                <a href="<?php echo BASE_URL; ?>/admin/subscription-plans.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="name" class="form-label fw-bold">Tên Gói <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $plan['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="code" class="form-label fw-bold">Mã Gói</label>
                                <input type="text" class="form-control" id="code" name="code" value="<?php echo htmlspecialchars($_POST['code'] ?? $plan['code'] ?? ''); ?>" placeholder="Tự tạo nếu trống">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="price" class="form-label fw-bold">Giá (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? $plan['price'] ?? ''); ?>" min="0" step="1000" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="duration_days" class="form-label fw-bold">Thời hạn (Ngày) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="duration_days" name="duration_days" value="<?php echo htmlspecialchars($_POST['duration_days'] ?? $plan['duration_days'] ?? ''); ?>" min="1" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="description" class="form-label fw-bold">Mô tả ngắn</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Nhập mô tả tóm tắt..."><?php echo htmlspecialchars($_POST['description'] ?? $plan['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="features" class="form-label fw-bold">Tính năng chi tiết</label>
                                <textarea class="form-control" id="features" name="features" rows="3" placeholder="Mỗi tính năng 1 dòng..."><?php echo htmlspecialchars($_POST['features'] ?? $plan['features'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="status" class="form-label fw-bold">Trạng thái</label>
                                <select class="form-select" id="status" name="status">
                                    <?php $current_status = $_POST['status'] ?? $plan['status'] ?? 'active'; ?>
                                    <option value="active" <?php echo $current_status === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="inactive" <?php echo $current_status === 'inactive' ? 'selected' : ''; ?>>Tạm ẩn</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="chatbot_limit_per_day" class="form-label fw-bold">Lượt Chatbot / Ngày</label>
                                <input type="number" class="form-control" id="chatbot_limit_per_day" name="chatbot_limit_per_day" value="<?php echo htmlspecialchars($_POST['chatbot_limit_per_day'] ?? $plan['chatbot_limit_per_day'] ?? 5); ?>" min="0">
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i><?php echo $is_edit ? 'Cập nhật' : 'Lưu Gói Đăng ký'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (isset($hide_footer) && $hide_footer) {
    echo '</body></html>';
} else {
    require_once __DIR__ . '/../includes/footer.php'; 
}
?>
