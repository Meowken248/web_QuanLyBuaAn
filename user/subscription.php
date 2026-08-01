<?php
// user/subscription.php - không còn yêu cầu gói hoặc thanh toán
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';

set_flash_message('info', 'Tài khoản của bạn được sử dụng toàn bộ tính năng miễn phí, không cần thanh toán.');
redirect('/user/dashboard.php');