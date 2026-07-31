<?php
// user/personal-notes.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Xử lý Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }

    if ($_POST['action'] === 'save_note') {
        $note_date = $_POST['note_date'];
        $content = trim($_POST['content'] ?? '');
        $mood = $_POST['mood'] ?? 'normal';
        $hunger_level = (int)($_POST['hunger_level'] ?? 5);
        $exercise_status = $_POST['exercise_status'] ?? 'none';
        
        if (!empty($note_date) && !empty($content)) {
            // Kiểm tra xem ngày này đã có ghi chú chưa
            $stmtCheck = $conn->prepare("SELECT id FROM personal_notes WHERE user_id = :user_id AND note_date = :note_date");
            $stmtCheck->execute([':user_id' => $user_id, ':note_date' => $note_date]);
            
            if ($stmtCheck->rowCount() > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE personal_notes SET content = :content, mood = :mood, hunger_level = :hunger, exercise_status = :exercise WHERE user_id = :user_id AND note_date = :note_date");
                $stmt->execute([
                    ':content' => $content,
                    ':mood' => $mood,
                    ':hunger' => $hunger_level,
                    ':exercise' => $exercise_status,
                    ':user_id' => $user_id,
                    ':note_date' => $note_date
                ]);
                $_SESSION['success'] = 'Đã cập nhật Nhật ký cho ngày ' . date('d/m/Y', strtotime($note_date));
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO personal_notes (user_id, note_date, content, mood, hunger_level, exercise_status) VALUES (:user_id, :note_date, :content, :mood, :hunger, :exercise)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':note_date' => $note_date,
                    ':content' => $content,
                    ':mood' => $mood,
                    ':hunger' => $hunger_level,
                    ':exercise' => $exercise_status
                ]);
                $_SESSION['success'] = 'Đã lưu Nhật ký thành công.';
            }
        } else {
            $_SESSION['error'] = 'Vui lòng nhập nội dung ghi chú.';
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM personal_notes WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa nhật ký.';
    }
    
    redirect('/user/personal-notes.php');
}

// Lấy danh sách ghi chú
$stmtNotes = $conn->prepare("SELECT * FROM personal_notes WHERE user_id = :user_id ORDER BY note_date DESC");
$stmtNotes->execute([':user_id' => $user_id]);
$notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

$mood_icons = [
    'very_bad' => ['icon' => 'bi-emoji-frown-fill', 'color' => 'text-danger', 'label' => 'Rất tệ'],
    'bad' => ['icon' => 'bi-emoji-frown', 'color' => 'text-warning', 'label' => 'Tệ'],
    'normal' => ['icon' => 'bi-emoji-neutral', 'color' => 'text-secondary', 'label' => 'Bình thường'],
    'good' => ['icon' => 'bi-emoji-smile', 'color' => 'text-info', 'label' => 'Tốt'],
    'very_good' => ['icon' => 'bi-emoji-laughing-fill', 'color' => 'text-success', 'label' => 'Rất tốt']
];

$exercise_labels = [
    'none' => 'Không tập',
    'light' => 'Tập nhẹ nhàng',
    'moderate' => 'Tập vừa phải',
    'hard' => 'Tập cường độ cao'
];

$page_title = 'Nhật ký cá nhân';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0 text-primary"><i class="bi bi-journal-text me-2"></i>Nhật ký cá nhân</h2>
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
        <!-- Form nhập nhật ký -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Viết Nhật ký</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="save_note">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ngày</label>
                            <input type="date" class="form-control" name="note_date" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>" id="note_date_input">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tâm trạng</label>
                            <select class="form-select" name="mood" id="mood_input">
                                <?php foreach ($mood_icons as $key => $val): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $key == 'normal' ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Vận động hôm nay</label>
                            <select class="form-select" name="exercise_status" id="exercise_input">
                                <?php foreach ($exercise_labels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Đánh giá mức độ đói (1-10)</label>
                            <input type="range" class="form-range" min="1" max="10" name="hunger_level" id="hunger_input" value="5">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>No bụng</span>
                                <span>Rất đói</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Ghi chép chi tiết <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="content" id="content_input" rows="4" placeholder="Hôm nay bạn cảm thấy thế nào? Ăn uống ra sao?..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 fw-bold" id="btn_submit_note">Lưu Nhật ký</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Danh sách Nhật ký -->
        <div class="col-lg-8 mb-4">
            <h5 class="fw-bold mb-3">Lịch sử ghi chép</h5>
            <div class="row">
                <?php foreach ($notes as $note): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border-0 bg-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                <h6 class="fw-bold mb-0 text-primary">
                                    <i class="bi bi-calendar-event me-2"></i><?php echo date('d/m/Y', strtotime($note['note_date'])); ?>
                                </h6>
                                <?php
                                    $m = $mood_icons[$note['mood']] ?? $mood_icons['normal'];
                                ?>
                                <span class="fs-4 <?php echo $m['color']; ?>" title="Tâm trạng: <?php echo $m['label']; ?>">
                                    <i class="bi <?php echo $m['icon']; ?>"></i>
                                </span>
                            </div>
                            <p class="mb-3" style="white-space: pre-wrap;"><?php echo htmlspecialchars($note['content']); ?></p>
                            
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-white text-dark border"><i class="bi bi-activity me-1"></i><?php echo $exercise_labels[$note['exercise_status']] ?? ''; ?></span>
                                <span class="badge bg-white text-dark border"><i class="bi bi-battery-half me-1"></i>Độ đói: <?php echo $note['hunger_level']; ?>/10</span>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 d-flex justify-content-end gap-2 pt-0">
                            <button class="btn btn-sm btn-outline-secondary" onclick='editNote(<?php echo json_encode($note); ?>)'>
                                <i class="bi bi-pencil"></i> Sửa
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa nhật ký ngày này?');">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($notes)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-journal text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">Chưa có ghi chép nào.</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function editNote(note) {
        document.getElementById('note_date_input').value = note.note_date;
        document.getElementById('mood_input').value = note.mood;
        document.getElementById('exercise_input').value = note.exercise_status;
        document.getElementById('hunger_input').value = note.hunger_level;
        document.getElementById('content_input').value = note.content;
        document.getElementById('btn_submit_note').innerText = 'Cập nhật';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
