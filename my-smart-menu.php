<?php
// my-smart-menu.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    redirect('/login.php'); // Assuming there's a login redirect helper or just handle it
}

$page_title = "Thực Đơn Của Tôi";
require_once __DIR__ . '/includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM user_smart_menus WHERE user_id = :user_id AND status = 'active' LIMIT 1");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$activeMenu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activeMenu) {
    echo '<div class="container py-5 text-center">';
    echo '<h3 class="mb-4 text-muted">Bạn chưa lưu thực đơn thông minh nào.</h3>';
    echo '<a href="' . BASE_URL . '/smart-menu.php" class="btn btn-success btn-lg rounded-pill"><i class="bi bi-magic me-2"></i>Tạo Thực Đơn Ngay</a>';
    echo '</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$ketQua = json_decode($activeMenu['menu_data'], true);
$completedDays = json_decode($activeMenu['completed_days'] ?? '[]', true);

// Xử lý Hủy thực đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $stmtCancel = $conn->prepare("UPDATE user_smart_menus SET status = 'cancelled' WHERE id = :id");
    $stmtCancel->execute([':id' => $activeMenu['id']]);
    redirect('/my-smart-menu.php');
}

?>

<style>
.hero-smart-menu {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    padding: 40px 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
.custom-day-pills .nav-link {
    color: #495057;
    border-radius: 10px;
    padding: 15px 20px;
    transition: all 0.2s;
}
.custom-day-pills .nav-link:hover {
    background-color: #f8f9fa;
    color: #2e7d32;
}
.custom-day-pills .nav-link.active {
    background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
    color: white;
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2);
}
.custom-day-pills .nav-link.is-completed {
    background: linear-gradient(135deg, #81c784 0%, #a5d6a7 100%);
    color: #1b5e20 !important;
    box-shadow: 0 4px 10px rgba(129, 199, 132, 0.2);
}
.custom-day-pills .nav-link.is-completed:hover {
    background: linear-gradient(135deg, #66bb6a 0%, #81c784 100%);
    color: #1b5e20 !important;
}
.day-completed {
    opacity: 0.7;
    background-color: #f8f9fa;
}
.day-completed-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 0.8rem;
}
</style>

<div class="hero-smart-menu text-center">
    <div class="container" data-aos="fade-up">
        <h1 class="display-5 fw-bold text-dark mb-2">🥗 Thực Đơn <span class="text-success">Của Tôi</span></h1>
        <p class="text-muted">Theo dõi và đánh dấu tiến độ bữa ăn mỗi ngày.</p>
    </div>
</div>

<div class="container py-5" id="results">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-success mb-0">Tiến độ: <?= count($completedDays) ?>/<?= count($ketQua) ?> ngày</h4>
        <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn hủy thực đơn hiện tại không?');">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill"><i class="bi bi-trash me-1"></i>Hủy thực đơn này</button>
        </form>
    </div>

    <div class="row g-4">
        <!-- Sidebar Days -->
        <div class="col-md-3 mb-4 mb-md-0">
            <!-- Toggle Button for Mobile -->
            <button class="btn btn-outline-success w-100 d-md-none mb-3 shadow-sm rounded-pill fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#dayListCollapse" aria-expanded="false" aria-controls="dayListCollapse">
                <i class="bi bi-list-ul me-2"></i>Chọn Xem Ngày Khác
            </button>
            
            <div class="collapse d-md-block" id="dayListCollapse">
                <div class="nav flex-column flex-nowrap nav-pills custom-day-pills shadow-sm rounded-4 bg-white p-2" id="v-pills-tab" role="tablist" aria-orientation="vertical" style="max-height: 500px; overflow-y: auto; overflow-x: hidden;">
                    <?php foreach ($ketQua as $index => $ngay): ?>
                        <?php $isCompleted = in_array($ngay['ngay'], $completedDays); ?>
                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?> <?= $isCompleted ? 'is-completed' : '' ?> fw-bold mb-2 text-start position-relative" id="v-pills-day<?= $ngay['ngay'] ?>-tab" data-bs-toggle="pill" data-bs-target="#v-pills-day<?= $ngay['ngay'] ?>" type="button" role="tab" aria-controls="v-pills-day<?= $ngay['ngay'] ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                            <i class="bi <?= $isCompleted ? 'bi-check-circle-fill' : 'bi-calendar-event' ?> me-2"></i>Ngày <?= $ngay['ngay'] ?>
                            <?php if ($isCompleted): ?>
                                <span class="badge bg-success text-white ms-auto float-end">Xong</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Content Days -->
        <div class="col-md-9">
            <div class="tab-content" id="v-pills-tabContent">
                <?php foreach ($ketQua as $index => $ngay): ?>
                    <?php $isCompleted = in_array($ngay['ngay'], $completedDays); ?>
                    <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="v-pills-day<?= $ngay['ngay'] ?>" role="tabpanel" aria-labelledby="v-pills-day<?= $ngay['ngay'] ?>-tab" tabindex="0">
                        <div class="card result-card p-4 h-100 border-0 shadow-sm rounded-4 bg-white <?= $isCompleted ? 'day-completed' : '' ?> position-relative">
                            <div class="day-header d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom">
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-2 mb-sm-0">
                                    <h4 class="mb-0 fw-bold <?= $isCompleted ? 'text-secondary' : 'text-success' ?>"><i class="bi bi-calendar-check me-2"></i>Thực đơn Ngày <?= $ngay['ngay'] ?></h4>
                                    <?php if ($isCompleted): ?>
                                        <span class="badge bg-success fs-6 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Đã Hoàn Thành</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 mt-sm-0">
                                    <span class="badge bg-warning text-dark fs-6 rounded-pill me-2"><i class="bi bi-fire me-1"></i> <?= $ngay['tong_calo'] ?> kcal</span>
                                    <span class="badge bg-info text-dark fs-6 rounded-pill"><i class="bi bi-egg-fried me-1"></i> <?= $ngay['tong_protein'] ?>g Pro</span>
                                </div>
                            </div>
                            
                            <div class="meals-list">
                                <?php foreach ($ngay['buoi'] as $bua): ?>
                                    <div class="meal-item d-flex align-items-start mb-3 p-3 rounded-3 border-bottom border-light">
                                        <div class="meal-icon bg-light text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                            <i class="bi <?= match($bua['ten_bua']) { 'Sáng' => 'bi-sunrise', 'Trưa' => 'bi-sun', 'Tối' => 'bi-moon-stars', default => 'bi-cup-hot' } ?> fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="meal-name mb-0"><?= htmlspecialchars($bua['ten_bua']) ?></h6>
                                                <small class="text-muted fw-bold"><?= $bua['calo'] ?> kcal</small>
                                            </div>
                                            <p class="mb-0 text-dark"><?= htmlspecialchars($bua['mon']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-4 pt-3 border-top text-end">
                                <?php if (!$isCompleted): ?>
                                    <button class="btn btn-success rounded-pill px-4 fw-bold complete-day-btn" data-day="<?= $ngay['ngay'] ?>" data-menuid="<?= $activeMenu['id'] ?>">
                                        <i class="bi bi-check2-circle me-2"></i>Đánh Dấu Hoàn Thành Ngày <?= $ngay['ngay'] ?>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary rounded-pill px-4 fw-bold" disabled>
                                        <i class="bi bi-check-circle-fill me-2"></i>Ngày Này Đã Hoàn Thành
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Auto-close sidebar on mobile when a day is selected
    const dayTabs = document.querySelectorAll('#dayListCollapse .nav-link');
    const dayListCollapse = document.getElementById('dayListCollapse');
    if(dayListCollapse) {
        dayTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                if(window.innerWidth < 768) {
                    const bsCollapse = bootstrap.Collapse.getInstance(dayListCollapse);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                }
            });
        });
    }

    // Complete Day Logic
    const completeBtns = document.querySelectorAll('.complete-day-btn');
    completeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const day = this.getAttribute('data-day');
            const menuId = this.getAttribute('data-menuid');
            
            const originalHtml = this.innerHTML;
            this.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang xử lý...`;
            this.disabled = true;

            fetch('<?= BASE_URL ?>/api/complete_smart_menu_day.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ day: day, menu_id: menuId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Reload the page to show the updated progress
                    window.location.reload();
                } else {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                    alert('Có lỗi xảy ra: ' + data.message);
                }
            })
            .catch(err => {
                this.disabled = false;
                this.innerHTML = originalHtml;
                console.error(err);
                alert('Lỗi kết nối. Vui lòng thử lại sau.');
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
