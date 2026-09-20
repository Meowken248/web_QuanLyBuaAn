<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/ProfileModel.php';
require_once __DIR__ . '/../models/MealModel.php';
require_once __DIR__ . '/../models/WeightModel.php';
require_once __DIR__ . '/../models/HealthMetricModel.php';

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');
$profileModel = new ProfileModel();
$mealModel = new MealModel();
$weightModel = new WeightModel();
$healthModel = new HealthMetricModel();
$profile = $profileModel->getProfileByUserId($userId);
$healthReady = $healthModel->isAvailable();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_hourly_health') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Phiên làm việc không hợp lệ.');
    } elseif (!$healthReady) {
        set_flash_message('warning', 'Hãy import tệp SQL nâng cấp trước khi ghi dữ liệu.');
    } else {
        $date = $_POST['log_date'] ?? $today;
        $hour = filter_var($_POST['log_hour'] ?? null, FILTER_VALIDATE_INT);
        $water = filter_var($_POST['water_ml'] ?? 0, FILTER_VALIDATE_INT);
        $steps = filter_var($_POST['steps'] ?? 0, FILTER_VALIDATE_INT);
        $active = filter_var($_POST['active_minutes'] ?? 0, FILTER_VALIDATE_INT);
        $burned = filter_var($_POST['calories_burned'] ?? 0, FILTER_VALIDATE_FLOAT);
        $heartRaw = trim((string)($_POST['heart_rate'] ?? ''));
        $heart = $heartRaw === '' ? null : filter_var($heartRaw, FILTER_VALIDATE_INT);
        $sleep = filter_var($_POST['sleep_minutes'] ?? 0, FILTER_VALIDATE_INT);
        $moodRaw = trim((string)($_POST['mood_level'] ?? ''));
        $mood = $moodRaw === '' ? null : filter_var($moodRaw, FILTER_VALIDATE_INT);
        $note = trim((string)($_POST['note'] ?? ''));

        $valid = is_valid_date($date) && $date >= date('Y-m-d', strtotime('-30 days')) && $date <= $today
            && $hour !== false && $hour >= 0 && $hour <= 23
            && $water !== false && $water >= 0 && $water <= 3000
            && $steps !== false && $steps >= 0 && $steps <= 50000
            && $active !== false && $active >= 0 && $active <= 60
            && $burned !== false && $burned >= 0 && $burned <= 2000
            && ($heart === null || ($heart !== false && $heart >= 30 && $heart <= 250))
            && $sleep !== false && $sleep >= 0 && $sleep <= 60
            && ($mood === null || ($mood !== false && $mood >= 1 && $mood <= 5))
            && strlen($note) <= 255;

        if (!$valid) {
            set_flash_message('danger', 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.');
        } else {
            $saved = $healthModel->saveHourlyLog($userId, [
                'log_date' => $date, 'log_hour' => $hour, 'water_ml' => $water,
                'steps' => $steps, 'active_minutes' => $active,
                'calories_burned' => round($burned, 2), 'heart_rate' => $heart,
                'sleep_minutes' => $sleep, 'mood_level' => $mood,
                'note' => $note === '' ? null : $note
            ]);
            set_flash_message($saved ? 'success' : 'danger', $saved ? 'Đã cập nhật dữ liệu theo giờ.' : 'Không thể lưu dữ liệu.');
        }
    }
    redirect('/user/dashboard.php');
}

$nutrition = $mealModel->getDailyNutrition($userId, $today);
$hourlyMeals = $mealModel->getHourlyNutrition($userId, $today);
$nutritionHistory = $mealModel->getNutritionHistory($userId, 7);
$weights = $weightModel->getWeightHistory($userId, 30);
$health = $healthModel->getDailySummary($userId, $today);
$hourlyHealth = $healthModel->getHourlyLogs($userId, $today);
$healthHistory = $healthModel->getHistory($userId, 7);

$targets = [
    'calories' => (float)($profile['calorie_target'] ?? 2000),
    'protein' => (float)($profile['protein_target'] ?? 100),
    'carbs' => (float)($profile['carb_target'] ?? 250),
    'fat' => (float)($profile['fat_target'] ?? 65),
    'fiber' => (float)($profile['fiber_target'] ?? 25),
    'water' => (int)($profile['water_target_ml'] ?? 2000),
    'steps' => 8000, 'active' => 30, 'sleep' => 420
];
$used = [
    'calories' => (float)($nutrition['calories'] ?? 0),
    'protein' => (float)($nutrition['protein'] ?? 0),
    'carbs' => (float)($nutrition['carbs'] ?? 0),
    'fat' => (float)($nutrition['fat'] ?? 0),
    'fiber' => (float)($nutrition['fiber'] ?? 0)
];
$pct = static fn($value, $target) => $target > 0 ? min(100, max(0, $value / $target * 100)) : 0;
$calLeft = $targets['calories'] - $used['calories'];

$hourLabels = $hourlyCalories = $hourlyBurned = $hourlyWater = $hourlySteps = $hourlyActive = $hourlySleep = [];
$hourlyHeart = $hourlyNotes = [];
for ($i = 0; $i < 24; $i++) {
    $hourLabels[$i] = sprintf('%02d:00', $i);
    $hourlyCalories[$i] = $hourlyBurned[$i] = $hourlyWater[$i] = $hourlySteps[$i] = $hourlyActive[$i] = $hourlySleep[$i] = 0;
    $hourlyHeart[$i] = null;
    $hourlyNotes[$i] = '';
}
foreach ($hourlyMeals as $row) {
    $h = (int)$row['log_hour'];
    if ($h >= 0 && $h < 24) $hourlyCalories[$h] = round((float)$row['calories'], 2);
}
foreach ($hourlyHealth as $row) {
    $h = (int)$row['log_hour'];
    if ($h < 0 || $h > 23) continue;
    $hourlyBurned[$h] = round((float)$row['calories_burned'], 2);
    $hourlyWater[$h] = (int)$row['water_ml'];
    $hourlySteps[$h] = (int)$row['steps'];
    $hourlyActive[$h] = (int)$row['active_minutes'];
    $hourlySleep[$h] = (int)$row['sleep_minutes'];
    $hourlyHeart[$h] = $row['heart_rate'] !== null ? (int)$row['heart_rate'] : null;
    $hourlyNotes[$h] = (string)($row['note'] ?? '');
}

