<?php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$conn = (new Database())->getConnection();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$food = null;
if ($id) {
    $stmt = $conn->prepare('SELECT * FROM foods WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $food = $stmt->fetch();
    if (!$food) {
        set_flash_message('danger', 'Không tìm thấy món ăn.');
        redirect('/admin/foods.php');
    }
}
$categories = $conn->query("SELECT id, name FROM food_categories WHERE status = 'active' ORDER BY name")->fetchAll();
$error = '';
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $serving_size = filter_var($_POST['serving_size'] ?? null, FILTER_VALIDATE_FLOAT);
    $serving_unit = trim($_POST['serving_unit'] ?? '');
    $nutrients = [];
    foreach (['calories', 'protein', 'carbs', 'fat', 'fiber'] as $field) {
        $raw = trim((string)($_POST[$field] ?? ''));
        $nutrients[$field] = $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_FLOAT);
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    if ($name === '') $field_errors['name'] = 'Vui lòng nhập tên món.';
    if (!$category_id) $field_errors['category_id'] = 'Vui lòng chọn danh mục.';
    if ($serving_size === false || $serving_size === null || $serving_size <= 0) $field_errors['serving_size'] = 'Khẩu phần phải lớn hơn 0.';
    if ($serving_unit === '') $field_errors['serving_unit'] = 'Vui lòng nhập đơn vị.';
    foreach ($nutrients as $field => $value) {
        if ($value === false || $value === null || $value < 0) $field_errors[$field] = 'Giá trị phải lớn hơn hoặc bằng 0.';
    }

    if (!$field_errors) {
        $macro_total = $nutrients['protein'] + $nutrients['carbs'] + $nutrients['fat'] + $nutrients['fiber'];
        if (in_array(mb_strtolower($serving_unit), ['g', 'gram', 'grams'], true) && $macro_total > $serving_size + 0.01) {
            $field_errors['protein'] = 'Tổng Protein + Carbs + Fat + Chất xơ không được vượt khẩu phần.';
        }
        $calculated_calories = $nutrients['protein'] * 4 + $nutrients['carbs'] * 4 + $nutrients['fat'] * 9;
        $allowed_difference = max(20, $calculated_calories * 0.2);
        if ($calculated_calories > 0 && abs($nutrients['calories'] - $calculated_calories) > $allowed_difference) {
            $field_errors['calories'] = 'Calories lệch nhiều so với macros (ước tính ' . round($calculated_calories, 1) . ' kcal). Vui lòng kiểm tra lại.';
        }
    }

    $image_path = $food['image'] ?? null;
    if (!$error && !$field_errors && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK || $_FILES['image']['size'] > 5 * 1024 * 1024) {
            $field_errors['image'] = 'Ảnh tải lên không hợp lệ hoặc lớn hơn 5 MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($extensions[$mime])) {
                $field_errors['image'] = 'Chỉ chấp nhận ảnh JPG, PNG, WEBP hoặc GIF.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/foods/';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                    $field_errors['image'] = 'Không thể tạo thư mục lưu ảnh.';
                } else {
                    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                        $field_errors['image'] = 'Không thể lưu ảnh tải lên.';
                    } else {
                        $image_path = '/uploads/foods/' . $filename;
                    }
                }
            }
        }
    }

    if (!$error && !$field_errors) {
        $slug_base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
        $params = [
            ':category_id' => $category_id, ':name' => $name, ':slug' => $slug_base . '-' . ($id ?: time()),
            ':image' => $image_path, ':description' => trim($_POST['description'] ?? ''),
            ':ingredients' => trim($_POST['ingredients'] ?? ''), ':instructions' => trim($_POST['instructions'] ?? ''),
            ':serving_size' => $serving_size, ':serving_unit' => $serving_unit, ':calories' => $nutrients['calories'],
            ':protein' => $nutrients['protein'], ':carbs' => $nutrients['carbs'], ':fat' => $nutrients['fat'],
            ':fiber' => $nutrients['fiber'], ':status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active'
        ];
        if ($id) {
            $params[':id'] = $id;
            $sql = 'UPDATE foods SET category_id=:category_id,name=:name,slug=:slug,image=:image,description=:description,ingredients=:ingredients,instructions=:instructions,serving_size=:serving_size,serving_unit=:serving_unit,calories=:calories,protein=:protein,carbs=:carbs,fat=:fat,fiber=:fiber,status=:status WHERE id=:id';
        } else {
            $sql = 'INSERT INTO foods (category_id,name,slug,image,description,ingredients,instructions,serving_size,serving_unit,calories,protein,carbs,fat,fiber,status,created_by) VALUES (:category_id,:name,:slug,:image,:description,:ingredients,:instructions,:serving_size,:serving_unit,:calories,:protein,:carbs,:fat,:fiber,:status,' . (int)$_SESSION['user_id'] . ')';
        }
        $conn->prepare($sql)->execute($params);
        set_flash_message('success', $id ? 'Đã cập nhật món ăn.' : 'Đã thêm món ăn mới.');
        redirect('/admin/foods.php');
    }
}

