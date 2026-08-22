<?php
// admin/class-detail.php
session_start();
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/admin-check.php';
require_once '../includes/SimpleXLSX.php';

$db = new Database();
$conn = $db->getConnection();

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy thông tin lớp
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = :id");
$stmt->execute([':id' => $class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    die("Không tìm thấy lớp học.");
}

$message = '';
$error = '';
$field_errors = [];
$old_input = [];

// Xử lý Export Excel
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    require_once '../includes/SimpleXLSXGen.php';
    
    $stmt = $conn->prepare("SELECT stt, mssv, full_name, birth_date, email FROM users WHERE class_id = :class_id ORDER BY stt ASC, id ASC");
    $stmt->execute([':class_id' => $class_id]);
    $export_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "DanhSachSinhVien_" . $class['class_code'] . "_" . date('Ymd') . ".xlsx";
    
    $data = [
        ['<b>STT</b>', '<b>MSSV</b>', '<b>Họ Và Tên</b>', '<b>Ngày Sinh</b>', '<b>Email</b>', '<b>Mật Khẩu</b>']
    ];
    
    foreach ($export_students as $row) {
        $dob = $row['birth_date'] ? date('d/m/Y', strtotime($row['birth_date'])) : '';
        $data[] = [
            $row['stt'],
            (string)$row['mssv'],
            $row['full_name'],
            $dob,
            $row['email'],
            '' // Mật khẩu trống khi export
        ];
    }
    
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs($filename);
    exit;
}

// Xử lý Xóa sinh viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_student') {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND class_id = :class_id");
            $stmt->execute([':id' => (int)$_POST['student_id'], ':class_id' => $class_id]);
            
            // Cập nhật lại STT cho các sinh viên còn lại trong lớp
            $conn->exec("SET @stt = 0");
            $reorder = $conn->prepare("UPDATE users SET stt = (@stt := @stt + 1) WHERE class_id = :cid ORDER BY stt ASC, id ASC");
            $reorder->execute([':cid' => $class_id]);
            
            $message = "Đã xóa sinh viên thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi khi xóa sinh viên: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'bulk_delete' && !empty($_POST['student_ids'])) {
        $ids = $_POST['student_ids'];
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $params = array_merge($ids, [$class_id]);
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders) AND class_id = ?");
            $stmt->execute($params);
            
            // Cập nhật lại STT cho các sinh viên còn lại trong lớp
            $conn->exec("SET @stt = 0");
            $reorder = $conn->prepare("UPDATE users SET stt = (@stt := @stt + 1) WHERE class_id = :cid ORDER BY stt ASC, id ASC");
            $reorder->execute([':cid' => $class_id]);
            
            $message = "Đã xóa " . $stmt->rowCount() . " sinh viên thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi khi xóa hàng loạt: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'add_student') {
        $mssv = trim($_POST['mssv'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $dob_raw = trim($_POST['birth_date'] ?? ''); 
        $email = trim($_POST['email'] ?? '');
        $password_raw = trim($_POST['password'] ?? '');
        
        $old_input = [
            'mssv' => $mssv,
            'full_name' => $full_name,
            'birth_date' => $dob_raw,
            'email' => $email
        ];
        
        if (empty($mssv)) $field_errors['mssv'] = "Vui lòng nhập MSSV.";
        if (empty($full_name)) $field_errors['full_name'] = "Vui lòng nhập Họ và tên.";
        
        if (empty($email)) {
            $field_errors['email'] = "Vui lòng nhập địa chỉ Email.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = "Vui lòng nhập địa chỉ Email hợp lệ.";
        }

        if (empty($password_raw)) $field_errors['password'] = "Vui lòng nhập Mật khẩu.";
        
        if (empty($dob_raw)) {
            $field_errors['birth_date'] = "Vui lòng nhập Ngày sinh.";
        } else {
            try {
                $dobDate = new DateTime($dob_raw);
                $today = new DateTime();
                if ($dobDate > $today) {
                    $field_errors['birth_date'] = "Sinh viên phải từ 18 tuổi trở lên.";
                } else {
                    $age = $today->diff($dobDate)->y;
                    if ($age < 18) {
                        $field_errors['birth_date'] = "Sinh viên phải từ 18 tuổi trở lên.";
                    } elseif ($age > 100) {
                        $field_errors['birth_date'] = "Ngày sinh không hợp lệ (quá 100 tuổi).";
                    }
                }
            } catch (Exception $e) {
                $field_errors['birth_date'] = "Định dạng ngày sinh không hợp lệ.";
            }
        }

        if (empty($field_errors)) {
            $check_stmt = $conn->prepare("SELECT email, mssv FROM users WHERE email = :email OR mssv = :mssv");
            $check_stmt->execute([':email' => $email, ':mssv' => $mssv]);
            $duplicates = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($duplicates) {
                foreach ($duplicates as $dup) {
                    if ($dup['email'] === $email) $field_errors['email'] = "Email này đã được sử dụng.";
                    if ($dup['mssv'] === $mssv) $field_errors['mssv'] = "MSSV này đã tồn tại.";
                }
            }
        }
        
        if (empty($field_errors)) {
            $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);
            try {
                // Tự động tính STT tiếp theo
                $stt_stmt = $conn->prepare("SELECT MAX(stt) FROM users WHERE class_id = :cid");
                $stt_stmt->execute([':cid' => $class_id]);
                $max_stt = (int)$stt_stmt->fetchColumn();
                $new_stt = $max_stt + 1;

                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, mssv, birth_date, class_id, stt, role, status) 
                                        VALUES (:full_name, :email, :password, :mssv, :birth_date, :class_id, :stt, 'user', 'active')");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':password' => $hashed_password,
                    ':mssv' => $mssv,
                    ':birth_date' => !empty($dob_raw) ? $dob_raw : null,
                    ':class_id' => $class_id,
                    ':stt' => $new_stt
                ]);
                $message = "Thêm sinh viên bằng tay thành công!";
                $old_input = []; 
            } catch (PDOException $e) {
                $error = "Lỗi khi thêm sinh viên: " . $e->getMessage();
            }
        }
    }
}

