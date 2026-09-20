<?php
// api/spin_mystery_box.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $conn = $db->getConnection();

    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    $today = date('Y-m-d');
    $maxSpins = 5;

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

    // Lấy danh sách lịch sử quay hôm nay
    $historySql = "SELECT h.id, h.created_at, h.meal_type, h.health_goal, h.budget,
                          f.id as food_id, f.name, f.image, f.calories, f.protein, f.carbs, f.fat, f.estimated_price,
                          c.name as category_name
                   FROM mystery_box_history h
                   JOIN foods f ON h.food_id = f.id
                   LEFT JOIN food_categories c ON f.category_id = c.id
                   WHERE (h.user_id = :uid OR (h.user_id IS NULL AND h.session_id = :sid))
                     AND h.spin_date = :today
                   ORDER BY h.id DESC LIMIT 10";
    $stmtHist = $conn->prepare($historySql);
    $stmtHist->execute([
        ':uid' => $userId ?: 0,
        ':sid' => $sessionId,
        ':today' => $today
    ]);
    $historyList = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    foreach ($historyList as &$hItem) {
        $hItem['image_url'] = food_image_url($hItem['image']);
        $hItem['calories'] = floatval($hItem['calories']);
        $hItem['protein'] = floatval($hItem['protein']);
        $hItem['carbs'] = floatval($hItem['carbs']);
        $hItem['fat'] = floatval($hItem['fat']);
        $hItem['time_formatted'] = date('H:i', strtotime($hItem['created_at']));
    }
    unset($hItem);

    // Kiểm tra action
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if ($action === 'status') {
        echo json_encode([
            'success' => true,
            'spins_left' => $spinsLeft,
            'spins_today' => $spinsToday,
            'max_spins' => $maxSpins,
            'history' => $historyList
        ]);
        exit;
    }

    // Nếu là thao tác MỞ HỘP (spin)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => true,
            'spins_left' => $spinsLeft,
            'spins_today' => $spinsToday,
            'max_spins' => $maxSpins,
            'history' => $historyList
        ]);
        exit;
    }

    // Không giới hạn lượt mở hộp (Unlimited spins)

    // Nhận dữ liệu filter từ request (JSON hoặc POST thông thường)
    $inputData = json_decode(file_get_contents('php://input'), true);
    if (!is_array($inputData)) {
        $inputData = $_POST;
    }

    $mealType = trim($inputData['meal_type'] ?? 'lunch');
    $healthGoal = trim($inputData['health_goal'] ?? 'all');

    // Xây dựng điều kiện lọc linh hoạt
    $conditions = ["f.status = 'active'"];
    $params = [];

    if ($mealType === 'snack') {
        $mealType = 'afternoon_snack';
    }

    // Filter theo meal_type
    if ($mealType === 'breakfast') {
        $conditions[] = "(f.category_id IN (23, 26, 41, 44, 65, 66, 67, 68, 70, 71) 
                         OR f.name LIKE '%phở%' OR f.name LIKE '%bún%' OR f.name LIKE '%bánh mì%' 
                         OR f.name LIKE '%cháo%' OR f.name LIKE '%xôi%' OR f.name LIKE '%trứng%' 
                         OR f.name LIKE '%yến mạch%' OR f.name LIKE '%pancake%')";
    } elseif ($mealType === 'afternoon_snack' || $mealType === 'morning_snack' || $mealType === 'evening_snack') {
        $conditions[] = "(f.category_id IN (25, 29, 70, 71, 72) 
                         OR f.calories <= 320 
                         OR f.name LIKE '%sinh tố%' OR f.name LIKE '%nước ép%' OR f.name LIKE '%sữa chua%' 
                         OR f.name LIKE '%chè%' OR f.name LIKE '%bánh%' OR f.name LIKE '%gỏi cuốn%')";
    } elseif ($mealType === 'dinner') {
        $conditions[] = "(f.category_id IN (22, 23, 24, 28, 30, 31, 39, 42, 43, 60, 61, 62) 
                         OR f.calories BETWEEN 200 AND 650)";
    } elseif ($mealType === 'lunch') {
        $conditions[] = "(f.category_id IN (22, 23, 27, 31, 35, 36, 37, 38, 39, 56, 58, 59, 62, 63, 65, 66, 67) 
                         OR f.calories >= 320)";
    }

    // Filter theo health_goal
    if ($healthGoal === 'weight_loss') {
        $conditions[] = "(f.calories <= 450 OR f.category_id IN (24, 45, 48, 50, 73))";
    } elseif ($healthGoal === 'muscle_gain') {
        $conditions[] = "(f.protein >= 25 OR f.category_id IN (46, 51, 53))";
    } elseif ($healthGoal === 'weight_gain') {
        $conditions[] = "(f.calories >= 520 OR f.category_id = 49)";
    } elseif ($healthGoal === 'eat_clean') {
        $conditions[] = "(f.category_id IN (24, 30, 42, 43, 47, 73) OR f.fiber >= 3 OR f.diet_type IN ('healthy', 'clean', 'eat_clean'))";
    }

    $whereClause = implode(' AND ', $conditions);
    $query = "SELECT f.*, c.name as category_name 
              FROM foods f
              LEFT JOIN food_categories c ON f.category_id = c.id
              WHERE {$whereClause}
              ORDER BY RAND() LIMIT 20";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback nếu bộ lọc quá khắt khe khiến < 4 món
    if (count($candidates) < 4) {
        $fallbackQuery = "SELECT f.*, c.name as category_name 
                          FROM foods f
                          LEFT JOIN food_categories c ON f.category_id = c.id
                          WHERE f.status = 'active'
                          ORDER BY RAND() LIMIT 15";
        $candidates = $conn->query($fallbackQuery)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Chọn ngẫu nhiên món trúng thưởng (Winner)
    $winnerIndex = array_rand($candidates);
    $winner = $candidates[$winnerIndex];

    // Tạo danh sách các món dùng cho hiệu ứng quay/lắc thẻ (Reel)
    // Bao gồm món trúng thưởng và các món mồi xung quanh
    shuffle($candidates);
    $displayCandidates = array_slice($candidates, 0, 8);
    // Đảm bảo winner có mặt trong danh sách
    $hasWinner = false;
    foreach ($displayCandidates as $dc) {
        if ($dc['id'] === $winner['id']) {
            $hasWinner = true;
            break;
        }
    }
    if (!$hasWinner) {
        $displayCandidates[0] = $winner;
        shuffle($displayCandidates);
    }

    // Format dữ liệu winner
    $calories = floatval($winner['calories']);
    $protein = floatval($winner['protein']);
    $carbs = floatval($winner['carbs']);
    $fat = floatval($winner['fat']);
    $fiber = floatval($winner['fiber'] ?? 0);
    $price = (int)($winner['estimated_price'] ?? 35000);

    // Sinh lời khuyên & đánh giá AI thông minh cho món ăn này
    $aiReasons = [];
    if ($healthGoal === 'weight_loss' || $calories <= 400) {
        $aiReasons[] = "Calo kiểm soát tốt (~{$calories} kcal), rất phù hợp cho kế hoạch giảm mỡ và siết dáng lành mạnh.";
    }
    if ($healthGoal === 'muscle_gain' || $protein >= 28) {
        $aiReasons[] = "Hàm lượng Protein dồi dào (~{$protein}g) hỗ trợ tái tạo mô cơ và duy trì năng lượng bền bỉ.";
    }
    if ($healthGoal === 'eat_clean' || $fiber >= 3) {
        $aiReasons[] = "Giàu chất xơ và vitamin tự nhiên, tạo cảm giác no nhẹ bụng và tốt cho hệ tiêu hóa.";
    }
    $aiReasons[] = "Hương vị thơm ngon, cân đối các nhóm chất giúp duy trì năng lượng bền bỉ cho ngày dài.";
    $aiReasoning = implode(' ', $aiReasons);

    // Chuẩn bị response object cho winner
    $winnerData = [
        'id' => (int)$winner['id'],
        'name' => $winner['name'],
        'category_name' => $winner['category_name'] ?? 'Món ngon',
        'image_url' => food_image_url($winner['image']),
        'calories' => $calories,
        'protein' => $protein,
        'carbs' => $carbs,
        'fat' => $fat,
        'fiber' => $fiber,
        'serving' => ($winner['serving_size'] ? floatval($winner['serving_size']) : '1') . ' ' . ($winner['serving_unit'] ?? 'phần'),
        'description' => $winner['description'] ?? 'Món ăn dinh dưỡng được AI chọn lọc dựa trên thói quen và mục tiêu sức khỏe của bạn.',
        'ai_reasoning' => $aiReasoning,
        'detail_url' => BASE_URL . '/food-detail.php?id=' . $winner['id']
    ];

    // Format reel candidates
    $reelCandidates = [];
    foreach ($displayCandidates as $c) {
        $reelCandidates[] = [
            'id' => (int)$c['id'],
            'name' => $c['name'],
            'image_url' => food_image_url($c['image']),
            'calories' => floatval($c['calories']),
            'protein' => floatval($c['protein']),
            'category_name' => $c['category_name'] ?? 'Món ngon'
        ];
    }

    // Lưu vào bảng mystery_box_history
    $stmtInsert = $conn->prepare("INSERT INTO mystery_box_history 
        (user_id, session_id, food_id, meal_type, health_goal, budget, spin_date) 
        VALUES (:user_id, :session_id, :food_id, :meal_type, :health_goal, :budget, :spin_date)");
    $stmtInsert->execute([
        ':user_id' => $userId,
        ':session_id' => $sessionId,
        ':food_id' => $winner['id'],
        ':meal_type' => $mealType,
        ':health_goal' => $healthGoal,
        ':budget' => null,
        ':spin_date' => $today
    ]);

    // Cập nhật lượt còn lại
    $spinsToday++;
    $spinsLeft = max(0, $maxSpins - $spinsToday);

    echo json_encode([
        'success' => true,
        'winner' => $winnerData,
        'candidates' => $reelCandidates,
        'spins_left' => $spinsLeft,
        'spins_today' => $spinsToday,
        'max_spins' => $maxSpins,
        'message' => 'Mở hộp thành công!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'Đã có lỗi xảy ra: ' . $e->getMessage()
    ]);
}