$nutritionMap = $healthMap = [];
foreach ($nutritionHistory as $row) $nutritionMap[$row['log_date']] = $row;
foreach ($healthHistory as $row) $healthMap[$row['log_date']] = $row;
$weekLabels = $weekCalories = $weekWater = $weekSteps = $weekActive = $weekSleep = [];
for ($offset = 6; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime("-{$offset} days"));
    $weekLabels[] = date('d/m', strtotime($date));
    $weekCalories[] = round((float)($nutritionMap[$date]['calories'] ?? 0), 2);
    $weekWater[] = (int)($healthMap[$date]['water_ml'] ?? 0);
    $weekSteps[] = (int)($healthMap[$date]['steps'] ?? 0);
    $weekActive[] = (int)($healthMap[$date]['active_minutes'] ?? 0);
    $weekSleep[] = round((int)($healthMap[$date]['sleep_minutes'] ?? 0) / 60, 1);
}

$weightLabels = $weightData = [];
foreach ($weights as $row) {
    $weightLabels[] = date('d/m', strtotime($row['log_date']));
    $weightData[] = (float)$row['weight'];
}
$currentWeight = $weightData ? end($weightData) : (float)($profile['current_weight_kg'] ?? 0);
$height = (float)($profile['height_cm'] ?? 0);
$bmi = $height > 0 && $currentWeight > 0 ? $currentWeight / (($height / 100) ** 2) : null;
$bmiText = 'Chưa có dữ liệu';
if ($bmi !== null) {
    $bmiText = $bmi < 18.5 ? 'Dưới ngưỡng tham khảo' : ($bmi < 25 ? 'Trong ngưỡng tham khảo' : ($bmi < 30 ? 'Trên ngưỡng tham khảo' : 'Cao hơn ngưỡng tham khảo'));
}

$habitScore = null;
if ($healthReady && (int)$health['logged_hours'] > 0) {
    $calScore = max(0, 100 - abs($used['calories'] - $targets['calories']) / max(1, $targets['calories']) * 100);
    $habitScore = (int)round(($calScore + $pct($used['protein'], $targets['protein'])
        + $pct($health['water_ml'], $targets['water']) + $pct($health['steps'], $targets['steps'])
        + $pct($health['active_minutes'], $targets['active']) + $pct($health['sleep_minutes'], $targets['sleep'])) / 6);
}
$habitText = $habitScore === null ? 'Chưa đủ dữ liệu' : ($habitScore >= 85 ? 'Rất tốt' : ($habitScore >= 70 ? 'Ổn định' : ($habitScore >= 50 ? 'Cần cải thiện' : 'Cần bổ sung thói quen')));

$insights = [];
if (!$healthReady) $insights[] = ['bi-database-add', 'Kích hoạt dữ liệu theo giờ', 'Import config/dashboard_health_upgrade.sql để bắt đầu ghi dữ liệu.'];
elseif (!(int)$health['logged_hours']) $insights[] = ['bi-clock-history', 'Chưa có dữ liệu theo giờ', 'Ghi nhanh nước uống, bước chân hoặc vận động ở biểu mẫu bên dưới.'];
if (!$used['calories']) $insights[] = ['bi-basket', 'Chưa ghi nhận bữa ăn', 'Thêm món ăn để phân tích năng lượng và dinh dưỡng.'];
elseif ($calLeft < 0) $insights[] = ['bi-exclamation-circle', 'Đã vượt mục tiêu năng lượng', 'Vượt khoảng ' . round(abs($calLeft)) . ' kcal trong hôm nay.'];
elseif ($calLeft > $targets['calories'] * .35) $insights[] = ['bi-lightning-charge', 'Năng lượng còn thiếu', 'Còn khoảng ' . round($calLeft) . ' kcal để đạt mục tiêu.'];
if ($used['protein'] > 0 && $used['protein'] < $targets['protein'] * .7) $insights[] = ['bi-egg-fried', 'Protein chưa đạt', 'Hiện đạt ' . round($pct($used['protein'], $targets['protein'])) . '% mục tiêu.'];
if ($healthReady && $health['water_ml'] > 0 && $health['water_ml'] < $targets['water'] * .7) $insights[] = ['bi-droplet', 'Nước uống còn thấp', 'Còn ' . max(0, $targets['water'] - $health['water_ml']) . ' ml để đạt mục tiêu.'];
$insights = array_slice($insights, 0, 5);

$page_title = 'Dashboard sức khỏe';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
/* Modern Health Tech Glassmorphism Styles */
.health-dashboard {
    --g: #2e7d32;
    --gd: #1b5e20;
    --gs: #e8f5e9;
    --accent: #43a047;
    --ink: #1e293b;
    --muted: #64748b;
    --line: rgba(220, 235, 225, 0.85);
    color: var(--ink);
    position: relative;
}

