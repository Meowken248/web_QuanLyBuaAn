<?php
// admin/index.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Lấy thống kê nhanh
$stats = [
    'users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'foods' => $conn->query("SELECT COUNT(*) FROM foods")->fetchColumn(),
    'meal_plans' => $conn->query("SELECT COUNT(*) FROM meal_plans WHERE status='active'")->fetchColumn(),
    'meals_logged' => $conn->query("SELECT COUNT(*) FROM meal_logs")->fetchColumn(),
];

// Lấy danh sách users mới nhất
$recent_users = $conn->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

$page_title = 'Admin Dashboard';
// Hide public footer
$hide_footer = true;
// We'll use the public header, but might want to build a custom admin layout eventually
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <h3 class="fw-bold mb-4">Bảng điều khiển Quản trị viên</h3>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="text-white-50">Tổng người dùng</h6>
                            <h2 class="fw-bold mb-0"><?php echo $stats['users']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="text-white-50">Tổng món ăn</h6>
                            <h2 class="fw-bold mb-0"><?php echo $stats['foods']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="text-black-50">Thực đơn đang hiển thị</h6>
                            <h2 class="fw-bold mb-0"><?php echo $stats['meal_plans']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="text-white-50">Lượt log bữa ăn</h6>
                            <h2 class="fw-bold mb-0"><?php echo $stats['meals_logged']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Người dùng mới đăng ký</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Họ Tên</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Ngày đăng ký</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">User</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
