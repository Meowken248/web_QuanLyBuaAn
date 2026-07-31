<?php
// includes/admin-check.php
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Bạn không có quyền truy cập khu vực này.'
    ];
    header("Location: " . BASE_URL . "/index.php");
    exit();
}
