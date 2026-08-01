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
        set_flash_message('danger', 'Phiên làm việc không hợp lệ.');
    } else {
        $dob = $_POST['date_of_birth'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $height = filter_var($_POST['height'] ?? null, FILTER_VALIDATE_FLOAT);
        $weight = filter_var($_POST['current_weight'] ?? null, FILTER_VALIDATE_FLOAT);
        $activity = $_POST['activity_level'] ?? '';
        $goal = $_POST['health_goal'] ?? '';
        $pace = $_POST['goal_pace'] ?? 'moderate';
        $meals_per_day = filter_var($_POST['meals_per_day'] ?? null, FILTER_VALIDATE_INT);
        $activity_multiplier = ['sedentary' => 1.2, 'light' => 1.375, 'moderate' => 1.55, 'very_active' => 1.725, 'extra_active' => 1.9];
        $valid_goals = ['lose_weight', 'gain_weight', 'maintain_weight', 'gain_muscle'];
        $goal_adjustments = [
            'lose_weight' => ['slow' => -250, 'moderate' => -500, 'fast' => -750],
            'gain_weight' => ['slow' => 300, 'moderate' => 500],
            'gain_muscle' => ['slow' => 300, 'moderate' => 500],
            'maintain_weight' => ['moderate' => 0]
        ];
        if ($goal === 'maintain_weight') $pace = 'moderate';
        $goal_adjustment = $goal_adjustments[$goal][$pace] ?? null;
        $birth_date = is_valid_date($dob) ? DateTime::createFromFormat('!Y-m-d', $dob) : false;
        $age = $birth_date ? $birth_date->diff(new DateTime('today'))->y : 0;

        if (!$birth_date || $birth_date > new DateTime('today') || $age < 13 || $age > 120) {
            set_flash_message('danger', 'Ngày sinh không hợp lệ; độ tuổi phải từ 13 đến 120.');
        } elseif (!in_array($gender, ['male', 'female'], true) || $height === false || $height < 80 || $height > 250 || $weight === false || $weight < 20 || $weight > 400) {
            set_flash_message('danger', 'Giới tính, chiều cao hoặc cân nặng không hợp lệ.');
        } elseif (!isset($activity_multiplier[$activity]) || !in_array($goal, $valid_goals, true) || $goal_adjustment === null || $meals_per_day === false || $meals_per_day < 1 || $meals_per_day > 6) {
            set_flash_message('danger', 'Mức vận động, mục tiêu, tốc độ mục tiêu hoặc số bữa ăn không hợp lệ.');
        } else {
            $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + ($gender === 'male' ? 5 : -161);
            $tdee = $bmr * $activity_multiplier[$activity];
            $calorie_target = $tdee + $goal_adjustment;
            $protein_target = ($calorie_target * 0.30) / 4;
            $carb_target = ($calorie_target * 0.40) / 4;
            $fat_target = ($calorie_target * 0.30) / 9;
            $data = [
                'user_id' => $user_id,
                'date_of_birth' => $dob,
                'age' => $age,
                'gender' => $gender,
                'height' => $height,
                'current_weight' => $weight,
                'activity_level' => $activity,
                'health_goal' => $goal,
                'goal_pace' => $pace,
                'diet_type' => $_POST['diet_type'] ?? 'normal',
                'allergies' => trim($_POST['allergies'] ?? ''),
                'disliked_foods' => trim($_POST['disliked_foods'] ?? ''),
                'meals_per_day' => $meals_per_day,
                'bmr' => round($bmr, 2),
                'tdee' => round($tdee, 2),
                'calorie_target' => round($calorie_target, 2),
                'protein_target' => round($protein_target, 2),
                'carb_target' => round($carb_target, 2),
                'fat_target' => round($fat_target, 2)
            ];
            if ($profileModel->saveProfile($data)) {
                set_flash_message('success', 'Hồ sơ và mục tiêu năng lượng đã được cập nhật.');
                $profile = $profileModel->getProfileByUserId($user_id);
            } else {
                set_flash_message('danger', 'Không thể lưu hồ sơ, vui lòng thử lại.');
            }
        }
    }
}

