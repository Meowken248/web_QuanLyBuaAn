<?php
// admin/ai-settings.php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();
$csrf_token = generate_csrf_token();

// Đảm bảo bảng system_settings đã tồn tại
$conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Xử lý AJAX Test API Key trực tiếp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_key') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Mã bảo mật CSRF không hợp lệ. Vui lòng tải lại trang!']);
        exit;
    }

    $testKey = trim($_POST['api_key'] ?? '');
    $testModel = trim($_POST['model'] ?? 'gemini-2.5-flash');

    if (empty($testKey)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập API Key để kiểm tra!']);
        exit;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($testModel) . ':generateContent';
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Trả lời "OK" nếu bạn nhận được tin nhắn này.']
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 10,
            'temperature' => 0.1
        ]
    ];

    $startTime = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $testKey
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $elapsed = round((microtime(true) - $startTime) * 1000);

    if ($curlError) {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi kết nối mạng: ' . $curlError,
            'latency' => $elapsed
        ]);
        exit;
    }

    $resData = json_decode($response, true);

    if ($httpCode === 200 && !empty($resData['candidates'][0]['content']['parts'][0]['text'])) {
        $replyText = trim($resData['candidates'][0]['content']['parts'][0]['text']);
        echo json_encode([
            'success' => true,
            'message' => "Kết nối thành công! Google Gemini phản hồi trong {$elapsed}ms. Phản hồi: \"{$replyText}\"",
            'model' => $testModel,
            'latency' => $elapsed
        ]);
    } else {
        $errMsg = $resData['error']['message'] ?? ('HTTP ' . $httpCode . ': ' . $response);
        $errStatus = $resData['error']['status'] ?? 'ERROR';
        
        $userFriendlyHint = '';
        if (strpos($errMsg, 'API_KEY_INVALID') !== false || $httpCode === 400) {
            $userFriendlyHint = ' (API Key không hợp lệ hoặc đã bị vô hiệu hóa)';
        } elseif (strpos($errMsg, 'RESOURCE_EXHAUSTED') !== false || $httpCode === 429) {
            $userFriendlyHint = ' (Key đã vượt hạn mức token / quota của Google. Hãy tạo key mới tại Google AI Studio)';
        } elseif (strpos($errMsg, 'PERMISSION_DENIED') !== false || $httpCode === 403) {
            $userFriendlyHint = ' (API Key không có quyền truy cập model này hoặc bị chặn)';
        }

        echo json_encode([
            'success' => false,
            'message' => "Lỗi ({$errStatus}): {$errMsg}{$userFriendlyHint}",
            'http_code' => $httpCode,
            'latency' => $elapsed
        ]);
    }
    exit;
}

