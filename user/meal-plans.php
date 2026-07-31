<?php
// user/meal-plans.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user profile
require_once __DIR__ . '/../models/ProfileModel.php';
$profileModel = new ProfileModel();
$profile = $profileModel->getProfileByUserId($user_id);

$goalFilter = isset($_GET['goal']) ? $_GET['goal'] : '';
$isPremiumFilter = isset($_GET['premium']) ? (int)$_GET['premium'] : -1;

$whereClauses = ["status = 'active'"];
$params = [];

if ($goalFilter) {
    $whereClauses[] = "goal_type = :goal";
    $params[':goal'] = $goalFilter;
}
if ($isPremiumFilter >= 0) {
    $whereClauses[] = "is_premium = :prem";
    $params[':prem'] = $isPremiumFilter;
}

$whereSql = "WHERE " . implode(' AND ', $whereClauses);

$stmt = $conn->prepare("SELECT * FROM meal_plans $whereSql ORDER BY created_at DESC");
$stmt->execute($params);
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's favorites
$stmtFav = $conn->prepare("SELECT meal_plan_id FROM favorite_meal_plans WHERE user_id = :uid");
$stmtFav->execute([':uid' => $user_id]);
$favorites = $stmtFav->fetchAll(PDO::FETCH_COLUMN);

// Handle Favorite Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
    $pid = (int)$_POST['plan_id'];
    if (in_array($pid, $favorites)) {
        $conn->prepare("DELETE FROM favorite_meal_plans WHERE user_id = :uid AND meal_plan_id = :pid")->execute([':uid' => $user_id, ':pid' => $pid]);
        $_SESSION['success'] = 'Đã bỏ yêu thích.';
    } else {
        $conn->prepare("INSERT INTO favorite_meal_plans (user_id, meal_plan_id) VALUES (:uid, :pid)")->execute([':uid' => $user_id, ':pid' => $pid]);
        $_SESSION['success'] = 'Đã thêm vào danh sách yêu thích.';
    }
    header("Location: /user/meal-plans.php" . ($goalFilter ? "?goal=$goalFilter" : ""));
    exit;
}

$page_title = 'Khám phá Thực đơn';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0 text-success"><i class="bi bi-book-half me-2"></i>Thực đơn Gợi ý</h2>
            <p class="text-muted mt-1">Khám phá các kế hoạch bữa ăn được thiết kế sẵn cho bạn.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="d-inline-flex gap-2">
                <a href="?goal=" class="btn btn-sm <?php echo empty($goalFilter) ? 'btn-success' : 'btn-outline-success'; ?>">Tất cả</a>
                <a href="?goal=lose_weight" class="btn btn-sm <?php echo $goalFilter == 'lose_weight' ? 'btn-success' : 'btn-outline-success'; ?>">Giảm cân</a>
                <a href="?goal=gain_muscle" class="btn btn-sm <?php echo $goalFilter == 'gain_muscle' ? 'btn-success' : 'btn-outline-success'; ?>">Tăng cơ</a>
                <a href="?goal=maintain_weight" class="btn btn-sm <?php echo $goalFilter == 'maintain_weight' ? 'btn-success' : 'btn-outline-success'; ?>">Khỏe mạnh</a>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Hiển thị lượng calo đề xuất nếu có Profile -->
    <?php if ($profile): ?>
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
        <i class="bi bi-info-circle-fill fs-3 text-info me-3"></i>
        <div>
            Theo hồ sơ của bạn, lượng Calories mục tiêu là <strong><?php echo $profile['calorie_target']; ?> kcal/ngày</strong> 
            với mục tiêu <strong><?php 
                echo $profile['health_goal'] == 'lose_weight' ? 'Giảm cân' : 
                    ($profile['health_goal'] == 'gain_weight' ? 'Tăng cân' : 'Giữ dáng'); 
            ?></strong>. Hãy chọn các thực đơn có tổng năng lượng tương đương nhé!
        </div>
    </div>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($plans as $plan): ?>
            <?php $isFav = in_array($plan['id'], $favorites); ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0 card-hover overflow-hidden">
                    <div class="position-relative">
                        <?php if ($plan['image']): ?>
                            <img src="<?php echo BASE_URL . '/uploads/meal_plans/' . $plan['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($plan['name']); ?>" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-success bg-opacity-25 d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="bi bi-journal-check text-success" style="font-size: 5rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badges -->
                        <div class="position-absolute top-0 start-0 m-3 d-flex flex-column gap-2">
                            <?php if ($plan['is_premium']): ?>
                                <span class="badge bg-warning text-dark px-3 py-2 fw-bold shadow-sm rounded-pill"><i class="bi bi-star-fill me-1"></i>Premium</span>
                            <?php endif; ?>
                            <span class="badge bg-primary px-3 py-2 shadow-sm rounded-pill"><?php echo round($plan['total_calories']); ?> kcal</span>
                        </div>
                        
                        <!-- Favorite Button -->
                        <form method="POST" class="position-absolute top-0 end-0 m-3">
                            <input type="hidden" name="action" value="toggle_favorite">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            <button type="submit" class="btn btn-light rounded-circle shadow-sm text-danger d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi <?php echo $isFav ? 'bi-heart-fill' : 'bi-heart'; ?> fs-5"></i>
                            </button>
                        </form>
                    </div>
                    
                    <div class="card-body">
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase small fw-bold text-muted"><?php echo htmlspecialchars($plan['diet_type']); ?></span>
                            <?php 
                                $bg = 'bg-secondary';
                                if($plan['goal_type'] == 'lose_weight') $bg = 'bg-info text-dark';
                                if($plan['goal_type'] == 'gain_muscle') $bg = 'bg-danger text-white';
                                if($plan['goal_type'] == 'maintain_weight') $bg = 'bg-success text-white';
                            ?>
                            <span class="badge <?php echo $bg; ?>"><?php echo $plan['goal_type']; ?></span>
                        </div>
                        <h5 class="card-title fw-bold mb-3"><?php echo htmlspecialchars($plan['name']); ?></h5>
                        <p class="card-text text-muted small" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($plan['description'] ?: 'Không có mô tả cho thực đơn này.'); ?>
                        </p>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3">
                        <div class="row text-center mb-3 g-1 small">
                            <div class="col-4"><div class="p-1 rounded bg-light"><strong><?php echo round($plan['total_protein']); ?>g</strong><br><span class="text-muted" style="font-size:0.75rem">Protein</span></div></div>
                            <div class="col-4"><div class="p-1 rounded bg-light"><strong><?php echo round($plan['total_carbs']); ?>g</strong><br><span class="text-muted" style="font-size:0.75rem">Carbs</span></div></div>
                            <div class="col-4"><div class="p-1 rounded bg-light"><strong><?php echo round($plan['total_fat']); ?>g</strong><br><span class="text-muted" style="font-size:0.75rem">Fat</span></div></div>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/user/meal-plan-view.php?id=<?php echo $plan['id']; ?>" class="btn btn-success w-100 fw-bold">Xem Chi tiết <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($plans)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-search text-muted mb-3" style="font-size: 3rem;"></i>
                <h4 class="text-muted">Không tìm thấy thực đơn nào phù hợp.</h4>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card-hover {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
