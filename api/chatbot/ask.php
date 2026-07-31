<?php
// api/chatbot/ask.php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/gemini.php';
require_once __DIR__ . '/../../models/ProfileModel.php';
require_once __DIR__ . '/../../models/MealModel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$question = trim($data['question'] ?? '');

if (empty($question)) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập câu hỏi.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy context
$profileModel = new ProfileModel();
$profile = $profileModel->getProfileByUserId($user_id);

$mealModel = new MealModel();
$today = date('Y-m-d');
$nutrition = $mealModel->getDailyNutrition($user_id, $today);

// Xây dựng System Prompt
$system_prompt = "Bạn là một trợ lý AI chuyên về dinh dưỡng và sức khỏe cá nhân. Bạn cần tư vấn ngắn gọn, thân thiện và dễ hiểu.\n";
$system_prompt .= "CẢNH BÁO QUAN TRỌNG: Chỉ tư vấn thông tin chung. Tuyệt đối không chẩn đoán bệnh, không kê đơn, không yêu cầu người dùng ngừng thuốc. Khuyến nghị gặp bác sĩ nếu liên quan đến bệnh lý nghiêm trọng.\n";

if ($profile) {
    $system_prompt .= "\nTHÔNG TIN NGƯỜI DÙNG HIỆN TẠI:\n";
    $system_prompt .= "- Giới tính: " . ($profile['gender'] == 'male' ? 'Nam' : 'Nữ') . "\n";
    $system_prompt .= "- Chiều cao: {$profile['height']} cm, Cân nặng: {$profile['current_weight']} kg\n";
    $system_prompt .= "- Mục tiêu: {$profile['health_goal']}\n";
    $system_prompt .= "- Calories mục tiêu hôm nay: {$profile['calorie_target']} kcal\n";
    if (!empty($profile['allergies'])) $system_prompt .= "- Dị ứng: {$profile['allergies']}\n";
    if (!empty($profile['diet_type'])) $system_prompt .= "- Chế độ ăn: {$profile['diet_type']}\n";
    
    $cal_left = $profile['calorie_target'] - $nutrition['calories'];
    $system_prompt .= "- Hôm nay đã ăn: {$nutrition['calories']} kcal. Còn lại: {$cal_left} kcal.\n";
}

$payload = [
    "system_instruction" => [
        "parts" => [
            ["text" => $system_prompt]
        ]
    ],
    "contents" => [
        [
            "parts" => [
                ["text" => $question]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối AI: ' . curl_error($ch)]);
    curl_close($ch);
    exit();
}

curl_close($ch);

if ($http_code == 200) {
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $result['candidates'][0]['content']['parts'][0]['text'];
        
        // Convert Markdown to HTML basic
        $answer = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $answer);
        $answer = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $answer);
        $answer = nl2br($answer);
        
        echo json_encode(['status' => 'success', 'answer' => $answer]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI trả về dữ liệu không hợp lệ.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi từ Gemini API: ' . $http_code]);
}