// Xử lý Lưu cấu hình
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Phiên làm việc đã hết hạn. Vui lòng thử lại!';
        redirect('/admin/ai-settings.php');
    }

    $apiKey = trim($_POST['gemini_api_key'] ?? '');
    $model = trim($_POST['gemini_model'] ?? 'gemini-2.5-flash');

    if (empty($apiKey)) {
        $_SESSION['error'] = 'API Key không được để trống!';
        redirect('/admin/ai-settings.php');
    }

    try {
        // 1. Cập nhật vào Database table system_settings
        $stmtSave = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) 
                                    VALUES (:k, :v, :d) 
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP");
        
        $stmtSave->execute([':k' => 'gemini_api_key', ':v' => $apiKey, ':d' => 'Google Gemini API Key for Nutrition Chatbot']);
        $stmtSave->execute([':k' => 'gemini_model', ':v' => $model, ':d' => 'Gemini AI Model identifier']);

        // 2. Ghi đồng bộ vào file config/gemini.php để đảm bảo tính nhất quán tuyệt đối
        $configFile = __DIR__ . '/../config/gemini.php';
        $configContent = "<?php\n"
            . "// config/gemini.php\n\n"
            . "// Mặc định tĩnh ban đầu\n"
            . "\$defaultApiKey = " . var_export($apiKey, true) . ";\n"
            . "\$defaultModel = " . var_export($model, true) . ";\n\n"
            . "// Tự động kiểm tra trong CSDL (system_settings) để ưu tiên key mới nhất admin đã cấu hình trong trang quản trị\n"
            . "try {\n"
            . "    if (!class_exists('Database')) {\n"
            . "        \$dbFile = __DIR__ . '/database.php';\n"
            . "        if (file_exists(\$dbFile)) {\n"
            . "            require_once \$dbFile;\n"
            . "        }\n"
            . "    }\n"
            . "    if (class_exists('Database')) {\n"
            . "        \$database = new Database();\n"
            . "        \$conn = \$database->getConnection();\n"
            . "        if (\$conn) {\n"
            . "            \$stmt = \$conn->prepare(\"SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gemini_api_key', 'gemini_model')\");\n"
            . "            \$stmt->execute();\n"
            . "            \$settings = \$stmt->fetchAll(PDO::FETCH_KEY_PAIR);\n"
            . "            if (!empty(\$settings['gemini_api_key']) && trim(\$settings['gemini_api_key']) !== '') {\n"
            . "                \$defaultApiKey = trim(\$settings['gemini_api_key']);\n"
            . "            }\n"
            . "            if (!empty(\$settings['gemini_model']) && trim(\$settings['gemini_model']) !== '') {\n"
            . "                \$defaultModel = trim(\$settings['gemini_model']);\n"
            . "            }\n"
            . "        }\n"
            . "    }\n"
            . "} catch (Exception \$e) {}\n\n"
            . "if (!defined('GEMINI_API_KEY')) {\n"
            . "    define('GEMINI_API_KEY', \$defaultApiKey);\n"
            . "}\n"
            . "if (!defined('GEMINI_MODEL')) {\n"
            . "    define('GEMINI_MODEL', \$defaultModel);\n"
            . "}\n"
            . "if (!defined('GEMINI_API_URL')) {\n"
            . "    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');\n"
            . "}\n";

        @file_put_contents($configFile, $configContent);

        $_SESSION['success'] = 'Đã lưu cấu hình API Key và Model AI thành công! Chatbot đã sẵn sàng sử dụng key mới.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Lỗi lưu cấu hình: ' . $e->getMessage();
    }

    redirect('/admin/ai-settings.php');
}

// Lấy thông tin cài đặt hiện tại
require_once __DIR__ . '/../config/gemini.php';

$currentKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$currentModel = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.5-flash';
$keyUpdatedAt = null;

try {
    $stmtGet = $conn->prepare("SELECT setting_key, setting_value, updated_at FROM system_settings WHERE setting_key IN ('gemini_api_key', 'gemini_model')");
    $stmtGet->execute();
    $rows = $stmtGet->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if ($r['setting_key'] === 'gemini_api_key') {
            $currentKey = $r['setting_value'];
            $keyUpdatedAt = $r['updated_at'];
        }
        if ($r['setting_key'] === 'gemini_model') {
            $currentModel = $r['setting_value'];
        }
    }
} catch (Exception $e) {}

// Thống kê tổng số cuộc trò chuyện
$totalConvs = (int)$conn->query("SELECT COUNT(*) FROM chat_conversations")->fetchColumn();
$totalMessages = 0;
try {
    $totalMessages = (int)$conn->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();
} catch (Exception $e) {}

$current_page = 'ai-settings.php';
$page_title = 'Cấu hình API Key AI';

