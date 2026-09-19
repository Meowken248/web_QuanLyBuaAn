<?php
// admin/users.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Đảm bảo tài khoản Root luôn tồn tại trong DB
$chkRoot = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$chkRoot->execute([':email' => ROOT_ADMIN_EMAIL]);
if (!$chkRoot->fetchColumn()) {
    $stmtRoot = $conn->prepare("
        INSERT INTO users (full_name, email, password, role, status) 
        VALUES ('Root Admin', :email, :password, 'admin', 'active')
    ");
    $stmtRoot->execute([
        ':email' => ROOT_ADMIN_EMAIL,
        ':password' => password_hash(ROOT_ADMIN_EMAIL, PASSWORD_DEFAULT)
    ]);
}

$current_user_email = $_SESSION['user_email'] ?? '';
if (empty($current_user_email) && isset($_SESSION['user_id'])) {
    $stmtMe = $conn->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
    $stmtMe->execute([':id' => $_SESSION['user_id']]);
    $current_user_email = (string)$stmtMe->fetchColumn();
    $_SESSION['user_email'] = $current_user_email;
}
$is_root_admin = (strtolower(trim($current_user_email)) === strtolower(ROOT_ADMIN_EMAIL));

// Tải file mẫu Excel (mẫu Quản trị viên)
if (isset($_GET['action']) && $_GET['action'] === 'sample_excel') {
    require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
    $data = [
        ['STT', 'Họ Và Tên', 'Email', 'Mật Khẩu', 'Vai Trò'],
        [1, 'Quản Trị Viên A', 'admin_a@example.com', '12345678', 'admin'],
        [2, 'Quản Trị Viên B', 'admin_b@example.com', '12345678', 'admin']
    ];
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($data);
    $xlsx->downloadAs("Mau_DanhSach_Admin.xlsx");
    exit;
}

// Xử lý Import Excel người dùng (tất cả tài khoản import tự động có quyền admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_excel') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
        redirect('/admin/users.php');
    }
    
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            require_once __DIR__ . '/../includes/SimpleXLSX.php';
            if ($xlsx = Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
                $successCount = 0;
                $errorCount = 0;
                $errorLog = [];
                
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $insertStmt = $conn->prepare("
                    INSERT INTO users (full_name, email, password, role, status) 
                    VALUES (:full_name, :email, :password, :role, 'active')
                ");

                foreach ($xlsx->rows() as $i => $row) {
                    $row = (array)$row;
                    if ($i === 0) continue; // Bỏ qua tiêu đề
                    if (count($row) < 2) continue;

                    // Hỗ trợ cả 2 định dạng: có cột STT ở đầu hoặc bắt đầu ngay bằng Họ Tên
                    if (is_numeric($row[0]) && isset($row[2])) {
                        $full_name = trim((string)$row[1]);
                        $email = strtolower(trim((string)$row[2]));
                        $password_raw = isset($row[3]) ? trim((string)$row[3]) : '12345678';
                    } else {
                        $full_name = trim((string)$row[0]);
                        $email = strtolower(trim((string)$row[1]));
                        $password_raw = isset($row[2]) ? trim((string)$row[2]) : '12345678';
                    }

                    // Quy tắc: Khi import từ file Excel vô thì là quyền admin luôn!
                    $role = 'admin';

                    if (empty($full_name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorCount++;
                        $errorLog[] = "Dòng " . ($i + 1) . ": Họ tên hoặc Email không hợp lệ ($email).";
                        continue;
                    }

                    // Không cho phép ghi đè email root
                    if (strtolower($email) === strtolower(ROOT_ADMIN_EMAIL)) {
                        $errorCount++;
                        $errorLog[] = "Dòng " . ($i + 1) . ": Không thể thêm trùng email với tài khoản Root Admin (" . ROOT_ADMIN_EMAIL . ").";
                        continue;
                    }

                    if (empty($password_raw)) {
                        $password_raw = '12345678';
                    }

                    // Kiểm tra trùng email
                    $checkStmt->execute([':email' => $email]);
                    if ($checkStmt->fetchColumn()) {
                        $errorCount++;
                        $errorLog[] = "Dòng " . ($i + 1) . ": Email '$email' đã tồn tại.";
                        continue;
                    }

                    $hashedPassword = password_hash($password_raw, PASSWORD_DEFAULT);
                    $insertStmt->execute([
                        ':full_name' => $full_name,
                        ':email' => $email,
                        ':password' => $hashedPassword,
                        ':role' => $role
                    ]);
                    $successCount++;
                }

                $msg = "Đã nhập thành công {$successCount} tài khoản Quản trị viên (Admin) từ file Excel.";
                if ($errorCount > 0) {
                    $msg .= " Có {$errorCount} dòng bị bỏ qua hoặc lỗi: " . implode('; ', array_slice($errorLog, 0, 4));
                    if (count($errorLog) > 4) $msg .= "...";
                    set_flash_message('warning', $msg);
                } else {
                    set_flash_message('success', $msg);
                }
            } else {
                set_flash_message('danger', 'Không thể đọc file Excel: ' . Shuchkin\SimpleXLSX::parseError());
            }
        } else {
            set_flash_message('danger', 'Vui lòng chọn file Excel có định dạng .xlsx');
        }
    } else {
        set_flash_message('danger', 'Vui lòng chọn file Excel hợp lệ để tải lên.');
    }
    redirect('/admin/users.php');
}

