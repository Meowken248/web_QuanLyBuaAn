<?php
// config/gemini.php

// Mặc định tĩnh ban đầu
$defaultApiKey = '';
$defaultModel = 'gemini-3.1-flash-lite';

// Tự động kiểm tra trong CSDL (system_settings) để ưu tiên key mới nhất admin đã cấu hình trong trang quản trị
try {
    if (!class_exists('Database')) {
        $dbFile = __DIR__ . '/database.php';
        if (file_exists($dbFile)) {
            require_once $dbFile;
        }
    }
    if (class_exists('Database')) {
        $database = new Database();
        $conn = $database->getConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gemini_api_key', 'gemini_model')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($settings['gemini_api_key']) && trim($settings['gemini_api_key']) !== '') {
                $defaultApiKey = trim($settings['gemini_api_key']);
            }
            if (!empty($settings['gemini_model']) && trim($settings['gemini_model']) !== '') {
                $defaultModel = trim($settings['gemini_model']);
            }
        }
    }
} catch (Exception $e) {}

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', $defaultApiKey);
}
if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', $defaultModel);
}
if (!defined('GEMINI_API_URL')) {
    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');
}