function food_form_value($key, $food, $default = '') {
    if (array_key_exists($key, $_POST)) return htmlspecialchars((string)$_POST[$key], ENT_QUOTES, 'UTF-8');
    return htmlspecialchars((string)($food[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

$page_title = $id ? 'Sửa món ăn' : 'Thêm món ăn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-9"><div class="card shadow-sm"><div class="card-body p-4">
<h3 class="fw-bold mb-4"><?php echo htmlspecialchars($page_title); ?></h3>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($field_errors): ?><div class="alert alert-danger">Dữ liệu chưa hợp lệ. Vui lòng kiểm tra các trường được đánh dấu.</div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" novalidate><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="row g-3">
<div class="col-md-8"><label class="form-label">Tên món <span class="text-danger">*</span></label><input class="form-control <?php echo isset($field_errors['name']) ? 'is-invalid' : ''; ?>" name="name" maxlength="200" value="<?php echo food_form_value('name', $food); ?>" required><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['name'] ?? 'Vui lòng nhập tên món.'); ?></div></div>
<div class="col-md-4"><label class="form-label">Danh mục <span class="text-danger">*</span></label><select class="form-select <?php echo isset($field_errors['category_id']) ? 'is-invalid' : ''; ?>" name="category_id" required><option value="">Chọn danh mục</option><?php foreach ($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo (string)($_POST['category_id'] ?? $food['category_id'] ?? '') === (string)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['category_id'] ?? 'Vui lòng chọn danh mục.'); ?></div></div>
<div class="col-12"><label class="form-label">Mô tả ngắn</label><textarea class="form-control" name="description" rows="2"><?php echo food_form_value('description', $food); ?></textarea></div>
<div class="col-md-6"><label class="form-label">Nguyên liệu</label><textarea class="form-control" name="ingredients" rows="4"><?php echo food_form_value('ingredients', $food); ?></textarea></div>
<div class="col-md-6"><label class="form-label">Cách làm</label><textarea class="form-control" name="instructions" rows="4"><?php echo food_form_value('instructions', $food); ?></textarea></div>
<div class="col-12"><label class="form-label">Hình ảnh minh họa</label><input type="file" class="form-control <?php echo isset($field_errors['image']) ? 'is-invalid' : ''; ?>" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['image'] ?? ''); ?></div><?php if (!empty($food['image'])): ?><div class="mt-2"><img src="<?php echo htmlspecialchars(food_image_url($food['image'])); ?>" alt="Hình ảnh" style="height:100px;object-fit:cover;border-radius:8px"></div><?php endif; ?></div>
<div class="col-md-4"><label class="form-label">Khẩu phần <span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" class="form-control <?php echo isset($field_errors['serving_size']) ? 'is-invalid' : ''; ?>" name="serving_size" value="<?php echo food_form_value('serving_size', $food, '100'); ?>" required><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['serving_size'] ?? 'Khẩu phần phải lớn hơn 0.'); ?></div></div>
<div class="col-md-4"><label class="form-label">Đơn vị <span class="text-danger">*</span></label><input class="form-control <?php echo isset($field_errors['serving_unit']) ? 'is-invalid' : ''; ?>" name="serving_unit" value="<?php echo food_form_value('serving_unit', $food, 'gram'); ?>" required><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['serving_unit'] ?? 'Vui lòng nhập đơn vị.'); ?></div></div>
<div class="col-md-4"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="active">Hoạt động</option><option value="inactive" <?php echo ($_POST['status'] ?? $food['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Ẩn</option></select></div>
<?php foreach (['calories'=>'Calories','protein'=>'Protein (g)','carbs'=>'Carbs (g)','fat'=>'Fat (g)','fiber'=>'Chất xơ (g)'] as $key=>$label): ?>
<div class="col-md"><label class="form-label"><?php echo $label; ?> <span class="text-danger">*</span></label><input type="number" min="0" step="0.01" data-clear-zero class="form-control <?php echo isset($field_errors[$key]) ? 'is-invalid' : ''; ?>" name="<?php echo $key; ?>" placeholder="0.00" value="<?php echo food_form_value($key, $food, ''); ?>" required><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors[$key] ?? 'Giá trị phải lớn hơn hoặc bằng 0.'); ?></div></div>
<?php endforeach; ?>
<div class="col-12"><div class="form-text">Calories tham khảo = Protein × 4 + Carbs × 4 + Fat × 9. Nếu đơn vị là gram, tổng macros và chất xơ không được vượt khẩu phần.</div></div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/admin/foods.php">Hủy</a><button class="btn btn-success">Lưu món ăn</button></div>
</form></div></div></div></div></div>
<script>
document.querySelectorAll('[data-clear-zero]').forEach(input => {
    input.addEventListener('focus', () => { if (parseFloat(input.value) === 0) input.value = ''; });
    input.addEventListener('blur', () => { if (input.value === '') input.value = '0.00'; });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
