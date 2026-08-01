<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/gemini.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/ProfileModel.php';
require_once __DIR__ . '/../../models/MealModel.php';

header('Content-Type: application/json; charset=utf-8');

function chatbot_response($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function local_nutrition_answer($question, $profile, $nutrition) {
    $q = mb_strtolower($question);
    $target = (float)($profile['calorie_target'] ?? 2000);
    $used = (float)($nutrition['calories'] ?? 0);
    $left = round($target - $used);

    if (str_contains($q, 'còn bao nhiêu') || str_contains($q, 'calo hôm nay') || str_contains($q, 'calories hôm nay')) {
        return "Hôm nay bạn đã nạp khoảng **" . round($used) . " kcal**, còn **{$left} kcal** so với mục tiêu **" . round($target) . " kcal**.";
    }
    if (str_contains($q, 'giảm cân') || str_contains($q, 'thực đơn')) {
        return "Gợi ý nguyên tắc cho bữa ăn giảm cân:\n- 1/2 đĩa là rau củ.\n- 1/4 là đạm nạc như ức gà, cá, trứng hoặc đậu hũ.\n- 1/4 là tinh bột ít tinh chế như gạo lứt hoặc khoai.\n- Ưu tiên nước lọc và theo dõi tổng calories trong nhật ký.";
    }
    if (str_contains($q, 'tăng cân')) {
        return "Để tăng cân lành mạnh, hãy tăng năng lượng từ từ khoảng **300–500 kcal/ngày**, ưu tiên đạm, tinh bột nguyên cám, sữa, hạt và tập sức mạnh. Theo dõi cân nặng mỗi tuần để điều chỉnh.";
    }
    if (str_contains($q, 'protein') || str_contains($q, 'tập gym') || str_contains($q, 'sau tập')) {
        return "Sau tập, bạn có thể kết hợp **20–30 g protein** với một nguồn carbohydrate: sữa chua và chuối, trứng với bánh mì nguyên cám, hoặc ức gà với cơm. Uống đủ nước và điều chỉnh theo mục tiêu cá nhân.";
    }
    if (str_contains($q, '500')) {
        return "Một số bữa dưới 500 kcal: ức gà + khoai lang + rau luộc; cá áp chảo + cơm gạo lứt + salad; hoặc đậu hũ + rau củ xào ít dầu. Hãy kiểm tra khẩu phần trong thư viện món ăn.";
    }
    return "Mình có thể hỗ trợ về calories hôm nay, gợi ý bữa ăn, giảm cân, tăng cân, protein và dinh dưỡng sau tập. Bạn hãy cho mình biết mục tiêu hoặc món ăn cụ thể nhé.";
}

if (!isset($_SESSION['user_id'])) chatbot_response(['status' => 'error', 'message' => 'Vui lòng đăng nhập lại.'], 401);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) chatbot_response(['status' => 'error', 'message' => 'Dữ liệu gửi lên không hợp lệ.'], 400);
if (!verify_csrf_token($data['csrf_token'] ?? '')) chatbot_response(['status' => 'error', 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 419);

$question = trim($data['question'] ?? '');
if ($question === '') chatbot_response(['status' => 'error', 'message' => 'Vui lòng nhập câu hỏi.'], 422);
if (mb_strlen($question) > 1000) chatbot_response(['status' => 'error', 'message' => 'Câu hỏi không được vượt quá 1.000 ký tự.'], 422);

$user_id = (int)$_SESSION['user_id'];
$conn = (new Database())->getConnection();
$conversation_id = filter_var($data['conversation_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

if ($conversation_id) {
    $owner = $conn->prepare('SELECT id FROM chat_conversations WHERE id = :id AND user_id = :user_id');
    $owner->execute([':id' => $conversation_id, ':user_id' => $user_id]);
    if (!$owner->fetchColumn()) $conversation_id = null;
}
if (!$conversation_id) {
    $title = mb_substr($question, 0, 50) . (mb_strlen($question) > 50 ? '...' : '');
    $stmt = $conn->prepare('INSERT INTO chat_conversations (user_id, title) VALUES (:user_id, :title)');
    $stmt->execute([':user_id' => $user_id, ':title' => $title]);
    $conversation_id = (int)$conn->lastInsertId();
} else {
    $conn->prepare('UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id')
         ->execute([':id' => $conversation_id, ':user_id' => $user_id]);
}

$conn->prepare("INSERT INTO chat_messages (conversation_id,sender,message,tokens_used) VALUES (:id,'user',:message,0)")
     ->execute([':id' => $conversation_id, ':message' => $question]);

$profile = (new ProfileModel())->getProfileByUserId($user_id);
$nutrition = (new MealModel())->getDailyNutrition($user_id, date('Y-m-d'));
$answer = null;
$used_fallback = false;

if (GEMINI_API_KEY !== '') {
    $system = "Bạn là trợ lý dinh dưỡng tiếng Việt. Trả lời ngắn gọn, dễ hiểu; không chẩn đoán bệnh hoặc kê đơn.";
    if ($profile) {
        $system .= "\nNgười dùng nặng {$profile['current_weight']} kg, mục tiêu {$profile['health_goal']}, mục tiêu năng lượng {$profile['calorie_target']} kcal/ngày.";
        if (!empty($profile['allergies'])) $system .= "\nDị ứng: {$profile['allergies']}.";
    }
    $payload = [
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 800]
    ];

    $ch = curl_init(GEMINI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_errno($ch);
    curl_close($ch);

    if (!$curl_error && $http_code === 200) {
        $result = json_decode($response, true);
        $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}
if (!$answer) {
    $answer = local_nutrition_answer($question, $profile, $nutrition);
    $used_fallback = true;
}

$conn->prepare("INSERT INTO chat_messages (conversation_id,sender,message,tokens_used) VALUES (:id,'assistant',:message,0)")
     ->execute([':id' => $conversation_id, ':message' => $answer]);

$safe = htmlspecialchars($answer, ENT_QUOTES, 'UTF-8');
$safe = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $safe);
$safe = nl2br($safe);

chatbot_response([
    'status' => 'success',
    'answer' => $safe,
    'conversation_id' => $conversation_id,
    'source' => $used_fallback ? 'local' : 'gemini'
]);
