<?php
// config/app.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define Base URL
// Please update this if your folder name in htdocs/www is different
define('BASE_URL', 'http://localhost/web_QuanLyBuaAn');

// Application Constants
define('APP_NAME', 'Meal & Health Manager');
define('APP_VERSION', '1.0.0');

// Basic timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Redirect helper function
if (!function_exists('redirect')) {
    function redirect($path) {
        header("Location: " . BASE_URL . $path);
        exit();
    }
}
