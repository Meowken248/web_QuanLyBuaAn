<?php
// admin/meal-plan-builder.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) redirect('/admin/meal-plans.php');

$stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = :id");
$stmt->execute([':id' => $id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plan) redirect('/admin/meal-plans.php');

// Hàm tính lại tổng dinh dưỡng cho Kế hoạch
function recalculateMealPlan($conn, $plan_id) {
    $stmt = $conn->prepare("
        SELECT SUM(calories) as t_cal, SUM(protein) as t_pro, SUM(carbs) as t_car, SUM(fat) as t_fat, SUM(fiber) as t_fib
        FROM meal_plan_items mpi
        JOIN meal_plan_meals mpm ON mpi.meal_plan_meal_id = mpm.id
        WHERE mpm.meal_plan_id = :id
    ");
    $stmt->execute([':id' => $plan_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $upd = $conn->prepare("UPDATE meal_plans SET total_calories=:cal, total_protein=:pro, total_carbs=:car, total_fat=:fat, total_fiber=:fib WHERE id=:id");
    $upd->execute([
        ':cal' => $totals['t_cal'] ?: 0,
        ':pro' => $totals['t_pro'] ?: 0,
        ':car' => $totals['t_car'] ?: 0,
        ':fat' => $totals['t_fat'] ?: 0,
        ':fib' => $totals['t_fib'] ?: 0,
        ':id' => $plan_id
    ]);
}

// Xử lý POST (Thêm bữa ăn, Thêm món ăn, Xóa)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die('CSRF token error');
    
    if ($_POST['action'] === 'add_meal') {
        $type = $_POST['meal_type'];
        $title = trim($_POST['title']);
        $stmt = $conn->prepare("INSERT INTO meal_plan_meals (meal_plan_id, meal_type, title, sort_order) VALUES (:pid, :type, :title, 0)");
        $stmt->execute([':pid' => $id, ':type' => $type, ':title' => $title]);
        $_SESSION['success'] = 'Đã thêm bữa ăn mới.';
        
    } elseif ($_POST['action'] === 'delete_meal') {
        $meal_id = (int)$_POST['meal_id'];
        $conn->prepare("DELETE FROM meal_plan_items WHERE meal_plan_meal_id = :mid")->execute([':mid' => $meal_id]);
        $conn->prepare("DELETE FROM meal_plan_meals WHERE id = :mid AND meal_plan_id = :pid")->execute([':mid' => $meal_id, ':pid' => $id]);
        recalculateMealPlan($conn, $id);
        $_SESSION['success'] = 'Đã xóa bữa ăn.';
        
    } elseif ($_POST['action'] === 'add_item') {
        $meal_id = (int)$_POST['meal_id'];
        $food_id = (int)$_POST['food_id'];
        $quantity = (float)$_POST['quantity'];
        
        // Tính toán dinh dưỡng từ food
        $fStmt = $conn->prepare("SELECT * FROM foods WHERE id = :fid");
        $fStmt->execute([':fid' => $food_id]);
        $food = $fStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($food && $quantity > 0) {
            $ratio = $quantity / 100;
            $cal = $food['calories'] * $ratio;
            $pro = $food['protein'] * $ratio;
            $car = $food['carbs'] * $ratio;
            $fat = $food['fat'] * $ratio;
            $fib = $food['fiber'] * $ratio;
            
            $stmt = $conn->prepare("INSERT INTO meal_plan_items (meal_plan_meal_id, food_id, quantity, unit, calculated_grams, calories, protein, carbs, fat, fiber, sort_order) 
                                    VALUES (:mid, :fid, :qty, :unit, :grams, :cal, :pro, :car, :fat, :fib, 0)");
            $stmt->execute([
                ':mid' => $meal_id, ':fid' => $food_id, ':qty' => $quantity, ':unit' => 'g', ':grams' => $quantity,
                ':cal' => $cal, ':pro' => $pro, ':car' => $car, ':fat' => $fat, ':fib' => $fib
            ]);
            recalculateMealPlan($conn, $id);
            $_SESSION['success'] = 'Đã thêm món ăn vào bữa.';
        }
        
    } elseif ($_POST['action'] === 'delete_item') {
        $item_id = (int)$_POST['item_id'];
        // Xác minh item này thuộc về plan này
        $stmt = $conn->prepare("DELETE mpi FROM meal_plan_items mpi JOIN meal_plan_meals mpm ON mpi.meal_plan_meal_id = mpm.id WHERE mpi.id = :iid AND mpm.meal_plan_id = :pid");
        $stmt->execute([':iid' => $item_id, ':pid' => $id]);
        recalculateMealPlan($conn, $id);
        $_SESSION['success'] = 'Đã xóa món ăn khỏi bữa.';
    }
    
    redirect("/admin/meal-plan-builder.php?id=$id");
}

// Lấy danh sách meals và items
$stmtMeals = $conn->prepare("SELECT * FROM meal_plan_meals WHERE meal_plan_id = :id ORDER BY FIELD(meal_type, 'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack'), id ASC");
$stmtMeals->execute([':id' => $id]);
$meals = $stmtMeals->fetchAll(PDO::FETCH_ASSOC);

$meal_types = [
    'breakfast' => 'Bữa sáng',
    'morning_snack' => 'Bữa phụ sáng',
    'lunch' => 'Bữa trưa',
    'afternoon_snack' => 'Bữa phụ chiều',
    'dinner' => 'Bữa tối',
    'evening_snack' => 'Bữa phụ tối'
];

// Lấy danh sách Food cho select dropdown
$stmtFoods = $conn->query("SELECT id, name, calories FROM foods ORDER BY name ASC");
$foods = $stmtFoods->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'meal-plans.php';
$page_title = 'Xây dựng Thực đơn: ' . htmlspecialchars($plan['name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Xây dựng Thực đơn</h2>
                    <h5 class="text-success"><?php echo htmlspecialchars($plan['name']); ?></h5>
                </div>
                <a href="<?php echo BASE_URL; ?>/admin/meal-plans.php" class="btn btn-outline-secondary">Quay lại danh sách</a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Bảng tổng hợp Dinh dưỡng của toàn Thực đơn -->
            <div class="card shadow-sm border-0 mb-4 bg-success text-white">
                <div class="card-body py-3">
                    <div class="row text-center">
                        <div class="col-md-2 border-end border-light border-opacity-25">
                            <h6 class="mb-1 text-white-50">Tổng Calories</h6>
                            <h3 class="mb-0 fw-bold"><?php echo round($plan['total_calories'] ?? 0); ?></h3>
                        </div>
                        <div class="col-md-2 border-end border-light border-opacity-25">
                            <h6 class="mb-1 text-white-50">Protein</h6>
                            <h4 class="mb-0 fw-bold"><?php echo round($plan['total_protein'] ?? 0); ?>g</h4>
                        </div>
                        <div class="col-md-2 border-end border-light border-opacity-25">
                            <h6 class="mb-1 text-white-50">Carbs</h6>
                            <h4 class="mb-0 fw-bold"><?php echo round($plan['total_carbs'] ?? 0); ?>g</h4>
                        </div>
                        <div class="col-md-2 border-end border-light border-opacity-25">
                            <h6 class="mb-1 text-white-50">Fat</h6>
                            <h4 class="mb-0 fw-bold"><?php echo round($plan['total_fat'] ?? 0); ?>g</h4>
                        </div>
                        <div class="col-md-4 text-start ps-4">
                            <h6 class="mb-1">Loại: <span class="badge bg-light text-success ms-2"><?php echo htmlspecialchars($plan['diet_type']); ?></span></h6>
                            <h6 class="mb-0">Mục tiêu: <span class="badge bg-light text-success ms-2"><?php echo $plan['goal_type']; ?></span></h6>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Danh sách các bữa ăn -->
                <div class="col-lg-8">
                    <?php foreach ($meals as $m): ?>
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0 text-primary">
                                    <i class="bi bi-clock me-2"></i><?php echo $meal_types[$m['meal_type']] ?? $m['meal_type']; ?> 
                                    - <?php echo htmlspecialchars($m['title']); ?>
                                </h5>
                                <form method="POST" class="m-0" onsubmit="return confirm('Bạn có chắc muốn xóa bữa ăn này cùng các món ăn bên trong?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="delete_meal">
                                    <input type="hidden" name="meal_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa bữa</button>
                                </form>
                            </div>
                            <div class="card-body p-0">
                                <?php
                                    $stmtItems = $conn->prepare("SELECT mpi.*, f.name, f.image FROM meal_plan_items mpi JOIN foods f ON mpi.food_id = f.id WHERE mpi.meal_plan_meal_id = :mid ORDER BY mpi.id ASC");
                                    $stmtItems->execute([':mid' => $m['id']]);
                                    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <table class="table table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Món ăn</th>
                                            <th>Khối lượng</th>
                                            <th>Calories</th>
                                            <th>Protein</th>
                                            <th class="text-end">Xóa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $meal_cal = 0;
                                            foreach ($items as $item): 
                                                $meal_cal += $item['calories'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                            </td>
                                            <td><?php echo round($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                            <td class="text-success fw-bold"><?php echo round($item['calories']); ?> kcal</td>
                                            <td><?php echo round($item['protein'], 1); ?>g</td>
                                            <td class="text-end">
                                                <form method="POST" class="m-0" onsubmit="return confirm('Xóa món này?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-light text-danger"><i class="bi bi-x-circle fs-5"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($items)): ?>
                                            <tr><td colspan="5" class="text-center py-3 text-muted">Chưa có món ăn nào trong bữa này.</td></tr>
                                        <?php endif; ?>
                                        <tr class="bg-light">
                                            <td colspan="5" class="py-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="fw-bold text-dark">Tổng cộng bữa này: <span class="text-success ms-2 fs-5"><?php echo round($meal_cal); ?> kcal</span></div>
                                                    
                                                    <!-- Nút mở form thêm món -->
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal<?php echo $m['id']; ?>">
                                                        <i class="bi bi-plus"></i> Thêm món ăn
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Modal Thêm Món Ăn -->
                        <div class="modal fade" id="addItemModal<?php echo $m['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">Thêm món vào <?php echo htmlspecialchars($m['title']); ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="add_item">
                                            <input type="hidden" name="meal_id" value="<?php echo $m['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Chọn món ăn từ thư viện</label>
                                                <!-- Sử dụng Select2 nếu có, ở đây dùng select thường -->
                                                <select class="form-select" name="food_id" required>
                                                    <option value="">-- Chọn món ăn --</option>
                                                    <?php foreach ($foods as $f): ?>
                                                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?> (<?php echo round($f['calories']); ?> kcal/100g)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Khối lượng (gram)</label>
                                                <input type="number" class="form-control" name="quantity" min="1" max="5000" value="100" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                            <button type="submit" class="btn btn-primary">Lưu món ăn</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($meals)): ?>
                        <div class="alert alert-info py-5 text-center">
                            <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                            <h5 class="fw-bold">Thực đơn chưa có bữa ăn nào.</h5>
                            <p class="mb-0">Hãy sử dụng form bên phải để thêm Bữa sáng, Bữa trưa, Bữa tối...</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Form tạo bữa ăn mới -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 position-sticky" style="top: 20px;">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0">Thêm Bữa ăn Mới</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="add_meal">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Loại bữa ăn</label>
                                    <select class="form-select" name="meal_type" id="meal_type_select">
                                        <?php foreach ($meal_types as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Tên gợi nhớ (Tùy chọn)</label>
                                    <input type="text" class="form-control" name="title" id="meal_title_input" value="Bữa sáng" required placeholder="VD: Bữa sáng nhẹ">
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100 fw-bold">
                                    <i class="bi bi-plus-circle"></i> Tạo Bữa Ăn
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const typeSelect = document.getElementById('meal_type_select');
    const titleInput = document.getElementById('meal_title_input');
    
    typeSelect.addEventListener('change', function() {
        const text = this.options[this.selectedIndex].text;
        titleInput.value = text;
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
