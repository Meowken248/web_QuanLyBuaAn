<?php
// config/app.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==============================================================================
// CẤU HÌNH ĐƯỜNG DẪN (BASE_URL)
// 1. Để trống '' : Hệ thống TỰ ĐỘNG nhận diện (chạy trên mọi máy localhost / WAMP / XAMPP)
// 2. Điền link vào đây: Nếu bạn muốn ép buộc cố định tên miền (vd: 'https://meal.plt.pro.vn')
// ==============================================================================
$manualBaseUrl = ''; // <-- BẠN ĐỔI ĐƯỜNG DẪN Ở ĐÂY NẾU MUỐN ÉP LINK CỐ ĐỊNH!

if (!empty($manualBaseUrl)) {
    define('BASE_URL', rtrim($manualBaseUrl, '/'));
} elseif (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
    $appRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptFilename = !empty($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']) : '';

    $subDir = '';
    if (!empty($docRoot) && strtolower(substr($appRoot, 0, strlen($docRoot))) === strtolower($docRoot)) {
        $subDir = substr($appRoot, strlen($docRoot));
    } elseif (!empty($scriptFilename) && !empty($appRoot) && strtolower(substr($scriptFilename, 0, strlen($appRoot))) === strtolower($appRoot)) {
        $relPath = substr($scriptFilename, strlen($appRoot));
        if (!empty($relPath) && substr($scriptName, -strlen($relPath)) === $relPath) {
            $subDir = substr($scriptName, 0, strlen($scriptName) - strlen($relPath));
        }
    } elseif (!empty($scriptName) && $scriptName !== '.') {
        $dir = dirname($scriptName);
        $subDir = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : $dir;
    }

    // Fallback nếu chạy CLI hoặc cấu hình web server không truyền DOCUMENT_ROOT
    if (empty($subDir) || $subDir === '/' || $subDir === '.') {
        if (preg_match('#/(?:www|htdocs)/(.+)$#i', $appRoot, $matches)) {
            $subDir = '/' . $matches[1];
        } else {
            $subDir = '';
        }
    }

    $cleanSubDir = trim(str_replace('\\', '/', $subDir), '/');
    $detectedBaseUrl = rtrim($protocol . $host . ($cleanSubDir !== '' ? '/' . $cleanSubDir : ''), '/');
    define('BASE_URL', $detectedBaseUrl);
}

// Application Constants
define('APP_NAME', 'Meal & Health Manager');
define('APP_VERSION', '1.0.0');
define('ROOT_ADMIN_EMAIL', 'kimthuyen@gmail.com');

// Basic timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Redirect helper function
if (!function_exists('redirect')) {
    function redirect($path) {
        header("Location: " . BASE_URL . $path);
        exit();
    }
}
