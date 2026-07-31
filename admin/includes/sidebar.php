<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="list-group shadow-sm mb-4">
    <a href="<?php echo BASE_URL; ?>/admin/index.php" class="list-group-item list-group-item-action <?php echo $current_page == 'index.php' ? 'active bg-dark border-dark' : 'text-dark'; ?>">
        <i class="bi bi-speedometer2 me-2"></i>Tổng quan
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="list-group-item list-group-item-action <?php echo strpos($current_page, 'user') !== false ? 'active bg-dark border-dark' : 'text-dark'; ?>">
        <i class="bi bi-people me-2"></i>Người dùng
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/food-categories.php" class="list-group-item list-group-item-action <?php echo strpos($current_page, 'food-categor') !== false ? 'active bg-dark border-dark' : 'text-dark'; ?>">
        <i class="bi bi-tags me-2"></i>Danh mục Món ăn
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/foods.php" class="list-group-item list-group-item-action <?php echo $current_page == 'foods.php' || $current_page == 'food-edit.php' ? 'active bg-dark border-dark' : 'text-dark'; ?>">
        <i class="bi bi-egg-fried me-2"></i>Thư viện Món ăn
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/subscription-plans.php" class="list-group-item list-group-item-action <?php echo strpos($current_page, 'subscription-plan') !== false ? 'active bg-dark border-dark' : 'text-dark'; ?>">
        <i class="bi bi-star me-2"></i>Gói Đăng ký
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/transactions.php" class="list-group-item list-group-item-action <?php echo $current_page == 'transactions.php' ? 'active bg-dark border-dark' : 'text-dark'; ?>">
        <i class="bi bi-credit-card me-2"></i>Giao dịch
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/contact-messages.php" class="list-group-item list-group-item-action <?php echo strpos($current_page, 'contact-message') !== false ? 'active bg-dark border-dark' : 'text-dark'; ?>">
        <i class="bi bi-envelope me-2"></i>Hộp thư Liên hệ
    </a>
    <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="list-group-item list-group-item-action text-danger">
        <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
    </a>
</div>
