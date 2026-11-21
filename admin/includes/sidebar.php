<?php
$current_page = basename($_SERVER['PHP_SELF']);

// BUG-02: Đếm số tin nhắn hỗ trợ chưa đọc để hiển thị badge trên menu admin
$admin_unread_support = 0;
$admin_unread_contacts = 0;
if (isset($conn)) {
    try {
        $stmtSb = $conn->query("SELECT COUNT(*) FROM support_messages WHERE sender_type = 'user' AND is_read = 0");
        $admin_unread_support = (int)$stmtSb->fetchColumn();

        $stmtCt = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
        $admin_unread_contacts = (int)$stmtCt->fetchColumn();
    } catch (Exception $e) {
        $admin_unread_support = 0;
        $admin_unread_contacts = 0;
    }
}
?>
<!-- Mobile Toggle Button -->
<div class="d-md-none mb-3">
    <button class="btn btn-success w-100 fw-bold" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
        <i class="bi bi-list me-2"></i>Menu Quản trị    
    </button>
</div>

<!-- Sidebar / Offcanvas -->
<div class="offcanvas-md offcanvas-start sticky-md-top admin-sidebar-offcanvas" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="adminSidebarLabel">Menu Quản trị</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 p-md-0 d-block">
        <div class="admin-sidebar-nav mb-4 w-100">
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="admin-sidebar-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i>Bảng điều khiển
            </a>
            <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="admin-sidebar-item admin-sidebar-item-user">
                <i class="bi bi-person-workspace me-2"></i>Chuyển sang trang người dùng
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="admin-sidebar-item <?php echo in_array($current_page, ['users.php', 'user-edit.php']) ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>Quản lý người dùng
            </a>
            <!-- Ẩn chức năng Quản lý Lớp & Sinh viên
            <a href="<?php echo BASE_URL; ?>/admin/classes.php" class="admin-sidebar-item <?php echo in_array($current_page, ['classes.php', 'class-detail.php']) ? 'active' : ''; ?>">
                <i class="bi bi-mortarboard me-2"></i>Quản lý Lớp & Sinh viên
            </a>
            -->
            <a href="<?php echo BASE_URL; ?>/admin/foods.php" class="admin-sidebar-item <?php echo in_array($current_page, ['foods.php', 'food-edit.php']) ? 'active' : ''; ?>">
                <i class="bi bi-egg-fried me-2"></i>Thư viện món ăn
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/food-categories.php" class="admin-sidebar-item <?php echo in_array($current_page, ['food-categories.php', 'food-category-edit.php']) ? 'active' : ''; ?>">
                <i class="bi bi-tags me-2"></i>Danh mục món ăn
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/contact-messages.php" class="admin-sidebar-item d-flex justify-content-between align-items-center <?php echo in_array($current_page, ['contact-messages.php', 'contact-message-view.php']) ? 'active' : ''; ?>">
                <span><i class="bi bi-envelope me-2"></i>Hộp thư liên hệ</span>
                <span id="adminSidebarContactBadgeContainer">
                <?php if ($admin_unread_contacts > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo $admin_unread_contacts; ?></span>
                <?php endif; ?>
                </span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/support-chats.php" class="admin-sidebar-item d-flex justify-content-between align-items-center <?php echo in_array($current_page, ['support-chats.php', 'support-chat-view.php']) ? 'active' : ''; ?>">
                <span><i class="bi bi-chat-dots me-2"></i>Hỗ trợ trực tuyến</span>
                <span id="adminSidebarSupportBadgeContainer">
                <?php if ($admin_unread_support > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo $admin_unread_support; ?></span>
                <?php endif; ?>
                </span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/meal-plans.php" class="admin-sidebar-item <?php echo in_array($current_page, ['meal-plans.php', 'meal-plan-edit.php', 'meal-plan-builder.php']) ? 'active' : ''; ?>">
                <i class="bi bi-journal-check me-2"></i>Thực đơn mẫu
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/chat-logs.php" class="admin-sidebar-item <?php echo $current_page == 'chat-logs.php' ? 'active' : ''; ?>">
                <i class="bi bi-robot me-2"></i>Lịch sử Chatbot AI
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/ai-settings.php" class="admin-sidebar-item <?php echo $current_page == 'ai-settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-key-fill me-2 text-warning"></i>Cấu hình API Key AI
            </a>
            <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="admin-sidebar-item admin-sidebar-item-danger">
                <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
            </a>
        </div>
    </div>
</div>
