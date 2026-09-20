<?php
// mystery-box.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/models/FoodModel.php';

$page_title = "Ăn Gì Cũng Được - Mở Hộp Dinh Dưỡng Sức Khỏe";
$extra_css = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
';

require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'] ?? null;
$sessionId = session_id();
$today = date('Y-m-d');
$maxSpins = 5;

$db = new Database();
$conn = $db->getConnection();

// Đếm số lượt đã mở hôm nay
if ($userId) {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM mystery_box_history WHERE user_id = :uid AND spin_date = :today");
    $stmtCount->execute([':uid' => $userId, ':today' => $today]);
} else {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM mystery_box_history WHERE session_id = :sid AND spin_date = :today");
    $stmtCount->execute([':sid' => $sessionId, ':today' => $today]);
}
$spinsToday = (int)$stmtCount->fetchColumn();
$spinsLeft = max(0, $maxSpins - $spinsToday);

// Lấy 3 món ngẫu nhiên cho thẻ 1, 2, 4 trên hàng thẻ sân khấu
$previewFoods = $conn->query("SELECT id, name, image, calories, category_id FROM foods WHERE status = 'active' ORDER BY RAND() LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

// Lấy lịch sử mở hộp hôm nay
$historySql = "SELECT h.id, h.created_at, h.meal_type, h.health_goal,
                      f.id as food_id, f.name, f.image, f.calories, f.protein, f.carbs, f.fat,
                      c.name as category_name
               FROM mystery_box_history h
               JOIN foods f ON h.food_id = f.id
               LEFT JOIN food_categories c ON f.category_id = c.id
               WHERE (h.user_id = :uid OR (h.user_id IS NULL AND h.session_id = :sid))
                 AND h.spin_date = :today
               ORDER BY h.id DESC LIMIT 12";
$stmtHist = $conn->prepare($historySql);
$stmtHist->execute([
    ':uid' => $userId ?: 0,
    ':sid' => $sessionId,
    ':today' => $today
]);
$todayHistory = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* ==========================================================
   MYSTERY BOX ULTIMATE GLASSMORPHISM UI - HEALTH EMERALD THEME
   ========================================================== */
:root {
    --mb-primary: #10b981;
    --mb-primary-dark: #059669;
    --mb-primary-deep: #047857;
    --mb-accent: #34d399;
    --mb-gold: #f59e0b;
    --mb-surface-glass: rgba(255, 255, 255, 0.82);
    --mb-border-glass: rgba(255, 255, 255, 0.9);
    --mb-shadow: 0 20px 45px -12px rgba(16, 185, 129, 0.14), 0 0 0 1px rgba(16, 185, 129, 0.06);
}

.mystery-page-wrapper {
    position: relative;
    overflow-x: hidden;
    min-height: 95vh;
    padding-bottom: 5rem;
    background: radial-gradient(at 15% 15%, rgba(16, 185, 129, 0.1) 0px, transparent 55%),
                radial-gradient(at 85% 20%, rgba(52, 211, 153, 0.1) 0px, transparent 55%),
                radial-gradient(at 50% 90%, rgba(16, 185, 129, 0.06) 0px, transparent 65%),
                #f8fafc;
}

/* Ambient Floating Glow Orbs */
.ambient-glow-1 {
    position: absolute;
    top: 40px;
    left: -120px;
    width: 520px;
    height: 520px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    z-index: 0;
    filter: blur(40px);
}
.ambient-glow-2 {
    position: absolute;
    top: 280px;
    right: -140px;
    width: 560px;
    height: 560px;
    background: radial-gradient(circle, rgba(52, 211, 153, 0.16) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    z-index: 0;
    filter: blur(50px);
}

/* Glassmorphism Card System */
.glass-panel-luxury {
    background: var(--mb-surface-glass);
    backdrop-filter: blur(20px) saturate(190%);
    -webkit-backdrop-filter: blur(20px) saturate(190%);
    border: 1px solid var(--mb-border-glass);
    border-radius: 28px;
    box-shadow: var(--mb-shadow);
    position: relative;
    z-index: 2;
}

/* Header Badge & Title */
.header-badge {
    background: linear-gradient(135deg, rgba(209, 250, 229, 0.9) 0%, rgba(236, 253, 245, 0.95) 100%);
    border: 1px solid rgba(110, 231, 183, 0.8);
    color: #065f46;
    font-weight: 700;
    font-size: 0.85rem;
    padding: 0.55rem 1.25rem;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.12);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.main-title-gradient {
    font-weight: 900;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, #0f172a 30%, #047857 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.title-icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 16px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    font-size: 1.6rem;
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
    border: 1.5px solid rgba(255, 255, 255, 0.8);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    vertical-align: middle;
}
.title-icon-badge:hover {
    transform: rotate(15deg) scale(1.1);
    box-shadow: 0 12px 25px rgba(16, 185, 129, 0.45);
}

/* ==========================================================
   4-CARDS STAGE ROW (Matching Mockup with Luxury 3D Polish)
   ========================================================== */
.mystery-cards-row {
    display: flex;
    justify-content: center;
    align-items: stretch;
    gap: 1.25rem;
    margin: 2.5rem 0 3rem;
    perspective: 1200px;
    position: relative;
    z-index: 2;
}

/* Side Preview Cards (Món 1, 2, 4) */
.stage-card-side {
    width: 185px;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(209, 250, 229, 0.8);
    border-radius: 22px;
    padding: 0.9rem;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex;
    flex-direction: column;
}
.stage-card-side:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 16px 36px rgba(16, 185, 129, 0.14);
    border-color: rgba(52, 211, 153, 0.9);
}
.stage-card-badge {
    background: rgba(240, 253, 244, 0.9);
    border: 1px solid rgba(167, 243, 208, 0.8);
    color: #047857;
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: 9999px;
    padding: 0.25rem 0.65rem;
    margin-bottom: 0.65rem;
    display: inline-block;
}
.stage-card-img-wrap {
    width: 100%;
    height: 125px;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 0.65rem;
    background: #e2e8f0;
    position: relative;
}
.stage-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.stage-card-side:hover .stage-card-img {
    transform: scale(1.08);
}
.stage-card-title {
    font-size: 0.92rem;
    font-weight: 750;
    color: #1e293b;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stage-card-cal-badge {
    background: #fff1f2;
    color: #e11d48;
    border: 1px solid #ffe4e6;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 9999px;
    padding: 0.2rem 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: auto;
}

/* THE CENTERPIECE: Món 3 Bí Ẩn (Mystery Lucky Box) */
.stage-card-center {
    width: 220px;
    background: linear-gradient(150deg, rgba(236, 253, 245, 0.95) 0%, rgba(209, 250, 229, 0.85) 50%, rgba(255, 255, 255, 0.92) 100%);
    border: 2px solid #10b981;
    border-radius: 26px;
    padding: 1.1rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 16px 40px rgba(16, 185, 129, 0.25), 0 0 0 6px rgba(16, 185, 129, 0.08);
    position: relative;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    overflow: hidden;
}
.stage-card-center:hover {
    transform: translateY(-10px) scale(1.04);
    box-shadow: 0 22px 50px rgba(16, 185, 129, 0.35), 0 0 0 8px rgba(16, 185, 129, 0.14);
}
.center-glow-ribbon {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #10b981, #f59e0b, #10b981);
    background-size: 200% 100%;
    animation: rainbowRibbon 3s infinite linear;
}
@keyframes rainbowRibbon {
    0% { background-position: 0% 50%; }
    100% { background-position: 200% 50%; }
}

.center-mystery-badge {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border-radius: 9999px;
    padding: 0.35rem 0.85rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    margin-bottom: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.center-artwork-wrap {
    width: 140px;
    height: 140px;
    border-radius: 20px;
    margin: 0.5rem 0;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
}
.center-box-art {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 18px;
    filter: drop-shadow(0 10px 18px rgba(16, 185, 129, 0.35));
    animation: boxFloat 3s infinite ease-in-out;
}
@keyframes boxFloat {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-7px) rotate(1deg); }
}

