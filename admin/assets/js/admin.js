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

    function initDashboardSearch() {
        var form = document.getElementById('dashboardSearchForm');
        var input = document.getElementById('dashboardSearchInput');
        var results = document.getElementById('dashboardSearchResults');
        var typingHint = document.getElementById('dashboardSearchTyping');
        var resetBtn = document.getElementById('dashboardSearchReset');

        if (!form || !input || !results) {
            return;
        }

        var searchUrl = form.getAttribute('data-search-url') || '';
        var debounceTimer = null;
        var activeController = null;
        var minLength = 2;

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function isMobileView() {
            return window.matchMedia('(max-width: 960px)').matches;
        }

        function toggleResetButton(query) {
            if (!resetBtn) {
                return;
            }
            resetBtn.hidden = query.length === 0;
        }

        function showTypingState(query) {
            if (query.length === 0) {
                results.hidden = true;
                if (typingHint) {
                    typingHint.hidden = true;
                }
                return;
            }

            if (query.length < minLength) {
                results.hidden = true;
                if (typingHint) {
                    typingHint.hidden = false;
                }
                return;
            }

            if (typingHint) {
                typingHint.hidden = true;
            }
        }

        function scrollToResults() {
            if (!isMobileView()) {
                return;
            }
            results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function renderMobileCard(car) {
            var photo = car.main_image
                ? '<img src="' + escapeHtml(car.main_image) + '" alt="">'
                : '<span>' + escapeHtml(tr('dashboard_no_photo')) + '</span>';

            return '<article class="car-card dashboard-search-card glass">' +
                '<a href="' + escapeHtml(car.view_url) + '" class="car-card-top dashboard-search-card-link">' +
                '<div class="car-card-photo">' + photo + '</div>' +
                '<div class="dashboard-search-card-body">' +
                '<h3>' + escapeHtml(car.name) + '</h3>' +
                '<code>' + escapeHtml(car.vin_code) + '</code>' +
                '<span class="badge ' + escapeHtml(car.status_class) + '">' + escapeHtml(car.status_label) + '</span>' +
                '</div></a>' +
                '<dl class="car-card-meta dashboard-search-card-meta">' +
                '<div><dt>' + escapeHtml(tr('dashboard_receive')) + '</dt><dd>' + escapeHtml(car.receive_display) + '</dd></div>' +
                '<div><dt>' + escapeHtml(tr('dashboard_upload')) + '</dt><dd>' + escapeHtml(car.upload_date) + '</dd></div>' +
                '<div><dt>' + escapeHtml(tr('dashboard_contact')) + '</dt><dd>' + escapeHtml(car.contact_display || tr('common_dash')) + '</dd></div>' +
                '<div><dt>' + escapeHtml(tr('dashboard_photos_count')) + '</dt><dd>' + String(car.image_count) + '</dd></div>' +
                '</dl>' +
                '<a href="' + escapeHtml(car.view_url) + '" class="btn-primary sm dashboard-search-open">' + escapeHtml(tr('dashboard_open')) + '</a>' +
                '</article>';
        }

        function renderResults(data) {
            var cars = data.cars || [];
            var countEl = document.getElementById('dashboardSearchCount');
            var emptyEl = document.getElementById('dashboardSearchEmpty');
            var tableWrap = document.getElementById('dashboardSearchTable');
            var tbody = document.getElementById('dashboardSearchTbody');
            var cards = document.getElementById('dashboardSearchCards');

            if (countEl) {
                countEl.textContent = String(data.count || 0);
            }

            if (!tbody || !cards) {
                return;
            }

            if (cars.length === 0) {
                tbody.innerHTML = '';
                cards.innerHTML = '';
                if (emptyEl) {
                    emptyEl.hidden = false;
                }
                if (tableWrap) {
                    tableWrap.hidden = true;
                }
                cards.hidden = true;
                results.hidden = false;
                scrollToResults();
                return;
            }

            if (emptyEl) {
                emptyEl.hidden = true;
            }
            if (tableWrap) {
                tableWrap.hidden = !isMobileView() ? false : true;
            }
            cards.hidden = isMobileView() ? false : true;

            tbody.innerHTML = cars.map(function (car) {
                var photo = car.main_image
                    ? '<img src="' + escapeHtml(car.main_image) + '" alt="">'
                    : '<span class="no-photo">' + escapeHtml(tr('common_dash')) + '</span>';

                return '<tr>' +
                    '<td><div class="thumb">' + photo + '</div></td>' +
                    '<td><a href="' + escapeHtml(car.view_url) + '"><code>' + escapeHtml(car.vin_code) + '</code></a></td>' +
                    '<td>' + escapeHtml(car.name) + '</td>' +
                    '<td><span class="badge ' + escapeHtml(car.status_class) + '">' + escapeHtml(car.status_label) + '</span></td>' +
                    '<td>' + escapeHtml(car.receive_display) + '</td>' +
                    '<td>' + escapeHtml(car.upload_date) + '</td>' +
                    '<td>' + String(car.image_count) + '</td>' +
                    '<td class="actions-cell"><a href="' + escapeHtml(car.view_url) + '" class="btn-link sm">' + escapeHtml(tr('dashboard_open')) + '</a></td>' +
                    '</tr>';
            }).join('');

            cards.innerHTML = cars.map(renderMobileCard).join('');
            results.hidden = false;
            scrollToResults();
        }

        function runSearch(query) {
            toggleResetButton(query);
            showTypingState(query);

            if (!searchUrl || query.length < minLength) {
                return;
            }

            if (activeController) {
                activeController.abort();
            }

            activeController = new AbortController();
            results.hidden = false;

            fetch(searchUrl + '?q=' + encodeURIComponent(query), {
                credentials: 'same-origin',
                signal: activeController.signal,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('search failed');
                    }
                    return response.json();
                })
                .then(function (data) {
                    renderResults(data);
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                });
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                runSearch(query);
            }, 280);
        });

        form.addEventListener('submit', function (event) {
            var query = input.value.trim();
            toggleResetButton(query);
            if (query.length < minLength) {
                event.preventDefault();
                showTypingState(query);
            }
        });

        toggleResetButton(input.value.trim());
    }

    initDashboardSearch();
    initDatePickers();

    function initDatePickers() {
        document.querySelectorAll('.date-picker-field').forEach(function (input) {
            function openPicker() {
                if (typeof input.showPicker === 'function') {
                    try {
                        input.showPicker();
                    } catch (e) {
                        // ignore
                    }
                }
            }

            input.addEventListener('click', openPicker);
            input.addEventListener('focus', function () {
                if (window.matchMedia('(max-width: 960px)').matches) {
                    openPicker();
                }
            });
        });
    }
})();
