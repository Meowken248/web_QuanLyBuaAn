<?php require_once __DIR__ . '/functions.php'; ?></main>
<!-- End Main Content -->

<!-- Footer Public -->
<?php if (!isset($hide_footer) || !$hide_footer): ?>
<footer class="bg-white pt-5 pb-4 mt-5 border-top">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <a href="<?php echo BASE_URL; ?>" class="d-inline-block mb-3" aria-label="<?php echo htmlspecialchars(APP_NAME); ?>">
                    <img src="<?php echo BASE_URL; ?>/img/logo_cty.png" alt="<?php echo htmlspecialchars(APP_NAME); ?>" class="company-logo company-logo-footer" style="max-height: 72px; max-width: 240px; width: auto; object-fit: contain;">
                </a>
                <p class="text-muted">Hệ thống quản lý bữa ăn, theo dõi dinh dưỡng và chăm sóc sức khỏe cá nhân thông minh với trợ lý AI.</p>
                <div class="mt-4">
                    <a href="#" class="text-secondary me-3 fs-5"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-secondary me-3 fs-5"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-secondary me-3 fs-5"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h6 class="fw-bold mb-3">Liên kết</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none text-muted">Trang chủ</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/features.php" class="text-decoration-none text-muted">Tính năng</a></li>

                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/about.php" class="text-decoration-none text-muted">Giới thiệu</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4 mb-4">
                <h6 class="fw-bold mb-3">Hỗ trợ</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/contact.php" class="text-decoration-none text-muted">Liên hệ</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Điều khoản sử dụng</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Chính sách bảo mật</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Disclaimer sức khỏe</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4 mb-4">
                <h6 class="fw-bold mb-3">Cảnh báo (Disclaimer)</h6>
                <p class="text-muted small">Website chỉ cung cấp thông tin tham khảo, không thay thế lời khuyên, chẩn đoán hoặc điều trị từ bác sĩ hoặc chuyên gia dinh dưỡng.</p>
            </div>
        </div>
        <hr class="mt-4 mb-4 text-muted">
        <div class="text-center text-muted small">
            &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. 
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js (included globally or can be conditional) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Lenis Smooth Scroll Engine (Global) -->
<script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/main.js'); ?>"></script>
<?php if (isset($extra_js)) echo $extra_js; ?>
<!-- Floating Chatbot Bubble -->
<?php if (isset($_SESSION['user_id']) && (!isset($hide_footer) || !$hide_footer) && !isset($hide_chatbot)): ?>
<div id="chatbot-bubble-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 1050; display: flex; flex-direction: column; align-items: flex-end;">
    <!-- Cửa sổ Chat -->
    <div id="chatbot-window" class="glass-panel d-none mb-3" style="width: 350px; max-width: calc(100vw - 40px); overflow: hidden; display: flex; flex-direction: column;">
        <style>
        #chatTabs .nav-link { color: rgba(255,255,255,0.7); border-bottom: 2px solid transparent !important; transition: all 0.3s;}
        #chatTabs .nav-link.active { color: white !important; font-weight: bold; border-bottom: 2px solid white !important; background: transparent; }
        .msg-bubble { max-width: 85%; padding: 8px 12px; border-radius: 15px; margin-bottom: 5px; word-break: break-word; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-size: 0.9rem;}
        .msg-user { background-color: #198754; color: #ffffff; border-bottom-right-radius: 4px; }
        .msg-admin { background-color: #ffffff; border: 1px solid #e9ecef; color: #333; border-bottom-left-radius: 4px; }
        </style>
        
        <div class="card-header bg-health text-white p-0 border-0">
            <div class="d-flex justify-content-between align-items-start p-2 pb-0">
                <ul class="nav nav-tabs border-0 flex-nowrap mb-0" id="chatTabs" role="tablist" style="width: 100%;">
                    <li class="nav-item" role="presentation" style="width: 50%;">
                        <button class="nav-link active w-100 rounded-0 border-0" id="ai-tab" data-bs-toggle="tab" data-bs-target="#chat-ai" type="button" role="tab" style="padding: 8px 5px;"><i class="bi bi-robot me-1"></i> Trợ lý AI</button>
                    </li>
                    <li class="nav-item" role="presentation" style="width: 50%;">
                        <button class="nav-link w-100 rounded-0 border-0" id="admin-tab" data-bs-toggle="tab" data-bs-target="#chat-admin" type="button" role="tab" style="padding: 8px 5px;"><i class="bi bi-headset me-1"></i> Hỗ trợ</button>
                    </li>
                </ul>
                <div class="d-flex align-items-center mt-1">
                    <button class="btn btn-sm btn-link text-white p-0 ms-2" id="chatbot-close-btn" style="z-index: 10;"><i class="bi bi-x-lg fs-5"></i></button>
                </div>
            </div>
        </div>

        <div class="tab-content flex-grow-1 d-flex flex-column" id="chatTabsContent" style="height: 380px;">
            <!-- AI Tab -->
            <div class="tab-pane fade show active h-100" id="chat-ai" role="tabpanel">
                <div class="d-flex flex-column h-100">
                    <div class="card-body p-3 flex-grow-1" id="chatbot-messages" style="overflow-y: auto; background-color: rgba(255,255,255,0.7);">
                        <div class="text-end mb-2">
                            <button class="btn btn-sm btn-link text-muted p-0" id="chatbot-clear-btn" title="Xóa trò chuyện"><i class="bi bi-trash"></i> Xóa cuộc trò chuyện</button>
                        </div>
                        <div class="text-center text-muted small mb-3">
                            Hôm nay<br><?php echo date('d/m/Y H:i'); ?>
                        </div>
                        <div class="d-flex mb-3">
                            <div class="bg-health text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0 shadow-sm" style="width: 35px; height: 35px;">
                                <i class="bi bi-robot"></i>
                            </div>
                            <div class="bg-white border rounded p-2 small shadow-sm">
                                Xin chào! Tôi là trợ lý ảo AI. Tôi có thể giúp gì cho mục tiêu sức khỏe của bạn hôm nay?
                            </div>
                        </div>
                    </div>
                    <div class="card-footer p-2 bg-white border-top mt-auto" style="border-radius: 0 0 var(--radius-lg) var(--radius-lg);">
                        <form id="chatbot-form" class="d-flex align-items-center m-0">
                            <input type="text" id="chatbot-input" class="form-control bg-light me-2 rounded-pill px-3 py-2" placeholder="Hỏi Trợ lý AI..." autocomplete="off" required>
                            <button type="submit" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 p-0 shadow-sm" style="width: 40px; height: 40px;">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Admin Tab -->
            <div class="tab-pane fade h-100" id="chat-admin" role="tabpanel">
                <div class="d-flex flex-column h-100">
                    <div class="card-body p-3 flex-grow-1" id="support-messages" style="overflow-y: auto; background-color: rgba(255,255,255,0.7);">
                        <div class="text-center text-muted py-5" id="support-loading">
                            <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                            Đang kết nối...
                        </div>
                    </div>
                    <div class="card-footer p-2 bg-white border-top mt-auto" style="border-radius: 0 0 var(--radius-lg) var(--radius-lg);">
                        <form id="support-form" class="d-flex align-items-center m-0">
                            <input type="text" id="support-input" class="form-control bg-light me-2 rounded-pill px-3 py-2" placeholder="Gửi tin nhắn cho Admin..." autocomplete="off" required>
                            <button type="submit" class="btn btn-success rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 p-0 shadow-sm" style="width: 40px; height: 40px;">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Nút mở chat -->
    <button id="chatbot-toggle-btn" class="btn btn-primary btn-glow rounded-circle shadow-heavy d-flex align-items-center justify-content-center position-relative" style="width: 60px; height: 60px; border: 3px solid white; outline: none;">
        <i class="bi bi-chat-dots-fill fs-3 text-white"></i>
        <span id="userChatbotBadgeContainer"></span>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('chatbot-toggle-btn');
    const closeBtn = document.getElementById('chatbot-close-btn');
    const chatWindow = document.getElementById('chatbot-window');
    const chatForm = document.getElementById('chatbot-form');
    const chatInput = document.getElementById('chatbot-input');
    const chatMessages = document.getElementById('chatbot-messages');
    const clearBtn = document.getElementById('chatbot-clear-btn');
    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    };
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            chatWindow.classList.toggle('d-none');
            if (!chatWindow.classList.contains('d-none')) {
                chatInput.focus();
            }
        });
        
        closeBtn.addEventListener('click', function() {
            chatWindow.classList.add('d-none');
        });

        clearBtn.addEventListener('click', function() {
            if(confirm('Bạn có chắc muốn xóa lịch sử trò chuyện này?')) {
                const welcomeMsg = chatMessages.firstElementChild.nextElementSibling.outerHTML;
                const timeMsg = chatMessages.firstElementChild.outerHTML;
                chatMessages.innerHTML = timeMsg + welcomeMsg;
            }
        });

        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const question = chatInput.value.trim();
            if (!question) return;

            const userHtml = `
            <div class="d-flex flex-row-reverse mb-3">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center ms-2 flex-shrink-0" style="width: 35px; height: 35px;">
                    <i class="bi bi-person"></i>
                </div>
                <div class="bg-health text-white rounded p-2 small shadow-sm">
                    ${escapeHtml(question)}
                </div>
            </div>`;
            chatMessages.insertAdjacentHTML('beforeend', userHtml);
            
            chatInput.value = '';
            chatInput.disabled = true;
            chatMessages.scrollTop = chatMessages.scrollHeight;

            const loadingId = 'loading-' + Date.now();
            const loadingHtml = `
            <div id="${loadingId}" class="d-flex mb-3">
                <div class="bg-health text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="bg-white border rounded p-2 small shadow-sm text-muted">
                    Đang xử lý...
                </div>
            </div>`;
            chatMessages.insertAdjacentHTML('beforeend', loadingHtml);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            fetch('<?php echo BASE_URL; ?>/api/chatbot/ask.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: question, csrf_token: '<?php echo generate_csrf_token(); ?>' })
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById(loadingId).remove();
                chatInput.disabled = false;
                chatInput.focus();

                let responseHtml = '';
                if (data.status === 'success') {
                    const noticeHtml = data.source === 'local' && data.notice
                        ? `<div class="small text-warning mt-1"><i class="bi bi-info-circle me-1"></i>${escapeHtml(data.notice)}</div>`
                        : '';
                    responseHtml = `
                    <div class="d-flex mb-3">
                        <div class="bg-health text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="bg-white border rounded p-2 small shadow-sm">
                            ${data.answer}
                            ${noticeHtml}
                        </div>
                    </div>`;
                } else {
                    responseHtml = `
                    <div class="d-flex mb-3">
                        <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="bg-white border rounded p-2 small shadow-sm text-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>${escapeHtml(data.message || 'Không xác định được lỗi.')}
                        </div>
                    </div>`;
                }
                chatMessages.insertAdjacentHTML('beforeend', responseHtml);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            })
            .catch(err => {
                document.getElementById(loadingId).remove();
                chatInput.disabled = false;
                
                const errHtml = `
                <div class="d-flex mb-3">
                    <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="bg-white border rounded p-2 small shadow-sm text-danger">
                        Đã xảy ra lỗi kết nối mạng.
                    </div>
                </div>`;
                chatMessages.insertAdjacentHTML('beforeend', errHtml);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        });
    }

    // --- ADMIN SUPPORT CHAT LOGIC ---
    const supportMessages = document.getElementById('support-messages');
    const supportForm = document.getElementById('support-form');
    const supportInput = document.getElementById('support-input');
    
    if (supportForm) {
        let lastId = 0;
        let supportPollInterval = null;
        
        function renderSupportMsg(msg) {
            const isSelf = msg.sender_type === 'user';
            const bubbleClass = isSelf ? 'msg-user' : 'msg-admin';
            const alignClass = isSelf ? 'text-end' : 'text-start';
            const nameLabel = isSelf ? 'Bạn' : 'Admin';
            const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});
            
            return `
                <div class="${alignClass} mb-3" id="msg-${msg.id}">
                    ${!isSelf ? `<div class="small text-muted mb-1 ms-1 fw-bold">${nameLabel}</div>` : ''}
                    <div class="msg-bubble ${bubbleClass}">
                        ${escapeHtml(msg.message)}
                    </div>
                    <div class="small text-muted mt-1" style="font-size: 0.75rem;">${time}</div>
                </div>
            `;
        }

        function fetchSupportMessages() {
            fetch('<?php echo BASE_URL; ?>/api/support_get_messages.php?last_id=' + lastId)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const loading = document.getElementById('support-loading');
                        if (loading) loading.remove();
                        
                        let shouldScroll = (supportMessages.scrollTop + supportMessages.clientHeight) >= supportMessages.scrollHeight - 50;
                        
                        data.messages.forEach(msg => {
                            supportMessages.insertAdjacentHTML('beforeend', renderSupportMsg(msg));
                            lastId = Math.max(lastId, parseInt(msg.id));
                        });
                        
                        if (shouldScroll || lastId === 0) {
                            supportMessages.scrollTop = supportMessages.scrollHeight;
                        }
                    } else if (lastId === 0 && document.getElementById('support-loading')) {
                        supportMessages.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-chat-dots fs-1 mb-3 d-block text-black-50"></i>Bạn cần hỗ trợ gì? Hãy gửi tin nhắn cho chúng tôi.</div>';
                    }
                })
                .catch(err => console.error(err));
        }

        supportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = supportInput.value.trim();
            if (!message) return;

            supportInput.disabled = true;
            const btn = supportForm.querySelector('button');
            btn.disabled = true;

            const formData = new FormData();
            formData.append('message', message);

            fetch('<?php echo BASE_URL; ?>/api/support_send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    supportInput.value = '';
                    fetchSupportMessages(); 
                }
            })
            .finally(() => {
                supportInput.disabled = false;
                btn.disabled = false;
                supportInput.focus();
            });
        });

        // Start polling when Admin tab is shown
        const adminTab = document.getElementById('admin-tab');
        if (adminTab) {
            adminTab.addEventListener('shown.bs.tab', function (e) {
                if (lastId === 0) fetchSupportMessages(); // initial fetch
                if (!supportPollInterval) supportPollInterval = setInterval(fetchSupportMessages, 3000);
                setTimeout(() => { supportMessages.scrollTop = supportMessages.scrollHeight; }, 100);
            });
            adminTab.addEventListener('hidden.bs.tab', function (e) {
                if (supportPollInterval) {
                    clearInterval(supportPollInterval);
                    supportPollInterval = null;
                }
            });
        }
    }
});
</script>
<?php endif; ?>

<!-- AOS JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 800,
                once: true,
                offset: 50,
                easing: 'ease-out-cubic'
            });
        }
    });
</script>
<!-- Back Button (Trừ trang chủ) -->
<?php if (basename($_SERVER['PHP_SELF']) != 'index.php'): ?>
<div style="position: fixed; bottom: 20px; left: 20px; z-index: 1050;">
    <button onclick="window.history.back()" class="btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center border" style="width: 50px; height: 50px; opacity: 0.85; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.85" title="Quay lại trang trước">
        <i class="bi bi-arrow-left fs-4 text-dark"></i>
    </button>
</div>
<?php endif; ?>

</body>
</html>
