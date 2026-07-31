<?php
// about.php
require_once __DIR__ . '/config/app.php';
$page_title = 'Giới thiệu';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row align-items-center">
        <div class="col-md-6 mb-4">
            <img src="https://images.unsplash.com/photo-1498837167922-41cfa6bd536f?auto=format&fit=crop&w=800&q=80" alt="Về chúng tôi" class="img-fluid rounded shadow">
        </div>
        <div class="col-md-6">
            <h1 class="fw-bold mb-4">Về <?php echo APP_NAME; ?></h1>
            <p class="lead text-muted">Chúng tôi tin rằng sức khỏe bắt đầu từ những bữa ăn hàng ngày. Sứ mệnh của chúng tôi là giúp bạn quản lý dinh dưỡng một cách dễ dàng và thông minh nhất.</p>
            <p>Với sự phát triển của công nghệ AI, việc theo dõi lượng calo, lên thực đơn và duy trì một lối sống lành mạnh không còn là thách thức quá lớn. Hệ thống cung cấp cho bạn những công cụ toàn diện từ nhật ký bữa ăn đến trợ lý tư vấn cá nhân 24/7.</p>
            <div class="mt-4">
                <a href="<?php echo BASE_URL; ?>/features.php" class="btn btn-success me-2">Khám phá tính năng</a>
                <a href="<?php echo BASE_URL; ?>/contact.php" class="btn btn-outline-success">Liên hệ với chúng tôi</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
