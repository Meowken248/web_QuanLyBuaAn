<?php
// user/weight-logs.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Cho phép lưu nhiều bản ghi trong cùng một ngày ở các thời điểm khác nhau (BUG-15)
try {
    $conn->exec("ALTER TABLE weight_logs DROP INDEX uk_weight_logs_user_date");
} catch (Exception $e) {
    // Không cần xử lý nếu index đã được xóa hoặc không tồn tại
}

// Lấy chiều cao của người dùng từ user_profiles
$stmtProfile = $conn->prepare("SELECT height_cm, current_weight_kg FROM user_profiles WHERE user_id = :user_id");
$stmtProfile->execute([':user_id' => $user_id]);
$profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);
$height_cm = $profile ? (float)$profile['height_cm'] : 0;

// Xử lý Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Lỗi CSRF token');
    }

    if ($_POST['action'] === 'log_weight') {
        $weight_kg = (float)$_POST['weight_kg'];
        $height_input = !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : $height_cm;
        $log_date = $_POST['log_date'] ?? date('Y-m-d');
        $log_time = $_POST['log_time'] ?? date('H:i');
        $log_datetime = $log_date . ' ' . (strlen($log_time) === 5 ? $log_time . ':00' : $log_time);
        $note = trim($_POST['note'] ?? '');
        
        if ($weight_kg > 0 && !empty($log_date)) {
            // Cập nhật chiều cao vào hồ sơ nếu người dùng thay đổi
            if ($height_input > 0 && $height_input != $height_cm) {
                $height_cm = $height_input;
                $stmtCheckP = $conn->prepare("SELECT id FROM user_profiles WHERE user_id = :user_id");
                $stmtCheckP->execute([':user_id' => $user_id]);
                if ($stmtCheckP->fetch()) {
                    $conn->prepare("UPDATE user_profiles SET height_cm = :height WHERE user_id = :user_id")
                         ->execute([':height' => $height_input, ':user_id' => $user_id]);
                } else {
                    $conn->prepare("INSERT INTO user_profiles (user_id, height_cm) VALUES (:user_id, :height)")
                         ->execute([':user_id' => $user_id, ':height' => $height_input]);
                }
            }

            // Tính BMI
            $bmi = null;
            if ($height_cm > 0) {
                $height_m = $height_cm / 100;
                $bmi = round($weight_kg / ($height_m * $height_m), 2);
            }
            
            // Insert bản ghi mới với ngày giờ chính xác
            $stmt = $conn->prepare("INSERT INTO weight_logs (user_id, weight_kg, bmi, log_date, note, created_at) 
                                    VALUES (:user_id, :weight, :bmi, :log_date, :note, :created_at)");
            $stmt->execute([
                ':user_id' => $user_id,
                ':weight' => $weight_kg,
                ':bmi' => $bmi,
                ':log_date' => $log_date,
                ':note' => $note,
                ':created_at' => $log_datetime
            ]);
            
            // Đồng thời cập nhật cân nặng hiện tại vào profile
            $conn->prepare("UPDATE user_profiles SET current_weight_kg = :weight WHERE user_id = :user_id")
                 ->execute([':weight' => $weight_kg, ':user_id' => $user_id]);
            
            $_SESSION['success'] = 'Đã ghi nhận cân nặng mới thành công (' . date('d/m/Y H:i', strtotime($log_datetime)) . ').';
        }
    } elseif ($_POST['action'] === 'edit_weight') {
        $id = (int)$_POST['id'];
        $weight_kg = (float)$_POST['weight_kg'];
        $height_input = !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : $height_cm;
        $log_date = $_POST['log_date'] ?? date('Y-m-d');
        $log_time = $_POST['log_time'] ?? date('H:i');
        $log_datetime = $log_date . ' ' . (strlen($log_time) === 5 ? $log_time . ':00' : $log_time);
        $note = trim($_POST['note'] ?? '');

        if ($id > 0 && $weight_kg > 0) {
            // Cập nhật chiều cao vào hồ sơ nếu có
            if ($height_input > 0 && $height_input != $height_cm) {
                $height_cm = $height_input;
                $conn->prepare("UPDATE user_profiles SET height_cm = :height WHERE user_id = :user_id")
                     ->execute([':height' => $height_input, ':user_id' => $user_id]);
            }

            $bmi = null;
            if ($height_cm > 0) {
                $height_m = $height_cm / 100;
                $bmi = round($weight_kg / ($height_m * $height_m), 2);
            }

            $stmt = $conn->prepare("UPDATE weight_logs SET weight_kg = :weight, bmi = :bmi, log_date = :log_date, note = :note, created_at = :created_at WHERE id = :id AND user_id = :user_id");
            $stmt->execute([
                ':weight' => $weight_kg,
                ':bmi' => $bmi,
                ':log_date' => $log_date,
                ':note' => $note,
                ':created_at' => $log_datetime,
                ':id' => $id,
                ':user_id' => $user_id
            ]);

            $_SESSION['success'] = 'Đã cập nhật bản ghi cân nặng thành công.';
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM weight_logs WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa bản ghi cân nặng.';
    }
    
    redirect('/user/weight-logs.php');
}

// Phân trang lịch sử cân nặng (BUG-16: 10 bản ghi / trang)
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$stmtTotal = $conn->prepare("SELECT COUNT(*) FROM weight_logs WHERE user_id = :user_id");
$stmtTotal->execute([':user_id' => $user_id]);
$total_records = (int)$stmtTotal->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy danh sách phân trang
$stmtLogs = $conn->prepare("SELECT * FROM weight_logs WHERE user_id = :user_id ORDER BY log_date DESC, created_at DESC, id DESC LIMIT :limit OFFSET :offset");
$stmtLogs->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmtLogs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtLogs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// Lấy TOÀN BỘ dữ liệu cho biểu đồ tiến trình và thuật toán nhận xét (BUG-17)
$stmtAllLogs = $conn->prepare("SELECT * FROM weight_logs WHERE user_id = :user_id ORDER BY log_date ASC, created_at ASC");
$stmtAllLogs->execute([':user_id' => $user_id]);
$allLogs = $stmtAllLogs->fetchAll(PDO::FETCH_ASSOC);

// Thuật toán phân tích xu hướng và đưa ra nhận xét tự động (BUG-17)
$trend_comment = "";
$trend_type = "info"; // success, warning, info
$weight_diff_7days = 0;
$latest_weight = 0;
$first_weight = 0;

if (count($allLogs) > 0) {
    $latest_log = end($allLogs);
    $latest_weight = (float)$latest_log['weight_kg'];
    $first_weight = (float)$allLogs[0]['weight_kg'];

    // Lấy các log trong 7 ngày gần nhất
    $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
    $recent_logs = array_filter($allLogs, function($l) use ($sevenDaysAgo) {
        return $l['log_date'] >= $sevenDaysAgo;
    });

    if (count($recent_logs) >= 2) {
        $recent_values = array_values($recent_logs);
        $oldest_recent = (float)$recent_values[0]['weight_kg'];
        $weight_diff_7days = round($latest_weight - $oldest_recent, 2);

        if ($weight_diff_7days > 0.5) {
            $trend_type = "warning";
            $trend_comment = "Cân nặng đang có xu hướng <strong>tăng (+" . $weight_diff_7days . " kg)</strong> trong 7 ngày qua. Lời khuyên: Hãy kiểm soát lượng tinh bột, hạn chế đồ uống có đường và tăng cường tập luyện cardio 30 phút mỗi ngày.";
        } elseif ($weight_diff_7days < -0.5) {
            $trend_type = "success";
            $trend_comment = "Cân nặng đang có xu hướng <strong>giảm (" . $weight_diff_7days . " kg)</strong> trong 7 ngày qua. Lời khuyên: Rất tích cực nếu bạn đang muốn giảm mỡ! Hãy bổ sung đầy đủ Protein để bảo vệ khối cơ bắp và uống đủ 2 lít nước.";
        } else {
            $trend_type = "info";
            $trend_comment = "Cân nặng đang duy trì <strong>rất ổn định</strong> (biến động ±0.5 kg). Lời khuyên: Chế độ dinh dưỡng và sinh hoạt của bạn đang rất cân bằng, hãy tiếp tục duy trì phong độ này!";
        }
    } else {
        $trend_type = "info";
        $trend_comment = "Hệ thống đã ghi nhận cân nặng hiện tại của bạn là <strong>" . $latest_weight . " kg</strong>. Hãy tiếp tục ghi nhận thêm 2-3 ngày để AI theo dõi và phân tích xu hướng thay đổi nhé!";
    }
}

$page_title = 'Theo dõi Cân nặng';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-success"><i class="bi bi-graph-up text-success me-2"></i>Theo dõi Cân nặng & Chỉ số BMI</h2>
            <p class="text-muted mb-0">Quản lý cân nặng, theo dõi BMI và phân tích xu hướng sức khỏe thông minh.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Form nhập cân nặng (BUG-15) -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle-fill text-success me-2"></i>Ghi nhận Cân nặng</h5>
                </div>
                <div class="card-body pt-0">
                    <form method="POST" id="formLogWeight" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="log_weight">
                        
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label fw-bold small text-muted">Ngày ghi nhận <span class="text-danger">*</span></label>
                                <input type="date" class="form-control rounded-3" name="log_date" id="input_log_date" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>">
                                <div class="invalid-feedback" id="input_log_date_error">Vui lòng chọn ngày hợp lệ.</div>
                            </div>
                            <div class="col-5">
                                <label class="form-label fw-bold small text-muted">Giờ ghi nhận</label>
                                <input type="time" class="form-control rounded-3" name="log_time" id="input_log_time" value="<?php echo date('H:i'); ?>" required>
                                <div class="invalid-feedback" id="input_log_time_error">Vui lòng chọn giờ ghi nhận.</div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">Cân nặng (kg) <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <input type="number" class="form-control rounded-start-3" id="input_weight_kg" name="weight_kg" step="0.1" min="20" max="300" required placeholder="65.5">
                                    <span class="input-group-text bg-light">kg</span>
                                    <div class="invalid-feedback" id="input_weight_kg_error">Cân nặng từ 20kg - 300kg.</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">Chiều cao (cm)</label>
                                <div class="input-group has-validation">
                                    <input type="number" class="form-control rounded-start-3" id="input_height_cm" name="height_cm" step="1" min="80" max="250" value="<?php echo $height_cm > 0 ? $height_cm : ''; ?>" placeholder="170">
                                    <span class="input-group-text bg-light">cm</span>
                                    <div class="invalid-feedback" id="input_height_cm_error">Chiều cao từ 80cm - 250cm.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Card preview BMI tự động tính (BUG-15) -->
                        <div class="p-3 bg-light rounded-3 mb-3 border border-light" id="bmi_preview_box">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small fw-bold">Chỉ số BMI dự tính:</span>
                                <span class="badge bg-secondary" id="bmi_preview_badge">-</span>
                            </div>
                            <div class="h5 fw-bold text-dark mb-0" id="bmi_preview_value">--</div>
                            <small class="text-muted" id="bmi_preview_desc">Nhập cân nặng và chiều cao để xem đánh giá</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Ghi chú (tùy chọn)</label>
                            <textarea class="form-control rounded-3" name="note" rows="2" placeholder="Ví dụ: Cân vào buổi sáng sau khi ngủ dậy..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 fw-bold rounded-pill shadow-sm py-2">
                            <i class="bi bi-check2-circle me-1"></i> Lưu thông tin
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Bảng lịch sử (BUG-15 & BUG-16) -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Lịch sử Ghi nhận</h5>
                    <span class="badge bg-light text-muted border"><?php echo $total_records; ?> bản ghi</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Thời gian</th>
                                    <th>Cân nặng</th>
                                    <th>BMI</th>
                                    <th>Ghi chú</th>
                                    <th class="text-end pe-4">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($log['log_date'])); ?></div>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td><span class="fw-bold text-success fs-6"><?php echo $log['weight_kg']; ?> kg</span></td>
                                    <td>
                                        <?php 
                                            if ($log['bmi']) {
                                                $bmi_color = 'bg-success';
                                                $bmi_text = 'Bình thường';
                                                if ($log['bmi'] < 18.5) { $bmi_color = 'bg-info text-dark'; $bmi_text = 'Gầy'; }
                                                elseif ($log['bmi'] >= 25 && $log['bmi'] < 30) { $bmi_color = 'bg-warning text-dark'; $bmi_text = 'Thừa cân'; }
                                                elseif ($log['bmi'] >= 30) { $bmi_color = 'bg-danger'; $bmi_text = 'Béo phì'; }
                                                echo '<span class="badge rounded-pill ' . $bmi_color . '" title="' . $bmi_text . '">' . $log['bmi'] . ' - ' . $bmi_text . '</span>';
                                            } else {
                                                echo '<span class="text-muted small">--</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-muted small" style="max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($log['note'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($log['note'] ?: '-'); ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <!-- Nút Sửa (BUG-15) -->
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-1 btn-edit-weight" 
                                                data-id="<?php echo $log['id']; ?>"
                                                data-weight="<?php echo $log['weight_kg']; ?>"
                                                data-date="<?php echo $log['log_date']; ?>"
                                                data-time="<?php echo date('H:i', strtotime($log['created_at'])); ?>"
                                                data-note="<?php echo htmlspecialchars($log['note'] ?? '', ENT_QUOTES); ?>"
                                                title="Sửa bản ghi">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <!-- Nút Xóa -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa bản ghi cân nặng này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $log['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Xóa bản ghi"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-journal-x fs-1 d-block mb-3 text-muted"></i>
                                            Chưa có dữ liệu. Hãy ghi nhận cân nặng đầu tiên của bạn!
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Phân trang 10 bản ghi / trang (BUG-16) -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center py-3 border-top">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link rounded-start-pill" href="?page=<?php echo $page - 1; ?>">&laquo;</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
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
    
    <!-- Biểu đồ & Nhận xét xu hướng (BUG-17) -->
    <?php if (count($allLogs) > 0): ?>
    <div class="row mt-2">
        <!-- Khối nhận xét AI / Xu hướng -->
        <div class="col-12 mb-4">
            <div class="alert alert-<?php echo $trend_type; ?> border-0 shadow-sm rounded-4 p-4 d-flex align-items-start">
                <i class="bi bi-stars fs-2 me-3 mt-1 text-<?php echo $trend_type; ?>"></i>
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-2">Đánh giá & Lời khuyên dinh dưỡng từ Trợ lý AI</h5>
                    <p class="mb-0 fs-6"><?php echo $trend_comment; ?></p>
                </div>
            </div>
        </div>

        <!-- Biểu đồ tiến trình có bộ lọc (BUG-17) -->
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bezier2 text-success me-2"></i>Biến động Tiến trình Cân nặng</h5>
                    <!-- Nút lọc thời gian -->
                    <div class="btn-group btn-group-sm" role="group" id="chartFilterGroup">
                        <button type="button" class="btn btn-outline-success active" data-filter="all">Tất cả</button>
                        <button type="button" class="btn btn-outline-success" data-filter="7days">Theo tuần (7 ngày)</button>
                        <button type="button" class="btn btn-outline-success" data-filter="month">Theo tháng (30 ngày)</button>
                        <button type="button" class="btn btn-outline-success" data-filter="year">Theo năm</button>
                    </div>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 320px; width: 100%;">
                        <canvas id="weightChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Chỉnh Sửa Bản Ghi Cân Nặng (BUG-15) -->
<div class="modal fade" id="modalEditWeight" tabindex="-1" aria-labelledby="modalEditWeightLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" id="formEditWeight" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="edit_weight">
                <input type="hidden" name="id" id="edit_log_id" value="">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditWeightLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Chỉnh sửa Bản ghi Cân nặng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-bold small text-muted">Ngày ghi nhận <span class="text-danger">*</span></label>
                            <input type="date" class="form-control rounded-3" name="log_date" id="edit_log_date" required max="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback" id="edit_log_date_error">Vui lòng chọn ngày hợp lệ.</div>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-bold small text-muted">Giờ ghi nhận</label>
                            <input type="time" class="form-control rounded-3" name="log_time" id="edit_log_time" required>
                            <div class="invalid-feedback" id="edit_log_time_error">Vui lòng chọn giờ ghi nhận.</div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Cân nặng (kg) <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="number" class="form-control rounded-start-3" id="edit_weight_kg" name="weight_kg" step="0.1" min="20" max="300" required>
                                <span class="input-group-text bg-light">kg</span>
                                <div class="invalid-feedback" id="edit_weight_kg_error">Cân nặng từ 20kg - 300kg.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Chiều cao (cm)</label>
                            <div class="input-group has-validation">
                                <input type="number" class="form-control rounded-start-3" id="edit_height_cm" name="height_cm" step="1" min="80" max="250" value="<?php echo $height_cm > 0 ? $height_cm : ''; ?>">
                                <span class="input-group-text bg-light">cm</span>
                                <div class="invalid-feedback" id="edit_height_cm_error">Chiều cao từ 80cm - 250cm.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Ghi chú</label>
                        <textarea class="form-control rounded-3" name="note" id="edit_note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Tự động tính BMI xem trước trên form nhập (BUG-15)
    const weightInput = document.getElementById('input_weight_kg');
    const heightInput = document.getElementById('input_height_cm');
    const bmiValueEl = document.getElementById('bmi_preview_value');
    const bmiBadgeEl = document.getElementById('bmi_preview_badge');
    const bmiDescEl = document.getElementById('bmi_preview_desc');

    function updateBmiPreview() {
        const w = parseFloat(weightInput.value);
        const h = parseFloat(heightInput.value);
        if (w > 0 && h > 0) {
            const hm = h / 100;
            const bmi = (w / (hm * hm)).toFixed(1);
            bmiValueEl.textContent = bmi;
            if (bmi < 18.5) {
                bmiBadgeEl.className = 'badge bg-info text-dark';
                bmiBadgeEl.textContent = 'Gầy';
                bmiDescEl.textContent = 'Dưới chuẩn cân nặng. Nên bổ sung thêm dinh dưỡng hợp lý.';
            } else if (bmi < 24.9) {
                bmiBadgeEl.className = 'badge bg-success text-white';
                bmiBadgeEl.textContent = 'Bình thường';
                bmiDescEl.textContent = 'Cân đối tuyệt vời! Hãy tiếp tục duy trì chế độ này.';
            } else if (bmi < 29.9) {
                bmiBadgeEl.className = 'badge bg-warning text-dark';
                bmiBadgeEl.textContent = 'Thừa cân';
                bmiDescEl.textContent = 'Hơi thừa cân một chút. Nên hạn chế đường mỡ và tập luyện.';
            } else {
                bmiBadgeEl.className = 'badge bg-danger text-white';
                bmiBadgeEl.textContent = 'Béo phì';
                bmiDescEl.textContent = 'Cần áp dụng thực đơn kiểm soát calo và tăng cường vận động.';
            }
        } else {
            bmiValueEl.textContent = '--';
            bmiBadgeEl.className = 'badge bg-secondary';
            bmiBadgeEl.textContent = '-';
            bmiDescEl.textContent = 'Nhập cân nặng và chiều cao để xem đánh giá';
        }
    }

    if (weightInput && heightInput) {
        weightInput.addEventListener('input', updateBmiPreview);
        heightInput.addEventListener('input', updateBmiPreview);
    }

    // Validation cho Form Ghi nhận Cân nặng chính
    const formLogWeight = document.getElementById('formLogWeight');
    if (formLogWeight) {
        const inputLogDate = document.getElementById('input_log_date');
        const inputLogTime = document.getElementById('input_log_time');

        function validateLogW() {
            const val = parseFloat(weightInput.value);
            if (isNaN(val) || val < 20 || val > 300) {
                weightInput.classList.add('is-invalid');
                weightInput.classList.remove('is-valid');
                return false;
            }
            weightInput.classList.remove('is-invalid');
            weightInput.classList.add('is-valid');
            return true;
        }

        function validateLogD() {
            if (!inputLogDate.value) {
                inputLogDate.classList.add('is-invalid');
                inputLogDate.classList.remove('is-valid');
                return false;
            }
            inputLogDate.classList.remove('is-invalid');
            inputLogDate.classList.add('is-valid');
            return true;
        }

        function validateLogT() {
            if (!inputLogTime.value) {
                inputLogTime.classList.add('is-invalid');
                inputLogTime.classList.remove('is-valid');
                return false;
            }
            inputLogTime.classList.remove('is-invalid');
            inputLogTime.classList.add('is-valid');
            return true;
        }

        function validateLogH() {
            if (heightInput && heightInput.value.trim() !== '') {
                const val = parseFloat(heightInput.value);
                if (isNaN(val) || val < 80 || val > 250) {
                    heightInput.classList.add('is-invalid');
                    heightInput.classList.remove('is-valid');
                    return false;
                }
            }
            if (heightInput) {
                heightInput.classList.remove('is-invalid');
                if (heightInput.value.trim() !== '') heightInput.classList.add('is-valid');
            }
            return true;
        }

        weightInput.addEventListener('input', validateLogW);
        weightInput.addEventListener('blur', validateLogW);
        inputLogDate.addEventListener('input', validateLogD);
        inputLogDate.addEventListener('blur', validateLogD);
        inputLogTime.addEventListener('input', validateLogT);
        inputLogTime.addEventListener('blur', validateLogT);
        if (heightInput) {
            heightInput.addEventListener('input', validateLogH);
            heightInput.addEventListener('blur', validateLogH);
        }

        formLogWeight.addEventListener('submit', function(e) {
            const isWOk = validateLogW();
            const isDOk = validateLogD();
            const isTOk = validateLogT();
            const isHOk = validateLogH();

            if (!isWOk || !isDOk || !isTOk || !isHOk) {
                e.preventDefault();
                e.stopPropagation();
                const firstInvalid = formLogWeight.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.focus();
            }
        });
    }

    // 2. Modal Chỉnh Sửa Bản Ghi Cân Nặng (BUG-15)
    const modalEditWeight = document.getElementById('modalEditWeight');
    const formEditWeight = document.getElementById('formEditWeight');
    let bsModalEdit = null;

    function resetEditWeightModal() {
        if (!formEditWeight) return;
        document.getElementById('edit_log_id').value = '';
        const wInput = document.getElementById('edit_weight_kg');
        const dInput = document.getElementById('edit_log_date');
        const tInput = document.getElementById('edit_log_time');
        const hInput = document.getElementById('edit_height_cm');
        const nInput = document.getElementById('edit_note');

        if (wInput) { wInput.value = ''; wInput.defaultValue = ''; wInput.classList.remove('is-invalid', 'is-valid'); }
        if (dInput) { dInput.value = ''; dInput.defaultValue = ''; dInput.classList.remove('is-invalid', 'is-valid'); }
        if (tInput) { tInput.value = ''; tInput.defaultValue = ''; tInput.classList.remove('is-invalid', 'is-valid'); }
        if (hInput) { hInput.classList.remove('is-invalid', 'is-valid'); }
        if (nInput) { nInput.value = ''; nInput.defaultValue = ''; }
    }

    if (modalEditWeight) {
        bsModalEdit = bootstrap.Modal.getOrCreateInstance(modalEditWeight);

        modalEditWeight.addEventListener('hidden.bs.modal', resetEditWeightModal);
        modalEditWeight.addEventListener('hide.bs.modal', resetEditWeightModal);
        modalEditWeight.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', resetEditWeightModal);
        });

        document.querySelectorAll('.btn-edit-weight').forEach(btn => {
            btn.addEventListener('click', function() {
                resetEditWeightModal();
                document.getElementById('edit_log_id').value = this.dataset.id;
                document.getElementById('edit_weight_kg').value = this.dataset.weight;
                document.getElementById('edit_log_date').value = this.dataset.date;
                document.getElementById('edit_log_time').value = this.dataset.time || '12:00';
                document.getElementById('edit_note').value = this.dataset.note || '';
                bsModalEdit.show();
            });
        });

        if (formEditWeight) {
            const editW = document.getElementById('edit_weight_kg');
            const editD = document.getElementById('edit_log_date');
            const editT = document.getElementById('edit_log_time');
            const editH = document.getElementById('edit_height_cm');

            function validateEditW() {
                const val = parseFloat(editW.value);
                if (isNaN(val) || val < 20 || val > 300) {
                    editW.classList.add('is-invalid');
                    editW.classList.remove('is-valid');
                    return false;
                }
                editW.classList.remove('is-invalid');
                editW.classList.add('is-valid');
                return true;
            }

            function validateEditD() {
                if (!editD.value) {
                    editD.classList.add('is-invalid');
                    editD.classList.remove('is-valid');
                    return false;
                }
                editD.classList.remove('is-invalid');
                editD.classList.add('is-valid');
                return true;
            }

            function validateEditT() {
                if (!editT.value) {
                    editT.classList.add('is-invalid');
                    editT.classList.remove('is-valid');
                    return false;
                }
                editT.classList.remove('is-invalid');
                editT.classList.add('is-valid');
                return true;
            }

            function validateEditH() {
                if (editH.value.trim() !== '') {
                    const val = parseFloat(editH.value);
                    if (isNaN(val) || val < 80 || val > 250) {
                        editH.classList.add('is-invalid');
                        editH.classList.remove('is-valid');
                        return false;
                    }
                }
                editH.classList.remove('is-invalid');
                if (editH.value.trim() !== '') editH.classList.add('is-valid');
                return true;
            }

            editW.addEventListener('input', validateEditW);
            editW.addEventListener('blur', validateEditW);
            editD.addEventListener('input', validateEditD);
            editD.addEventListener('blur', validateEditD);
            editT.addEventListener('input', validateEditT);
            editT.addEventListener('blur', validateEditT);
            editH.addEventListener('input', validateEditH);
            editH.addEventListener('blur', validateEditH);

            formEditWeight.addEventListener('submit', function(e) {
                const isWOk = validateEditW();
                const isDOk = validateEditD();
                const isTOk = validateEditT();
                const isHOk = validateEditH();

                if (!isWOk || !isDOk || !isTOk || !isHOk) {
                    e.preventDefault();
                    e.stopPropagation();
                    const firstInvalid = formEditWeight.querySelector('.is-invalid');
                    if (firstInvalid) firstInvalid.focus();
                }
            });
        }
    }

    // 3. Biểu đồ Chart.js với Bộ lọc Thời gian (BUG-17)
    const rawLogs = <?php echo json_encode($allLogs); ?>;
    if (rawLogs.length > 0 && document.getElementById('weightChart')) {
        const ctx = document.getElementById('weightChart').getContext('2d');
        let chartInstance = null;

        function getFilteredData(filterType) {
            const now = new Date();
            let filtered = rawLogs;

            if (filterType === '7days') {
                const cutoff = new Date();
                cutoff.setDate(now.getDate() - 7);
                filtered = rawLogs.filter(l => new Date(l.log_date) >= cutoff);
            } else if (filterType === 'month') {
                const cutoff = new Date();
                cutoff.setDate(now.getDate() - 30);
                filtered = rawLogs.filter(l => new Date(l.log_date) >= cutoff);
            } else if (filterType === 'year') {
                const cutoff = new Date();
                cutoff.setFullYear(now.getFullYear() - 1);
                filtered = rawLogs.filter(l => new Date(l.log_date) >= cutoff);
            }

            if (filtered.length === 0) filtered = rawLogs;

            const labels = filtered.map(l => {
                const d = new Date(l.log_date);
                return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
            });
            const data = filtered.map(l => parseFloat(l.weight_kg));

            return { labels, data };
        }

        function renderChart(filterType) {
            const { labels, data } = getFilteredData(filterType);
            const minVal = Math.floor(Math.min(...data)) - 2;
            const maxVal = Math.ceil(Math.max(...data)) + 2;

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cân nặng (kg)',
                        data: data,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.12)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#059669',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ` ${context.parsed.y} kg`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            suggestedMin: minVal,
                            suggestedMax: maxVal,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        renderChart('all');

        // Bắt sự kiện chuyển đổi bộ lọc biểu đồ
        document.querySelectorAll('#chartFilterGroup button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#chartFilterGroup button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                renderChart(this.dataset.filter);
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
