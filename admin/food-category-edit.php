<?php
// admin/food-category-edit.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$category = null;

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM food_categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        $_SESSION['error'] = 'Danh mục không tồn tại.';
        redirect('/admin/food-categories.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }
    
    $name = trim($_POST['name'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $slug = trim($_POST['slug'] ?? '');
    
    if (empty($slug)) {
        // Tự động tạo slug nếu trống
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
    }

    if (empty($name)) {
        $_SESSION['error'] = 'Vui lòng nhập tên danh mục.';
    } else {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE food_categories SET name = :name, slug = :slug, status = :status WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':status' => $status,
                ':id' => $id
            ]);
            $_SESSION['success'] = 'Cập nhật danh mục thành công.';
        } else {
            $stmt = $conn->prepare("INSERT INTO food_categories (name, slug, status) VALUES (:name, :slug, :status)");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':status' => $status
            ]);
            $_SESSION['success'] = 'Thêm danh mục mới thành công.';
        }
        redirect('/admin/food-categories.php');
    }
}

$page_title = $is_edit ? 'Sửa Danh mục' : 'Thêm Danh mục mới';
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
                <h3 class="fw-bold mb-0"><?php echo $page_title; ?></h3>
                <a href="<?php echo BASE_URL; ?>/admin/food-categories.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Tên danh mục <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $category['name'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label fw-bold">Đường dẫn (Slug)</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?php echo htmlspecialchars($_POST['slug'] ?? $category['slug'] ?? ''); ?>" placeholder="Để trống để tự động tạo từ tên danh mục">
                            <div class="form-text">Chuỗi URL thân thiện. Ví dụ: do-uong, mon-chinh</div>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label fw-bold">Trạng thái</label>
                            <select class="form-select" id="status" name="status">
                                <?php $current_status = $_POST['status'] ?? $category['status'] ?? 'active'; ?>
                                <option value="active" <?php echo $current_status === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="inactive" <?php echo $current_status === 'inactive' ? 'selected' : ''; ?>>Tạm ẩn</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i><?php echo $is_edit ? 'Cập nhật' : 'Lưu Danh mục'; ?>
                            </button>
                        </div>
                    </form>
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
