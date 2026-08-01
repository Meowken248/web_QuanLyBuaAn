<?php
// models/SubscriptionModel.php
// Giữ lớp tương thích cho mã cũ; hệ thống không còn bán gói hoặc xử lý thanh toán.
class SubscriptionModel {
    public function getPlans() {
        return [];
    }

    public function getPlanById($id) {
        return null;
    }

    public function processMockPayment($user_id, $plan_id, $amount, $card_number) {
        return [
            'status' => false,
            'transaction_code' => null,
            'message' => 'Hệ thống hiện miễn phí cho mọi tài khoản và không yêu cầu thanh toán.'
        ];
    }
}