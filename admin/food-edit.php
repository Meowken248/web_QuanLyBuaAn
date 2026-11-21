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

    // Kiểm tra trùng tên món trong cùng danh mục (BUG-19)
    if ($name !== '' && $category_id) {
        $checkDuplicateSql = "SELECT id FROM foods WHERE category_id = :category_id AND LOWER(TRIM(name)) = LOWER(TRIM(:name))";
        $checkDuplicateParams = [':category_id' => $category_id, ':name' => $name];
        if ($id) {
            $checkDuplicateSql .= " AND id != :id";
            $checkDuplicateParams[':id'] = $id;
        }
        $checkStmt = $conn->prepare($checkDuplicateSql);
        $checkStmt->execute($checkDuplicateParams);
        if ($checkStmt->fetch()) {
            $field_errors['name'] = 'Tên món ăn đã tồn tại trong danh mục này.';
        }
    }

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
        $seasons = $_POST['season'] ?? [];
        if (is_array($seasons)) {
            $validSeasons = ['xuan', 'he', 'thu', 'dong'];
            $cleanSeasons = array_values(array_intersect($validSeasons, $seasons));
            $seasonStr = !empty($cleanSeasons) ? implode(',', $cleanSeasons) : 'xuan,he,thu,dong';
        } else {
            $seasonStr = 'xuan,he,thu,dong';
        }

        $slug_base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
        $params = [
            ':category_id' => $category_id, ':name' => $name, ':slug' => $slug_base . '-' . ($id ?: time()),
            ':image' => $image_path, ':description' => trim($_POST['description'] ?? ''),
            ':ingredients' => trim($_POST['ingredients'] ?? ''), ':instructions' => trim($_POST['instructions'] ?? ''),
            ':serving_size' => $serving_size, ':serving_unit' => $serving_unit, ':calories' => $nutrients['calories'],
            ':protein' => $nutrients['protein'], ':carbs' => $nutrients['carbs'], ':fat' => $nutrients['fat'],
            ':fiber' => $nutrients['fiber'], ':season' => $seasonStr, ':status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active'
        ];
        if ($id) {
            $params[':id'] = $id;
            $sql = 'UPDATE foods SET category_id=:category_id,name=:name,slug=:slug,image=:image,description=:description,ingredients=:ingredients,instructions=:instructions,serving_size=:serving_size,serving_unit=:serving_unit,calories=:calories,protein=:protein,carbs=:carbs,fat=:fat,fiber=:fiber,season=:season,status=:status WHERE id=:id';
        } else {
            $sql = 'INSERT INTO foods (category_id,name,slug,image,description,ingredients,instructions,serving_size,serving_unit,calories,protein,carbs,fat,fiber,season,status,created_by) VALUES (:category_id,:name,:slug,:image,:description,:ingredients,:instructions,:serving_size,:serving_unit,:calories,:protein,:carbs,:fat,:fiber,:season,:status,' . (int)$_SESSION['user_id'] . ')';
        }
        $conn->prepare($sql)->execute($params);
        set_flash_message('success', $id ? 'Đã cập nhật món ăn.' : 'Đã thêm món ăn mới.');
        redirect('/admin/foods.php');
    }
}

/**
 * @param string $key
 * @param array|null $food
 * @param string $default
 * @return string
 */
