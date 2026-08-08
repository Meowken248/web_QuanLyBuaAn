<?php
// user/add-meal.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/FoodModel.php';
require_once __DIR__ . '/../models/MealModel.php';
require_once __DIR__ . '/../config/database.php';

$valid_meal_types = ['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack'];
$date = $_GET['date'] ?? date('Y-m-d');
$meal_type = $_GET['type'] ?? 'breakfast';
$food_id = filter_input(INPUT_GET, 'food_id', FILTER_VALIDATE_INT) ?: null;
$search = trim($_GET['search'] ?? '');
if (!is_valid_date($date)) $date = date('Y-m-d');
if (!in_array($meal_type, $valid_meal_types, true)) $meal_type = 'breakfast';

$foodModel = new FoodModel();
$mealModel = new MealModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_meal') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
    } else {
        $fid = filter_var($_POST['food_id'] ?? null, FILTER_VALIDATE_INT);
        $qty = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $fdate = $_POST['log_date'] ?? '';
        $fmtype = $_POST['meal_type'] ?? '';

        if (!$fid || $qty === false || $qty <= 0 || !is_valid_date($fdate) || !in_array($fmtype, $valid_meal_types, true)) {
            set_flash_message('danger', 'Món ăn, số lượng, ngày hoặc loại bữa không hợp lệ.');
        } else {
            $foodInfo = $foodModel->getFoodById($fid);
            if (!$foodInfo || (float)$foodInfo['serving_size'] <= 0) {
                set_flash_message('danger', 'Không tìm thấy món ăn hoặc khẩu phần gốc không hợp lệ.');
            } else {
                $ratio = $qty / (float)$foodInfo['serving_size'];
                $meal_log_id = $mealModel->getOrCreateMealLog($_SESSION['user_id'], $fdate, $fmtype);
                $unit = trim((string)$foodInfo['serving_unit']);
                $is_gram = in_array(mb_strtolower($unit), ['g', 'gram', 'grams'], true);
                $data = [
                    ':meal_log_id' => $meal_log_id,
                    ':food_id' => $fid,
                    ':quantity' => $qty,
                    ':unit' => $unit,
                    ':calculated_grams' => $is_gram ? $qty : 0,
                    ':calories' => round((float)$foodInfo['calories'] * $ratio, 2),
                    ':protein' => round((float)$foodInfo['protein'] * $ratio, 2),
                    ':carbs' => round((float)$foodInfo['carbs'] * $ratio, 2),
                    ':fat' => round((float)$foodInfo['fat'] * $ratio, 2),
                    ':fiber' => round((float)$foodInfo['fiber'] * $ratio, 2)
                ];
                if ($meal_log_id && $mealModel->addMealItem($data)) {
                    set_flash_message('success', 'Đã thêm món ăn vào nhật ký.');
                    redirect('/user/meals.php?date=' . urlencode($fdate));
                }
                set_flash_message('danger', 'Không thể lưu món ăn vào nhật ký.');
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_custom_food') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $serving_size = filter_var($_POST['serving_size'] ?? null, FILTER_VALIDATE_FLOAT);
        $serving_unit = trim($_POST['serving_unit'] ?? '');
        $fdate = $_POST['log_date'] ?? '';
        $fmtype = $_POST['meal_type'] ?? '';
        $values = [];
        foreach (['calories', 'protein', 'carbs', 'fat', 'fiber'] as $field) {
            $raw = trim((string)($_POST[$field] ?? '0'));
            $values[$field] = filter_var($raw, FILTER_VALIDATE_FLOAT);
        }

        $custom_food_errors = [];
        if ($name === '') $custom_food_errors['name'] = 'Vui lòng nhập tên món ăn.';
        if ($serving_size === false || $serving_size <= 0) $custom_food_errors['serving_size'] = 'Khẩu phần không hợp lệ.';
        if ($serving_unit === '') $custom_food_errors['serving_unit'] = 'Vui lòng nhập đơn vị.';
        
        foreach ($values as $k => $value) {
            if ($value === false || $value < 0) $custom_food_errors[$k] = 'Giá trị không hợp lệ.';
        }
        
        $macro_total = $values['protein'] + $values['carbs'] + $values['fat'] + $values['fiber'];
        if (in_array(mb_strtolower($serving_unit), ['g', 'gram', 'grams'], true) && $macro_total > $serving_size + 0.01) {
            $custom_food_errors['serving_size'] = 'Tổng macros và chất xơ không vượt quá khẩu phần.';
        }
        $estimated_calories = $values['protein'] * 4 + $values['carbs'] * 4 + $values['fat'] * 9;
        if ($estimated_calories > 0 && abs($values['calories'] - $estimated_calories) > max(20, $estimated_calories * 0.2)) {
            $custom_food_errors['calories'] = 'Calories lệch nhiều so với macros (ước tính ' . round($estimated_calories, 1) . ' kcal).';
        }

        if (!empty($custom_food_errors)) {
            $show_custom_food_modal = true;
            if (empty($_SESSION['flash_message'])) set_flash_message('danger', 'Dữ liệu không hợp lệ. Vui lòng kiểm tra các ô báo đỏ.');
        } else {
            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
                $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
                if (isset($extensions[$mime])) {
                    $upload_dir = __DIR__ . '/../uploads/foods/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) $image_path = '/uploads/foods/' . $filename;
                }
            }

            $conn = (new Database())->getConnection();
            $conn->beginTransaction();
            try {
                $stmt_cat = $conn->query("SELECT id FROM food_categories ORDER BY id ASC LIMIT 1");
                $default_category_id = $stmt_cat->fetchColumn() ?: 1;

                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-')) . '-' . time();
                $stmt = $conn->prepare("INSERT INTO foods (category_id,name,slug,image,ingredients,instructions,serving_size,serving_unit,calories,protein,carbs,fat,fiber,status,created_by) VALUES (:category_id,:name,:slug,:image,:ingredients,:instructions,:serving_size,:serving_unit,:calories,:protein,:carbs,:fat,:fiber,'active',:created_by)");
                $stmt->execute([
                    ':category_id'=>$default_category_id, ':name'=>$name, ':slug'=>$slug, ':image'=>$image_path,
                    ':ingredients'=>trim($_POST['ingredients'] ?? ''), ':instructions'=>trim($_POST['instructions'] ?? ''),
                    ':serving_size'=>$serving_size, ':serving_unit'=>$serving_unit,
                    ':calories'=>$values['calories'], ':protein'=>$values['protein'], ':carbs'=>$values['carbs'],
                    ':fat'=>$values['fat'], ':fiber'=>$values['fiber'], ':created_by'=>(int)$_SESSION['user_id']
                ]);
                $new_food_id = (int)$conn->lastInsertId();
                
                // Commit ngay lập tức để giải phóng khóa (lock) trên bảng foods.
                // Tránh lỗi Deadlock/Hang do MealModel sử dụng một kết nối CSDL khác.
                $conn->commit();

                $meal_log_id = $mealModel->getOrCreateMealLog($_SESSION['user_id'], $fdate, $fmtype);
                if (!$new_food_id || !$meal_log_id || !$mealModel->addMealItem([
                    ':meal_log_id'=>$meal_log_id, ':food_id'=>$new_food_id, ':quantity'=>$serving_size,
                    ':unit'=>$serving_unit, ':calculated_grams'=>in_array(mb_strtolower($serving_unit), ['g','gram','grams'], true) ? $serving_size : 0,
                    ':calories'=>$values['calories'], ':protein'=>$values['protein'], ':carbs'=>$values['carbs'],
                    ':fat'=>$values['fat'], ':fiber'=>$values['fiber']
                ])) throw new RuntimeException('Không thể thêm món vào nhật ký.');
                
                set_flash_message('success', 'Đã tạo món ăn mới và thêm vào nhật ký.');
                redirect('/user/meals.php?date=' . urlencode($fdate));
            } catch (Throwable $e) {
                try {
                    if (isset($conn) && $conn->inTransaction()) {
                        $conn->rollBack();
                    }
                } catch (Throwable $e2) {
                    // Bỏ qua lỗi khi rollback (ví dụ: mất kết nối)
                }
                error_log("Lỗi tạo món ăn: " . $e->getMessage());
                set_flash_message('danger', 'Không thể tạo món ăn: ' . $e->getMessage());
                $show_custom_food_modal = true;
            }
        }
    }
}

