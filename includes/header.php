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
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="bg-light">

<!-- Navbar Public -->
<?php if (!isset($hide_navbar) || !$hide_navbar): ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand company-brand" href="<?php echo BASE_URL; ?>" aria-label="<?php echo htmlspecialchars(APP_NAME); ?>">
            <img src="<?php echo BASE_URL; ?>/img/logo_cty.png" alt="<?php echo htmlspecialchars(APP_NAME); ?>" class="company-logo company-logo-navbar">
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
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 38px; height: 38px; font-weight: bold;">
                                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <strong class="d-none d-md-block"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Tài khoản'); ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end text-small shadow border-0 mt-2" aria-labelledby="dropdownUser">
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Bảng điều khiển</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/user/profile.php"><i class="bi bi-person me-2"></i>Trang cá nhân</a></li>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-bold" href="<?php echo BASE_URL; ?>/admin/index.php"><i class="bi bi-shield-lock me-2"></i>Trang Quản trị</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </div>
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
