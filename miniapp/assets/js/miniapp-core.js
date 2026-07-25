'use strict';

window.MiniAppCore = (function () {
    var tg = window.Telegram && window.Telegram.WebApp;
    var API_BASE = '../api';

    function isTelegram() {
        return !!(tg && tg.initData);
    }

    function initTelegram(options) {
        options = options || {};

        if (!tg) {
            document.body.classList.add('browser-mode');
            document.documentElement.setAttribute('data-theme', 'dark');
            return false;
        }

        tg.ready();
        tg.expand();
        applyTheme();

        if (tg.enableClosingConfirmation) {
            tg.enableClosingConfirmation();
        }

        if (tg.onEvent) {
            tg.onEvent('themeChanged', applyTheme);
        }

        if (options.showBack && tg.BackButton) {
            tg.BackButton.show();
            tg.BackButton.onClick(function () {
                if (options.onBack) {
                    options.onBack();
                } else if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = 'index.php';
                }
            });
        } else if (tg.BackButton) {
            tg.BackButton.hide();
        }

        setupMainButton(options);

        document.body.classList.add('telegram-mode');
        return true;
    }

    function setupMainButton(options) {
        if (!tg || !tg.MainButton) {
            return;
        }

        var label = options.mainButtonText || '';
        if (!label) {
            tg.MainButton.hide();
            return;
        }

        tg.MainButton.setText(label);
        tg.MainButton.color = tg.themeParams.button_color || '#2563eb';
        tg.MainButton.textColor = tg.themeParams.button_text_color || '#ffffff';
        tg.MainButton.show();

        if (options.onMainButton) {
            tg.MainButton.onClick(options.onMainButton);
        }
    }

    function applyTheme() {
        var root = document.documentElement;
        var p = (tg && tg.themeParams) || {};
        var fallback = {
            bg_color: '#0a0e17',
            text_color: '#f0f4ff',
            hint_color: '#7b8ba8',
            link_color: '#3b82f6',
            button_color: '#2563eb',
            button_text_color: '#ffffff',
            secondary_bg_color: '#121820'
        };

        Object.keys(fallback).forEach(function (key) {
            var cssKey = '--tg-theme-' + key.replace(/_/g, '-');
            root.style.setProperty(cssKey, p[key] || fallback[key]);
        });

        var bgColor = p.bg_color || fallback.bg_color;
        var isLight = isLightColor(bgColor);
        document.documentElement.setAttribute('data-theme', isLight ? 'light' : 'dark');
        document.body.classList.toggle('theme-light', isLight);
        document.body.classList.toggle('theme-dark', !isLight);

        root.style.setProperty('--neon-blue', p.link_color || '#3b82f6');
        root.style.setProperty('--neon-cyan', '#22d3ee');

        if (tg && tg.setHeaderColor) {
            tg.setHeaderColor(isLight ? '#ffffff' : '#0a0e17');
        }
        if (tg && tg.setBackgroundColor) {
            tg.setBackgroundColor(bgColor);
        }
    }

    function isLightColor(hex) {
        if (!hex || hex.charAt(0) !== '#') {
            return false;
        }
        var value = hex.slice(1);
        if (value.length === 3) {
            value = value.split('').map(function (c) { return c + c; }).join('');
        }
        if (value.length !== 6) {
            return false;
        }
        var r = parseInt(value.slice(0, 2), 16);
        var g = parseInt(value.slice(2, 4), 16);
        var b = parseInt(value.slice(4, 6), 16);
        var luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.62;
    }

    function getInitData() {
        return tg && tg.initData ? tg.initData : '';
    }

    function apiFetch(url, options) {
        options = options || {};
        var initData = getInitData();

        if (!initData) {
            return Promise.reject({ code: 'preview', message: 'Дар Telegram кушоед' });
        }

        var fetchOptions = {
            credentials: 'same-origin',
            headers: {
                'X-Telegram-Init-Data': initData,
                'Accept': 'application/json'
            }
        };

        if (options.signal) {
            fetchOptions.signal = options.signal;
        }

        return fetch(url, fetchOptions).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    var err = new Error(data.error || 'request_failed');
                    err.code = data.error || 'request_failed';
                    throw err;
                }
                return data;
            });
        });
    }

    function adminApiFetch(url, options) {
        options = options || {};
        var headers = Object.assign({
            'Accept': 'application/json'
        }, options.headers || {});

        var initData = getInitData();
        if (initData) {
            headers['X-Telegram-Init-Data'] = initData;
        }

        return fetch(url, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: headers,
            body: options.body || null
        }).then(function (response) {
            return response.text().then(function (text) {
                var data = null;

                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (parseError) {
                        var parseErr = new Error('server_response_invalid');
                        parseErr.code = 'server_response_invalid';
                        throw parseErr;
                    }
                }

                data = data || {};

                if (!response.ok) {
                    var err = new Error(data.error || 'request_failed');
                    err.code = data.error || 'request_failed';
                    throw err;
                }

                return data;
            });
        });
    }

    function adminApiPost(url, payload) {
        return adminApiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload || {})
        });
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return value;
        }
        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    function showScreen(screens, name) {
        Object.keys(screens).forEach(function (key) {
            if (screens[key]) {
                screens[key].classList.toggle('hidden', key !== name);
            }
        });
    }

    function renderGallery(elements, images) {
        var galleryTrack = elements.galleryTrack;
        var galleryDots = elements.galleryDots;
        var galleryCounter = elements.galleryCounter;
        var galleryEmpty = elements.galleryEmpty;

        galleryTrack.innerHTML = '';
        galleryDots.innerHTML = '';

        if (!images || images.length === 0) {
            galleryTrack.classList.add('hidden');
            galleryEmpty.classList.remove('hidden');
            galleryCounter.classList.add('hidden');
            galleryDots.classList.add('hidden');
            return;
        }

        galleryTrack.classList.remove('hidden');
        galleryEmpty.classList.add('hidden');
        galleryCounter.classList.remove('hidden');
        galleryDots.classList.toggle('hidden', images.length <= 1);

        images.forEach(function (image, index) {
            var slide = document.createElement('div');
            slide.className = 'gallery-slide';
            var img = document.createElement('img');
            img.alt = 'Фото ' + (index + 1);
            img.decoding = 'async';
            if (index === 0) {
                img.src = image.url;
                img.loading = 'eager';
                img.fetchPriority = 'high';
            } else {
                img.loading = 'lazy';
                img.dataset.src = image.url;
                img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="8" height="8"%3E%3C/svg%3E';
            }
            slide.appendChild(img);
            galleryTrack.appendChild(slide);

            if (images.length > 1) {
                var dot = document.createElement('span');
                dot.className = 'gallery-dot' + (index === 0 ? ' active' : '');
                galleryDots.appendChild(dot);
            }
        });

        galleryCounter.textContent = '1 / ' + images.length;

        function loadVisibleSlides() {
            var width = galleryTrack.offsetWidth || 1;
            var index = Math.round(galleryTrack.scrollLeft / width);
            [index - 1, index, index + 1].forEach(function (i) {
                if (i < 0 || i >= images.length) {
                    return;
                }
                var slideImg = galleryTrack.children[i] && galleryTrack.children[i].querySelector('img');
                if (slideImg && slideImg.dataset.src) {
                    slideImg.src = slideImg.dataset.src;
                    delete slideImg.dataset.src;
                }
            });
        }

        if (images.length > 1) {
            galleryTrack.onscroll = function () {
                var width = galleryTrack.offsetWidth;
                if (!width) {
                    return;
                }
                var index = Math.round(galleryTrack.scrollLeft / width);
                index = Math.max(0, Math.min(images.length - 1, index));
                galleryCounter.textContent = (index + 1) + ' / ' + images.length;
                galleryDots.querySelectorAll('.gallery-dot').forEach(function (dot, i) {
                    dot.classList.toggle('active', i === index);
                });
                loadVisibleSlides();
            };
            // Prefetch second photo shortly after first paint.
            setTimeout(loadVisibleSlides, 120);
        }
    }

    function formatUploadDisplayDate(value) {
        if (!value) {
            return '';
        }
        var parts = String(value).split('-');
        if (parts.length !== 3) {
            return String(value);
        }
        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    function uploadTypeParts(type, uploadNumber, uploadDateValue) {
        var parts = [type];
        var date = formatUploadDisplayDate(uploadDateValue);
        if (date !== '') {
            parts.push(date);
        }
        return parts.length > 1 ? parts.join(' · ') : type;
    }

    function renderUploadDisplay(car, container, fallbackElement) {
        var display = car.upload_display || null;
        var target = container || fallbackElement;
        if (!target) {
            return;
        }

        if (!display || !display.type) {
            target.innerHTML = '';
            if (fallbackElement) {
                fallbackElement.textContent = miniUploadTypeLabel(car);
                target.appendChild(fallbackElement);
            }
            return;
        }

        var html = '';
        html += '<span class="upload-chip upload-chip-type">' + display.type + '</span>';
        if (display.date) {
            html += '<span class="upload-chip upload-chip-date">' + display.date + '</span>';
        }
        target.innerHTML = html;
    }

    function miniUploadTypeLabel(car) {
        if (car.upload_type_label) {
            return car.upload_type_label;
        }
        var vagon = car.vagon ? String(car.vagon).trim() : '';
        var treiler = car.treiler ? String(car.treiler).trim() : '';
        if (vagon !== '') {
            return uploadTypeParts('Вагон', '', car.upload_date);
        }
        if (treiler !== '') {
            return uploadTypeParts('Трейлер', '', car.upload_date);
        }
        if (car.upload_date) {
            return formatDate(car.upload_date) || '—';
        }
        return '—';
    }

    function renderCarView(data, elements, displayValueFn) {
        var car = data.car;
        var display = displayValueFn || function (v) { return v || '—'; };

        elements.carName.textContent = car.name;
        elements.carVin.textContent = car.vin_code;

        if (elements.carNameSheet) {
            elements.carNameSheet.textContent = car.name;
        }
        if (elements.carSharja) {
            elements.carSharja.textContent = car.receive_display || formatDate(car.receive_date);
        }
        if (elements.carUploadStatus) {
            var uploadContainer = document.getElementById('car-upload-display');
            renderUploadDisplay(car, uploadContainer, elements.carUploadStatus);
        }

        var notes = car.notes || car.description || '';
        if (notes && elements.carNotes && elements.notesBlock) {
            elements.carNotes.textContent = notes;
            elements.notesBlock.classList.remove('hidden');
        } else if (elements.notesBlock) {
            elements.notesBlock.classList.add('hidden');
        }

        renderGallery(elements, car.images || []);
    }

    function showPreview(vin, screens) {
        var previewVin = document.getElementById('preview-vin');
        if (previewVin) {
            previewVin.textContent = vin || '—';
        }
        showScreen(screens, 'preview');
    }

    return {
        tg: tg,
        isTelegram: isTelegram,
        initTelegram: initTelegram,
        apiFetch: apiFetch,
        formatDate: formatDate,
        showScreen: showScreen,
        renderGallery: renderGallery,
        renderCarView: renderCarView,
        showPreview: showPreview,
        setupMainButton: setupMainButton,
        adminApiFetch: adminApiFetch,
        adminApiPost: adminApiPost,
        API_BASE: API_BASE
    };
})();
