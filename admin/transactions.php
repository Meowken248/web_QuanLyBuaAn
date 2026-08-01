<?php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../includes/functions.php';

set_flash_message('info', 'Hệ thống hiện miễn phí cho mọi tài khoản; quản lý gói và thanh toán đã được tắt.');
redirect('/admin/index.php');