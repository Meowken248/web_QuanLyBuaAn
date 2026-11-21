<?php
// admin/food-categories.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Tải file mẫu Excel danh mục (BUG-18)
if (isset($_GET['action']) && $_GET['action'] === 'sample_excel') {
    require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
    $data = [
        ['STT', 'Tên Danh Mục', 'Đường Dẫn (Slug - để trống để tự tạo)', 'Mô Tả', 'Trạng Thái (active/inactive)'],
        [1, 'Món Cơm', 'mon-com', 'Các món cơm dinh dưỡng hàng ngày', 'active'],
        [2, 'Món Nước & Canh', 'mon-nuoc-canh', 'Các món bún, phở, miến và canh thanh mát', 'active'],
        [3, 'Món Salad & Healthy', '', 'Salad rau củ quả và món ít calo', 'active'],
        [4, 'Tráng Miệng & Sinh Tố', '', 'Trái cây tươi, sinh tố và đồ ngọt bổ dưỡng', 'active']
    ];
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs("Mau_DanhSach_DanhMuc.xlsx");
    exit;
}

// Xử lý Import Excel danh mục (BUG-18)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_excel') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Phiên làm việc không hợp lệ.';
        redirect('/admin/food-categories.php');
    }
    
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            require_once __DIR__ . '/../includes/SimpleXLSX.php';
            if ($xlsx = Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
                $successCount = 0;
                $skipCount = 0;
                $checkStmt = $conn->prepare("SELECT id FROM food_categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
                $insertStmt = $conn->prepare("INSERT INTO food_categories (name, slug, description, status) VALUES (:name, :slug, :description, :status)");

                /** @var array<int, array<int, mixed>> $excelRows */
                $excelRows = (array)$xlsx->rows();
                foreach ($excelRows as $i => $row) {
                    /** @var array<int, mixed> $row */
                    if ($i === 0) continue; // Bỏ qua tiêu đề
                    $name = trim($row[1] ?? '');
                    if (empty($name)) continue;

                    // Kiểm tra trùng lặp tên danh mục
                    $checkStmt->execute([':name' => $name]);
                    if ($checkStmt->fetch()) {
                        $skipCount++;
                        continue;
                    }

                    $slug = trim($row[2] ?? '');
                    if (empty($slug)) {
                        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
                    }
                    if (empty($slug)) $slug = 'danh-muc-' . time() . '-' . rand(100, 999);

                    // Đảm bảo slug là duy nhất
                    $checkSlugStmt = $conn->prepare("SELECT id FROM food_categories WHERE slug = :slug LIMIT 1");
                    $original_slug = $slug;
                    $counter = 1;
                    while (true) {
                        $checkSlugStmt->execute([':slug' => $slug]);
                        if (!$checkSlugStmt->fetch()) break;
                        $slug = $original_slug . '-' . $counter++;
                    }

                    $description = trim($row[3] ?? '');
                    $status = strtolower(trim($row[4] ?? '')) === 'inactive' ? 'inactive' : 'active';

                    $insertStmt->execute([
                        ':name' => $name,
                        ':slug' => $slug,
                        ':description' => $description,
                        ':status' => $status
                    ]);
                    $successCount++;
                }

                $_SESSION['success'] = "Đã nhập thành công $successCount danh mục." . ($skipCount > 0 ? " (Đã bỏ qua $skipCount danh mục bị trùng tên)" : "");
            } else {
                $_SESSION['error'] = 'Lỗi đọc file Excel: ' . Shuchkin\SimpleXLSX::parseError();
            }
        } else {
            $_SESSION['error'] = 'Vui lòng upload file đúng định dạng .xlsx';
        }
    } else {
        $_SESSION['error'] = 'Lỗi khi upload file.';
    }
    redirect('/admin/food-categories.php');
}

