<?php
// admin/transactions.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Lấy danh sách giao dịch
$transactions = $conn->query("
    SELECT t.*, u.full_name, u.email, p.name as plan_name 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN subscription_plans p ON t.plan_id = p.id
    ORDER BY t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Giao dịch';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <h3 class="fw-bold mb-4">Lịch sử Giao dịch (Mock Payments)</h3>
            
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã GD</th>
                                    <th>Người dùng</th>
                                    <th>Gói đăng ký</th>
                                    <th>Số tiền</th>
                                    <th>Phương thức</th>
                                    <th>Trạng thái</th>
                                    <th>Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr><td colspan="7" class="text-center py-4">Chưa có giao dịch nào</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><span class="font-monospace text-muted"><?php echo $t['transaction_code']; ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($t['full_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($t['email']); ?></div>
                                    </td>
                                    <td><span class="badge bg-success-subtle text-success"><?php echo htmlspecialchars($t['plan_name']); ?></span></td>
                                    <td class="fw-bold text-danger"><?php echo number_format($t['amount'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo htmlspecialchars($t['payment_method']); ?></td>
                                    <td>
                                        <?php if ($t['status'] === 'success'): ?>
                                            <span class="badge bg-success">Thành công</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Thất bại</span>
                                        <?php endif; ?>
                                        <?php if (!empty($t['message'])): ?>
                                            <div class="small text-muted mt-1" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($t['message']); ?>">
                                                <?php echo htmlspecialchars($t['message']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