// Xử lý Import Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            if ($xlsx = Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
                $successCount = 0;
                $errorCount = 0;
                $errorLog = [];
                $isValidFormat = false;
                
                // Bỏ qua dòng tiêu đề (dòng 0)
                foreach ($xlsx->rows() as $i => $row) {
                    $row = (array)$row; // Ép kiểu mảng để khắc phục lỗi báo sai của IDE
                    
                    if ($i === 0) {
                        $col0 = isset($row[0]) ? mb_strtolower(trim((string)$row[0]), 'UTF-8') : '';
                        $col1 = isset($row[1]) ? mb_strtolower(trim((string)$row[1]), 'UTF-8') : '';
                        
                        if (count($row) >= 6 && ($col0 === 'stt' || $col1 === 'mssv')) {
                            $isValidFormat = true;
                            continue;
                        } else {
                            $error = "Định dạng file không hợp lệ! Vui lòng upload đúng file mẫu (Dòng đầu tiên phải có các cột STT, MSSV...).";
                            break;
                        }
                    }

                    // Cấu trúc cột mong đợi: 0:STT, 1:MSSV, 2:Họ Tên, 3:Ngày Sinh, 4:Email, 5:Mật khẩu
                    if (count($row) < 6) continue; // Bỏ qua nếu thiếu dữ liệu

                    $stt = (int)$row[0];
                    $mssv = trim($row[1]);
                    $full_name = trim($row[2]);
                    $dob_raw = trim($row[3]); // Có thể dạng DD/MM/YYYY
                    $email = trim($row[4]);
                    $password_raw = trim($row[5]);

                    if (empty($mssv) || empty($email) || empty($password_raw)) {
                        continue;
                    }

                    // Xử lý ngày sinh DD/MM/YYYY -> YYYY-MM-DD
                    $birth_date = null;
                    if (!empty($dob_raw)) {
                        $dateObj = DateTime::createFromFormat('d/m/Y', $dob_raw);
                        if ($dateObj !== false) {
                            $birth_date = $dateObj->format('Y-m-d');
                        } else {
                            // Cố gắng fallback nếu format khác
                            $birth_date = date('Y-m-d', strtotime(str_replace('/', '-', $dob_raw)));
                        }
                    }

                    // Mã hóa mật khẩu
                    $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);

                    try {
                        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, mssv, birth_date, class_id, stt, role, status) 
                                                VALUES (:full_name, :email, :password, :mssv, :birth_date, :class_id, :stt, 'user', 'active')
                                                ON DUPLICATE KEY UPDATE 
                                                full_name = :full_name_up,
                                                password = :password_up,
                                                birth_date = :birth_date_up,
                                                class_id = :class_id_up,
                                                stt = :stt_up");
                                                
                        $stmt->execute([
                            ':full_name' => $full_name,
                            ':email' => $email,
                            ':password' => $hashed_password,
                            ':mssv' => $mssv,
                            ':birth_date' => $birth_date,
                            ':class_id' => $class_id,
                            ':stt' => $stt,
                            // Update values
                            ':full_name_up' => $full_name,
                            ':password_up' => $hashed_password,
                            ':birth_date_up' => $birth_date,
                            ':class_id_up' => $class_id,
                            ':stt_up' => $stt
                        ]);
                        $successCount++;
                    } catch (PDOException $e) {
                        $errorCount++;
                        $errorLog[] = "Dòng " . ($i + 1) . " (MSSV: $mssv): " . $e->getMessage();
                    }
                }
                
                if ($isValidFormat) {
                    $message = "Import thành công $successCount sinh viên.";
                    if ($errorCount > 0) {
                        $error = "Có $errorCount dòng bị lỗi: " . implode("<br>", $errorLog);
                    }
                }
            } else {
                $error = "Lỗi đọc file Excel: " . Shuchkin\SimpleXLSX::parseError();
            }
        } else {
            $error = "Vui lòng upload file có định dạng .xlsx";
        }
    } else {
        $error = "Lỗi khi upload file.";
    }
}