.center-status-title {
    font-size: 1.05rem;
    font-weight: 850;
    color: #0f172a;
    line-height: 1.2;
    margin-bottom: 0.2rem;
}
.center-sub-hint {
    font-size: 0.78rem;
    font-weight: 600;
    color: #059669;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Card Spinning Animation when Rolling */
.card-spinning-3d {
    animation: spin3D 0.18s infinite linear !important;
}
@keyframes spin3D {
    0% { transform: rotateY(0deg) scale(0.96); }
    50% { transform: rotateY(90deg) scale(1.05); }
    100% { transform: rotateY(180deg) scale(0.96); }
}

/* ==========================================================
   FILTER CONTROLS & PILLS
   ========================================================== */
.filter-card-body {
    padding: 2.5rem;
}

/* Meal Pills */
.pill-meal-option {
    background: #ffffff;
    border: 1.5px solid #d1fae5;
    color: #334155;
    font-weight: 650;
    font-size: 0.95rem;
    border-radius: 9999px;
    padding: 0.65rem 1.45rem;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}
.pill-meal-option:hover {
    border-color: var(--mb-primary);
    color: var(--mb-primary-dark);
    background: #f0fdf4;
    transform: translateY(-2px);
}
.pill-meal-option.active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: #047857;
    color: #ffffff;
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.38);
    transform: translateY(-2px) scale(1.03);
}

/* Goal Chips */
.goal-chips-deck {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.65rem;
}
.goal-chip {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    color: #475569;
    font-weight: 600;
    font-size: 0.88rem;
    border-radius: 14px;
    padding: 0.55rem 1.15rem;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}
.goal-chip:hover {
    border-color: #6ee7b7;
    color: #047857;
    background: #f0fdf4;
    transform: translateY(-1px);
}
.goal-chip.active {
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95) 0%, rgba(209, 250, 229, 0.9) 100%);
    border-color: #10b981;
    color: #065f46;
    font-weight: 750;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.16);
    transform: translateY(-1px);
}

/* ==========================================================
   MAGNETIC "MỞ HỘP GỢI Ý" CTA BUTTON
   ========================================================== */
.btn-open-box-magnetic {
    background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
    border: none;
    font-size: 1.25rem;
    font-weight: 850;
    letter-spacing: 0.5px;
    color: #ffffff;
    border-radius: 9999px;
    padding: 1.1rem 3.5rem;
    box-shadow: 0 12px 30px rgba(16, 185, 129, 0.42), 0 0 0 6px rgba(16, 185, 129, 0.12);
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.btn-open-box-magnetic::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(60deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transform: rotate(30deg);
    animation: btnGleam 2.8s infinite linear;
}
@keyframes btnGleam {
    0% { transform: translateX(-100%) rotate(30deg); }
    100% { transform: translateX(100%) rotate(30deg); }
}
.btn-open-box-magnetic:hover:not(:disabled) {
    transform: translateY(-4px) scale(1.03);
    box-shadow: 0 18px 40px rgba(16, 185, 129, 0.55), 0 0 0 8px rgba(16, 185, 129, 0.18);
    background: linear-gradient(135deg, #34d399 0%, #059669 100%);
}
.btn-open-box-magnetic:active:not(:disabled) {
    transform: translateY(1px) scale(0.99);
}
.btn-open-box-magnetic:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    filter: grayscale(25%);
}

/* Remaining Spins Live Indicator */
.spins-pills-bar {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-left: 0.5rem;
}
.spin-indicator-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
    transition: all 0.3s ease;
}
.spin-indicator-dot.used {
    background: #cbd5e1;
    box-shadow: none;
}

