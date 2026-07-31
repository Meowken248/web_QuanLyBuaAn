<?php
// index.php
$page_title = 'Trang chủ';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="bg-health text-white py-5">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h1 class="display-4 fw-bold mb-3">Quản lý dinh dưỡng, nâng cao sức khỏe</h1>
                <p class="lead mb-4">Theo dõi bữa ăn, tính toán calories và nhận lời khuyên từ trợ lý AI thông minh để đạt được mục tiêu sức khỏe của bạn.</p>
                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-light btn-lg px-4 me-md-2 text-success fw-bold">Bắt đầu miễn phí</a>
                    <a href="<?php echo BASE_URL; ?>/features.php" class="btn btn-outline-light btn-lg px-4">Tìm hiểu thêm</a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <!-- Placeholder for Hero Image -->
                <img src="img/bg1.jpg" alt="Healthy Food" class="img-fluid rounded-4 shadow-lg" style="max-height: 400px; object-fit: cover;">
            </div>
        </div>
    </div>
</section>

<!-- Features Preview -->
<section class="py-5 bg-white">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Tính năng nổi bật</h2>
            <p class="text-muted">Mọi thứ bạn cần để duy trì thói quen ăn uống lành mạnh</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 card-hover text-center p-4">
                    <div class="card-body">
                        <div class="display-5 text-success mb-3"><i class="bi bi-journal-text"></i></div>
                        <h4 class="card-title fw-bold">Ghi chép bữa ăn</h4>
                        <p class="card-text text-muted">Ghi lại các bữa ăn hàng ngày dễ dàng với hàng nghìn món ăn Việt Nam.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 card-hover text-center p-4">
                    <div class="card-body">
                        <div class="display-5 text-success mb-3"><i class="bi bi-pie-chart-fill"></i></div>
                        <h4 class="card-title fw-bold">Theo dõi dinh dưỡng</h4>
                        <p class="card-text text-muted">Tự động tính toán lượng Calories, Protein, Carb và Fat bạn đã nạp vào.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 card-hover text-center p-4">
                    <div class="card-body">
                        <div class="display-5 text-success mb-3"><i class="bi bi-robot"></i></div>
                        <h4 class="card-title fw-bold">Trợ lý AI Gemini</h4>
                        <p class="card-text text-muted">Nhận lời khuyên dinh dưỡng cá nhân hóa từ trợ lý AI thông minh.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5 bg-light">
    <div class="container text-center py-4">
        <h2 class="fw-bold mb-3">Sẵn sàng thay đổi vóc dáng?</h2>
        <p class="lead text-muted mb-4">Đăng ký tài khoản ngay hôm nay và trải nghiệm các tính năng tuyệt vời.</p>
        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-success btn-lg px-5">Đăng ký ngay</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>