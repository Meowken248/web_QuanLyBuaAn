<?php
// user/profile.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/ProfileModel.php';

$profileModel = new ProfileModel();
$user_id = $_SESSION['user_id'];
$profile = $profileModel->getProfileByUserId($user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $dob = $_POST['date_of_birth'];
        $gender = $_POST['gender'];
        $height = (float)$_POST['height']; // cm
        $weight = (float)$_POST['current_weight']; // kg
        $activity = $_POST['activity_level'];
        $goal = $_POST['health_goal'];
        
        // Tính tuổi
        $age = date_diff(date_create($dob), date_create('today'))->y;
        if ($age <= 0) $age = 1;
        
        // 1. Tính BMR (Mifflin-St Jeor)
        $bmr = 0;
        if ($gender === 'male') {
            $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
        } else {
            $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
        }
        
        // 2. Tính TDEE
        $activity_multiplier = [
            'sedentary' => 1.2,
            'light' => 1.375,
            'moderate' => 1.55,
            'active' => 1.725,
            'very_active' => 1.9
        ];
        $tdee = $bmr * ($activity_multiplier[$activity] ?? 1.2);
        
        // 3. Tính Calories Mục tiêu
        $calorie_target = $tdee;
        if ($goal === 'lose_weight') {
            $calorie_target = $tdee - 400; // Giảm 400 calo
        } elseif ($goal === 'gain_weight') {
            $calorie_target = $tdee + 400; // Tăng 400 calo
        } elseif ($goal === 'gain_muscle') {
            $calorie_target = $tdee + 250; // Tăng cơ cần thặng dư nhẹ
        }
        // keep_weight thì bằng TDEE
        
        // 4. Tính Macros cơ bản (Tỉ lệ tham khảo)
        // Protein: 4 calo/g, Carb: 4 calo/g, Fat: 9 calo/g
        // Lấy tỉ lệ chung: 30% Protein, 40% Carb, 30% Fat
        $protein_target = ($calorie_target * 0.3) / 4;
        $carb_target = ($calorie_target * 0.4) / 4;
        $fat_target = ($calorie_target * 0.3) / 9;

        $data = [
            'user_id' => $user_id,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'height' => $height,
            'current_weight' => $weight,
            'activity_level' => $activity,
            'health_goal' => $goal,
            'diet_type' => $_POST['diet_type'] ?? 'standard',
            'allergies' => $_POST['allergies'] ?? '',
            'disliked_foods' => $_POST['disliked_foods'] ?? '',
            'meals_per_day' => (int)$_POST['meals_per_day'],
            'bmr' => round($bmr),
            'tdee' => round($tdee),
            'calorie_target' => round($calorie_target),
            'protein_target' => round($protein_target),
            'carb_target' => round($carb_target),
            'fat_target' => round($fat_target)
        ];
        
        if ($profileModel->saveProfile($data)) {
            set_flash_message('success', 'Hồ sơ đã được cập nhật thành công!');
            // Refresh data
            $profile = $profileModel->getProfileByUserId($user_id);
        } else {
            set_flash_message('danger', 'Không thể lưu hồ sơ, vui lòng thử lại.');
        }
    }
}

