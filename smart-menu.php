<?php
// smart-menu.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/FoodModel.php';
require_once __DIR__ . '/includes/smart-menu-functions.php';

$page_title = "Gợi Ý Thực Đơn Thông Minh AI";
require_once __DIR__ . '/includes/header.php';

$foodModel = new FoodModel();
$che_do = $_GET['che_do'] ?? '';
$ketQua = null;
$loaiKetQua = null;

$hasActiveMenu = false;
if (isset($_SESSION['user_id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id FROM user_smart_menus WHERE user_id = :user_id AND status = 'active' LIMIT 1");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $hasActiveMenu = true;
    }
}

if (isset($_GET['che_do'])) {
    switch ($che_do) {
        case 'theo_mua':
            $mua = $_GET['mua'] ?? '';
            $ketQua = $foodModel->getFoodsBySeason($mua);
            $loaiKetQua = 'thu_vien';
            break;

        case 'theo_muc_tieu':
            $mucTieu = $_GET['muc_tieu'] ?? '';
            $soNgay = (int) ($_GET['so_ngay'] ?? 3);
            $soNgay = max(1, min(30, $soNgay));
            $soBua = (int) ($_GET['so_bua'] ?? 4);
            $soBua = max(1, min(6, $soBua));
            $ketQua = taoThucDonTheoMucTieu($mucTieu, $soNgay, $soBua);
            $loaiKetQua = 'ai_thuc_don';
            break;

        case 'theo_nguyen_lieu':
            $nguyenLieuTho = $_GET['nguyen_lieu'] ?? '';
            $dsNguyenLieu = array_filter(array_map('trim', explode(',', $nguyenLieuTho)));
            $tatCaMon = $foodModel->getAllActiveFoods();
            $ketQua = locMonAnTheoNguyenLieu($tatCaMon, $dsNguyenLieu);
            $loaiKetQua = 'thu_vien';
            break;
    }
}
?>

<style>
/* Premium Smart Menu Styles */
.hero-smart-menu {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    padding: 60px 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}
.hero-smart-menu::before {
    content: '';
    position: absolute;
    top: -50%; left: -50%; width: 200%; height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 70%);
    pointer-events: none;
}
.smart-menu-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    backdrop-filter: blur(10px);
    background: rgba(255,255,255,0.95);
    overflow: hidden;
}
.nav-pills-custom .nav-link {
    color: #555;
    background-color: transparent;
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-bottom: 10px;
}
.nav-pills-custom .nav-link:hover {
    background-color: #f1f8e9;
}
.nav-pills-custom .nav-link.active {
    color: #fff;
    background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
}
.form-control-custom {
    border-radius: 10px;
    padding: 12px 15px;
    border: 1px solid #e0e0e0;
    transition: all 0.3s;
}
.form-control-custom:focus {
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
    border-color: #43a047;
}
.btn-generate {
    background: linear-gradient(135deg, #ff8f00 0%, #ff6f00 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px 30px;
    font-size: 1.1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(255, 143, 0, 0.4);
}
.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 143, 0, 0.5);
    color: white;
}
.result-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
}
.result-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
}
.result-tag {
    display: inline-block;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-right: 8px;
    margin-bottom: 8px;
}
.day-header {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px 20px;
}
.meal-item {
    transition: background-color 0.2s;
}
.meal-item:hover {
    background-color: #f8f9fa;
}
.meal-name {
    font-weight: 700;
    color: #2e7d32;
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
.border-dashed {
    border-style: dashed !important;
}
</style>

<div class="hero-smart-menu text-center">
    <div class="container" data-aos="fade-up">
        <h1 class="display-4 fw-bold text-dark mb-3">🍽️ Gợi Ý Thực Đơn <span class="text-success">Thông Minh AI</span></h1>
        <p class="lead text-muted mb-0">Thiết kế bữa ăn hoàn hảo dựa trên nhu cầu, sở thích và mục tiêu sức khỏe của bạn.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if ($hasActiveMenu): ?>
                <div class="alert alert-info shadow-sm rounded-4 mb-4 d-flex flex-column flex-md-row align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill fs-3 me-md-3 mb-2 mb-md-0 text-info"></i>
                    <div class="text-center text-md-start mb-3 mb-md-0">
                        <strong>Bạn đang có một thực đơn đang áp dụng!</strong>
                        <br>Bạn có thể tiếp tục theo dõi tiến độ, hoặc nếu bạn bấm "Lưu Thực Đơn Này" ở dưới, thực đơn cũ sẽ được thay thế bằng thực đơn mới.
                    </div>
                    <a href="<?= BASE_URL ?>/my-smart-menu.php" class="btn btn-outline-info ms-md-auto fw-bold text-nowrap">Xem Thực Đơn Của Tôi</a>
                </div>
            <?php endif; ?>
            <div class="card smart-menu-card p-4 p-md-5 mb-5" data-aos="fade-up" data-aos-delay="100">
                
                <ul class="nav nav-pills nav-pills-custom justify-content-center mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= ($che_do === 'theo_mua' || empty($che_do)) ? 'active' : '' ?>" id="pills-mua-tab" data-bs-toggle="pill" data-bs-target="#pills-mua" type="button" role="tab" onclick="setCheDo('theo_mua')">
                            <i class="bi bi-cloud-sun me-2"></i>Theo Mùa
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= ($che_do === 'theo_muc_tieu') ? 'active' : '' ?>" id="pills-muctieu-tab" data-bs-toggle="pill" data-bs-target="#pills-muctieu" type="button" role="tab" onclick="setCheDo('theo_muc_tieu')">
                            <i class="bi bi-bullseye me-2"></i>Theo Mục Tiêu
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= ($che_do === 'theo_nguyen_lieu') ? 'active' : '' ?>" id="pills-nguyenlieu-tab" data-bs-toggle="pill" data-bs-target="#pills-nguyenlieu" type="button" role="tab" onclick="setCheDo('theo_nguyen_lieu')">
                            <i class="bi bi-basket me-2"></i>Từ Tủ Lạnh
                        </button>
                    </li>
                </ul>

                <form method="get" id="form-thuc-don" action="smart-menu.php#results">
                    <input type="hidden" name="che_do" id="input_che_do" value="<?= htmlspecialchars($che_do ?: 'theo_mua') ?>">
                    
                    <div class="tab-content" id="pills-tabContent">
                        <!-- Theo Mùa -->
                        <div class="tab-pane fade <?= ($che_do === 'theo_mua' || empty($che_do)) ? 'show active' : '' ?>" id="pills-mua" role="tabpanel">
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Chọn thời tiết / mùa hiện tại:</label>
                                        <select name="mua" class="form-select form-control-custom form-select-lg">
                                            <option value="xuan" <?= (isset($_GET['mua']) && $_GET['mua'] === 'xuan') ? 'selected' : '' ?>>🌸 Mùa Xuân (Ấm áp, tươi mới)</option>
                                            <option value="he" <?= (isset($_GET['mua']) && $_GET['mua'] === 'he') ? 'selected' : '' ?>>☀️ Mùa Hè (Nóng bức, cần thanh mát)</option>
                                            <option value="thu" <?= (isset($_GET['mua']) && $_GET['mua'] === 'thu') ? 'selected' : '' ?>>🍂 Mùa Thu (Mát mẻ, dễ chịu)</option>
                                            <option value="dong" <?= (isset($_GET['mua']) && $_GET['mua'] === 'dong') ? 'selected' : '' ?>>❄️ Mùa Đông (Lạnh giá, cần ấm bụng)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Theo Mục Tiêu -->
                        <div class="tab-pane fade <?= ($che_do === 'theo_muc_tieu') ? 'show active' : '' ?>" id="pills-muctieu" role="tabpanel">
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <label class="form-label fw-bold">Mục tiêu sức khỏe của bạn:</label>
                                    <select name="muc_tieu" id="muc_tieu_select" class="form-select form-control-custom form-select-lg" onchange="hienThiMoTaMucTieu()">
                                        <?php foreach (danhSachMucTieu() as $ma => $mt): ?>
                                            <option value="<?= htmlspecialchars($ma) ?>" data-mota="<?= htmlspecialchars($mt['mo_ta']) ?>" <?= (isset($_GET['muc_tieu']) && $_GET['muc_tieu'] === $ma) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($mt['nhan']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="mt-2 text-muted small" id="mo_ta_muc_tieu"><i class="bi bi-info-circle me-1"></i> <span></span></div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">Số ngày áp dụng:</label>
                                    <div class="input-group">
                                        <input type="number" name="so_ngay" class="form-control form-control-custom" min="1" max="30" value="<?= (int) ($_GET['so_ngay'] ?? 3) ?>">
                                        <span class="input-group-text bg-white border-start-0">ngày</span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">Số bữa ăn mỗi ngày:</label>
                                    <select name="so_bua" class="form-select form-control-custom">
                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <option value="<?= $i ?>" <?= ((int) ($_GET['so_bua'] ?? 4) === $i) ? 'selected' : '' ?>>
                                                <?= $i ?> bữa / ngày
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Theo Nguyên Liệu -->
                        <div class="tab-pane fade <?= ($che_do === 'theo_nguyen_lieu') ? 'show active' : '' ?>" id="pills-nguyenlieu" role="tabpanel">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Nhập nguyên liệu bạn đang có (cách nhau bởi dấu phẩy):</label>
                                        <input type="text" name="nguyen_lieu" class="form-control form-control-custom form-control-lg" placeholder="VD: thịt heo, cà chua, trứng, rau muống..." value="<?= htmlspecialchars($_GET['nguyen_lieu'] ?? '') ?>">
                                        <div class="form-text text-muted mt-2"><i class="bi bi-lightbulb text-warning"></i> Mẹo: Nhập càng chi tiết, kết quả gợi ý càng chính xác.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-generate px-5 py-3">
                            <i class="bi bi-magic me-2"></i> Lên Thực Đơn Ngay
                        </button>
                    </div>
                </form>
            </div>

            <!-- RESULTS SECTION -->
            <?php if ($ketQua !== null): ?>
            <div id="results" class="mt-5 pt-3" data-aos="fade-up">
                <div class="text-center mb-5">
                    <h2 class="fw-bold">
                        <?= $loaiKetQua === 'ai_thuc_don' ? '<i class="bi bi-robot text-primary me-2"></i>Thực Đơn AI Gợi Ý' : '<i class="bi bi-journal-check text-success me-2"></i>Kết Quả Phù Hợp' ?>
                    </h2>
                    <div style="width: 60px; height: 4px; background: #2e7d32; margin: 15px auto; border-radius: 2px;"></div>
                    
                    <?php if ($loaiKetQua === 'ai_thuc_don'): ?>
                        <p class="text-muted"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> <i>Chỉ số Calo & Protein là ước tính trung bình tham khảo cho từng bữa.</i></p>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="mt-4">
                                <button type="button" id="btn-save-menu" class="btn btn-success btn-lg rounded-pill fw-bold shadow px-5" onclick="saveSmartMenu()">
                                    <i class="bi bi-save me-2"></i>Lưu Thực Đơn Này
                                </button>
                            </div>
                            <script>
                                function saveSmartMenu() {
                                    const btn = document.getElementById('btn-save-menu');
                                    const originalHtml = btn.innerHTML;
                                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';
                                    btn.disabled = true;
                                    
                                    const menuData = <?= json_encode($ketQua) ?>;
                                    fetch('<?= BASE_URL ?>/api/save_smart_menu.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/json'},
                                        body: JSON.stringify({ menu_data: menuData })
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data.status === 'success') {
                                            window.location.href = '<?= BASE_URL ?>/my-smart-menu.php';
                                        } else {
                                            alert('Lỗi: ' + data.message);
                                            btn.innerHTML = originalHtml;
                                            btn.disabled = false;
                                        }
                                    }).catch(err => {
                                        alert('Lỗi kết nối!');
                                        btn.innerHTML = originalHtml;
                                        btn.disabled = false;
                                    });
                                }
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (empty($ketQua)): ?>
                    <div class="alert alert-warning text-center p-4 rounded-4 shadow-sm border-0">
                        <i class="bi bi-search fs-1 d-block mb-3 text-warning"></i>
                        <h4 class="alert-heading fw-bold">Không tìm thấy món ăn!</h4>
                        <p class="mb-0">Vui lòng thử thay đổi các tùy chọn hoặc cung cấp thêm nguyên liệu khác.</p>
                    </div>

                <?php elseif ($loaiKetQua === 'thu_vien'): ?>
                    <div class="row g-4">
                        <?php foreach ($ketQua as $mon): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card result-card h-100">
                                    <?php if (!empty($mon['image']) && file_exists(__DIR__ . '/uploads/foods/' . $mon['image'])): ?>
                                        <img src="<?= BASE_URL . '/uploads/foods/' . $mon['image'] ?>" class="card-img-top" alt="<?= htmlspecialchars($mon['name']) ?>" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="bi bi-image text-muted fs-1"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h5 class="card-title fw-bold text-dark mb-3">
                                            <a href="<?= BASE_URL ?>/food-detail.php?id=<?= $mon['id'] ?>" class="text-decoration-none text-dark stretched-link">
                                                <?= htmlspecialchars($mon['name']) ?>
                                            </a>
                                        </h5>
                                        
                                        <?php if (isset($mon['do_phu_hop'])): ?>
                                            <div class="mb-3">
                                                <div class="progress" style="height: 8px;">
                                                  <div class="progress-bar bg-success" role="progressbar" style="width: <?= $mon['do_phu_hop'] ?>%;" aria-valuenow="<?= $mon['do_phu_hop'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <small class="text-success fw-bold d-block mt-1"><?= $mon['do_phu_hop'] ?>% nguyên liệu khớp</small>
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <span class="result-tag"><i class="bi bi-fire me-1"></i> <?= $mon['calories'] ?> kcal</span>
                                            <span class="result-tag"><i class="bi bi-egg-fried me-1"></i> <?= $mon['protein'] ?>g Pro</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($loaiKetQua === 'ai_thuc_don'): ?>
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
                                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?> fw-bold mb-2 text-start" id="v-pills-day<?= $ngay['ngay'] ?>-tab" data-bs-toggle="pill" data-bs-target="#v-pills-day<?= $ngay['ngay'] ?>" type="button" role="tab" aria-controls="v-pills-day<?= $ngay['ngay'] ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                                            <i class="bi bi-calendar-event me-2"></i>Ngày <?= $ngay['ngay'] ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Content Days -->
                        <div class="col-md-9">
                            <div class="tab-content" id="v-pills-tabContent">
                                <?php foreach ($ketQua as $index => $ngay): ?>
                                    <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="v-pills-day<?= $ngay['ngay'] ?>" role="tabpanel" aria-labelledby="v-pills-day<?= $ngay['ngay'] ?>-tab" tabindex="0">
                                        <div class="card result-card p-4 h-100 border-0 shadow-sm rounded-4 bg-white">
                                            <div class="day-header d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom">
                                                <h4 class="mb-0 fw-bold text-success"><i class="bi bi-calendar-check me-2"></i>Thực đơn Ngày <?= $ngay['ngay'] ?></h4>
                                                <div class="mt-2 mt-sm-0">
                                                    <span class="badge bg-warning text-dark fs-6 rounded-pill me-2"><i class="bi bi-fire me-1"></i> <?= $ngay['tong_calo'] ?> kcal</span>
                                                    <span class="badge bg-info text-dark fs-6 rounded-pill"><i class="bi bi-egg-fried me-1"></i> <?= $ngay['tong_protein'] ?>g Protein</span>
                                                </div>
                                            </div>
                                            
                                            <div class="card-body p-0 ps-3 border-start border-3 border-success ms-2">
                                                <?php foreach ($ngay['buoi'] as $bua): ?>
                                                    <div class="meal-item py-3 px-3 border-bottom border-dashed border-light rounded-3 mb-2">
                                                        <div class="d-flex align-items-start">
                                                            <div class="flex-grow-1 pe-3">
                                                                <span class="meal-name d-block fs-5 mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= htmlspecialchars($bua['ten_bua']) ?></span>
                                                                <p class="mb-0 text-dark ms-4" style="font-size: 1.05rem;"><?= htmlspecialchars($bua['mon']) ?></p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-center bg-light rounded-3 p-2 border">
                                                                <div class="text-dark fw-bold"><?= $bua['calo'] ?> <small class="text-muted fw-normal">kcal</small></div>
                                                                <div class="text-primary fw-bold mt-1"><?= $bua['protein'] ?>g <small class="text-muted fw-normal">Pro</small></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div class="mt-4 p-3 bg-light rounded-3 text-muted small mb-4 border">
                                                <i class="bi bi-info-square me-2 text-primary"></i> <strong>Lưu ý:</strong> <?= htmlspecialchars($ngay['ghi_chu']) ?>
                                            </div>
                                            
                                            <div class="text-end mt-auto pt-3 border-top">
                                                <button class="btn btn-success btn-lg px-4 fw-bold shadow-sm complete-day-btn rounded-pill" data-day="<?= $ngay['ngay'] ?>">
                                                    <i class="bi bi-check2-all me-2"></i>Đánh dấu Hoàn thành Ngày <?= $ngay['ngay'] ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function setCheDo(val) {
    document.getElementById('input_che_do').value = val;
}

