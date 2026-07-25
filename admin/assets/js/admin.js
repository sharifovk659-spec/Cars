'use strict';

(function () {
    var I18N = window.ADMIN_I18N || {};

    function tr(key, vars) {
        var text = I18N[key] || '';
        if (!text) {
            return '';
        }
        if (vars) {
            Object.keys(vars).forEach(function (name) {
                text = text.split(':' + name).join(String(vars[name]));
            });
        }
        return text;
    }

    function closeActionMenus(exceptMenu) {
        document.querySelectorAll('[data-action-menu]').forEach(function (menu) {
            if (exceptMenu && menu === exceptMenu) {
                return;
            }
            var panel = menu.querySelector('.action-menu-panel');
            var toggle = menu.querySelector('.action-menu-toggle');
            if (panel) {
                panel.hidden = true;
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            menu.classList.remove('open');
        });
    }

    function initActionMenus() {
        document.querySelectorAll('[data-action-menu]').forEach(function (menu) {
            var toggle = menu.querySelector('.action-menu-toggle');
            var panel = menu.querySelector('.action-menu-panel');
            if (!toggle || !panel) {
                return;
            }

            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var willOpen = panel.hidden;
                closeActionMenus();
                panel.hidden = !willOpen;
                toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                menu.classList.toggle('open', willOpen);
            });
        });

        document.addEventListener('click', function () {
            closeActionMenus();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeActionMenus();
            }
        });
    }

    var toggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');

    if (toggle && sidebar) {
        function openSidebar() {
            sidebar.classList.add('open');
            if (backdrop) {
                backdrop.classList.add('visible');
            }
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            if (backdrop) {
                backdrop.classList.remove('visible');
            }
            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function () {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }

        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 960) {
                    closeSidebar();
                }
            });
        });
    }

    initActionMenus();

    var deleteModal = document.getElementById('deleteModal');
    var deleteForm = document.getElementById('deleteForm');
    var deleteCarId = document.getElementById('deleteCarId');
    var deleteModalText = document.getElementById('deleteModalText');
    var confirmDelete = document.getElementById('confirmDelete');

    if (deleteModal && deleteForm) {
        document.querySelectorAll('.btn-delete').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeActionMenus();
                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name');
                deleteCarId.value = id;
                deleteModalText.textContent = tr('delete_confirm', { name: name });
                deleteModal.hidden = false;
            });
        });

        deleteModal.querySelectorAll('[data-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                deleteModal.hidden = true;
            });
        });

        if (confirmDelete) {
            confirmDelete.addEventListener('click', function () {
                deleteForm.submit();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !deleteModal.hidden) {
                deleteModal.hidden = true;
            }
        });
    }
})();
