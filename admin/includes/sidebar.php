<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile Toggle Button -->
<div class="d-md-none mb-3">
    <button class="btn btn-primary w-100 fw-bold" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
        <i class="bi bi-list me-2"></i>Menu Quản trị
    </button>
</div>

<!-- Sidebar / Offcanvas -->
<div class="offcanvas-md offcanvas-start" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="adminSidebarLabel">Menu Quản trị</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 p-md-0 d-block">
        <div class="list-group shadow-sm mb-4 w-100 rounded-0 rounded-md-3">
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="list-group-item list-group-item-action <?php echo $current_page == 'index.php' ? 'active bg-dark border-dark' : 'text-dark'; ?>">
                <i class="bi bi-speedometer2 me-2"></i>Bảng điều khiển
            </a>
            <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="list-group-item list-group-item-action text-success fw-bold">
                <i class="bi bi-person-workspace me-2"></i>Chuyển sang trang người dùng
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="list-group-item list-group-item-action <?php echo in_array($current_page, ['users.php', 'user-edit.php']) ? 'active bg-dark border-dark' : 'text-dark'; ?>">
                <i class="bi bi-people me-2"></i>Quản lý người dùng
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/foods.php" class="list-group-item list-group-item-action <?php echo in_array($current_page, ['foods.php', 'food-edit.php']) ? 'active bg-dark border-dark' : 'text-dark'; ?>">
                <i class="bi bi-egg-fried me-2"></i>Thư viện món ăn
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/food-categories.php" class="list-group-item list-group-item-action <?php echo in_array($current_page, ['food-categories.php', 'food-category-edit.php']) ? 'active bg-dark border-dark' : 'text-dark'; ?>">
                <i class="bi bi-tags me-2"></i>Danh mục món ăn
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/contact-messages.php" class="list-group-item list-group-item-action <?php echo in_array($current_page, ['contact-messages.php', 'contact-message-view.php']) ? 'active bg-dark border-dark' : 'text-dark'; ?>">
                <i class="bi bi-envelope me-2"></i>Hộp thư liên hệ
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/meal-plans.php" class="list-group-item list-group-item-action <?php echo in_array($current_page, ['meal-plans.php', 'meal-plan-edit.php', 'meal-plan-builder.php']) ? 'active bg-dark border-dark' : 'text-dark'; ?>">
                <i class="bi bi-journal-check me-2"></i>Thực đơn mẫu
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/chat-logs.php" class="list-group-item list-group-item-action <?php echo $current_page == 'chat-logs.php' ? 'active bg-dark border-dark' : 'text-dark'; ?>">
                <i class="bi bi-robot me-2"></i>Lịch sử Chatbot AI
            </a>
            <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="list-group-item list-group-item-action text-danger">
                <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
            </a>
        </div>
    </div>
</div>