function hienThiMoTaMucTieu() {
    const select = document.getElementById('muc_tieu_select');
    const opt = select.options[select.selectedIndex];
    if(opt && document.getElementById('mo_ta_muc_tieu')) {
        document.getElementById('mo_ta_muc_tieu').querySelector('span').textContent = opt.dataset.mota;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    hienThiMoTaMucTieu();
    
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
            
            // UX feedback immediately
            const originalHtml = this.innerHTML;
            this.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang xử lý...`;
            this.disabled = true;

            fetch('<?= BASE_URL ?>/api/complete_smart_menu_day.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ day: day })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    this.classList.remove('btn-success');
                    this.classList.add('btn-secondary');
                    this.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i>Đã Hoàn Thành (Lưu thành công)`;
                    
                    // Show a quick success alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-4 shadow';
                    alertDiv.style.zIndex = 9999;
                    alertDiv.innerHTML = `
                        <strong>Tuyệt vời!</strong> Bạn đã hoàn thành xuất sắc ngày ${day}. Thông báo đã được gửi.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.body.appendChild(alertDiv);
                    
                    // Auto close alert after 4s
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alertDiv);
                        bsAlert.close();
                    }, 4000);

                } else {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                    if (data.message === 'unauthorized') {
                        alert('Bạn cần đăng nhập để lưu tiến độ và nhận thông báo!');
                    } else {
                        alert('Có lỗi xảy ra: ' + data.message);
                    }
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
