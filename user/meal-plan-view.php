<?php
// user/meal-plan-view.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) redirect('/user/meal-plans.php');

$stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = :id AND status = 'active'");
$stmt->execute([':id' => $id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    $_SESSION['error'] = 'Thực đơn không tồn tại hoặc đã bị ẩn.';
    redirect('/user/meal-plans.php');
}

// Nếu là Premium, có thể yêu cầu user là VIP (nếu hệ thống có phân quyền). Ở đây tạm thời hiển thị badge thôi.

// Check yêu thích
$stmtFav = $conn->prepare("SELECT 1 FROM favorite_meal_plans WHERE user_id = :uid AND meal_plan_id = :pid");
$stmtFav->execute([':uid' => $user_id, ':pid' => $id]);
$isFav = $stmtFav->fetchColumn() ? true : false;

// Lấy danh sách Meals & Items
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

$page_title = htmlspecialchars($plan['name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header Thực Đơn -->
    <div class="row align-items-center mb-4 bg-white rounded shadow-sm p-4 border-start border-success border-5">
        <div class="col-md-3 text-center mb-3 mb-md-0">
            <?php if ($plan['image']): ?>
                <img src="<?php echo BASE_URL . '/uploads/meal_plans/' . $plan['image']; ?>" class="img-fluid rounded shadow-sm" style="max-height: 200px; object-fit: cover;">
            <?php else: ?>
                <div class="bg-success bg-opacity-10 rounded d-flex align-items-center justify-content-center" style="height: 150px;">
                    <i class="bi bi-journal-check text-success" style="font-size: 4rem;"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="mb-2">
                        <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($plan['diet_type']); ?></span>
                        <span class="badge bg-info text-dark me-1"><?php echo $plan['goal_type']; ?></span>
                        <?php if ($plan['is_premium']): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Premium</span>
                        <?php endif; ?>
                    </div>
                    <h2 class="fw-bold mb-2"><?php echo htmlspecialchars($plan['name']); ?></h2>
                    <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($plan['description'] ?: 'Thực đơn dinh dưỡng chuẩn bị sẵn cho bạn.')); ?></p>
                </div>
                <div>
                    <form method="POST" action="<?php echo BASE_URL; ?>/user/meal-plans.php">
                        <input type="hidden" name="action" value="toggle_favorite">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                        <button type="submit" class="btn <?php echo $isFav ? 'btn-danger' : 'btn-outline-danger'; ?> rounded-circle" style="width: 45px; height: 45px;" title="<?php echo $isFav ? 'Bỏ yêu thích' : 'Yêu thích'; ?>">
                            <i class="bi <?php echo $isFav ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <hr>
            
            <div class="row text-center g-2">
                <div class="col-6 col-md-3">
                    <div class="bg-light rounded p-2 border">
                        <h6 class="mb-1 text-muted small">Calories</h6>
                        <h4 class="mb-0 fw-bold text-success"><?php echo round($plan['total_calories']); ?></h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bg-light rounded p-2 border">
                        <h6 class="mb-1 text-muted small">Protein</h6>
                        <h4 class="mb-0 fw-bold"><?php echo round($plan['total_protein']); ?>g</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bg-light rounded p-2 border">
                        <h6 class="mb-1 text-muted small">Carbs</h6>
                        <h4 class="mb-0 fw-bold"><?php echo round($plan['total_carbs']); ?>g</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bg-light rounded p-2 border">
                        <h6 class="mb-1 text-muted small">Fat</h6>
                        <h4 class="mb-0 fw-bold"><?php echo round($plan['total_fat']); ?>g</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chi tiết Bữa ăn -->
    <h4 class="fw-bold mb-3">Chi tiết Thực đơn</h4>
    
    <div class="row">
        <?php foreach ($meals as $m): ?>
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-success text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-clock me-2"></i><?php echo $meal_types[$m['meal_type']] ?? $m['meal_type']; ?>
                            <small class="fw-normal ms-2 opacity-75">- <?php echo htmlspecialchars($m['title']); ?></small>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php
                            $stmtItems = $conn->prepare("SELECT mpi.*, f.name, f.image FROM meal_plan_items mpi JOIN foods f ON mpi.food_id = f.id WHERE mpi.meal_plan_meal_id = :mid ORDER BY mpi.id ASC");
                            $stmtItems->execute([':mid' => $m['id']]);
                            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <ul class="list-group list-group-flush">
                            <?php 
                                $meal_cal = 0;
                                foreach ($items as $item): 
                                    $meal_cal += $item['calories'];
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <div class="small text-muted">Khẩu phần: <?php echo round($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success rounded-pill px-3 py-2"><?php echo round($item['calories']); ?> kcal</span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            
                            <?php if (empty($items)): ?>
                                <li class="list-group-item text-center py-4 text-muted">Chưa có thông tin món ăn.</li>
                            <?php endif; ?>
                            
                            <li class="list-group-item bg-light text-end">
                                <strong>Tổng bữa:</strong> <span class="text-success fs-5 fw-bold ms-2"><?php echo round($meal_cal); ?> kcal</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($meals)): ?>
            <div class="col-12 text-center py-5 bg-white rounded shadow-sm">
                <i class="bi bi-calendar-x text-muted mb-3" style="font-size: 3rem;"></i>
                <h5 class="text-muted">Thực đơn này đang được cập nhật, vui lòng quay lại sau!</h5>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="text-center mt-4">
        <a href="<?php echo BASE_URL; ?>/user/meal-plans.php" class="btn btn-outline-secondary px-4 fw-bold">Quay lại danh sách</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
