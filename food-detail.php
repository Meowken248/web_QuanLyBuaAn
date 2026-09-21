<?php
// food-detail.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/models/FoodModel.php';

$food_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($food_id <= 0) {
    header('Location: ' . BASE_URL . '/foods.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();
$foodModel = new FoodModel();
$food = $foodModel->getFoodById($food_id);

if (!$food) {
    header('Location: ' . BASE_URL . '/foods.php');
    exit;
}

$page_title = $food['name'] . ' - Dinh dưỡng & Công thức';
require_once __DIR__ . '/includes/header.php';

$img_src = food_image_url($food['image'] ?? null);

// Calculate Macro Energy Distribution
$protein_g = (float)($food['protein'] ?? 0);
$carbs_g = (float)($food['carbs'] ?? 0);
$fat_g = (float)($food['fat'] ?? 0);

$p_cal = $protein_g * 4;
$c_cal = $carbs_g * 4;
$f_cal = $fat_g * 9;
$total_macro_cal = $p_cal + $c_cal + $f_cal;

if ($total_macro_cal > 0) {
    $p_pct = round(($p_cal / $total_macro_cal) * 100);
    $c_pct = round(($c_cal / $total_macro_cal) * 100);
    $f_pct = max(0, 100 - $p_pct - $c_pct);
} else {
    $p_pct = 0;
    $c_pct = 0;
    $f_pct = 0;
}

// Health highlights
$health_tags = [];
if ($fat_g <= 5 && (float)$food['calories'] <= 450) {
    $health_tags[] = ['icon' => 'bi-shield-check', 'text' => 'Ít chất béo', 'class' => 'tag-low-fat'];
}
if ((float)($food['fiber'] ?? 0) >= 5) {
    $health_tags[] = ['icon' => 'bi-flower1', 'text' => 'Giàu chất xơ', 'class' => 'tag-high-fiber'];
}
if ($protein_g >= 15) {
    $health_tags[] = ['icon' => 'bi-lightning-charge-fill', 'text' => 'Giàu chất đạm', 'class' => 'tag-high-protein'];
}
if ((float)$food['calories'] > 0 && (float)$food['calories'] <= 350) {
    $health_tags[] = ['icon' => 'bi-heart-pulse-fill', 'text' => 'Kiểm soát calo', 'class' => 'tag-low-cal'];
}
if ((float)($food['sugar'] ?? 0) <= 2 && (float)$food['calories'] > 0) {
    $health_tags[] = ['icon' => 'bi-droplet-half', 'text' => 'Ít đường', 'class' => 'tag-low-sugar'];
}

// Parse Ingredients
$ingredients_raw = trim($food['ingredients'] ?? '');
$ingredients_list = [];
if (!empty($ingredients_raw)) {
    $split_items = preg_split('/[\r\n,;]+/', $ingredients_raw);
    foreach ($split_items as $item) {
        $clean = trim($item, " \t\n\r\0\x0B-•*");
        if ($clean !== '') {
            $ingredients_list[] = $clean;
        }
    }
}

// Parse Instructions
$instructions_raw = trim($food['instructions'] ?? '');
$instruction_steps = [];
if (!empty($instructions_raw)) {
    $lines = preg_split('/[\r\n]+/', $instructions_raw);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            $instruction_steps[] = $trimmed;
        }
    }
}

