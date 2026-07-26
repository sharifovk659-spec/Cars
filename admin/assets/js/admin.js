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
                panel.classList.remove('is-fixed');
                panel.style.top = '';
                panel.style.left = '';
                panel.style.right = '';
                panel.style.bottom = '';
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            menu.classList.remove('open');
        });
    }

    function positionActionMenu(menu, panel, toggle) {
        panel.classList.add('is-fixed');
        panel.hidden = false;
        panel.style.top = '0px';
        panel.style.left = '0px';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';

        var toggleRect = toggle.getBoundingClientRect();
        var panelRect = panel.getBoundingClientRect();
        var gap = 8;
        var top = toggleRect.bottom + gap;
        var left = toggleRect.right - panelRect.width;

        if (top + panelRect.height > window.innerHeight - gap) {
            top = toggleRect.top - panelRect.height - gap;
        }
        if (top < gap) {
            top = gap;
        }
        if (left + panelRect.width > window.innerWidth - gap) {
            left = window.innerWidth - panelRect.width - gap;
        }
        if (left < gap) {
            left = gap;
        }

        panel.style.top = Math.round(top) + 'px';
        panel.style.left = Math.round(left) + 'px';
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
                if (!willOpen) {
                    return;
                }
                menu.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
                positionActionMenu(menu, panel, toggle);
            });

            panel.addEventListener('click', function (event) {
                event.stopPropagation();
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

        window.addEventListener('resize', function () {
            closeActionMenus();
        }, { passive: true });

        window.addEventListener('scroll', function () {
            closeActionMenus();
        }, true);
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
                deleteModalText.textContent = tr('delete_confirm') || 'Вы действительно хотите удалить эту машину?';
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
        var typeInput = document.getElementById('dashboardSearchType');
        var results = document.getElementById('dashboardSearchResults');
        var typingHint = document.getElementById('dashboardSearchTyping');
        var errorEl = document.getElementById('dashboardSearchError');
        var resetBtn = document.getElementById('dashboardSearchReset');
        var submitBtn = document.getElementById('dashboardSearchSubmit');
        var tabs = document.getElementById('dashboardSearchTabs');

        if (!form || !input || !results || !typeInput) {
            return;
        }

        var searchUrl = form.getAttribute('data-search-url') || '';
        var debounceTimer = null;
        var activeController = null;
        var requestSeq = 0;

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

        function currentType() {
            return String(typeInput.value || 'vin');
        }

        function normalizeQuery(type, raw) {
            var query = String(raw || '').trim();
            if (type === 'vin') {
                query = query.toUpperCase();
            }
            if (type === 'digits') {
                query = query.replace(/\D+/g, '');
            }
            if (type === 'phone') {
                query = query.replace(/[\s()\-]+/g, '');
            }
            return query;
        }

        function validateQuery(type, query) {
            if (!query) {
                return { ok: false, message: tr('dashboard_search_err_empty') };
            }
            if (type === 'digits') {
                if (!/^\d+$/.test(query)) {
                    return { ok: false, message: tr('dashboard_search_err_digits') };
                }
                if (query.length < 4) {
                    return { ok: false, message: tr('dashboard_search_err_digits_short') };
                }
            } else if (type === 'phone') {
                if (query.length < 3) {
                    return { ok: false, message: tr('dashboard_search_err_short') };
                }
            } else if (query.length < 2) {
                return { ok: false, message: tr('dashboard_search_err_short') };
            }
            return { ok: true, message: '' };
        }

        function toggleResetButton(query) {
            if (!resetBtn) {
                return;
            }
            resetBtn.hidden = query.length === 0;
        }

        function setSearching(isSearching) {
            form.classList.toggle('is-searching', !!isSearching);
            input.setAttribute('aria-busy', isSearching ? 'true' : 'false');
            if (submitBtn) {
                var label = submitBtn.querySelector('.dashboard-search-submit-label');
                var loading = submitBtn.querySelector('.dashboard-search-submit-loading');
                if (label) {
                    label.hidden = !!isSearching;
                }
                if (loading) {
                    loading.hidden = !isSearching;
                }
                submitBtn.disabled = !!isSearching;
            }
        }

        function showError(message) {
            if (!errorEl) {
                return;
            }
            if (!message) {
                errorEl.hidden = true;
                errorEl.textContent = '';
                return;
            }
            errorEl.hidden = false;
            errorEl.textContent = message;
        }

        function showTypingState(query, type) {
            showError('');
            if (query.length === 0) {
                results.hidden = true;
                if (typingHint) {
                    typingHint.hidden = true;
                }
                setSearching(false);
                return;
            }

            var validation = validateQuery(type, query);
            if (!validation.ok) {
                results.hidden = true;
                if (typingHint) {
                    typingHint.hidden = false;
                    typingHint.textContent = validation.message;
                }
                setSearching(false);
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
                '<div><dt>' + escapeHtml(tr('dashboard_contact_name')) + '</dt><dd>' + escapeHtml(car.contact_name || tr('common_dash')) + '</dd></div>' +
                '<div><dt>' + escapeHtml(tr('dashboard_contact')) + '</dt><dd>' + escapeHtml(car.contact_phone || tr('common_dash')) + '</dd></div>' +
                '<div><dt>' + escapeHtml(tr('dashboard_receive')) + '</dt><dd>' + escapeHtml(car.receive_display) + '</dd></div>' +
                '<div><dt>' + escapeHtml(tr('dashboard_upload')) + '</dt><dd>' + escapeHtml(car.upload_date) + '</dd></div>' +
                '<div><dt>' + escapeHtml(tr('dashboard_status')) + '</dt><dd>' + escapeHtml(car.status_label) + '</dd></div>' +
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
                    '<td>' + escapeHtml(car.name) + '</td>' +
                    '<td><a href="' + escapeHtml(car.view_url) + '"><code>' + escapeHtml(car.vin_code) + '</code></a></td>' +
                    '<td>' + escapeHtml(car.contact_name || tr('common_dash')) + '</td>' +
                    '<td>' + escapeHtml(car.contact_phone || tr('common_dash')) + '</td>' +
                    '<td>' + escapeHtml(car.receive_display) + '</td>' +
                    '<td>' + escapeHtml(car.upload_date) + '</td>' +
                    '<td><span class="badge ' + escapeHtml(car.status_class) + '">' + escapeHtml(car.status_label) + '</span></td>' +
                    '<td class="actions-cell"><a href="' + escapeHtml(car.view_url) + '" class="btn-link sm">' + escapeHtml(tr('dashboard_open')) + '</a></td>' +
                    '</tr>';
            }).join('');

            cards.innerHTML = cars.map(renderMobileCard).join('');
            results.hidden = false;
            scrollToResults();
        }

        function clearResultsUi() {
            results.hidden = true;
            var countEl = document.getElementById('dashboardSearchCount');
            var emptyEl = document.getElementById('dashboardSearchEmpty');
            var tableWrap = document.getElementById('dashboardSearchTable');
            var tbody = document.getElementById('dashboardSearchTbody');
            var cards = document.getElementById('dashboardSearchCards');
            if (countEl) {
                countEl.textContent = '0';
            }
            if (tbody) {
                tbody.innerHTML = '';
            }
            if (cards) {
                cards.innerHTML = '';
                cards.hidden = true;
            }
            if (emptyEl) {
                emptyEl.hidden = true;
            }
            if (tableWrap) {
                tableWrap.hidden = true;
            }
        }

        function runSearch(rawQuery, options) {
            options = options || {};
            var type = currentType();
            var query = normalizeQuery(type, rawQuery);
            if (query !== input.value && (type === 'vin' || type === 'digits' || type === 'phone')) {
                // keep user typing for phone/digits partially; only sync VIN uppercase softly on submit
            }
            if (options.forceNormalize) {
                input.value = query;
            }

            toggleResetButton(String(rawQuery || '').trim());
            showTypingState(query, type);

            var validation = validateQuery(type, query);
            if (!validation.ok) {
                if (options.showError) {
                    showError(validation.message);
                }
                return;
            }

            if (!searchUrl) {
                return;
            }

            if (activeController) {
                activeController.abort();
            }

            activeController = new AbortController();
            var seq = ++requestSeq;
            setSearching(true);
            showError('');
            results.hidden = false;

            var url = searchUrl
                + '?type=' + encodeURIComponent(type)
                + '&q=' + encodeURIComponent(query)
                + '&_=' + String(Date.now());

            fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: activeController.signal,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('search failed');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (seq !== requestSeq) {
                        return;
                    }
                    setSearching(false);
                    if (data && data.ok === false) {
                        showError(data.message || tr('dashboard_search_err_server'));
                        clearResultsUi();
                        return;
                    }
                    showError('');
                    renderResults(data || {});
                    if (window.history && window.history.replaceState) {
                        var next = window.location.pathname
                            + '?type=' + encodeURIComponent(type)
                            + '&q=' + encodeURIComponent(query);
                        window.history.replaceState({}, '', next);
                    }
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    if (seq === requestSeq) {
                        setSearching(false);
                        showError(tr('dashboard_search_err_server'));
                    }
                });
        }

        if (tabs) {
            tabs.querySelectorAll('[data-search-type]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var type = tab.getAttribute('data-search-type') || 'vin';
                    var placeholder = tab.getAttribute('data-placeholder') || '';
                    typeInput.value = type;
                    tabs.querySelectorAll('[data-search-type]').forEach(function (item) {
                        var active = item === tab;
                        item.classList.toggle('is-active', active);
                        item.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    if (placeholder) {
                        input.placeholder = placeholder;
                    }
                    input.setAttribute('inputmode', (type === 'digits' || type === 'phone') ? 'tel' : 'search');
                    input.focus();
                    clearTimeout(debounceTimer);
                    if (String(input.value || '').trim() !== '') {
                        runSearch(input.value, { showError: true, forceNormalize: true });
                    } else {
                        clearResultsUi();
                        showError('');
                        if (typingHint) {
                            typingHint.hidden = true;
                        }
                    }
                });
            });
        }

        input.addEventListener('input', function () {
            var type = currentType();
            var raw = input.value;
            if (type === 'vin') {
                var upper = raw.toUpperCase();
                if (upper !== raw) {
                    var start = input.selectionStart;
                    var end = input.selectionEnd;
                    input.value = upper;
                    if (typeof start === 'number' && typeof end === 'number') {
                        input.setSelectionRange(start, end);
                    }
                }
            }
            if (type === 'digits') {
                var digits = raw.replace(/\D+/g, '');
                if (digits !== raw) {
                    input.value = digits;
                }
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                runSearch(input.value);
            }, 180);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearTimeout(debounceTimer);
            runSearch(input.value, { showError: true, forceNormalize: true });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function (event) {
                event.preventDefault();
                clearTimeout(debounceTimer);
                if (activeController) {
                    activeController.abort();
                }
                requestSeq += 1;
                setSearching(false);
                input.value = '';
                toggleResetButton('');
                showError('');
                if (typingHint) {
                    typingHint.hidden = true;
                }
                clearResultsUi();
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', resetBtn.getAttribute('href') || window.location.pathname);
                }
            });
        }

        toggleResetButton(input.value.trim());
        if (input.value.trim() !== '') {
            runSearch(input.value, { forceNormalize: true });
        }
    }

    function syncStatusSelectTone(select) {
        if (!select) {
            return;
        }
        select.classList.remove('status-available', 'status-reserved', 'status-sold', 'status-archived');
        var value = String(select.value || '').trim();
        if (value) {
            select.classList.add('status-' + value);
        }
    }

    function initStatusSelectColors() {
        document.querySelectorAll('select.status-select').forEach(function (select) {
            syncStatusSelectTone(select);
            select.addEventListener('change', function () {
                syncStatusSelectTone(select);
            });
        });
    }

    initDashboardSearch();
    initStatusSelectColors();
    initCarsMobileCards();
    initDatePickers();

    function initCarsMobileCards() {
        var list = document.querySelector('.cars-mobile-list');
        if (list) {
            list.addEventListener('click', function (event) {
                var toggle = event.target.closest('.cars-mobile-card-toggle');
                if (!toggle || !list.contains(toggle)) {
                    return;
                }

                var card = toggle.closest('[data-cars-mobile-card]');
                if (!card) {
                    return;
                }

                var willOpen = !card.classList.contains('is-open');
                list.querySelectorAll('[data-cars-mobile-card].is-open').forEach(function (openCard) {
                    if (openCard === card) {
                        return;
                    }
                    openCard.classList.remove('is-open');
                    var openToggle = openCard.querySelector('.cars-mobile-card-toggle');
                    if (openToggle) {
                        openToggle.setAttribute('aria-expanded', 'false');
                    }
                });

                card.classList.toggle('is-open', willOpen);
                toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
        }

        var filters = document.querySelector('[data-cars-filters]');
        if (filters) {
            var filtersToggle = filters.querySelector('.cars-filters-toggle');
            if (filtersToggle) {
                filtersToggle.addEventListener('click', function () {
                    var willOpen = !filters.classList.contains('is-open');
                    filters.classList.toggle('is-open', willOpen);
                    filtersToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                });
            }
        }
    }

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
