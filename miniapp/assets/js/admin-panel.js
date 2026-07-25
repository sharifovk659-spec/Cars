'use strict';

(function () {
    var core = window.MiniAppCore;

    core.initTelegram({
        showBack: true,
        onBack: function () {
            window.location.href = 'index.php';
        },
        mainButtonText: ''
    });

    var logoutBtn = document.getElementById('admin-logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function () {
            core.adminApiPost('../api/admin/logout.php', {})
                .finally(function () {
                    window.location.href = 'index.php';
                });
        });
    }
})();
