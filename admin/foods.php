<?php
// admin/foods.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_food') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $target_id = filter_var(
            $_POST['food_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($target_id) {
            try {
                $conn->beginTransaction();

                $foodStmt = $conn->prepare("SELECT id, name FROM foods WHERE id = :id FOR UPDATE");
                $foodStmt->execute([':id' => $target_id]);
                $food = $foodStmt->fetch(PDO::FETCH_ASSOC);

                if (!$food) {
                    $conn->rollBack();
                    set_flash_message('warning', 'Không tìm thấy món ăn để xóa.');
                } else {
                    $referenceStmt = $conn->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM meal_log_items WHERE food_id = :log_food_id) +
                            (SELECT COUNT(*) FROM meal_plan_items WHERE food_id = :plan_food_id) AS total_references
                    ");
                    $referenceStmt->execute([
                        ':log_food_id' => $target_id,
                        ':plan_food_id' => $target_id
                    ]);
                    $referenceCount = (int)$referenceStmt->fetchColumn();

                    if ($referenceCount > 0) {
                        // Giữ dữ liệu lịch sử và ẩn món khỏi thư viện/người dùng.
                        $stmt = $conn->prepare("UPDATE foods SET status = 'inactive' WHERE id = :id");
                        $stmt->execute([':id' => $target_id]);
                        $conn->commit();
                        set_flash_message(
                            'success',
                            'Món “' . $food['name'] . '” đã được ẩn khỏi thư viện vì đang được dùng trong ' . $referenceCount . ' bản ghi lịch sử/thực đơn.'
                        );
                    } else {
                        $stmt = $conn->prepare("DELETE FROM foods WHERE id = :id");
                        $stmt->execute([':id' => $target_id]);
                        $conn->commit();
                        set_flash_message('success', 'Đã xóa món ăn thành công.');
                    }
                }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log('Không thể xóa món ăn #' . $target_id . ': ' . $e->getMessage());
                set_flash_message('danger', 'Không thể xóa món ăn. Vui lòng thử lại hoặc chuyển món sang trạng thái ẩn.');
            }
        } else {
            set_flash_message('warning', 'Món ăn cần xóa không hợp lệ.');
        }
    }
    redirect('/admin/foods.php');
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Đếm tổng số để phân trang
$total_foods = $conn->query("SELECT COUNT(id) FROM foods")->fetchColumn();
$total_pages = ceil($total_foods / $limit);

// Lấy danh sách món ăn
$stmt = $conn->prepare("
    SELECT f.id, f.name, f.image, f.calories, f.protein, f.carbs, f.fat, f.status, c.name as category_name
    FROM foods f 
    LEFT JOIN food_categories c ON f.category_id = c.id
    ORDER BY f.id DESC LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$foods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Món ăn';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Quản lý Món ăn</h3>
                <a href="<?php echo BASE_URL; ?>/admin/food-edit.php" class="btn btn-success"><i class="bi bi-plus-circle me-2"></i>Thêm món mới</a>
            </div>
            <?php display_flash_message(); ?>
            
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tên món ăn</th>
                                    <th>Danh mục</th>
                                    <th>Calories</th>
                                    <th>Protein/Carb/Fat</th>
                                    <th>Trạng thái</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($foods as $f): ?>
                                <tr>
                                    <td><?php echo $f['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($f['image'])): ?>
                                                <img src="<?php echo food_image_url($f['image']); ?>" alt="Hình ảnh" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2 text-muted" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <a href="<?php echo BASE_URL; ?>/admin/food-detail.php?id=<?php echo $f['id']; ?>" class="text-decoration-none fw-bold text-dark">
                                                <?php echo htmlspecialchars($f['name']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-success border border-success"><?php echo htmlspecialchars($f['category_name'] ?? 'Khác'); ?></span></td>
                                    <td class="text-danger fw-bold"><?php echo $f['calories']; ?> kcal</td>
                                    <td class="text-muted small">
                                        P: <?php echo $f['protein']; ?>g | C: <?php echo $f['carbs']; ?>g | F: <?php echo $f['fat']; ?>g
                                    </td>
                                    <td>
                                        <?php if ($f['status'] === 'active'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Đã ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="<?php echo BASE_URL; ?>/admin/food-edit.php?id=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" onsubmit="return confirm('Xóa món ăn này? Nếu món đã có trong lịch sử hoặc thực đơn, hệ thống sẽ ẩn món để bảo toàn dữ liệu.');" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete_food">
                                                <input type="hidden" name="food_id" value="<?php echo $f['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa hoặc ẩn món"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sau</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
