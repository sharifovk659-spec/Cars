'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.login-form');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.btn-login span');
            if (btn) {
                btn.textContent = 'Вход...';
            }
        });
    }
});
