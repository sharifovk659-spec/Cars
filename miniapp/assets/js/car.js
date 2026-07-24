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
        carReceive: document.getElementById('car-receive'),
        carUpload: document.getElementById('car-upload'),
        carContact: document.getElementById('car-contact'),
        carPhone: document.getElementById('car-phone'),
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
            window.location.href = 'index.html';
        },
        mainButtonText: '',
        onMainButton: null
    });

    function loadCar() {
        if (!vin) {
            window.location.href = 'index.html';
            return;
        }

        core.showScreen(screens, 'loading');

        if (!core.isTelegram()) {
            core.showPreview(vin, screens);
            return;
        }

        var url = core.API_BASE + '/car.php?vin=' + encodeURIComponent(vin);

        core.apiFetch(url)
            .then(function (data) {
                core.renderCarView(data, elements);
                core.showScreen(screens, 'car');

                if (core.tg && core.tg.MainButton && data.car.contact_phone) {
                    core.setupMainButton({
                        mainButtonText: '📞 ' + data.car.contact_phone,
                        onMainButton: function () {
                            window.location.href = 'tel:' + data.car.contact_phone;
                        }
                    });
                }
            })
            .catch(function (err) {
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
        window.location.href = 'index.html';
    });

    loadCar();
})();
