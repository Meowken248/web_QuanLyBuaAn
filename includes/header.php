<?php
// includes/header.php
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="bg-light">

<!-- Navbar Public -->
<?php if (!isset($hide_navbar) || !$hide_navbar): ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand text-success fw-bold" href="<?php echo BASE_URL; ?>">
            <i class="bi bi-heart-pulse-fill me-2"></i><?php echo APP_NAME; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'pricing.php') ? 'active text-success' : ''; ?>" href="<?php echo BASE_URL; ?>/pricing.php">Bảng giá</a>
                </li>
            </ul>
            <div class="d-flex">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="btn btn-outline-success me-2">Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="btn btn-danger">Đăng xuất</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn btn-outline-success me-2">Đăng nhập</a>
                    <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-success">Đăng ký miễn phí</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Main Content wrapper -->
<main class="min-vh-100">
