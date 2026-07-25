'use strict';

(function () {
    var core = window.MiniAppCore;

    var screens = {
        loading: document.getElementById('state-loading'),
        preview: document.getElementById('state-preview'),
        error: document.getElementById('state-error'),
        notFound: document.getElementById('state-not-found'),
        search: document.getElementById('state-search'),
        adminLogin: document.getElementById('state-admin-login')
    };

    var searchForm = document.getElementById('search-form');
    var searchInput = document.getElementById('search-input');
    var adminEntryBtn = document.getElementById('admin-entry-btn');
    var adminLoginForm = document.getElementById('admin-login-form');
    var adminLoginBack = document.getElementById('admin-login-back');
    var adminLoginError = document.getElementById('admin-login-error');
    var adminLoginInput = document.getElementById('admin-login-input');
    var adminPasswordInput = document.getElementById('admin-password-input');
    var adminRememberInput = document.getElementById('admin-remember-input');

    var isAdminFlow = false;

    function setMainButtonForScreen(screenName) {
        if (screenName === 'search' && core.isTelegram()) {
            core.setupMainButton({
                mainButtonText: 'Ҷустуҷӯ',
                onMainButton: function () {
                    searchForm.requestSubmit();
                }
            });
            return;
        }

        if (screenName === 'adminLogin' && core.isTelegram()) {
            core.setupMainButton({
                mainButtonText: 'Даромад',
                onMainButton: function () {
                    adminLoginForm.requestSubmit();
                }
            });
            return;
        }

        core.setupMainButton({ mainButtonText: '' });
    }

    function initTelegramUi(showBack) {
        core.initTelegram({
            showBack: !!showBack,
            onBack: function () {
                if (isAdminFlow) {
                    isAdminFlow = false;
                    core.showScreen(screens, 'search');
                    setMainButtonForScreen('search');
                    if (core.tg && core.tg.BackButton) {
                        core.tg.BackButton.hide();
                    }
                    return;
                }
                core.showScreen(screens, 'search');
                setMainButtonForScreen('search');
            },
            mainButtonText: ''
        });
    }

    initTelegramUi(false);

    function openAdminFlow() {
        isAdminFlow = true;

        if (!core.isTelegram()) {
            window.location.href = 'admin.php';
            return;
        }

        core.adminApiFetch('../api/admin/session.php')
            .then(function (data) {
                if (data.ok && data.authenticated) {
                    window.location.href = 'admin.php';
                    return;
                }
                showAdminLogin();
            })
            .catch(function () {
                showAdminLogin();
            });
    }

    function showAdminLogin() {
        core.showScreen(screens, 'adminLogin');
        setMainButtonForScreen('adminLogin');
        if (core.tg && core.tg.BackButton) {
            core.tg.BackButton.show();
        }
        if (adminLoginError) {
            adminLoginError.classList.add('hidden');
            adminLoginError.textContent = '';
        }
    }

    function showAdminLoginError(message) {
        if (!adminLoginError) {
            return;
        }
        adminLoginError.textContent = message;
        adminLoginError.classList.remove('hidden');
    }

    if (!core.isTelegram()) {
        core.showScreen(screens, 'preview');
    } else {
        core.showScreen(screens, 'search');
        setMainButtonForScreen('search');

        if (new URLSearchParams(window.location.search).get('admin') === '1') {
            openAdminFlow();
        }
    }

    var searchAbortController = null;
    var prefetchTimer = null;

    function cacheCarPayload(vinCode, data) {
        try {
            sessionStorage.setItem('tc_car_' + vinCode, JSON.stringify({
                car: data.car,
                expires: Date.now() + 120000
            }));
        } catch (e) {
            /* ignore cache errors */
        }
    }

    function searchCar(query) {
        query = (query || '').trim().toUpperCase();

        if (!query) {
            return;
        }

        if (!core.isTelegram()) {
            window.location.href = 'car.php?vin=' + encodeURIComponent(query);
            return;
        }

        if (searchAbortController) {
            searchAbortController.abort();
        }

        searchAbortController = new AbortController();
        core.showScreen(screens, 'loading');
        setMainButtonForScreen('loading');

        var url = core.API_BASE + '/car.php?vin=' + encodeURIComponent(query);

        core.apiFetch(url, { signal: searchAbortController.signal })
            .then(function (data) {
                var vinCode = data.car && data.car.vin_code ? data.car.vin_code : query;
                cacheCarPayload(vinCode, data);
                window.location.href = 'car.php?vin=' + encodeURIComponent(vinCode);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (err.code === 'not_found') {
                    core.showScreen(screens, 'notFound');
                    setMainButtonForScreen('notFound');
                    return;
                }
                document.getElementById('error-text').textContent = err.message || 'Хатогӣ';
                core.showScreen(screens, 'error');
                setMainButtonForScreen('error');
            })
            .finally(function () {
                searchAbortController = null;
            });
    }

    function schedulePrefetch(query) {
        if (prefetchTimer) {
            clearTimeout(prefetchTimer);
        }

        if (!core.isTelegram() || query.length < 4) {
            return;
        }

        prefetchTimer = setTimeout(function () {
            var url = core.API_BASE + '/car.php?vin=' + encodeURIComponent(query);
            core.apiFetch(url)
                .then(function (data) {
                    if (data && data.car && data.car.vin_code) {
                        cacheCarPayload(data.car.vin_code, data);
                    }
                })
                .catch(function () {
                    /* ignore prefetch errors */
                });
        }, 280);
    }

    searchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var query = (searchInput.value || '').trim().toUpperCase();
        if (query) {
            searchCar(query);
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            schedulePrefetch((searchInput.value || '').trim().toUpperCase());
        });
    }

    document.getElementById('back-search-btn').addEventListener('click', function () {
        core.showScreen(screens, 'search');
        setMainButtonForScreen('search');
    });

    if (adminEntryBtn) {
        adminEntryBtn.addEventListener('click', openAdminFlow);
    }

    if (adminLoginBack) {
        adminLoginBack.addEventListener('click', function () {
            isAdminFlow = false;
            core.showScreen(screens, 'search');
            setMainButtonForScreen('search');
            if (core.tg && core.tg.BackButton) {
                core.tg.BackButton.hide();
            }
        });
    }

    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var login = (adminLoginInput.value || '').trim();
            var password = adminPasswordInput.value || '';
            var remember = !!(adminRememberInput && adminRememberInput.checked);

            if (!login || !password) {
                showAdminLoginError('Логин ва паролро нависед');
                return;
            }

            showAdminLoginError('');
            adminLoginForm.classList.add('is-loading');

            core.adminApiPost('../api/admin/login.php', {
                login: login,
                password: password,
                remember: true
            })
                .then(function (data) {
                    window.location.href = (data && data.redirect) ? data.redirect : 'admin.php';
                })
                .catch(function (err) {
                    var message = err && err.message ? err.message : '';
                    if (message === 'server_response_invalid') {
                        message = 'Хатогии сервер. Боз такрор кунед.';
                    } else if (message === 'request_failed' || message === '') {
                        message = 'Логин ё парол нодуруст';
                    }
                    showAdminLoginError(message);
                })
                .finally(function () {
                    adminLoginForm.classList.remove('is-loading');
                });
        });
    }
})();
