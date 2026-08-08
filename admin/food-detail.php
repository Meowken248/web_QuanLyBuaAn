<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/FoodModel.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    redirect('/auth/login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    redirect('/admin/foods.php');
}

$db = new Database();
$conn = $db->getConnection();
$foodModel = new FoodModel($conn);

$food = $foodModel->getFoodById($id);
if (!$food) {
    redirect('/admin/foods.php');
}

$stmt = $conn->prepare("SELECT name FROM food_categories WHERE id = :id");
$stmt->execute([':id' => $food['category_id']]);
$category = $stmt->fetchColumn();

$hide_footer = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Chi tiết Món ăn: <?php echo htmlspecialchars($food['name']); ?></h3>
                <div>
                    <a href="<?php echo BASE_URL; ?>/admin/foods.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left me-2"></i>Quay lại</a>
                    <a href="<?php echo BASE_URL; ?>/admin/food-edit.php?id=<?php echo $food['id']; ?>" class="btn btn-primary"><i class="bi bi-pencil me-2"></i>Sửa món ăn</a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Hình ảnh & Thông tin cơ bản -->
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <?php if (!empty($food['image'])): ?>
                            <img src="<?php echo food_image_url($food['image']); ?>" class="card-img-top" alt="Image" style="height: 250px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
                                <i class="bi bi-image text-muted fs-1"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="fw-bold"><?php echo htmlspecialchars($food['name']); ?></h5>
                            <p class="text-muted mb-2">Danh mục: <span class="badge bg-success"><?php echo htmlspecialchars($category ?: 'Không xác định'); ?></span></p>
                            <p class="text-muted mb-3">Trạng thái: 
                                <?php if (isset($food['status']) && $food['status'] === 'active'): ?>
                                    <span class="badge bg-primary">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Ẩn</span>
                                <?php endif; ?>
                            </p>
                            
                            <h6 class="fw-bold mt-4 border-bottom pb-2">Mô tả</h6>
                            <p class="small text-muted"><?php echo nl2br(htmlspecialchars($food['description'] ?? 'Chưa có mô tả.')); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Thành phần dinh dưỡng -->
                <div class="col-md-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="fw-bold mb-0 text-success"><i class="bi bi-heart-pulse me-2"></i>Thành phần dinh dưỡng</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 text-center mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="bg-light p-3 rounded border border-danger-subtle h-100 d-flex flex-column justify-content-center">
                                        <div class="text-danger fw-bold fs-4 text-nowrap"><?php echo floatval($food['calories']); ?></div>
                                        <div class="text-muted small fw-semibold text-uppercase mt-1">Kcal</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="bg-light p-3 rounded border border-primary-subtle h-100 d-flex flex-column justify-content-center">
                                        <div class="text-primary fw-bold fs-4 text-nowrap"><?php echo floatval($food['protein']); ?>g</div>
                                        <div class="text-muted small fw-semibold text-uppercase mt-1">Protein</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="bg-light p-3 rounded border border-warning-subtle h-100 d-flex flex-column justify-content-center">
                                        <div class="text-warning fw-bold fs-4 text-nowrap"><?php echo floatval($food['carbs']); ?>g</div>
                                        <div class="text-muted small fw-semibold text-uppercase mt-1">Carbs</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="bg-light p-3 rounded border border-info-subtle h-100 d-flex flex-column justify-content-center">
                                        <div class="text-info fw-bold fs-4 text-nowrap"><?php echo floatval($food['fat']); ?>g</div>
                                        <div class="text-muted small fw-semibold text-uppercase mt-1">Fat</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mt-4 text-nowrap">
                                    <thead class="table-light">
                                        <tr>
                                            <th colspan="2" class="text-center">Chi tiết bổ sung</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="w-50 text-muted"><i class="bi bi-asterisk text-success me-2"></i>Chất xơ (Fiber)</td>
                                            <td class="fw-bold"><?php echo floatval($food['fiber'] ?? 0); ?>g</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="bi bi-asterisk text-success me-2"></i>Đường (Sugar)</td>
                                            <td class="fw-bold"><?php echo floatval($food['sugar'] ?? 0); ?>g</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
