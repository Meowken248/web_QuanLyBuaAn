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
    // Only auto-dismiss success and info alerts; keep danger and warning alerts visible
    var alerts = document.querySelectorAll('.alert-dismissible.alert-success, .alert-dismissible.alert-info');
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

    // Helper functions for notifications and realtime sync
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatShortDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dateStr;
        var h = String(d.getHours()).padStart(2, '0');
        var m = String(d.getMinutes()).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        var mo = String(d.getMonth() + 1).padStart(2, '0');
        return h + ':' + m + ' ' + day + '/' + mo;
    }

    // Global Floating Toast notification for real-time incoming messages
    function showGlobalToast(title, text, link, iconClass) {
        var container = document.getElementById('globalToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'globalToastContainer';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'custom-toast-card';
        toast.innerHTML = 
            '<div class="d-flex justify-content-between align-items-start mb-1">' +
                '<strong class="text-primary text-truncate pe-2" style="font-size: 0.88rem;">' +
                    '<i class="' + (iconClass || 'bi bi-chat-dots-fill text-success') + ' me-1"></i>' + escapeHtml(title) +
                '</strong>' +
                '<button type="button" class="btn-close btn-close-toast" style="font-size: 0.65rem;" aria-label="Close"></button>' +
            '</div>' +
            '<p class="mb-0 text-muted text-truncate" style="font-size: 0.8rem; line-height: 1.3;">' +
                escapeHtml(text) +
            '</p>';

        function dismiss() {
            toast.classList.add('toast-hiding');
            setTimeout(function() {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }

        toast.addEventListener('click', function(e) {
            if (e.target.closest('.btn-close-toast')) {
                dismiss();
                return;
            }
            if (typeof link === 'function') {
                link();
            } else if (typeof link === 'string' && link) {
                window.location.href = link;
            }
        });

        var closeBtn = toast.querySelector('.btn-close-toast');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dismiss();
            });
        }

        container.appendChild(toast);

        // Auto dismiss after 6 seconds
        setTimeout(dismiss, 6000);
    }

    // Bind "Đã đọc tất cả" button in Header dropdown
    var headerMarkAllBtn = document.getElementById('headerMarkAllReadBtn');
    if (headerMarkAllBtn) {
        headerMarkAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var basePath = window.location.pathname.indexOf('/web_QuanLyBuaAn') !== -1 ? '/web_QuanLyBuaAn' : '';
            fetch(basePath + '/api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=all'
            })
            .then(function() {
                pollUnreadCounts();
            })
            .catch(function(err) {
                console.error('Error marking all notifications as read:', err);
            });
        });
    }

    // Realtime notification and support chat polling
    var lastKnownMsgId = null;
    var lastKnownContactId = null;

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

                // 2b. Cập nhật menu Hộp thư liên hệ trong Admin Sidebar
                var adminContactBadgeContainer = document.getElementById('adminSidebarContactBadgeContainer');
                if (adminContactBadgeContainer) {
                    if (data.unread_contacts > 0) {
                        adminContactBadgeContainer.innerHTML = '<span class="badge bg-danger rounded-pill">' + data.unread_contacts + '</span>';
                    } else {
                        adminContactBadgeContainer.innerHTML = '';
                    }
                }

                // 3. Cập nhật icon Chatbot nổi của User
                var userChatbotBadge = document.getElementById('userChatbotBadgeContainer');
                if (userChatbotBadge) {
                    if (!data.is_admin && data.unread_support > 0) {
                        userChatbotBadge.innerHTML = '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">' + data.unread_support + '</span>';
                    } else {
                        userChatbotBadge.innerHTML = '';
                    }
                }

                // 4. Đồng bộ danh sách thông báo động trong Header dropdown
                var dropdownItemsContainer = document.getElementById('headerNotificationDropdownItems');
                if (dropdownItemsContainer) {
                    var html = '';

                    // Mục tin nhắn hỗ trợ trực tuyến
                    if (data.is_admin) {
                        if (data.support_items && data.support_items.length > 0) {
                            data.support_items.forEach(function(usc) {
                                html += '<li>' +
                                    '<a class="notif-item notif-unread" href="' + basePath + '/admin/support-chats.php?user_id=' + usc.user_id + '">' +
                                        '<div class="d-flex w-100 justify-content-between align-items-center mb-1">' +
                                            '<div class="d-flex align-items-center me-2 flex-grow-1" style="min-width: 0;">' +
                                                '<span class="notif-unread-dot"></span>' +
                                                '<h6 class="mb-0 notif-item-title text-truncate">' +
                                                    '<i class="bi bi-chat-dots-fill me-1 text-success"></i>' + escapeHtml(usc.full_name) +
                                                '</h6>' +
                                            '</div>' +
                                            '<span class="badge bg-success rounded-pill text-nowrap flex-shrink-0" style="font-size: 0.65rem; font-weight: 600;">' + usc.unread_count + ' tin mới</span>' +
                                        '</div>' +
                                        '<p class="mb-1 notif-item-preview text-truncate">' +
                                            escapeHtml(usc.message) +
                                        '</p>' +
                                        '<small class="notif-item-time"><i class="bi bi-clock me-1"></i>' + formatShortDate(usc.created_at) + '</small>' +
                                    '</a>' +
                                '</li>';
                            });
                        }
                    } else {
                        if (data.unread_support > 0) {
                            html += '<li>' +
                                '<a class="notif-item notif-unread" href="#" onclick="var w=document.getElementById(\'chatbot-window\'); if(w){w.classList.remove(\'d-none\');} var at=document.getElementById(\'admin-tab\'); if(at){at.click();} return false;">' +
                                    '<div class="d-flex w-100 justify-content-between align-items-center mb-1">' +
                                        '<div class="d-flex align-items-center me-2 flex-grow-1" style="min-width: 0;">' +
                                            '<span class="notif-unread-dot"></span>' +
                                            '<h6 class="mb-0 notif-item-title text-truncate"><i class="bi bi-chat-dots-fill me-1 text-success"></i>Hỗ trợ trực tuyến</h6>' +
                                        '</div>' +
                                        '<span class="badge bg-success rounded-pill text-nowrap flex-shrink-0" style="font-size: 0.65rem;">' + data.unread_support + ' mới</span>' +
                                    '</div>' +
                                    '<p class="mb-0 notif-item-preview">' +
                                        'Bạn có phản hồi mới từ ban quản trị.' +
                                    '</p>' +
                                '</a>' +
                            '</li>';
                        }
                    }

                    // Danh sách thông báo hệ thống
                    if (data.recent_notifications && data.recent_notifications.length > 0) {
                        data.recent_notifications.forEach(function(n) {
                            var isUnread = !n.is_read;
                            var itemClass = isUnread ? 'notif-item notif-unread' : 'notif-item';
                            var unreadDot = isUnread ? '<span class="notif-unread-dot"></span>' : '';
                            var displayMsg = (n.message || '').replace(/^(\[UID:\d+\]|\[MID:\d+\])\s*/, '');
                            var targetLink = basePath + '/user/notifications.php?read=' + n.id + '#notif-' + n.id;

                            if (data.is_admin && n.title.indexOf('💬 Tin nhắn') !== -1) {
                                var uidMatch = (n.message || '').match(/\[UID:(\d+)\]/);
                                if (uidMatch && uidMatch[1]) {
                                    targetLink = basePath + '/admin/support-chats.php?user_id=' + uidMatch[1];
                                } else {
                                    targetLink = basePath + '/admin/support-chats.php';
                                }
                            } else if (data.is_admin && (n.title.indexOf('Thư liên hệ') !== -1 || n.title.indexOf('liên hệ') !== -1)) {
                                var midMatch = (n.message || '').match(/\[MID:(\d+)\]/);
                                if (midMatch && midMatch[1]) {
                                    targetLink = basePath + '/admin/contact-message-view.php?id=' + midMatch[1];
                                } else {
                                    targetLink = basePath + '/admin/contact-messages.php';
                                }
                            }

                            html += '<li>' +
                                '<a class="' + itemClass + '" href="' + targetLink + '">' +
                                    '<div class="d-flex w-100 justify-content-between align-items-center mb-1">' +
                                        '<div class="d-flex align-items-center me-2 flex-grow-1" style="min-width: 0;">' +
                                            unreadDot +
                                            '<h6 class="mb-0 notif-item-title text-truncate">' + escapeHtml(n.title) + '</h6>' +
                                        '</div>' +
                                        '<small class="notif-item-time text-nowrap flex-shrink-0">' + formatShortDate(n.created_at) + '</small>' +
                                    '</div>' +
                                    '<p class="mb-0 notif-item-preview text-truncate">' + escapeHtml(displayMsg) + '</p>' +
                                '</a>' +
                            '</li>';
                        });
                    } else if ((!data.support_items || data.support_items.length === 0) && data.unread_support === 0) {
                        html = '<li><div class="text-muted text-center py-4 small"><i class="bi bi-bell-slash d-block mb-1 fs-4 text-secondary opacity-50"></i>Không có thông báo mới</div></li>';
                    }

                    dropdownItemsContainer.innerHTML = html;
                }

                // 5. Hiển thị Toast thông báo nổi khi có tin nhắn mới tới
                var currentLatestId = parseInt(data.latest_msg_id, 10) || 0;
                if (lastKnownMsgId === null) {
                    lastKnownMsgId = currentLatestId;
                } else if (currentLatestId > lastKnownMsgId) {
                    lastKnownMsgId = currentLatestId;

                    if (data.is_admin && data.support_items && data.support_items.length > 0) {
                        var topMsg = data.support_items[0];
                        // Kiểm tra nếu admin đang mở đúng phòng chat của user này thì không cần popup toast
                        var urlParams = new URLSearchParams(window.location.search);
                        var currentChatUserId = urlParams.get('user_id');
                        var isCurrentChat = window.location.pathname.indexOf('/admin/support-chats.php') !== -1 && currentChatUserId == topMsg.user_id;

                        if (!isCurrentChat) {
                            showGlobalToast(
                                'Tin nhắn mới từ ' + topMsg.full_name,
                                topMsg.message,
                                basePath + '/admin/support-chats.php?user_id=' + topMsg.user_id,
                                'bi bi-chat-dots-fill text-success'
                            );
                        }
                    } else if (!data.is_admin && data.unread_support > 0 && data.support_items && data.support_items.length > 0) {
                        var userMsg = data.support_items[0];
                        showGlobalToast(
                            'Phản hồi từ Ban quản trị',
                            userMsg.message,
                            function() {
                                var w = document.getElementById('chatbot-window');
                                if (w) w.classList.remove('d-none');
                                var at = document.getElementById('admin-tab');
                                if (at) at.click();
                            },
                            'bi bi-shield-check text-primary'
                        );
                    }
                }

                // 5b. Hiển thị Toast khi có thư liên hệ mới gửi đến
                var currentLatestContactId = parseInt(data.latest_contact_id, 10) || 0;
                if (lastKnownContactId === null) {
                    lastKnownContactId = currentLatestContactId;
                } else if (currentLatestContactId > lastKnownContactId) {
                    lastKnownContactId = currentLatestContactId;
                    if (data.is_admin) {
                        showGlobalToast(
                            'Thư liên hệ mới!',
                            'Bạn có một thư liên hệ mới trong Hộp thư.',
                            basePath + '/admin/contact-messages.php',
                            'bi bi-envelope-fill text-success'
                        );
                    }
                }
            })
            .catch(function(err) {
                // Ignore network errors during background polling
            });
    }

    // Run immediately once and every 8 seconds
    pollUnreadCounts();
    setInterval(pollUnreadCounts, 8000);
});

