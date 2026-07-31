<?php
// pricing.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/SubscriptionModel.php';

$subModel = new SubscriptionModel();
$plans = $subModel->getPlans();

$page_title = 'Bảng giá';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold text-success">Bảng giá dịch vụ</h1>
        <p class="text-muted lead">Lựa chọn gói dịch vụ phù hợp với mục tiêu sức khỏe của bạn</p>
    </div>

    <div class="row justify-content-center">
        <?php foreach ($plans as $plan): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow border-0 <?php echo $plan['price'] > 0 ? 'border-success border border-2' : ''; ?> hover-shadow transition-all">
                    <div class="card-body text-center p-4">
                        <?php if ($plan['price'] > 0): ?>
                            <div class="badge bg-success text-white mb-3 px-3 py-2 rounded-pill">Khuyên dùng</div>
                        <?php endif; ?>
                        
                        <h4 class="fw-bold <?php echo $plan['price'] > 0 ? 'text-success' : ''; ?>"><?php echo htmlspecialchars($plan['name']); ?></h4>
                        
                        <h2 class="my-4 fw-bold display-5">
                            <?php echo number_format($plan['price'], 0, ',', '.'); ?>đ
                            <small class="text-muted fs-6 d-block mt-2">/<?php echo $plan['duration_days']; ?> ngày</small>
                        </h2>
                        
                        <hr class="my-4 text-muted">
                        
                        <ul class="list-unstyled text-start mb-4">
                            <?php 
                            $features = explode("\n", $plan['features']);
                            foreach($features as $f): 
                                if(trim($f)):
                            ?>
                                <li class="mb-3 d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill text-success me-3 fs-5"></i> 
                                    <span><?php echo htmlspecialchars(trim($f)); ?></span>
                                </li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                    </div>
                    <div class="card-footer bg-white border-0 p-4 text-center">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo BASE_URL; ?>/user/subscription.php" class="btn <?php echo $plan['price'] > 0 ? 'btn-success' : 'btn-outline-success'; ?> w-100 py-3 fw-bold rounded-pill shadow-sm">Nâng cấp ngay</a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn <?php echo $plan['price'] > 0 ? 'btn-success' : 'btn-outline-success'; ?> w-100 py-3 fw-bold rounded-pill shadow-sm">Đăng nhập để đăng ký</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- FAQ Section -->
<div class="bg-light py-5 mt-5">
    <div class="container">
        <h3 class="text-center fw-bold mb-4">Câu hỏi thường gặp</h3>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="accordion accordion-flush shadow-sm" id="accordionFAQ">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                Tôi có thể hủy gói Premium bất cứ lúc nào không?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">
                                Có, bạn có thể hủy gia hạn gói Premium bất cứ lúc nào trong phần cài đặt tài khoản của mình. Bạn vẫn có thể sử dụng các tính năng Premium cho đến hết chu kỳ đã thanh toán.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Tính năng Chatbot AI hỗ trợ những gì?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">
                                Chatbot AI sử dụng mô hình Gemini tiên tiến, có thể tư vấn thực đơn cá nhân hóa, đề xuất bài tập, và giải đáp các thắc mắc về dinh dưỡng dựa trên thông số BMI, TDEE và mục tiêu sức khỏe riêng của bạn.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
