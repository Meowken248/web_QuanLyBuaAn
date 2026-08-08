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

// Xử lý xóa món hoặc toàn bộ bữa
if (!is_valid_date($date)) $date = date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Phiên làm việc không hợp lệ.');
    } elseif (($_POST['action'] ?? '') === 'delete_item') {
        $item_id = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
        $deleted = $item_id ? $mealModel->deleteMealItem($item_id, $user_id) : false;
        set_flash_message($deleted ? 'success' : 'danger', $deleted ? 'Đã xóa món ăn khỏi bữa.' : 'Không thể xóa món ăn.');
    } elseif (($_POST['action'] ?? '') === 'delete_meal') {
        $type = $_POST['meal_type'] ?? '';
        $valid_types = ['breakfast','morning_snack','lunch','afternoon_snack','dinner','evening_snack'];
        $deleted = in_array($type, $valid_types, true) && $mealModel->deleteMeal($user_id, $date, $type);
        set_flash_message($deleted ? 'success' : 'warning', $deleted ? 'Đã xóa toàn bộ bữa ăn.' : 'Bữa ăn không tồn tại hoặc đã trống.');
    }
    redirect('/user/meals.php?date=' . urlencode($date));
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
$cal_percent = $cal_target > 0 ? ($cal_used / $cal_target) * 100 : 0;
if ($cal_percent > 100) $cal_percent = 100;
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body py-3">
                    <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                        <div>
                            <div class="small text-muted">Ngày đang chọn</div>
                            <div class="fw-bold text-success"><?php echo date('d/m/Y', strtotime($date)); ?></div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
                            <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-chevron-left me-1"></i>Hôm qua</a>
                            <form method="GET" action="" class="m-0">
                                <label class="visually-hidden" for="meal-log-date">Chọn ngày nhật ký</label>
                                <input type="date" class="form-control form-control-sm" id="meal-log-date" name="date" value="<?php echo htmlspecialchars($date); ?>" onchange="this.form.submit()">
                            </form>
                            <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>" class="btn btn-sm btn-outline-secondary text-nowrap">Ngày mai<i class="bi bi-chevron-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Tóm tắt dinh dưỡng -->
            <div class="card glass-panel mb-4 border-0" data-aos="fade-up">
                <div class="card-body p-4">
                    <?php display_flash_message(); ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Tổng quan dinh dưỡng</h5>
                        <span class="badge bg-light text-dark border"><i class="bi bi-calendar-event me-1"></i><?php echo date('d/m/Y', strtotime($date)); ?></span>
                    </div>
                    
                    <div class="progress mb-4" style="height: 12px; border-radius: 10px; background-color: rgba(0,0,0,0.05);">
                        <div class="progress-bar <?php echo $cal_used > $cal_target ? 'bg-danger' : 'bg-gradient'; ?>" role="progressbar" style="width: <?php echo $cal_percent; ?>%; background: var(--gradient-primary);" aria-valuenow="<?php echo $cal_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4 small fw-bold">
                        <span class="text-muted">Đã nạp: <span class="text-dark"><?php echo round($cal_used); ?> kcal</span></span>
                        <span class="text-muted">Mục tiêu: <span class="text-dark"><?php echo round($cal_target); ?> kcal</span></span>
                    </div>
                    
                    <div class="row text-center g-3">
                        <div class="col-6 col-md-3">
                            <div class="p-3 border-0 rounded-4 shadow-sm bg-white card-hover h-100">
                                <div class="mb-2"><i class="bi bi-fire text-danger fs-4"></i></div>
                                <span class="d-block text-muted small fw-medium mb-1">Calories còn lại</span>
                                <h4 class="mb-0 fw-bold <?php echo $cal_left < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo round($cal_left); ?></h4>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 border-0 rounded-4 shadow-sm bg-white card-hover h-100">
                                <div class="mb-2"><i class="bi bi-egg-fried text-warning fs-4"></i></div>
                                <span class="d-block text-muted small fw-medium mb-1">Protein</span>
                                <h4 class="mb-0 fw-bold text-dark"><?php echo round($dailyNutrition['protein']); ?>g</h4>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 border-0 rounded-4 shadow-sm bg-white card-hover h-100">
                                <div class="mb-2"><i class="bi bi-heptagon-half text-info fs-4"></i></div>
                                <span class="d-block text-muted small fw-medium mb-1">Carbs</span>
                                <h4 class="mb-0 fw-bold text-dark"><?php echo round($dailyNutrition['carbs']); ?>g</h4>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 border-0 rounded-4 shadow-sm bg-white card-hover h-100">
                                <div class="mb-2"><i class="bi bi-droplet-fill text-primary fs-4"></i></div>
                                <span class="d-block text-muted small fw-medium mb-1">Fat</span>
                                <h4 class="mb-0 fw-bold text-dark"><?php echo round($dailyNutrition['fat']); ?>g</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danh sách bữa ăn -->
            <?php 
            $delay = 100;
            foreach ($meal_types as $type_key => $type_name): 
            ?>
            <div class="card card-premium mb-4 border-0 shadow-sm" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3 px-4">
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="bi bi-cup-hot-fill"></i>
                        </div>
                        <?php echo $type_name; ?>
                    </h5>
                    <div class="d-flex gap-2">
                        <?php if (!empty($dailyMeals[$type_key])): ?>
                        <form method="POST" onsubmit="return confirm('Xóa toàn bộ món trong bữa này?');">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="delete_meal">
                            <input type="hidden" name="meal_type" value="<?php echo $type_key; ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="bi bi-trash me-1"></i>Xóa bữa</button>
                        </form>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/user/add-meal.php?date=<?php echo urlencode($date); ?>&type=<?php echo urlencode($type_key); ?>" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm btn-glow"><i class="bi bi-plus-lg me-1"></i>Thêm</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($dailyMeals[$type_key])): ?>
                        <div class="text-center p-5 text-muted bg-light">
                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                            <p class="mb-0">Chưa có món ăn nào.</p>
                            <a href="<?php echo BASE_URL; ?>/user/add-meal.php?date=<?php echo $date; ?>&type=<?php echo $type_key; ?>" class="text-success text-decoration-none small fw-medium mt-2 d-inline-block">Bấm vào đây để thêm món</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle border-top text-nowrap">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4">Món ăn</th>
                                        <th>Khẩu phần</th>
                                        <th>Calories</th>
                                        <th>Macros</th>
                                        <th class="text-end pe-4">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $meal_cals = 0;
                                    foreach ($dailyMeals[$type_key] as $item): 
                                        $meal_cals += $item['calories'];
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($item['food_name']); ?></td>
                                        <td class="text-muted"><?php echo floatval($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?><?php if ((float)$item['calculated_grams'] > 0): ?> <span class="small opacity-50">(<?php echo floatval($item['calculated_grams']); ?>g)</span><?php endif; ?></td>
                                        <td><span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1"><?php echo floatval($item['calories']); ?> kcal</span></td>
                                        <td class="small fw-medium">
                                            <span class="text-warning">P: <?php echo floatval($item['protein']); ?></span> | 
                                            <span class="text-info">C: <?php echo floatval($item['carbs']); ?></span> | 
                                            <span class="text-primary">F: <?php echo floatval($item['fat']); ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border shadow-sm" style="width: 32px; height: 32px; padding: 0;"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light fw-bold text-dark">
                                    <tr>
                                        <td colspan="2" class="text-end py-3">Tổng cộng:</td>
                                        <td colspan="3" class="text-danger py-3 pe-4"><?php echo floatval($meal_cals); ?> kcal</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php 
            $delay += 100;
            endforeach; 
            ?>
            
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
