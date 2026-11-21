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
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10">
            <!-- Header section -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-speedometer2 text-success"></i> Bảng điều khiển Quản trị viên
                    </h3>
                    <p class="text-muted small mb-0">Tổng quan dữ liệu, hoạt động người dùng và hệ thống quản lý bữa ăn</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 rounded-pill fs-7 d-flex align-items-center gap-2">
                        <span class="spinner-grow spinner-grow-sm text-success" role="status" style="width: 8px; height: 8px;"></span>
                        <span>Hệ thống trực tuyến</span>
                    </span>
                </div>
            </div>
            
            <!-- 4 Metric Cards in Glassmorphism -->
            <div class="row g-3 mb-4">
                <!-- Thẻ 1: Tổng người dùng -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card glass-card border-0 rounded-4 shadow-sm p-3 h-100 card-hover position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100" style="height: 4px; background: linear-gradient(90deg, #3b82f6, #60a5fa);"></div>
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Tổng người dùng</span>
                                <h2 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['users']); ?></h2>
                                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="text-primary text-decoration-none small mt-1 d-inline-block fw-medium">
                                    Quản lý tài khoản <i class="bi bi-chevron-right small"></i>
                                </a>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background: rgba(59, 130, 246, 0.12); color: #2563eb;">
                                <i class="bi bi-people-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thẻ 2: Tổng món ăn -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card glass-card border-0 rounded-4 shadow-sm p-3 h-100 card-hover position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100" style="height: 4px; background: linear-gradient(90deg, #10b981, #34d399);"></div>
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Tổng món ăn</span>
                                <h2 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['foods']); ?></h2>
                                <a href="<?php echo BASE_URL; ?>/admin/foods.php" class="text-success text-decoration-none small mt-1 d-inline-block fw-medium">
                                    Thư viện món <i class="bi bi-chevron-right small"></i>
                                </a>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background: rgba(16, 185, 129, 0.12); color: #059669;">
                                <i class="bi bi-egg-fried fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thẻ 3: Thực đơn đang hiển thị -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card glass-card border-0 rounded-4 shadow-sm p-3 h-100 card-hover position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100" style="height: 4px; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Thực đơn áp dụng</span>
                                <h2 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['meal_plans']); ?></h2>
                                <a href="<?php echo BASE_URL; ?>/admin/meal-plans.php" class="text-warning text-decoration-none small mt-1 d-inline-block fw-medium">
                                    Xem thực đơn mẫu <i class="bi bi-chevron-right small"></i>
                                </a>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background: rgba(245, 158, 11, 0.12); color: #d97706;">
                                <i class="bi bi-journal-check fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thẻ 4: Lượt log bữa ăn -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card glass-card border-0 rounded-4 shadow-sm p-3 h-100 card-hover position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100" style="height: 4px; background: linear-gradient(90deg, #06b6d4, #38bdf8);"></div>
                        <div class="card-body p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Lượt log bữa ăn</span>
                                <h2 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['meals_logged']); ?></h2>
                                <span class="text-info small mt-1 d-inline-block fw-medium">
                                    <i class="bi bi-activity me-1"></i>Nhật ký người dùng
                                </span>
                            </div>
                            <div class="rounded-4 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background: rgba(6, 182, 212, 0.12); color: #0891b2;">
                                <i class="bi bi-calendar2-check fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Access Shortcuts -->
            <div class="card glass-card border-0 rounded-4 shadow-sm p-3 mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
                        <span class="fw-bold text-dark">Lối tắt thao tác nhanh:</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo BASE_URL; ?>/admin/user-edit.php" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="bi bi-person-plus me-1"></i>Thêm người dùng
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/food-edit.php" class="btn btn-sm btn-outline-success rounded-pill">
                            <i class="bi bi-plus-circle me-1"></i>Thêm món ăn
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/support-chats.php" class="btn btn-sm btn-outline-secondary rounded-pill position-relative">
                            <i class="bi bi-chat-dots me-1"></i>Hỗ trợ trực tuyến
                            <?php if ($admin_unread_support > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $admin_unread_support; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="btn btn-sm btn-outline-success rounded-pill">
                            <i class="bi bi-person-workspace me-1"></i>Xem giao diện User
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Users Glass Table -->
            <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                <div class="card-header bg-transparent py-3 px-4 border-bottom d-flex justify-content-between align-items-center" style="border-color: rgba(0,0,0,0.05) !important;">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-person-lines-fill text-success me-2"></i>Người dùng mới đăng ký</h5>
                        <small class="text-muted">Các tài khoản được tạo gần đây nhất trong hệ thống</small>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-outline-success rounded-pill px-3">
                        Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead style="background: rgba(243, 244, 246, 0.7);">
                                <tr>
                                    <th class="ps-4 py-3 text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">ID</th>
                                    <th class="py-3 text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Họ Tên</th>
                                    <th class="py-3 text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Email</th>
                                    <th class="py-3 text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Vai trò</th>
                                    <th class="py-3 text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Ngày đăng ký</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $u): ?>
                                <?php 
                                    $is_this_root = (defined('ROOT_ADMIN_EMAIL') && strtolower(trim($u['email'])) === strtolower(trim(ROOT_ADMIN_EMAIL)));
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-secondary">#<?php echo $u['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle <?php echo $is_this_root ? 'bg-dark text-warning border border-warning' : ($u['role'] === 'admin' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success'); ?> d-flex align-items-center justify-content-center fw-bold me-2" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                                <?php echo mb_strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                                            </div>
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-secondary"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php if ($is_this_root): ?>
                                            <span class="badge bg-dark border border-warning text-warning rounded-pill px-3 py-1"><i class="bi bi-shield-shaded me-1"></i>Root Admin</span>
                                        <?php elseif ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-3 py-1"><i class="bi bi-shield-lock me-1"></i>Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle rounded-pill px-3 py-1"><i class="bi bi-person me-1"></i>User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
