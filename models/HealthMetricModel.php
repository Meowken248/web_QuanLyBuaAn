<?php
// models/HealthMetricModel.php
require_once __DIR__ . '/../config/database.php';

class HealthMetricModel {
    private $conn;
    private $available;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->available = null;
    }

    public function isAvailable() {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $stmt = $this->conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'health_hourly_logs'");
            $this->available = (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->available = false;
        }

        return $this->available;
    }

    public function saveHourlyLog($userId, array $data) {
        if (!$this->isAvailable()) {
            return false;
        }

        $query = "INSERT INTO health_hourly_logs
                    (user_id, log_date, log_hour, water_ml, steps, active_minutes, calories_burned, heart_rate, sleep_minutes, mood_level, note)
                  VALUES
                    (:user_id, :log_date, :log_hour, :water_ml, :steps, :active_minutes, :calories_burned, :heart_rate, :sleep_minutes, :mood_level, :note)
                  ON DUPLICATE KEY UPDATE
                    water_ml = VALUES(water_ml),
                    steps = VALUES(steps),
                    active_minutes = VALUES(active_minutes),
                    calories_burned = VALUES(calories_burned),
                    heart_rate = VALUES(heart_rate),
                    sleep_minutes = VALUES(sleep_minutes),
                    mood_level = VALUES(mood_level),
                    note = VALUES(note),
                    updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':user_id' => $userId,
            ':log_date' => $data['log_date'],
            ':log_hour' => $data['log_hour'],
            ':water_ml' => $data['water_ml'],
            ':steps' => $data['steps'],
            ':active_minutes' => $data['active_minutes'],
            ':calories_burned' => $data['calories_burned'],
            ':heart_rate' => $data['heart_rate'],
            ':sleep_minutes' => $data['sleep_minutes'],
            ':mood_level' => $data['mood_level'],
            ':note' => $data['note']
        ]);
    }

    public function getDailySummary($userId, $date) {
        if (!$this->isAvailable()) {
            return $this->emptySummary();
        }

        $query = "SELECT
                    COALESCE(SUM(water_ml), 0) AS water_ml,
                    COALESCE(SUM(steps), 0) AS steps,
                    COALESCE(SUM(active_minutes), 0) AS active_minutes,
                    COALESCE(SUM(calories_burned), 0) AS calories_burned,
                    ROUND(AVG(NULLIF(heart_rate, 0)), 0) AS avg_heart_rate,
                    COALESCE(SUM(sleep_minutes), 0) AS sleep_minutes,
                    ROUND(AVG(NULLIF(mood_level, 0)), 1) AS avg_mood,
                    COUNT(*) AS logged_hours
                  FROM health_hourly_logs
                  WHERE user_id = :user_id AND log_date = :log_date";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $userId, ':log_date' => $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: $this->emptySummary();
    }

    public function getHourlyLogs($userId, $date) {
        if (!$this->isAvailable()) {
            return [];
        }

        $query = "SELECT log_hour, water_ml, steps, active_minutes, calories_burned,
                         heart_rate, sleep_minutes, mood_level, note
                  FROM health_hourly_logs
                  WHERE user_id = :user_id AND log_date = :log_date
                  ORDER BY log_hour ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $userId, ':log_date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistory($userId, $days = 7) {
        if (!$this->isAvailable()) {
            return [];
        }

        $days = max(1, min(90, (int)$days));
        $query = "SELECT log_date,
                         COALESCE(SUM(water_ml), 0) AS water_ml,
                         COALESCE(SUM(steps), 0) AS steps,
                         COALESCE(SUM(active_minutes), 0) AS active_minutes,
                         COALESCE(SUM(calories_burned), 0) AS calories_burned,
                         ROUND(AVG(NULLIF(heart_rate, 0)), 0) AS avg_heart_rate,
                         COALESCE(SUM(sleep_minutes), 0) AS sleep_minutes,
                         ROUND(AVG(NULLIF(mood_level, 0)), 1) AS avg_mood
                  FROM health_hourly_logs
                  WHERE user_id = :user_id
                    AND log_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                  GROUP BY log_date
                  ORDER BY log_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function emptySummary() {
        return [
            'water_ml' => 0,
            'steps' => 0,
            'active_minutes' => 0,
            'calories_burned' => 0,
            'avg_heart_rate' => null,
            'sleep_minutes' => 0,
            'avg_mood' => null,
            'logged_hours' => 0
        ];
    }
}