// ==========================================================
// GLOBAL LENIS INERTIA SMOOTH SCROLL ENGINE
// ==========================================================
(function() {
    if (typeof Lenis === 'undefined') return;
    if (window.lenis) return;

    try {
        const lenis = new Lenis({
            duration: 1.15,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            orientation: 'vertical',
            gestureOrientation: 'vertical',
            smoothWheel: true,
            wheelMultiplier: 0.95,
            touchMultiplier: 1.5,
            infinite: false,
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);

        // Pause smooth scroll when Bootstrap modal or offcanvas is open
        document.addEventListener('show.bs.modal', function() {
            lenis.stop();
        });
        document.addEventListener('hidden.bs.modal', function() {
            lenis.start();
        });
        document.addEventListener('show.bs.offcanvas', function() {
            lenis.stop();
        });
        document.addEventListener('hidden.bs.offcanvas', function() {
            lenis.start();
        });

        // Global Anchor Smooth Scrolling (#...)
        document.addEventListener('click', function(e) {
            const anchor = e.target.closest('a[href*="#"]');
            if (!anchor) return;

            const href = anchor.getAttribute('href');
            if (!href) return;

            const hashIndex = href.indexOf('#');
            if (hashIndex === -1) return;

            const hash = href.substring(hashIndex);
            if (!hash || hash === '#' || hash === '#!' || hash.startsWith('#collapse') || anchor.hasAttribute('data-bs-toggle') || anchor.hasAttribute('data-bs-target')) {
                return;
            }

            const pathPart = href.substring(0, hashIndex);
            const currentPath = window.location.pathname;
            if (!pathPart || pathPart === '' || currentPath.endsWith(pathPart) || pathPart === window.location.href.split('#')[0]) {
                try {
                    const target = document.querySelector(hash);
                    if (target) {
                        e.preventDefault();
                        lenis.scrollTo(target, { offset: -90, duration: 1.2 });
                        if (history.pushState) {
                            history.pushState(null, null, hash);
                        }
                    }
                } catch (err) {
                    // Ignore selector errors
                }
            }
        });

        window.lenis = lenis;
    } catch (e) {
        console.warn('Lenis smooth scroll init error:', e);
    }
})();