// Xử lý Thêm / Sửa danh mục bằng Modal trên trang (In-page CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_category') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Phiên làm việc không hợp lệ.';
        redirect('/admin/food-categories.php');
    }

    $cat_id = filter_var($_POST['cat_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

    if (empty($name)) {
        $_SESSION['error'] = 'Vui lòng nhập tên danh mục.';
        redirect('/admin/food-categories.php');
    }

    // Kiểm tra trùng tên danh mục
    $checkNameSql = "SELECT id FROM food_categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))";
    $checkNameParams = [':name' => $name];
    if ($cat_id) {
        $checkNameSql .= " AND id != :id";
        $checkNameParams[':id'] = $cat_id;
    }
    $chkNameStmt = $conn->prepare($checkNameSql);
    $chkNameStmt->execute($checkNameParams);
    if ($chkNameStmt->fetch()) {
        $_SESSION['error'] = 'Tên danh mục này đã tồn tại, vui lòng chọn tên khác.';
        redirect('/admin/food-categories.php');
    }

    // Tự động tạo slug nếu để trống
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
    }
    if (empty($slug)) $slug = 'danh-muc-' . time();

    // Đảm bảo slug là duy nhất
    $orig_slug = $slug;
    $counter = 1;
    while (true) {
        $checkSlugSql = "SELECT id FROM food_categories WHERE slug = :slug";
        $slugParams = [':slug' => $slug];
        if ($cat_id) {
            $checkSlugSql .= " AND id != :id";
            $slugParams[':id'] = $cat_id;
        }
        $chkSlugStmt = $conn->prepare($checkSlugSql);
        $chkSlugStmt->execute($slugParams);
        if (!$chkSlugStmt->fetch()) {
            break;
        }
        $slug = $orig_slug . '-' . $counter++;
    }

    if ($cat_id) {
        $stmt = $conn->prepare("UPDATE food_categories SET name = :name, slug = :slug, description = :description, status = :status WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':status' => $status,
            ':id' => $cat_id
        ]);
        $_SESSION['success'] = 'Cập nhật danh mục “' . htmlspecialchars($name) . '” thành công.';
    } else {
        $stmt = $conn->prepare("INSERT INTO food_categories (name, slug, description, status) VALUES (:name, :slug, :description, :status)");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':status' => $status
        ]);
        $_SESSION['success'] = 'Thêm danh mục mới “' . htmlspecialchars($name) . '” thành công.';
    }
    redirect('/admin/food-categories.php');
}

// Xử lý xóa danh mục
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$id) {
            $_SESSION['error'] = 'Danh mục cần xóa không hợp lệ.';
        } else {
            try {
                $conn->beginTransaction();

                $categoryStmt = $conn->prepare("SELECT id, name FROM food_categories WHERE id = :id FOR UPDATE");
                $categoryStmt->execute([':id' => $id]);
                $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

                if (!$category) {
                    $conn->rollBack();
                    $_SESSION['error'] = 'Danh mục không tồn tại hoặc đã được xóa.';
                } else {
                    // Bảo toàn món ăn: chuyển về chưa phân loại trước khi xóa danh mục.
                    $moveStmt = $conn->prepare("UPDATE foods SET category_id = NULL WHERE category_id = :id");
                    $moveStmt->execute([':id' => $id]);
                    $movedFoods = $moveStmt->rowCount();

                    $deleteStmt = $conn->prepare("DELETE FROM food_categories WHERE id = :id");
                    $deleteStmt->execute([':id' => $id]);
                    $conn->commit();

                    $_SESSION['success'] = 'Đã xóa danh mục “' . $category['name'] . '”.'
                        . ($movedFoods > 0 ? ' ' . $movedFoods . ' món ăn đã được chuyển sang chưa phân loại.' : '');
                }
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log('Không thể xóa danh mục #' . $id . ': ' . $e->getMessage());
                $_SESSION['error'] = 'Không thể xóa danh mục. Vui lòng thử lại.';
            }
        }
    }
    redirect('/admin/food-categories.php');
}

// Phân trang danh mục món ăn (BUG-18: 10 danh mục / trang)
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$totalCategories = (int)$conn->query("SELECT COUNT(*) FROM food_categories")->fetchColumn();
$totalPages = ceil($totalCategories / $limit);