// Phân trang
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Đếm tổng số sinh viên
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM users WHERE class_id = :class_id");
$stmt_count->execute([':class_id' => $class_id]);
$total_students = $stmt_count->fetchColumn();
$total_pages = ceil($total_students / $limit);

// Lấy danh sách sinh viên trong lớp có phân trang
$stmt = $conn->prepare("SELECT * FROM users WHERE class_id = :class_id ORDER BY stt ASC, id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':class_id', $class_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Chi tiết lớp: " . htmlspecialchars($class['class_name']);
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require_once 'includes/sidebar.php'; ?>
        </div>
        
        <div class="col-md-10">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="classes.php" class="btn btn-sm btn-outline-secondary mb-2 rounded-pill shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách lớp
                    </a>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-mortarboard-fill text-primary me-2"></i>Lớp: <?= htmlspecialchars($class['class_name']) ?> 
                        <span class="fs-5 text-muted">(Mã: <?= htmlspecialchars($class['class_code']) ?>)</span>
                    </h2>
                </div>
                <div class="text-end">
                    <div class="badge bg-info text-dark fs-5 rounded-pill shadow-sm px-4 py-2">
                        <i class="bi bi-people-fill me-2"></i>Sĩ số: <?= $total_students ?> sinh viên
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Import Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-excel text-success me-2"></i>Import Tài Khoản Sinh Viên</h5>
                    <p class="text-muted mb-3">Tải lên file Excel (.xlsx) với các cột theo thứ tự: <strong>STT | MSSV | Họ Và Tên | Ngày Sinh | Email | Mật Khẩu</strong>.</p>
                    
                    <form method="post" enctype="multipart/form-data" class="d-flex align-items-center flex-wrap gap-2">
                        <input class="form-control" type="file" name="excel_file" accept=".xlsx" required style="max-width: 400px;">
                        <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                            <i class="bi bi-cloud-upload me-2"></i>Upload & Import
                        </button>
                        <a href="?id=<?= $class_id ?>&action=export" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                            <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                        </a>
                    </form>
                </div>
            </div>

            <!-- Student List -->
            <form method="post" id="bulkDeleteForm">
                <input type="hidden" name="action" value="bulk_delete">
            </form>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-card-checklist me-2"></i>Danh sách Sinh viên trong lớp</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                <i class="bi bi-person-plus-fill me-1"></i>Thêm Sinh Viên
                            </button>
                            <button type="submit" form="bulkDeleteForm" class="btn btn-sm btn-danger rounded-pill shadow-sm" id="btnBulkDelete" disabled onclick="return confirm('Bạn có chắc chắn muốn xóa những sinh viên đã chọn?');">
                                <i class="bi bi-trash-fill me-1"></i>Xóa Đã Chọn
                            </button>
                        </div>
                    </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-3" style="width: 40px;">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                </th>
                                <th scope="col">STT</th>
                                <th scope="col">MSSV</th>
                                <th scope="col">Họ Và Tên</th>
                                <th scope="col">Ngày Sinh</th>
                                <th scope="col">Email</th>
                                <th scope="col" class="text-end pe-4">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-x fs-1 d-block mb-3"></i>
                                        Lớp học này chưa có sinh viên nào.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <input form="bulkDeleteForm" class="form-check-input student-checkbox" type="checkbox" name="student_ids[]" value="<?= $student['id'] ?>">
                                        </td>
                                        <td class="fw-bold text-muted"><?= htmlspecialchars($student['stt'] ?? '-') ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($student['mssv'] ?? '-') ?></span></td>
                                        <td class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></td>
                                        <td><?= $student['birth_date'] ? date('d/m/Y', strtotime($student['birth_date'])) : '-' ?></td>
                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                        <td class="text-end pe-4">
                                            <a href="student-edit.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-primary rounded-pill shadow-sm">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </a>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Xóa sinh viên này khỏi hệ thống?');">
                                                <input type="hidden" name="action" value="delete_student">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill shadow-sm">
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
                
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-center">
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?id=<?= $class_id ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?id=<?= $class_id ?>&page=<?= $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?id=<?= $class_id ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm Sinh Viên -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="post" id="addStudentForm" novalidate>
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Thêm Sinh Viên Bằng Tay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_student">
                    <div class="mb-3">
                        <label class="form-label fw-bold">MSSV *</label>
                        <input type="text" class="form-control <?= isset($field_errors['mssv']) ? 'is-invalid' : '' ?>" name="mssv" id="mssv" value="<?= htmlspecialchars($old_input['mssv'] ?? '') ?>" required>
                        <div class="invalid-feedback"><?= $field_errors['mssv'] ?? 'Vui lòng nhập MSSV.' ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Họ Và Tên *</label>
                        <input type="text" class="form-control <?= isset($field_errors['full_name']) ? 'is-invalid' : '' ?>" name="full_name" id="full_name" value="<?= htmlspecialchars($old_input['full_name'] ?? '') ?>" required>
                        <div class="invalid-feedback"><?= $field_errors['full_name'] ?? 'Vui lòng nhập Họ và tên.' ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ngày Sinh *</label>
                        <input type="date" class="form-control <?= isset($field_errors['birth_date']) ? 'is-invalid' : '' ?>" name="birth_date" id="birth_date" value="<?= htmlspecialchars($old_input['birth_date'] ?? '') ?>" required>
                        <div class="invalid-feedback" id="birth_date_error"><?= $field_errors['birth_date'] ?? 'Vui lòng nhập Ngày sinh hợp lệ.' ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email *</label>
                        <input type="email" class="form-control <?= isset($field_errors['email']) ? 'is-invalid' : '' ?>" name="email" id="email" value="<?= htmlspecialchars($old_input['email'] ?? '') ?>" required>
                        <div class="invalid-feedback"><?= $field_errors['email'] ?? 'Vui lòng nhập địa chỉ Email hợp lệ.' ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mật Khẩu *</label>
                        <input type="password" class="form-control <?= isset($field_errors['password']) ? 'is-invalid' : '' ?>" name="password" id="password" required>
                        <div class="invalid-feedback"><?= $field_errors['password'] ?? 'Vui lòng nhập Mật khẩu.' ?></div>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Thêm Sinh Viên</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const btnBulkDelete = document.getElementById('btnBulkDelete');

    function updateDeleteButton() {
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        if (btnBulkDelete) {
            btnBulkDelete.disabled = checkedCount === 0;
        }
    }

    if(selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateDeleteButton();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateDeleteButton);
    });

    // Form Validation for Add Student
    const addStudentForm = document.getElementById('addStudentForm');
    const birthDateInput = document.getElementById('birth_date');
    const birthDateError = document.getElementById('birth_date_error');

    if (birthDateInput) {
        // Set max date to 18 years ago, min date to 100 years ago
        const today = new Date();
        const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate()).toISOString().split('T')[0];
        const minDate = new Date(today.getFullYear() - 100, today.getMonth(), today.getDate()).toISOString().split('T')[0];
        
        birthDateInput.setAttribute('max', maxDate);
        birthDateInput.setAttribute('min', minDate);
    }

    if (addStudentForm) {
        addStudentForm.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            let isValid = true;
            
            // Check MSSV
            const mssv = document.getElementById('mssv');
            if (!mssv.value.trim()) {
                mssv.classList.add('is-invalid');
                isValid = false;
            } else {
                mssv.classList.remove('is-invalid');
            }

            // Check Full Name
            const fullName = document.getElementById('full_name');
            if (!fullName.value.trim()) {
                fullName.classList.add('is-invalid');
                isValid = false;
            } else {
                fullName.classList.remove('is-invalid');
            }

            // Check Email
            const email = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailRegex.test(email.value.trim())) {
                email.classList.add('is-invalid');
                isValid = false;
            } else {
                email.classList.remove('is-invalid');
            }

            // Check Password
            const password = document.getElementById('password');
            if (!password.value.trim()) {
                password.classList.add('is-invalid');
                isValid = false;
            } else {
                password.classList.remove('is-invalid');
            }

            // Check Birth Date
            const dobValue = birthDateInput.value;
            if (dobValue) {
                const dobDate = new Date(dobValue);
                const maxAllowedDate = new Date(birthDateInput.getAttribute('max'));
                const minAllowedDate = new Date(birthDateInput.getAttribute('min'));
                
                if (dobDate > maxAllowedDate) {
                    birthDateInput.classList.add('is-invalid');
                    birthDateError.textContent = "Sinh viên phải từ 18 tuổi trở lên.";
                    isValid = false;
                } else if (dobDate < minAllowedDate) {
                    birthDateInput.classList.add('is-invalid');
                    birthDateError.textContent = "Ngày sinh không hợp lệ (quá 100 tuổi).";
                    isValid = false;
                } else {
                    birthDateInput.classList.remove('is-invalid');
                }
            } else {
                birthDateInput.classList.add('is-invalid');
                birthDateError.textContent = "Vui lòng nhập Ngày sinh.";
                isValid = false;
            }

            if (isValid) {
                addStudentForm.submit();
            }
        });

        // Clear validation on input
        const inputs = addStudentForm.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
});
</script>

<?php if (!empty($field_errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var addStudentModal = new bootstrap.Modal(document.getElementById('addStudentModal'));
    addStudentModal.show();
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
