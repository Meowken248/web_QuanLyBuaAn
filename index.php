<?php
// index.php
$page_title = 'Trang chủ';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="bg-health text-white position-relative" style="padding: 100px 0; overflow: hidden;">
    <!-- Abstract shape background -->
    <div class="position-absolute" style="top: -100px; right: -100px; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(50px);"></div>
    <div class="position-absolute" style="bottom: -50px; left: -50px; width: 300px; height: 300px; background: rgba(0,0,0,0.1); border-radius: 50%; filter: blur(40px);"></div>
    
    <div class="container position-relative z-index-1">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0 animate-fade-in-up">
                <div class="badge bg-white text-success mb-3 px-3 py-2 shadow-sm rounded-pill">✨ Phiên bản mới 2026</div>
                <h1 class="display-4 fw-bold mb-4" style="line-height: 1.2;">Kiểm soát Dinh dưỡng,<br><span style="color: #fbbf24;">Làm chủ Sức khỏe</span></h1>
                <p class="lead mb-4 opacity-75">Theo dõi bữa ăn, tính toán lượng calories tự động và nhận lời khuyên từ Trợ lý AI thông minh để đạt được vóc dáng mơ ước của bạn.</p>
                <div class="d-grid gap-3 d-md-flex justify-content-md-start">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-light btn-lg px-4 me-md-2 text-success fw-bold shadow-soft" style="border-radius: 30px;">Bắt đầu miễn phí</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="btn btn-light btn-lg px-4 me-md-2 text-success fw-bold shadow-soft" style="border-radius: 30px;">Về Bảng điều khiển</a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/features.php" class="btn btn-outline-light btn-lg px-4" style="border-radius: 30px;">Khám phá Tính năng</a>
                </div>
            </div>
            <div class="col-lg-6 text-center animate-fade-in-up delay-200">
                <div class="position-relative">
                    <img src="img/bg1.jpg" alt="Healthy Food" class="img-fluid rounded-4 shadow-heavy" style="max-height: 450px; object-fit: cover; border: 4px solid rgba(255,255,255,0.2);">
                    <!-- Floating Badge -->
                    <div class="position-absolute bg-white text-dark p-3 rounded-4 shadow-lg glass-card d-flex align-items-center animate-fade-in-up delay-300" style="bottom: -20px; left: -20px; animation-duration: 1s; animation-iteration-count: infinite; animation-direction: alternate; animation-name: floatBadge;">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;"><i class="bi bi-robot"></i></div>
                        <div class="text-start">
                            <h6 class="mb-0 fw-bold">Trợ lý AI</h6>
                            <small class="text-muted">Đang trực tuyến</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
@keyframes floatBadge {
    0% { transform: translateY(0px); }
    100% { transform: translateY(-10px); }
}
</style>

<!-- Features Preview -->
<section class="py-5" style="background-color: var(--bg-color);">
    <div class="container py-5">
        <div class="text-center mb-5 animate-fade-in-up">
            <h2 class="fw-bold mb-3 text-dark">Tính năng <span class="text-success">Đột phá</span></h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Hệ sinh thái công cụ cao cấp giúp bạn dễ dàng duy trì thói quen ăn uống lành mạnh mỗi ngày.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4 animate-fade-in-up delay-100">
                <div class="card h-100 card-premium card-hover p-2">
                    <div class="card-body p-4 text-center">
                        <div class="d-inline-block bg-success bg-opacity-10 text-success p-3 rounded-circle mb-4">
                            <i class="bi bi-journal-check fs-2"></i>
                        </div>
                        <h4 class="card-title fw-bold mb-3">Kế hoạch Hoàn hảo</h4>
                        <p class="card-text text-muted">Xây dựng và theo dõi các thực đơn bữa ăn phù hợp với mục tiêu giảm mỡ, tăng cơ.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-in-up delay-200">
                <div class="card h-100 card-premium card-hover p-2">
                    <div class="card-body p-4 text-center">
                        <div class="d-inline-block bg-warning bg-opacity-10 text-warning p-3 rounded-circle mb-4">
                            <i class="bi bi-pie-chart-fill fs-2"></i>
                        </div>
                        <h4 class="card-title fw-bold mb-3">Đo lường Chi tiết</h4>
                        <p class="card-text text-muted">Hệ thống tự động tính toán tổng lượng Calories, Protein, Carb và Fat chuẩn xác.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-in-up delay-300">
                <div class="card h-100 card-premium card-hover p-2">
                    <div class="card-body p-4 text-center">
                        <div class="d-inline-block bg-primary bg-opacity-10 text-primary p-3 rounded-circle mb-4">
                            <i class="bi bi-robot fs-2"></i>
                        </div>
                        <h4 class="card-title fw-bold mb-3">AI Cố vấn</h4>
                        <p class="card-text text-muted">Trò chuyện với Trợ lý ảo AI bất cứ lúc nào để hỏi đáp về sức khỏe và dinh dưỡng.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!isset($_SESSION['user_id'])): ?>
<!-- Call to Action -->
<section class="py-5 position-relative bg-dark-gradient overflow-hidden">
    <div class="container text-center py-5 position-relative z-index-1 animate-fade-in-up">
        <h2 class="fw-bold mb-3 text-white">Sẵn sàng để thay đổi vóc dáng?</h2>
        <p class="lead text-white-50 mb-5 mx-auto" style="max-width: 600px;">Tham gia cộng đồng của chúng tôi ngay hôm nay và trải nghiệm miễn phí toàn bộ công cụ dinh dưỡng cao cấp.</p>
        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-success btn-glow btn-lg px-5 shadow-heavy fw-bold" style="border-radius: 30px;">Tạo tài khoản Miễn phí</a>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>