$modelsList = [
    [
        'id' => 'gemini-2.5-flash',
        'badge' => 'Khuyên Dùng',
        'badge_class' => 'bg-success text-white',
        'icon' => 'bi-stars',
        'icon_bg' => 'background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #15803d;',
        'title' => 'gemini-2.5-flash',
        'desc' => 'Cân bằng hoàn hảo: Tốc độ phản hồi cực nhanh, suy luận thực đơn thông minh & tối ưu hạn mức token.',
        'speed' => 'Rất nhanh',
        'quality' => 'Thông minh cao'
    ],
    [
        'id' => 'gemini-2.0-flash',
        'badge' => 'Tốc Độ Cao',
        'badge_class' => 'bg-info text-dark',
        'icon' => 'bi-lightning-charge-fill',
        'icon_bg' => 'background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%); color: #0e7490;',
        'title' => 'gemini-2.0-flash',
        'desc' => 'Tối ưu độ trễ thấp cực điểm. Phản hồi tức thì, độ ổn định tuyệt vời khi trò chuyện liên tục.',
        'speed' => 'Cực nhanh',
        'quality' => 'Ổn định cao'
    ],
    [
        'id' => 'gemini-3.1-flash-lite',
        'badge' => 'Mới Nhất',
        'badge_class' => 'bg-purple text-white',
        'icon' => 'bi-fire',
        'icon_bg' => 'background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); color: #7e22ce;',
        'title' => 'gemini-3.1-flash-lite',
        'desc' => 'Thế hệ Gemini mới nhất từ Google, siêu nhẹ, tiết kiệm tối đa chi phí quota cho câu hỏi đáp.',
        'speed' => 'Nhanh nhẹ',
        'quality' => 'Tiết kiệm token'
    ],
    [
        'id' => 'gemini-1.5-flash',
        'badge' => 'Bản Chuẩn',
        'badge_class' => 'bg-secondary text-white',
        'icon' => 'bi-shield-check',
        'icon_bg' => 'background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #475569;',
        'title' => 'gemini-1.5-flash',
        'desc' => 'Bản ổn định truyền thống lâu đời, hỗ trợ rộng rãi trên mọi loại tài khoản Google AI Studio.',
        'speed' => 'Nhanh',
        'quality' => 'Tương thích cao'
    ],
    [
        'id' => 'gemini-1.5-pro',
        'badge' => 'Chuyên Sâu',
        'badge_class' => 'bg-warning text-dark',
        'icon' => 'bi-cpu-fill',
        'icon_bg' => 'background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #b45309;',
        'title' => 'gemini-1.5-pro',
        'desc' => 'Khả năng tư duy và phân tích bữa ăn chuyên sâu. Lưu ý tiêu thụ nhiều token hơn các bản flash.',
        'speed' => 'Trung bình',
        'quality' => 'Suy luận sâu'
    ],
    [
        'id' => 'custom',
        'badge' => 'Tùy Chỉnh',
        'badge_class' => 'bg-dark text-white',
        'icon' => 'bi-gear-wide-connected',
        'icon_bg' => 'background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%); color: #1e293b;',
        'title' => 'Mô hình khác...',
        'desc' => 'Dành cho quản trị viên muốn nhập trực tiếp tên model khác (ví dụ: các bản experimental mới).',
        'speed' => 'Tùy chọn',
        'quality' => 'Tự cấu hình'
    ]
];

$standardModelIds = array_slice(array_column($modelsList, 'id'), 0, 5);
$isCustomModel = !in_array($currentModel, $standardModelIds);
$activeModelId = $isCustomModel ? 'custom' : $currentModel;
$customModelVal = $isCustomModel ? $currentModel : '';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern Model Selection & AI Settings Styles */
.bg-purple {
    background-color: #9333ea !important;
}
.text-purple {
    color: #9333ea !important;
}

.ai-settings-card {
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
    background: #ffffff;
}

.model-card-item {
    position: relative;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    height: 100%;
    user-select: none;
}

.model-card-item:hover {
    border-color: #94a3b8;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
}