/* ==========================================================
   PANORAMIC LIFESTYLE BANNER (From Mockup Image!)
   ========================================================== */
.lifestyle-banner-wrap {
    border-radius: 28px;
    overflow: hidden;
    position: relative;
    min-height: 180px;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #064e3b 0%, #065f46 60%, rgba(6, 95, 70, 0.6) 100%),
                url('<?php echo BASE_URL; ?>/img/banner_healthy_food.jpg') center/cover no-repeat;
    box-shadow: 0 16px 40px rgba(6, 78, 59, 0.25);
    margin: 3.5rem 0;
}
.lifestyle-banner-content {
    padding: 2.25rem 2.75rem;
    position: relative;
    z-index: 2;
    color: #ffffff;
    max-width: 650px;
}
.lifestyle-banner-pill {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.3rem 0.8rem;
    border-radius: 9999px;
    display: inline-block;
    margin-bottom: 0.65rem;
}
.lifestyle-banner-title {
    font-size: 1.85rem;
    font-weight: 900;
    line-height: 1.25;
    margin-bottom: 0.4rem;
    letter-spacing: -0.01em;
}
.lifestyle-banner-sub {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 0;
}
.ai-status-chip {
    position: absolute;
    right: 2rem;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    padding: 0.75rem 1.25rem;
    border-radius: 20px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 0.65rem;
    z-index: 2;
}

/* ==========================================================
   WINNING REVEAL MODAL GLASS
   ========================================================== */
.modal-glass-superb {
    background: rgba(255, 255, 255, 0.97);
    backdrop-filter: blur(28px);
    -webkit-backdrop-filter: blur(28px);
    border: 1px solid rgba(167, 243, 208, 0.9);
    border-radius: 32px;
    box-shadow: 0 30px 70px -15px rgba(6, 78, 59, 0.35);
    overflow: hidden;
}

