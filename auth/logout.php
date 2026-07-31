<?php
// auth/logout.php
require_once __DIR__ . '/../config/app.php';

// Hủy tất cả session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Chuyển về trang chủ
header("Location: " . BASE_URL . "/index.php");
exit();
