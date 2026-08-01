<?php
// admin/meal-plan-edit.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$plan = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        $_SESSION['error'] = 'Không tìm thấy thực đơn.';
        redirect('/admin/meal-plans.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die('CSRF token error');
    
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'])));
    $description = trim($_POST['description']);
    $goal_type = $_POST['goal_type'];
    $diet_type = trim($_POST['diet_type']);
    $status = $_POST['status'];
    
    // Upload image
    $image = $plan['image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/meal_plans/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $new_name = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_name)) {
                if ($image && file_exists($upload_dir . $image)) unlink($upload_dir . $image);
                $image = $new_name;
            }
        }
    }
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE meal_plans SET name=:n, slug=:s, description=:d, goal_type=:g, diet_type=:dt, image=:img, is_premium=0, status=:st WHERE id=:id");
        $stmt->execute([':n'=>$name, ':s'=>$slug, ':d'=>$description, ':g'=>$goal_type, ':dt'=>$diet_type, ':img'=>$image, ':st'=>$status, ':id'=>$id]);
        $_SESSION['success'] = 'Đã cập nhật thực đơn thành công.';
        redirect('/admin/meal-plans.php');
    } else {
        $stmt = $conn->prepare("INSERT INTO meal_plans (name, slug, description, goal_type, diet_type, image, status, created_by) VALUES (:n, :s, :d, :g, :dt, :img, :st, :cb)");
        $stmt->execute([':n'=>$name, ':s'=>$slug, ':d'=>$description, ':g'=>$goal_type, ':dt'=>$diet_type, ':img'=>$image, ':st'=>$status, ':cb'=>$_SESSION['user_id']]);
        $_SESSION['success'] = 'Đã thêm thực đơn mới.';
        redirect('/admin/meal-plans.php');
    }
}

$current_page = 'meal-plans.php';
$page_title = $id > 0 ? 'Sửa Thực đơn' : 'Thêm Thực đơn mới';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0"><?php echo $page_title; ?></h2>
                <a href="<?php echo BASE_URL; ?>/admin/meal-plans.php" class="btn btn-outline-secondary">Quay lại</a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tên thực đơn <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="name" value="<?php echo htmlspecialchars($plan['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Đường dẫn thân thiện (Slug) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="slug" id="slug" value="<?php echo htmlspecialchars($plan['slug'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Mô tả chi tiết</label>
                            <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Mục tiêu <span class="text-danger">*</span></label>
                                <select class="form-select" name="goal_type" required>
                                    <option value="lose_weight" <?php echo ($plan['goal_type'] ?? '') == 'lose_weight' ? 'selected' : ''; ?>>Giảm cân</option>
                                    <option value="gain_weight" <?php echo ($plan['goal_type'] ?? '') == 'gain_weight' ? 'selected' : ''; ?>>Tăng cân</option>
                                    <option value="maintain_weight" <?php echo ($plan['goal_type'] ?? '') == 'maintain_weight' ? 'selected' : ''; ?>>Giữ dáng / Khỏe mạnh</option>
                                    <option value="gain_muscle" <?php echo ($plan['goal_type'] ?? '') == 'gain_muscle' ? 'selected' : ''; ?>>Tăng cơ</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Kiểu ăn uống (Diet Type)</label>
                                <input type="text" class="form-control" name="diet_type" value="<?php echo htmlspecialchars($plan['diet_type'] ?? 'Truyền thống'); ?>" placeholder="Ví dụ: Keto, Eat Clean, Chay...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Trạng thái <span class="text-danger">*</span></label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo ($plan['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Đang bật (Hiển thị)</option>
                                    <option value="inactive" <?php echo ($plan['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Đang tắt (Ẩn)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-4 align-items-center">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Ảnh đại diện (Tùy chọn)</label>
                                <input type="file" class="form-control" name="image" accept="image/*">
                                <?php if (isset($plan['image']) && $plan['image']): ?>
                                    <div class="mt-2">
                                        <img src="<?php echo BASE_URL . '/uploads/meal_plans/' . $plan['image']; ?>" height="100" class="rounded border">
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Lưu thông tin</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($id > 0): ?>
            <div class="alert alert-info mt-4">
                <i class="bi bi-info-circle me-2"></i> 
                <strong>Lưu ý:</strong> Để thêm các món ăn chi tiết vào thực đơn này, vui lòng truy cập phần <a href="meal-plan-builder.php?id=<?php echo $id; ?>" class="alert-link fw-bold">Xây dựng chi tiết bữa ăn</a>. 
                Tổng Calories và dinh dưỡng sẽ được tự động tính toán dựa trên các món ăn mà bạn thêm vào.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Tự động tạo slug từ tên
    document.getElementById('name').addEventListener('input', function() {
        let slug = this.value.toLowerCase();
        slug = slug.replace(/á|à|ả|ạ|ã|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ/gi, 'a');
        slug = slug.replace(/é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ/gi, 'e');
        slug = slug.replace(/i|í|ì|ỉ|ĩ|ị/gi, 'i');
        slug = slug.replace(/ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ/gi, 'o');
        slug = slug.replace(/ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự/gi, 'u');
        slug = slug.replace(/ý|ỳ|ỷ|ỹ|ỵ/gi, 'y');
        slug = slug.replace(/đ/gi, 'd');
        slug = slug.replace(/[^a-z0-9-]/g, '-');
        slug = slug.replace(/-+/g, '-');
        slug = slug.replace(/^-|-$/g, '');
        
        <?php if (!$id): ?>
        document.getElementById('slug').value = slug;
        <?php endif; ?>
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
