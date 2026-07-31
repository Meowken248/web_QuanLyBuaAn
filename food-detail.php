<?php
// food-detail.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/models/FoodModel.php';

$food_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($food_id <= 0) {
    header('Location: ' . BASE_URL . '/foods.php');
    exit;
}

$foodModel = new FoodModel();
// We'll borrow getFoods logic but for a single item by id. Wait, getFoodById is better.
$food = $foodModel->getFoodById($food_id);

if (!$food) {
    header('Location: ' . BASE_URL . '/foods.php');
    exit;
}

$page_title = $food['name'];
require_once __DIR__ . '/includes/header.php';

$img_src = food_image_url($food['image'] ?? null);
?>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>" class="text-success text-decoration-none">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/foods.php" class="text-success text-decoration-none">Thư viện món ăn</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($food['name']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Ảnh và thông tin cơ bản -->
        <div class="col-lg-6 mb-4">
            <img src="<?php echo $img_src; ?>" class="img-fluid rounded-4 shadow w-100 mb-4" alt="<?php echo htmlspecialchars($food['name']); ?>" style="max-height: 450px; object-fit: cover;">
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-basket me-2"></i>Nguyên liệu</h5>
                    <div class="bg-success bg-opacity-10 p-3 rounded-4">
                        <?php if (!empty(trim($food['ingredients'] ?? ''))): ?>
                            <p class="mb-0 text-dark" style="white-space: pre-line;"><?php echo htmlspecialchars($food['ingredients']); ?></p>
                        <?php else: ?>
                            <p class="mb-0 text-muted fst-italic">Chưa có thông tin nguyên liệu.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-book me-2"></i>Cách làm</h5>
                    <div class="bg-primary bg-opacity-10 p-3 rounded-4">
                        <?php if (!empty(trim($food['instructions'] ?? ''))): ?>
                            <p class="mb-0 text-dark" style="white-space: pre-line;"><?php echo htmlspecialchars($food['instructions']); ?></p>
                        <?php else: ?>
                            <p class="mb-0 text-muted fst-italic">Chưa có thông tin cách làm.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Chi tiết dinh dưỡng -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <span class="badge bg-success-subtle text-success fs-6 mb-3 px-3 py-2 rounded-pill"><?php echo htmlspecialchars($food['category_name'] ?? 'Chưa phân loại'); ?></span>
                    <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($food['name']); ?></h1>
                    
                    <p class="text-muted fs-5 mb-4"><?php echo nl2br(htmlspecialchars($food['description'] ?? 'Món ăn giàu dinh dưỡng, phù hợp cho mọi chế độ ăn uống lành mạnh.')); ?></p>
                    
                    <div class="bg-light p-4 rounded-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Thành phần dinh dưỡng</h5>
                            <span class="text-muted small">Khẩu phần: <?php echo $food['serving_size']; ?> <?php echo htmlspecialchars($food['serving_unit']); ?></span>
                        </div>
                        
                        <div class="row g-3 text-center mb-4">
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded shadow-sm border border-danger-subtle">
                                    <div class="text-danger fw-bold fs-3"><?php echo $food['calories']; ?></div>
                                    <div class="text-muted small fw-semibold text-uppercase">Kcal</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded shadow-sm border border-primary-subtle">
                                    <div class="text-primary fw-bold fs-3"><?php echo $food['protein']; ?>g</div>
                                    <div class="text-muted small fw-semibold text-uppercase">Protein</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded shadow-sm border border-warning-subtle">
                                    <div class="text-warning fw-bold fs-3"><?php echo $food['carbs']; ?>g</div>
                                    <div class="text-muted small fw-semibold text-uppercase">Carbs</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white p-3 rounded shadow-sm border border-info-subtle">
                                    <div class="text-info fw-bold fs-3"><?php echo $food['fat']; ?>g</div>
                                    <div class="text-muted small fw-semibold text-uppercase">Fat</div>
                                </div>
                            </div>
                        </div>

                        <ul class="list-group list-group-flush bg-transparent">
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-muted"><i class="bi bi-asterisk text-success me-2"></i>Chất xơ (Fiber)</span>
                                <span class="fw-bold"><?php echo $food['fiber'] ?? 0; ?>g</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-muted"><i class="bi bi-asterisk text-success me-2"></i>Đường (Sugar)</span>
                                <span class="fw-bold"><?php echo $food['sugar'] ?? 0; ?>g</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-muted"><i class="bi bi-asterisk text-success me-2"></i>Natri (Sodium)</span>
                                <span class="fw-bold"><?php echo $food['sodium'] ?? 0; ?>mg</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0 pb-0">
                                <span class="text-muted"><i class="bi bi-asterisk text-success me-2"></i>Cholesterol</span>
                                <span class="fw-bold"><?php echo $food['cholesterol'] ?? 0; ?>mg</span>
                            </li>
                        </ul>
                    </div>

                    <div class="d-flex gap-2 mt-auto">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo BASE_URL; ?>/user/add-meal.php?food_id=<?php echo $food['id']; ?>" class="btn btn-success btn-lg flex-grow-1 fw-bold shadow-sm">
                                <i class="bi bi-plus-circle me-2"></i>Thêm vào nhật ký
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn btn-success btn-lg flex-grow-1 fw-bold shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập để thêm
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/foods.php" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
