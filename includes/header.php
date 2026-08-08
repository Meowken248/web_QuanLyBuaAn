<?php
// includes/header.php
require_once __DIR__ . '/../config/app.php';
$request_path = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$is_user_area = isset($_SESSION['user_id']) && str_contains($request_path, '/user/');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/img/logo_cty.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/img/logo_cty.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
    
    <!-- AOS CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="bg-light<?php echo $is_user_area ? ' user-area' : ''; ?>">

<!-- Navbar Public -->
<?php if (!isset($hide_navbar) || !$hide_navbar): ?>
<nav class="navbar navbar-expand-lg navbar-light glass-navbar shadow-soft sticky-top">
    <div class="container">
        <a class="navbar-brand company-brand" href="<?php echo BASE_URL; ?>" aria-label="<?php echo htmlspecialchars(APP_NAME); ?>">
            <img src="<?php echo BASE_URL; ?>/img/logo_cty.png" alt="<?php echo htmlspecialchars(APP_NAME); ?>" class="company-logo company-logo-navbar">
        </a>
        <div class="d-flex align-items-center ms-auto order-lg-last">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                        if (!isset($conn)) {
                            require_once __DIR__ . '/../config/database.php';
                            $db = new Database();
                            $conn = $db->getConnection();
                        }
                        
                        // 1. Kiểm tra và kích hoạt các Nhắc nhở (Reminders)
                        $current_time = date('H:i:00');
                        $current_date = date('Y-m-d');
                        $day_of_week = date('N'); // 1 (Mon) - 7 (Sun)
                        
                        $stmtReminders = $conn->prepare("SELECT * FROM reminders WHERE user_id = :user_id AND status = 'active' AND (last_triggered_date IS NULL OR last_triggered_date < :current_date)");
                        $stmtReminders->execute([
                            ':user_id' => $_SESSION['user_id'],
                            ':current_date' => $current_date
                        ]);
                        $pending_reminders = $stmtReminders->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($pending_reminders as $r) {
                            $should_trigger = false;
                            
                            if ($r['reminder_time'] <= $current_time) {
                                if ($r['repeat_type'] === 'daily' || $r['repeat_type'] === 'once') {
                                    $should_trigger = true;
                                } elseif ($r['repeat_type'] === 'weekdays' && $day_of_week <= 5) {
                                    $should_trigger = true;
                                } elseif ($r['repeat_type'] === 'weekly' && date('N', strtotime($r['created_at'])) == $day_of_week) {
                                    $should_trigger = true;
                                }
                            }
                            
                            if ($should_trigger) {
                                // Tạo thông báo
                                $msg = "Đã đến giờ cho: " . $r['title'];
                                $stmtInsert = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, :title, :message, 'info')");
                                $stmtInsert->execute([
                                    ':user_id' => $_SESSION['user_id'],
                                    ':title' => '⏰ Nhắc nhở: ' . $r['title'],
                                    ':message' => $msg
                                ]);
                                
                                // Cập nhật ngày trigger
                                $stmtUpdate = $conn->prepare("UPDATE reminders SET last_triggered_date = :current_date WHERE id = :id");
                                $stmtUpdate->execute([
                                    ':current_date' => $current_date,
                                    ':id' => $r['id']
                                ]);
                                
                                // Nếu loại là once, tắt nhắc nhở luôn
                                if ($r['repeat_type'] === 'once') {
                                    $stmtOff = $conn->prepare("UPDATE reminders SET status = 'inactive' WHERE id = :id");
                                    $stmtOff->execute([':id' => $r['id']]);
                                }
                            }
                        }

                        // 2. Đếm số thông báo chưa đọc
                        $stmtNotif = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
                        $stmtNotif->execute([':user_id' => $_SESSION['user_id']]);
                        $unread_count = $stmtNotif->fetchColumn();
                    ?>
                    
                    <div class="dropdown me-3">
                        <a href="#" class="text-dark position-relative text-decoration-none" id="dropdownNotification" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-4"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="dropdownNotification" style="min-width: 300px;">
                            <li><h6 class="dropdown-header fw-bold">Thông báo mới</h6></li>
                            <?php
                                $stmtList = $conn->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
                                $stmtList->execute([':user_id' => $_SESSION['user_id']]);
                                $notifs = $stmtList->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($notifs) > 0) {
                                    foreach ($notifs as $n) {
                                        $bg = $n['is_read'] ? '' : 'bg-light';
                                        echo '<li><a class="dropdown-item py-2 border-bottom ' . $bg . '" href="' . BASE_URL . '/user/notifications.php">';
                                        echo '<div class="d-flex w-100 justify-content-between">';
                                        echo '<h6 class="mb-1 text-truncate" style="max-width: 200px;">' . htmlspecialchars($n['title']) . '</h6>';
                                        echo '<small class="text-muted" style="font-size: 0.7rem;">' . date('d/m', strtotime($n['created_at'])) . '</small>';
                                        echo '</div>';
                                        echo '<p class="mb-0 text-muted text-truncate" style="font-size: 0.8rem; max-width: 250px;">' . htmlspecialchars($n['message']) . '</p>';
                                        echo '</a></li>';
                                    }
                                } else {
                                    echo '<li><span class="dropdown-item text-muted text-center py-3">Không có thông báo mới</span></li>';
                                }
                            ?>
                            <li><a class="dropdown-item text-center text-primary fw-bold py-2 mt-1" href="<?php echo BASE_URL; ?>/user/notifications.php">Xem tất cả thông báo</a></li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-health text-white rounded-circle d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 38px; height: 38px; font-weight: bold;">
                                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <strong class="d-none d-md-block"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Tài khoản'); ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end text-small shadow border-0 mt-2" aria-labelledby="dropdownUser">
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Bảng điều khiển</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/profile.php"><i class="bi bi-person me-2"></i>Trang cá nhân</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/reminders.php"><i class="bi bi-alarm me-2"></i>Nhắc nhở của tôi</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/weight-logs.php"><i class="bi bi-graph-up me-2"></i>Theo dõi Cân nặng</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/personal-notes.php"><i class="bi bi-journal-text me-2"></i>Nhật ký cá nhân</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/meal-plans.php"><i class="bi bi-book-half me-2"></i>Thực đơn Gợi ý</a></li>
                            <li><a class="dropdown-item py-2 text-success fw-bold bg-light" href="<?php echo BASE_URL; ?>/my-smart-menu.php"><i class="bi bi-stars me-2"></i>Thực đơn Của tôi</a></li>

                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-bold" href="<?php echo BASE_URL; ?>/admin/index.php"><i class="bi bi-shield-lock me-2"></i>Trang Quản trị</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn btn-outline-success me-2 fw-bold">Đăng nhập</a>
                    <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-success btn-glow">Đăng ký miễn phí</a>
                <?php endif; ?>
            </div>
        <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active text-success' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php">Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'features.php') ? 'active text-success' : ''; ?>" href="<?php echo BASE_URL; ?>/features.php">Tính năng</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'foods.php') ? 'active text-success' : ''; ?>" href="<?php echo BASE_URL; ?>/foods.php">Thư viện món ăn</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'smart-menu.php') ? 'active text-success' : ''; ?>" href="<?php echo BASE_URL; ?>/smart-menu.php">Thực đơn Thông minh <span class="badge bg-danger rounded-pill" style="font-size: 0.65em; vertical-align: top;">AI</span></a>
                </li>

            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Main Content wrapper -->
<main class="min-vh-100">
<?php if ($is_user_area): ?>
    <?php require __DIR__ . '/../user/includes/sidebar.php'; ?>
<?php endif; ?>
