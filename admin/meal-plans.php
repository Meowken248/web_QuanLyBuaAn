<?php
// admin/meal-plans.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die('CSRF token error');
    $id = (int)$_POST['id'];
    
    // Xóa cascade an toàn hoặc trigger nếu có foreign key, tạm thời xóa dữ liệu thủ công
    $conn->prepare("DELETE FROM favorite_meal_plans WHERE meal_plan_id = :id")->execute([':id' => $id]);
    $conn->prepare("DELETE FROM meal_plan_items WHERE meal_plan_meal_id IN (SELECT id FROM meal_plan_meals WHERE meal_plan_id = :id)")->execute([':id' => $id]);
    $conn->prepare("DELETE FROM meal_plan_meals WHERE meal_plan_id = :id")->execute([':id' => $id]);
    $conn->prepare("DELETE FROM meal_plans WHERE id = :id")->execute([':id' => $id]);
    
    $_SESSION['success'] = 'Đã xóa Kế hoạch Bữa ăn thành công.';
    redirect('/admin/meal-plans.php');
}

$stmt = $conn->query("SELECT * FROM meal_plans ORDER BY created_at DESC");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'meal-plans.php';
$page_title = 'Quản lý Thực đơn / Kế hoạch Bữa ăn';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Thực đơn Mẫu (Meal Plans)</h2>
                <a href="<?php echo BASE_URL; ?>/admin/meal-plan-edit.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Thêm Thực đơn mới
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tên thực đơn</th>
                                    <th>Mục tiêu</th>
                                    <th>Calories</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $p): ?>
                                <tr>
                                    <td>#<?php echo $p['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($p['image']): ?>
                                                <img src="<?php echo BASE_URL . '/uploads/meal_plans/' . $p['image']; ?>" class="rounded me-3" style="width:50px; height:50px; object-fit:cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center text-muted" style="width:50px; height:50px;"><i class="bi bi-image"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($p['name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($p['diet_type']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $g = $p['goal_type'];
                                            echo $g == 'lose_weight' ? 'Giảm cân' : ($g == 'gain_weight' ? 'Tăng cân' : ($g == 'gain_muscle' ? 'Tăng cơ' : 'Giữ cân'));
                                        ?>
                                    </td>
                                    <td><span class="badge bg-success"><?php echo $p['total_calories']; ?> kcal</span></td>
                                    <td>
                                        <?php if ($p['status'] == 'active'): ?>
                                            <span class="badge bg-success">Đang bật</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Đang tắt</span>
                                        <?php endif; ?>

                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>/admin/meal-plan-builder.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-info text-white" title="Xây dựng thực đơn"><i class="bi bi-list-check"></i> Chi tiết bữa ăn</a>
                                        <a href="<?php echo BASE_URL; ?>/admin/meal-plan-edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa thực đơn này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($plans)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">Chưa có thực đơn nào.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
