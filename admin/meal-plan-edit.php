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

$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die('CSRF token error');
    
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'] ?? '')));
    $description = trim($_POST['description'] ?? '');
    $goal_type = $_POST['goal_type'] ?? 'lose_weight';
    $diet_type = trim($_POST['diet_type'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $field_errors['name'] = 'Vui lòng nhập tên thực đơn.';
    }
    if (empty($slug)) {
        $field_errors['slug'] = 'Vui lòng nhập đường dẫn thân thiện.';
    }
    
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
    
    if (empty($field_errors)) {
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
    } else {
        $_SESSION['error'] = 'Vui lòng kiểm tra lại thông tin nhập liệu.';
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
                <a href="<?php echo BASE_URL; ?>/admin/meal-plans.php" class="btn btn-sm btn-outline-secondary rounded-pill">Quay lại</a>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data" id="mealPlanForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tên thực đơn <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($field_errors['name']) ? 'is-invalid' : ''; ?>" name="name" id="name" value="<?php echo old('name', $plan['name'] ?? ''); ?>" required>
                                <div class="invalid-feedback" id="nameError"><?php echo $field_errors['name'] ?? 'Vui lòng nhập tên thực đơn (tối thiểu 3 ký tự).'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Đường dẫn thân thiện (Slug) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($field_errors['slug']) ? 'is-invalid' : ''; ?>" name="slug" id="slug" value="<?php echo old('slug', $plan['slug'] ?? ''); ?>" required>
                                <div class="invalid-feedback" id="slugError"><?php echo $field_errors['slug'] ?? 'Đường dẫn (slug) không hợp lệ (chỉ gồm chữ thường không dấu, số và dấu gạch nối).'; ?></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Mô tả chi tiết</label>
                            <textarea class="form-control" name="description" rows="4"><?php echo old('description', $plan['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Mục tiêu <span class="text-danger">*</span></label>
                                <select class="form-select" name="goal_type" required>
                                    <option value="lose_weight" <?php echo old('goal_type', $plan['goal_type'] ?? '') == 'lose_weight' ? 'selected' : ''; ?>>Giảm cân</option>
                                    <option value="gain_weight" <?php echo old('goal_type', $plan['goal_type'] ?? '') == 'gain_weight' ? 'selected' : ''; ?>>Tăng cân</option>
                                    <option value="maintain_weight" <?php echo old('goal_type', $plan['goal_type'] ?? '') == 'maintain_weight' ? 'selected' : ''; ?>>Giữ dáng / Khỏe mạnh</option>
                                    <option value="gain_muscle" <?php echo old('goal_type', $plan['goal_type'] ?? '') == 'gain_muscle' ? 'selected' : ''; ?>>Tăng cơ</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Kiểu ăn uống (Diet Type)</label>
                                <input type="text" class="form-control" name="diet_type" value="<?php echo old('diet_type', $plan['diet_type'] ?? 'Truyền thống'); ?>" placeholder="Ví dụ: Keto, Eat Clean, Chay...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Trạng thái <span class="text-danger">*</span></label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo old('status', $plan['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Đang bật (Hiển thị)</option>
                                    <option value="inactive" <?php echo old('status', $plan['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Đang tắt (Ẩn)</option>
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
                            <button type="submit" class="btn btn-outline-primary rounded-pill px-4 fw-bold">Lưu thông tin</button>
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
    const form = document.getElementById('mealPlanForm');
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const nameError = document.getElementById('nameError');
    const slugError = document.getElementById('slugError');

    // Tự động tạo slug từ tên
    nameInput.addEventListener('input', function() {
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
        slugInput.value = slug;
        if (slug) validateSlug();
        <?php endif; ?>
    });

    function validateName() {
        const val = nameInput.value.trim();
        if (!val) {
            nameInput.classList.add('is-invalid');
            nameInput.classList.remove('is-valid');
            if (nameError) nameError.textContent = 'Vui lòng nhập tên thực đơn.';
            return false;
        }
        if (val.length < 3) {
            nameInput.classList.add('is-invalid');
            nameInput.classList.remove('is-valid');
            if (nameError) nameError.textContent = 'Tên thực đơn phải có ít nhất 3 ký tự.';
            return false;
        }
        nameInput.classList.remove('is-invalid');
        nameInput.classList.add('is-valid');
        return true;
    }

    function validateSlug() {
        const val = slugInput.value.trim();
        if (!val) {
            slugInput.classList.add('is-invalid');
            slugInput.classList.remove('is-valid');
            if (slugError) slugError.textContent = 'Vui lòng nhập đường dẫn thân thiện (slug).';
            return false;
        }
        if (val.length < 3) {
            slugInput.classList.add('is-invalid');
            slugInput.classList.remove('is-valid');
            if (slugError) slugError.textContent = 'Slug phải có ít nhất 3 ký tự.';
            return false;
        }
        if (!/^[a-z0-9-]+$/.test(val)) {
            slugInput.classList.add('is-invalid');
            slugInput.classList.remove('is-valid');
            if (slugError) slugError.textContent = 'Slug chỉ chứa chữ cái thường không dấu, số và dấu gạch ngang.';
            return false;
        }
        slugInput.classList.remove('is-invalid');
        slugInput.classList.add('is-valid');
        return true;
    }

    nameInput.addEventListener('input', validateName);
    nameInput.addEventListener('blur', validateName);
    slugInput.addEventListener('input', validateSlug);
    slugInput.addEventListener('blur', validateSlug);

    if (form) {
        form.addEventListener('submit', function(e) {
            const isNameOk = validateName();
            const isSlugOk = validateSlug();

            if (!isNameOk || !isSlugOk) {
                e.preventDefault();
                e.stopPropagation();
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.focus();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
