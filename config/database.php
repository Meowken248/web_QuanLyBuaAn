<?php
// config/database.php

class Database {
    private $host = "127.0.0.1";
    private $db_name = "meal_health_manager";
    private $username = "root";
    private $password = ""; // Change this if you have a MySQL password
    public $conn;

    // Lấy kết nối CSDL (Sử dụng Singleton Pattern hoặc gọi trực tiếp)
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            // Thiết lập chế độ lỗi PDO để dễ debug
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Lỗi kết nối CSDL: " . $exception->getMessage();
            exit();
        }

        return $this->conn;
    }
}
