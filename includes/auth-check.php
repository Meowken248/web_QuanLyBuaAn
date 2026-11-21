<?php
// includes/auth-check.php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'message' => 'Vui lòng đăng nhập để truy cập trang này.'
    ];
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}