$foods = [];
if (!empty($search)) {
    $foods = $foodModel->getFoods(20, 0, $search); // Lấy tối đa 20 kết quả
} elseif ($food_id) {
    // Nếu chọn món trực tiếp từ trang thư viện món ăn
    $foods = [$foodModel->getFoodById($food_id)];
}

$page_title = 'Thêm món ăn';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card glass-panel border-0 mb-4" data-aos="fade-up">
                <div class="card-header bg-white bg-opacity-75 p-4 border-0 d-flex justify-content-between align-items-center rounded-top-4">
                    <h5 class="mb-0 fw-bold text-success d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="bi bi-search"></i>
                        </div>
                        Tìm kiếm và Thêm món ăn
                    </h5>
                    <!-- Nút Mở Modal Tạo món mới -->
                    <button type="button" class="btn btn-outline-success rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createFoodModal">
                        <i class="bi bi-plus-circle me-1"></i>Tự tạo món ăn
                    </button>
                </div>
                <div class="card-body p-4 p-lg-5">
                    <?php display_flash_message(); ?>
                    
                    <form method="GET" action="" class="mb-4">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($meal_type); ?>">
                        
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg bg-light border-0" name="search" placeholder="Nhập tên món ăn..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-success btn-glow px-4" type="submit"><i class="bi bi-search me-2"></i>Tìm kiếm</button>
                        </div>
                    </form>

                    <?php if (!empty($search) && empty($foods)): ?>
                        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center rounded-3">
                            <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
                            <div>Không tìm thấy món ăn nào. Bạn có thể <a href="#" data-bs-toggle="modal" data-bs-target="#createFoodModal" class="fw-bold text-warning text-decoration-none">Tự tạo món mới</a>.</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($foods)): ?>
                        <div class="list-group border-0 shadow-sm rounded-4 overflow-hidden">
                            <?php foreach ($foods as $food): ?>
                                <?php if (!$food) continue; ?>
                                <div class="list-group-item list-group-item-action p-4 border-bottom border-light">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($food['image'])): ?>
                                                <img src="<?php echo food_image_url($food['image']); ?>" class="rounded-3 me-3 shadow-sm" style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded-3 d-flex align-items-center justify-content-center me-3 text-muted shadow-sm" style="width: 60px; height: 60px;">
                                                    <i class="bi bi-image fs-4"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h5 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($food['name']); ?></h5>
                                                <small class="text-muted d-block mt-1">
                                                    Mặc định: <?php echo $food['serving_size']; ?> <?php echo htmlspecialchars($food['serving_unit']); ?> | 
                                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2"><?php echo $food['calories']; ?> kcal</span>
                                                </small>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm btn-glow" data-bs-toggle="collapse" data-bs-target="#addForm<?php echo $food['id']; ?>">
                                            Chọn
                                        </button>
                                    </div>
                                    
                                    <!-- Form thêm món ăn bị ẩn -->
                                    <div class="collapse mt-3" id="addForm<?php echo $food['id']; ?>">
                                        <div class="card card-body bg-light border-0 rounded-4 p-4 mt-3 shadow-sm">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="add_to_meal">
                                                <input type="hidden" name="food_id" value="<?php echo $food['id']; ?>">
                                                <input type="hidden" name="log_date" value="<?php echo htmlspecialchars($date); ?>">
                                                <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
                                                
                                                <div class="row align-items-center g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted fw-bold">Số lượng</label>
                                                        <div class="input-group">
                                                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control bg-white border-0 meal-qty-input" 
                                                                data-base-cal="<?php echo $food['calories']; ?>" 
                                                                data-base-qty="<?php echo $food['serving_size']; ?>"
                                                                value="<?php echo $food['serving_size']; ?>" required>
                                                            <span class="input-group-text bg-white border-0 text-muted"><?php echo htmlspecialchars($food['serving_unit']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted fw-bold">Loại bữa</label>
                                                        <select class="form-select bg-white border-0" name="meal_type" disabled>
                                                            <option value="breakfast" <?php echo $meal_type == 'breakfast' ? 'selected' : ''; ?>>Bữa sáng</option>
                                                            <option value="morning_snack" <?php echo $meal_type == 'morning_snack' ? 'selected' : ''; ?>>Phụ sáng</option>
                                                            <option value="lunch" <?php echo $meal_type == 'lunch' ? 'selected' : ''; ?>>Bữa trưa</option>
                                                            <option value="afternoon_snack" <?php echo $meal_type == 'afternoon_snack' ? 'selected' : ''; ?>>Phụ chiều</option>
                                                            <option value="dinner" <?php echo $meal_type == 'dinner' ? 'selected' : ''; ?>>Bữa tối</option>
                                                            <option value="evening_snack" <?php echo $meal_type == 'evening_snack' ? 'selected' : ''; ?>>Phụ tối</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <span class="text-danger d-block mb-2 small fw-bold">Ước tính: <span class="calc-cal-display fs-5"><?php echo $food['calories']; ?></span> kcal</span>
                                                        <button type="submit" class="btn btn-success btn-sm w-100 rounded-pill fw-bold shadow-sm">Lưu vào nhật ký</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                <a href="<?php echo BASE_URL; ?>/user/meals.php?date=<?php echo urlencode($date); ?>" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm"><i class="bi bi-arrow-left me-2"></i>Quay lại nhật ký</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tạo món ăn tùy chỉnh -->
<div class="modal fade" id="createFoodModal" tabindex="-1" aria-labelledby="createFoodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-header bg-success bg-opacity-10 border-0 p-4">
                    <h5 class="modal-title text-success fw-bold d-flex align-items-center" id="createFoodModalLabel">
                        <i class="bi bi-plus-circle me-2 fs-4"></i>Tạo món ăn của riêng bạn
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 p-lg-5">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create_custom_food">
                    <input type="hidden" name="log_date" value="<?php echo htmlspecialchars($date); ?>">
                    <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label text-muted fw-bold">Tên món ăn <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg bg-light <?php echo isset($custom_food_errors['name']) ? 'is-invalid border-danger' : 'border-0'; ?>" name="name" required placeholder="VD: Cơm tấm sườn sành điệu..." value="<?php echo old('name'); ?>">
                            <?php if(isset($custom_food_errors['name'])): ?><div class="invalid-feedback"><?php echo $custom_food_errors['name']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">Tải ảnh lên (Tùy chọn)</label>
                            <input type="file" class="form-control bg-light border-0" name="image" accept="image/*">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-bold">Khẩu phần <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control bg-light <?php echo isset($custom_food_errors['serving_size']) ? 'is-invalid border-danger' : 'border-0'; ?>" name="serving_size" value="<?php echo old('serving_size', '100'); ?>" required>
                            <?php if(isset($custom_food_errors['serving_size'])): ?><div class="invalid-feedback"><?php echo $custom_food_errors['serving_size']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-bold">Đơn vị <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light <?php echo isset($custom_food_errors['serving_unit']) ? 'is-invalid border-danger' : 'border-0'; ?>" name="serving_unit" value="<?php echo old('serving_unit', 'gram'); ?>" required>
                            <?php if(isset($custom_food_errors['serving_unit'])): ?><div class="invalid-feedback"><?php echo $custom_food_errors['serving_unit']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">Nguyên liệu</label>
                            <textarea class="form-control bg-light border-0" name="ingredients" rows="3" placeholder="VD: 100g sườn, 1 muỗng mật ong..."><?php echo old('ingredients'); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">Cách làm</label>
                            <textarea class="form-control bg-light border-0" name="instructions" rows="3" placeholder="VD: Ướp sườn trong 30 phút, nướng ở 180 độ..."><?php echo old('instructions'); ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <hr class="text-muted opacity-25">
                            <h6 class="text-success fw-bold mb-3"><i class="bi bi-fire me-2"></i>Thành phần dinh dưỡng (trên khẩu phần)</h6>
                        </div>
                        
                        <?php foreach (['calories'=>'Calories ','protein'=>'Protein (g)','carbs'=>'Carbs (g)','fat'=>'Fat (g)','fiber'=>'Chất xơ (g)'] as $field => $label): ?>
                        <div class="col">
                            <label class="form-label text-muted fw-bold"><?php echo $label; ?> <?php if($field !== 'calories'): ?><span class="text-danger">*</span><?php endif; ?></label>
                            <input type="number" step="0.01" min="0" data-clear-zero class="form-control bg-light <?php echo isset($custom_food_errors[$field]) ? 'is-invalid border-danger' : 'border-0'; ?> <?php echo $field === 'calories' ? 'text-muted fw-bold shadow-none' : ''; ?>" name="<?php echo $field; ?>" placeholder="0.00" value="<?php echo old($field); ?>" <?php echo $field === 'calories' ? 'readonly tabindex="-1"' : 'required'; ?>>
                            <?php if(isset($custom_food_errors[$field])): ?><div class="invalid-feedback"><?php echo $custom_food_errors[$field]; ?></div><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-12"><div class="form-text">Calories = Protein × 4 + Carbs × 4 + Fat × 9. Tổng macros và chất xơ không được vượt khẩu phần nếu tính theo gram.</div></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success btn-glow rounded-pill px-4 fw-bold">Lưu và Thêm vào nhật ký</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

    // Tự động tính toán calories khi thay đổi số lượng
    const qtyInputs = document.querySelectorAll('.meal-qty-input');
    qtyInputs.forEach(input => {
        input.addEventListener('input', function() {
            let baseCal = parseFloat(this.getAttribute('data-base-cal'));
            let baseQty = parseFloat(this.getAttribute('data-base-qty'));
            let currentQty = parseFloat(this.value);
            
            if (!isNaN(currentQty) && currentQty > 0) {
                let calcCal = (baseCal * currentQty) / baseQty;
                // Tìm element hiển thị cal
                let displayObj = this.closest('.row').querySelector('.calc-cal-display');
                if(displayObj) displayObj.textContent = calcCal.toFixed(1);
            }
        });
    });
});
</script>

<?php if (!empty($show_custom_food_modal)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var createFoodModal = new bootstrap.Modal(document.getElementById('createFoodModal'));
    createFoodModal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
