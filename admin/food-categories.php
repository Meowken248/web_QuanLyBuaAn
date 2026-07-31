<?php
// admin/food-categories.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Xử lý xóa danh mục
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    
    $id = (int)$_POST['id'];
    
    // Kiểm tra xem danh mục có chứa món ăn nào không
    $stmt = $conn->prepare("SELECT COUNT(*) FROM foods WHERE category_id = :id");
    $stmt->execute([':id' => $id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        // Không cho phép xóa nếu đang chứa món ăn
        $_SESSION['error'] = 'Không thể xóa danh mục này vì đang chứa ' . $count . ' món ăn. Vui lòng chuyển các món ăn sang danh mục khác trước.';
    } else {
        $stmt = $conn->prepare("DELETE FROM food_categories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = 'Đã xóa danh mục thành công.';
    }
    redirect('/admin/food-categories.php');
}

// Lấy danh sách danh mục
$query = "SELECT c.*, (SELECT COUNT(*) FROM foods WHERE category_id = c.id) as total_foods FROM food_categories c ORDER BY c.name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Danh mục Món ăn';
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
                <h3 class="fw-bold mb-0">Quản lý Danh mục Món ăn</h3>
                <a href="<?php echo BASE_URL; ?>/admin/food-category-edit.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Thêm Danh mục
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tên danh mục</th>
                                    <th>Đường dẫn (Slug)</th>
                                    <th>Trạng thái</th>
                                    <th>Số món ăn</th>
                                    <th class="text-end">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?php echo $cat['id']; ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                    <td>
                                        <?php if ($cat['status'] === 'active'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tạm ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark rounded-pill"><?php echo $cat['total_foods']; ?> món</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>/admin/food-category-edit.php?id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa" <?php echo ($cat['total_foods'] > 0) ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Chưa có danh mục nào.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (isset($hide_footer) && $hide_footer) {
    echo '</body></html>';
} else {
    require_once __DIR__ . '/../includes/footer.php'; 
}
?>
