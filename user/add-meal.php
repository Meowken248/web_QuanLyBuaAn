<?php
// user/add-meal.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/FoodModel.php';
require_once __DIR__ . '/../models/MealModel.php';

$date = $_GET['date'] ?? date('Y-m-d');
$meal_type = $_GET['type'] ?? 'breakfast';
$food_id = $_GET['food_id'] ?? null;
$search = $_GET['search'] ?? '';

$foodModel = new FoodModel();
$mealModel = new MealModel();

// Nếu user nhấn thêm món ăn (POST submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_meal') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $fid = (int)$_POST['food_id'];
        $qty = (float)$_POST['quantity'];
        $fdate = $_POST['log_date'];
        $fmtype = $_POST['meal_type'];
        
        $foodInfo = $foodModel->getFoodById($fid);
        if ($foodInfo) {
            // Tính toán tỷ lệ
            $ratio = $qty / $foodInfo['serving_size'];
            
            // Tìm hoặc tạo Meal Log ID
            $meal_log_id = $mealModel->getOrCreateMealLog($_SESSION['user_id'], $fdate, $fmtype);
            
            if ($meal_log_id) {
                // Giả định serving_unit = 'gram' cho calculated_grams, nếu khác cần có logic chuyển đổi
                // Tuy nhiên theo prompt: Hệ thống tự tính lại calories và chất dinh dưỡng
                $calc_grams = ($foodInfo['serving_unit'] == 'gram') ? $qty : ($qty * 100); // Tạm tính
                
                $data = [
                    ':meal_log_id' => $meal_log_id,
                    ':food_id' => $fid,
                    ':quantity' => $qty,
                    ':unit' => $foodInfo['serving_unit'],
                    ':calculated_grams' => $calc_grams,
                    ':calories' => round($foodInfo['calories'] * $ratio, 1),
                    ':protein' => round($foodInfo['protein'] * $ratio, 1),
                    ':carbs' => round($foodInfo['carbs'] * $ratio, 1),
                    ':fat' => round($foodInfo['fat'] * $ratio, 1),
                    ':fiber' => round($foodInfo['fiber'] * $ratio, 1)
                ];
                
                if ($mealModel->addMealItem($data)) {
                    set_flash_message('success', 'Đã thêm món ăn vào nhật ký!');
                    redirect('/user/meals.php?date=' . urlencode($fdate));
                } else {
                    set_flash_message('danger', 'Có lỗi xảy ra khi lưu.');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_custom_food') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $name = trim($_POST['name'] ?? '');
        $calories = (float)($_POST['calories'] ?? 0);
        $protein = (float)($_POST['protein'] ?? 0);
        $carbs = (float)($_POST['carbs'] ?? 0);
        $fat = (float)($_POST['fat'] ?? 0);
        $serving_size = (float)($_POST['serving_size'] ?? 100);
        $serving_unit = trim($_POST['serving_unit'] ?? 'gram');
        $fdate = $_POST['log_date'] ?? date('Y-m-d');
        $fmtype = $_POST['meal_type'] ?? 'breakfast';
        
        if ($name === '' || $serving_size <= 0) {
            set_flash_message('danger', 'Vui lòng nhập tên món và khẩu phần hợp lệ.');
        } else {
            $image_path = null;
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
            
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-')) . '-' . time();
            
            $conn = (new Database())->getConnection();
            $stmt = $conn->prepare("INSERT INTO foods (category_id, name, slug, image, serving_size, serving_unit, calories, protein, carbs, fat, fiber, status, created_by) VALUES (NULL, :name, :slug, :image, :serving_size, :serving_unit, :calories, :protein, :carbs, :fat, 0, 'active', :created_by)");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':image' => $image_path,
                ':serving_size' => $serving_size,
                ':serving_unit' => $serving_unit,
                ':calories' => $calories,
                ':protein' => $protein,
                ':carbs' => $carbs,
                ':fat' => $fat,
                ':created_by' => (int)$_SESSION['user_id']
            ]);
            $new_food_id = $conn->lastInsertId();
            
            if ($new_food_id) {
                // Tự động thêm vào nhật ký luôn
                $meal_log_id = $mealModel->getOrCreateMealLog($_SESSION['user_id'], $fdate, $fmtype);
                if ($meal_log_id) {
                    $calc_grams = ($serving_unit == 'gram') ? $serving_size : ($serving_size * 100);
                    $data = [
                        ':meal_log_id' => $meal_log_id,
                        ':food_id' => $new_food_id,
                        ':quantity' => $serving_size,
                        ':unit' => $serving_unit,
                        ':calculated_grams' => $calc_grams,
                        ':calories' => $calories,
                        ':protein' => $protein,
                        ':carbs' => $carbs,
                        ':fat' => $fat,
                        ':fiber' => 0
                    ];
                    $mealModel->addMealItem($data);
                }
                set_flash_message('success', 'Đã tạo món ăn mới và thêm vào nhật ký!');
                redirect('/user/meals.php?date=' . urlencode($fdate));
            } else {
                set_flash_message('danger', 'Lỗi khi tạo món ăn.');
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
                                                            <input type="number" step="0.1" name="quantity" class="form-control bg-white border-0 meal-qty-input" 
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
                            <input type="text" class="form-control form-control-lg bg-light border-0" name="name" required placeholder="VD: Cơm tấm sườn sành điệu...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">Tải ảnh lên (Tùy chọn)</label>
                            <input type="file" class="form-control bg-light border-0" name="image" accept="image/*">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-bold">Khẩu phần <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" class="form-control bg-light border-0" name="serving_size" value="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted fw-bold">Đơn vị <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light border-0" name="serving_unit" value="gram" required>
                        </div>
                        
                        <div class="col-12">
                            <hr class="text-muted opacity-25">
                            <h6 class="text-success fw-bold mb-3"><i class="bi bi-fire me-2"></i>Thành phần dinh dưỡng (trên khẩu phần)</h6>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label text-danger fw-bold">Calories <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" class="form-control bg-danger bg-opacity-10 border-0 text-danger" name="calories" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-warning fw-bold">Protein (g)</label>
                            <input type="number" step="0.1" class="form-control bg-warning bg-opacity-10 border-0 text-warning" name="protein" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-info fw-bold">Carbs (g)</label>
                            <input type="number" step="0.1" class="form-control bg-info bg-opacity-10 border-0 text-info" name="carbs" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-primary fw-bold">Fat (g)</label>
                            <input type="number" step="0.1" class="form-control bg-primary bg-opacity-10 border-0 text-primary" name="fat" value="0">
                        </div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
