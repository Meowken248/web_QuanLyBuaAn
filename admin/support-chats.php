<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SESSION['user_role'] !== 'admin') {
    redirect('/index.php');
}

$db = new Database();
$conn = $db->getConnection();

$page_title = 'Hỗ trợ trực tuyến';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';

$target_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$target_user = null;
if ($target_user_id) {
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
    $stmt->execute([':id' => $target_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch chats with their latest message
$stmt = $conn->query("
    SELECT sc.id, sc.user_id, sc.status, u.full_name, u.email,
           (SELECT message FROM support_messages WHERE chat_id = sc.id ORDER BY id DESC LIMIT 1) as last_message,
           (SELECT created_at FROM support_messages WHERE chat_id = sc.id ORDER BY id DESC LIMIT 1) as last_time,
           (SELECT COUNT(id) FROM support_messages WHERE chat_id = sc.id AND sender_type = 'user' AND is_read = 0) as unread_count
    FROM support_chats sc
    JOIN users u ON sc.user_id = u.id
    ORDER BY last_time DESC, sc.created_at DESC
");
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4 h-100">
    <div class="row h-100">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10 d-flex flex-column" style="height: calc(100vh - 130px);">
            <div class="row h-100 g-0 card shadow border-0 mt-2 mb-3 flex-row overflow-hidden">
                <!-- Left Sidebar: Chat List -->
                <div class="col-md-4 border-end bg-white d-flex flex-column h-100">
                    <div class="card-header bg-light border-bottom py-3 d-flex align-items-center rounded-0" style="min-height: 73px;">
                        <div>
                            <h5 class="mb-0 fw-bold">Danh sách hội thoại</h5>
                            <small class="text-muted">Quản lý tin nhắn khách hàng</small>
                        </div>
                    </div>
                    <div class="list-group list-group-flush flex-grow-1 overflow-auto" style="background-color: #ffffff;">
                        <?php if (empty($chats)): ?>
                            <div class="text-center py-4 text-muted small">Chưa có cuộc hội thoại nào.</div>
                        <?php else: ?>
                            <?php foreach ($chats as $chat): ?>
                                <?php $isActive = ($target_user_id == $chat['user_id']); ?>
                                <a href="<?php echo BASE_URL; ?>/admin/support-chats.php?user_id=<?php echo $chat['user_id']; ?>" 
                                   class="list-group-item list-group-item-action p-3 <?php echo $isActive ? 'active text-white' : ''; ?>" style="background-color: #53853fff;">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h6 class="mb-0 fw-bold text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($chat['full_name']); ?></h6>
                                        <small class="<?php echo $isActive ? 'text-white-50' : 'text-muted'; ?>">
                                            <?php echo $chat['last_time'] ? date('H:i d/m', strtotime($chat['last_time'])) : ''; ?>
                                        </small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-truncate small <?php echo $isActive ? 'text-white' : 'text-secondary'; ?>" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($chat['last_message'] ?: 'Chưa có tin nhắn'); ?>
                                        </div>
                                        <?php if ($chat['unread_count'] > 0): ?>
                                            <span class="badge <?php echo $isActive ? 'bg-light text-success' : 'bg-danger'; ?> rounded-pill"><?php echo $chat['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Side: Chat View -->
                <div class="col-md-8 d-flex flex-column h-100">
                    <?php if ($target_user): ?>
                        <div class="card-header bg-success text-white py-3 d-flex align-items-center border-0 rounded-0" style="min-height: 73px;">
                            <i class="bi bi-person-circle fs-4 me-2"></i>
                            <div>
                                <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($target_user['full_name']); ?></h5>
                                <small class="text-white-50">Đang hỗ trợ trực tuyến</small>
                            </div>
                        </div>

                        <div class="card-body bg-light overflow-auto p-4 flex-grow-1" id="support-chat-box">
                            <div class="text-center mb-4 text-muted small">
                                Cuộc trò chuyện bắt đầu<br>
                                <?php echo date('d/m/Y H:i'); ?>
                            </div>
                            <div class="text-center text-muted py-3" id="support-loading">
                                <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                                Đang tải tin nhắn...
                            </div>
                        </div>

                        <div class="card-footer bg-white p-3 border-0 border-top">
                            <form id="support-chat-form">
                                <div class="input-group">
                                    <input type="text" id="support-chat-input" class="form-control form-control-lg border-success" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                                    <button type="submit" class="btn btn-success px-4" id="support-chat-btn">
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                </div>
                            </form>
                            <div class="text-center mt-2">
                                <small class="text-muted">Tin nhắn sẽ được gửi trực tiếp đến người dùng.</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 bg-light text-muted">
                            <i class="bi bi-chat-dots" style="font-size: 4rem; opacity: 0.2;"></i>
                            <h5 class="mt-3">Hãy chọn một cuộc trò chuyện để bắt đầu</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Chat styles are now handled entirely by Bootstrap utility classes */
</style>

<?php if ($target_user): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatBox = document.getElementById('support-chat-box');
    const chatForm = document.getElementById('support-chat-form');
    const chatInput = document.getElementById('support-chat-input');
    const chatBtn = document.getElementById('support-chat-btn');
    
    let lastId = 0;
    const targetUserId = <?php echo $target_user_id; ?>;

    function renderMessage(msg) {
        const isSelf = msg.sender_type === 'admin';
        
        const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});
        const safeMessage = msg.message.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        
        if (isSelf) {
            return `
                <div class="d-flex mb-4 justify-content-end" id="msg-${msg.id}">
                    <div class="flex-grow-1 text-end">
                        <div class="bg-success text-white p-3 rounded shadow-sm d-inline-block text-start" style="max-width: 80%;">${safeMessage}</div>
                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">${time}</div>
                    </div>
                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center ms-3 shadow-sm flex-shrink-0" style="width: 40px; height: 40px;"><i class="bi bi-headset"></i></div>
                </div>
            `;
        } else {
            return `
                <div class="d-flex mb-4" id="msg-${msg.id}">
                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm flex-shrink-0" style="width: 40px; height: 40px;"><i class="bi bi-person"></i></div>
                    <div class="flex-grow-1">
                        <div class="bg-white p-3 rounded shadow-sm d-inline-block border" style="max-width: 80%;">${safeMessage}</div>
                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">${time}</div>
                    </div>
                </div>
            `;
        }
    }

    function fetchMessages() {
        fetch(`<?php echo BASE_URL; ?>/api/support_get_messages.php?last_id=${lastId}&target_user_id=${targetUserId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    if (lastId === 0) chatBox.innerHTML = ''; // Clear loading
                    
                    let shouldScroll = (chatBox.scrollTop + chatBox.clientHeight) >= chatBox.scrollHeight - 50;
                    
                    data.messages.forEach(msg => {
                        chatBox.insertAdjacentHTML('beforeend', renderMessage(msg));
                        lastId = Math.max(lastId, parseInt(msg.id));
                    });
                    
                    if (shouldScroll || lastId === 0) {
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                } else if (lastId === 0) {
                    chatBox.innerHTML = '<div class="text-center text-muted py-4">Bắt đầu cuộc trò chuyện.</div>';
                }
            })
            .catch(err => console.error(err));
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        chatInput.disabled = true;
        chatBtn.disabled = true;

        const formData = new FormData();
        formData.append('message', message);
        formData.append('target_user_id', targetUserId);

        fetch('<?php echo BASE_URL; ?>/api/support_send_message.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                chatInput.value = '';
                fetchMessages(); // Fetch immediately
            }
        })
        .finally(() => {
            chatInput.disabled = false;
            chatBtn.disabled = false;
            chatInput.focus();
        });
    });

    // Initial fetch
    fetchMessages();
    
    // Poll every 3 seconds
    setInterval(fetchMessages, 3000);
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
