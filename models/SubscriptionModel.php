<?php
// models/SubscriptionModel.php
require_once __DIR__ . '/../config/database.php';

class SubscriptionModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Lấy danh sách gói
    public function getPlans() {
        $query = "SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY price ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlanById($id) {
        $query = "SELECT * FROM subscription_plans WHERE id = :id AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Ghi lại giao dịch giả lập
    public function processMockPayment($user_id, $plan_id, $amount, $card_number) {
        $transaction_code = 'TXN' . time() . rand(1000, 9999);
        $status = 'failed';
        $message = 'Giao dịch thất bại do thẻ bị từ chối';

        // Giả lập logic theo yêu cầu: 4242 4242 4242 4242 là thành công
        $card_clean = str_replace(' ', '', $card_number);
        if ($card_clean === '4242424242424242') {
            $status = 'success';
            $message = 'Thanh toán thành công (Giả lập)';
            
            // Cập nhật subscription của user
            $this->updateUserSubscription($user_id, $plan_id);
        }

        $query = "INSERT INTO transactions (user_id, plan_id, transaction_code, amount, payment_method, status, message) 
                  VALUES (:user_id, :plan_id, :code, :amount, 'Credit Card Mock', :status, :message)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':plan_id', $plan_id);
        $stmt->bindParam(':code', $transaction_code);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':message', $message);
        $stmt->execute();
        
        return [
            'status' => $status === 'success',
            'transaction_code' => $transaction_code,
            'message' => $message
        ];
    }

    private function updateUserSubscription($user_id, $plan_id) {
        $plan = $this->getPlanById($plan_id);
        if (!$plan) return false;

        $duration = $plan['duration_days'];
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime("+$duration days"));

        // Kiểm tra xem đã có sub chưa
        $query = "SELECT id FROM user_subscriptions WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $update = "UPDATE user_subscriptions SET plan_id = :plan_id, start_date = :start, end_date = :end, status = 'active' WHERE user_id = :user_id";
            $upStmt = $this->conn->prepare($update);
            $upStmt->bindParam(':plan_id', $plan_id);
            $upStmt->bindParam(':start', $start_date);
            $upStmt->bindParam(':end', $end_date);
            $upStmt->bindParam(':user_id', $user_id);
            return $upStmt->execute();
        } else {
            $insert = "INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status) VALUES (:user_id, :plan_id, :start, :end, 'active')";
            $inStmt = $this->conn->prepare($insert);
            $inStmt->bindParam(':user_id', $user_id);
            $inStmt->bindParam(':plan_id', $plan_id);
            $inStmt->bindParam(':start', $start_date);
            $inStmt->bindParam(':end', $end_date);
            return $inStmt->execute();
        }
    }
}