/* Ambient glow orbs in background */
.health-dashboard::before {
    content: '';
    position: fixed;
    top: 90px;
    right: 5%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(74, 222, 128, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    z-index: 0;
}
.health-dashboard::after {
    content: '';
    position: fixed;
    bottom: 8%;
    left: 8%;
    width: 480px;
    height: 480px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    z-index: 0;
}

/* Glassmorphic Card Base */
.dash-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid var(--line);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(23, 72, 49, 0.05);
    position: relative;
    z-index: 1;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    overflow: hidden;
}
.dash-card:hover {
    box-shadow: 0 14px 35px rgba(23, 72, 49, 0.08);
}
.dash-head {
    padding: 1.35rem 1.4rem 0.5rem;
}
.dash-body {
    padding: 1.35rem 1.4rem;
}

/* Welcome Banner */
.dash-welcome-card {
    background: linear-gradient(135deg, rgba(232, 245, 233, 0.85) 0%, rgba(255, 255, 255, 0.95) 100%);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(165, 214, 167, 0.6);
    border-radius: 22px;
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.06);
    position: relative;
    z-index: 1;
}
.dash-avatar-badge {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 800;
    box-shadow: 0 6px 18px rgba(46, 125, 50, 0.3);
    flex-shrink: 0;
}

/* Energy Hero Card */
.energy {
    background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 55%, #388e3c 100%);
    color: #ffffff;
    border-radius: 22px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 14px 35px rgba(27, 94, 32, 0.28);
}
.energy::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -15%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.energy-number {
    font-size: clamp(2.6rem, 5vw, 4.2rem);
    line-height: 1;
    letter-spacing: -0.04em;
    font-weight: 800;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
}
.frosted-chip {
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 14px;
    padding: 10px 8px;
    transition: all 0.2s ease;
}
.frosted-chip:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: translateY(-2px);
}

