<?php
// user/weight-logs.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Lấy chiều cao của người dùng từ user_profiles
$stmtProfile = $conn->prepare("SELECT height_cm FROM user_profiles WHERE user_id = :user_id");
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
        $log_date = $_POST['log_date'];
        $note = trim($_POST['note'] ?? '');
        
        if ($weight_kg > 0 && !empty($log_date)) {
            // Tính BMI nếu có chiều cao
            $bmi = null;
            if ($height_cm > 0) {
                $height_m = $height_cm / 100;
                $bmi = round($weight_kg / ($height_m * $height_m), 2);
            }
            
            // Kiểm tra xem ngày này đã có log chưa
            $stmtCheck = $conn->prepare("SELECT id FROM weight_logs WHERE user_id = :user_id AND log_date = :log_date");
            $stmtCheck->execute([':user_id' => $user_id, ':log_date' => $log_date]);
            
            if ($stmtCheck->rowCount() > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE weight_logs SET weight_kg = :weight, bmi = :bmi, note = :note WHERE user_id = :user_id AND log_date = :log_date");
                $stmt->execute([
                    ':weight' => $weight_kg,
                    ':bmi' => $bmi,
                    ':note' => $note,
                    ':user_id' => $user_id,
                    ':log_date' => $log_date
                ]);
                
                // Đồng thời cập nhật cân nặng hiện tại vào profile
                $stmtUpdProfile = $conn->prepare("UPDATE user_profiles SET current_weight_kg = :weight WHERE user_id = :user_id");
                $stmtUpdProfile->execute([':weight' => $weight_kg, ':user_id' => $user_id]);
                
                $_SESSION['success'] = 'Đã cập nhật cân nặng cho ngày ' . date('d/m/Y', strtotime($log_date));
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO weight_logs (user_id, weight_kg, bmi, log_date, note) VALUES (:user_id, :weight, :bmi, :log_date, :note)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':weight' => $weight_kg,
                    ':bmi' => $bmi,
                    ':log_date' => $log_date,
                    ':note' => $note
                ]);
                
                // Đồng thời cập nhật cân nặng hiện tại vào profile
                $stmtUpdProfile = $conn->prepare("UPDATE user_profiles SET current_weight_kg = :weight WHERE user_id = :user_id");
                $stmtUpdProfile->execute([':weight' => $weight_kg, ':user_id' => $user_id]);
                
                $_SESSION['success'] = 'Đã ghi lại cân nặng mới.';
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM weight_logs WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        $_SESSION['success'] = 'Đã xóa bản ghi cân nặng.';
    }
    
    redirect('/user/weight-logs.php');
}

// Lấy danh sách lịch sử cân nặng
$stmtLogs = $conn->prepare("SELECT * FROM weight_logs WHERE user_id = :user_id ORDER BY log_date DESC");
$stmtLogs->execute([':user_id' => $user_id]);
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Theo dõi Cân nặng';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0 text-success"><i class="bi bi-graph-up text-success me-2"></i>Biểu đồ Cân nặng</h2>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Form nhập cân nặng -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Ghi nhận Cân nặng</h5>
                </div>
                <div class="card-body">
                    <?php if ($height_cm == 0): ?>
                        <div class="alert alert-warning py-2 mb-3 small">
                            <i class="bi bi-exclamation-triangle me-1"></i> Bạn chưa nhập chiều cao. Hãy cập nhật ở <a href="profile.php" class="alert-link">Trang cá nhân</a> để hệ thống tự động tính chỉ số BMI.
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="log_weight">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ngày ghi nhận</label>
                            <input type="date" class="form-control" name="log_date" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cân nặng (kg)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="weight_kg" step="0.1" min="20" max="300" required placeholder="Ví dụ: 65.5">
                                <span class="input-group-text">kg</span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Ghi chú (tùy chọn)</label>
                            <textarea class="form-control" name="note" rows="2" placeholder="Ví dụ: Mới ăn tiệc về, cân hơi nặng..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 fw-bold">Lưu thông tin</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Bảng lịch sử -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ngày</th>
                                    <th>Cân nặng</th>
                                    <th>BMI</th>
                                    <th>Ghi chú</th>
                                    <th class="text-end">Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo date('d/m/Y', strtotime($log['log_date'])); ?></td>
                                    <td class="text-danger fw-bold fs-5"><?php echo $log['weight_kg']; ?> kg</td>
                                    <td>
                                        <?php 
                                            if ($log['bmi']) {
                                                $bmi_color = 'bg-success';
                                                $bmi_text = 'Bình thường';
                                                if ($log['bmi'] < 18.5) { $bmi_color = 'bg-info'; $bmi_text = 'Gầy'; }
                                                elseif ($log['bmi'] >= 25 && $log['bmi'] < 30) { $bmi_color = 'bg-warning'; $bmi_text = 'Thừa cân'; }
                                                elseif ($log['bmi'] >= 30) { $bmi_color = 'bg-danger'; $bmi_text = 'Béo phì'; }
                                                echo '<span class="badge ' . $bmi_color . '" title="' . $bmi_text . '">' . $log['bmi'] . '</span>';
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-muted small" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($log['note'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($log['note'] ?? ''); ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa bản ghi này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $log['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-journal-x fs-1 d-block mb-3"></i>
                                            Chưa có dữ liệu. Hãy ghi nhận cân nặng hôm nay!
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Biểu đồ -->
    <?php if (count($logs) > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Biến động cân nặng</h5>
                </div>
                <div class="card-body">
                    <canvas id="weightChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const logs = <?php echo json_encode(array_reverse($logs)); ?>;
        const labels = logs.map(log => {
            const d = new Date(log.log_date);
            return d.toLocaleDateString('vi-VN');
        });
        const data = logs.map(log => log.weight_kg);
        
        const ctx = document.getElementById('weightChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Cân nặng (kg)',
                    data: data,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.2)',
                    borderWidth: 2,
                    pointBackgroundColor: '#198754',
                    pointRadius: 4,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        suggestedMin: Math.min(...data) - 5,
                        suggestedMax: Math.max(...data) + 5
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
