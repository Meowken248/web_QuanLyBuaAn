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
            <div class="card glass-panel border-0" data-aos="fade-up">
                <div class="card-header bg-white bg-opacity-75 p-4 border-0 d-flex align-items-center rounded-top-4">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-person-gear fs-4"></i>
                    </div>
                    <h4 class="mb-0 fw-bold text-dark">Thiết lập Hồ sơ Sức khỏe</h4>
                </div>
                <div class="card-body p-4 p-lg-5">
                    <?php display_flash_message(); ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-5" data-aos="fade-up" data-aos-delay="100">
                            <h5 class="mb-4 text-success fw-bold d-flex align-items-center"><i class="bi bi-info-circle me-2"></i>Thông tin cơ bản</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" class="form-control bg-light border-0" id="dob" name="date_of_birth" value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?>" required>
                                        <label for="dob" class="text-muted">Ngày sinh</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select bg-light border-0" id="gender" name="gender" required>
                                            <option value="male" <?php echo (isset($profile['gender']) && $profile['gender'] == 'male') ? 'selected' : ''; ?>>Nam</option>
                                            <option value="female" <?php echo (isset($profile['gender']) && $profile['gender'] == 'female') ? 'selected' : ''; ?>>Nữ</option>
                                        </select>
                                        <label for="gender" class="text-muted">Giới tính</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control bg-light border-0" id="height" name="height" value="<?php echo htmlspecialchars($profile['height'] ?? ''); ?>" required placeholder="cm">
                                        <label for="height" class="text-muted">Chiều cao (cm)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" step="0.1" class="form-control bg-light border-0" id="weight" name="current_weight" value="<?php echo htmlspecialchars($profile['current_weight'] ?? ''); ?>" required placeholder="kg">
                                        <label for="weight" class="text-muted">Cân nặng (kg)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-5" data-aos="fade-up" data-aos-delay="200">
                            <h5 class="mb-4 text-success fw-bold d-flex align-items-center"><i class="bi bi-bullseye me-2"></i>Mục tiêu & Thói quen</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select bg-light border-0" id="activity" name="activity_level" required>
                                            <option value="sedentary" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'sedentary') ? 'selected' : ''; ?>>Ít vận động</option>
                                            <option value="light" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'light') ? 'selected' : ''; ?>>Vận động nhẹ</option>
                                            <option value="moderate" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'moderate') ? 'selected' : ''; ?>>Vận động vừa</option>
                                            <option value="active" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'active') ? 'selected' : ''; ?>>Vận động nhiều</option>
                                            <option value="very_active" <?php echo (isset($profile['activity_level']) && $profile['activity_level'] == 'very_active') ? 'selected' : ''; ?>>Vận động rất nhiều</option>
                                        </select>
                                        <label for="activity" class="text-muted">Mức độ vận động</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select bg-light border-0" id="goal" name="health_goal" required>
                                            <option value="lose_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'lose_weight') ? 'selected' : ''; ?>>Giảm cân</option>
                                            <option value="gain_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'gain_weight') ? 'selected' : ''; ?>>Tăng cân</option>
                                            <option value="keep_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'keep_weight') ? 'selected' : ''; ?>>Giữ cân</option>
                                            <option value="gain_muscle" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'gain_muscle') ? 'selected' : ''; ?>>Tăng cơ</option>
                                        </select>
                                        <label for="goal" class="text-muted">Mục tiêu sức khỏe</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select bg-light border-0" id="diet" name="diet_type">
                                            <option value="standard" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'standard') ? 'selected' : ''; ?>>Bình thường</option>
                                            <option value="vegetarian" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'vegetarian') ? 'selected' : ''; ?>>Ăn chay</option>
                                            <option value="keto" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'keto') ? 'selected' : ''; ?>>Keto (Ít carb)</option>
                                            <option value="eat_clean" <?php echo (isset($profile['diet_type']) && $profile['diet_type'] == 'eat_clean') ? 'selected' : ''; ?>>Eat Clean</option>
                                        </select>
                                        <label for="diet" class="text-muted">Chế độ ăn yêu thích</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control bg-light border-0" id="meals_per_day" name="meals_per_day" value="<?php echo htmlspecialchars($profile['meals_per_day'] ?? '3'); ?>" min="1" max="6" placeholder="3">
                                        <label for="meals_per_day" class="text-muted">Số bữa ăn/ngày mong muốn</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control bg-light border-0" id="allergies" name="allergies" value="<?php echo htmlspecialchars($profile['allergies'] ?? ''); ?>" placeholder="VD: Hải sản, đậu phộng...">
                                        <label for="allergies" class="text-muted">Dị ứng thực phẩm</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control bg-light border-0" id="disliked" name="disliked_foods" value="<?php echo htmlspecialchars($profile['disliked_foods'] ?? ''); ?>" placeholder="VD: Hành, ngò...">
                                        <label for="disliked" class="text-muted">Thực phẩm không thích</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end" data-aos="fade-up" data-aos-delay="300">
                            <button type="submit" class="btn btn-success btn-glow px-5 py-3 rounded-pill fw-bold text-uppercase shadow-sm">
                                <i class="bi bi-save me-2"></i>Lưu hồ sơ & Tính toán
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($profile): ?>
                        <div class="mt-5 pt-4 border-top" data-aos="fade-up" data-aos-delay="400">
                            <h5 class="mb-4 text-dark fw-bold d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-calculator-fill"></i>
                                </div>
                                Kết quả tính toán
                            </h5>
                            <div class="row g-4 text-center">
                                <div class="col-md-4">
                                    <div class="p-4 bg-white rounded-4 shadow-sm border-0 card-hover h-100 position-relative overflow-hidden">
                                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-secondary bg-opacity-10" style="z-index: 0;"></div>
                                        <div class="position-relative z-index-1">
                                            <h6 class="text-muted text-uppercase small fw-bold mb-3">BMR <i class="bi bi-info-circle ms-1" title="Tỷ lệ trao đổi chất cơ bản"></i></h6>
                                            <h2 class="text-secondary fw-bold mb-0"><?php echo $profile['bmr']; ?> <span class="fs-6 text-muted fw-normal">kcal</span></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-4 bg-white rounded-4 shadow-sm border-0 card-hover h-100 position-relative overflow-hidden">
                                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-warning bg-opacity-10" style="z-index: 0;"></div>
                                        <div class="position-relative z-index-1">
                                            <h6 class="text-muted text-uppercase small fw-bold mb-3">TDEE <i class="bi bi-info-circle ms-1" title="Tổng năng lượng tiêu hao mỗi ngày"></i></h6>
                                            <h2 class="text-warning fw-bold mb-0"><?php echo $profile['tdee']; ?> <span class="fs-6 text-muted fw-normal">kcal</span></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-4 bg-white rounded-4 shadow-sm border-0 card-hover h-100 position-relative overflow-hidden">
                                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-success bg-opacity-10" style="z-index: 0;"></div>
                                        <div class="position-relative z-index-1">
                                            <h6 class="text-success text-uppercase small fw-bold mb-3">Mục tiêu/ngày</h6>
                                            <h2 class="text-success fw-bold mb-0"><?php echo $profile['calorie_target']; ?> <span class="fs-6 text-muted fw-normal">kcal</span></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="alert bg-light border-0 text-muted small d-flex align-items-center rounded-3">
                                        <i class="bi bi-lightbulb text-warning fs-5 me-3"></i>
                                        <div><strong>Lưu ý:</strong> Các kết quả trên sử dụng công thức Mifflin-St Jeor. Kết quả chỉ mang tính chất tham khảo, không thay thế chẩn đoán y tế.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
