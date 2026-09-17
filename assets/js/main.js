// assets/js/main.js
// Main JavaScript file

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('data-password-toggle'));
        if (!input) return;

        button.addEventListener('click', function () {
            var isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';

            var icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', isVisible);
                icon.classList.toggle('bi-eye-slash', !isVisible);
            }

            var nowVisible = !isVisible;
            button.setAttribute('aria-pressed', nowVisible ? 'true' : 'false');
            button.setAttribute('aria-label', nowVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
            input.focus({ preventScroll: true });
        });
    });

    // Auto-dismiss alert notifications after 4 seconds (BUG-08)
    var alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alertEl) {
        setTimeout(function() {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                    var bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                    if (bsAlert) bsAlert.close();
                } else {
                    alertEl.classList.remove('show');
                    setTimeout(function() { alertEl.remove(); }, 300);
                }
            } catch (e) {
                if (alertEl.parentNode) alertEl.parentNode.removeChild(alertEl);
            }
        }, 4000);
    });

    // Realtime notification and support chat badge polling (BUG-02)
    function pollUnreadCounts() {
        var basePath = window.location.pathname.indexOf('/web_QuanLyBuaAn') !== -1 ? '/web_QuanLyBuaAn' : '';
        var apiUrl = basePath + '/api/check_unread_support.php';

        fetch(apiUrl)
            .then(function(res) {
                if (!res.ok) return null;
                return res.json();
            })
            .then(function(data) {
                if (!data || !data.logged_in) return;

                // 1. Cập nhật chuông thông báo trên Header
                var bellContainer = document.getElementById('headerBellBadgeContainer');
                if (bellContainer) {
                    if (data.total > 0) {
                        var text = data.total > 99 ? '99+' : data.total;
                        bellContainer.innerHTML = '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">' + text + '</span>';
                    } else {
                        bellContainer.innerHTML = '';
                    }
                }

                // 2. Cập nhật menu Hỗ trợ trực tuyến trong Admin Sidebar
                var adminBadgeContainer = document.getElementById('adminSidebarSupportBadgeContainer');
                if (adminBadgeContainer) {
                    if (data.unread_support > 0) {
                        adminBadgeContainer.innerHTML = '<span class="badge bg-danger rounded-pill">' + data.unread_support + '</span>';
                    } else {
                        adminBadgeContainer.innerHTML = '';
                    }
                }

                // 3. Cập nhật icon Chatbot nổi của User khi Admin gửi tin nhắn
                var userChatbotBadge = document.getElementById('userChatbotBadgeContainer');
                if (userChatbotBadge) {
                    if (!data.is_admin && data.unread_support > 0) {
                        userChatbotBadge.innerHTML = '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">' + data.unread_support + '</span>';
                    } else {
                        userChatbotBadge.innerHTML = '';
                    }
                }
            })
            .catch(function(err) {
                // Ignore network errors during polling
            });
    }

    // Run immediately once and every 10 seconds
    pollUnreadCounts();
    setInterval(pollUnreadCounts, 10000);
});