.macro-box-sleek {
    background: rgba(248, 250, 252, 0.9);
    border: 1.5px solid #e2e8f0;
    border-radius: 16px;
    padding: 0.85rem 0.5rem;
    text-align: center;
    transition: all 0.2s ease;
}
.macro-box-sleek:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.04);
}
.macro-box-sleek.macro-cal { border-color: #fecdd3; background: #fff1f2; }
.macro-box-sleek.macro-pro { border-color: #bfdbfe; background: #eff6ff; }
.macro-box-sleek.macro-carb { border-color: #fde68a; background: #fffbeb; }
.macro-box-sleek.macro-fat { border-color: #e9d5ff; background: #faf5ff; }

.macro-box-val {
    font-size: 1.35rem;
    font-weight: 850;
    line-height: 1.1;
}
.macro-box-lbl {
    font-size: 0.72rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

/* History Card */
.history-food-tile {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    padding: 0.9rem;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    height: 100%;
    display: flex;
    flex-direction: column;
}
.history-food-tile:hover {
    transform: translateY(-5px);
    border-color: #6ee7b7;
    box-shadow: 0 12px 30px rgba(16, 185, 129, 0.15);
}
.history-food-img {
    width: 100%;
    height: 135px;
    object-fit: cover;
    border-radius: 14px;
    margin-bottom: 0.75rem;
}

@media (max-width: 992px) {
    .ai-status-chip {
        display: none;
    }
}
@media (max-width: 768px) {
    .mystery-cards-row {
        gap: 0.65rem;
        flex-wrap: wrap;
    }
    .stage-card-side {
        width: 47%;
        padding: 0.75rem;
    }
    .stage-card-center {
        width: 100%;
        order: -1;
        margin-bottom: 0.75rem;
    }
    .filter-card-body {
        padding: 1.5rem;
    }
    .btn-open-box-magnetic {
        width: 100%;
        padding: 1rem 1.5rem;
        font-size: 1.1rem;
    }
    .lifestyle-banner-content {
        padding: 1.5rem;
    }
    .lifestyle-banner-title {
        font-size: 1.35rem;
    }
}
</style>

<div class="mystery-page-wrapper">
    <!-- Ambient Lighting Elements -->
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="container py-4 position-relative" style="z-index: 2;">
        
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>" class="text-success text-decoration-none">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/smart-menu.php" class="text-success text-decoration-none">Thực đơn AI</a></li>
                <li class="breadcrumb-item active text-secondary" aria-current="page">Ăn Gì Hôm Nay</li>
            </ol>
        </nav>

        <!-- Main Hero Header -->
        <div class="text-center mb-4">
            <div class="header-badge rounded-pill shadow-sm mb-2">
                <i class="bi bi-stars text-warning fs-6"></i> ĂN GÌ CŨNG ĐƯỢC • TRỢ LÝ DINH DƯỠNG
            </div>
            <h1 class="display-5 mb-2 fw-bold d-flex align-items-center justify-content-center flex-wrap gap-3">
                <span class="title-icon-badge shadow-sm">
                    <i class="bi bi-dice-5-fill"></i>
                </span>
                <span class="main-title-gradient">MỞ HỘP DINH DƯỠNG SỨC KHỎE</span>
            </h1>
            <p class="text-muted mx-auto fs-5" style="max-width: 680px;">
                Giải phóng tâm trí khỏi câu hỏi muôn thuở <em>"Bữa nay ăn gì?"</em>. Chạm mở ngẫu nhiên món ăn cân đối calo, hợp chuẩn theo mục tiêu sức khỏe của bạn!
            </p>
        </div>

        <!-- ==========================================================
             4-CARDS STAGE ROW (Matching Mockup with Luxury 3D Polish)
             ========================================================== -->
        <div class="mystery-cards-row" id="mysteryCardsRow">
            <!-- Món 1 -->
            <div class="stage-card-side" id="stageCard1">
                <div>
                    <span class="stage-card-badge"><i class="bi bi-egg-fried me-1 text-success"></i>Món 1</span>
                </div>
                <div class="stage-card-img-wrap">
                    <img src="<?php echo food_image_url($previewFoods[0]['image'] ?? null); ?>" class="stage-card-img" alt="Món 1">
                </div>
                <div class="stage-card-title"><?php echo htmlspecialchars($previewFoods[0]['name'] ?? 'Phở bò Hà Nội'); ?></div>
                <div class="mt-auto">
                    <span class="stage-card-cal-badge">
                        <i class="bi bi-fire"></i> <?php echo floatval($previewFoods[0]['calories'] ?? 420); ?> kcal
                    </span>
                </div>
            </div>

            <!-- Món 2 -->
            <div class="stage-card-side" id="stageCard2">
                <div>
                    <span class="stage-card-badge"><i class="bi bi-cup-hot me-1 text-success"></i>Món 2</span>
                </div>
                <div class="stage-card-img-wrap">
                    <img src="<?php echo food_image_url($previewFoods[1]['image'] ?? null); ?>" class="stage-card-img" alt="Món 2">
                </div>
                <div class="stage-card-title"><?php echo htmlspecialchars($previewFoods[1]['name'] ?? 'Salad ức gà mè rang'); ?></div>
                <div class="mt-auto">
                    <span class="stage-card-cal-badge">
                        <i class="bi bi-fire"></i> <?php echo floatval($previewFoods[1]['calories'] ?? 320); ?> kcal
                    </span>
                </div>
            </div>

            <!-- Món 3: BÍ ẨN (Centerpiece Mystery Card) -->
            <div class="stage-card-center" id="centerMysteryBox" title="Nhấn để mở hộp ngẫu nhiên!">
                <div class="center-glow-ribbon"></div>
                <div>
                    <span class="center-mystery-badge">
                        <i class="bi bi-sparkles text-warning"></i> Món 3: Bí ẩn
                    </span>
                </div>
                <div class="center-artwork-wrap" id="centerArtworkWrap">
                    <img src="<?php echo BASE_URL; ?>/img/lucky_mystery_box.jpg" class="center-box-art" id="centerBoxImg" alt="Mystery Box">
                </div>
                <div class="mt-2">
                    <div class="center-status-title" id="centerStatusTitle">Hộp Dinh Dưỡng</div>
                    <div class="center-sub-hint" id="centerSubHint">
                        <i class="bi bi-stars"></i> Sắp được mở...
                    </div>
                </div>
            </div>

            <!-- Món 4 -->
            <div class="stage-card-side" id="stageCard4">
                <div>
                    <span class="stage-card-badge"><i class="bi bi-droplet me-1 text-success"></i>Món 4</span>
                </div>
                <div class="stage-card-img-wrap">
                    <img src="<?php echo food_image_url($previewFoods[2]['image'] ?? null); ?>" class="stage-card-img" alt="Món 4">
                </div>
                <div class="stage-card-title"><?php echo htmlspecialchars($previewFoods[2]['name'] ?? 'Sinh tố bơ chuối'); ?></div>
                <div class="mt-auto">
                    <span class="stage-card-cal-badge">
                        <i class="bi bi-fire"></i> <?php echo floatval($previewFoods[2]['calories'] ?? 260); ?> kcal
                    </span>
                </div>
            </div>
        </div>

        <!-- ==========================================================
             GLASS CONTROLS PANEL (Meal Types, Goal Chips & Spin CTA)
             ========================================================== -->
        <div class="glass-panel-luxury mb-5 mx-auto" style="max-width: 860px;">
            <div class="filter-card-body">
                
                <!-- 1. Bữa ăn (Pills) -->
                <div class="mb-4 text-center">
                    <div class="text-uppercase small fw-bold text-muted letter-spacing-1 mb-2">
                        <i class="bi bi-clock me-1 text-success"></i> 1. Bạn đang chuẩn bị cho bữa nào?
                    </div>
                    <div class="d-flex flex-wrap justify-content-center gap-2" id="mealPillsGroup">
                        <button type="button" class="btn pill-meal-option" data-meal="breakfast">
                            <i class="bi bi-sun me-1"></i> Bữa sáng
                        </button>
                        <button type="button" class="btn pill-meal-option active" data-meal="lunch">
                            <i class="bi bi-fire me-1"></i> Bữa trưa
                        </button>
                        <button type="button" class="btn pill-meal-option" data-meal="dinner">
                            <i class="bi bi-moon-stars me-1"></i> Bữa tối
                        </button>
                        <button type="button" class="btn pill-meal-option" data-meal="snack">
                            <i class="bi bi-cup-straw me-1"></i> Ăn vặt / Phụ
                        </button>
                    </div>
                </div>

                <!-- 2. Mục tiêu sức khỏe (Interactive Chips) -->
                <div class="mb-4 text-center">
                    <div class="text-uppercase small fw-bold text-muted letter-spacing-1 mb-2">
                        <i class="bi bi-bullseye me-1 text-success"></i> 2. Mục tiêu dinh dưỡng của bạn
                    </div>
                    <div class="goal-chips-deck" id="goalChipsDeck">
                        <div class="goal-chip active" data-goal="all"><i class="bi bi-compass me-1 text-success"></i> Tất cả mục tiêu</div>
                        <div class="goal-chip" data-goal="weight_loss"><i class="bi bi-arrow-down-right-circle me-1 text-danger"></i> Giảm mỡ &amp; Siết cân</div>
                        <div class="goal-chip" data-goal="muscle_gain"><i class="bi bi-lightning-charge-fill me-1 text-warning"></i> Tăng cơ (High Protein)</div>
                        <div class="goal-chip" data-goal="maintenance"><i class="bi bi-shield-check me-1 text-info"></i> Giữ dáng &amp; Cân bằng</div>
                        <div class="goal-chip" data-goal="weight_gain"><i class="bi bi-arrow-up-right-circle me-1 text-primary"></i> Tăng cân an toàn</div>
                        <div class="goal-chip" data-goal="eat_clean"><i class="bi bi-leaf-fill me-1 text-success"></i> Eat Clean &amp; Lành mạnh</div>
                    </div>
                </div>

                <!-- 3. Spin Action Button & Real-time Live Counters -->
                <div class="text-center pt-2">
                    <button type="button" class="btn btn-open-box-magnetic shadow-lg" id="btnSpin">
                        <i class="bi bi-gift-fill me-2 fs-5"></i> MỞ HỘP GỢI Ý
                    </button>

                    <!-- Lượt mở còn lại -->
                    <div class="mt-3 d-flex align-items-center justify-content-center">
                        <span class="text-muted small fw-semibold">
                            Lượt mở miễn phí hôm nay:
                            <strong class="text-success ms-1 fs-6" id="spinsLeftNum"><?php echo $spinsLeft; ?></strong>/<?php echo $maxSpins; ?>
                        </span>
                        <div class="spins-pills-bar" id="spinsPillsBar">
                            <?php for ($i = 1; $i <= $maxSpins; $i++): ?>
                                <span class="spin-indicator-dot <?php echo ($i > $spinsLeft) ? 'used' : ''; ?>"></span>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="spinNotice" class="small mt-2" style="min-height: 22px;"></div>
                </div>

            </div>
        </div>

        <!-- ==========================================================
             PANORAMIC LIFESTYLE BANNER (From Mockup Image!)
             ========================================================== -->
        <div class="lifestyle-banner-wrap">
            <div class="lifestyle-banner-content">
                <span class="lifestyle-banner-pill">
                    <i class="bi bi-shield-check me-1"></i> Trợ lý Dinh Dưỡng Thông Minh
                </span>
                <h2 class="lifestyle-banner-title">
                    Kiểm soát Dinh dưỡng,<br>Tận hưởng Cuộc sống
                </h2>
                <p class="lifestyle-banner-sub">
                    Chế độ ăn khoa học giúp cơ thể nhẹ nhàng, tràn đầy năng lượng và duy trì vóc dáng lý tưởng mỗi ngày.
                </p>
            </div>

            <div class="ai-status-chip">
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bi bi-robot fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark small" style="line-height: 1.1;">AI Dinh Dưỡng</div>
                    <div class="small text-success fw-semibold" style="font-size: 0.72rem;">● Đang trực tuyến</div>
                </div>
            </div>
        </div>

        <!-- ==========================================================
             TODAY'S SPIN HISTORY SECTION
             ========================================================== -->
        <div class="mt-5" id="historySection">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold text-dark mb-0">
                    <i class="bi bi-clock-history text-success me-2"></i>Món bạn đã mở hôm nay
                </h4>
                <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill small shadow-sm">
                    Đã lưu <span id="historyCount" class="text-success fw-bold"><?php echo count($todayHistory); ?></span> món
                </span>
            </div>

            <div class="row g-3" id="historyGrid">
                <?php if (empty($todayHistory)): ?>
                    <div class="col-12" id="emptyHistoryNotice">
                        <div class="glass-panel-luxury text-center py-5">
                            <i class="bi bi-inbox text-success opacity-50 display-4 d-block mb-3"></i>
                            <h5 class="fw-bold text-dark mb-1">Chưa có món nào được mở hôm nay</h5>
                            <p class="text-muted small mb-0">Hãy bấm nút <strong>"MỞ HỘP GỢI Ý"</strong> ở trên để bắt đầu khám phá thực đơn hấp dẫn!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayHistory as $hist): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="history-food-tile">
                                <img src="<?php echo food_image_url($hist['image']); ?>" class="history-food-img" alt="<?php echo htmlspecialchars($hist['name']); ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-success-subtle text-success rounded-pill small" style="font-size: 0.7rem;">
                                        <?php echo htmlspecialchars($hist['category_name'] ?? 'Món ngon'); ?>
                                    </span>
                                    <span class="small text-muted" style="font-size: 0.72rem;">
                                        <i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($hist['created_at'])); ?>
                                    </span>
                                </div>
                                <h6 class="fw-bold text-dark text-truncate mb-2" title="<?php echo htmlspecialchars($hist['name']); ?>">
                                    <?php echo htmlspecialchars($hist['name']); ?>
                                </h6>
                                <div class="text-muted small mb-3">
                                    <span class="badge bg-danger-subtle text-danger rounded-pill fw-bold">
                                        <i class="bi bi-fire"></i> <?php echo floatval($hist['calories']); ?> kcal
                                    </span>
                                </div>
                                <div class="mt-auto d-flex gap-1">
                                    <a href="<?php echo BASE_URL; ?>/food-detail.php?id=<?php echo $hist['food_id']; ?>" class="btn btn-sm btn-outline-success w-50 rounded-pill fw-semibold" target="_blank" title="Xem công thức">
                                        <i class="bi bi-book me-1"></i>Xem
                                    </a>
                                    <button type="button" class="btn btn-sm btn-success w-50 rounded-pill fw-semibold btn-quick-add" data-food-id="<?php echo $hist['food_id']; ?>" data-meal-type="<?php echo htmlspecialchars($hist['meal_type']); ?>" title="Thêm vào nhật ký">
                                        <i class="bi bi-plus"></i>Nhật ký
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ==========================================================
     WINNING DISH RESULT MODAL (Glassmorphism Reveal Popup)
     ========================================================== -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-glass-superb border-0">
            <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold fs-6">
                    <i class="bi bi-check-circle-fill me-1 text-success"></i> KẾT QUẢ MỞ HỘP THÀNH CÔNG
                </span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-2">
                <div class="row g-4 align-items-center">
                    <!-- Ảnh món ăn -->
                    <div class="col-md-5 text-center">
                        <div class="position-relative">
                            <img id="winnerImage" src="" alt="" class="img-fluid rounded-4 shadow-sm w-100" style="max-height: 280px; object-fit: cover;">
                        </div>
                    </div>

                    <!-- Thông tin dinh dưỡng & AI Reasoning -->
                    <div class="col-md-7">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span id="winnerCategory" class="badge bg-success-subtle text-success rounded-pill px-3 py-1 fw-semibold small">Món Cơm</span>
                            <span id="winnerServing" class="text-muted small">1 phần</span>
                        </div>

                        <h3 id="winnerName" class="fw-bold text-dark mb-2">Tên món ăn</h3>
                        <p id="winnerDesc" class="text-muted small mb-3">Mô tả ngắn gọn về món ăn</p>

                        <!-- Macro Badges Grid (Pastel & Sleek) -->
                        <div class="row g-2 mb-3 text-center">
                            <div class="col-3">
                                <div class="macro-box-sleek macro-cal">
                                    <div class="macro-box-val text-danger" id="winnerCalories">0</div>
                                    <div class="macro-box-lbl text-danger">Kcal</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="macro-box-sleek macro-pro">
                                    <div class="macro-box-val text-primary" id="winnerProtein">0g</div>
                                    <div class="macro-box-lbl text-primary">Protein</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="macro-box-sleek macro-carb">
                                    <div class="macro-box-val text-warning" id="winnerCarbs">0g</div>
                                    <div class="macro-box-lbl text-warning">Carbs</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="macro-box-sleek macro-fat">
                                    <div class="macro-box-val" style="color: #9333ea;" id="winnerFat">0g</div>
                                    <div class="macro-box-lbl" style="color: #9333ea;">Fat</div>
                                </div>
                            </div>
                        </div>

                        <!-- AI Reasoning Box -->
                        <div class="p-3 rounded-4 mb-4" style="background: linear-gradient(135deg, rgba(236, 253, 245, 0.95) 0%, rgba(209, 250, 229, 0.7) 100%); border: 1px solid #a7f3d0;">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-robot text-success fs-5 flex-shrink-0 mt-1"></i>
                                <div>
                                    <div class="fw-bold text-success small text-uppercase">Đánh giá dinh dưỡng AI:</div>
                                    <div class="text-dark small" id="winnerAiReasoning" style="line-height: 1.55;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-success rounded-pill px-4 py-2 fw-semibold shadow-sm flex-grow-1" id="btnAddToLog">
                                <i class="bi bi-journal-plus me-1"></i> Thêm vào nhật ký
                            </button>
                            <a href="#" target="_blank" class="btn btn-outline-success rounded-pill px-3 py-2 fw-semibold" id="btnViewRecipe">
                                <i class="bi bi-book me-1"></i> Xem công thức
                            </a>
                            <button type="button" class="btn btn-light rounded-pill px-3 py-2 fw-semibold border" id="btnSpinAgain">
                                <i class="bi bi-arrow-repeat me-1"></i> Mở lại
                            </button>
                        </div>
                        <div id="modalActionNotice" class="small mt-2" style="min-height: 22px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
     CLIENT JAVASCRIPT LOGIC
     ========================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentSelectedMeal = 'lunch';
    let currentSelectedGoal = 'all';
    let currentWinner = null;
    let isSpinning = false;
    let spinsLeft = <?php echo (int)$spinsLeft; ?>;
    const maxSpins = <?php echo (int)$maxSpins; ?>;

    const btnSpin = document.getElementById('btnSpin');
    const centerMysteryBox = document.getElementById('centerMysteryBox');
    const centerArtworkWrap = document.getElementById('centerArtworkWrap');
    const centerBoxImg = document.getElementById('centerBoxImg');
    const centerStatusTitle = document.getElementById('centerStatusTitle');
    const centerSubHint = document.getElementById('centerSubHint');
    const spinsLeftNum = document.getElementById('spinsLeftNum');
    const spinsPillsBar = document.getElementById('spinsPillsBar');
    const spinNotice = document.getElementById('spinNotice');

    const resultModalEl = document.getElementById('resultModal');
    const resultModal = new bootstrap.Modal(resultModalEl);

    const winnerImage = document.getElementById('winnerImage');
    const winnerName = document.getElementById('winnerName');
    const winnerCategory = document.getElementById('winnerCategory');
    const winnerServing = document.getElementById('winnerServing');
    const winnerDesc = document.getElementById('winnerDesc');
    const winnerCalories = document.getElementById('winnerCalories');
    const winnerProtein = document.getElementById('winnerProtein');
    const winnerCarbs = document.getElementById('winnerCarbs');
    const winnerFat = document.getElementById('winnerFat');
    const winnerAiReasoning = document.getElementById('winnerAiReasoning');
    const btnAddToLog = document.getElementById('btnAddToLog');
    const btnViewRecipe = document.getElementById('btnViewRecipe');
    const btnSpinAgain = document.getElementById('btnSpinAgain');
    const modalActionNotice = document.getElementById('modalActionNotice');

    // 1. Xử lý click chọn bữa ăn
    const mealPills = document.querySelectorAll('.pill-meal-option');
    mealPills.forEach(pill => {
        pill.addEventListener('click', function() {
            mealPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentSelectedMeal = this.getAttribute('data-meal');
        });
    });

    // 2. Xử lý click chọn chip mục tiêu sức khỏe
    const goalChips = document.querySelectorAll('.goal-chip');
    goalChips.forEach(chip => {
        chip.addEventListener('click', function() {
            goalChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            currentSelectedGoal = this.getAttribute('data-goal');
        });
    });

    // 3. Click vào thẻ trung tâm cũng kích hoạt quay
    centerMysteryBox.addEventListener('click', function() {
        if (!isSpinning && spinsLeft > 0) {
            btnSpin.click();
        }
    });

    // 4. Hàm kích hoạt mở hộp
    btnSpin.addEventListener('click', function() {
        if (isSpinning) return;

        if (spinsLeft <= 0) {
            spinNotice.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Bạn đã dùng hết 5 lượt mở hộp miễn phí hôm nay! Vui lòng quay lại ngày mai nhé.</span>';
            return;
        }

        isSpinning = true;
        btnSpin.disabled = true;
        btnSpin.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ĐANG MỞ HỘP...';
        spinNotice.innerHTML = '';

        // Hiệu ứng xoay 3D lốc xoáy trên thẻ trung tâm
        centerMysteryBox.classList.add('card-spinning-3d');
        centerStatusTitle.innerText = 'Đang chọn món...';
        centerSubHint.innerHTML = '<i class="bi bi-hourglass-split"></i> AI đang phân tích';

        // Gọi API
        fetch('<?php echo BASE_URL; ?>/api/spin_mystery_box.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                meal_type: currentSelectedMeal,
                health_goal: currentSelectedGoal
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                stopSpinning();
                spinNotice.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>${data.message || 'Đã có lỗi xảy ra.'}</span>`;
                return;
            }

            const candidates = data.candidates || [];
            const winner = data.winner;
            currentWinner = winner;

            // Cập nhật số lượt còn lại & cập nhật thanh dots
            spinsLeft = data.spins_left;
            spinsLeftNum.innerText = spinsLeft;
            updateSpinsDots(spinsLeft);

            // Guồng quay ảnh và tên món chạy tốc độ cao
            let candidateIndex = 0;
            const shuffleInterval = setInterval(() => {
                if (candidates.length > 0) {
                    const c = candidates[candidateIndex % candidates.length];
                    centerStatusTitle.innerText = c.name;
                    centerSubHint.innerHTML = `<i class="bi bi-fire text-danger"></i> ${c.calories} kcal`;
                    candidateIndex++;
                }
            }, 110);

            // Dừng guồng quay sau 2 giây và reveal món trúng
            setTimeout(() => {
                clearInterval(shuffleInterval);
                stopSpinning();

                // Hiển thị winner trên center card
                centerBoxImg.src = winner.image_url;
                centerStatusTitle.innerText = winner.name;
                centerSubHint.innerHTML = `<i class="bi bi-fire text-danger"></i> ${winner.calories} kcal • ${winner.category_name}`;

                // Pháo hoa ăn mừng Canvas Confetti
                if (typeof confetti === 'function') {
                    confetti({
                        particleCount: 90,
                        spread: 80,
                        origin: { y: 0.6 }
                    });
                }

                // Điền dữ liệu vào Result Modal
                winnerImage.src = winner.image_url;
                winnerName.innerText = winner.name;
                winnerCategory.innerText = winner.category_name;
                winnerServing.innerText = winner.serving;
                winnerDesc.innerText = winner.description;
                winnerCalories.innerText = winner.calories;
                winnerProtein.innerText = `${winner.protein}g`;
                winnerCarbs.innerText = `${winner.carbs}g`;
                winnerFat.innerText = `${winner.fat}g`;
                winnerAiReasoning.innerText = winner.ai_reasoning;
                btnViewRecipe.href = winner.detail_url;
                modalActionNotice.innerHTML = '';

                // Thêm vào danh sách lịch sử hiển thị tức thì
                prependHistoryItem(winner, currentSelectedMeal);

                // Mở modal kết quả sau 350ms
                setTimeout(() => {
                    resultModal.show();
                }, 350);

            }, 2100);

        })
        .catch(err => {
            console.error(err);
            stopSpinning();
            spinNotice.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Lỗi kết nối máy chủ. Vui lòng thử lại sau!</span>';
        });
    });

    function stopSpinning() {
        isSpinning = false;
        btnSpin.disabled = (spinsLeft <= 0);
        btnSpin.innerHTML = '<i class="bi bi-gift-fill me-2 fs-5"></i> MỞ HỘP GỢI Ý';
        centerMysteryBox.classList.remove('card-spinning-3d');
        if (spinsLeft <= 0) {
            spinNotice.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-info-circle me-1"></i>Bạn đã sử dụng hết 5 lượt hôm nay. Hãy quay lại vào ngày mai nhé!</span>';
        }
    }

    function updateSpinsDots(left) {
        if (!spinsPillsBar) return;
        const dots = spinsPillsBar.querySelectorAll('.spin-indicator-dot');
        dots.forEach((dot, index) => {
            if (index >= left) {
                dot.classList.add('used');
            } else {
                dot.classList.remove('used');
            }
        });
    }

    // 5. Mở lại (đổi món khác)
    btnSpinAgain.addEventListener('click', function() {
        resultModal.hide();
        setTimeout(() => {
            if (spinsLeft > 0) {
                btnSpin.click();
            } else {
                spinNotice.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Bạn đã dùng hết lượt mở hộp hôm nay!</span>';
            }
        }, 400);
    });

    // 6. Thêm món vào nhật ký bữa ăn
    btnAddToLog.addEventListener('click', function() {
        if (!currentWinner) return;

        btnAddToLog.disabled = true;
        btnAddToLog.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Đang lưu...';
        modalActionNotice.innerHTML = '';

        fetch('<?php echo BASE_URL; ?>/api/add_box_to_meal_log.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                food_id: currentWinner.id,
                meal_type: currentSelectedMeal,
                log_date: '<?php echo $today; ?>'
            })
        })
        .then(res => res.json())
        .then(data => {
            btnAddToLog.disabled = false;
            btnAddToLog.innerHTML = '<i class="bi bi-journal-check me-1"></i> Đã thêm vào nhật ký';

            if (data.require_login) {
                modalActionNotice.innerHTML = `<span class="text-warning fw-bold"><i class="bi bi-box-arrow-in-right me-1"></i>${data.message} <a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-success text-decoration-underline">Đăng nhập ngay</a></span>`;
                return;
            }

            if (data.success) {
                modalActionNotice.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>${data.message} <a href="${data.log_url}" class="text-success text-decoration-underline ms-1" target="_blank">Xem nhật ký</a></span>`;
            } else {
                modalActionNotice.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>${data.message}</span>`;
            }
        })
        .catch(err => {
            console.error(err);
            btnAddToLog.disabled = false;
            btnAddToLog.innerHTML = '<i class="bi bi-journal-plus me-1"></i> Thêm vào nhật ký';
            modalActionNotice.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Lỗi kết nối. Không thể lưu vào nhật ký.</span>';
        });
    });

    // 7. Thêm nhanh từ danh sách lịch sử
    document.addEventListener('click', function(e) {
        const targetBtn = e.target.closest('.btn-quick-add');
        if (targetBtn) {
            const foodId = targetBtn.getAttribute('data-food-id');
            const mealType = targetBtn.getAttribute('data-meal-type') || 'lunch';

            targetBtn.disabled = true;
            targetBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

            fetch('<?php echo BASE_URL; ?>/api/add_box_to_meal_log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    food_id: foodId,
                    meal_type: mealType,
                    log_date: '<?php echo $today; ?>'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.require_login) {
                    alert('Vui lòng đăng nhập để lưu món ăn vào nhật ký!');
                    targetBtn.disabled = false;
                    targetBtn.innerHTML = '<i class="bi bi-plus"></i>Nhật ký';
                    window.location.href = '<?php echo BASE_URL; ?>/auth/login.php';
                    return;
                }
                if (data.success) {
                    targetBtn.classList.remove('btn-success');
                    targetBtn.classList.add('btn-outline-success');
                    targetBtn.innerHTML = '<i class="bi bi-check2"></i> Đã thêm';
                } else {
                    alert(data.message);
                    targetBtn.disabled = false;
                    targetBtn.innerHTML = '<i class="bi bi-plus"></i>Nhật ký';
                }
            })
            .catch(err => {
                console.error(err);
                targetBtn.disabled = false;
                targetBtn.innerHTML = '<i class="bi bi-plus"></i>Nhật ký';
                alert('Lỗi kết nối. Vui lòng thử lại!');
            });
        }
    });

    // 8. Đưa món vừa quay vào đầu danh sách lịch sử
    function prependHistoryItem(item, mealType) {
        const emptyNotice = document.getElementById('emptyHistoryNotice');
        if (emptyNotice) {
            emptyNotice.remove();
        }

        const now = new Date();
        const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        const historyGrid = document.getElementById('historyGrid');

        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3 animate__animated animate__fadeInDown';
        col.innerHTML = `
            <div class="history-food-tile">
                <img src="${item.image_url}" class="history-food-img" alt="${item.name}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-success-subtle text-success rounded-pill small" style="font-size: 0.7rem;">
                        ${item.category_name}
                    </span>
                    <span class="small text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-clock me-1"></i>${timeStr}
                    </span>
                </div>
                <h6 class="fw-bold text-dark text-truncate mb-2" title="${item.name}">
                    ${item.name}
                </h6>
                <div class="text-muted small mb-3">
                    <span class="badge bg-danger-subtle text-danger rounded-pill fw-bold">
                        <i class="bi bi-fire"></i> ${item.calories} kcal
                    </span>
                </div>
                <div class="mt-auto d-flex gap-1">
                    <a href="${item.detail_url}" class="btn btn-sm btn-outline-success w-50 rounded-pill fw-semibold" target="_blank" title="Xem công thức">
                        <i class="bi bi-book me-1"></i>Xem
                    </a>
                    <button type="button" class="btn btn-sm btn-success w-50 rounded-pill fw-semibold btn-quick-add" data-food-id="${item.id}" data-meal-type="${mealType}" title="Thêm vào nhật ký">
                        <i class="bi bi-plus"></i>Nhật ký
                    </button>
                </div>
            </div>
        `;

        historyGrid.insertBefore(col, historyGrid.firstChild);

        // Update count
        const historyCount = document.getElementById('historyCount');
        if (historyCount) {
            historyCount.innerText = parseInt(historyCount.innerText || 0) + 1;
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
