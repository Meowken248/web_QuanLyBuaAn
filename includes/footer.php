</main>
<!-- End Main Content -->

<!-- Footer Public -->
<?php if (!isset($hide_footer) || !$hide_footer): ?>
<footer class="bg-white pt-5 pb-4 mt-5 border-top">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="text-success fw-bold mb-3">
                    <i class="bi bi-heart-pulse-fill me-2"></i><?php echo APP_NAME; ?>
                </h5>
                <p class="text-muted">Hệ thống quản lý bữa ăn, theo dõi dinh dưỡng và chăm sóc sức khỏe cá nhân thông minh với trợ lý AI.</p>
                <div class="mt-4">
                    <a href="#" class="text-secondary me-3 fs-5"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-secondary me-3 fs-5"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-secondary me-3 fs-5"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h6 class="fw-bold mb-3">Liên kết</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none text-muted">Trang chủ</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/features.php" class="text-decoration-none text-muted">Tính năng</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/pricing.php" class="text-decoration-none text-muted">Bảng giá</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/about.php" class="text-decoration-none text-muted">Giới thiệu</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4 mb-4">
                <h6 class="fw-bold mb-3">Hỗ trợ</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/contact.php" class="text-decoration-none text-muted">Liên hệ</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Điều khoản sử dụng</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Chính sách bảo mật</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Disclaimer sức khỏe</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4 mb-4">
                <h6 class="fw-bold mb-3">Cảnh báo (Disclaimer)</h6>
                <p class="text-muted small">Website chỉ cung cấp thông tin tham khảo, không thay thế lời khuyên, chẩn đoán hoặc điều trị từ bác sĩ hoặc chuyên gia dinh dưỡng.</p>
            </div>
        </div>
        <hr class="mt-4 mb-4 text-muted">
        <div class="text-center text-muted small">
            &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js (included globally or can be conditional) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
