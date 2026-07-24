'use strict';

(function () {
    var core = window.MiniAppCore;

    var screens = {
        loading: document.getElementById('state-loading'),
        preview: document.getElementById('state-preview'),
        error: document.getElementById('state-error'),
        notFound: document.getElementById('state-not-found'),
        search: document.getElementById('state-search')
    };

    var searchForm = document.getElementById('search-form');
    var searchInput = document.getElementById('search-input');

    core.initTelegram({
        showBack: false,
        mainButtonText: 'Ҷустуҷӯ',
        onMainButton: function () {
            searchForm.requestSubmit();
        }
    });

    if (!core.isTelegram()) {
        core.showScreen(screens, 'preview');
    } else {
        core.showScreen(screens, 'search');
    }

    function searchCar(query) {
        if (!core.isTelegram()) {
            window.location.href = 'car.php?vin=' + encodeURIComponent(query);
            return;
        }

        core.showScreen(screens, 'loading');

        core.apiFetch(core.API_BASE + '/search.php?q=' + encodeURIComponent(query))
            .then(function (data) {
                window.location.href = 'car.php?vin=' + encodeURIComponent(data.car.vin_code);
            })
            .catch(function (err) {
                if (err.code === 'not_found') {
                    core.showScreen(screens, 'notFound');
                    return;
                }
                document.getElementById('error-text').textContent = err.message || 'Хатогӣ';
                core.showScreen(screens, 'error');
            });
    }

    searchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var query = (searchInput.value || '').trim().toUpperCase();
        if (query) {
            searchCar(query);
        }
    });

    document.getElementById('back-search-btn').addEventListener('click', function () {
        core.showScreen(screens, 'search');
    });
})();