/* Circular Score Gauge */
.score {
    width: 136px;
    height: 136px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: conic-gradient(#2e7d32 calc(var(--score)*1%), #e2ede6 0);
    position: relative;
    box-shadow: 0 8px 24px rgba(46, 125, 50, 0.15);
    transition: transform 0.3s ease;
}
.score:hover {
    transform: scale(1.03);
}
.score:after {
    content: "";
    position: absolute;
    inset: 12px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: inset 0 2px 8px rgba(0,0,0,0.04);
}
.score > div {
    position: relative;
    z-index: 1;
    text-align: center;
}
.score strong {
    font-size: 2.2rem;
    display: block;
    line-height: 1;
    color: #1e293b;
    font-weight: 800;
}

/* Body & Goals Mini Grid */
.body-stat-tile {
    background: rgba(248, 251, 249, 0.85);
    border: 1px solid rgba(220, 235, 225, 0.75);
    border-radius: 14px;
    padding: 12px 14px;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.body-stat-tile:hover {
    background: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(23, 72, 49, 0.06);
    border-color: rgba(46, 125, 50, 0.35);
}
.tile-icon-box {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

/* 6 Modern Metric Cards */
.metric-card-modern {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(23, 72, 49, 0.04);
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.metric-card-modern:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 32px rgba(23, 72, 49, 0.08);
    border-color: rgba(46, 125, 50, 0.35);
}
.metric-card-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Insights list items */
.insight-modern {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    background: rgba(248, 251, 249, 0.9);
    border: 1px solid rgba(220, 235, 225, 0.7);
    margin-bottom: 10px;
    transition: all 0.2s ease;
}
.insight-modern:hover {
    background: #ffffff;
    transform: translateX(3px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.04);
    border-color: rgba(46, 125, 50, 0.3);
}
.insight-modern:last-child {
    margin-bottom: 0;
}
.insight-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    background: rgba(46, 125, 50, 0.1);
    color: #2e7d32;
    flex-shrink: 0;
}

/* Quick Log Form Controls */
.dash-input, .dash-select {
    border-radius: 12px;
    border: 1px solid rgba(200, 220, 210, 0.85);
    padding: 10px 14px;
    font-size: 0.92rem;
    background: rgba(255, 255, 255, 0.95);
    transition: all 0.2s ease;
}
.dash-input:focus, .dash-select:focus {
    border-color: #2e7d32;
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
    background: #ffffff;
}
.dash-btn-submit {
    background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    padding: 12px 20px;
    font-weight: 700;
    box-shadow: 0 6px 18px rgba(46, 125, 50, 0.25);
    transition: all 0.25s ease;
}
.dash-btn-submit:hover {
    background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(46, 125, 50, 0.35);
}

/* Micro-animations */
@keyframes heartbeat {
    0% { transform: scale(1); }
    14% { transform: scale(1.2); }
    28% { transform: scale(1); }
    42% { transform: scale(1.2); }
    70% { transform: scale(1); }
}
.pulse-heart {
    display: inline-block;
    animation: heartbeat 1.8s infinite ease-in-out;
}
@keyframes pulse-dot {
    0% { box-shadow: 0 0 0 0 rgba(46, 125, 50, 0.6); }
    70% { box-shadow: 0 0 0 6px rgba(46, 125, 50, 0); }
    100% { box-shadow: 0 0 0 0 rgba(46, 125, 50, 0); }
}
.pulse-dot {
    width: 8px;
    height: 8px;
    background: #2e7d32;
    border-radius: 50%;
    display: inline-block;
    animation: pulse-dot 2s infinite;
}

.chart-lg { height: 320px; }
.chart-sm { height: 265px; }
.title { font-size: 1.05rem; font-weight: 750; margin: 0; color: #1e293b; }
.subtitle { font-size: 0.84rem; color: var(--muted); margin: 0.25rem 0 0; }
.hour-table { font-variant-numeric: tabular-nums; }
@media(prefers-reduced-motion:reduce){ .health-dashboard * { transition: none !important; } }
</style>

<div class="container-fluid health-dashboard py-4 px-lg-4">
<div class="row g-4">
<div class="col-12">

<!-- Welcome Hero Banner -->
<div class="dash-welcome-card mb-4 p-4 position-relative overflow-hidden">
  <div class="row align-items-center g-3">
    <div class="col-lg-8">
      <div class="d-flex align-items-center gap-3">
        <div class="dash-avatar-badge shadow-sm">
          <span><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></span>
        </div>
        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <h1 class="h3 fw-bold text-dark mb-0">Xin chào, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'bạn'); ?> 👋</h1>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 small fw-semibold">
              <span class="pulse-dot me-1"></span> Đang hoạt động
            </span>
          </div>
          <p class="text-muted mb-0 mt-1 small">
            <i class="bi bi-clock-history me-1 text-success"></i>Dữ liệu sức khỏe cập nhật đến <strong><?php echo date('H:i'); ?></strong> &bull; Chúc bạn một ngày tràn đầy năng lượng!
          </p>
        </div>
      </div>
    </div>
    <div class="col-lg-4 text-lg-end d-flex flex-wrap justify-content-lg-end align-items-center gap-2">
      <a href="<?php echo BASE_URL; ?>/mystery-box.php" class="btn btn-sm btn-success rounded-pill px-3 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-1" title="Mở hộp món ăn ngẫu nhiên hôm nay">
        <i class="bi bi-gift-fill text-warning"></i> Ăn Gì Hôm Nay?
      </a>
      <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-75 px-3 py-2 rounded-pill border shadow-sm">
        <i class="bi bi-calendar3 text-success"></i>
        <span class="fw-bold text-dark"><?php echo date('d/m/Y'); ?></span>
      </div>
    </div>
  </div>
</div>

<?php display_flash_message(); ?>

<?php if (!$healthReady): ?>
  <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 p-3 mb-4" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 5px solid #f59e0b !important;">
    <div class="rounded-circle bg-warning bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; flex-shrink: 0;">
      <i class="bi bi-database-add text-warning fs-5"></i>
    </div>
    <div>
      <div class="fw-bold text-dark">Cần import SQL để bật theo dõi theo giờ</div>
      <div class="small text-muted">Import <code>config/dashboard_health_upgrade.sql</code> vào database <code>meal_health_manager</code> để ghi dữ liệu nước, bước chân, giấc ngủ.</div>
    </div>
  </div>
<?php endif; ?>

<?php if (!$profile): ?>
  <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 mb-4" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 5px solid #f59e0b !important;">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded-circle bg-warning bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; flex-shrink: 0;">
        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
      </div>
      <div>
        <div class="fw-bold text-dark">Chưa hoàn thiện hồ sơ sức khỏe!</div>
        <div class="small text-muted">Cập nhật chiều cao, cân nặng và mục tiêu để tính chính xác BMR, TDEE và lộ trình calo.</div>
      </div>
    </div>
    <a href="<?php echo BASE_URL; ?>/user/profile.php" class="btn btn-warning btn-sm fw-bold px-3 py-2 rounded-pill shadow-sm">
      <i class="bi bi-pencil-square me-1"></i> Cập nhật ngay
    </a>
  </div>
<?php endif; ?>

<!-- Section 1: Top 3 Core Cards -->
<section class="row g-4 mb-4">
  <!-- Energy Hero Card -->
  <div class="col-xl-5">
    <div class="energy p-4 h-100 d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-3 py-1">
            <i class="bi bi-fire text-warning me-1"></i> Năng lượng đã nạp
          </span>
          <span class="small text-white-50">Hôm nay</span>
        </div>
        <div class="energy-number mt-2">
          <?php echo number_format(round($used['calories']), 0, ',', '.'); ?>
          <span class="fs-4 fw-normal text-white-50">kcal</span>
        </div>
        <div class="text-white-50 mt-1 small">
          trên <strong class="text-white"><?php echo number_format(round($targets['calories']), 0, ',', '.'); ?> kcal</strong> mục tiêu
        </div>
      </div>

      <div class="mt-4">
        <div class="d-flex justify-content-between small text-white fw-bold mb-2">
          <span><?php echo round($pct($used['calories'], $targets['calories'])); ?>% mục tiêu</span>
          <span><?php echo $calLeft >= 0 ? 'Còn ' . round($calLeft) : 'Vượt ' . round(abs($calLeft)); ?> kcal</span>
        </div>
        <div class="progress" style="height: 10px; border-radius: 99px; background: rgba(255, 255, 255, 0.22);">
          <div class="progress-bar bg-white shadow-sm" style="width: <?php echo $pct($used['calories'], $targets['calories']); ?>%; border-radius: 99px;"></div>
        </div>

        <div class="row g-2 mt-3">
          <div class="col-4">
            <div class="frosted-chip text-center">
              <span class="text-white-50 d-block small"><i class="bi bi-heart-pulse me-1"></i>BMR</span>
              <strong class="text-white"><?php echo round((float)($profile['bmr'] ?? 0)); ?> <span class="small fw-normal">kcal</span></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="frosted-chip text-center">
              <span class="text-white-50 d-block small"><i class="bi bi-lightning-charge me-1"></i>TDEE</span>
              <strong class="text-white"><?php echo round((float)($profile['tdee'] ?? 0)); ?> <span class="small fw-normal">kcal</span></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="frosted-chip text-center">
              <span class="text-white-50 d-block small"><i class="bi bi-person-walking me-1"></i>Vận động</span>
              <strong class="text-white"><?php echo round((float)$health['calories_burned']); ?> <span class="small fw-normal">kcal</span></strong>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Habit Score Card -->
  <div class="col-md-5 col-xl-3">
    <div class="dash-card h-100 d-flex flex-column justify-content-between p-4 text-center">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="title"><i class="bi bi-award-fill text-warning me-2"></i>Điểm thói quen</h2>
        <span class="badge bg-light text-muted border small">AI Score</span>
      </div>

      <div class="my-auto py-3">
        <div class="score mx-auto mb-3" style="--score:<?php echo $habitScore ?? 0; ?>;">
          <div>
            <strong><?php echo $habitScore ?? '--'; ?></strong>
            <small class="text-muted fw-bold">/ 100</small>
          </div>
        </div>
        <?php
          $habitBadgeClass = match($habitText) {
              'Rất tốt' => 'bg-success text-white',
              'Ổn định' => 'bg-success-subtle text-success border border-success-subtle',
              'Cần cải thiện' => 'bg-warning-subtle text-warning border border-warning-subtle',
              default => 'bg-secondary-subtle text-secondary border'
          };
        ?>
        <span class="badge <?php echo $habitBadgeClass; ?> px-3 py-2 rounded-pill fw-bold fs-6 mb-1"><?php echo $habitText; ?></span>
      </div>

      <div class="small text-muted border-top pt-2">
        <i class="bi bi-info-circle me-1"></i>Tổng hợp dinh dưỡng, nước, vận động & giấc ngủ.
      </div>
    </div>
  </div>

  <!-- Body & Goals Grid Card -->
  <div class="col-md-7 col-xl-4">
    <div class="dash-card h-100 d-flex flex-column justify-content-between p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="title"><i class="bi bi-person-badge-fill text-success me-2"></i>Cơ thể & Mục tiêu</h2>
          <p class="subtitle">Từ hồ sơ và lần cân gần nhất</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/user/profile.php" class="btn btn-sm btn-outline-success rounded-pill px-3">
          Chi tiết
        </a>
      </div>

      <div class="row g-2">
        <div class="col-6">
          <div class="body-stat-tile">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <small class="text-muted">Cân nặng</small>
              <span class="tile-icon-box bg-primary-subtle text-primary"><i class="bi bi-speedometer2"></i></span>
            </div>
            <strong class="fs-5 text-dark"><?php echo $currentWeight ? round($currentWeight, 1) . ' kg' : '--'; ?></strong>
          </div>
        </div>
        <div class="col-6">
          <div class="body-stat-tile">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <small class="text-muted">BMI tham khảo</small>
              <span class="tile-icon-box bg-success-subtle text-success"><i class="bi bi-calculator"></i></span>
            </div>
            <strong class="fs-5 text-dark"><?php echo $bmi !== null ? round($bmi, 1) : '--'; ?></strong>
          </div>
        </div>
        <div class="col-12">
          <div class="body-stat-tile">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <small class="text-muted d-block">Đánh giá BMI</small>
                <span class="fw-bold text-dark small"><?php echo $bmiText; ?></span>
              </div>
              <span class="tile-icon-box bg-info-subtle text-info"><i class="bi bi-shield-check"></i></span>
            </div>
          </div>
        </div>
        <div class="col-4">
          <div class="body-stat-tile text-center p-2">
            <span class="tile-icon-box bg-danger-subtle text-danger mx-auto mb-1"><i class="bi bi-heart-pulse-fill pulse-heart"></i></span>
            <small class="text-muted d-block" style="font-size: 0.75rem;">Nhịp tim TB</small>
            <strong class="text-dark small"><?php echo $health['avg_heart_rate'] !== null ? round($health['avg_heart_rate']) . ' bpm' : '--'; ?></strong>
          </div>
        </div>
        <div class="col-4">
          <div class="body-stat-tile text-center p-2">
            <span class="tile-icon-box bg-indigo-subtle text-primary mx-auto mb-1" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;"><i class="bi bi-moon-stars-fill"></i></span>
            <small class="text-muted d-block" style="font-size: 0.75rem;">Giấc ngủ</small>
            <strong class="text-dark small"><?php echo round($health['sleep_minutes'] / 60, 1); ?> giờ</strong>
          </div>
        </div>
        <div class="col-4">
          <div class="body-stat-tile text-center p-2">
            <span class="tile-icon-box bg-warning-subtle text-warning mx-auto mb-1"><i class="bi bi-lightning-charge-fill"></i></span>
            <small class="text-muted d-block" style="font-size: 0.75rem;">Vận động</small>
            <strong class="text-dark small"><?php echo (int)$health['active_minutes']; ?> phút</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Section 2: 6 Modern Metric Cards -->
<section class="row g-3 mb-4">
  <?php
  $modernMetrics = [
    [
      'name' => 'Protein',
      'icon' => 'bi-egg-fried',
      'icon_bg' => 'rgba(139, 92, 246, 0.12)',
      'icon_color' => '#7c3aed',
      'bar_color' => 'linear-gradient(90deg, #8b5cf6, #a78bfa)',
      'current' => round($used['protein'], 1),
      'target' => round($targets['protein']),
      'unit' => 'g',
      'pct' => $pct($used['protein'], $targets['protein'])
    ],
    [
      'name' => 'Carbohydrate',
      'icon' => 'bi-basket2-fill',
      'icon_bg' => 'rgba(245, 158, 11, 0.12)',
      'icon_color' => '#d97706',
      'bar_color' => 'linear-gradient(90deg, #f59e0b, #fbbf24)',
      'current' => round($used['carbs'], 1),
      'target' => round($targets['carbs']),
      'unit' => 'g',
      'pct' => $pct($used['carbs'], $targets['carbs'])
    ],
    [
      'name' => 'Chất béo',
      'icon' => 'bi-droplet-half',
      'icon_bg' => 'rgba(249, 115, 22, 0.12)',
      'icon_color' => '#ea580c',
      'bar_color' => 'linear-gradient(90deg, #f97316, #fb923c)',
      'current' => round($used['fat'], 1),
      'target' => round($targets['fat']),
      'unit' => 'g',
      'pct' => $pct($used['fat'], $targets['fat'])
    ],
    [
      'name' => 'Chất xơ',
      'icon' => 'bi-flower1',
      'icon_bg' => 'rgba(16, 185, 129, 0.12)',
      'icon_color' => '#059669',
      'bar_color' => 'linear-gradient(90deg, #10b981, #34d399)',
      'current' => round($used['fiber'], 1),
      'target' => round($targets['fiber']),
      'unit' => 'g',
      'pct' => $pct($used['fiber'], $targets['fiber'])
    ],
    [
      'name' => 'Nước uống',
      'icon' => 'bi-cup-straw',
      'icon_bg' => 'rgba(6, 182, 212, 0.12)',
      'icon_color' => '#0891b2',
      'bar_color' => 'linear-gradient(90deg, #06b6d4, #38bdf8)',
      'current' => number_format($health['water_ml'], 0, ',', '.'),
      'target' => number_format($targets['water'], 0, ',', '.'),
      'unit' => 'ml',
      'pct' => $pct($health['water_ml'], $targets['water'])
    ],
    [
      'name' => 'Bước chân',
      'icon' => 'bi-person-walking',
      'icon_bg' => 'rgba(244, 63, 94, 0.12)',
      'icon_color' => '#e11d48',
      'bar_color' => 'linear-gradient(90deg, #f43f5e, #fb7185)',
      'current' => number_format($health['steps'], 0, ',', '.'),
      'target' => number_format($targets['steps'], 0, ',', '.'),
      'unit' => 'bước',
      'pct' => $pct($health['steps'], $targets['steps'])
    ],
  ];
  ?>
  <?php foreach ($modernMetrics as $mm): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="metric-card-modern">
        <div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="metric-card-icon" style="background: <?php echo $mm['icon_bg']; ?>; color: <?php echo $mm['icon_color']; ?>;">
              <i class="bi <?php echo $mm['icon']; ?>"></i>
            </span>
            <span class="badge bg-light text-dark border small fw-bold">
              <?php echo round($mm['pct']); ?>%
            </span>
          </div>
          <small class="text-muted d-block mb-1"><?php echo $mm['name']; ?></small>
          <div class="fw-bold text-dark fs-6 text-truncate">
            <?php echo $mm['current']; ?> <span class="text-muted fw-normal small">/ <?php echo $mm['target']; ?> <?php echo $mm['unit']; ?></span>
          </div>
        </div>

        <div class="mt-3">
          <div class="progress" style="height: 6px; border-radius: 99px; background: rgba(0,0,0,0.06);">
            <div class="progress-bar" style="width: <?php echo min(100, $mm['pct']); ?>%; background: <?php echo $mm['bar_color']; ?>; border-radius: 99px;"></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<!-- Section 3: 24h Energy Chart -->
<section class="dash-card mb-4">
  <div class="dash-head d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
      <h2 class="title"><i class="bi bi-clock-history me-2 text-success"></i>Năng lượng theo 24 giờ</h2>
      <p class="subtitle">Calo nạp và calo vận động được ghi nhận theo từng khung giờ trong ngày</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/user/meals.php" class="btn btn-sm btn-outline-success rounded-pill px-3 shadow-sm align-self-start">
      <i class="bi bi-journal-plus me-1"></i>Nhật ký bữa ăn
    </a>
  </div>
  <div class="dash-body">
    <div class="chart-lg">
      <canvas id="hourlyChart"></canvas>
    </div>
  </div>
</section>

<!-- Section 4: 7-day Calories & Insights -->
<section class="row g-4 mb-4">
  <div class="col-xl-8">
    <div class="dash-card h-100">
      <div class="dash-head">
        <h2 class="title"><i class="bi bi-graph-up-arrow text-success me-2"></i>Năng lượng 7 ngày gần nhất</h2>
        <p class="subtitle">So sánh lượng calo đã nạp với mục tiêu hàng ngày</p>
      </div>
      <div class="dash-body">
        <div class="chart-sm">
          <canvas id="weekCalChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="dash-card h-100 d-flex flex-column justify-content-between">
      <div class="dash-head">
        <h2 class="title"><i class="bi bi-chat-square-quote-fill text-info me-2"></i>Nhận xét hôm nay</h2>
        <p class="subtitle">Lời khuyên thông minh dựa trên dữ liệu đã ghi nhận</p>
      </div>
      <div class="dash-body pt-2 flex-grow-1">
        <?php if (!$insights): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
            <div class="fw-bold">Chưa có cảnh báo đáng chú ý</div>
            <small>Dữ liệu dinh dưỡng của bạn đang ở mức cân bằng tốt.</small>
          </div>
        <?php else: ?>
          <?php foreach ($insights as $item): ?>
            <div class="insight-modern">
              <div class="insight-icon-box">
                <i class="bi <?php echo $item[0]; ?>"></i>
              </div>
              <div>
                <div class="fw-bold text-dark small"><?php echo htmlspecialchars($item[1]); ?></div>
                <div class="small text-muted mt-1"><?php echo htmlspecialchars($item[2]); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Section 5: Macro Comparison & Habits -->
<section class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="dash-card h-100">
      <div class="dash-head">
        <h2 class="title"><i class="bi bi-pie-chart-fill text-success me-2"></i>Dinh dưỡng so với mục tiêu</h2>
        <p class="subtitle">Tỷ lệ bốn nhóm dinh dưỡng chính đã nạp hôm nay</p>
      </div>
      <div class="dash-body">
        <div class="chart-sm">
          <canvas id="macroChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="dash-card h-100">
      <div class="dash-head">
        <h2 class="title"><i class="bi bi-droplet-fill text-primary me-2"></i>Nước uống & Bước chân 7 ngày</h2>
        <p class="subtitle">Theo dõi thói quen uống nước và vận động mỗi ngày</p>
      </div>
      <div class="dash-body">
        <div class="chart-sm">
          <canvas id="healthChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Section 6: Weight Logs & Quick Hourly Form -->
<section class="row g-4 mb-4">
  <div class="col-xl-8">
    <div class="dash-card h-100">
      <div class="dash-head d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div>
          <h2 class="title"><i class="bi bi-activity text-success me-2"></i>Cân nặng 30 lần ghi gần nhất</h2>
          <p class="subtitle">Biểu đồ theo dõi tiến độ cân nặng theo thời gian</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/user/weight-logs.php" class="btn btn-sm btn-outline-success rounded-pill px-3 shadow-sm align-self-start">
          <i class="bi bi-plus-circle me-1"></i>Ghi cân nặng
        </a>
      </div>
      <div class="dash-body">
        <?php if ($weightData): ?>
          <div class="chart-sm">
            <canvas id="weightChart"></canvas>
          </div>
        <?php else: ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-graph-up text-muted fs-1 d-block mb-2"></i>
            Chưa có dữ liệu cân nặng. Hãy bấm "Ghi cân nặng" để theo dõi!
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="dash-card h-100">
      <div class="dash-head">
        <h2 class="title"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Ghi nhanh theo giờ</h2>
        <p class="subtitle">Ghi hoặc cập nhật số liệu cho một khung giờ</p>
      </div>
      <div class="dash-body">
        <?php if (!$healthReady): ?>
          <div class="alert alert-warning small mb-0 rounded-3">Biểu mẫu hoạt động sau khi import SQL.</div>
        <?php else: ?>
          <form method="POST" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="save_hourly_health">
            
            <div class="col-7">
              <label class="form-label small fw-bold text-muted mb-1" for="logDate">Ngày</label>
              <input class="form-control dash-input" type="date" id="logDate" name="log_date" value="<?php echo $today; ?>" min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" max="<?php echo $today; ?>" required>
            </div>
            <div class="col-5">
              <label class="form-label small fw-bold text-muted mb-1" for="logHour">Khung giờ</label>
              <select class="form-select dash-select" id="logHour" name="log_hour">
                <?php for ($h = 0; $h < 24; $h++): ?>
                  <option value="<?php echo $h; ?>" <?php echo $h === (int)date('G') ? 'selected' : ''; ?>>
                    <?php echo sprintf('%02d:00', $h); ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="col-6">
              <label class="form-label small fw-bold text-muted mb-1" for="water"><i class="bi bi-cup-straw text-info me-1"></i>Nước (ml)</label>
              <input class="form-control dash-input" type="number" id="water" name="water_ml" value="0" min="0" max="3000">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold text-muted mb-1" for="steps"><i class="bi bi-person-walking text-danger me-1"></i>Bước chân</label>
              <input class="form-control dash-input" type="number" id="steps" name="steps" value="0" min="0" max="50000">
            </div>

            <div class="col-6">
              <label class="form-label small fw-bold text-muted mb-1" for="active"><i class="bi bi-lightning-charge text-warning me-1"></i>Vận động (p)</label>
              <input class="form-control dash-input" type="number" id="active" name="active_minutes" value="0" min="0" max="60">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold text-muted mb-1" for="burned"><i class="bi bi-fire text-warning me-1"></i>Calo đốt</label>
              <input class="form-control dash-input" type="number" id="burned" name="calories_burned" value="0" min="0" max="2000" step=".1">
            </div>

            <div class="col-6">
              <label class="form-label small fw-bold text-muted mb-1" for="heart"><i class="bi bi-heart-pulse text-danger me-1"></i>Nhịp tim</label>
              <input class="form-control dash-input" type="number" id="heart" name="heart_rate" min="30" max="250" placeholder="bpm">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold text-muted mb-1" for="sleep"><i class="bi bi-moon-stars text-primary me-1"></i>Ngủ (phút)</label>
              <input class="form-control dash-input" type="number" id="sleep" name="sleep_minutes" value="0" min="0" max="60">
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold text-muted mb-1" for="mood"><i class="bi bi-emoji-smile text-success me-1"></i>Tâm trạng</label>
              <select class="form-select dash-select" id="mood" name="mood_level">
                <option value="">Không ghi nhận</option>
                <option value="1">1 - Rất không tốt 😞</option>
                <option value="2">2 - Không tốt 🙁</option>
                <option value="3">3 - Bình thường 😐</option>
                <option value="4">4 - Tốt 🙂</option>
                <option value="5">5 - Rất tốt 😄</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold text-muted mb-1" for="note">Ghi chú</label>
              <input class="form-control dash-input" id="note" name="note" maxlength="255" placeholder="Ví dụ: đi bộ sau bữa trưa...">
            </div>

            <div class="col-12 mt-3">
              <button class="dash-btn-submit w-100">
                <i class="bi bi-check2-circle me-1"></i>Lưu dữ liệu giờ này
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Section 7: 24h Detailed Table Accordion -->
<section class="dash-card mb-4">
  <details>
    <summary class="dash-body fw-bold d-flex justify-content-between align-items-center" style="cursor: pointer; user-select: none;">
      <span><i class="bi bi-table me-2 text-success"></i>Xem bảng thống kê chi tiết 24 khung giờ</span>
      <span class="badge bg-light text-success border rounded-pill px-3 py-1">Nhấn để mở / đóng</span>
    </summary>
    <div class="table-responsive border-top">
      <table class="table table-hover mb-0 hour-table text-nowrap align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-4">Giờ</th>
            <th>Calo nạp</th>
            <th>Calo vận động</th>
            <th>Nước</th>
            <th>Bước chân</th>
            <th>Vận động</th>
            <th>Nhịp tim</th>
            <th>Ngủ</th>
            <th>Ghi chú</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($h = 0; $h < 24; $h++): ?>
            <tr>
              <td class="ps-4 fw-bold text-success"><?php echo sprintf('%02d:00', $h); ?></td>
              <td><?php echo $hourlyCalories[$h] ? '<strong>' . round($hourlyCalories[$h]) . '</strong> <small class="text-muted">kcal</small>' : '-'; ?></td>
              <td><?php echo $hourlyBurned[$h] ? '<strong>' . round($hourlyBurned[$h]) . '</strong> <small class="text-muted">kcal</small>' : '-'; ?></td>
              <td><?php echo $hourlyWater[$h] ? $hourlyWater[$h] . ' ml' : '-'; ?></td>
              <td><?php echo $hourlySteps[$h] ? number_format($hourlySteps[$h], 0, ',', '.') : '-'; ?></td>
              <td><?php echo $hourlyActive[$h] ? $hourlyActive[$h] . ' phút' : '-'; ?></td>
              <td><?php echo $hourlyHeart[$h] !== null ? '<span class="badge bg-danger-subtle text-danger">' . $hourlyHeart[$h] . ' bpm</span>' : '-'; ?></td>
              <td><?php echo $hourlySleep[$h] ? $hourlySleep[$h] . ' phút' : '-'; ?></td>
              <td class="small text-muted"><?php echo $hourlyNotes[$h] !== '' ? htmlspecialchars($hourlyNotes[$h]) : '-'; ?></td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </details>
</section>

<div class="text-center text-muted small pb-3">
  <i class="bi bi-shield-check me-1 text-success"></i>Các chỉ số phục vụ mục đích theo dõi và cải thiện thói quen cá nhân, không thay thế chẩn đoán y khoa.
</div>

</div></div></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
if(typeof Chart==='undefined')return;
Chart.defaults.color='#64748b';Chart.defaults.font.family=getComputedStyle(document.body).fontFamily;
const green='#2e7d32',greenSoft='rgba(46,125,50,.18)',orange='#f59e0b',blue='#0284c7',grid='rgba(99,121,109,.08)';
const common={responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:16}}}};
const hourly=document.getElementById('hourlyChart');if(hourly)new Chart(hourly,{data:{labels:<?php echo json_encode($hourLabels); ?>,datasets:[
{type:'bar',label:'Calo nạp',data:<?php echo json_encode($hourlyCalories); ?>,backgroundColor:greenSoft,borderColor:green,borderWidth:1.5,borderRadius:8},
{type:'line',label:'Calo vận động ghi nhận',data:<?php echo json_encode($hourlyBurned); ?>,borderColor:orange,backgroundColor:orange,borderWidth:2.5,tension:.35,pointRadius:3}
]},options:{...common,scales:{x:{grid:{display:false},ticks:{maxRotation:0,maxTicksLimit:12}},y:{beginAtZero:true,grid:{color:grid},title:{display:true,text:'kcal'}}}}});
const wc=document.getElementById('weekCalChart');if(wc)new Chart(wc,{type:'line',data:{labels:<?php echo json_encode($weekLabels); ?>,datasets:[
{label:'Calo đã nạp',data:<?php echo json_encode($weekCalories); ?>,borderColor:green,backgroundColor:greenSoft,fill:true,tension:.35,pointRadius:4},
{label:'Mục tiêu',data:<?php echo json_encode(array_fill(0,7,round($targets['calories'],2))); ?>,borderColor:'#94a3b8',borderDash:[6,5],pointRadius:0}
]},options:{...common,scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:grid}}}}});
const mc=document.getElementById('macroChart');if(mc)new Chart(mc,{type:'bar',data:{labels:['Protein','Carbohydrate','Chất béo','Chất xơ'],datasets:[
{label:'Đã nạp',data:<?php echo json_encode([round($used['protein'],2),round($used['carbs'],2),round($used['fat'],2),round($used['fiber'],2)]); ?>,backgroundColor:green,borderRadius:6},
{label:'Mục tiêu',data:<?php echo json_encode([round($targets['protein'],2),round($targets['carbs'],2),round($targets['fat'],2),round($targets['fiber'],2)]); ?>,backgroundColor:'#e2e8f0',borderRadius:6}
]},options:{...common,indexAxis:'y',scales:{x:{beginAtZero:true,grid:{color:grid}},y:{grid:{display:false}}}}});
const hc=document.getElementById('healthChart');if(hc)new Chart(hc,{data:{labels:<?php echo json_encode($weekLabels); ?>,datasets:[
{type:'bar',label:'Bước chân',data:<?php echo json_encode($weekSteps); ?>,backgroundColor:greenSoft,borderColor:green,borderWidth:1.5,borderRadius:8,yAxisID:'steps'},
{type:'line',label:'Nước uống (ml)',data:<?php echo json_encode($weekWater); ?>,borderColor:blue,backgroundColor:blue,tension:.35,pointRadius:4,yAxisID:'water'}
]},options:{...common,scales:{x:{grid:{display:false}},steps:{beginAtZero:true,position:'left',grid:{color:grid}},water:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}}}});
const wt=document.getElementById('weightChart');if(wt)new Chart(wt,{type:'line',data:{labels:<?php echo json_encode($weightLabels); ?>,datasets:[{label:'Cân nặng (kg)',data:<?php echo json_encode($weightData); ?>,borderColor:green,backgroundColor:greenSoft,fill:true,tension:.35,pointRadius:4}]},options:{...common,scales:{x:{grid:{display:false}},y:{beginAtZero:false,grid:{color:grid}}},plugins:{legend:{display:false}}}});
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
