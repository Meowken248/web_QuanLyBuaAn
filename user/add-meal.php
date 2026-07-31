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
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-success"><i class="bi bi-search me-2"></i>Tìm kiếm và Thêm món ăn</h5>
                </div>
                <div class="card-body">
                    <?php display_flash_message(); ?>
                    
                    <form method="GET" action="" class="mb-4">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($meal_type); ?>">
                        
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Nhập tên món ăn..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-success" type="submit">Tìm kiếm</button>
                        </div>
                    </form>

                    <?php if (!empty($search) && empty($foods)): ?>
                        <div class="alert alert-warning">Không tìm thấy món ăn nào. <a href="<?php echo BASE_URL; ?>/foods.php">Quay lại thư viện</a>.</div>
                    <?php endif; ?>

                    <?php if (!empty($foods)): ?>
                        <div class="list-group">
                            <?php foreach ($foods as $food): ?>
                                <?php if (!$food) continue; ?>
                                <div class="list-group-item list-group-item-action p-3">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($food['name']); ?></h5>
                                            <small class="text-muted">
                                                Mặc định: <?php echo $food['serving_size']; ?> <?php echo htmlspecialchars($food['serving_unit']); ?> | 
                                                <span class="text-danger fw-bold"><?php echo $food['calories']; ?> kcal</span>
                                            </small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#addForm<?php echo $food['id']; ?>">
                                            Chọn
                                        </button>
                                    </div>
                                    
                                    <!-- Form thêm món ăn bị ẩn -->
                                    <div class="collapse mt-3" id="addForm<?php echo $food['id']; ?>">
                                        <div class="card card-body bg-light">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="add_to_meal">
                                                <input type="hidden" name="food_id" value="<?php echo $food['id']; ?>">
                                                <input type="hidden" name="log_date" value="<?php echo htmlspecialchars($date); ?>">
                                                <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($meal_type); ?>">
                                                
                                                <div class="row align-items-center">
                                                    <div class="col-md-4 mb-2">
                                                        <label class="form-label small">Số lượng</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" step="0.1" name="quantity" class="form-control meal-qty-input" 
                                                                data-base-cal="<?php echo $food['calories']; ?>" 
                                                                data-base-qty="<?php echo $food['serving_size']; ?>"
                                                                value="<?php echo $food['serving_size']; ?>" required>
                                                            <span class="input-group-text"><?php echo htmlspecialchars($food['serving_unit']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 mb-2">
                                                        <label class="form-label small">Loại bữa</label>
                                                        <select class="form-select form-select-sm" name="meal_type" disabled>
                                                            <option value="breakfast" <?php echo $meal_type == 'breakfast' ? 'selected' : ''; ?>>Bữa sáng</option>
                                                            <option value="morning_snack" <?php echo $meal_type == 'morning_snack' ? 'selected' : ''; ?>>Phụ sáng</option>
                                                            <option value="lunch" <?php echo $meal_type == 'lunch' ? 'selected' : ''; ?>>Bữa trưa</option>
                                                            <option value="afternoon_snack" <?php echo $meal_type == 'afternoon_snack' ? 'selected' : ''; ?>>Phụ chiều</option>
                                                            <option value="dinner" <?php echo $meal_type == 'dinner' ? 'selected' : ''; ?>>Bữa tối</option>
                                                            <option value="evening_snack" <?php echo $meal_type == 'evening_snack' ? 'selected' : ''; ?>>Phụ tối</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 text-end mt-3">
                                                        <strong class="text-danger d-block mb-2">Ước tính: <span class="calc-cal-display"><?php echo $food['calories']; ?></span> kcal</strong>
                                                        <button type="submit" class="btn btn-success btn-sm w-100">Thêm vào nhật ký</button>
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
            <div class="text-center">
                <a href="<?php echo BASE_URL; ?>/user/meals.php?date=<?php echo urlencode($date); ?>" class="text-secondary"><i class="bi bi-arrow-left"></i> Quay lại nhật ký</a>
            </div>
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
