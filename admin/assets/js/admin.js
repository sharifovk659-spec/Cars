'use strict';

(function () {
    var toggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');

    if (!toggle || !sidebar) {
        return;
    }

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

    var deleteModal = document.getElementById('deleteModal');
    var deleteForm = document.getElementById('deleteForm');
    var deleteCarId = document.getElementById('deleteCarId');
    var deleteModalText = document.getElementById('deleteModalText');
    var confirmDelete = document.getElementById('confirmDelete');

    if (deleteModal && deleteForm) {
        document.querySelectorAll('.btn-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name');
                deleteCarId.value = id;
                deleteModalText.textContent = 'Вы уверены, что хотите удалить «' + name + '»?';
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
