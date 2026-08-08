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
.health-dashboard{--g:#16794c;--gd:#0d5c39;--gs:#e9f5ef;--ink:#17231d;--muted:#64726b;--line:#dfe8e3;color:var(--ink)}
.dash-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 12px 30px rgba(23,72,49,.06)}
.dash-head{padding:1.2rem 1.3rem 0}.dash-body{padding:1.3rem}.energy{background:linear-gradient(135deg,var(--gd),var(--g));color:#f7fffa;border-radius:16px}
.energy-number{font-size:clamp(2.5rem,5vw,4.5rem);line-height:.95;letter-spacing:-.05em}
.metric-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}.metric{padding:1rem;border-right:1px solid var(--line);border-bottom:1px solid var(--line)}
.metric:nth-child(3n){border-right:0}.metric:nth-last-child(-n+3){border-bottom:0}.metric small{color:var(--muted)}.metric strong{display:block;font-size:1.18rem;font-variant-numeric:tabular-nums}
.score{width:128px;height:128px;border-radius:50%;display:grid;place-items:center;background:conic-gradient(var(--g) calc(var(--score)*1%),#e5eee9 0);position:relative}
.score:after{content:"";position:absolute;inset:11px;border-radius:50%;background:#fff}.score>div{position:relative;z-index:1;text-align:center}.score strong{font-size:2rem;display:block;line-height:1}
.chart-lg{height:320px}.chart-sm{height:265px}.title{font-size:1rem;font-weight:750;margin:0}.subtitle{font-size:.82rem;color:var(--muted);margin:.25rem 0 0}
.insight{display:grid;grid-template-columns:40px 1fr;gap:.8rem;padding:.85rem 0;border-bottom:1px solid var(--line)}.insight:last-child{border:0}
.insight i{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:var(--gs);color:var(--g)}
.health-dashboard .form-control,.health-dashboard .form-select{border-radius:10px;border-color:#d8e2dc}.health-dashboard .form-label{font-size:.8rem;font-weight:700}
.setup{border:1px solid #e8c46b;background:#fff9e8;color:#6a5011;border-radius:14px}.hour-table{font-variant-numeric:tabular-nums}
@media(max-width:991.98px){.metric-grid{grid-template-columns:repeat(2,1fr)}.metric,.metric:nth-child(3n){border-right:1px solid var(--line);border-bottom:1px solid var(--line)}.metric:nth-child(2n){border-right:0}.metric:nth-last-child(-n+2){border-bottom:0}}
@media(max-width:575.98px){.metric-grid{grid-template-columns:1fr}.metric,.metric:nth-child(2n),.metric:nth-child(3n){border-right:0;border-bottom:1px solid var(--line)}.metric:last-child{border-bottom:0}}
@media(prefers-reduced-motion:reduce){.health-dashboard *{transition:none!important}}
</style>

<div class="container-fluid health-dashboard py-4 px-lg-4">
<div class="row g-4">
<div class="col-12">
<header class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1">Tổng quan sức khỏe hôm nay</h1><p class="text-muted mb-0">Xin chào <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'bạn'); ?>. Dữ liệu cập nhật đến <?php echo date('H:i'); ?>.</p></div>
  <div class="text-md-end"><div class="fw-bold"><?php echo date('d/m/Y'); ?></div><div class="small text-muted">Asia/Ho_Chi_Minh</div></div>
</header>
<?php display_flash_message(); ?>
<?php if (!$healthReady): ?><div class="setup p-3 mb-4"><strong><i class="bi bi-database-add me-2"></i>Cần import SQL để bật theo dõi theo giờ.</strong><div class="small mt-1">Import <code>config/dashboard_health_upgrade.sql</code> vào database <code>meal_health_manager</code>.</div></div><?php endif; ?>
<?php if (!$profile): ?><div class="alert alert-warning"><strong>Chưa có hồ sơ sức khỏe.</strong> Hãy cập nhật để tính BMR, TDEE và mục tiêu. <a href="<?php echo BASE_URL; ?>/user/profile.php" class="alert-link">Cập nhật ngay</a></div><?php endif; ?>

<section class="row g-4 mb-4">
  <div class="col-xl-5"><div class="energy p-4 h-100">
    <div class="small text-white-50">Năng lượng đã nạp</div><div class="energy-number fw-bold mt-2"><?php echo number_format(round($used['calories']),0,',','.'); ?></div>
    <div>kcal trên <?php echo number_format(round($targets['calories']),0,',','.'); ?> kcal mục tiêu</div>
    <div class="d-flex justify-content-between small mt-4 mb-2"><span><?php echo round($pct($used['calories'],$targets['calories'])); ?>% mục tiêu</span><span><?php echo $calLeft>=0?'Còn '.round($calLeft):'Vượt '.round(abs($calLeft)); ?> kcal</span></div>
    <div class="progress bg-white bg-opacity-25" style="height:8px"><div class="progress-bar bg-white" style="width:<?php echo $pct($used['calories'],$targets['calories']); ?>%"></div></div>
    <div class="row g-2 mt-4 small"><div class="col-4"><span class="text-white-50 d-block">BMR</span><strong><?php echo round((float)($profile['bmr']??0)); ?> kcal</strong></div><div class="col-4"><span class="text-white-50 d-block">TDEE</span><strong><?php echo round((float)($profile['tdee']??0)); ?> kcal</strong></div><div class="col-4"><span class="text-white-50 d-block">Vận động</span><strong><?php echo round((float)$health['calories_burned']); ?> kcal</strong></div></div>
  </div></div>
  <div class="col-md-5 col-xl-3"><div class="dash-card h-100"><div class="dash-body h-100 d-flex flex-column justify-content-center align-items-center text-center">
    <div class="score mb-3" style="--score:<?php echo $habitScore??0; ?>"><div><strong><?php echo $habitScore??'--'; ?></strong><small>/ 100</small></div></div><div class="fw-bold"><?php echo $habitText; ?></div><small class="text-muted mt-1">Điểm thói quen, không phải chẩn đoán</small>
  </div></div></div>
  <div class="col-md-7 col-xl-4"><div class="dash-card h-100"><div class="dash-head"><h2 class="title">Cơ thể và mục tiêu</h2><p class="subtitle">Từ hồ sơ và lần cân gần nhất</p></div><div class="metric-grid mt-3">
    <div class="metric"><small>Cân nặng</small><strong><?php echo $currentWeight?round($currentWeight,1).' kg':'--'; ?></strong></div>
    <div class="metric"><small>BMI tham khảo</small><strong><?php echo $bmi!==null?round($bmi,1):'--'; ?></strong></div>
    <div class="metric"><small>Đánh giá BMI</small><div class="fw-bold small mt-1"><?php echo $bmiText; ?></div></div>
    <div class="metric"><small>Nhịp tim TB</small><strong><?php echo $health['avg_heart_rate']!==null?round($health['avg_heart_rate']).' bpm':'--'; ?></strong></div>
    <div class="metric"><small>Ngủ đã ghi</small><strong><?php echo round($health['sleep_minutes']/60,1); ?> giờ</strong></div>
    <div class="metric"><small>Vận động</small><strong><?php echo (int)$health['active_minutes']; ?> phút</strong></div>
  </div></div></div>
</section>

<section class="dash-card mb-4"><div class="metric-grid">
<?php
$metrics=[
 ['Protein',round($used['protein'],1).' / '.round($targets['protein']).' g',$pct($used['protein'],$targets['protein'])],
 ['Carbohydrate',round($used['carbs'],1).' / '.round($targets['carbs']).' g',$pct($used['carbs'],$targets['carbs'])],
 ['Chất béo',round($used['fat'],1).' / '.round($targets['fat']).' g',$pct($used['fat'],$targets['fat'])],
 ['Chất xơ',round($used['fiber'],1).' / '.round($targets['fiber']).' g',$pct($used['fiber'],$targets['fiber'])],
 ['Nước uống',number_format($health['water_ml'],0,',','.').' / '.number_format($targets['water'],0,',','.').' ml',$pct($health['water_ml'],$targets['water'])],
 ['Bước chân',number_format($health['steps'],0,',','.').' / '.number_format($targets['steps'],0,',','.'),$pct($health['steps'],$targets['steps'])]
]; foreach($metrics as $m): ?>
<div class="metric"><small><?php echo $m[0]; ?></small><strong><?php echo $m[1]; ?></strong><span class="small text-success"><?php echo round($m[2]); ?>% mục tiêu</span></div>
<?php endforeach; ?>
</div></section>

<section class="dash-card mb-4"><div class="dash-head d-flex justify-content-between gap-3"><div><h2 class="title"><i class="bi bi-clock me-2 text-success"></i>Năng lượng theo 24 giờ</h2><p class="subtitle">Calo nạp và calo vận động được ghi theo từng giờ</p></div><a href="<?php echo BASE_URL; ?>/user/meals.php" class="btn btn-sm btn-outline-success align-self-start">Nhật ký bữa ăn</a></div><div class="dash-body"><div class="chart-lg"><canvas id="hourlyChart"></canvas></div></div></section>

<section class="row g-4 mb-4">
<div class="col-xl-8"><div class="dash-card h-100"><div class="dash-head"><h2 class="title">Năng lượng 7 ngày</h2><p class="subtitle">So sánh lượng nạp với mục tiêu hiện tại</p></div><div class="dash-body"><div class="chart-sm"><canvas id="weekCalChart"></canvas></div></div></div></div>
<div class="col-xl-4"><div class="dash-card h-100"><div class="dash-head"><h2 class="title">Nhận xét hôm nay</h2><p class="subtitle">Dựa trên dữ liệu đã ghi nhận</p></div><div class="dash-body pt-2">
<?php if(!$insights): ?><div class="text-center text-muted py-5"><i class="bi bi-check-circle text-success fs-2 d-block mb-2"></i>Chưa có cảnh báo đáng chú ý.</div>
<?php else: foreach($insights as $item): ?><div class="insight"><i class="bi <?php echo $item[0]; ?>"></i><div><div class="fw-bold small"><?php echo htmlspecialchars($item[1]); ?></div><div class="small text-muted mt-1"><?php echo htmlspecialchars($item[2]); ?></div></div></div><?php endforeach; endif; ?>
</div></div></div>
</section>

<section class="row g-4 mb-4">
<div class="col-xl-6"><div class="dash-card h-100"><div class="dash-head"><h2 class="title">Dinh dưỡng so với mục tiêu</h2><p class="subtitle">Bốn nhóm dinh dưỡng chính hôm nay</p></div><div class="dash-body"><div class="chart-sm"><canvas id="macroChart"></canvas></div></div></div></div>
<div class="col-xl-6"><div class="dash-card h-100"><div class="dash-head"><h2 class="title">Nước và bước chân 7 ngày</h2><p class="subtitle">Theo dõi mức độ duy trì thói quen</p></div><div class="dash-body"><div class="chart-sm"><canvas id="healthChart"></canvas></div></div></div></div>
</section>

<section class="row g-4 mb-4">
<div class="col-xl-8"><div class="dash-card h-100"><div class="dash-head d-flex justify-content-between gap-3"><div><h2 class="title">Cân nặng 30 lần ghi gần nhất</h2><p class="subtitle">Dữ liệu từ nhật ký cân nặng</p></div><a href="<?php echo BASE_URL; ?>/user/weight-logs.php" class="btn btn-sm btn-outline-success">Cập nhật</a></div><div class="dash-body"><?php if($weightData): ?><div class="chart-sm"><canvas id="weightChart"></canvas></div><?php else: ?><div class="text-center text-muted py-5">Chưa có dữ liệu cân nặng.</div><?php endif; ?></div></div></div>
<div class="col-xl-4"><div class="dash-card h-100"><div class="dash-head"><h2 class="title">Ghi nhanh theo giờ</h2><p class="subtitle">Ghi hoặc cập nhật một khung giờ</p></div><div class="dash-body">
<?php if(!$healthReady): ?><div class="alert alert-warning small mb-0">Biểu mẫu hoạt động sau khi import SQL.</div><?php else: ?>
<form method="POST" class="row g-3"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="save_hourly_health">
<div class="col-7"><label class="form-label" for="logDate">Ngày</label><input class="form-control" type="date" id="logDate" name="log_date" value="<?php echo $today; ?>" min="<?php echo date('Y-m-d',strtotime('-30 days')); ?>" max="<?php echo $today; ?>" required></div>
<div class="col-5"><label class="form-label" for="logHour">Giờ</label><select class="form-select" id="logHour" name="log_hour"><?php for($h=0;$h<24;$h++): ?><option value="<?php echo $h; ?>" <?php echo $h===(int)date('G')?'selected':''; ?>><?php echo sprintf('%02d:00',$h); ?></option><?php endfor; ?></select></div>
<div class="col-6"><label class="form-label" for="water">Nước (ml)</label><input class="form-control" type="number" id="water" name="water_ml" value="0" min="0" max="3000"></div>
<div class="col-6"><label class="form-label" for="steps">Bước chân</label><input class="form-control" type="number" id="steps" name="steps" value="0" min="0" max="50000"></div>
<div class="col-6"><label class="form-label" for="active">Vận động (phút)</label><input class="form-control" type="number" id="active" name="active_minutes" value="0" min="0" max="60"></div>
<div class="col-6"><label class="form-label" for="burned">Calo vận động</label><input class="form-control" type="number" id="burned" name="calories_burned" value="0" min="0" max="2000" step=".1"></div>
<div class="col-6"><label class="form-label" for="heart">Nhịp tim TB</label><input class="form-control" type="number" id="heart" name="heart_rate" min="30" max="250" placeholder="bpm"></div>
<div class="col-6"><label class="form-label" for="sleep">Ngủ (phút)</label><input class="form-control" type="number" id="sleep" name="sleep_minutes" value="0" min="0" max="60"></div>
<div class="col-12"><label class="form-label" for="mood">Tâm trạng</label><select class="form-select" id="mood" name="mood_level"><option value="">Không ghi nhận</option><option value="1">1 - Rất không tốt</option><option value="2">2 - Không tốt</option><option value="3">3 - Bình thường</option><option value="4">4 - Tốt</option><option value="5">5 - Rất tốt</option></select></div>
<div class="col-12"><label class="form-label" for="note">Ghi chú</label><input class="form-control" id="note" name="note" maxlength="255" placeholder="Ví dụ: đi bộ sau bữa trưa"></div>
<div class="col-12"><button class="btn btn-success w-100 fw-bold">Lưu dữ liệu giờ này</button></div></form>
<?php endif; ?>
</div></div></div>
</section>
<section class="dash-card mb-3"><details><summary class="dash-body fw-bold" style="cursor:pointer"><i class="bi bi-table me-2 text-success"></i>Xem bảng chi tiết đủ 24 giờ</summary><div class="table-responsive border-top"><table class="table table-hover mb-0 hour-table"><thead class="table-light"><tr><th class="ps-4">Giờ</th><th>Calo nạp</th><th>Calo vận động</th><th>Nước</th><th>Bước</th><th>Vận động</th><th>Nhịp tim</th><th>Ngủ</th><th>Ghi chú</th></tr></thead><tbody>
<?php for($h=0;$h<24;$h++): ?><tr><td class="ps-4 fw-bold"><?php echo sprintf('%02d:00',$h); ?></td><td><?php echo $hourlyCalories[$h]?round($hourlyCalories[$h]).' kcal':'-'; ?></td><td><?php echo $hourlyBurned[$h]?round($hourlyBurned[$h]).' kcal':'-'; ?></td><td><?php echo $hourlyWater[$h]?$hourlyWater[$h].' ml':'-'; ?></td><td><?php echo $hourlySteps[$h]?number_format($hourlySteps[$h],0,',','.'):'-'; ?></td><td><?php echo $hourlyActive[$h]?$hourlyActive[$h].' phút':'-'; ?></td><td><?php echo $hourlyHeart[$h]!==null?$hourlyHeart[$h].' bpm':'-'; ?></td><td><?php echo $hourlySleep[$h]?$hourlySleep[$h].' phút':'-'; ?></td><td class="small text-muted"><?php echo $hourlyNotes[$h]!==''?htmlspecialchars($hourlyNotes[$h]):'-'; ?></td></tr><?php endfor; ?>
</tbody></table></div></details></section>
<p class="small text-muted">Các chỉ số phục vụ theo dõi thói quen cá nhân, không thay thế chẩn đoán hoặc tư vấn y tế.</p>
</div></div></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
if(typeof Chart==='undefined')return;
Chart.defaults.color='#64726b';Chart.defaults.font.family=getComputedStyle(document.body).fontFamily;
const green='#16794c',greenSoft='rgba(22,121,76,.16)',orange='#d97706',blue='#2563eb',grid='rgba(99,121,109,.12)';
const common={responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}}};
const hourly=document.getElementById('hourlyChart');if(hourly)new Chart(hourly,{data:{labels:<?php echo json_encode($hourLabels); ?>,datasets:[
{type:'bar',label:'Calo nạp',data:<?php echo json_encode($hourlyCalories); ?>,backgroundColor:greenSoft,borderColor:green,borderWidth:1,borderRadius:5},
{type:'line',label:'Calo vận động ghi nhận',data:<?php echo json_encode($hourlyBurned); ?>,borderColor:orange,backgroundColor:orange,borderWidth:2,tension:.3,pointRadius:2}
]},options:{...common,scales:{x:{grid:{display:false},ticks:{maxRotation:0,maxTicksLimit:12}},y:{beginAtZero:true,grid:{color:grid},title:{display:true,text:'kcal'}}}}});
const wc=document.getElementById('weekCalChart');if(wc)new Chart(wc,{type:'line',data:{labels:<?php echo json_encode($weekLabels); ?>,datasets:[
{label:'Calo đã nạp',data:<?php echo json_encode($weekCalories); ?>,borderColor:green,backgroundColor:greenSoft,fill:true,tension:.35,pointRadius:4},
{label:'Mục tiêu',data:<?php echo json_encode(array_fill(0,7,round($targets['calories'],2))); ?>,borderColor:'#829188',borderDash:[6,5],pointRadius:0}
]},options:{...common,scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:grid}}}}});
const mc=document.getElementById('macroChart');if(mc)new Chart(mc,{type:'bar',data:{labels:['Protein','Carbohydrate','Chất béo','Chất xơ'],datasets:[
{label:'Đã nạp',data:<?php echo json_encode([round($used['protein'],2),round($used['carbs'],2),round($used['fat'],2),round($used['fiber'],2)]); ?>,backgroundColor:green,borderRadius:5},
{label:'Mục tiêu',data:<?php echo json_encode([round($targets['protein'],2),round($targets['carbs'],2),round($targets['fat'],2),round($targets['fiber'],2)]); ?>,backgroundColor:'#dce8e1',borderRadius:5}
]},options:{...common,indexAxis:'y',scales:{x:{beginAtZero:true,grid:{color:grid}},y:{grid:{display:false}}}}});
const hc=document.getElementById('healthChart');if(hc)new Chart(hc,{data:{labels:<?php echo json_encode($weekLabels); ?>,datasets:[
{type:'bar',label:'Bước chân',data:<?php echo json_encode($weekSteps); ?>,backgroundColor:greenSoft,borderColor:green,borderWidth:1,borderRadius:5,yAxisID:'steps'},
{type:'line',label:'Nước uống (ml)',data:<?php echo json_encode($weekWater); ?>,borderColor:blue,backgroundColor:blue,tension:.35,pointRadius:3,yAxisID:'water'}
]},options:{...common,scales:{x:{grid:{display:false}},steps:{beginAtZero:true,position:'left',grid:{color:grid}},water:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}}}});
const wt=document.getElementById('weightChart');if(wt)new Chart(wt,{type:'line',data:{labels:<?php echo json_encode($weightLabels); ?>,datasets:[{label:'Cân nặng (kg)',data:<?php echo json_encode($weightData); ?>,borderColor:green,backgroundColor:greenSoft,fill:true,tension:.3,pointRadius:4}]},options:{...common,scales:{x:{grid:{display:false}},y:{beginAtZero:false,grid:{color:grid}}},plugins:{legend:{display:false}}}});
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