.model-card-item.selected-model-card {
    border-color: #10b981 !important;
    background: linear-gradient(160deg, #f0fdf4 0%, #ffffff 100%) !important;
    box-shadow: 0 10px 25px -4px rgba(16, 185, 129, 0.2), 0 0 0 1px #10b981 !important;
}

.model-card-item .check-indicator {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    transition: all 0.2s ease;
    background: #ffffff;
    flex-shrink: 0;
}

.model-card-item.selected-model-card .check-indicator {
    border-color: #10b981;
    background: #10b981;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.4);
}

.model-icon-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Seamless API Key Input Bar */
.api-key-container {
    border: 1.5px solid #cbd5e1;
    border-radius: 14px;
    background: #ffffff;
    padding: 0.5rem 0.85rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.api-key-container:focus-within {
    border-color: #10b981 !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15) !important;
}

.api-key-icon-wrapper {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #fefce8;
    color: #eab308;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.api-key-input-field {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0.35rem 0.75rem !important;
    font-size: 1.05rem;
    color: #1e293b;
    letter-spacing: 0.5px;
}

.api-key-input-field::placeholder {
    color: #94a3b8;
    font-size: 0.9rem;
    letter-spacing: normal;
}

.btn-icon-action {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.18s ease;
    cursor: pointer;
    font-size: 1.05rem;
}

.btn-icon-action:hover {
    background: #f1f5f9;
    color: #0f172a;
    transform: scale(1.06);
}

.btn-icon-action:active {
    transform: scale(0.96);
}

.btn-icon-action.copied {
    background: #dcfce7 !important;
    color: #16a34a !important;
}

.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-2px);
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2">
            <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <!-- Header Section -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h2 class="fw-bold mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-key-fill text-warning"></i> Cấu hình API Key Chatbot AI
                    </h2>
                    <p class="text-muted mb-0 small">
                        Thay đổi API Key Google Gemini nhanh chóng tại đây mỗi khi hết token hoặc muốn đổi Model AI.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo BASE_URL; ?>/admin/chat-logs.php" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
                        <i class="bi bi-journal-text me-1"></i> Xem Lịch sử Chatbot
                    </a>
                </div>
            </div>

            <!-- Flash Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Status Overview Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 p-3 hover-lift" style="background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 100%); border: 1px solid #d1fae5 !important;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px; font-size: 1.3rem;">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Trạng thái API Key</div>
                                <div class="fw-bold fs-6 mt-1">
                                    <?php if (!empty($currentKey)): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                            <i class="bi bi-check2-circle me-1"></i>Đã cấu hình
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1">
                                            <i class="bi bi-x-circle me-1"></i>Chưa có Key
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 p-3 hover-lift" style="background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%); border: 1px solid #dbeafe !important;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px; font-size: 1.3rem;">
                                <i class="bi bi-cpu"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Mô hình AI đang kích hoạt</div>
                                <div class="fw-bold text-dark fs-6 mt-1">
                                    <code id="currentModelDisplay" class="bg-white px-2 py-1 rounded-2 border"><?php echo htmlspecialchars($currentModel); ?></code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 p-3 hover-lift" style="background: linear-gradient(135deg, #fefce8 0%, #ffffff 100%); border: 1px solid #fef08a !important;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px; font-size: 1.3rem;">
                                <i class="bi bi-chat-quote-fill"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Tổng hội thoại Chatbot</div>
                                <div class="fw-bold text-dark fs-5 mt-1">
                                    <?php echo number_format($totalConvs); ?> <small class="text-muted fs-6 fw-normal">cuộc trò chuyện</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Form Card -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-sliders2 text-success"></i> Thiết lập Khóa API &amp; Mô Hình Gemini
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?php echo BASE_URL; ?>/admin/ai-settings.php" method="POST" id="formAiSettings">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" id="csrfToken">
                        <input type="hidden" name="action" value="save_settings">

                        <!-- API Key Input Field -->
                        <div class="mb-4">
                            <label for="geminiApiKey" class="form-label fw-bold text-dark fs-6 mb-2">
                                Google Gemini API Key <span class="text-danger">*</span>
                            </label>
                            
                            <div class="api-key-container d-flex align-items-center">
                                <div class="api-key-icon-wrapper me-2">
                                    <i class="bi bi-key-fill"></i>
                                </div>
                                
                                <input type="password" 
                                       class="form-control api-key-input-field font-monospace flex-grow-1" 
                                       id="geminiApiKey" 
                                       name="gemini_api_key" 
                                       placeholder="Dán mã API Key của bạn vào đây (vd: AQ.Ab8... hoặc AIzaSy...)" 
                                       value="<?php echo htmlspecialchars($currentKey); ?>" 
                                       required 
                                       autocomplete="off">
                                
                                <div class="d-flex align-items-center gap-1 ms-2 border-start ps-2">
                                    <button class="btn-icon-action" type="button" id="btnToggleKey" title="Hiện / Ẩn API Key">
                                        <i class="bi bi-eye-slash" id="iconEye"></i>
                                    </button>
                                    <button class="btn-icon-action" type="button" id="btnCopyKey" title="Sao chép API Key">
                                        <i class="bi bi-clipboard" id="iconCopy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                <div class="form-text text-muted small mb-0">
                                    <i class="bi bi-info-circle me-1"></i> Khóa này dùng để kết nối trực tiếp với Google Generative AI (Gemini Studio).
                                    <?php if ($keyUpdatedAt): ?>
                                        • Cập nhật lần cuối: <strong><?php echo date('H:i d/m/Y', strtotime($keyUpdatedAt)); ?></strong>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" class="text-success text-decoration-none small fw-bold">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Lấy Key mới tại Google AI Studio
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Modern Model Selection Grid -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <label class="form-label fw-bold text-dark fs-6 mb-0">
                                    <i class="bi bi-cpu-fill text-success me-1"></i> Chọn Mô hình AI (Gemini Model) <span class="text-danger">*</span>
                                </label>
                                <span class="badge bg-light text-muted border rounded-pill px-3 py-1 small">
                                    Đang chọn: <strong id="selectedModelBadge" class="text-success"><?php echo htmlspecialchars($currentModel); ?></strong>
                                </span>
                            </div>
                            
                            <!-- Hidden input to submit with the form & read by test key script -->
                            <input type="hidden" name="gemini_model" id="geminiModel" value="<?php echo htmlspecialchars($currentModel); ?>">

                            <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-3 mt-1">
                                <?php foreach ($modelsList as $m): ?>
                                    <?php 
                                        $isSelected = ($activeModelId === $m['id']); 
                                    ?>
                                    <div class="col">
                                        <div class="model-card-item <?php echo $isSelected ? 'selected-model-card' : ''; ?>" 
                                             data-model-id="<?php echo htmlspecialchars($m['id']); ?>"
                                             onclick="selectModelCard('<?php echo htmlspecialchars($m['id']); ?>')">
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="model-icon-avatar shadow-xs" style="<?php echo $m['icon_bg']; ?>">
                                                        <i class="bi <?php echo $m['icon']; ?>"></i>
                                                    </div>
                                                    <span class="badge <?php echo $m['badge_class']; ?> rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem; letter-spacing: 0.3px;">
                                                        <?php echo $m['badge']; ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="check-indicator">
                                                    <i class="bi bi-check-lg fw-bold"></i>
                                                </div>
                                            </div>

                                            <div class="fw-bold font-monospace text-dark fs-6 mb-1">
                                                <?php echo $m['title']; ?>
                                            </div>

                                            <p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.83rem; line-height: 1.45;">
                                                <?php echo $m['desc']; ?>
                                            </p>

                                            <div class="d-flex gap-1 flex-wrap pt-2 border-top border-light-subtle">
                                                <span class="badge bg-light text-secondary border rounded-pill small py-1 px-2">
                                                    <i class="bi bi-speedometer2 text-success me-1"></i><?php echo $m['speed']; ?>
                                                </span>
                                                <span class="badge bg-light text-secondary border rounded-pill small py-1 px-2">
                                                    <i class="bi bi-award text-primary me-1"></i><?php echo $m['quality']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Custom Model Input Expansion -->
                            <div id="customModelWrapper" class="mt-3 <?php echo ($activeModelId === 'custom') ? '' : 'd-none'; ?>">
                                <div class="p-3 rounded-4" style="background: #f8fafc; border: 1.5px dashed #94a3b8;">
                                    <label for="customModelInput" class="form-label fw-bold text-dark small mb-1">
                                        <i class="bi bi-pencil-square text-primary me-1"></i> Nhập chính xác mã mô hình Google Gemini bạn muốn cấu hình:
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted font-monospace small">models/</span>
                                        <input type="text" 
                                               class="form-control font-monospace border-start-0" 
                                               id="customModelInput" 
                                               placeholder="Ví dụ: gemini-2.0-pro-exp-02-05 hoặc gemini-1.5-flash-8b"
                                               value="<?php echo htmlspecialchars($customModelVal); ?>">
                                    </div>
                                    <div class="form-text text-muted small mt-1">
                                        <i class="bi bi-info-circle me-1"></i> Đảm bảo mã model chính xác theo tài liệu Google Gemini API và tài khoản của bạn có quyền sử dụng.
                                    </div>
                                </div>
                            </div>

                            <div class="form-text text-muted small mt-2 d-flex align-items-center gap-1">
                                <i class="bi bi-shield-check text-success"></i>
                                <span>Gợi ý: Hãy chọn <code>gemini-2.5-flash</code> hoặc <code>gemini-2.0-flash</code> để đạt tốc độ phản hồi và sự ổn định cao nhất.</span>
                            </div>
                        </div>

                        <!-- Live Test Results Container -->
                        <div id="testResultBox" class="mb-4 d-none"></div>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-success btn-lg rounded-pill px-4 fw-bold shadow-sm hover-lift">
                                <i class="bi bi-floppy-fill me-2"></i> Lưu Cấu Hình Key
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-lg rounded-pill px-4 fw-bold shadow-sm hover-lift" id="btnTestKey">
                                <i class="bi bi-lightning-charge-fill me-2"></i> Kiểm Tra Kết Nối (Test Key)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Guide Card: How to get key -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-light py-3 px-4 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-question-circle text-primary me-2"></i>Hướng dẫn đổi API Key miễn phí khi hết token
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1.5px dashed #cbd5e1;">
                                <div class="badge bg-primary rounded-pill mb-2">Bước 1</div>
                                <h6 class="fw-bold text-dark">Mở Google AI Studio</h6>
                                <p class="text-muted small mb-2">
                                    Truy cập trang quản lý API Key của Google bằng tài khoản Gmail của bạn.
                                </p>
                                <a href="https://aistudio.google.com/app/apikey" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill fw-bold">
                                    Mở AI Studio <i class="bi bi-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1.5px dashed #cbd5e1;">
                                <div class="badge bg-success rounded-pill mb-2">Bước 2</div>
                                <h6 class="fw-bold text-dark">Tạo Key mới</h6>
                                <p class="text-muted small mb-0">
                                    Bấm vào nút <strong>"Create API key"</strong> màu xanh. Chọn một Google Cloud Project bất kỳ (hoặc tạo project mới chỉ với 1 click). Copy mã key hiển thị ra.
                                </p>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1.5px dashed #cbd5e1;">
                                <div class="badge bg-warning text-dark rounded-pill mb-2">Bước 3</div>
                                <h6 class="fw-bold text-dark">Dán vào đây &amp; Bấm Lưu</h6>
                                <p class="text-muted small mb-0">
                                    Dán key mới vào ô bên trên, bấm <strong>"Kiểm tra kết nối"</strong> để xác nhận key còn sống, sau đó bấm <strong>"Lưu Cấu Hình Key"</strong>. Người dùng sẽ chat lại bình thường ngay lập tức!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Function chọn Model Card tương tác
function selectModelCard(modelId) {
    const cards = document.querySelectorAll('.model-card-item');
    cards.forEach(card => card.classList.remove('selected-model-card'));

    const targetCard = document.querySelector(`.model-card-item[data-model-id="${modelId}"]`);
    if (targetCard) {
        targetCard.classList.add('selected-model-card');
    }

    const hiddenInput = document.getElementById('geminiModel');
    const customWrapper = document.getElementById('customModelWrapper');
    const customInput = document.getElementById('customModelInput');
    const currentModelDisplay = document.getElementById('currentModelDisplay');
    const selectedModelBadge = document.getElementById('selectedModelBadge');

    if (modelId === 'custom') {
        if (customWrapper) customWrapper.classList.remove('d-none');
        if (customInput) {
            customInput.focus();
            const val = customInput.value.trim();
            hiddenInput.value = val || 'gemini-2.5-flash';
        }
    } else {
        if (customWrapper) customWrapper.classList.add('d-none');
        hiddenInput.value = modelId;
    }

    if (currentModelDisplay) {
        currentModelDisplay.textContent = hiddenInput.value;
    }
    if (selectedModelBadge) {
        selectedModelBadge.textContent = hiddenInput.value;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const inputKey = document.getElementById('geminiApiKey');
    const selectModel = document.getElementById('geminiModel');
    const btnToggle = document.getElementById('btnToggleKey');
    const iconEye = document.getElementById('iconEye');
    const btnCopy = document.getElementById('btnCopyKey');
    const btnTest = document.getElementById('btnTestKey');
    const testResultBox = document.getElementById('testResultBox');
    const csrfToken = document.getElementById('csrfToken').value;
    const customInput = document.getElementById('customModelInput');

    // Lắng nghe gõ tên model tùy chỉnh
    if (customInput) {
        customInput.addEventListener('input', function() {
            const val = this.value.trim();
            const hiddenInput = document.getElementById('geminiModel');
            hiddenInput.value = val || 'gemini-2.5-flash';
            const currentModelDisplay = document.getElementById('currentModelDisplay');
            const selectedModelBadge = document.getElementById('selectedModelBadge');
            if (currentModelDisplay) {
                currentModelDisplay.textContent = hiddenInput.value;
            }
            if (selectedModelBadge) {
                selectedModelBadge.textContent = hiddenInput.value;
            }
        });
    }

    // Toggle Show/Hide Key
    btnToggle.addEventListener('click', function() {
        if (inputKey.type === 'password') {
            inputKey.type = 'text';
            iconEye.classList.remove('bi-eye-slash');
            iconEye.classList.add('bi-eye');
        } else {
            inputKey.type = 'password';
            iconEye.classList.remove('bi-eye');
            iconEye.classList.add('bi-eye-slash');
        }
    });

    // Copy Key to Clipboard
    btnCopy.addEventListener('click', function() {
        if (!inputKey.value) {
            alert('Chưa có API Key để sao chép!');
            return;
        }
        navigator.clipboard.writeText(inputKey.value).then(function() {
            btnCopy.classList.add('copied');
            btnCopy.innerHTML = '<i class="bi bi-check-lg fw-bold"></i>';
            setTimeout(() => {
                btnCopy.classList.remove('copied');
                btnCopy.innerHTML = '<i class="bi bi-clipboard" id="iconCopy"></i>';
            }, 1800);
        }).catch(function() {
            alert('Không thể sao chép tự động, vui lòng bôi đen và copy thủ công.');
        });
    });

    // Live Test Key via AJAX
    btnTest.addEventListener('click', function() {
        const key = inputKey.value.trim();
        const model = selectModel.value.trim() || 'gemini-2.5-flash';

        if (!key) {
            alert('Vui lòng nhập API Key trước khi kiểm tra!');
            inputKey.focus();
            return;
        }

        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Đang kết nối thử với Google...';
        testResultBox.classList.remove('d-none');
        testResultBox.innerHTML = `
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-2 mb-0 rounded-3">
                <span class="spinner-border spinner-border-sm"></span>
                <span>Đang gửi gói tin ping kiểm tra tới Google Gemini API (Model: <strong>${model}</strong>)...</span>
            </div>
        `;

        const formData = new FormData();
        formData.append('action', 'test_key');
        formData.append('csrf_token', csrfToken);
        formData.append('api_key', key);
        formData.append('model', model);

        fetch('<?php echo BASE_URL; ?>/admin/ai-settings.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="bi bi-lightning-charge-fill me-2"></i> Kiểm Tra Kết Nối (Test Key)';

            if (data.success) {
                testResultBox.innerHTML = `
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-start gap-3 mb-0 rounded-4 p-3">
                        <i class="bi bi-check-circle-fill fs-4 text-success flex-shrink-0"></i>
                        <div>
                            <div class="fw-bold fs-6">Kiểm tra thành công! Key hoàn toàn hợp lệ.</div>
                            <div class="small mt-1 text-dark">${data.message}</div>
                            <div class="small text-muted mt-1">Độ trễ phản hồi: <strong>${data.latency}ms</strong> • Bạn có thể bấm <strong>"Lưu Cấu Hình Key"</strong> bên dưới để áp dụng ngay.</div>
                        </div>
                    </div>
                `;
            } else {
                testResultBox.innerHTML = `
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-3 mb-0 rounded-4 p-3">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-danger flex-shrink-0"></i>
                        <div>
                            <div class="fw-bold fs-6">Kiểm tra kết nối thất bại!</div>
                            <div class="small mt-1">${data.message}</div>
                            <div class="small mt-2">Gợi ý khắc phục: Kiểm tra xem key có bị dán thừa khoảng trắng không, hoặc tạo một key mới tại <a href="https://aistudio.google.com/app/apikey" target="_blank" class="fw-bold text-decoration-underline text-danger">Google AI Studio</a>.</div>
                        </div>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error(err);
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="bi bi-lightning-charge-fill me-2"></i> Kiểm Tra Kết Nối (Test Key)';
            testResultBox.innerHTML = `
                <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2 mb-0 rounded-3">
                    <i class="bi bi-x-circle-fill"></i>
                    <span>Lỗi gửi yêu cầu kiểm tra tới máy chủ. Vui lòng kiểm tra lại kết nối mạng.</span>
                </div>
            `;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