// Xử lý Xóa nhiều người dùng (Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $selected_ids = $_POST['user_ids'] ?? [];
        $clean_ids = [];
        foreach ($selected_ids as $target_id) {
            $tid = (int)$target_id;
            if ($tid > 0 && $tid !== (int)$_SESSION['user_id']) {
                $clean_ids[] = $tid;
            }
        }

        if (!empty($clean_ids)) {
            $placeholders = implode(',', array_fill(0, count($clean_ids), '?'));
            if ($is_root_admin) {
                // Root có toàn quyền xóa cả tài khoản Admin khác và User (chỉ trừ chính tài khoản Root)
                $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders) AND email <> ?");
                $execParams = array_merge($clean_ids, [ROOT_ADMIN_EMAIL]);
            } else {
                // Admin cùng cấp KHÔNG ĐƯỢC PHÉP xóa Admin khác hoặc Root, chỉ xóa được User thường
                $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role <> 'admin' AND email <> ?");
                $execParams = array_merge($clean_ids, [ROOT_ADMIN_EMAIL]);
            }
            $stmt->execute($execParams);
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                set_flash_message('success', "Đã xóa thành công {$deleted} người dùng.");
            } else {
                set_flash_message('warning', 'Không có người dùng nào được xóa (Quản trị viên cùng cấp không được phép xóa tài khoản Admin).');
            }
        } else {
            set_flash_message('warning', 'Vui lòng chọn ít nhất một người dùng hợp lệ để xóa.');
        }
    }
    redirect('/admin/users.php');
}

// Xử lý Khóa/Mở khóa người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $target_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($target_id && $target_id !== (int)$_SESSION['user_id']) {
            $stmtTarget = $conn->prepare("SELECT id, email, role FROM users WHERE id = :id LIMIT 1");
            $stmtTarget->execute([':id' => $target_id]);
            $targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                set_flash_message('danger', 'Không tìm thấy người dùng.');
            } elseif (strtolower($targetUser['email']) === strtolower(ROOT_ADMIN_EMAIL)) {
                set_flash_message('danger', 'Tài khoản Root Admin tối cao không thể bị khóa!');
            } elseif ($targetUser['role'] === 'admin' && !$is_root_admin) {
                set_flash_message('danger', 'Quản trị viên cùng cấp không thể thay đổi trạng thái tài khoản của nhau! Chỉ Root mới có toàn quyền.');
            } else {
                $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'locked', 'active') WHERE id = :id");
                $stmt->execute([':id' => $target_id]);
                set_flash_message('success', 'Đã cập nhật trạng thái tài khoản thành công.');
            }
        } else {
            set_flash_message('danger', 'Không thể tự khóa tài khoản của chính mình.');
        }
    }
    redirect('/admin/users.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Yêu cầu không hợp lệ.');
    } else {
        $target_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($target_id && $target_id !== (int)$_SESSION['user_id']) {
            $stmtTarget = $conn->prepare("SELECT id, email, role, full_name FROM users WHERE id = :id LIMIT 1");
            $stmtTarget->execute([':id' => $target_id]);
            $targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                set_flash_message('danger', 'Không tìm thấy người dùng.');
            } elseif (strtolower($targetUser['email']) === strtolower(ROOT_ADMIN_EMAIL)) {
                set_flash_message('danger', 'Tài khoản Root Admin tối cao (' . ROOT_ADMIN_EMAIL . ') không thể bị xóa!');
            } elseif ($targetUser['role'] === 'admin' && !$is_root_admin) {
                set_flash_message('danger', 'Quản trị viên cùng cấp không được phép xóa nhau! Chỉ có Root mới có toàn quyền.');
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $target_id]);
                set_flash_message('success', 'Đã xóa người dùng "' . htmlspecialchars($targetUser['full_name']) . '" thành công.');
            }
        } else {
            set_flash_message('danger', 'Không thể xóa tài khoản của chính mình.');
        }
    }
    redirect('/admin/users.php');
}