$query = "SELECT c.*, (SELECT COUNT(*) FROM foods WHERE category_id = c.id) as total_foods 
          FROM food_categories c 
          ORDER BY c.id DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Danh mục Món ăn';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h3 class="fw-bold mb-0">Quản lý Danh mục Món ăn</h3>
                    <p class="text-muted small mb-0">Tổng cộng: <?php echo $totalCategories; ?> danh mục (10 danh mục/trang)</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>/admin/food-categories.php?action=sample_excel" class="btn btn-sm btn-outline-secondary rounded-pill">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Tải file mẫu Excel
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i>Nhập từ Excel
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="openCategoryModal(null)">
                        <i class="bi bi-plus-circle me-1"></i>Thêm Danh mục
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead style="background: rgba(243, 244, 246, 0.7);">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Tên danh mục</th>
                                    <th>Đường dẫn (Slug)</th>
                                    <th>Trạng thái</th>
                                    <th>Số món ăn</th>
                                    <th class="text-end pe-4">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="ps-4">#<?php echo $cat['id']; ?></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                    <td>
                                        <?php if ($cat['status'] === 'active'): ?>
                                            <span class="badge bg-success rounded-pill px-3">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill px-3">Tạm ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark rounded-pill px-3"><?php echo $cat['total_foods']; ?> món</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill me-1" title="Sửa" onclick='openCategoryModal(<?php echo htmlspecialchars(json_encode($cat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa danh mục này? Các món bên trong sẽ được giữ lại và chuyển sang chưa phân loại.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Xóa danh mục">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        Chưa có danh mục nào. Hãy thêm hoặc nhập từ file Excel!
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Phân trang 10 danh mục / trang (BUG-18) -->
                    <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-center py-3 border-top">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link rounded-start-pill" href="?page=<?php echo $page - 1; ?>">&laquo;</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link rounded-end-pill" href="?page=<?php echo $page + 1; ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Import Excel Danh Mục (BUG-18) -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="import_excel">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="importExcelModalLabel">
                        <i class="bi bi-file-earmark-excel text-success me-2"></i>Nhập Danh Mục từ File Excel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Vui lòng tải lên file Excel (<code>.xlsx</code>) theo mẫu chuẩn. Nếu chưa có file mẫu, hãy nhấp vào nút "Tải file mẫu Excel" bên dưới.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Chọn file Excel (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control rounded-3" name="excel_file" accept=".xlsx" required>
                    </div>
                    <div class="text-end">
                        <a href="<?php echo BASE_URL; ?>/admin/food-categories.php?action=sample_excel" class="text-success small fw-bold text-decoration-none">
                            <i class="bi bi-download me-1"></i>Tải file mẫu tại đây
                        </a>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-upload me-1"></i>Tiến hành Nhập
                    </button>
                </div>
            </form>
        </div>
</div>
    </div>
</div>

<!-- Modal Thêm / Sửa Danh Mục (In-Page CRUD) -->
<div class="modal fade" id="categoryFormModal" tabindex="-1" aria-labelledby="categoryFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" id="categoryForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="cat_id" id="cat_modal_id" value="0">

                <div class="modal-header border-0 pb-0 bg-health text-white p-4">
                    <h5 class="modal-title fw-bold" id="categoryFormModalLabel">
                        <i class="bi bi-tag-fill me-2"></i><span id="cat_modal_title">Thêm Danh Mục Mới</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="cat_modal_name" class="form-label fw-bold">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="cat_modal_name" name="name" required placeholder="Ví dụ: Món Cơm, Món Nước..." maxlength="100" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label for="cat_modal_slug" class="form-label fw-bold">Đường dẫn (Slug)</label>
                        <input type="text" class="form-control rounded-3" id="cat_modal_slug" name="slug" placeholder="Tự động tạo từ tên nếu để trống" maxlength="150" autocomplete="off">
                        <div class="form-text small">Đường dẫn tĩnh thân thiện (URL). Có thể để trống hệ thống sẽ tự sinh.</div>
                    </div>
                    <div class="mb-3">
                        <label for="cat_modal_desc" class="form-label fw-bold">Mô tả danh mục</label>
                        <textarea class="form-control rounded-3" id="cat_modal_desc" name="description" rows="3" placeholder="Mô tả ngắn về nhóm món ăn trong danh mục này..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="cat_modal_status" class="form-label fw-bold">Trạng thái</label>
                        <select class="form-select rounded-3" id="cat_modal_status" name="status">
                            <option value="active">Hoạt động (Hiển thị công khai)</option>
                            <option value="inactive">Tạm ẩn (Không hiển thị)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="cat_modal_submit_btn">
                        <i class="bi bi-check-circle me-1"></i>Lưu Danh Mục
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCategoryModal(cat) {
    const modalEl = document.getElementById('categoryFormModal');
    const modalTitle = document.getElementById('cat_modal_title');
    const catIdInput = document.getElementById('cat_modal_id');
    const nameInput = document.getElementById('cat_modal_name');
    const slugInput = document.getElementById('cat_modal_slug');
    const descInput = document.getElementById('cat_modal_desc');
    const statusSelect = document.getElementById('cat_modal_status');
    const submitBtn = document.getElementById('cat_modal_submit_btn');

    if (!cat) {
        modalTitle.textContent = 'Thêm Danh Mục Mới';
        catIdInput.value = '0';
        nameInput.value = '';
        slugInput.value = '';
        descInput.value = '';
        statusSelect.value = 'active';
        submitBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Thêm Mới';
    } else {
        modalTitle.textContent = 'Chỉnh Sửa Danh Mục: ' + cat.name;
        catIdInput.value = cat.id || 0;
        nameInput.value = cat.name || '';
        slugInput.value = cat.slug || '';
        descInput.value = cat.description || '';
        statusSelect.value = cat.status || 'active';
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Cập Nhật';
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    setTimeout(() => { nameInput.focus(); }, 400);
}
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>
