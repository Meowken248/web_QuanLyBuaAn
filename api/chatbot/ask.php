<?php
// api/chatbot/ask.php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/gemini.php';
require_once __DIR__ . '/../../models/ProfileModel.php';
require_once __DIR__ . '/../../models/MealModel.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$question = trim($data['question'] ?? '');
$conversation_id = isset($data['conversation_id']) && $data['conversation_id'] > 0 ? (int)$data['conversation_id'] : null;

if (empty($question)) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập câu hỏi.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getConnection();

// Xử lý Conversation ID
if (!$conversation_id) {
    // Tạo cuộc hội thoại mới
    $title = mb_substr($question, 0, 50) . (mb_strlen($question) > 50 ? '...' : '');
    $stmtConv = $conn->prepare("INSERT INTO chat_conversations (user_id, title) VALUES (:user_id, :title)");
    $stmtConv->execute([':user_id' => $user_id, ':title' => $title]);
    $conversation_id = $conn->lastInsertId();
} else {
    // Cập nhật updated_at
    $stmtUpd = $conn->prepare("UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id");
    $stmtUpd->execute([':id' => $conversation_id, ':user_id' => $user_id]);
}

// Lưu câu hỏi của User
$stmtMsg = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender, message, tokens_used) VALUES (:conv_id, 'user', :msg, 0)");
$stmtMsg->execute([':conv_id' => $conversation_id, ':msg' => $question]);

// Lấy lịch sử chat của conversation này để làm context (Tối đa 10 tin nhắn gần nhất)
$stmtHistory = $conn->prepare("SELECT sender, message FROM chat_messages WHERE conversation_id = :conv_id ORDER BY id ASC LIMIT 10");
$stmtHistory->execute([':conv_id' => $conversation_id]);
$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// Lấy context sức khỏe
$profileModel = new ProfileModel();
$profile = $profileModel->getProfileByUserId($user_id);
$mealModel = new MealModel();
$nutrition = $mealModel->getDailyNutrition($user_id, date('Y-m-d'));

// Xây dựng System Prompt
$system_prompt = "Bạn là một trợ lý AI chuyên về dinh dưỡng. Tư vấn ngắn gọn, thân thiện, dễ hiểu.\n";
$system_prompt .= "Tuyệt đối không chẩn đoán bệnh. Khuyến nghị gặp bác sĩ nếu bệnh lý nghiêm trọng.\n";

if ($profile) {
    $system_prompt .= "\nTHÔNG TIN USER:\n";
    $system_prompt .= "- Cân nặng: {$profile['current_weight']}kg\n";
    $system_prompt .= "- Mục tiêu: {$profile['health_goal']}, Calo/ngày: {$profile['calorie_target']}kcal\n";
    $cal_left = $profile['calorie_target'] - $nutrition['calories'];
    $system_prompt .= "- Hôm nay đã nạp: {$nutrition['calories']}kcal. Còn lại: {$cal_left}kcal.\n";
}

$contents = [
    [
        "role" => "user",
        "parts" => [
            ["text" => "--- SYSTEM INSTRUCTIONS ---\n$system_prompt\n--- END SYSTEM INSTRUCTIONS ---"]
        ]
    ],
    [
        "role" => "model",
        "parts" => [
            ["text" => "Tôi đã hiểu thông tin hệ thống và thông tin người dùng. Tôi sẵn sàng trả lời."]
        ]
    ]
];

// Nạp lịch sử
foreach ($history as $h) {
    if ($h['sender'] === 'system') continue;
    $role = $h['sender'] === 'user' ? 'user' : 'model';
    $contents[] = [
        "role" => $role,
        "parts" => [
            ["text" => $h['message']]
        ]
    ];
}

$payload = [
    "contents" => $contents,
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_URL, GEMINI_API_URL . '?key=' . urlencode(GEMINI_API_KEY));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

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
        
        // Lưu câu trả lời của Bot
        $stmtBot = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender, message, tokens_used) VALUES (:conv_id, 'assistant', :msg, 0)");
        $stmtBot->execute([':conv_id' => $conversation_id, ':msg' => $answer]);

        $answerHtml = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $answer);
        $answerHtml = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $answerHtml);
        $answerHtml = nl2br($answerHtml);
        
        echo json_encode([
            'status' => 'success', 
            'answer' => $answerHtml,
            'conversation_id' => $conversation_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI trả về dữ liệu không hợp lệ.']);
    }
} else {
    $result = json_decode($response, true);
    $error_msg = isset($result['error']['message']) ? $result['error']['message'] : 'Lỗi từ API';
    if ($http_code === 404) $error_msg = 'Model AI hiện không khả dụng.';
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
}
