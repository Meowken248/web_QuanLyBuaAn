</main>
<!-- End Main Content -->

<!-- Footer Public -->
<?php if (!isset($hide_footer) || !$hide_footer): ?>
<footer class="bg-white pt-5 pb-4 mt-5 border-top">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <a href="<?php echo BASE_URL; ?>" class="d-inline-block mb-3" aria-label="<?php echo htmlspecialchars(APP_NAME); ?>">
                    <img src="<?php echo BASE_URL; ?>/img/logo_cty.png" alt="<?php echo htmlspecialchars(APP_NAME); ?>" class="company-logo company-logo-footer">
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
                    <li class="mb-2"><a href="<?php echo BASE_URL; ?>/pricing.php" class="text-decoration-none text-muted">Bảng giá</a></li>
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
<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
<?php if (isset($extra_js)) echo $extra_js; ?>
<!-- Floating Chatbot Bubble -->
<?php if (isset($_SESSION['user_id']) && (!isset($hide_footer) || !$hide_footer)): ?>
<div id="chatbot-bubble-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 1050; display: flex; flex-direction: column; align-items: flex-end;">
    <!-- Cửa sổ Chat -->
    <div id="chatbot-window" class="card shadow-lg d-none mb-3" style="width: 350px; max-width: calc(100vw - 40px); border-radius: 15px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center p-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-robot fs-4 me-2"></i>
                <h6 class="mb-0 fw-bold">Gemini Dinh Dưỡng</h6>
            </div>
            <div>
                <button class="btn btn-sm btn-link text-white p-0 me-2" id="chatbot-clear-btn" title="Xóa trò chuyện"><i class="bi bi-trash fs-5"></i></button>
                <button class="btn btn-sm btn-link text-white p-0" id="chatbot-close-btn"><i class="bi bi-x-lg fs-5"></i></button>
            </div>
        </div>
        <div class="card-body p-3 bg-light flex-grow-1" id="chatbot-messages" style="height: 350px; overflow-y: auto;">
            <div class="text-center text-muted small mb-3">
                Cuộc trò chuyện bắt đầu<br><?php echo date('d/m/Y H:i'); ?>
            </div>
            
            <div class="d-flex mb-3">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="bg-white border rounded p-2 small shadow-sm">
                    Xin chào! Tôi là trợ lý ảo Gemini. Tôi có thể giúp gì cho mục tiêu sức khỏe của bạn hôm nay?
                </div>
            </div>
        </div>
        <div class="card-footer p-2 bg-white border-top mt-auto">
            <form id="chatbot-form" class="d-flex align-items-center m-0">
                <input type="text" id="chatbot-input" class="form-control border-0 bg-light me-2 rounded-pill px-3 py-2" placeholder="Nhập câu hỏi..." autocomplete="off" required style="box-shadow: none;">
                <button type="submit" class="btn btn-success rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 p-0" style="width: 40px; height: 40px; border: none;">
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Nút mở chat -->
    <button id="chatbot-toggle-btn" class="btn btn-success rounded-circle shadow-lg d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border: 3px solid white; outline: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;">
        <i class="bi bi-chat-dots-fill fs-3 text-white"></i>
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
                <div class="bg-success text-white rounded p-2 small shadow-sm">
                    ${question.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                </div>
            </div>`;
            chatMessages.insertAdjacentHTML('beforeend', userHtml);
            
            chatInput.value = '';
            chatInput.disabled = true;
            chatMessages.scrollTop = chatMessages.scrollHeight;

            const loadingId = 'loading-' + Date.now();
            const loadingHtml = `
            <div id="${loadingId}" class="d-flex mb-3">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
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
                body: JSON.stringify({ question: question })
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById(loadingId).remove();
                chatInput.disabled = false;
                chatInput.focus();

                let responseHtml = '';
                if (data.status === 'success') {
                    responseHtml = `
                    <div class="d-flex mb-3">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="bg-white border rounded p-2 small shadow-sm">
                            ${data.answer}
                        </div>
                    </div>`;
                } else {
                    responseHtml = `
                    <div class="d-flex mb-3">
                        <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="bg-white border rounded p-2 small shadow-sm text-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>${data.message}
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
});
</script>
<?php endif; ?>

</body>
</html>
