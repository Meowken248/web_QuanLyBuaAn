<?php
// models/FoodModel.php
require_once __DIR__ . '/../config/database.php';

class FoodModel {
    private $conn;
    private $table_name = "foods";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Lấy tất cả danh mục món ăn
    public function getCategories() {
        $query = "SELECT id, name, slug FROM food_categories WHERE status = 'active' ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lấy danh sách món ăn có phân trang và lọc
    public function getFoods($limit = 12, $offset = 0, $search = '', $category_id = null) {
        $query = "SELECT f.*, c.name as category_name 
                  FROM " . $this->table_name . " f
                  LEFT JOIN food_categories c ON f.category_id = c.id
                  WHERE f.status = 'active'";
                  
        if (!empty($search)) {
            $query .= " AND f.name LIKE :search";
        }
        if ($category_id) {
            $query .= " AND f.category_id = :category_id";
        }
        
        $query .= " ORDER BY f.id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindParam(':search', $search_param);
        }
        if ($category_id) {
            $stmt->bindParam(':category_id', $category_id);
        }
        
        // PDO bindParam requires variables for INT if emulation is off, so we bind explicitly
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Đếm tổng số món ăn để phân trang
    public function getTotalFoods($search = '', $category_id = null) {
        $query = "SELECT COUNT(id) as total FROM " . $this->table_name . " WHERE status = 'active'";
        
        if (!empty($search)) {
            $query .= " AND name LIKE :search";
        }
        if ($category_id) {
            $query .= " AND category_id = :category_id";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindParam(':search', $search_param);
        }
        if ($category_id) {
            $stmt->bindParam(':category_id', $category_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Lấy chi tiết món ăn
    public function getFoodById($id) {
        $query = "SELECT f.*, c.name as category_name 
                  FROM " . $this->table_name . " f
                  LEFT JOIN food_categories c ON f.category_id = c.id
                  WHERE f.id = :id AND f.status = 'active' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
