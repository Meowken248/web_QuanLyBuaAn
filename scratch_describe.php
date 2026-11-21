<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    // 1. Tạo bảng classes
    $sqlCreate = "CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_code VARCHAR(50) NOT NULL UNIQUE,
        class_name VARCHAR(150) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sqlCreate);

    // 2. Thêm cột class_id vào bảng users để liên kết
    $sqlAlter = "ALTER TABLE users ADD COLUMN class_id INT NULL AFTER id";
    $conn->exec($sqlAlter);
    
    // (Tùy chọn) Thêm khóa ngoại
    $conn->exec("ALTER TABLE users ADD CONSTRAINT fk_user_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL");

    echo "Tạo bảng classes và thêm liên kết thành công!";
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage();
}
