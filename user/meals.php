<?php
// user/meals.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/MealModel.php';
require_once __DIR__ . '/../models/ProfileModel.php';

$user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');

$mealModel = new MealModel();
$dailyMeals = $mealModel->getDailyMeals($user_id, $date);
$dailyNutrition = $mealModel->getDailyNutrition($user_id, $date);

$profileModel = new ProfileModel();
$profile = $profileModel->getProfileByUserId($user_id);

// Xử lý xoá món
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_item') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $item_id = $_POST['item_id'];
        $mealModel->deleteMealItem($item_id, $user_id);
        set_flash_message('success', 'Đã xóa món ăn khỏi bữa!');
        redirect("/user/meals.php?date=" . urlencode($date));
    }
}

$page_title = 'Nhật ký bữa ăn';
require_once __DIR__ . '/../includes/header.php';

// Các loại bữa ăn để hiển thị
$meal_types = [
    'breakfast' => 'Bữa sáng',
    'morning_snack' => 'Bữa phụ sáng',
    'lunch' => 'Bữa trưa',
    'afternoon_snack' => 'Bữa phụ chiều',
    'dinner' => 'Bữa tối',
    'evening_snack' => 'Bữa phụ tối'
];

$cal_target = $profile['calorie_target'] ?? 2000;
$cal_used = $dailyNutrition['calories'];
$cal_left = $cal_target - $cal_used;
$cal_percent = ($cal_used / $cal_target) * 100;
if ($cal_percent > 100) $cal_percent = 100;
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3">
            <div class="list-group shadow-sm mb-4">
                <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="list-group-item list-group-item-action"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/user/profile.php" class="list-group-item list-group-item-action"><i class="bi bi-person-circle me-2"></i>Hồ sơ sức khỏe</a>
                <a href="<?php echo BASE_URL; ?>/user/meals.php" class="list-group-item list-group-item-action active bg-success border-success"><i class="bi bi-journal-text me-2"></i>Nhật ký bữa ăn</a>
            </div>
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold text-success mb-3">Ngày đang chọn</h5>
                    <form method="GET" action="">
                        <input type="date" class="form-control mb-2" name="date" value="<?php echo htmlspecialchars($date); ?>" onchange="this.form.submit()">
                    </form>
                    <div class="d-flex justify-content-between mt-2">
                        <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i> Hôm qua</a>
                        <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>" class="btn btn-sm btn-outline-secondary">Ngày mai <i class="bi bi-chevron-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <!-- Tóm tắt dinh dưỡng -->
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body">
                    <?php display_flash_message(); ?>
                    <h5 class="fw-bold mb-3">Tổng quan dinh dưỡng (<?php echo date('d/m/Y', strtotime($date)); ?>)</h5>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar <?php echo $cal_used > $cal_target ? 'bg-danger' : 'bg-success'; ?>" role="progressbar" style="width: <?php echo $cal_percent; ?>%" aria-valuenow="<?php echo $cal_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($cal_used); ?> / <?php echo round($cal_target); ?> kcal
                        </div>
                    </div>
                    <div class="row text-center mt-3 g-2">
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small">Calories còn lại</span>
                                <strong class="<?php echo $cal_left < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo round($cal_left); ?></strong>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small">Protein</span>
                                <strong><?php echo round($dailyNutrition['protein']); ?>g</strong>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small">Carbs</span>
                                <strong><?php echo round($dailyNutrition['carbs']); ?>g</strong>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <span class="d-block text-muted small">Fat</span>
                                <strong><?php echo round($dailyNutrition['fat']); ?>g</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danh sách bữa ăn -->
            <?php foreach ($meal_types as $type_key => $type_name): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold text-success"><i class="bi bi-cup-hot me-2"></i><?php echo $type_name; ?></h5>
                    <a href="<?php echo BASE_URL; ?>/user/add-meal.php?date=<?php echo $date; ?>&type=<?php echo $type_key; ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-plus-lg"></i> Thêm món</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($dailyMeals[$type_key])): ?>
                        <div class="text-center p-4 text-muted">
                            Chưa có món ăn nào trong bữa này.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Món ăn</th>
                                        <th>Khẩu phần</th>
                                        <th>Calories</th>
                                        <th>Macros</th>
                                        <th class="text-end">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $meal_cals = 0;
                                    foreach ($dailyMeals[$type_key] as $item): 
                                        $meal_cals += $item['calories'];
                                    ?>
                                    <tr>
                                        <td class="fw-medium"><?php echo htmlspecialchars($item['food_name']); ?></td>
                                        <td><?php echo floatval($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?> (<?php echo floatval($item['calculated_grams']); ?>g)</td>
                                        <td class="text-danger fw-bold"><?php echo floatval($item['calories']); ?> kcal</td>
                                        <td class="small text-muted">
                                            P: <?php echo floatval($item['protein']); ?> | 
                                            C: <?php echo floatval($item['carbs']); ?> | 
                                            F: <?php echo floatval($item['fat']); ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="2" class="text-end">Tổng cộng:</td>
                                        <td colspan="3" class="text-danger"><?php echo floatval($meal_cals); ?> kcal</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
