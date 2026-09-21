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
$field_errors = [];

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
        $field_errors['name'] = 'Vui lòng nhập tên danh mục.';
    } else {
        $check_name_sql = "SELECT id FROM food_categories WHERE name = :name";
        $name_params = [':name' => $name];
        if ($is_edit) {
            $check_name_sql .= " AND id != :id";
            $name_params[':id'] = $id;
        }
        $stmt_check_name = $conn->prepare($check_name_sql);
        $stmt_check_name->execute($name_params);
        if ($stmt_check_name->fetch()) {
            $field_errors['name'] = 'Tên danh mục này đã tồn tại, vui lòng chọn tên khác.';
        }
    }
    
    if (empty($field_errors)) {
        // Đảm bảo slug là duy nhất
        $original_slug = $slug;
        $counter = 1;
        while (true) {
            $check_sql = "SELECT id FROM food_categories WHERE slug = :slug";
            $params = [':slug' => $slug];
            if ($is_edit) {
                $check_sql .= " AND id != :id";
                $params[':id'] = $id;
            }
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->execute($params);
            if (!$stmt_check->fetch()) {
                break;
            }
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

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
    } else {
        $_SESSION['error'] = 'Vui lòng kiểm tra lại thông tin nhập liệu.';
    }
}

$page_title = $is_edit ? 'Sửa Danh mục' : 'Thêm Danh mục mới';
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
                <a href="<?php echo BASE_URL; ?>/admin/food-categories.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                <div class="card-body">
                    <form method="POST" action="" id="categoryForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Tên danh mục <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($field_errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo old('name', $category['name'] ?? ''); ?>" required autocomplete="off">
                            <div class="invalid-feedback" id="name_error"><?php echo htmlspecialchars($field_errors['name'] ?? 'Vui lòng nhập tên danh mục.'); ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label fw-bold">Đường dẫn (Slug)</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?php echo old('slug', $category['slug'] ?? ''); ?>" placeholder="Để trống để tự động tạo từ tên danh mục" autocomplete="off">
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
                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-4 shadow-sm">
                                <i class="bi bi-save me-1"></i><?php echo $is_edit ? 'Cập nhật' : 'Lưu Danh mục'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('categoryForm');
    const nameInput = document.getElementById('name');

    function validateName(trigger = 'blur') {
        if (!nameInput) return true;
        const val = nameInput.value.trim();
        const err = document.getElementById('name_error');
        if (!val) {
            if (trigger === 'submit' || trigger === 'blur') {
                nameInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập tên danh mục.';
                return false;
            }
            return true;
        }
        if (val.length < 2) {
            nameInput.classList.add('is-invalid');
            if (err) err.textContent = 'Tên danh mục phải có ít nhất 2 ký tự.';
            return false;
        }
        nameInput.classList.remove('is-invalid');
        return true;
    }

    if (nameInput) {
        nameInput.addEventListener('input', () => validateName('input'));
        nameInput.addEventListener('blur', () => validateName('blur'));
    }

    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopPropagation();

            if (validateName('submit')) {
                form.submit();
            } else {
                nameInput.focus();
            }
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>