// Fetch Related Foods
$related_foods = [];
if (!empty($food['category_id'])) {
    $rel_stmt = $conn->prepare("SELECT f.*, c.name as category_name 
                                FROM foods f 
                                LEFT JOIN food_categories c ON f.category_id = c.id 
                                WHERE f.category_id = :cat_id AND f.id != :id AND f.status = 'active' 
                                ORDER BY RAND() LIMIT 4");
    $rel_stmt->execute([':cat_id' => $food['category_id'], ':id' => $food_id]);
    $related_foods = $rel_stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (count($related_foods) < 4) {
    $need = 4 - count($related_foods);
    $exclude_ids = array_merge([$food_id], array_column($related_foods, 'id'));
    $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
    $fill_stmt = $conn->prepare("SELECT f.*, c.name as category_name 
                                FROM foods f 
                                LEFT JOIN food_categories c ON f.category_id = c.id 
                                WHERE f.status = 'active' AND f.id NOT IN ($placeholders)
                                ORDER BY RAND() LIMIT $need");
    $fill_stmt->execute($exclude_ids);
    $extra_foods = $fill_stmt->fetchAll(PDO::FETCH_ASSOC);
    $related_foods = array_merge($related_foods, $extra_foods);
}
?>

<style>
/* ==========================================================
   MODERN FOOD DETAIL & NUTRITION MASTERWORK STYLES
   ========================================================== */
.food-detail-wrapper {
    position: relative;
}

/* Ambient glow orbs in background */
.food-detail-wrapper::before {
    content: '';
    position: fixed;
    top: 90px;
    right: 5%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(74, 222, 128, 0.09) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    z-index: 0;
}
.food-detail-wrapper::after {
    content: '';
    position: fixed;
    bottom: 5%;
    left: 4%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(251, 146, 60, 0.07) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    z-index: 0;
}

/* Breadcrumb Glass Pill */
.food-breadcrumb {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(220, 235, 225, 0.7);
    border-radius: 50rem;
    padding: 0.65rem 1.35rem;
    box-shadow: 0 4px 15px rgba(23, 72, 49, 0.03);
    display: inline-flex;
    align-items: center;
}
.food-breadcrumb .breadcrumb {
    margin-bottom: 0;
    font-size: 0.88rem;
}
.food-breadcrumb a {
    color: #2e7d32;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}
.food-breadcrumb a:hover {
    color: #1b5e20;
    text-decoration: underline;
}

/* Hero Media Card */
.food-hero-card {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid rgba(220, 235, 225, 0.8);
    box-shadow: 0 16px 36px rgba(23, 72, 49, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.food-hero-img-wrap {
    position: relative;
    width: 100%;
    height: 420px;
    overflow: hidden;
    background: #f1f5f9;
}
.food-hero-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.food-hero-card:hover .food-hero-img-wrap img {
    transform: scale(1.04);
}
.food-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0) 40%, rgba(0,0,0,0.65) 100%);
    pointer-events: none;
}
.food-hero-top-badges {
    position: absolute;
    top: 18px;
    left: 18px;
    right: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    z-index: 2;
}
.food-glass-badge {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    border-radius: 50rem;
    padding: 0.4rem 0.9rem;
    font-size: 0.82rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.food-hero-bottom-info {
    position: absolute;
    bottom: 18px;
    left: 18px;
    right: 18px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    z-index: 2;
}
.food-cal-pill {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.92) 0%, rgba(249, 115, 22, 0.92) 100%);
    backdrop-filter: blur(10px);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.4);
    box-shadow: 0 6px 18px rgba(239, 68, 68, 0.35);
    border-radius: 50rem;
    padding: 0.45rem 1.1rem;
    font-size: 0.92rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Glass Section Cards */
.food-section-card {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(220, 235, 225, 0.8);
    border-radius: 22px;
    box-shadow: 0 10px 28px rgba(23, 72, 49, 0.05);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: box-shadow 0.25s ease;
}
.food-section-card:hover {
    box-shadow: 0 14px 34px rgba(23, 72, 49, 0.08);
}

/* Ingredients Chips Grid */
.ingredient-chips-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}
.ingredient-chip {
    background: #f8faf9;
    border: 1px solid rgba(220, 235, 225, 0.9);
    border-radius: 12px;
    padding: 0.5rem 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.88rem;
    color: #1e293b;
    font-weight: 500;
    transition: all 0.2s ease;
}
.ingredient-chip:hover {
    background: #e8f5e9;
    border-color: #a5d6a7;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 125, 50, 0.1);
    color: #1b5e20;
}
.ingredient-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(46, 125, 50, 0.12);
    color: #2e7d32;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}

/* Recipe Step Timeline */
.recipe-timeline {
    position: relative;
    padding-left: 1rem;
}
.recipe-step-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding-bottom: 1.25rem;
}
.recipe-step-item:not(:last-child)::before {
    content: '';
    position: absolute;
    top: 32px;
    left: 17px;
    bottom: 0;
    width: 2px;
    background: #e2e8f0;
}
.step-badge {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.92rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.25);
    z-index: 1;
}
.step-content {
    flex-grow: 1;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0.85rem 1.15rem;
}

