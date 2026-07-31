<?php
// models/MealModel.php
require_once __DIR__ . '/../config/database.php';

class MealModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Lấy log của một ngày
    public function getMealLogByDate($user_id, $date) {
        $query = "SELECT * FROM meal_logs WHERE user_id = :user_id AND log_date = :log_date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':log_date', $date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lấy hoặc tạo mới log theo loại bữa ăn (sáng, trưa, chiều...) trong ngày
    public function getOrCreateMealLog($user_id, $date, $meal_type) {
        $query = "SELECT id FROM meal_logs WHERE user_id = :user_id AND log_date = :log_date AND meal_type = :meal_type LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':log_date', $date);
        $stmt->bindParam(':meal_type', $meal_type);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['id'];
        }

        // Tạo mới
        $insertQuery = "INSERT INTO meal_logs (user_id, log_date, meal_type) VALUES (:user_id, :log_date, :meal_type)";
        $insertStmt = $this->conn->prepare($insertQuery);
        $insertStmt->bindParam(':user_id', $user_id);
        $insertStmt->bindParam(':log_date', $date);
        $insertStmt->bindParam(':meal_type', $meal_type);
        if ($insertStmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Thêm món ăn vào bữa
    public function addMealItem($data) {
        $query = "INSERT INTO meal_log_items 
                  (meal_log_id, food_id, quantity, unit, calculated_grams, calories, protein, carbs, fat, fiber)
                  VALUES 
                  (:meal_log_id, :food_id, :quantity, :unit, :calculated_grams, :calories, :protein, :carbs, :fat, :fiber)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    // Xóa món ăn khỏi bữa
    public function deleteMealItem($item_id, $user_id) {
        // Cần verify item này thuộc về user này (thông qua meal_log)
        $query = "DELETE i FROM meal_log_items i 
                  JOIN meal_logs l ON i.meal_log_id = l.id 
                  WHERE i.id = :item_id AND l.user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':item_id', $item_id);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    // Lấy chi tiết các món trong một ngày
    public function getDailyMeals($user_id, $date) {
        $query = "SELECT i.*, f.name as food_name, l.meal_type 
                  FROM meal_log_items i
                  JOIN meal_logs l ON i.meal_log_id = l.id
                  JOIN foods f ON i.food_id = f.id
                  WHERE l.user_id = :user_id AND l.log_date = :log_date
                  ORDER BY l.meal_type, i.id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':log_date', $date);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Nhóm lại theo bữa
        $grouped = [
            'breakfast' => [],
            'morning_snack' => [],
            'lunch' => [],
            'afternoon_snack' => [],
            'dinner' => [],
            'evening_snack' => []
        ];
        
        foreach ($results as $item) {
            $grouped[$item['meal_type']][] = $item;
        }
        
        return $grouped;
    }

    // Tính tổng dinh dưỡng của 1 ngày
    public function getDailyNutrition($user_id, $date) {
        $query = "SELECT 
                    SUM(i.calories) as total_calories,
                    SUM(i.protein) as total_protein,
                    SUM(i.carbs) as total_carbs,
                    SUM(i.fat) as total_fat,
                    SUM(i.fiber) as total_fiber
                  FROM meal_log_items i
                  JOIN meal_logs l ON i.meal_log_id = l.id
                  WHERE l.user_id = :user_id AND l.log_date = :log_date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':log_date', $date);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'calories' => $res['total_calories'] ?? 0,
            'protein' => $res['total_protein'] ?? 0,
            'carbs' => $res['total_carbs'] ?? 0,
            'fat' => $res['total_fat'] ?? 0,
            'fiber' => $res['total_fiber'] ?? 0
        ];
    }

    // Lấy lịch sử calo trong N ngày qua
    public function getCaloriesHistory($user_id, $days = 7) {
        $query = "SELECT l.log_date, SUM(i.calories) as total_calories
                  FROM meal_logs l
                  LEFT JOIN meal_log_items i ON l.id = i.meal_log_id
                  WHERE l.user_id = :user_id 
                  AND l.log_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  GROUP BY l.log_date
                  ORDER BY l.log_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindValue(':days', (int)$days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
