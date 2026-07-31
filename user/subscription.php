<?php
// user/subscription.php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/SubscriptionModel.php';

$subModel = new SubscriptionModel();
$plans = $subModel->getPlans();

$payment_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mock_payment') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $plan_id = $_POST['plan_id'];
        $card_number = $_POST['card_number'];
        $plan = $subModel->getPlanById($plan_id);
        
        if ($plan) {
            $payment_result = $subModel->processMockPayment($_SESSION['user_id'], $plan_id, $plan['price'], $card_number);
            if ($payment_result['status']) {
                set_flash_message('success', $payment_result['message']);
            } else {
                set_flash_message('danger', $payment_result['message']);
            }
        }
    }
}

$page_title = 'Nâng cấp Premium';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold">Chọn gói dịch vụ phù hợp</h2>
        <p class="text-muted">Nâng cấp để trải nghiệm các tính năng AI nâng cao và thực đơn cá nhân hóa</p>
        <?php display_flash_message(); ?>
    </div>
    
    <div class="row justify-content-center">
        <?php foreach ($plans as $plan): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-0 <?php echo $plan['price'] > 0 ? 'border-success border border-2' : ''; ?>">
                    <div class="card-body text-center p-4">
                        <h4 class="fw-bold <?php echo $plan['price'] > 0 ? 'text-success' : ''; ?>"><?php echo htmlspecialchars($plan['name']); ?></h4>
                        <h2 class="my-4 fw-bold">
                            <?php echo number_format($plan['price'], 0, ',', '.'); ?>đ
                            <small class="text-muted fs-6">/<?php echo $plan['duration_days']; ?> ngày</small>
                        </h2>
                        
                        <ul class="list-unstyled text-start mb-4">
                            <?php 
                            $features = explode("\n", $plan['features']);
                            foreach($features as $f): 
                                if(trim($f)):
                            ?>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> <?php echo htmlspecialchars(trim($f)); ?></li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                        
                        <?php if ($plan['price'] > 0): ?>
                            <button type="button" class="btn btn-success w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $plan['id']; ?>">
                                Nâng cấp ngay
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100 fw-bold" disabled>Gói hiện tại</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Modal Thanh Toán Giả Lập -->
            <?php if ($plan['price'] > 0): ?>
            <div class="modal fade" id="paymentModal<?php echo $plan['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">Thanh toán (Mock Payment)</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning small">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Đây là hệ thống thanh toán mô phỏng phục vụ mục đích học tập. Website không xử lý hoặc thu tiền thật.
                            </div>
                            
                            <p><strong>Gói:</strong> <?php echo htmlspecialchars($plan['name']); ?></p>
                            <p><strong>Số tiền:</strong> <span class="text-danger fw-bold"><?php echo number_format($plan['price'], 0, ',', '.'); ?>đ</span></p>
                            <hr>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="mock_payment">
                                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Số thẻ (Nhập 4242 4242 4242 4242 để thành công)</label>
                                    <input type="text" name="card_number" class="form-control font-monospace" placeholder="4242 4242 4242 4242" required>
                                </div>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label text-muted small">Ngày hết hạn</label>
                                        <input type="text" class="form-control font-monospace" placeholder="MM/YY" required>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label text-muted small">CVV</label>
                                        <input type="password" class="form-control font-monospace" placeholder="***" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100 fw-bold">Xác nhận thanh toán giả lập</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
