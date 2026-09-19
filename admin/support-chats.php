<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SESSION['user_role'] !== 'admin') {
    redirect('/index.php');
}

$db = new Database();
$conn = $db->getConnection();

$target_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$target_user = null;
if ($target_user_id) {
    // Đánh dấu tin nhắn của user này là đã đọc NGAY LẬP TỨC trước khi header.php chạy
    try {
        $stmtMarkRead = $conn->prepare("
            UPDATE support_messages sm
            JOIN support_chats sc ON sm.chat_id = sc.id
            SET sm.is_read = 1
            WHERE sc.user_id = :uid AND sm.sender_type = 'user' AND sm.is_read = 0
        ");
        $stmtMarkRead->execute([':uid' => $target_user_id]);

        // Đánh dấu đã đọc các thông báo chuông liên quan đến user này
        $stmtNotifRead = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = :admin_id AND message LIKE :pattern AND is_read = 0
        ");
        $stmtNotifRead->execute([
            ':admin_id' => $_SESSION['user_id'],
            ':pattern' => '%[UID:' . $target_user_id . ']%'
        ]);
    } catch (Exception $e) {}

    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = :id");
    $stmt->execute([':id' => $target_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page_title = 'Hỗ trợ trực tuyến';
$hide_footer = true;
require_once __DIR__ . '/../includes/header.php';

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

// Helper: lấy chữ cái đầu viết tắt từ tên
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if ($w !== '') $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return mb_substr($initials, 0, 2, 'UTF-8');
}
?>

<div class="container-fluid py-4 h-100">
    <div class="row h-100">
        <div class="col-md-2">
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-10 d-flex flex-column" style="height: calc(100vh - 130px);">
            <div class="d-flex h-100 rounded-4 overflow-hidden shadow mt-2 mb-3" style="border: 1px solid #e0e0e0;">

                <!-- ========== LEFT: Chat List ========== -->
                <div class="d-flex flex-column h-100 bg-white" style="width: 340px; min-width: 280px; border-right: 1px solid #e0e0e0;">
                    <!-- Left Header -->
                    <div class="px-3 py-3 border-bottom" style="background: #f8f9fa;">
                        <h6 class="mb-0 fw-bold text-uppercase text-dark" style="letter-spacing: 0.5px; font-size: 0.85rem;">Danh sách hội thoại</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Quản lý tin nhắn khách hàng</small>
                    </div>

                    <!-- Chat List -->
                    <div class="flex-grow-1 overflow-auto" id="admin-chat-list">
                        <?php if (empty($chats)): ?>
                            <div class="text-center py-5 text-muted small">
                                <i class="bi bi-chat-dots d-block mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                                Chưa có cuộc hội thoại nào.
                            </div>
                        <?php else: ?>
                            <?php foreach ($chats as $chat): ?>
                                <?php
                                    $isActive = ($target_user_id == $chat['user_id']);
                                    $initials = getInitials($chat['full_name']);
                                    $timeStr = $chat['last_time'] ? 'Hôm nay, ' . date('H:i', strtotime($chat['last_time'])) . ' - ' . date('d/m', strtotime($chat['last_time'])) : '';
                                ?>
                                <a href="<?php echo BASE_URL; ?>/admin/support-chats.php?user_id=<?php echo $chat['user_id']; ?>"
                                   id="chat-user-<?php echo $chat['user_id']; ?>"
                                   data-user-id="<?php echo $chat['user_id']; ?>"
                                   class="chat-list-item d-flex align-items-start px-3 py-3 text-decoration-none <?php echo $isActive ? 'active' : ''; ?>"
                                   style="border-bottom: 1px solid #f0f0f0; <?php echo $isActive ? 'border-left: 4px solid #198754; background: #f0faf5;' : 'border-left: 4px solid transparent;'; ?>">
                                    <!-- Avatar -->
                                    <div class="flex-shrink-0 me-3 rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" 
                                         style="width: 44px; height: 44px; font-size: 0.85rem; background: <?php echo $isActive ? '#198754' : '#6c757d'; ?>;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <!-- Info -->
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center mb-1 chat-name-header">
                                            <span class="fw-bold text-dark text-truncate" style="font-size: 0.9rem; max-width: 130px;"><?php echo htmlspecialchars($chat['full_name']); ?></span>
                                            <?php if ($chat['unread_count'] > 0 && !$isActive): ?>
                                                <span class="badge bg-success rounded-pill ms-1 chat-badge-count" style="font-size: 0.65rem;"><?php echo $chat['unread_count']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                            <span class="chat-preview-time"><?php echo $timeStr ? $timeStr . ' · ' : ''; ?></span><span class="chat-preview-text"><?php echo htmlspecialchars(mb_strimwidth($chat['last_message'] ?: 'Chưa có tin nhắn', 0, 30, '...', 'UTF-8')); ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ========== RIGHT: Chat View ========== -->
                <div class="d-flex flex-column flex-grow-1 h-100">
                    <?php if ($target_user): ?>
                        <?php $userInitials = getInitials($target_user['full_name']); ?>
                        <!-- Right Header -->
                        <div class="d-flex align-items-center px-4 py-3 text-white" style="background: linear-gradient(135deg, #1a6b3c 0%, #198754 100%); min-height: 68px;">
                            <div>
                                <h6 class="mb-0 fw-bold" style="font-size: 1rem;">Hỗ trợ: <?php echo htmlspecialchars($target_user['full_name']); ?></h6>
                                <small style="font-size: 0.75rem; opacity: 0.8;"><span style="color: #90ee90;">●</span> Đang hoạt động trực tuyến</small>
                            </div>
                        </div>

                        <!-- Chat Messages -->
                        <div class="flex-grow-1 overflow-auto p-4" id="support-chat-box">
                            <div class="text-center text-muted py-3" id="support-loading">
                                <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                                Đang tải tin nhắn...
                            </div>
                        </div>

                        <!-- Chat Input -->
                        <div class="px-4 py-3 bg-white border-top">
                            <form id="support-chat-form">
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" id="support-chat-input" class="form-control rounded-3 border" placeholder="Nhập tin nhắn..." autocomplete="off" required
                                           style="padding: 10px 16px; font-size: 0.9rem;">
                                    <button type="submit" class="btn btn-success rounded-3 px-3 d-flex align-items-center gap-2" id="support-chat-btn"
                                            style="white-space: nowrap; padding: 10px 18px;">
                                        Gửi <i class="bi bi-send-fill"></i>
                                    </button>
                                </div>
                            </form>
                            <div class="text-center mt-2">
                                <small class="text-muted" style="font-size: 0.7rem;">Tin nhắn sẽ được gửi trực tiếp đến người dùng.</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted" style="background: #efeae2;">
                            <i class="bi bi-chat-square-text" style="font-size: 4rem; opacity: 0.15;"></i>
                            <h5 class="mt-3 fw-normal" style="opacity: 0.5;">Hãy chọn một cuộc trò chuyện để bắt đầu</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Chat list item hover */
.chat-list-item { transition: background 0.15s; }
.chat-list-item:hover:not(.active) { background: #f8f9fa !important; }

/* Chat bubbles */
.chat-bubble-user {
    background: #dcf8c6;
    color: #111;
    padding: 10px 14px;
    border-radius: 12px 12px 0 12px;
    display: inline-block;
    word-break: break-word;
    box-shadow: 0 1px 1px rgba(0,0,0,0.08);
}
.chat-bubble-admin {
    background: #198754;
    color: #fff;
    padding: 10px 14px;
    border-radius: 12px 12px 12px 0;
    display: inline-block;
    word-break: break-word;
    box-shadow: 0 1px 1px rgba(0,0,0,0.08);
}
.chat-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // BUG-03: Realtime cập nhật danh sách hội thoại bên trái
    function fetchChatList() {
        fetch('<?php echo BASE_URL; ?>/api/support_get_chats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.chats)) {
                    const chatListContainer = document.getElementById('admin-chat-list');
                    data.chats.forEach(chat => {
                        let item = document.getElementById('chat-user-' + chat.user_id);
                        const isCurrent = (typeof targetUserId !== 'undefined' && targetUserId == chat.user_id);
                        const unread = isCurrent ? 0 : parseInt(chat.unread_count || 0);

                        let timeText = '';
                        if (chat.last_time) {
                            const d = new Date(chat.last_time);
                            const h = String(d.getHours()).padStart(2, '0');
                            const m = String(d.getMinutes()).padStart(2, '0');
                            const day = String(d.getDate()).padStart(2, '0');
                            const mo = String(d.getMonth() + 1).padStart(2, '0');
                            timeText = 'Hôm nay, ' + h + ':' + m + ' - ' + day + '/' + mo + ' · ';
                        }

                        const msgText = chat.last_message ? chat.last_message.substring(0, 30) : 'Chưa có tin nhắn';

                        if (item) {
                            // Cập nhật text preview
                            const previewEl = item.querySelector('.chat-preview-text');
                            if (previewEl) previewEl.textContent = msgText;

                            // Cập nhật thời gian
                            const timeEl = item.querySelector('.chat-preview-time');
                            if (timeEl) timeEl.textContent = timeText;

                            // Cập nhật badge unread (BUG-04)
                            let badgeEl = item.querySelector('.chat-badge-count');
                            if (unread > 0) {
                                if (!badgeEl) {
                                    const header = item.querySelector('.chat-name-header');
                                    if (header) {
                                        badgeEl = document.createElement('span');
                                        badgeEl.className = 'badge bg-success rounded-pill ms-1 chat-badge-count';
                                        badgeEl.style.fontSize = '0.65rem';
                                        header.appendChild(badgeEl);
                                    }
                                }
                                if (badgeEl) badgeEl.textContent = unread;
                            } else if (badgeEl) {
                                badgeEl.remove();
                            }
                        } else if (chatListContainer) {
                            // Tạo mới item nếu user này chưa có trong danh sách
                            // Xóa thông báo trống nếu có
                            const emptyMsg = chatListContainer.querySelector('.text-center.py-5');
                            if (emptyMsg) emptyMsg.remove();

                            let initials = '';
                            const words = (chat.full_name || 'Khách').trim().split(' ');
                            words.forEach(w => { if (w) initials += w.charAt(0).toUpperCase(); });
                            initials = initials.substring(0, 2);

                            const newA = document.createElement('a');
                            newA.href = '<?php echo BASE_URL; ?>/admin/support-chats.php?user_id=' + chat.user_id;
                            newA.id = 'chat-user-' + chat.user_id;
                            newA.dataset.userId = chat.user_id;
                            newA.className = 'chat-list-item d-flex align-items-start px-3 py-3 text-decoration-none ' + (isCurrent ? 'active' : '');
                            newA.style.borderBottom = '1px solid #f0f0f0';
                            newA.style.borderLeft = isCurrent ? '4px solid #198754' : '4px solid transparent';
                            if (isCurrent) newA.style.background = '#f0faf5';

                            newA.innerHTML = `
                                <div class="flex-shrink-0 me-3 rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" 
                                     style="width: 44px; height: 44px; font-size: 0.85rem; background: ${isCurrent ? '#198754' : '#6c757d'};">
                                    ${initials}
                                </div>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center mb-1 chat-name-header">
                                        <span class="fw-bold text-dark text-truncate" style="font-size: 0.9rem; max-width: 130px;">${(chat.full_name || '').replace(/</g, '&lt;')}</span>
                                        ${unread > 0 ? `<span class="badge bg-success rounded-pill ms-1 chat-badge-count" style="font-size: 0.65rem;">${unread}</span>` : ''}
                                    </div>
                                    <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                        <span class="chat-preview-time">${timeText}</span><span class="chat-preview-text">${msgText.replace(/</g, '&lt;')}</span>
                                    </div>
                                </div>
                            `;
                            chatListContainer.prepend(newA);
                        }
                    });
                }
            })
            .catch(err => console.error(err));
    }

    // Polling danh sách hội thoại mỗi 3 giây
    setInterval(fetchChatList, 3000);

    <?php if ($target_user): ?>
    const chatBox = document.getElementById('support-chat-box');
    const chatForm = document.getElementById('support-chat-form');
    const chatInput = document.getElementById('support-chat-input');
    const chatBtn = document.getElementById('support-chat-btn');
    
    let lastId = 0;
    const targetUserId = <?php echo $target_user_id; ?>;
    const userInitials = '<?php echo $userInitials; ?>';

    // BUG-04: Xóa badge của cuộc hội thoại đang mở ngay trên giao diện
    const currentBadge = document.querySelector('#chat-user-' + targetUserId + ' .chat-badge-count');
    if (currentBadge) currentBadge.remove();

    function renderMessage(msg) {
        const isSelf = msg.sender_type === 'admin';
        const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});
        const safeMessage = msg.message.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        
        if (isSelf) {
            return `
                <div class="d-flex mb-3 justify-content-end" id="msg-${msg.id}">
                    <div class="text-end me-2" style="max-width: 75%;">
                        <div class="chat-bubble-admin">${safeMessage}</div>
                        <div class="text-muted mt-1" style="font-size: 0.65rem;">${time}</div>
                    </div>
                    <div class="chat-avatar" style="background: #198754;">A</div>
                </div>
            `;
        } else {
            return `
                <div class="d-flex mb-3" id="msg-${msg.id}">
                    <div class="chat-avatar me-2" style="background: #6c757d;">${userInitials}</div>
                    <div style="max-width: 75%;">
                        <div class="chat-bubble-user">${safeMessage}</div>
                        <div class="text-muted mt-1" style="font-size: 0.65rem;">${time}</div>
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
                    if (lastId === 0) chatBox.innerHTML = '';
                    
                    let shouldScroll = (chatBox.scrollTop + chatBox.clientHeight) >= chatBox.scrollHeight - 50;
                    
                    data.messages.forEach(msg => {
                        chatBox.insertAdjacentHTML('beforeend', renderMessage(msg));
                        lastId = Math.max(lastId, parseInt(msg.id));
                    });
                    
                    if (shouldScroll || lastId === 0) {
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                    // Cập nhật lại danh sách bên trái ngay khi có tin nhắn mới trong box
                    fetchChatList();
                } else if (lastId === 0) {
                    chatBox.innerHTML = '<div class="text-center text-muted py-4" style="font-size: 0.85rem;">Bắt đầu cuộc trò chuyện.</div>';
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
                fetchMessages();
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
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
