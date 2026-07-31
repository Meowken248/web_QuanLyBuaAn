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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $serving_size = (float)($_POST['serving_size'] ?? 0);
    $fields = ['calories', 'protein', 'carbs', 'fat', 'fiber'];
    $nutrients = [];
    foreach ($fields as $field) $nutrients[$field] = (float)($_POST[$field] ?? 0);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ.';
    } elseif ($name === '' || !$category_id || $serving_size <= 0 || min($nutrients) < 0) {
        $error = 'Vui lòng nhập dữ liệu món ăn hợp lệ.';
    } else {
        $image_path = $food['image'] ?? null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/foods/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $filename = time() . '_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = '/uploads/foods/' . $filename;
                }
            }
        }
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
        $params = [
            ':category_id' => $category_id, ':name' => $name, ':slug' => $slug . ($id ? '-' . $id : '-' . time()),
            ':image' => $image_path,
            ':description' => trim($_POST['description'] ?? ''), 
            ':ingredients' => trim($_POST['ingredients'] ?? ''),
            ':instructions' => trim($_POST['instructions'] ?? ''),
            ':serving_size' => $serving_size,
            ':serving_unit' => trim($_POST['serving_unit'] ?? 'gram'), ':calories' => $nutrients['calories'],
            ':protein' => $nutrients['protein'], ':carbs' => $nutrients['carbs'], ':fat' => $nutrients['fat'],
            ':fiber' => $nutrients['fiber'], ':status' => $_POST['status'] === 'inactive' ? 'inactive' : 'active'
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
$page_title = $id ? 'Sửa món ăn' : 'Thêm món ăn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-8"><div class="card shadow-sm"><div class="card-body p-4">
<h3 class="fw-bold mb-4"><?php echo htmlspecialchars($page_title); ?></h3>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<div class="row g-3">
<div class="col-md-8"><label class="form-label">Tên món</label><input class="form-control" name="name" maxlength="200" value="<?php echo old('name', $food['name'] ?? ''); ?>" required></div>
<div class="col-md-4"><label class="form-label">Danh mục</label><select class="form-select" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo (string)($_POST['category_id'] ?? $food['category_id'] ?? '') === (string)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Mô tả ngắn</label><textarea class="form-control" name="description" rows="2"><?php echo old('description', $food['description'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label">Nguyên liệu</label><textarea class="form-control" name="ingredients" rows="4"><?php echo old('ingredients', $food['ingredients'] ?? ''); ?></textarea></div>
<div class="col-md-6"><label class="form-label">Cách làm</label><textarea class="form-control" name="instructions" rows="4"><?php echo old('instructions', $food['instructions'] ?? ''); ?></textarea></div>
<div class="col-12">
    <label class="form-label">Hình ảnh minh họa</label>
    <input type="file" class="form-control" name="image" accept="image/*">
    <?php if (!empty($food['image'])): ?>
        <div class="mt-2"><img src="<?php echo food_image_url($food['image']); ?>" alt="Hình ảnh" style="height: 100px; object-fit: cover; border-radius: 8px;"></div>
    <?php endif; ?>
</div>
<div class="col-md-4"><label class="form-label">Khẩu phần</label><input type="number" step="0.1" min="0.1" class="form-control" name="serving_size" value="<?php echo old('serving_size', $food['serving_size'] ?? 100); ?>" required></div>
<div class="col-md-4"><label class="form-label">Đơn vị</label><input class="form-control" name="serving_unit" value="<?php echo old('serving_unit', $food['serving_unit'] ?? 'gram'); ?>" required></div>
<div class="col-md-4"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="active">Hoạt động</option><option value="inactive" <?php echo ($_POST['status'] ?? $food['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Ẩn</option></select></div>
<?php foreach (['calories'=>'Calories','protein'=>'Protein (g)','carbs'=>'Carbs (g)','fat'=>'Fat (g)','fiber'=>'Chất xơ (g)'] as $key=>$label): ?><div class="col-md"><label class="form-label"><?php echo $label; ?></label><input type="number" min="0" step="0.1" class="form-control" name="<?php echo $key; ?>" value="<?php echo old($key, $food[$key] ?? 0); ?>" required></div><?php endforeach; ?>
</div><div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/admin/foods.php">Hủy</a><button class="btn btn-success">Lưu món ăn</button></div>
</form></div></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
