<?php
// models/WeightModel.php
require_once __DIR__ . '/../config/database.php';

class WeightModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getWeightHistory($user_id, $limit = 30) {
        $query = "SELECT log_date, weight_kg as weight, bmi FROM weight_logs WHERE user_id = :user_id ORDER BY log_date ASC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
