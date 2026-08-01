<?php
// models/ProfileModel.php
require_once __DIR__ . '/../config/database.php';

class ProfileModel {
    private $conn;
    private $table_name = "user_profiles";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Lấy hồ sơ người dùng
    public function getProfileByUserId($user_id) {
        $query = "SELECT *, height_cm as height, current_weight_kg as current_weight FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lưu hoặc cập nhật hồ sơ
    public function saveProfile($data) {
        // Kiểm tra xem đã có profile chưa
        if ($this->getProfileByUserId($data['user_id'])) {
            // Cập nhật
            $query = "UPDATE " . $this->table_name . " SET 
                date_of_birth = :date_of_birth,
                age = :age,
                gender = :gender,
                height_cm = :height,
                current_weight_kg = :current_weight,
                activity_level = :activity_level,
                health_goal = :health_goal,
                goal_pace = :goal_pace,
                diet_type = :diet_type,
                allergies = :allergies,
                disliked_foods = :disliked_foods,
                meals_per_day = :meals_per_day,
                bmr = :bmr,
                tdee = :tdee,
                calorie_target = :calorie_target,
                protein_target = :protein_target,
                carb_target = :carb_target,
                fat_target = :fat_target
                WHERE user_id = :user_id";
        } else {
            // Thêm mới
            $query = "INSERT INTO " . $this->table_name . " 
                (user_id, date_of_birth, age, gender, height_cm, current_weight_kg, activity_level, health_goal, goal_pace, diet_type, allergies, disliked_foods, meals_per_day, bmr, tdee, calorie_target, protein_target, carb_target, fat_target)
                VALUES 
                (:user_id, :date_of_birth, :age, :gender, :height, :current_weight, :activity_level, :health_goal, :goal_pace, :diet_type, :allergies, :disliked_foods, :meals_per_day, :bmr, :tdee, :calorie_target, :protein_target, :carb_target, :fat_target)";
        }

        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':date_of_birth', $data['date_of_birth']);
        $stmt->bindParam(':age', $data['age']);
        $stmt->bindParam(':gender', $data['gender']);
        $stmt->bindParam(':height', $data['height']);
        $stmt->bindParam(':current_weight', $data['current_weight']);
        $stmt->bindParam(':activity_level', $data['activity_level']);
        $stmt->bindParam(':health_goal', $data['health_goal']);
        $stmt->bindParam(':goal_pace', $data['goal_pace']);
        $stmt->bindParam(':diet_type', $data['diet_type']);
        $stmt->bindParam(':allergies', $data['allergies']);
        $stmt->bindParam(':disliked_foods', $data['disliked_foods']);
        $stmt->bindParam(':meals_per_day', $data['meals_per_day']);
        $stmt->bindParam(':bmr', $data['bmr']);
        $stmt->bindParam(':tdee', $data['tdee']);
        $stmt->bindParam(':calorie_target', $data['calorie_target']);
        $stmt->bindParam(':protein_target', $data['protein_target']);
        $stmt->bindParam(':carb_target', $data['carb_target']);
        $stmt->bindParam(':fat_target', $data['fat_target']);
        
        return $stmt->execute();
    }
}
