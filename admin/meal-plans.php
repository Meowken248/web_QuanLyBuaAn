<?php
// admin/meal-plans.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Xử lý Thêm / Sửa Thực đơn bằng Modal trên trang (In-page CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_meal_plan') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Phiên làm việc không hợp lệ.';
        redirect('/admin/meal-plans.php');
    }

    $plan_id = filter_var($_POST['plan_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'] ?? '')));
    $description = trim($_POST['description'] ?? '');
    $goal_type = in_array($_POST['goal_type'] ?? '', ['lose_weight', 'gain_weight', 'gain_muscle', 'maintain_weight'], true) ? $_POST['goal_type'] : 'lose_weight';
    $diet_type = trim($_POST['diet_type'] ?? '');
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

    if (empty($name)) {
        $_SESSION['error'] = 'Vui lòng nhập tên thực đơn.';
        redirect('/admin/meal-plans.php');
    }

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
    }
    if (empty($slug)) $slug = 'thuc-don-' . time();

    // Lấy ảnh cũ nếu đang sửa
    $image = null;
    if ($plan_id) {
        $stOld = $conn->prepare("SELECT image FROM meal_plans WHERE id = :id");
        $stOld->execute([':id' => $plan_id]);
        $image = $stOld->fetchColumn() ?: null;
    }

    // Xử lý upload ảnh
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/meal_plans/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed, true)) {
            $new_name = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_name)) {
                if ($image && file_exists($upload_dir . $image)) @unlink($upload_dir . $image);
                $image = $new_name;
            }
        }
    }

    if ($plan_id) {
        $stmt = $conn->prepare("UPDATE meal_plans SET name=:n, slug=:s, description=:d, goal_type=:g, diet_type=:dt, image=:img, is_premium=0, status=:st WHERE id=:id");
        $stmt->execute([':n'=>$name, ':s'=>$slug, ':d'=>$description, ':g'=>$goal_type, ':dt'=>$diet_type, ':img'=>$image, ':st'=>$status, ':id'=>$plan_id]);
        $_SESSION['success'] = 'Đã cập nhật thực đơn “' . htmlspecialchars($name) . '” thành công.';
    } else {
        $stmt = $conn->prepare("INSERT INTO meal_plans (name, slug, description, goal_type, diet_type, image, status, created_by) VALUES (:n, :s, :d, :g, :dt, :img, :st, :cb)");
        $stmt->execute([':n'=>$name, ':s'=>$slug, ':d'=>$description, ':g'=>$goal_type, ':dt'=>$diet_type, ':img'=>$image, ':st'=>$status, ':cb'=>$_SESSION['user_id'] ?? 1]);
        $_SESSION['success'] = 'Đã thêm thực đơn mới “' . htmlspecialchars($name) . '” thành công.';
    }
    redirect('/admin/meal-plans.php');
}

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
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="openMealPlanModal(null)">
                    <i class="bi bi-plus-circle me-1"></i>Thêm Thực đơn mới
                </button>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead style="background: rgba(243, 244, 246, 0.7);">
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
                                        <a href="<?php echo BASE_URL; ?>/admin/meal-plan-builder.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-info rounded-pill" title="Xây dựng thực đơn"><i class="bi bi-list-check"></i> Chi tiết bữa ăn</a>
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill" title="Sửa" onclick='openMealPlanModal(<?php echo htmlspecialchars(json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa thực đơn này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i></button>
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

