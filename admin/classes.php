<?php
// admin/classes.php
session_start();
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/admin-check.php';

$db = new Database();
$conn = $db->getConnection();

// Xử lý thêm lớp
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $class_code = trim($_POST['class_code']);
        $class_name = trim($_POST['class_name']);
        
        if (!empty($class_code) && !empty($class_name)) {
            try {
                // Lấy STT lớn nhất hiện tại
                $stt_stmt = $conn->query("SELECT MAX(stt) FROM classes");
                $max_stt = (int)$stt_stmt->fetchColumn();
                $new_stt = $max_stt + 1;

                $stmt = $conn->prepare("INSERT INTO classes (class_code, class_name, stt) VALUES (:code, :name, :stt)");
                $stmt->execute([':code' => $class_code, ':name' => $class_name, ':stt' => $new_stt]);
                $message = "Thêm lớp học thành công!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry
                    $error = "Mã lớp này đã tồn tại!";
                } else {
                    $error = "Lỗi: " . $e->getMessage();
                }
            }
        } else {
            $error = "Vui lòng nhập đầy đủ Mã lớp và Tên lớp.";
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $delete_class_id = (int)$_POST['id'];
        
        // Kiểm tra xem lớp còn sinh viên không
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE class_id = :class_id");
        $check_stmt->execute([':class_id' => $delete_class_id]);
        $student_count = $check_stmt->fetchColumn();
        
        if ($student_count > 0) {
            $error = "Không thể xóa lớp học này vì vẫn còn $student_count sinh viên trong lớp. Hãy xóa sinh viên trước.";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM classes WHERE id = :id");
                $stmt->execute([':id' => $delete_class_id]);
                
                // Cập nhật lại STT cho các lớp còn lại
                $conn->exec("SET @stt = 0");
                $conn->exec("UPDATE classes SET stt = (@stt := @stt + 1) ORDER BY stt ASC, id ASC");
                
                $message = "Đã xóa lớp học thành công!";
            } catch (PDOException $e) {
                $error = "Lỗi khi xóa lớp: " . $e->getMessage();
            }
        }
    }
}

// Lấy danh sách lớp kèm số sinh viên
$stmt = $conn->query("
    SELECT c.*, COUNT(u.id) as student_count 
    FROM classes c 
    LEFT JOIN users u ON c.id = u.class_id 
    GROUP BY c.id 
    ORDER BY c.stt ASC
");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Quản lý Lớp học";
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold"><i class="bi bi-mortarboard text-primary me-2"></i>Quản lý Lớp học & Sinh viên</h2>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="bi bi-plus-circle me-1"></i>Thêm Lớp Mới
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead style="background: rgba(243, 244, 246, 0.7);">
                            <tr>
                                <th scope="col" class="ps-4">STT</th>
                                <th scope="col">Mã Lớp</th>
                                <th scope="col">Tên Lớp</th>
                                <th scope="col">Sĩ số</th>
                                <th scope="col" class="text-end pe-4">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classes)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        Chưa có lớp học nào. Hãy thêm lớp đầu tiên!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classes as $cls): ?>
                                    <tr>
                                        <td class="ps-4"><strong>#<?= $cls['stt'] ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($cls['class_code']) ?></span></td>
                                        <td class="fw-bold">
                                            <a href="class-detail.php?id=<?= $cls['id'] ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($cls['class_name']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark rounded-pill">
                                                <i class="bi bi-people me-1"></i><?= $cls['student_count'] ?> SV
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="class-detail.php?id=<?= $cls['id'] ?>" class="btn btn-sm btn-outline-success rounded-pill">
                                                <i class="bi bi-eye"></i> Xem / Nhập Excel
                                            </a>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lớp này?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $cls['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm Lớp -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="post" id="addClassForm" novalidate>
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold" id="addClassModalLabel"><i class="bi bi-plus-circle text-success me-2"></i>Thêm Lớp Học Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mã Lớp <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="class_code" id="class_code" required placeholder="VD: D21_TH01" autocomplete="off">
                        <div class="invalid-feedback" id="class_code_error">Vui lòng nhập Mã lớp.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên Lớp <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="class_name" id="class_name" required placeholder="VD: Công nghệ Thông tin 1" autocomplete="off">
                        <div class="invalid-feedback" id="class_name_error">Vui lòng nhập Tên lớp.</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-outline-success rounded-pill px-4 shadow-sm">Lưu Lớp Học</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addClassModalEl = document.getElementById('addClassModal');
    const addClassForm = document.getElementById('addClassForm');
    const classCodeInput = document.getElementById('class_code');
    const classNameInput = document.getElementById('class_name');

    function validateClassCode(trigger = 'blur') {
        if (!classCodeInput) return true;
        const val = classCodeInput.value.trim();
        const err = document.getElementById('class_code_error');
        if (!val) {
            if (trigger === 'submit' || trigger === 'blur') {
                classCodeInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập Mã lớp.';
                return false;
            }
            return true;
        }
        if (/\s/.test(val) || /[^\x00-\x7F]/.test(val)) {
            classCodeInput.classList.add('is-invalid');
            if (err) err.textContent = 'Mã lớp không được chứa khoảng trắng hoặc dấu tiếng Việt.';
            return false;
        }
        classCodeInput.classList.remove('is-invalid');
        return true;
    }

    function validateClassName(trigger = 'blur') {
        if (!classNameInput) return true;
        const val = classNameInput.value.trim();
        const err = document.getElementById('class_name_error');
        if (!val) {
            if (trigger === 'submit' || trigger === 'blur') {
                classNameInput.classList.add('is-invalid');
                if (err) err.textContent = 'Vui lòng nhập Tên lớp.';
                return false;
            }
            return true;
        }
        if (val.length < 2) {
            classNameInput.classList.add('is-invalid');
            if (err) err.textContent = 'Tên lớp phải có ít nhất 2 ký tự.';
            return false;
        }
        classNameInput.classList.remove('is-invalid');
        return true;
    }

    if (classCodeInput) {
        classCodeInput.addEventListener('input', () => validateClassCode('input'));
        classCodeInput.addEventListener('blur', () => validateClassCode('blur'));
    }
    if (classNameInput) {
        classNameInput.addEventListener('input', () => validateClassName('input'));
        classNameInput.addEventListener('blur', () => validateClassName('blur'));
    }

    if (addClassForm) {
        addClassForm.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const isCodeValid = validateClassCode('submit');
            const isNameValid = validateClassName('submit');

            if (isCodeValid && isNameValid) {
                addClassForm.submit();
            } else {
                const firstInvalid = addClassForm.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.focus();
            }
        });
    }

    function resetAddClassModal() {
        if (!addClassForm) return;
        addClassForm.reset();
        addClassForm.querySelectorAll('input').forEach(input => {
            if (input.type !== 'hidden') {
                input.value = '';
                input.defaultValue = '';
                input.classList.remove('is-invalid', 'is-valid');
            }
        });
        const errCode = document.getElementById('class_code_error');
        if (errCode) errCode.textContent = 'Vui lòng nhập Mã lớp.';
        const errName = document.getElementById('class_name_error');
        if (errName) errName.textContent = 'Vui lòng nhập Tên lớp.';
    }

    if (addClassModalEl) {
        addClassModalEl.addEventListener('hidden.bs.modal', resetAddClassModal);
        addClassModalEl.addEventListener('hide.bs.modal', resetAddClassModal);
        addClassModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', resetAddClassModal);
        });
    }

    const btnOpenAddClass = document.querySelector('[data-bs-target="#addClassModal"]');
    if (btnOpenAddClass) {
        btnOpenAddClass.addEventListener('click', resetAddClassModal);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
