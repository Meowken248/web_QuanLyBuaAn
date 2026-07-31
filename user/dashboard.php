<?php
// user/dashboard.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../models/ProfileModel.php';
require_once __DIR__ . '/../models/MealModel.php';
require_once __DIR__ . '/../models/WeightModel.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

$profileModel = new ProfileModel();
$profile = $profileModel->getProfileByUserId($user_id);

$mealModel = new MealModel();
$dailyNutrition = $mealModel->getDailyNutrition($user_id, $today);
$calHistory = $mealModel->getCaloriesHistory($user_id, 7);

$weightModel = new WeightModel();
$weightHistory = $weightModel->getWeightHistory($user_id, 30);

// Prepare Chart Data
$labels_cal = [];
$data_cal = [];
foreach($calHistory as $ch) {
    $labels_cal[] = date('d/m', strtotime($ch['log_date']));
    $data_cal[] = $ch['total_calories'];
}

$labels_weight = [];
$data_weight = [];
foreach($weightHistory as $wh) {
    $labels_weight[] = date('d/m', strtotime($wh['log_date']));
    $data_weight[] = $wh['weight'];
}

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$cal_target = $profile['calorie_target'] ?? 2000;
$cal_used = $dailyNutrition['calories'] ?? 0;
$cal_left = $cal_target - $cal_used;
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3">
            <div class="list-group shadow-sm mb-4">
                <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="list-group-item list-group-item-action active bg-success border-success"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/user/profile.php" class="list-group-item list-group-item-action"><i class="bi bi-person-circle me-2"></i>Hồ sơ sức khỏe</a>
                <a href="<?php echo BASE_URL; ?>/user/meals.php" class="list-group-item list-group-item-action"><i class="bi bi-journal-text me-2"></i>Nhật ký bữa ăn</a>
                <a href="<?php echo BASE_URL; ?>/user/chatbot.php" class="list-group-item list-group-item-action"><i class="bi bi-robot me-2"></i>Chatbot AI</a>
            </div>
            
            <div class="card bg-health text-white shadow-sm border-0 mb-4">
                <div class="card-body text-center">
                    <h6 class="mb-3">Có câu hỏi về dinh dưỡng?</h6>
                    <p class="small">Trợ lý AI của chúng tôi luôn sẵn sàng hỗ trợ bạn.</p>
                    <a href="<?php echo BASE_URL; ?>/user/chatbot.php" class="btn btn-light btn-sm fw-bold text-success w-100">Hỏi AI ngay</a>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Xin chào, <span class="text-success fw-bold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>!</h4>
                <div class="text-muted"><?php echo date('d/m/Y'); ?></div>
            </div>
            
            <?php if (!$profile): ?>
                <div class="alert alert-warning shadow-sm">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Chưa cập nhật hồ sơ</h5>
                    <p>Bạn cần cập nhật thông tin chiều cao, cân nặng và mục tiêu để hệ thống tính toán Calories cho bạn.</p>
                    <hr>
                    <a href="<?php echo BASE_URL; ?>/user/profile.php" class="btn btn-warning fw-bold">Cập nhật ngay</a>
                </div>
            <?php else: ?>
                <!-- Thẻ thống kê -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3 animate-fade-in-up delay-100">
                        <div class="card bg-health text-white h-100 shadow-heavy border-0 card-hover glass-card" style="border-radius: var(--radius-lg);">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="card-title text-white-50 fw-bold mb-0">Mục tiêu hôm nay</h6>
                                    <i class="bi bi-bullseye fs-4 opacity-50"></i>
                                </div>
                                <h2 class="display-6 fw-bold mb-0"><?php echo round($cal_target); ?> <small class="fs-6 opacity-75">kcal</small></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 animate-fade-in-up delay-200">
                        <div class="card h-100 shadow-heavy border-0 card-hover glass-panel">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="card-title text-muted fw-bold mb-0">Đã nạp</h6>
                                    <i class="bi bi-basket-fill text-success fs-4"></i>
                                </div>
                                <h2 class="display-6 fw-bold mb-0 text-dark"><?php echo round($cal_used); ?> <small class="fs-6 text-muted">kcal</small></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 animate-fade-in-up delay-300">
                        <div class="card h-100 shadow-heavy border-0 card-hover glass-panel">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="card-title text-muted fw-bold mb-0">Còn lại</h6>
                                    <i class="bi bi-fire text-warning fs-4"></i>
                                </div>
                                <h2 class="display-6 fw-bold mb-0 <?php echo $cal_left < 0 ? 'text-danger' : 'text-primary'; ?>"><?php echo round($cal_left); ?> <small class="fs-6 text-muted">kcal</small></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Biểu đồ -->
                <div class="row animate-fade-in-up delay-400">
                    <div class="col-md-8 mb-4">
                        <div class="card glass-panel shadow-soft border-0 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-fill me-2 text-success"></i>Lượng Calories 7 ngày qua</h6>
                            </div>
                            <div class="card-body p-4">
                                <canvas id="calChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card glass-panel shadow-soft border-0 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-pie-chart-fill me-2 text-warning"></i>Macros Hôm nay</h6>
                            </div>
                            <div class="card-body d-flex justify-content-center align-items-center p-4">
                                <div style="width: 200px; height: 200px;">
                                    <canvas id="macroChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row animate-fade-in-up delay-500">
                    <div class="col-12 mb-4">
                        <div class="card glass-panel shadow-soft border-0">
                            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Tiến trình Cân nặng (30 ngày)</h6>
                            </div>
                            <div class="card-body p-4">
                                <canvas id="weightChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($profile): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Biểu đồ Calories (Bar Chart)
    const ctxCal = document.getElementById('calChart');
    if (ctxCal) {
        new Chart(ctxCal, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_cal); ?>,
                datasets: [{
                    label: 'Calories đã nạp',
                    data: <?php echo json_encode($data_cal); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Biểu đồ Macros (Doughnut Chart)
    const ctxMacro = document.getElementById('macroChart');
    if (ctxMacro) {
        const p = <?php echo round($dailyNutrition['protein'] ?? 0); ?>;
        const c = <?php echo round($dailyNutrition['carbs'] ?? 0); ?>;
        const f = <?php echo round($dailyNutrition['fat'] ?? 0); ?>;
        
        let mData = [p, c, f];
        // Xử lý empty state
        if (p === 0 && c === 0 && f === 0) {
            mData = [1, 1, 1]; // Giả để hiện màu xám
        }

        new Chart(ctxMacro, {
            type: 'doughnut',
            data: {
                labels: ['Protein', 'Carbs', 'Fat'],
                datasets: [{
                    data: mData,
                    backgroundColor: (p===0 && c===0 && f===0) 
                                     ? ['#e9ecef', '#e9ecef', '#e9ecef'] 
                                     : ['#0d6efd', '#ffc107', '#17a2b8'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                cutout: '75%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Biểu đồ Cân Nặng (Line Chart)
    const ctxWeight = document.getElementById('weightChart');
    if (ctxWeight) {
        new Chart(ctxWeight, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels_weight); ?>,
                datasets: [{
                    label: 'Cân nặng (kg)',
                    data: <?php echo json_encode($data_weight); ?>,
                    borderColor: 'rgba(13, 110, 253, 1)',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: false }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
