<?php
// user/chatbot.php
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Chatbot Tư Vấn Dinh Dưỡng';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3">
            <div class="list-group shadow-sm mb-4">
                <a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="list-group-item list-group-item-action"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/user/profile.php" class="list-group-item list-group-item-action"><i class="bi bi-person-circle me-2"></i>Hồ sơ sức khỏe</a>
                <a href="<?php echo BASE_URL; ?>/user/meals.php" class="list-group-item list-group-item-action"><i class="bi bi-journal-text me-2"></i>Nhật ký bữa ăn</a>
                <a href="<?php echo BASE_URL; ?>/user/chatbot.php" class="list-group-item list-group-item-action active bg-success border-success"><i class="bi bi-robot me-2"></i>Chatbot AI</a>
            </div>
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold">Gợi ý câu hỏi</div>
                <div class="list-group list-group-flush small">
                    <button class="list-group-item list-group-item-action prompt-btn">Tôi nên ăn gì để giảm cân?</button>
                    <button class="list-group-item list-group-item-action prompt-btn">Hôm nay tôi còn bao nhiêu calories?</button>
                    <button class="list-group-item list-group-item-action prompt-btn">Gợi ý món ăn dưới 500 calories</button>
                    <button class="list-group-item list-group-item-action prompt-btn">Tôi nên ăn gì sau khi tập gym?</button>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card shadow border-0" style="height: 600px; display: flex; flex-direction: column;">
                <div class="card-header bg-success text-white py-3 d-flex align-items-center">
                    <i class="bi bi-robot fs-4 me-2"></i>
                    <div>
                        <h5 class="mb-0 fw-bold">Gemini Dinh Dưỡng</h5>
                        <small class="text-white-50">Sẵn sàng giải đáp thắc mắc của bạn</small>
                    </div>
                    <div class="ms-auto">
                        <button class="btn btn-sm btn-outline-light" id="clearChatBtn" title="Xóa lịch sử"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                
                <div class="card-body bg-light overflow-auto p-4" id="chatBox" style="flex: 1;">
                    <div class="text-center mb-4 text-muted small">
                        Cuộc trò chuyện bắt đầu<br>
                        <?php echo date('d/m/Y H:i'); ?>
                    </div>
                    
                    <!-- Lời chào mặc định -->
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-robot"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="bg-white p-3 rounded shadow-sm d-inline-block border">
                                Xin chào <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b>! Tôi là trợ lý ảo Gemini. Tôi có thể giúp gì cho mục tiêu sức khỏe của bạn hôm nay?
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-white p-3 border-0 border-top">
                    <form id="chatForm">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg bg-light" id="chatInput" placeholder="Nhập câu hỏi của bạn..." required autocomplete="off">
                            <button class="btn btn-success px-4" type="submit" id="sendBtn">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </form>
                    <div class="text-center mt-2">
                        <small class="text-muted" style="font-size: 11px;">
                            Chatbot AI chỉ cung cấp thông tin tham khảo và không thay thế bác sĩ hoặc chuyên gia dinh dưỡng.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatBox = document.getElementById('chatBox');
    const sendBtn = document.getElementById('sendBtn');
    const promptBtns = document.querySelectorAll('.prompt-btn');
    const clearChatBtn = document.getElementById('clearChatBtn');
    
    // Add message to UI
    function appendMessage(sender, text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `d-flex mb-4 ${sender === 'user' ? 'justify-content-end' : ''}`;
        
        let avatarHTML = '';
        if (sender === 'user') {
            avatarHTML = `<div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 40px; height: 40px;"><i class="bi bi-person"></i></div>`;
            msgDiv.innerHTML = `
                <div class="flex-grow-1 text-end">
                    <div class="bg-success text-white p-3 rounded shadow-sm d-inline-block text-start">
                        ${text}
                    </div>
                </div>
                ${avatarHTML}
            `;
        } else {
            avatarHTML = `<div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;"><i class="bi bi-robot"></i></div>`;
            msgDiv.innerHTML = `
                ${avatarHTML}
                <div class="flex-grow-1">
                    <div class="bg-white p-3 rounded shadow-sm d-inline-block border">
                        ${text}
                    </div>
                </div>
            `;
        }
        
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight;
    }
    
    // Loading indicator
    function showLoading() {
        const msgDiv = document.createElement('div');
        msgDiv.id = 'loadingIndicator';
        msgDiv.className = 'd-flex mb-4';
        msgDiv.innerHTML = `
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;"><i class="bi bi-robot"></i></div>
            <div class="flex-grow-1">
                <div class="bg-white p-3 rounded shadow-sm d-inline-block border text-muted">
                    <div class="spinner-grow spinner-grow-sm text-success" role="status"></div>
                    <div class="spinner-grow spinner-grow-sm text-success mx-1" role="status"></div>
                    <div class="spinner-grow spinner-grow-sm text-success" role="status"></div>
                </div>
            </div>
        `;
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight;
    }
    
    function hideLoading() {
        const loading = document.getElementById('loadingIndicator');
        if (loading) loading.remove();
    }
    
    // Send request to API
    async function sendMessage(question) {
        if (!question.trim()) return;
        
        appendMessage('user', question);
        chatInput.value = '';
        chatInput.disabled = true;
        sendBtn.disabled = true;
        
        showLoading();
        
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/chatbot/ask.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: question })
            });
            
            const data = await response.json();
            hideLoading();
            
            if (data.status === 'success') {
                appendMessage('bot', data.answer);
            } else {
                appendMessage('bot', `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Lỗi: ${data.message}</span>`);
            }
        } catch (error) {
            hideLoading();
            appendMessage('bot', `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Lỗi kết nối máy chủ.</span>`);
        }
        
        chatInput.disabled = false;
        sendBtn.disabled = false;
        chatInput.focus();
    }
    
    // Event listeners
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendMessage(chatInput.value);
    });
    
    promptBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            sendMessage(this.textContent);
        });
    });
    
    clearChatBtn.addEventListener('click', function() {
        if(confirm('Xóa lịch sử trò chuyện?')) {
            // Keep the first message
            const firstMsg = chatBox.children[0];
            const secondMsg = chatBox.children[1];
            chatBox.innerHTML = '';
            chatBox.appendChild(firstMsg);
            chatBox.appendChild(secondMsg);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