// Phân trang danh sách người dùng (loại trừ sinh viên thuộc lớp học)
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

// Đếm tổng số người dùng (loại trừ sinh viên có class_id hoặc có mssv)
$whereSql = "WHERE (class_id IS NULL OR class_id = 0) AND (mssv IS NULL OR mssv = '')";
$stmtCount = $conn->query("SELECT COUNT(*) FROM users {$whereSql}");
$total_users = (int)$stmtCount->fetchColumn();
$total_pages = max(1, (int)ceil($total_users / $limit));

if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Lấy danh sách users trang hiện tại
$stmt = $conn->prepare("
    SELECT id, full_name, email, role, status, created_at
    FROM users
    {$whereSql}
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Người dùng';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Quản lý Người dùng</h3>
                    <p class="text-muted small mb-0">Quản lý các tài khoản người dùng hệ thống (không bao gồm sinh viên trong lớp học)</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" id="btnBulkDelete" class="btn btn-sm btn-outline-danger rounded-pill" disabled onclick="confirmBulkDelete()">
                        <i class="bi bi-trash me-1"></i>Xóa đã chọn (<span id="selectedCount">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                        <i class="bi bi-file-earmark-excel me-1"></i>Nhập từ Excel
                    </button>
                    <a href="<?php echo BASE_URL; ?>/admin/user-edit.php" class="btn btn-sm btn-outline-success rounded-pill">
                        <i class="bi bi-person-plus me-1"></i>Thêm người dùng
                    </a>
                </div>
            </div>

            <?php display_flash_message(); ?>
            
            <form id="bulkDeleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="bulk_delete">

                <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-nowrap">
                                <thead style="background: rgba(243, 244, 246, 0.7);">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllUsers" title="Chọn tất cả">
                                        </th>
                                        <th>ID</th>
                                        <th>Họ Tên</th>
                                        <th>Email</th>
                                        <th>Vai trò</th>
                                        <th>Quyền sử dụng</th>
                                        <th>Ngày đăng ký</th>
                                        <th class="text-end pe-4">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="bi bi-people fs-2 d-block mb-2 text-secondary"></i>
                                                Chưa có người dùng nào.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $u): 
                                            $is_this_root = (strtolower($u['email']) === strtolower(ROOT_ADMIN_EMAIL));
                                            $is_self = ($u['id'] == $_SESSION['user_id']);

                                            // Xác định quyền Xóa
                                            if ($is_this_root) {
                                                $can_delete = false;
                                                $delete_disabled_title = 'Tài khoản Root Admin tối cao không thể bị xóa';
                                            } elseif ($is_self) {
                                                $can_delete = false;
                                                $delete_disabled_title = 'Không thể xóa tài khoản của chính mình';
                                            } elseif ($u['role'] === 'admin') {
                                                if ($is_root_admin) {
                                                    $can_delete = true;
                                                    $delete_disabled_title = '';
                                                } else {
                                                    $can_delete = false;
                                                    $delete_disabled_title = 'Quản trị viên cùng cấp không được phép xóa nhau (Chỉ Root mới có toàn quyền)';
                                                }
                                            } else {
                                                $can_delete = true;
                                                $delete_disabled_title = '';
                                            }

                                            // Xác định quyền Khóa / Mở khóa
                                            if ($is_this_root) {
                                                $can_toggle = false;
                                                $toggle_disabled_title = 'Tài khoản Root Admin tối cao không thể bị khóa';
                                            } elseif ($is_self) {
                                                $can_toggle = false;
                                                $toggle_disabled_title = 'Không thể tự khóa tài khoản của chính mình';
                                            } elseif ($u['role'] === 'admin') {
                                                if ($is_root_admin) {
                                                    $can_toggle = true;
                                                    $toggle_disabled_title = '';
                                                } else {
                                                    $can_toggle = false;
                                                    $toggle_disabled_title = 'Quản trị viên cùng cấp không thể khóa tài khoản của nhau';
                                                }
                                            } else {
                                                $can_toggle = true;
                                                $toggle_disabled_title = '';
                                            }

                                            // Xác định quyền Sửa
                                            if ($is_this_root && !$is_root_admin) {
                                                $can_edit = false;
                                                $edit_disabled_title = 'Chỉ tài khoản Root mới có quyền chỉnh sửa tài khoản Root Admin';
                                            } else {
                                                $can_edit = true;
                                                $edit_disabled_title = '';
                                            }
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <?php if ($can_delete): ?>
                                                    <input type="checkbox" name="user_ids[]" value="<?php echo $u['id']; ?>" class="form-check-input user-select-cb">
                                                <?php else: ?>
                                                    <input type="checkbox" class="form-check-input" disabled title="<?php echo htmlspecialchars($delete_disabled_title); ?>">
                                                <?php endif; ?>
                                            </td>
                                            <td>#<?php echo $u['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle-sm <?php echo $is_this_root ? 'bg-dark text-warning border border-warning' : ($u['role'] === 'admin' ? 'bg-danger text-white' : 'bg-primary text-white'); ?> rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 14px; font-weight: bold;">
                                                        <?php echo strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8')); ?>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                                        <?php if ($is_self): ?>
                                                            <span class="badge bg-secondary ms-1" style="font-size: 10px;">Bạn</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <?php if ($is_this_root): ?>
                                                    <span class="badge bg-dark border border-warning text-warning"><i class="bi bi-shield-shaded me-1"></i>Root Admin</span>
                                                <?php elseif ($u['role'] === 'admin'): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-shield-lock me-1"></i>Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="bi bi-person me-1"></i>User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success bg-opacity-75">Đầy đủ · Miễn phí</span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                            <td class="text-end">
                                                <div class="d-flex gap-1 justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-outline-info" title="Xem chi tiết" data-bs-toggle="modal" data-bs-target="#userModal<?php echo $u['id']; ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($can_edit): ?>
                                                        <a href="<?php echo BASE_URL; ?>/admin/user-edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary opacity-50" disabled title="<?php echo htmlspecialchars($edit_disabled_title); ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($can_toggle): ?>
                                                        <button type="button" class="btn btn-sm <?php echo $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                                                title="<?php echo $u['status'] === 'active' ? 'Khóa' : 'Mở khóa'; ?>"
                                                                onclick="submitSingleAction('toggle_status', <?php echo $u['id']; ?>)">
                                                            <i class="bi <?php echo $u['status'] === 'active' ? 'bi-lock' : 'bi-unlock'; ?>"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary opacity-50" disabled title="<?php echo htmlspecialchars($toggle_disabled_title); ?>">
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($can_delete): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="submitSingleAction('delete_user', <?php echo $u['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary opacity-50" disabled title="<?php echo htmlspecialchars($delete_disabled_title); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-transparent py-3 border-0">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                                <div class="text-muted small">
                                    Hiển thị <?php echo min(($page - 1) * $limit + 1, $total_users); ?> - <?php echo min($page * $limit, $total_users); ?> trên tổng số <?php echo $total_users; ?> người dùng
                                </div>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Tiếp</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </form>

            <?php foreach ($users as $u): 
                $is_this_root = (strtolower($u['email']) === strtolower(ROOT_ADMIN_EMAIL));
            ?>
                <!-- Modal Chi Tiết Người Dùng -->
                <div class="modal fade" id="userModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">Chi tiết tài khoản</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="<?php echo $is_this_root ? 'bg-dark text-warning border border-warning' : ($u['role'] === 'admin' ? 'bg-danger text-white' : 'bg-primary text-white'); ?> rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 20px;">
                                        <?php echo strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8')); ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></h5>
                                        <div class="text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </div>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span>Vai trò</span>
                                        <strong>
                                            <?php 
                                            if ($is_this_root) {
                                                echo '<span class="text-warning fw-bold"><i class="bi bi-shield-shaded me-1"></i>Root Admin (Toàn quyền)</span>';
                                            } elseif ($u['role'] === 'admin') {
                                                echo '<span class="text-danger fw-bold"><i class="bi bi-shield-lock me-1"></i>Quản trị viên (Admin)</span>';
                                            } else {
                                                echo '<span>Khách hàng (User)</span>';
                                            }
                                            ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span>Quyền sử dụng</span>
                                        <strong class="text-success">Toàn bộ tính năng · Miễn phí</strong>
                                    </li>
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span>Ngày đăng ký</span>
                                        <strong><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></strong>
                                    </li>
                                </ul>
                                <div class="alert alert-info mt-3 mb-0 small">
                                    <i class="bi bi-info-circle me-1"></i> Trạng thái tài khoản: <?php echo $u['status'] === 'active' ? 'Đang hoạt động' : 'Đã khóa'; ?>.
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<!-- Modal Import Excel -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="import_excel">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel me-2"></i>Nhập Quản trị viên từ Excel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Tải lên file Excel (<code>.xlsx</code>) chứa danh sách tài khoản để thêm nhanh vào hệ thống với vai trò <strong>Quản trị viên (Admin)</strong>.
                    </p>
                    
                    <div class="alert alert-warning border small mb-3">
                        <div class="fw-bold mb-1"><i class="bi bi-shield-exclamation me-1"></i>Lưu ý về phân quyền:</div>
                        <div>Tất cả tài khoản được nhập từ file Excel sẽ tự động được gán quyền <strong>Quản trị viên (Admin)</strong>. Các Admin cùng cấp không được phép xóa nhau; chỉ có tài khoản <strong>Root (<?php echo ROOT_ADMIN_EMAIL; ?>)</strong> mới có toàn quyền quản lý và xóa.</div>
                    </div>

                    <div class="alert alert-light border small mb-3">
                        <div class="fw-bold mb-1"><i class="bi bi-info-circle text-primary me-1"></i>Cấu trúc các cột trong file:</div>
                        <ul class="mb-2 ps-3">
                            <li>Cột 1 (hoặc 2 nếu có STT): <strong>Họ Và Tên</strong> (bắt buộc)</li>
                            <li>Cột 2 (hoặc 3): <strong>Email</strong> (bắt buộc, không trùng lặp)</li>
                            <li>Cột 3 (hoặc 4): <strong>Mật khẩu</strong> (mặc định: 12345678 nếu để trống)</li>
                        </ul>
                        <div class="text-end">
                            <a href="?action=sample_excel" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i>Tải file Excel mẫu (.xlsx)
                            </a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="excel_file" class="form-label fw-bold">Chọn file Excel (.xlsx):</label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i>Tải lên & Nhập</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Form phụ cho thao tác đơn lẻ -->
<form id="singleActionForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="action" id="singleActionType" value="">
    <input type="hidden" name="user_id" id="singleActionUserId" value="">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllUsers');
    const userCheckboxes = document.querySelectorAll('.user-select-cb');
    const btnBulkDelete = document.getElementById('btnBulkDelete');
    const selectedCountSpan = document.getElementById('selectedCount');

    function updateSelectedState() {
        const checkedBoxes = document.querySelectorAll('.user-select-cb:checked');
        const count = checkedBoxes.length;
        const enabledBoxes = document.querySelectorAll('.user-select-cb:not(:disabled)');
        if (selectedCountSpan) selectedCountSpan.textContent = count;
        if (btnBulkDelete) btnBulkDelete.disabled = (count === 0);

        if (selectAll && enabledBoxes.length > 0) {
            selectAll.checked = (count === enabledBoxes.length);
            selectAll.indeterminate = (count > 0 && count < enabledBoxes.length);
        } else if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            userCheckboxes.forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = selectAll.checked;
                }
            });
            updateSelectedState();
        });
    }

    userCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedState);
    });

    const importExcelModal = document.getElementById('importExcelModal');
    if (importExcelModal) {
        const fileInput = document.getElementById('excel_file');
        function resetExcelModal() {
            if (fileInput) fileInput.value = '';
        }
        importExcelModal.addEventListener('hidden.bs.modal', resetExcelModal);
        importExcelModal.addEventListener('hide.bs.modal', resetExcelModal);
        importExcelModal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', resetExcelModal);
        });
    }
});

function confirmBulkDelete() {
    const count = document.querySelectorAll('.user-select-cb:checked').length;
    if (count === 0) {
        alert('Vui lòng chọn ít nhất một người dùng để xóa.');
        return;
    }
    if (confirm('Bạn có chắc chắn muốn xóa ' + count + ' người dùng đã chọn không? Hành động này không thể hoàn tác.')) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

function submitSingleAction(action, userId) {
    if (action === 'delete_user') {
        if (!confirm('Bạn có chắc chắn muốn xóa người dùng này không? Hành động này không thể hoàn tác.')) {
            return;
        }
    } else if (action === 'toggle_status') {
        if (!confirm('Xác nhận thay đổi trạng thái tài khoản?')) {
            return;
        }
    }
    
    document.getElementById('singleActionType').value = action;
    document.getElementById('singleActionUserId').value = userId;
    document.getElementById('singleActionForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
