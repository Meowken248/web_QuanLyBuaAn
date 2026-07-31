<?php
// models/UserModel.php
require_once __DIR__ . '/../config/database.php';

class UserModel {
    private $conn;
    private $table_name = "users";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Kiểm tra email đã tồn tại chưa
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Đăng ký người dùng mới
    public function register($full_name, $email, $password) {
        $query = "INSERT INTO " . $this->table_name . " (full_name, email, password, role, status) VALUES (:full_name, :email, :password, 'user', 'active')";
        $stmt = $this->conn->prepare($query);
        
        // Mã hóa mật khẩu
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password_hash);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Đăng nhập
    public function login($email, $password) {
        $query = "SELECT id, full_name, password, role, status FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kiểm tra trạng thái tài khoản
            if ($row['status'] !== 'active') {
                return ['status' => false, 'message' => 'Tài khoản của bạn đã bị khóa.'];
            }
            
            // Kiểm tra mật khẩu
            if (password_verify($password, $row['password'])) {
                // Đăng nhập thành công, tạo lại session ID để chống Session Fixation
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_role'] = $row['role'];
                
                return ['status' => true, 'message' => 'Đăng nhập thành công.'];
            }
        }
        
        return ['status' => false, 'message' => 'Email hoặc mật khẩu không chính xác.'];
    }

    // Lấy thông tin user
    public function getUserById($id) {
        $query = "SELECT id, full_name, email, avatar, role, status FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
