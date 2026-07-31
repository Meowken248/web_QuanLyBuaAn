<?php
// user/reminders.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Xử lý Form Thêm / Cập nhật / Xóa nhắc nhở
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }

    $action = $_POST['action'];

    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title']);
        $reminder_type = $_POST['reminder_type'];
        $reminder_time = $_POST['reminder_time'];
        $repeat_type = $_POST['repeat_type'];
        $status = $_POST['status'] ?? 'active';

        if (empty($title) || empty($reminder_time)) {
            $_SESSION['error'] = 'Vui lòng nhập tên và thời gian nhắc nhở.';
        } else {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO reminders (user_id, title, reminder_type, reminder_time, repeat_type, status) VALUES (:user_id, :title, :type, :time, :repeat, :status)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':title' => $title,
                    ':type' => $reminder_type,
                    ':time' => $reminder_time,
                    ':repeat' => $repeat_type,
                    ':status' => $status
                ]);
                $_SESSION['success'] = 'Đã thêm nhắc nhở mới.';
            } elseif ($action === 'update') {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE reminders SET title = :title, reminder_type = :type, reminder_time = :time, repeat_type = :repeat, status = :status WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    ':title' => $title,
                    ':type' => $reminder_type,
                    ':time' => $reminder_time,
                    ':repeat' => $repeat_type,
                    ':status' => $status,
                    ':id' => $id,
                    ':user_id' => $user_id
                ]);
                $_SESSION['success'] = 'Đã cập nhật nhắc nhở.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM reminders WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa nhắc nhở.';
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $new_status = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE reminders SET status = :status WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':status' => $new_status, ':id' => $id, ':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã thay đổi trạng thái nhắc nhở.';
    }

    redirect('/user/reminders.php');
}

// Lấy danh sách nhắc nhở
$stmt = $conn->prepare("SELECT * FROM reminders WHERE user_id = :user_id ORDER BY reminder_time ASC");
$stmt->execute([':user_id' => $user_id]);
$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Nhắc nhở của tôi';
require_once __DIR__ . '/../includes/header.php';

$types = [
    'breakfast' => 'Ăn sáng',
    'lunch' => 'Ăn trưa',
    'dinner' => 'Ăn tối',
    'snack' => 'Ăn nhẹ',
    'water' => 'Uống nước',
    'weight' => 'Cân nặng',
    'custom' => 'Khác'
];

$repeats = [
    'once' => 'Một lần',
    'daily' => 'Hàng ngày',
    'weekdays' => 'Các ngày trong tuần (T2-T6)',
    'weekly' => 'Hàng tuần'
];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Quản lý Nhắc nhở</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reminderModal" onclick="resetForm()">
            <i class="bi bi-plus-circle me-1"></i>Thêm Nhắc nhở
        </button>
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

    <div class="row">
        <?php foreach ($reminders as $r): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 <?php echo $r['status'] === 'inactive' ? 'opacity-50' : ''; ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title fw-bold mb-0 text-primary">
                            <i class="bi bi-clock me-2"></i><?php echo date('H:i', strtotime($r['reminder_time'])); ?>
                        </h5>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $r['status']; ?>">
                            <div class="form-check form-switch fs-5 m-0">
                                <input class="form-check-input" type="checkbox" role="switch" onchange="this.form.submit()" <?php echo $r['status'] === 'active' ? 'checked' : ''; ?>>
                            </div>
                        </form>
                    </div>
                    
                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($r['title']); ?></h6>
                    <p class="text-muted small mb-3">
                        <span class="badge bg-light text-dark border me-1"><?php echo $types[$r['reminder_type']] ?? $r['reminder_type']; ?></span>
                        <i class="bi bi-arrow-repeat ms-2 me-1"></i><?php echo $repeats[$r['repeat_type']] ?? $r['repeat_type']; ?>
                    </p>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0 d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-outline-secondary" onclick='editReminder(<?php echo json_encode($r); ?>)'>
                        <i class="bi bi-pencil"></i> Sửa
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa nhắc nhở này?');">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($reminders)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-alarm text-muted" style="font-size: 3rem;"></i>
                <h5 class="text-muted mt-3">Chưa có nhắc nhở nào được thiết lập.</h5>
                <button type="button" class="btn btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#reminderModal">
                    Tạo nhắc nhở ngay
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Thêm/Sửa Nhắc nhở -->
<div class="modal fade" id="reminderModal" tabindex="-1" aria-labelledby="reminderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="reminderModalLabel">Thêm Nhắc Nhở</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="form_action" value="create">
                    <input type="hidden" name="id" id="reminder_id" value="">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Tên nhắc nhở <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required placeholder="VD: Uống cốc nước 300ml">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reminder_type" class="form-label fw-bold">Loại nhắc nhở</label>
                            <select class="form-select" id="reminder_type" name="reminder_type">
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="reminder_time" class="form-label fw-bold">Giờ nhắc <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="reminder_time" name="reminder_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="repeat_type" class="form-label fw-bold">Chu kỳ lặp lại</label>
                        <select class="form-select" id="repeat_type" name="repeat_type">
                            <?php foreach ($repeats as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label fw-bold">Trạng thái</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Bật (Hoạt động)</option>
                            <option value="inactive">Tắt (Tạm ẩn)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Lưu Nhắc nhở</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('reminderModalLabel').innerText = 'Thêm Nhắc Nhở';
        document.getElementById('form_action').value = 'create';
        document.getElementById('reminder_id').value = '';
        document.getElementById('title').value = '';
        document.getElementById('reminder_time').value = '';
        document.getElementById('reminder_type').value = 'water';
        document.getElementById('repeat_type').value = 'daily';
        document.getElementById('status').value = 'active';
        document.getElementById('btnSubmit').innerText = 'Lưu Nhắc nhở';
    }

    function editReminder(r) {
        document.getElementById('reminderModalLabel').innerText = 'Sửa Nhắc Nhở';
        document.getElementById('form_action').value = 'update';
        document.getElementById('reminder_id').value = r.id;
        document.getElementById('title').value = r.title;
        document.getElementById('reminder_time').value = r.reminder_time.substring(0,5);
        document.getElementById('reminder_type').value = r.reminder_type;
        document.getElementById('repeat_type').value = r.repeat_type;
        document.getElementById('status').value = r.status;
        document.getElementById('btnSubmit').innerText = 'Cập nhật';
        
        var modal = new bootstrap.Modal(document.getElementById('reminderModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
