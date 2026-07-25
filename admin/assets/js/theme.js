'use strict';

(function () {
    var STORAGE_KEY = 'admin-theme';
    var animating = false;

    function getTheme() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            /* ignore */
        }
        updateToggle(theme);
    }

    function updateToggle(theme) {
        var btn = document.getElementById('themeToggle');
        if (!btn) {
            return;
        }
        btn.setAttribute('data-theme-state', theme);
        var i18n = window.ADMIN_I18N || {};
        btn.setAttribute(
            'aria-label',
            theme === 'light' ? (i18n.theme_dark || 'Dark theme') : (i18n.theme_light || 'Light theme')
        );
    }

    function toggleTheme() {
        if (animating) {
            return;
        }

        var next = getTheme() === 'dark' ? 'light' : 'dark';
        var curtain = document.getElementById('themeCurtain');

        if (!curtain) {
            setTheme(next);
            return;
        }

        animating = true;
        curtain.className = 'theme-curtain to-' + next + ' drop';

        window.setTimeout(function () {
            setTheme(next);
            curtain.classList.remove('drop');
            curtain.classList.add('rise');
        }, 380);

        window.setTimeout(function () {
            curtain.className = 'theme-curtain';
            animating = false;
        }, 760);
    }

    function init() {
        updateToggle(getTheme());
        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', toggleTheme);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