$page_title = 'Hồ sơ cá nhân';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
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
                                        <input type="date" class="form-control bg-light border-0" id="dob" name="date_of_birth" max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>" min="<?php echo date('Y-m-d', strtotime('-120 years')); ?>" value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?>" required>
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
                                            <option value="sedentary" <?php echo (($profile['activity_level'] ?? '') === 'sedentary') ? 'selected' : ''; ?>>Ít vận động</option>
                                            <option value="light" <?php echo (($profile['activity_level'] ?? '') === 'light') ? 'selected' : ''; ?>>Vận động nhẹ</option>
                                            <option value="moderate" <?php echo (($profile['activity_level'] ?? '') === 'moderate') ? 'selected' : ''; ?>>Vận động vừa</option>
                                            <option value="very_active" <?php echo (($profile['activity_level'] ?? '') === 'very_active') ? 'selected' : ''; ?>>Vận động nhiều</option>
                                            <option value="extra_active" <?php echo (($profile['activity_level'] ?? '') === 'extra_active') ? 'selected' : ''; ?>>Vận động rất nhiều</option>
                                        </select>
                                        <label for="activity" class="text-muted">Mức độ vận động</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select bg-light border-0" id="goal" name="health_goal" required>
                                            <option value="lose_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'lose_weight') ? 'selected' : ''; ?>>Giảm cân</option>
                                            <option value="gain_weight" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'gain_weight') ? 'selected' : ''; ?>>Tăng cân</option>
                                            <option value="maintain_weight" <?php echo (($profile['health_goal'] ?? '') === 'maintain_weight') ? 'selected' : ''; ?>>Giữ cân</option>
                                            <option value="gain_muscle" <?php echo (isset($profile['health_goal']) && $profile['health_goal'] == 'gain_muscle') ? 'selected' : ''; ?>>Tăng cơ</option>
                                        </select>
                                        <label for="goal" class="text-muted">Mục tiêu sức khỏe</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select bg-light border-0" id="goalPace" name="goal_pace" required>
                                            <option value="slow" <?php echo (($profile['goal_pace'] ?? 'moderate') === 'slow') ? 'selected' : ''; ?>>Chậm</option>
                                            <option value="moderate" <?php echo (($profile['goal_pace'] ?? 'moderate') === 'moderate') ? 'selected' : ''; ?>>Trung Bình</option>
                                            <option value="fast" <?php echo (($profile['goal_pace'] ?? 'moderate') === 'fast') ? 'selected' : ''; ?>>Nhanh</option>
                                        </select>
                                        <label for="goalPace" class="text-muted">Tốc độ mục tiêu</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select bg-light border-0" id="diet" name="diet_type">
                                            <option value="normal" <?php echo (($profile['diet_type'] ?? 'normal') === 'normal') ? 'selected' : ''; ?>>Bình thường</option>
                                            <option value="vegetarian" <?php echo (($profile['diet_type'] ?? '') === 'vegetarian') ? 'selected' : ''; ?>>Ăn chay</option>
                                            <option value="vegan" <?php echo (($profile['diet_type'] ?? '') === 'vegan') ? 'selected' : ''; ?>>Thuần chay</option>
                                            <option value="low_carb" <?php echo (($profile['diet_type'] ?? '') === 'low_carb') ? 'selected' : ''; ?>>Ít carb / Keto</option>
                                            <option value="low_sugar" <?php echo (($profile['diet_type'] ?? '') === 'low_sugar') ? 'selected' : ''; ?>>Ít đường</option>
                                            <option value="gluten_free" <?php echo (($profile['diet_type'] ?? '') === 'gluten_free') ? 'selected' : ''; ?>>Không gluten</option>
                                            <option value="high_protein" <?php echo (($profile['diet_type'] ?? '') === 'high_protein') ? 'selected' : ''; ?>>Giàu protein</option>
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
                                            <h2 class="text-secondary fw-bold mb-0"><?php echo number_format((float)$profile['bmr'], 2, '.', ''); ?> <span class="fs-6 text-muted fw-normal">kcal</span></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-4 bg-white rounded-4 shadow-sm border-0 card-hover h-100 position-relative overflow-hidden">
                                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-warning bg-opacity-10" style="z-index: 0;"></div>
                                        <div class="position-relative z-index-1">
                                            <h6 class="text-muted text-uppercase small fw-bold mb-3">TDEE <i class="bi bi-info-circle ms-1" title="Tổng năng lượng tiêu hao mỗi ngày"></i></h6>
                                            <h2 class="text-warning fw-bold mb-0"><?php echo number_format((float)$profile['tdee'], 2, '.', ''); ?> <span class="fs-6 text-muted fw-normal">kcal</span></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-4 bg-white rounded-4 shadow-sm border-0 card-hover h-100 position-relative overflow-hidden">
                                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-success bg-opacity-10" style="z-index: 0;"></div>
                                        <div class="position-relative z-index-1">
                                            <h6 class="text-success text-uppercase small fw-bold mb-3">Mục tiêu/ngày</h6>
                                            <h2 class="text-success fw-bold mb-0"><?php echo number_format((float)$profile['calorie_target'], 2, '.', ''); ?> <span class="fs-6 text-muted fw-normal">kcal</span></h2>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const goalSelect = document.getElementById('goal');
        const paceSelect = document.getElementById('goalPace');
        if (!goalSelect || !paceSelect) return;

        function syncGoalPace() {
            const goal = goalSelect.value;
            const fastOption = paceSelect.querySelector('option[value="fast"]');
            const isMaintain = goal === 'maintain_weight';
            const isGain = goal === 'gain_weight' || goal === 'gain_muscle';

            if (fastOption) fastOption.disabled = isGain || isMaintain;
            paceSelect.disabled = isMaintain;
            if (isMaintain) paceSelect.value = 'moderate';
            if (isGain && paceSelect.value === 'fast') paceSelect.value = 'moderate';
        }

        goalSelect.addEventListener('change', syncGoalPace);
        syncGoalPace();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>