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
                        try {
                            $current_time = date('H:i:s');
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
                        } catch (Exception $e) {}

                        // 2. Đếm số thông báo chưa đọc
                        $unread_notif_count = 0;
                        try {
                            $stmtNotif = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
                            $stmtNotif->execute([':user_id' => $_SESSION['user_id']]);
                            $unread_notif_count = (int)$stmtNotif->fetchColumn();
                        } catch (Exception $e) {}

                        // 2b. Đếm số tin nhắn hỗ trợ chưa đọc & lấy chi tiết (BUG-02)
                        $unread_support_count = 0;
                        $unread_support_chats = [];
                        try {
                            if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                                $stmtSup = $conn->query("SELECT COUNT(*) FROM support_messages WHERE sender_type = 'user' AND is_read = 0");
                                $unread_support_count = (int)$stmtSup->fetchColumn();

                                if ($unread_support_count > 0) {
                                    $stmtSupChats = $conn->query("
                                        SELECT sc.user_id, u.full_name, sm.message, sm.created_at,
                                               (SELECT COUNT(*) FROM support_messages sm2 WHERE sm2.chat_id = sc.id AND sm2.sender_type = 'user' AND sm2.is_read = 0) as unread_user_msgs
                                        FROM support_messages sm
                                        JOIN support_chats sc ON sm.chat_id = sc.id
                                        JOIN users u ON sc.user_id = u.id
                                        WHERE sm.sender_type = 'user' AND sm.is_read = 0
                                        AND sm.id = (SELECT MAX(sm3.id) FROM support_messages sm3 WHERE sm3.chat_id = sc.id AND sm3.sender_type = 'user' AND sm3.is_read = 0)
                                        ORDER BY sm.created_at DESC
                                        LIMIT 5
                                    ");
                                    $unread_support_chats = $stmtSupChats->fetchAll(PDO::FETCH_ASSOC);
                                }
                            } else {
                                $stmtSup = $conn->prepare("SELECT COUNT(sm.id) FROM support_messages sm JOIN support_chats sc ON sm.chat_id = sc.id WHERE sc.user_id = :user_id AND sm.sender_type = 'admin' AND sm.is_read = 0");
                                $stmtSup->execute([':user_id' => $_SESSION['user_id']]);
                                $unread_support_count = (int)$stmtSup->fetchColumn();
                            }
                        } catch (Exception $e) {
                            $unread_support_count = 0;
                        }
                        $total_bell_count = $unread_notif_count + $unread_support_count;
                    ?>
                    
                    <div class="dropdown me-3">
                        <a href="#" class="text-dark position-relative text-decoration-none" id="dropdownNotification" data-bs-toggle="dropdown" aria-expanded="false" title="Thông báo">
                            <i class="bi bi-bell fs-4"></i>
                            <span id="headerBellBadgeContainer">
                                <?php if ($total_bell_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                        <?php echo $total_bell_count > 99 ? '99+' : $total_bell_count; ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notif-dropdown-menu mt-2" aria-labelledby="dropdownNotification">
                            <li class="d-flex justify-content-between align-items-center notif-dropdown-header">
                                <h6 class="fw-bold text-dark p-0 m-0" style="font-size: 0.9rem;"><i class="bi bi-bell-fill me-2 text-success"></i>Thông báo mới</h6>
                                <a href="#" id="headerMarkAllReadBtn" class="small text-success text-decoration-none fw-semibold" style="font-size: 0.75rem;">Đã đọc tất cả</a>
                            </li>
                            <div id="headerNotificationDropdownItems" style="max-height: 360px; overflow-y: auto;">
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && !empty($unread_support_chats)): ?>
                                    <?php foreach ($unread_support_chats as $usc): ?>
                                        <li>
                                            <a class="notif-item notif-unread" href="<?php echo BASE_URL; ?>/admin/support-chats.php?user_id=<?php echo $usc['user_id']; ?>">
                                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                                    <div class="d-flex align-items-center me-2 flex-grow-1" style="min-width: 0;">
                                                        <span class="notif-unread-dot"></span>
                                                        <h6 class="mb-0 notif-item-title text-truncate">
                                                            <i class="bi bi-chat-dots-fill me-1 text-success"></i><?php echo htmlspecialchars($usc['full_name']); ?>
                                                        </h6>
                                                    </div>
                                                    <span class="badge bg-success rounded-pill text-nowrap flex-shrink-0" style="font-size: 0.65rem; font-weight: 600;"><?php echo $usc['unread_user_msgs']; ?> tin mới</span>
                                                </div>
                                                <p class="mb-1 notif-item-preview text-truncate">
                                                    <?php echo htmlspecialchars($usc['message']); ?>
                                                </p>
                                                <small class="notif-item-time"><i class="bi bi-clock me-1"></i><?php echo date('H:i d/m', strtotime($usc['created_at'])); ?></small>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php elseif (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'): ?>
                                    <?php if ($unread_support_count > 0): ?>
                                        <li>
                                            <a class="notif-item notif-unread" href="#" onclick="var w=document.getElementById('chatbot-window'); if(w){w.classList.remove('d-none');} var at=document.getElementById('admin-tab'); if(at){at.click();} return false;">
                                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                                    <div class="d-flex align-items-center me-2 flex-grow-1" style="min-width: 0;">
                                                        <span class="notif-unread-dot"></span>
                                                        <h6 class="mb-0 notif-item-title text-truncate"><i class="bi bi-chat-dots-fill me-1 text-success"></i>Hỗ trợ trực tuyến</h6>
                                                    </div>
                                                    <span class="badge bg-success rounded-pill text-nowrap flex-shrink-0" style="font-size: 0.65rem;"><?php echo $unread_support_count; ?> mới</span>
                                                </div>
                                                <p class="mb-0 notif-item-preview">
                                                    Bạn có phản hồi mới từ ban quản trị.
                                                </p>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php
                                    $stmtList = $conn->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
                                    $stmtList->execute([':user_id' => $_SESSION['user_id']]);
                                    $notifs = $stmtList->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($notifs) > 0) {
                                        foreach ($notifs as $n) {
                                            $item_class = $n['is_read'] ? 'notif-item' : 'notif-item notif-unread';
                                            $display_msg = preg_replace('/^\[UID:\d+\]\s*/', '', $n['message']);
                                            
                                            // Điều hướng thông minh
                                            $target_link = BASE_URL . '/user/notifications.php?read=' . $n['id'] . '#notif-' . $n['id'];
                                            if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && str_starts_with($n['title'], '💬 Tin nhắn')) {
                                                if (preg_match('/\[UID:(\d+)\]/', $n['message'], $m)) {
                                                    $target_link = BASE_URL . '/admin/support-chats.php?user_id=' . $m[1];
                                                } else {
                                                    $target_link = BASE_URL . '/admin/support-chats.php';
                                                }
                                            }

                                            echo '<li><a class="' . $item_class . '" href="' . $target_link . '">';
                                            echo '<div class="d-flex w-100 justify-content-between align-items-center mb-1">';
                                            echo '<div class="d-flex align-items-center me-2 flex-grow-1" style="min-width: 0;">';
                                            if (!$n['is_read']) {
                                                echo '<span class="notif-unread-dot"></span>';
                                            }
                                            echo '<h6 class="mb-0 notif-item-title text-truncate">' . htmlspecialchars($n['title']) . '</h6>';
                                            echo '</div>';
                                            echo '<small class="notif-item-time text-nowrap flex-shrink-0">' . date('d/m H:i', strtotime($n['created_at'])) . '</small>';
                                            echo '</div>';
                                            echo '<p class="mb-0 notif-item-preview text-truncate">' . htmlspecialchars($display_msg) . '</p>';
                                            echo '</a></li>';
                                        }
                                    } elseif (empty($unread_support_chats) && $unread_support_count === 0) {
                                        echo '<li><div class="text-muted text-center py-4 small"><i class="bi bi-bell-slash d-block mb-1 fs-4 text-secondary opacity-50"></i>Không có thông báo mới</div></li>';
                                    }
                                ?>
                            </div>
                            <li class="notif-dropdown-footer text-center"><a class="dropdown-item text-center fw-bold py-1 rounded" href="<?php echo BASE_URL; ?>/user/notifications.php">Xem tất cả thông báo</a></li>
                        </ul>
                    </div>

                    <?php
                        // BUG-05: Kiểm tra trang hiện tại để gán class active cho dropdownUser
                        $current_file = basename($_SERVER['PHP_SELF']);
                        $is_admin_current = str_contains($request_path, '/admin/');
                    ?>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-health text-white rounded-circle d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 38px; height: 38px; font-weight: bold;">
                                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <strong class="d-none d-md-block"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Tài khoản'); ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end text-small shadow border-0 mt-2" aria-labelledby="dropdownUser">
                            <li><a class="dropdown-item py-2 <?php echo ($current_file === 'dashboard.php' && $is_user_area) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Bảng điều khiển</a></li>
                            <li><a class="dropdown-item py-2 <?php echo ($current_file === 'profile.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/profile.php"><i class="bi bi-person me-2"></i>Trang cá nhân</a></li>
                            <li><a class="dropdown-item py-2 <?php echo ($current_file === 'reminders.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/reminders.php"><i class="bi bi-alarm me-2"></i>Nhắc nhở của tôi</a></li>
                            <li><a class="dropdown-item py-2 <?php echo ($current_file === 'weight-logs.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/weight-logs.php"><i class="bi bi-graph-up me-2"></i>Theo dõi Cân nặng</a></li>
                            <li><a class="dropdown-item py-2 <?php echo ($current_file === 'personal-notes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/personal-notes.php"><i class="bi bi-journal-text me-2"></i>Nhật ký cá nhân</a></li>
                            <li><a class="dropdown-item py-2 <?php echo (in_array($current_file, ['meal-plans.php', 'meal-plan-view.php']) && $is_user_area) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/meal-plans.php"><i class="bi bi-book-half me-2"></i>Thực đơn Gợi ý</a></li>
                            <li><a class="dropdown-item py-2 <?php echo ($current_file === 'my-smart-menu.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/my-smart-menu.php"><i class="bi bi-stars me-2"></i>Thực đơn Của tôi</a></li>

                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 <?php echo $is_admin_current ? 'active bg-danger text-white' : 'text-danger fw-bold'; ?>" href="<?php echo BASE_URL; ?>/admin/index.php"><i class="bi bi-shield-lock me-2"></i>Trang Quản trị</a></li>
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
<main class="min-vh-100<?php echo $is_user_area ? ' d-lg-flex' : ''; ?>">
<?php if ($is_user_area): ?>
    <?php require __DIR__ . '/../user/includes/sidebar.php'; ?>
<?php endif; ?>