$page_title = 'Hồ sơ cá nhân';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3">
            <!-- Sidebar giả lập -->
            <div class="list-group shadow-sm">
                <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="list-group-item list-group-item-action"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/user/profile.php" class="list-group-item list-group-item-action active bg-success border-success"><i class="bi bi-person-circle me-2"></i>Hồ sơ sức khỏe</a>
                <a href="<?php echo BASE_URL; ?>/user/meals.php" class="list-group-item list-group-item-action"><i class="bi bi-journal-text me-2"></i>Nhật ký bữa ăn</a>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-white p-3 border-bottom">
                    <h4 class="mb-0 fw-bold text-success">Thiết lập Hồ sơ Sức khỏe</h4>
                </div>
                <div class="card-body p-4">
                    <?php display_flash_message(); ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <h5 class="mb-3 text-secondary">Thông tin cơ bản</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày sinh</label>
                                <input type="date" class="form-control" name="date_of_birth" value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giới tính</label>
                                <select class="form-select" name="gender" required>
                                    <option value="male" <?php echo (isset($profile['gender']) && $profile['gender'] == 'male') ? 'selected' : ''; ?>>Nam</option>
                                    <option value="female" <?php echo (isset($profile['gender']) && $profile['gender'] == 'female') ? 'selected' : ''; ?>>Nữ</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chiều cao (cm)</label>
                                <input type="number" class="form-control" name="height" value="<?php echo htmlspecialchars($profile['height'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cân nặng (kg)</label>
                                <input type="number" step="0.1" class="form-control" name="current_weight" value="<?php echo htmlspecialchars($profile['current_weight'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <h5 class="mb-3 text-secondary">Mục tiêu & Thói quen</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mức độ vận động</label>
                                <select class="form-select" name="activity_level" required>
                                    <option value="sedentary" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'sedentary') ? 'selected' : ''; ?>>Ít vận động</option>
                                    <option value="light" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'light') ? 'selected' : ''; ?>>Vận động nhẹ</option>
                                    <option value="moderate" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'moderate') ? 'selected' : ''; ?>>Vận động vừa</option>
                                    <option value="active" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'active') ? 'selected' : ''; ?>>Vận động nhiều</option>
                                    <option value="very_active" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'very_active') ? 'selected' : ''; ?>>Vận động rất nhiều</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mục tiêu sức khỏe</label>
                                <select class="form-select" name="health_goal" required>
                                    <option value="lose_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'lose_weight') ? 'selected' : ''; ?>>Giảm cân</option>
                                    <option value="gain_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'gain_weight') ? 'selected' : ''; ?>>Tăng cân</option>
                                    <option value="keep_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'keep_weight') ? 'selected' : ''; ?>>Giữ cân</option>
                                    <option value="gain_muscle" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'gain_muscle') ? 'selected' : ''; ?>>Tăng cơ</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chế độ ăn yêu thích</label>
                                <select class="form-select" name="diet_type">
                                    <option value="standard" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'standard') ? 'selected' : ''; ?>>Bình thường</option>
                                    <option value="vegetarian" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'vegetarian') ? 'selected' : ''; ?>>Ăn chay</option>
                                    <option value="keto" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'keto') ? 'selected' : ''; ?>>Keto (Ít carb)</option>
                                    <option value="eat_clean" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'eat_clean') ? 'selected' : ''; ?>>Eat Clean</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số bữa ăn/ngày mong muốn</label>
                                <input type="number" class="form-control" name="meals_per_day" value="<?php echo htmlspecialchars($profile['meals_per_day'] ?? '3'); ?>" min="1" max="6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dị ứng thực phẩm</label>
                                <input type="text" class="form-control" name="allergies" value="<?php echo htmlspecialchars($profile['allergies'] ?? ''); ?>" placeholder="VD: Hải sản, đậu phộng...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Thực phẩm không thích</label>
                                <input type="text" class="form-control" name="disliked_foods" value="<?php echo htmlspecialchars($profile['disliked_foods'] ?? ''); ?>" placeholder="VD: Hành, ngò...">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success px-4">Lưu hồ sơ & Tính toán</button>
                        </div>
                    </form>
                    
                    <?php if ($profile): ?>
                        <hr class="my-5">
                        <h5 class="mb-4 text-success fw-bold"><i class="bi bi-calculator me-2"></i>Kết quả tính toán</h5>
                        <div class="row text-center">
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-light rounded shadow-sm border">
                                    <h6 class="text-muted">BMR</h6>
                                    <h3 class="text-primary fw-bold"><?php echo $profile['bmr']; ?> <small class="fs-6 text-muted">kcal</small></h3>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-light rounded shadow-sm border">
                                    <h6 class="text-muted">TDEE</h6>
                                    <h3 class="text-warning fw-bold"><?php echo $profile['tdee']; ?> <small class="fs-6 text-muted">kcal</small></h3>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-light rounded shadow-sm border border-success">
                                    <h6 class="text-success">Mục tiêu/ngày</h6>
                                    <h3 class="text-success fw-bold"><?php echo $profile['calorie_target']; ?> <small class="fs-6 text-muted">kcal</small></h3>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <p class="text-muted small"><strong>Lưu ý:</strong> Các kết quả trên sử dụng công thức Mifflin-St Jeor. Kết quả chỉ mang tính chất tham khảo, không thay thế chẩn đoán y tế.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
