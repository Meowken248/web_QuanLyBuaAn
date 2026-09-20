<?php
// api/add_box_to_meal_log.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/MealModel.php';
require_once __DIR__ . '/../models/FoodModel.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'require_login' => true,
        'message' => 'Bạn cần đăng nhập để lưu món ăn vào Nhật ký bữa ăn cá nhân.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
if (!is_array($inputData)) {
    $inputData = $_POST;
}

$foodId = (int)($inputData['food_id'] ?? 0);
$mealType = trim($inputData['meal_type'] ?? 'lunch');
$logDate = trim($inputData['log_date'] ?? date('Y-m-d'));

// Hợp lệ hóa meal_type (Chuyển snack sang afternoon_snack để khớp DB enum)
if ($mealType === 'snack') {
    $mealType = 'afternoon_snack';
}
$validMealTypes = ['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack'];
if (!in_array($mealType, $validMealTypes, true)) {
    $mealType = 'lunch';
}

if ($foodId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Mã món ăn không hợp lệ.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $foodModel = new FoodModel();
    $food = $foodModel->getFoodById($foodId);

    if (!$food) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy thông tin món ăn.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mealModel = new MealModel();
    $userId = $_SESSION['user_id'];
    
    // Tìm hoặc tạo mới meal_log
    $mealLogId = $mealModel->getOrCreateMealLog($userId, $logDate, $mealType);
    if (!$mealLogId) {
        echo json_encode([
            'success' => false,
            'message' => 'Không thể khởi tạo nhật ký bữa ăn.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Chuẩn bị item data
    $quantity = 1.0;
    $unit = !empty($food['serving_unit']) ? $food['serving_unit'] : 'phần';
    $calculatedGrams = !empty($food['serving_size']) ? floatval($food['serving_size']) : 100.0;
    $calories = floatval($food['calories'] ?? 0);
    $protein = floatval($food['protein'] ?? 0);
    $carbs = floatval($food['carbs'] ?? 0);
    $fat = floatval($food['fat'] ?? 0);
    $fiber = floatval($food['fiber'] ?? 0);

    $itemData = [
        ':meal_log_id' => $mealLogId,
        ':food_id' => $foodId,
        ':quantity' => $quantity,
        ':unit' => $unit,
        ':calculated_grams' => $calculatedGrams,
        ':calories' => $calories,
        ':protein' => $protein,
        ':carbs' => $carbs,
        ':fat' => $fat,
        ':fiber' => $fiber
    ];

    $added = $mealModel->addMealItem($itemData);

    if ($added) {
        $mealLabels = [
            'breakfast' => 'Bữa sáng',
            'morning_snack' => 'Bữa phụ sáng',
            'lunch' => 'Bữa trưa',
            'afternoon_snack' => 'Bữa phụ chiều',
            'dinner' => 'Bữa tối',
            'evening_snack' => 'Bữa phụ tối'
        ];
        $label = $mealLabels[$mealType] ?? 'Bữa ăn';
        $logUrl = BASE_URL . '/user/meals.php?date=' . urlencode($logDate) . '#meal-' . urlencode($mealType);

        echo json_encode([
            'success' => true,
            'message' => "Đã thêm thành công \"{$food['name']}\" vào {$label} hôm nay!",
            'log_url' => $logUrl,
            'meal_type' => $mealType,
            'meal_label' => $label
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi khi lưu món ăn vào cơ sở dữ liệu.'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Đã có lỗi xảy ra: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
