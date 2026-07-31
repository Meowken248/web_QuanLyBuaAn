<?php
// features.php
require_once __DIR__ . '/config/app.php';
$page_title = 'Tính năng';
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-success text-white py-5">
    <div class="container text-center py-4">
        <h1 class="display-4 fw-bold">Khám Phá Các Tính Năng</h1>
        <p class="lead mb-0">Công cụ toàn diện giúp bạn đạt được mục tiêu sức khỏe và vóc dáng</p>
    </div>
</div>

<div class="container py-5">
    <div class="row align-items-center mb-5">
        <div class="col-md-6 order-md-2 mb-4 mb-md-0">
            <img src="https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=800&q=80" alt="Hồ sơ sức khỏe" class="img-fluid rounded-4 shadow">
        </div>
        <div class="col-md-6 order-md-1 pe-md-5">
            <div class="text-success mb-3"><i class="bi bi-person-lines-fill fs-1"></i></div>
            <h2 class="fw-bold mb-3">Hồ sơ sức khỏe cá nhân hóa</h2>
            <p class="text-muted fs-5">Cung cấp chiều cao, cân nặng, độ tuổi và mức độ vận động. Hệ thống sẽ tự động tính toán:</p>
            <ul class="list-unstyled mt-4">
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-success me-3 fs-5"></i> <div><strong>Chỉ số BMI:</strong> Đánh giá tình trạng cơ thể.</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-success me-3 fs-5"></i> <div><strong>Chỉ số BMR & TDEE:</strong> Biết chính xác lượng calo cơ thể đốt cháy mỗi ngày.</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-success me-3 fs-5"></i> <div><strong>Mục tiêu Calories:</strong> Gợi ý lượng calo nạp vào dựa trên mục tiêu tăng/giảm cân của bạn.</div></li>
            </ul>
        </div>
    </div>

    <hr class="my-5 text-muted">

    <div class="row align-items-center mb-5">
        <div class="col-md-6 mb-4 mb-md-0">
            <img src="https://images.unsplash.com/photo-1505253716362-afaea1d3d1af?auto=format&fit=crop&w=800&q=80" alt="Nhật ký bữa ăn" class="img-fluid rounded-4 shadow">
        </div>
        <div class="col-md-6 ps-md-5">
            <div class="text-primary mb-3"><i class="bi bi-journal-bookmark fs-1"></i></div>
            <h2 class="fw-bold mb-3">Nhật ký bữa ăn thông minh</h2>
            <p class="text-muted fs-5">Ghi chép và theo dõi lượng thức ăn nạp vào mỗi ngày chưa bao giờ dễ dàng đến thế.</p>
            <ul class="list-unstyled mt-4">
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-primary me-3 fs-5"></i> <div><strong>Thư viện thực phẩm phong phú:</strong> Hàng trăm món ăn với thông tin dinh dưỡng chi tiết (Protein, Carbs, Fat).</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-primary me-3 fs-5"></i> <div><strong>Tự động tính toán:</strong> Nhập số gram, hệ thống tự động quy đổi lượng Calories và Macros.</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-primary me-3 fs-5"></i> <div><strong>Phân loại bữa ăn:</strong> Sáng, Trưa, Chiều, Tối để dễ dàng quản lý.</div></li>
            </ul>
        </div>
    </div>

    <hr class="my-5 text-muted">

    <div class="row align-items-center mb-5">
        <div class="col-md-6 order-md-2 mb-4 mb-md-0">
            <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80" alt="Dashboard trực quan" class="img-fluid rounded-4 shadow">
        </div>
        <div class="col-md-6 order-md-1 pe-md-5">
            <div class="text-warning mb-3"><i class="bi bi-pie-chart-fill fs-1"></i></div>
            <h2 class="fw-bold mb-3">Dashboard & Biểu đồ trực quan</h2>
            <p class="text-muted fs-5">Cái nhìn tổng quan về tiến trình của bạn thông qua các báo cáo và biểu đồ chuyên nghiệp.</p>
            <ul class="list-unstyled mt-4">
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-warning me-3 fs-5"></i> <div><strong>Biểu đồ Calories 7 ngày:</strong> Theo dõi sự thay đổi lượng calo qua từng ngày.</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-warning me-3 fs-5"></i> <div><strong>Biểu đồ Macros (Doughnut):</strong> Xem tỷ lệ Protein, Carbs, Fat nạp vào trong ngày.</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-warning me-3 fs-5"></i> <div><strong>Cảnh báo calo:</strong> Biết ngay bạn còn được phép ăn thêm bao nhiêu calo trong ngày hôm nay.</div></li>
            </ul>
        </div>
    </div>

    <hr class="my-5 text-muted">

    <div class="row align-items-center">
        <div class="col-md-6 mb-4 mb-md-0">
            <img src="https://images.unsplash.com/photo-1531746790731-6c087fecd65a?auto=format&fit=crop&w=800&q=80" alt="Chatbot AI" class="img-fluid rounded-4 shadow">
        </div>
        <div class="col-md-6 ps-md-5">
            <div class="text-info mb-3"><i class="bi bi-robot fs-1"></i></div>
            <h2 class="fw-bold mb-3">Trợ lý AI Gemini thông minh</h2>
            <p class="text-muted fs-5">Như một chuyên gia dinh dưỡng 24/7, sẵn sàng giải đáp mọi thắc mắc của bạn.</p>
            <ul class="list-unstyled mt-4">
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-info me-3 fs-5"></i> <div><strong>Tư vấn cá nhân hóa:</strong> AI biết các chỉ số của bạn và lượng calo bạn đã nạp hôm nay để đưa ra lời khuyên chính xác.</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-info me-3 fs-5"></i> <div><strong>Gợi ý thực đơn:</strong> Gợi ý các món ăn phù hợp với mục tiêu (giảm cân, tăng cơ).</div></li>
                <li class="mb-3 d-flex"><i class="bi bi-check-circle-fill text-info me-3 fs-5"></i> <div><strong>Hỗ trợ Premium:</strong> Người dùng Premium nhận được phản hồi nhanh và chi tiết hơn.</div></li>
            </ul>
        </div>
    </div>
</div>

<div class="bg-light py-5 mt-4 text-center">
    <div class="container">
        <h2 class="fw-bold mb-4">Sẵn sàng bắt đầu hành trình của bạn?</h2>
        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn btn-success btn-lg px-5 py-3 rounded-pill fw-bold shadow">Đăng ký hoàn toàn miễn phí</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
