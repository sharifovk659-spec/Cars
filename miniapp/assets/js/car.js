'use strict';

(function () {
    var core = window.MiniAppCore;
    var app = document.getElementById('app');
    var vin = (app && app.dataset.vin) || '';

    var screens = {
        loading: document.getElementById('state-loading'),
        preview: document.getElementById('state-preview'),
        error: document.getElementById('state-error'),
        notFound: document.getElementById('state-not-found'),
        car: document.getElementById('state-car')
    };

    var elements = {
        galleryTrack: document.getElementById('gallery-track'),
        galleryDots: document.getElementById('gallery-dots'),
        galleryCounter: document.getElementById('gallery-counter'),
        galleryEmpty: document.getElementById('gallery-empty'),
        carName: document.getElementById('car-name'),
        carVin: document.getElementById('car-vin'),
        carStatus: document.getElementById('car-status'),
        carNameSheet: document.getElementById('car-name-sheet'),
        carSharja: document.getElementById('car-sharja'),
        carUploadStatus: document.getElementById('car-upload-status'),
        carNotes: document.getElementById('car-notes'),
        notesBlock: document.getElementById('notes-block')
    };

    var params = new URLSearchParams(window.location.search);
    if (!vin) {
        vin = (params.get('vin') || '').trim().toUpperCase();
    }

    core.initTelegram({
        showBack: true,
        onBack: function () {
            window.location.href = 'index.php';
        },
        mainButtonText: '',
        onMainButton: null
    });

    function displayValue(value) {
        return value && String(value).trim() !== '' ? value : '—';
    }

    function loadCar() {
        if (!vin) {
            window.location.href = 'index.php';
            return;
        }

        core.showScreen(screens, 'loading');

        if (!core.isTelegram()) {
            core.showPreview(vin, screens);
            return;
        }

        var cacheKey = 'tc_car_' + vin;
        try {
            var cached = sessionStorage.getItem(cacheKey);
            if (cached) {
                var parsed = JSON.parse(cached);
                if (parsed && parsed.car && parsed.expires > Date.now()) {
                    core.renderCarView(parsed, elements, displayValue);
                    core.showScreen(screens, 'car');
                    setupPhoneButton(parsed.car);
                    fetchCar(true);
                    return;
                }
            }
        } catch (e) {
            /* ignore cache errors */
        }

        fetchCar(false);
    }

    function setupPhoneButton(car) {
        if (core.tg && core.tg.MainButton && car.contact_phone) {
            core.setupMainButton({
                mainButtonText: '📞 ' + car.contact_phone,
                onMainButton: function () {
                    window.location.href = 'tel:' + car.contact_phone;
                }
            });
        }
    }

    function fetchCar(isBackground) {
        var url = core.API_BASE + '/car.php?vin=' + encodeURIComponent(vin);

        core.apiFetch(url)
            .then(function (data) {
                try {
                    sessionStorage.setItem('tc_car_' + vin, JSON.stringify({
                        car: data.car,
                        expires: Date.now() + 120000
                    }));
                } catch (e) {
                    /* ignore */
                }

                core.renderCarView(data, elements, displayValue);
                if (!isBackground) {
                    core.showScreen(screens, 'car');
                }
                setupPhoneButton(data.car);
            })
            .catch(function (err) {
                if (isBackground) {
                    return;
                }
                if (err.code === 'not_found') {
                    var nf = document.getElementById('not-found-vin');
                    if (nf) {
                        nf.textContent = vin;
                    }
                    core.showScreen(screens, 'notFound');
                    return;
                }
                document.getElementById('error-text').textContent = err.message || 'Хатогӣ';
                core.showScreen(screens, 'error');
            });
    }

    document.getElementById('retry-btn').addEventListener('click', loadCar);
    document.getElementById('back-search-btn').addEventListener('click', function () {
        window.location.href = 'index.php';
    });

    loadCar();
})();