/* Serving Selector Card */
.serving-control-card {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.85) 0%, rgba(255, 255, 255, 0.95) 100%);
    border: 1px solid rgba(167, 243, 208, 0.8);
}
.serving-icon-wrap {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    margin-right: 14px;
}
.serving-pills .serving-btn {
    border: none;
    color: #64748b;
    background: transparent;
    font-size: 0.82rem;
    transition: all 0.2s ease;
}
.serving-pills .serving-btn:hover {
    color: #1e293b;
}
.serving-pills .serving-btn.active {
    background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(46, 125, 50, 0.3);
}

/* 4 Core Macro Nutrient Cards */
.macro-card {
    border-radius: 18px;
    padding: 1.1rem 0.85rem;
    text-align: center;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.macro-card:hover {
    transform: translateY(-3px);
}
.macro-icon {
    font-size: 1.25rem;
    margin-bottom: 0.35rem;
}
.macro-value {
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.5px;
}
.macro-value small {
    font-size: 0.85rem;
    font-weight: 600;
    margin-left: 2px;
}
.macro-unit {
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 2px;
    opacity: 0.85;
}
.macro-label {
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.45rem;
}

/* Macro Variants */
.macro-calories {
    background: linear-gradient(145deg, #fff5f5 0%, #ffe4e6 100%);
    border: 1px solid rgba(244, 63, 94, 0.3);
    color: #e11d48;
    box-shadow: 0 6px 18px rgba(244, 63, 94, 0.08);
}
.macro-calories .macro-icon { color: #f43f5e; }
.macro-calories:hover { box-shadow: 0 10px 24px rgba(244, 63, 94, 0.16); }

.macro-protein {
    background: linear-gradient(145deg, #f5f3ff 0%, #ede9fe 100%);
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: #6d28d9;
    box-shadow: 0 6px 18px rgba(139, 92, 246, 0.08);
}
.macro-protein .macro-icon { color: #8b5cf6; }
.macro-protein:hover { box-shadow: 0 10px 24px rgba(139, 92, 246, 0.16); }

.macro-carbs {
    background: linear-gradient(145deg, #fffbeb 0%, #fef3c7 100%);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #b45309;
    box-shadow: 0 6px 18px rgba(245, 158, 11, 0.08);
}
.macro-carbs .macro-icon { color: #f59e0b; }
.macro-carbs:hover { box-shadow: 0 10px 24px rgba(245, 158, 11, 0.16); }

.macro-fat {
    background: linear-gradient(145deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #047857;
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.08);
}
.macro-fat .macro-icon { color: #10b981; }
.macro-fat:hover { box-shadow: 0 10px 24px rgba(16, 185, 129, 0.16); }

/* Macro Distribution Bar */
.macro-ratio-wrapper {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
}
.macro-progress-bar {
    background: #e2e8f0;
    overflow: hidden;
}
.bg-indigo { background-color: #8b5cf6 !important; }
.bg-amber { background-color: #f59e0b !important; }
.bg-emerald { background-color: #10b981 !important; }
.dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
}

/* Micro Nutrients Grid */
.micro-tile {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}
.micro-tile:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
    transform: translateY(-1px);
}
.micro-tile-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.micro-tile-icon {
    font-size: 1.05rem;
    display: flex;
    align-items: center;
}
.micro-tile-name {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}
.micro-tile-val {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
}

/* Health Tags */
.health-tag-badge {
    border-radius: 50rem;
    padding: 0.35rem 0.8rem;
    font-size: 0.78rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid transparent;
}
.tag-low-fat {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}
.tag-high-fiber {
    background: #f0fdf4;
    color: #15803d;
    border-color: #bbf7d0;
}
.tag-high-protein {
    background: #f5f3ff;
    color: #6d28d9;
    border-color: #ddd6fe;
}
.tag-low-cal {
    background: #fff7ed;
    color: #c2410c;
    border-color: #ffedd5;
}
.tag-low-sugar {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #dbeafe;
}

/* Action Buttons */
.action-buttons-group {
    margin-top: 1rem;
}
.action-buttons-flex {
    display: flex;
    gap: 14px !important;
}
.btn-food-action {
    background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
    color: #ffffff;
    border: none;
    border-radius: 50rem;
    padding: 0.85rem 1.75rem;
    font-weight: 700;
    font-size: 1.02rem;
    box-shadow: 0 8px 20px rgba(46, 125, 50, 0.28);
    transition: all 0.25s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 50px;
}
.btn-food-action:hover {
    background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 12px 26px rgba(46, 125, 50, 0.38);
}
.btn-food-secondary {
    border-radius: 50rem;
    padding: 0.75rem 1.35rem;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 50px;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.btn-food-secondary:hover {
    transform: translateY(-2px);
}

/* Related Food Cards */
.related-food-card {
    background: #ffffff;
    border: 1px solid rgba(220, 235, 225, 0.8);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(23, 72, 49, 0.04);
    transition: all 0.25s ease;
}
.related-food-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 28px rgba(23, 72, 49, 0.09);
    border-color: #a5d6a7;
}
.related-img-wrap {
    position: relative;
    height: 145px;
    overflow: hidden;
    background: #f1f5f9;
}
.related-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.related-food-card:hover .related-img-wrap img {
    transform: scale(1.08);
}
.related-kcal-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(8px);
    border-radius: 50rem;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #1e293b;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Toast Copy Notification */
.share-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1e293b;
    color: #ffffff;
    padding: 0.75rem 1.25rem;
    border-radius: 50rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    font-size: 0.9rem;
    font-weight: 500;
    z-index: 9999;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
}
.share-toast.show {
    opacity: 1;
    transform: translateY(0);
}

@media (max-width: 767.98px) {
    .food-hero-img-wrap {
        height: 280px;
    }
    .macro-value {
        font-size: 1.35rem;
    }
    .food-section-card {
        padding: 1.15rem;
    }
}
</style>

<div class="container py-4 py-lg-5 food-detail-wrapper">
    <!-- Breadcrumb Glass Pill -->
    <nav aria-label="breadcrumb" class="food-breadcrumb mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="<?php echo BASE_URL; ?>"><i class="bi bi-house-door-fill me-1"></i>Trang chủ</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo BASE_URL; ?>/foods.php">Thư viện món ăn</a>
            </li>
            <?php if (!empty($food['category_name'])): ?>
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>/foods.php?category=<?php echo $food['category_id']; ?>">
                        <?php echo htmlspecialchars($food['category_name']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-dark fw-bold text-truncate" style="max-width: 260px;" aria-current="page">
                <?php echo htmlspecialchars($food['name']); ?>
            </li>
        </ol>
    </nav>

    <div class="row g-4 g-xl-5">
        <!-- ================= CỘT TRÁI: HÌNH ẢNH & NGUYÊN LIỆU & CÁCH LÀM ================= -->
        <div class="col-lg-5 col-xl-5">
            <!-- Hero Image Card -->
            <div class="food-hero-card mb-4">
                <div class="food-hero-img-wrap">
                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($food['name']); ?>">
                    <div class="food-hero-overlay"></div>
                    
                    <!-- Floating Top Badges -->
                    <div class="food-hero-top-badges">
                        <span class="food-glass-badge text-success">
                            <i class="bi bi-tag-fill"></i> <?php echo htmlspecialchars($food['category_name'] ?? 'Món ăn'); ?>
                        </span>
                        <?php 
                        $uSeasons = !empty($food['season']) ? explode(',', $food['season']) : ['xuan','he','thu','dong'];
                        $uIsAll = (count($uSeasons) >= 4 || in_array('all', $uSeasons) || in_array('bon_mua', $uSeasons));
                        if ($uIsAll) {
                            echo '<span class="food-glass-badge text-dark"><i class="bi bi-calendar4-week text-primary"></i> Bốn mùa</span>';
                        } else {
                            $uMap = ['xuan' => '🌸 Xuân', 'he' => '☀️ Hè', 'thu' => '🍂 Thu', 'dong' => '❄️ Đông'];
                            foreach ($uSeasons as $us) {
                                $us = trim($us);
                                if (isset($uMap[$us])) {
                                    echo '<span class="food-glass-badge text-dark">' . $uMap[$us] . '</span>';
                                }
                            }
                        }
                        ?>
                    </div>

                    <!-- Floating Bottom Info -->
                    <div class="food-hero-bottom-info">
                        <div class="food-cal-pill">
                            <i class="bi bi-fire"></i>
                            <span id="img-badge-calories"><?php echo floatval($food['calories']); ?></span> kcal
                        </div>
                        <span class="food-glass-badge text-muted">
                            <i class="bi bi-basket me-1 text-success"></i> 
                            <?php echo count($ingredients_list) ? count($ingredients_list) . ' nguyên liệu' : 'Món chuẩn vị'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card: Nguyên liệu chế biến -->
            <div class="food-section-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-success d-flex align-items-center gap-2">
                        <i class="bi bi-basket3-fill"></i> Nguyên liệu
                    </h5>
                    <?php if (count($ingredients_list) > 0): ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1">
                            <?php echo count($ingredients_list); ?> thành phần
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (count($ingredients_list) > 0): ?>
                    <div class="ingredient-chips-grid">
                        <?php foreach ($ingredients_list as $ing): ?>
                            <div class="ingredient-chip">
                                <span class="ingredient-dot"><i class="bi bi-check2"></i></span>
                                <span><?php echo htmlspecialchars($ing); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <i class="bi bi-inbox fs-2 text-muted opacity-50 mb-1 d-block"></i>
                        <p class="small mb-0 fst-italic">Chưa có thông tin nguyên liệu chi tiết.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card: Cách làm & Chế biến -->
            <div class="food-section-card mb-lg-0">
                <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                    <i class="bi bi-journal-check"></i> Hướng dẫn thực hiện
                </h5>

                <?php if (count($instruction_steps) > 0): ?>
                    <div class="recipe-timeline">
                        <?php foreach ($instruction_steps as $idx => $step): ?>
                            <div class="recipe-step-item">
                                <div class="step-badge"><?php echo ($idx + 1); ?></div>
                                <div class="step-content">
                                    <div class="fw-bold text-dark mb-1" style="font-size: 0.88rem;">Bước <?php echo ($idx + 1); ?></div>
                                    <p class="mb-0 text-secondary" style="font-size: 0.9rem; line-height: 1.55;">
                                        <?php echo htmlspecialchars($step); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 bg-light rounded-4">
                        <i class="bi bi-cup-hot fs-2 text-muted opacity-50 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">Công thức đang được cập nhật</h6>
                        <p class="text-muted small mb-0 px-3">Đội ngũ chuyên gia dinh dưỡng đang hoàn thiện các bước chế biến món này. Bạn có thể tự do biến tấu theo khẩu vị gia đình nhé!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= CỘT PHẢI: CHI TIẾT DINH DƯỠNG & HÀNH ĐỘNG ================= -->
        <div class="col-lg-7 col-xl-7">
            <div class="food-section-card h-100 d-flex flex-column justify-content-between">
                <div>
                    <!-- Health Highlights Badges -->
                    <?php if (!empty($health_tags)): ?>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <?php foreach ($health_tags as $htag): ?>
                                <span class="health-tag-badge <?php echo $htag['class']; ?>">
                                    <i class="bi <?php echo $htag['icon']; ?>"></i> <?php echo $htag['text']; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Food Title -->
                    <h1 class="fw-bold text-dark mb-2" style="font-size: 2.1rem; letter-spacing: -0.5px;">
                        <?php echo htmlspecialchars($food['name']); ?>
                    </h1>

                    <!-- Description -->
                    <p class="text-muted mb-4" style="font-size: 1.05rem; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($food['description'] ?? 'Món ăn giàu dinh dưỡng, thanh đạm và dễ tiêu hóa, phù hợp cho mọi chế độ ăn uống lành mạnh.')); ?>
                    </p>

                    <!-- Interactive Serving Size Adjuster -->
                    <div class="serving-control-card p-3 rounded-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div class="d-flex align-items-center">
                                <div class="serving-icon-wrap">
                                    <i class="bi bi-pie-chart-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.95rem;">Khẩu phần tham chiếu</div>
                                    <div class="text-muted small">
                                        <span id="display-serving-size"><?php echo floatval($food['serving_size']); ?></span> 
                                        <?php echo htmlspecialchars($food['serving_unit']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-muted d-none d-sm-inline">Tỉ lệ ăn:</span>
                                <div class="serving-pills d-inline-flex align-items-center p-1 rounded-pill bg-white border shadow-sm">
                                    <button type="button" class="serving-btn btn btn-sm rounded-pill px-2.5 py-1 fw-semibold" data-multiplier="0.5">0.5x</button>
                                    <button type="button" class="serving-btn btn btn-sm rounded-pill px-2.5 py-1 fw-semibold active" data-multiplier="1">1x Chuẩn</button>
                                    <button type="button" class="serving-btn btn btn-sm rounded-pill px-2.5 py-1 fw-semibold" data-multiplier="1.5">1.5x</button>
                                    <button type="button" class="serving-btn btn btn-sm rounded-pill px-2.5 py-1 fw-semibold" data-multiplier="2">2x</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4 Core Macro Nutrient Cards -->
                    <div class="row g-2 g-md-3 mb-4">
                        <!-- Calories -->
                        <div class="col-6 col-md-3">
                            <div class="macro-card macro-calories">
                                <div class="macro-icon"><i class="bi bi-fire"></i></div>
                                <div class="macro-value">
                                    <span id="val-calories"><?php echo floatval($food['calories']); ?></span>
                                </div>
                                <div class="macro-unit">Kcal</div>
                                <div class="macro-label">Năng lượng</div>
                            </div>
                        </div>
                        <!-- Protein -->
                        <div class="col-6 col-md-3">
                            <div class="macro-card macro-protein">
                                <div class="macro-icon"><i class="bi bi-shield-shaded"></i></div>
                                <div class="macro-value">
                                    <span id="val-protein"><?php echo floatval($food['protein']); ?></span><small>g</small>
                                </div>
                                <div class="macro-unit"><?php echo $p_pct; ?>% calo</div>
                                <div class="macro-label">Chất đạm</div>
                            </div>
                        </div>
                        <!-- Carbs -->
                        <div class="col-6 col-md-3">
                            <div class="macro-card macro-carbs">
                                <div class="macro-icon"><i class="bi bi-lightning-charge-fill"></i></div>
                                <div class="macro-value">
                                    <span id="val-carbs"><?php echo floatval($food['carbs']); ?></span><small>g</small>
                                </div>
                                <div class="macro-unit"><?php echo $c_pct; ?>% calo</div>
                                <div class="macro-label">Tinh bột</div>
                            </div>
                        </div>
                        <!-- Fat -->
                        <div class="col-6 col-md-3">
                            <div class="macro-card macro-fat">
                                <div class="macro-icon"><i class="bi bi-droplet-fill"></i></div>
                                <div class="macro-value">
                                    <span id="val-fat"><?php echo floatval($food['fat']); ?></span><small>g</small>
                                </div>
                                <div class="macro-unit"><?php echo $f_pct; ?>% calo</div>
                                <div class="macro-label">Chất béo</div>
                            </div>
                        </div>
                    </div>

                    <!-- Macro Energy Distribution Bar -->
                    <div class="macro-ratio-wrapper p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-pie-chart-fill text-success"></i> Phân bổ tỷ lệ năng lượng Macro
                            </span>
                            <span class="small text-muted">
                                Tổng ~<strong id="val-total-macro-cal"><?php echo round($total_macro_cal); ?></strong> kcal
                            </span>
                        </div>
                        <div class="progress rounded-pill macro-progress-bar" style="height: 10px;">
                            <div class="progress-bar bg-indigo" id="bar-protein" style="width: <?php echo $p_pct; ?>%;" title="Đạm: <?php echo $p_pct; ?>%"></div>
                            <div class="progress-bar bg-amber" id="bar-carbs" style="width: <?php echo $c_pct; ?>%;" title="Tinh bột: <?php echo $c_pct; ?>%"></div>
                            <div class="progress-bar bg-emerald" id="bar-fat" style="width: <?php echo $f_pct; ?>%;" title="Chất béo: <?php echo $f_pct; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2.5 pt-1 small text-secondary flex-wrap gap-2">
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="dot bg-indigo"></span> Đạm: <strong class="text-dark" id="pct-protein"><?php echo $p_pct; ?>%</strong>
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="dot bg-amber"></span> Tinh bột: <strong class="text-dark" id="pct-carbs"><?php echo $c_pct; ?>%</strong>
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="dot bg-emerald"></span> Chất béo: <strong class="text-dark" id="pct-fat"><?php echo $f_pct; ?>%</strong>
                            </span>
                        </div>
                    </div>

                    <!-- Micro-Nutrients 2x2 Grid -->
                    <div class="micro-nutrients-section mb-4">
                        <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-activity text-success"></i> Vi chất dinh dưỡng bổ sung
                        </h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="micro-tile">
                                    <div class="micro-tile-head">
                                        <span class="micro-tile-icon text-success"><i class="bi bi-flower1"></i></span>
                                        <span class="micro-tile-name">Chất xơ (Fiber)</span>
                                    </div>
                                    <div class="micro-tile-val">
                                        <span id="val-fiber"><?php echo floatval($food['fiber'] ?? 0); ?></span> g
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="micro-tile">
                                    <div class="micro-tile-head">
                                        <span class="micro-tile-icon text-warning"><i class="bi bi-cup-straw"></i></span>
                                        <span class="micro-tile-name">Đường (Sugar)</span>
                                    </div>
                                    <div class="micro-tile-val">
                                        <span id="val-sugar"><?php echo floatval($food['sugar'] ?? 0); ?></span> g
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="micro-tile">
                                    <div class="micro-tile-head">
                                        <span class="micro-tile-icon text-info"><i class="bi bi-moisture"></i></span>
                                        <span class="micro-tile-name">Natri (Sodium)</span>
                                    </div>
                                    <div class="micro-tile-val">
                                        <span id="val-sodium"><?php echo floatval($food['sodium'] ?? 0); ?></span> mg
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="micro-tile">
                                    <div class="micro-tile-head">
                                        <span class="micro-tile-icon text-danger"><i class="bi bi-heart-pulse"></i></span>
                                        <span class="micro-tile-name">Cholesterol</span>
                                    </div>
                                    <div class="micro-tile-val">
                                        <span id="val-cholesterol"><?php echo floatval($food['cholesterol'] ?? 0); ?></span> mg
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Buttons Group -->
                <div class="action-buttons-group pt-3 border-top">
                    <div class="action-buttons-flex flex-column flex-sm-row">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo BASE_URL; ?>/user/add-meal.php?food_id=<?php echo $food['id']; ?>" class="btn-food-action flex-grow-1 shadow-sm text-decoration-none">
                                <i class="bi bi-plus-circle-fill me-2"></i>Thêm vào nhật ký bữa ăn
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn-food-action flex-grow-1 shadow-sm text-decoration-none">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập để thêm món
                            </a>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-outline-secondary btn-food-secondary" id="btn-share-food" title="Sao chép liên kết món ăn">
                            <i class="bi bi-share"></i> <span>Chia sẻ</span>
                        </button>
                        <a href="<?php echo BASE_URL; ?>/foods.php" class="btn btn-outline-success btn-food-secondary text-decoration-none">
                            <i class="bi bi-arrow-left"></i> <span>Quay lại</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= GỢI Ý MÓN ĂN TƯƠNG TỰ (RELATED FOODS) ================= -->
    <?php if (!empty($related_foods)): ?>
    <section class="mt-5 pt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-stars text-warning"></i> Có thể bạn cũng thích
                </h4>
                <p class="text-muted small mb-0">Các món ăn bổ dưỡng khác cùng danh mục <?php echo htmlspecialchars($food['category_name'] ?? ''); ?></p>
            </div>
            <a href="<?php echo BASE_URL; ?>/foods.php<?php echo !empty($food['category_id']) ? '?category=' . $food['category_id'] : ''; ?>" class="btn btn-sm btn-outline-success rounded-pill px-3.5 py-1.5 fw-semibold text-decoration-none">
                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row g-3 g-md-4">
            <?php foreach ($related_foods as $rel): ?>
                <div class="col-6 col-md-3">
                    <a href="<?php echo BASE_URL; ?>/food-detail.php?id=<?php echo $rel['id']; ?>" class="text-decoration-none text-dark h-100 d-block">
                        <div class="related-food-card h-100 d-flex flex-column justify-content-between">
                            <div class="related-img-wrap">
                                <img src="<?php echo food_image_url($rel['image'] ?? null); ?>" alt="<?php echo htmlspecialchars($rel['name']); ?>" loading="lazy">
                                <span class="related-kcal-badge"><i class="bi bi-fire text-danger"></i> <?php echo floatval($rel['calories']); ?> kcal</span>
                            </div>
                            <div class="related-body p-3">
                                <h6 class="related-title text-truncate fw-bold mb-1" style="font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($rel['name']); ?>
                                </h6>
                                <div class="d-flex justify-content-between align-items-center text-muted small mt-2">
                                    <span><i class="bi bi-shield-shaded text-primary"></i> <?php echo floatval($rel['protein']); ?>g đạm</span>
                                    <span class="text-success fw-semibold">Chi tiết &rarr;</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Floating Toast Notification for Share -->
<div id="shareToast" class="share-toast">
    <i class="bi bi-check2-circle text-success me-1"></i> Đã sao chép liên kết món ăn vào khay nhớ tạm!
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Base Nutritional Data
    const baseData = {
        servingSize: <?php echo floatval($food['serving_size']); ?>,
        calories: <?php echo floatval($food['calories']); ?>,
        protein: <?php echo floatval($food['protein']); ?>,
        carbs: <?php echo floatval($food['carbs']); ?>,
        fat: <?php echo floatval($food['fat']); ?>,
        fiber: <?php echo floatval($food['fiber'] ?? 0); ?>,
        sugar: <?php echo floatval($food['sugar'] ?? 0); ?>,
        sodium: <?php echo floatval($food['sodium'] ?? 0); ?>,
        cholesterol: <?php echo floatval($food['cholesterol'] ?? 0); ?>
    };

    const servingButtons = document.querySelectorAll('.serving-btn');
    const displayServingSize = document.getElementById('display-serving-size');
    const valCalories = document.getElementById('val-calories');
    const imgBadgeCalories = document.getElementById('img-badge-calories');
    const valProtein = document.getElementById('val-protein');
    const valCarbs = document.getElementById('val-carbs');
    const valFat = document.getElementById('val-fat');
    const valFiber = document.getElementById('val-fiber');
    const valSugar = document.getElementById('val-sugar');
    const valSodium = document.getElementById('val-sodium');
    const valCholesterol = document.getElementById('val-cholesterol');
    const valTotalMacroCal = document.getElementById('val-total-macro-cal');

    // Helper to format float cleanly
    function formatNum(num) {
        return Math.round(num * 10) / 10;
    }

    servingButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            servingButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const mult = parseFloat(this.getAttribute('data-multiplier')) || 1;

            // Recalculate values
            if (displayServingSize) displayServingSize.textContent = formatNum(baseData.servingSize * mult);
            
            const curCalories = formatNum(baseData.calories * mult);
            if (valCalories) valCalories.textContent = curCalories;
            if (imgBadgeCalories) imgBadgeCalories.textContent = curCalories;

            const curProtein = formatNum(baseData.protein * mult);
            const curCarbs = formatNum(baseData.carbs * mult);
            const curFat = formatNum(baseData.fat * mult);

            if (valProtein) valProtein.textContent = curProtein;
            if (valCarbs) valCarbs.textContent = curCarbs;
            if (valFat) valFat.textContent = curFat;

            if (valFiber) valFiber.textContent = formatNum(baseData.fiber * mult);
            if (valSugar) valSugar.textContent = formatNum(baseData.sugar * mult);
            if (valSodium) valSodium.textContent = formatNum(baseData.sodium * mult);
            if (valCholesterol) valCholesterol.textContent = formatNum(baseData.cholesterol * mult);

            // Total Macro energy
            const pCal = curProtein * 4;
            const cCal = curCarbs * 4;
            const fCal = curFat * 9;
            const totalMacro = pCal + cCal + fCal;
            if (valTotalMacroCal) valTotalMacroCal.textContent = Math.round(totalMacro);

            if (totalMacro > 0) {
                const pPct = Math.round((pCal / totalMacro) * 100);
                const cPct = Math.round((cCal / totalMacro) * 100);
                const fPct = Math.max(0, 100 - pPct - cPct);

                const barProtein = document.getElementById('bar-protein');
                const barCarbs = document.getElementById('bar-carbs');
                const barFat = document.getElementById('bar-fat');
                const pctProtein = document.getElementById('pct-protein');
                const pctCarbs = document.getElementById('pct-carbs');
                const pctFat = document.getElementById('pct-fat');

                if (barProtein) barProtein.style.width = pPct + '%';
                if (barCarbs) barCarbs.style.width = cPct + '%';
                if (barFat) barFat.style.width = fPct + '%';

                if (pctProtein) pctProtein.textContent = pPct + '%';
                if (pctCarbs) pctCarbs.textContent = cPct + '%';
                if (pctFat) pctFat.textContent = fPct + '%';
            }
        });
    });

    // Share Food Button (Copy Link)
    const shareBtn = document.getElementById('btn-share-food');
    const toast = document.getElementById('shareToast');
    if (shareBtn && toast) {
        shareBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(window.location.href).then(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }).catch(() => {
                prompt('Sao chép liên kết này:', window.location.href);
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
