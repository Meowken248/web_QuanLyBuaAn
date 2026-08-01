<?php
// pricing.php - hệ thống hiện miễn phí cho mọi tài khoản
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

set_flash_message('info', 'Toàn bộ tính năng hiện được cung cấp miễn phí cho mọi tài khoản.');
redirect(isset($_SESSION['user_id']) ? '/user/dashboard.php' : '/features.php');