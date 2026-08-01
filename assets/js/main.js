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
});
