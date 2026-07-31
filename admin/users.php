<?php
// admin/users.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Lấy danh sách users
$users = $conn->query("
    SELECT u.id, u.full_name, u.email, u.role, u.created_at, 
           s.plan_id, s.end_date, s.status as sub_status 
    FROM users u 
    LEFT JOIN user_subscriptions s ON u.id = s.user_id AND s.status = 'active'
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

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
            <h3 class="fw-bold mb-4">Quản lý Người dùng</h3>
            
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Họ Tên</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Gói hiện tại</th>
                                    <th>Ngày đăng ký</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td><span class="fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['plan_id'])): ?>
                                            <span class="badge bg-success">Premium</span>
                                            <div class="small text-muted mt-1">Hết hạn: <?php echo !empty($u['end_date']) ? date('d/m/Y', strtotime($u['end_date'])) : 'Vĩnh viễn'; ?></div>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">Miễn phí</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" title="Xem chi tiết" data-bs-toggle="modal" data-bs-target="#userModal<?php echo $u['id']; ?>"><i class="bi bi-eye"></i></button>
                                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Khóa/Xóa" onclick="alert('Chức năng khóa/xóa người dùng đang được cập nhật!');"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Modal Chi Tiết Người Dùng -->
                                <div class="modal fade" id="userModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">Chi tiết người dùng</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 20px;">
                                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></h5>
                                                        <div class="text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                                    </div>
                                                </div>
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                                        <span>Vai trò</span>
                                                        <strong><?php echo $u['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng'; ?></strong>
                                                    </li>
                                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                                        <span>Gói dịch vụ</span>
                                                        <strong><?php echo !empty($u['plan_id']) ? '<span class="text-success">Premium</span>' : 'Miễn phí'; ?></strong>
                                                    </li>
                                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                                        <span>Ngày đăng ký</span>
                                                        <strong><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></strong>
                                                    </li>
                                                </ul>
                                                <div class="alert alert-info mt-3 mb-0 small">
                                                    <i class="bi bi-info-circle me-1"></i> Tính năng xem hồ sơ sức khỏe chi tiết của user đang được cập nhật.
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