<!-- Modal Thêm / Sửa Kế Hoạch Bữa Ăn (In-Page CRUD) -->
<div class="modal fade" id="mealPlanFormModal" tabindex="-1" aria-labelledby="mealPlanFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" id="mealPlanForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="save_meal_plan">
                <input type="hidden" name="plan_id" id="plan_modal_id" value="0">

                <div class="modal-header border-0 pb-0 bg-health text-white p-4">
                    <h5 class="modal-title fw-bold" id="mealPlanFormModalLabel">
                        <i class="bi bi-calendar2-check me-2"></i><span id="plan_modal_title">Thêm Thực Đơn Mới</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="plan_modal_name" class="form-label fw-bold">Tên thực đơn <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="plan_modal_name" name="name" required placeholder="Ví dụ: Thực đơn Eat Clean 7 ngày..." maxlength="150" autocomplete="off">
                        </div>
                        <div class="col-md-5">
                            <label for="plan_modal_slug" class="form-label fw-bold">Đường dẫn thân thiện (Slug)</label>
                            <input type="text" class="form-control rounded-3" id="plan_modal_slug" name="slug" placeholder="Tự động tạo từ tên nếu để trống" maxlength="150" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="plan_modal_goal" class="form-label fw-bold">Mục tiêu dinh dưỡng</label>
                            <select class="form-select rounded-3" id="plan_modal_goal" name="goal_type">
                                <option value="lose_weight">Giảm cân</option>
                                <option value="maintain_weight">Giữ cân / Cân bằng</option>
                                <option value="gain_weight">Tăng cân</option>
                                <option value="gain_muscle">Tăng cơ (Gym / Thể hình)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="plan_modal_diet" class="form-label fw-bold">Chế độ ăn (Diet Type)</label>
                            <input type="text" class="form-control rounded-3" id="plan_modal_diet" name="diet_type" placeholder="Ví dụ: Cân bằng, Keto, Low carb, Chay..." maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label for="plan_modal_image" class="form-label fw-bold">Ảnh đại diện thực đơn</label>
                            <input type="file" class="form-control rounded-3" id="plan_modal_image" name="image" accept="image/jpeg,image/png,image/webp">
                            <div id="plan_current_img_wrapper" class="mt-2 d-none">
                                <img id="plan_current_img" src="" alt="Preview" class="rounded border" style="height: 60px; width: 60px; object-fit: cover;">
                                <span class="text-muted small ms-2">Ảnh hiện tại (chọn ảnh mới nếu muốn thay đổi)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="plan_modal_status" class="form-label fw-bold">Trạng thái</label>
                            <select class="form-select rounded-3" id="plan_modal_status" name="status">
                                <option value="active">Đang bật (Hoạt động)</option>
                                <option value="inactive">Đang tắt (Ẩn)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="plan_modal_desc" class="form-label fw-bold">Mô tả thực đơn</label>
                            <textarea class="form-control rounded-3" id="plan_modal_desc" name="description" rows="3" placeholder="Mô tả chi tiết về đối tượng, lợi ích dinh dưỡng và hướng dẫn áp dụng..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="plan_modal_submit_btn">
                        <i class="bi bi-check-circle me-1"></i>Lưu Thực Đơn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openMealPlanModal(p) {
    const modalEl = document.getElementById('mealPlanFormModal');
    const modalTitle = document.getElementById('plan_modal_title');
    const idInput = document.getElementById('plan_modal_id');
    const nameInput = document.getElementById('plan_modal_name');
    const slugInput = document.getElementById('plan_modal_slug');
    const goalSelect = document.getElementById('plan_modal_goal');
    const dietInput = document.getElementById('plan_modal_diet');
    const statusSelect = document.getElementById('plan_modal_status');
    const descInput = document.getElementById('plan_modal_desc');
    const imgWrapper = document.getElementById('plan_current_img_wrapper');
    const imgPreview = document.getElementById('plan_current_img');
    const fileInput = document.getElementById('plan_modal_image');
    const submitBtn = document.getElementById('plan_modal_submit_btn');

    fileInput.value = '';

    if (!p) {
        modalTitle.textContent = 'Thêm Thực Đơn Mới';
        idInput.value = '0';
        nameInput.value = '';
        slugInput.value = '';
        goalSelect.value = 'lose_weight';
        dietInput.value = '';
        statusSelect.value = 'active';
        descInput.value = '';
        imgWrapper.classList.add('d-none');
        submitBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Thêm Mới';
    } else {
        modalTitle.textContent = 'Chỉnh Sửa Thực Đơn: ' + p.name;
        idInput.value = p.id || 0;
        nameInput.value = p.name || '';
        slugInput.value = p.slug || '';
        goalSelect.value = p.goal_type || 'lose_weight';
        dietInput.value = p.diet_type || '';
        statusSelect.value = p.status || 'active';
        descInput.value = p.description || '';
        if (p.image) {
            imgPreview.src = '<?php echo BASE_URL; ?>/uploads/meal_plans/' + p.image;
            imgWrapper.classList.remove('d-none');
        } else {
            imgWrapper.classList.add('d-none');
        }
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Cập Nhật';
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    setTimeout(() => { nameInput.focus(); }, 400);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