function food_form_value(string $key, ?array $food = null, string $default = ''): string {
    if (array_key_exists($key, $_POST)) return htmlspecialchars((string)$_POST[$key], ENT_QUOTES, 'UTF-8');
    return htmlspecialchars((string)($food[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

$page_title = $id ? 'Sửa món ăn' : 'Thêm món ăn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-9"><div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4"><div class="card-body p-4">
<h3 class="fw-bold mb-4"><?php echo htmlspecialchars($page_title); ?></h3>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($field_errors): ?><div class="alert alert-danger">Dữ liệu chưa hợp lệ. Vui lòng kiểm tra các trường được đánh dấu.</div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" novalidate id="foodForm"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="row g-3">
<div class="col-md-8"><label class="form-label fw-bold">Tên món <span class="text-danger">*</span></label><input id="food_name" class="form-control <?php echo isset($field_errors['name']) ? 'is-invalid' : ''; ?>" name="name" maxlength="200" value="<?php echo food_form_value('name', $food); ?>" required autocomplete="off"><div class="invalid-feedback" id="food_name_error"><?php echo htmlspecialchars($field_errors['name'] ?? 'Vui lòng nhập tên món.'); ?></div></div>
<div class="col-md-4"><label class="form-label fw-bold">Danh mục <span class="text-danger">*</span></label><select id="category_id" class="form-select <?php echo isset($field_errors['category_id']) ? 'is-invalid' : ''; ?>" name="category_id" required><option value="">Chọn danh mục</option><?php foreach ($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo (string)($_POST['category_id'] ?? $food['category_id'] ?? '') === (string)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select><div class="invalid-feedback" id="category_id_error"><?php echo htmlspecialchars($field_errors['category_id'] ?? 'Vui lòng chọn danh mục.'); ?></div></div>
<div class="col-12"><label class="form-label fw-bold">Mô tả ngắn</label><textarea class="form-control" name="description" rows="2"><?php echo food_form_value('description', $food); ?></textarea></div>
<div class="col-md-6"><label class="form-label fw-bold">Nguyên liệu</label><textarea class="form-control" name="ingredients" rows="4"><?php echo food_form_value('ingredients', $food); ?></textarea></div>
<div class="col-md-6"><label class="form-label fw-bold">Cách làm</label><textarea class="form-control" name="instructions" rows="4"><?php echo food_form_value('instructions', $food); ?></textarea></div>
<div class="col-12"><label class="form-label fw-bold">Hình ảnh minh họa</label><input type="file" class="form-control <?php echo isset($field_errors['image']) ? 'is-invalid' : ''; ?>" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['image'] ?? ''); ?></div><?php if (!empty($food['image'])): ?><div class="mt-2"><img src="<?php echo htmlspecialchars(food_image_url($food['image'])); ?>" alt="Hình ảnh" style="height:100px;object-fit:cover;border-radius:8px"></div><?php endif; ?></div>
<div class="col-md-4"><label class="form-label fw-bold">Khẩu phần <span class="text-danger">*</span></label><input id="serving_size" type="number" step="0.01" min="0.01" class="form-control <?php echo isset($field_errors['serving_size']) ? 'is-invalid' : ''; ?>" name="serving_size" value="<?php echo food_form_value('serving_size', $food, '100'); ?>" required><div class="invalid-feedback" id="serving_size_error"><?php echo htmlspecialchars($field_errors['serving_size'] ?? 'Khẩu phần phải lớn hơn 0.'); ?></div></div>
<div class="col-md-4"><label class="form-label fw-bold">Đơn vị <span class="text-danger">*</span></label><input id="serving_unit" class="form-control <?php echo isset($field_errors['serving_unit']) ? 'is-invalid' : ''; ?>" name="serving_unit" value="<?php echo food_form_value('serving_unit', $food, 'gram'); ?>" required autocomplete="off"><div class="invalid-feedback" id="serving_unit_error"><?php echo htmlspecialchars($field_errors['serving_unit'] ?? 'Vui lòng nhập đơn vị.'); ?></div></div>
<div class="col-md-4"><label class="form-label fw-bold">Trạng thái</label><select class="form-select" name="status"><option value="active">Hoạt động</option><option value="inactive" <?php echo ($_POST['status'] ?? $food['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Ẩn</option></select></div>
<?php
$currentSeasons = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentSeasons = (array)($_POST['season'] ?? []);
} elseif ($food && !empty($food['season'])) {
    $currentSeasons = explode(',', $food['season']);
} else {
    $currentSeasons = ['xuan', 'he', 'thu', 'dong'];
}
?>
<div class="col-12">
    <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1">
        <span><i class="bi bi-cloud-sun me-1 text-success"></i> Mùa / Thời tiết phù hợp <span class="text-muted fw-normal small">(Dùng cho Gợi ý Thực đơn Thông minh AI theo mùa)</span></span>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-success fw-bold" onclick="toggleAllSeasons()">
            <i class="bi bi-check2-all me-1"></i>Chọn cả 4 mùa (Quanh năm)
        </button>
    </label>
    <div class="p-3 bg-light rounded-3 border d-flex flex-wrap gap-4 align-items-center">
        <div class="form-check form-check-inline m-0">
            <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_xuan" value="xuan" <?php echo in_array('xuan', $currentSeasons) || in_array('all', $currentSeasons) ? 'checked' : ''; ?>>
            <label class="form-check-label fw-semibold" for="season_xuan">🌸 Mùa Xuân (Ấm áp)</label>
        </div>
        <div class="form-check form-check-inline m-0">
            <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_he" value="he" <?php echo in_array('he', $currentSeasons) || in_array('all', $currentSeasons) ? 'checked' : ''; ?>>
            <label class="form-check-label fw-semibold" for="season_he">☀️ Mùa Hè (Thanh nhiệt)</label>
        </div>
        <div class="form-check form-check-inline m-0">
            <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_thu" value="thu" <?php echo in_array('thu', $currentSeasons) || in_array('all', $currentSeasons) ? 'checked' : ''; ?>>
            <label class="form-check-label fw-semibold" for="season_thu">🍂 Mùa Thu (Mát mẻ)</label>
        </div>
        <div class="form-check form-check-inline m-0">
            <input class="form-check-input season-checkbox" type="checkbox" name="season[]" id="season_dong" value="dong" <?php echo in_array('dong', $currentSeasons) || in_array('all', $currentSeasons) ? 'checked' : ''; ?>>
            <label class="form-check-label fw-semibold" for="season_dong">❄️ Mùa Đông (Ấm bụng)</label>
        </div>
    </div>
    <div class="form-text small text-muted">Nếu chọn cả 4 mùa hoặc để trống, món ăn sẽ tự động được xếp vào thực đơn quanh năm.</div>
</div>
<?php foreach (['calories'=>'Calories','protein'=>'Protein (g)','carbs'=>'Carbs (g)','fat'=>'Fat (g)','fiber'=>'Chất xơ (g)'] as $key=>$label): ?>
<div class="col-md"><label class="form-label fw-bold"><?php echo $label; ?> <?php if($key !== 'calories'): ?><span class="text-danger">*</span><?php endif; ?></label><input type="number" min="0" step="0.01" data-clear-zero class="form-control <?php echo isset($field_errors[$key]) ? 'is-invalid' : ''; ?> <?php echo $key === 'calories' ? 'bg-light text-muted fw-bold' : ''; ?>" name="<?php echo $key; ?>" placeholder="0.00" value="<?php echo food_form_value($key, $food, ''); ?>" <?php echo $key === 'calories' ? 'readonly tabindex="-1"' : 'required'; ?>><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors[$key] ?? 'Giá trị phải lớn hơn hoặc bằng 0.'); ?></div></div>
<?php endforeach; ?>
<div class="col-12"><div class="form-text">Calories tham khảo = Protein × 4 + Carbs × 4 + Fat × 9. Nếu đơn vị là gram, tổng macros và chất xơ không được vượt khẩu phần.</div></div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary rounded-pill" href="<?php echo BASE_URL; ?>/admin/foods.php">Hủy</a><button class="btn btn-outline-success rounded-pill px-4 shadow-sm">Lưu món ăn</button></div>
</form></div></div></div></div></div>
<script>
document.querySelectorAll('[data-clear-zero]').forEach(input => {
    input.addEventListener('focus', () => { if (parseFloat(input.value) === 0 && !input.readOnly) input.value = ''; });
    input.addEventListener('blur', () => { if (input.value === '') input.value = '0.00'; });
});

const macros = document.querySelectorAll('input[name="protein"], input[name="carbs"], input[name="fat"]');
const calInput = document.querySelector('input[name="calories"]');
if (calInput && macros.length > 0) {
    const calcCals = () => {
        let p = parseFloat(document.querySelector('input[name="protein"]').value) || 0;
        let c = parseFloat(document.querySelector('input[name="carbs"]').value) || 0;
        let f = parseFloat(document.querySelector('input[name="fat"]').value) || 0;
        calInput.value = (p * 4 + c * 4 + f * 9).toFixed(2);
    };
    macros.forEach(el => el.addEventListener('input', calcCals));
}

function toggleAllSeasons() {
    const seasonCheckboxes = document.querySelectorAll('.season-checkbox');
    const allChecked = Array.from(seasonCheckboxes).every(cb => cb.checked);
    seasonCheckboxes.forEach(cb => { cb.checked = !allChecked; });
}

// Client-side validation for Food Form
const foodForm = document.getElementById('foodForm');
const foodNameInput = document.getElementById('food_name');
const categorySelect = document.getElementById('category_id');
const servingSizeInput = document.getElementById('serving_size');
const servingUnitInput = document.getElementById('serving_unit');

function validateFoodName(trigger = 'blur') {
    if (!foodNameInput) return true;
    const val = foodNameInput.value.trim();
    const err = document.getElementById('food_name_error');
    if (!val) {
        if (trigger === 'submit' || trigger === 'blur') {
            foodNameInput.classList.add('is-invalid');
            if (err) err.textContent = 'Vui lòng nhập tên món.';
            return false;
        }
        return true;
    }
    foodNameInput.classList.remove('is-invalid');
    return true;
}

function validateCategory(trigger = 'blur') {
    if (!categorySelect) return true;
    const val = categorySelect.value;
    const err = document.getElementById('category_id_error');
    if (!val) {
        if (trigger === 'submit' || trigger === 'blur') {
            categorySelect.classList.add('is-invalid');
            if (err) err.textContent = 'Vui lòng chọn danh mục.';
            return false;
        }
        return true;
    }
    categorySelect.classList.remove('is-invalid');
    return true;
}

function validateServingSize(trigger = 'blur') {
    if (!servingSizeInput) return true;
    const val = parseFloat(servingSizeInput.value);
    const err = document.getElementById('serving_size_error');
    if (isNaN(val) || val <= 0) {
        if (trigger === 'submit' || trigger === 'blur') {
            servingSizeInput.classList.add('is-invalid');
            if (err) err.textContent = 'Khẩu phần phải lớn hơn 0.';
            return false;
        }
        return true;
    }
    servingSizeInput.classList.remove('is-invalid');
    return true;
}

function validateServingUnit(trigger = 'blur') {
    if (!servingUnitInput) return true;
    const val = servingUnitInput.value.trim();
    const err = document.getElementById('serving_unit_error');
    if (!val) {
        if (trigger === 'submit' || trigger === 'blur') {
            servingUnitInput.classList.add('is-invalid');
            if (err) err.textContent = 'Vui lòng nhập đơn vị.';
            return false;
        }
        return true;
    }
    servingUnitInput.classList.remove('is-invalid');
    return true;
}

if (foodNameInput) {
    foodNameInput.addEventListener('input', () => validateFoodName('input'));
    foodNameInput.addEventListener('blur', () => validateFoodName('blur'));
}
if (categorySelect) {
    categorySelect.addEventListener('change', () => validateCategory('blur'));
}
if (servingSizeInput) {
    servingSizeInput.addEventListener('input', () => validateServingSize('input'));
    servingSizeInput.addEventListener('blur', () => validateServingSize('blur'));
}
if (servingUnitInput) {
    servingUnitInput.addEventListener('input', () => validateServingUnit('input'));
    servingUnitInput.addEventListener('blur', () => validateServingUnit('blur'));
}

if (foodForm) {
    foodForm.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();

        const isNameValid = validateFoodName('submit');
        const isCatValid = validateCategory('submit');
        const isSizeValid = validateServingSize('submit');
        const isUnitValid = validateServingUnit('submit');

        if (isNameValid && isCatValid && isSizeValid && isUnitValid) {
            foodForm.submit();
        } else {
            const firstInvalid = foodForm.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
        }
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
