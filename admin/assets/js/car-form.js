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

    var MAX_IMAGES = Math.max(1, parseInt(String(window.CAR_FORM_MAX_IMAGES || 5), 10) || 5);
    var MAX_SIZE = 20 * 1024 * 1024; // raw phone photo before compress
    var MAX_OUTPUT = 5 * 1024 * 1024;
    var TARGET_WIDTH = 1600;
    var JPEG_QUALITY = 0.82;
    var isEdit = window.CAR_FORM_MODE === 'edit';

    var previewGrid = document.getElementById('previewGrid');
    var imageInput = document.getElementById('imageInput');
    var mainImageInput = document.getElementById('mainImageInput');
    var previewHint = document.getElementById('previewHint');

    var newPreviewGrid = document.getElementById('newPreviewGrid');
    var newImageInput = document.getElementById('newImageInput');
    var existingImages = document.getElementById('existingImages');
    var mainImageId = document.getElementById('mainImageId');
    var mainNewIndex = document.getElementById('mainNewIndex');

    var selectedFiles = [];
    var newFiles = [];
    var compressBusy = false;

    function isProbablyImage(file) {
        if (!file) {
            return false;
        }
        if (file.type && file.type.indexOf('image/') === 0) {
            return true;
        }
        return /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(file.name || '');
    }

    function validateFile(file) {
        if (!isProbablyImage(file)) {
            return tr('file_type');
        }
        if (file.size > MAX_SIZE) {
            return tr('file_size');
        }
        return null;
    }

    function loadImageFromFile(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                if (!window.createImageBitmap) {
                    reject(new Error('decode'));
                    return;
                }
                createImageBitmap(file).then(resolve).catch(function () {
                    reject(new Error('decode'));
                });
            };
            img.src = url;
        });
    }

    function canvasFromSource(source) {
        var width = source.width || source.naturalWidth || 0;
        var height = source.height || source.naturalHeight || 0;
        if (!width || !height) {
            throw new Error('size');
        }

        var scale = width > TARGET_WIDTH ? TARGET_WIDTH / width : 1;
        var outW = Math.max(1, Math.round(width * scale));
        var outH = Math.max(1, Math.round(height * scale));
        var canvas = document.createElement('canvas');
        canvas.width = outW;
        canvas.height = outH;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('canvas');
        }
        ctx.drawImage(source, 0, 0, outW, outH);
        return canvas;
    }

    function canvasToJpegFile(canvas, baseName) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('blob'));
                    return;
                }
                var name = (baseName || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
                resolve(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
            }, 'image/jpeg', JPEG_QUALITY);
        });
    }

    function prepareImageFile(file) {
        // Already small jpeg/png/webp — keep as-is for speed.
        if (
            (file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/webp')
            && file.size <= 1.5 * 1024 * 1024
        ) {
            return Promise.resolve(file);
        }

        return loadImageFromFile(file)
            .then(function (source) {
                var canvas = canvasFromSource(source);
                if (source.close) {
                    try { source.close(); } catch (e) { /* ignore */ }
                }
                return canvasToJpegFile(canvas, file.name);
            })
            .then(function (outFile) {
                if (outFile.size > MAX_OUTPUT) {
                    // Second pass with stronger compression.
                    return loadImageFromFile(outFile).then(function (source) {
                        var canvas = canvasFromSource(source);
                        if (source.close) {
                            try { source.close(); } catch (e) { /* ignore */ }
                        }
                        return new Promise(function (resolve, reject) {
                            canvas.toBlob(function (blob) {
                                if (!blob) {
                                    reject(new Error('blob'));
                                    return;
                                }
                                resolve(new File([blob], outFile.name, { type: 'image/jpeg', lastModified: Date.now() }));
                            }, 'image/jpeg', 0.7);
                        });
                    });
                }
                return outFile;
            });
    }

    function ensureUploadOverlay() {
        var overlay = document.getElementById('uploadBusyOverlay');
        if (overlay) {
            return overlay;
        }
        overlay = document.createElement('div');
        overlay.id = 'uploadBusyOverlay';
        overlay.className = 'upload-busy-overlay hidden';
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML =
            '<div class="upload-busy-stage">' +
            '<div class="upload-busy-ring" id="uploadBusyRing" aria-hidden="true">' +
            '<span class="upload-busy-pct" id="uploadBusyPct">0%</span>' +
            '</div>' +
            '<p class="upload-busy-label" id="uploadBusyLabel">' + (tr('compressing_photos') || 'Загрузка…') + '</p>' +
            '<p class="upload-busy-sub" id="uploadBusySub">0 / 100</p>' +
            '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    function setUploadProgress(percent, label) {
        var overlay = ensureUploadOverlay();
        var ring = document.getElementById('uploadBusyRing');
        var pctEl = document.getElementById('uploadBusyPct');
        var labelEl = document.getElementById('uploadBusyLabel');
        var subEl = document.getElementById('uploadBusySub');
        var value = Math.max(0, Math.min(100, Math.round(percent)));

        overlay.classList.remove('hidden');
        if (ring) {
            ring.style.setProperty('--progress', String(value));
        }
        if (pctEl) {
            pctEl.textContent = value + '%';
        }
        if (labelEl && label) {
            labelEl.textContent = label;
        }
        if (subEl) {
            subEl.textContent = value + ' / 100';
        }
    }

    function setUploadBusy(busy, label) {
        compressBusy = busy;
        document.querySelectorAll('#carForm button[type="submit"], #editCarForm button[type="submit"]').forEach(function (btn) {
            btn.disabled = busy;
        });
        var overlay = ensureUploadOverlay();
        if (busy) {
            overlay.classList.remove('hidden');
            document.body.classList.add('upload-busy-lock');
            setUploadProgress(0, label || tr('compressing_photos') || 'Загрузка…');
        } else {
            overlay.classList.add('hidden');
            document.body.classList.remove('upload-busy-lock');
        }
        if (previewHint) {
            if (busy) {
                if (!previewHint.dataset.defaultText) {
                    previewHint.dataset.defaultText = previewHint.textContent;
                }
                previewHint.hidden = false;
                previewHint.textContent = label || tr('compressing_photos') || 'Оптимизация фото...';
            } else {
                previewHint.textContent = previewHint.dataset.defaultText || previewHint.textContent;
                previewHint.hidden = selectedFiles.length > 0 || newFiles.length > 0;
            }
        }
    }

    function animateProgressTo(target, durationMs, label) {
        return new Promise(function (resolve) {
            var start = Date.now();
            var from = 0;
            var ring = document.getElementById('uploadBusyRing');
            if (ring) {
                from = parseFloat(ring.style.getPropertyValue('--progress')) || 0;
            }
            function tick() {
                var t = Math.min(1, (Date.now() - start) / Math.max(1, durationMs));
                var eased = 1 - Math.pow(1 - t, 3);
                setUploadProgress(from + (target - from) * eased, label);
                if (t < 1) {
                    requestAnimationFrame(tick);
                } else {
                    resolve();
                }
            }
            requestAnimationFrame(tick);
        });
    }

    function submitFormWithProgress(form) {
        return new Promise(function (resolve, reject) {
            var formData = new FormData(form);
            var xhr = new XMLHttpRequest();
            xhr.open(form.method || 'POST', form.action || window.location.href, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.onprogress = function (event) {
                if (!event.lengthComputable || event.total <= 0) {
                    return;
                }
                var pct = Math.min(92, Math.round((event.loaded / event.total) * 90) + 2);
                setUploadProgress(pct, tr('publishing_car') || 'Публикация…');
            };

            xhr.upload.onload = function () {
                setUploadProgress(94, tr('publishing_car') || 'Публикация…');
            };

            xhr.onerror = function () {
                reject(new Error('network'));
            };

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 400) {
                    setUploadProgress(100, tr('publishing_car') || 'Публикация…');
                    var url = xhr.responseURL || window.location.href;
                    if (/cars\/(index|view)\.php/i.test(url)) {
                        resolve(url);
                        return;
                    }
                    document.open();
                    document.write(xhr.responseText);
                    document.close();
                    resolve(null);
                    return;
                }
                reject(new Error('http_' + xhr.status));
            };

            xhr.send(formData);
        });
    }

    document.querySelectorAll('#carForm, #editCarForm').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (compressBusy) {
                event.preventDefault();
            }
        });
    });

    function swapArrayItems(list, indexA, indexB) {
        var temp = list[indexA];
        list[indexA] = list[indexB];
        list[indexB] = temp;
    }

    function adjustMainIndex(mainIndex, fromIndex, toIndex) {
        if (mainIndex === fromIndex) {
            return toIndex;
        }
        if (fromIndex < mainIndex && toIndex >= mainIndex) {
            return mainIndex - 1;
        }
        if (fromIndex > mainIndex && toIndex <= mainIndex) {
            return mainIndex + 1;
        }
        return mainIndex;
    }

    function moveArrayItem(list, index, direction) {
        var toIndex = direction === 'left' ? index - 1 : index + 1;
        if (toIndex < 0 || toIndex >= list.length) {
            return index;
        }
        swapArrayItems(list, index, toIndex);
        return toIndex;
    }

    function createActionButton(className, label, title) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-ghost xs ' + className;
        btn.textContent = label;
        btn.title = title;
        return btn;
    }

    function appendStandardActions(actions, options) {
        var mainBtn = createActionButton('set-main-btn', tr('main_photo'), tr('main_photo'));
        mainBtn.addEventListener('click', options.onMain);

        var leftBtn = createActionButton('move-left', '←', tr('move_back'));
        leftBtn.addEventListener('click', options.onLeft);

        var rightBtn = createActionButton('move-right', '→', tr('move_forward'));
        rightBtn.addEventListener('click', options.onRight);

        actions.appendChild(mainBtn);
        actions.appendChild(leftBtn);
        actions.appendChild(rightBtn);

        if (options.onRemove) {
            var removeBtn = createActionButton('', '✕', tr('remove_photo'));
            removeBtn.addEventListener('click', options.onRemove);
            actions.appendChild(removeBtn);
        }
    }

    function renderAddPreviews() {
        if (!previewGrid) {
            return;
        }

        previewGrid.innerHTML = '';
        var mainIndex = parseInt(mainImageInput.value, 10) || 0;

        selectedFiles.forEach(function (item, index) {
            var wrap = document.createElement('div');
            wrap.className = 'preview-item';
            wrap.dataset.index = String(index);
            if (index === mainIndex) {
                wrap.classList.add('is-main');
            }

            var img = document.createElement('img');
            img.src = item.url;
            img.alt = item.file.name;
            img.addEventListener('click', function () {
                mainImageInput.value = String(index);
                renderAddPreviews();
            });

            var badge = document.createElement('span');
            badge.className = 'main-badge';
            badge.textContent = tr('main_photo');

            var actions = document.createElement('div');
            actions.className = 'preview-actions';

            appendStandardActions(actions, {
                onMain: function () {
                    mainImageInput.value = String(index);
                    renderAddPreviews();
                },
                onLeft: function () {
                    if (index === 0) {
                        return;
                    }
                    swapArrayItems(selectedFiles, index, index - 1);
                    mainImageInput.value = String(adjustMainIndex(mainIndex, index, index - 1));
                    syncAddInputFiles();
                    renderAddPreviews();
                },
                onRight: function () {
                    if (index >= selectedFiles.length - 1) {
                        return;
                    }
                    swapArrayItems(selectedFiles, index, index + 1);
                    mainImageInput.value = String(adjustMainIndex(mainIndex, index, index + 1));
                    syncAddInputFiles();
                    renderAddPreviews();
                },
                onRemove: function () {
                    URL.revokeObjectURL(item.url);
                    selectedFiles.splice(index, 1);
                    var nextMain = mainIndex;
                    if (index === mainIndex) {
                        nextMain = 0;
                    } else if (index < mainIndex) {
                        nextMain = mainIndex - 1;
                    }
                    mainImageInput.value = String(Math.max(0, Math.min(nextMain, selectedFiles.length - 1)));
                    syncAddInputFiles();
                    renderAddPreviews();
                }
            });

            wrap.appendChild(img);
            wrap.appendChild(badge);
            wrap.appendChild(actions);
            previewGrid.appendChild(wrap);
        });

        if (previewHint) {
            previewHint.hidden = selectedFiles.length > 0;
        }
    }

    function syncAddInputFiles() {
        if (!imageInput) {
            return;
        }
        var dt = new DataTransfer();
        selectedFiles.forEach(function (item) {
            dt.items.add(item.file);
        });
        imageInput.files = dt.files;
    }

    if (imageInput) {
        if (previewHint && !previewHint.dataset.defaultText) {
            previewHint.dataset.defaultText = previewHint.textContent;
        }
        imageInput.addEventListener('change', function () {
            var incoming = Array.from(imageInput.files || []);
            if (incoming.length === 0) {
                return;
            }

            var compressLabel = tr('compressing_photos') || 'Загрузка…';
            setUploadBusy(true, compressLabel);
            setUploadProgress(0, compressLabel);
            var done = 0;
            var total = Math.max(1, incoming.length);
            var queue = Promise.resolve();

            incoming.forEach(function (file) {
                queue = queue.then(function () {
                    function bump() {
                        done += 1;
                        setUploadProgress((done / total) * 100, compressLabel);
                    }
                    if (selectedFiles.length >= MAX_IMAGES) {
                        bump();
                        return;
                    }
                    var err = validateFile(file);
                    if (err) {
                        alert(file.name + ': ' + err);
                        bump();
                        return;
                    }
                    return prepareImageFile(file).then(function (ready) {
                        if (selectedFiles.length >= MAX_IMAGES) {
                            return;
                        }
                        selectedFiles.push({ file: ready, url: URL.createObjectURL(ready) });
                    }).catch(function () {
                        alert(file.name + ': ' + tr('file_type'));
                    }).finally(bump);
                });
            });

            queue.finally(function () {
                if (selectedFiles.length === 1) {
                    mainImageInput.value = '0';
                }
                syncAddInputFiles();
                renderAddPreviews();
                animateProgressTo(100, 180, compressLabel).then(function () {
                    setUploadBusy(false);
                    imageInput.value = '';
                });
            });
        });
    }

    function renderNewPreviews() {
        if (!newPreviewGrid) {
            return;
        }

        newPreviewGrid.innerHTML = '';
        var mainNew = parseInt(mainNewIndex.value, 10);

        newFiles.forEach(function (item, index) {
            var wrap = document.createElement('div');
            wrap.className = 'preview-item';
            if (mainNew === index) {
                wrap.classList.add('is-main');
            }

            var img = document.createElement('img');
            img.src = item.url;
            img.alt = item.file.name;
            img.addEventListener('click', function () {
                mainNewIndex.value = String(index);
                if (mainImageId) {
                    mainImageId.value = '0';
                }
                updateExistingMainBadges();
                renderNewPreviews();
            });

            var badge = document.createElement('span');
            badge.className = 'main-badge';
            badge.textContent = tr('main_photo');

            var actions = document.createElement('div');
            actions.className = 'preview-actions';

            appendStandardActions(actions, {
                onMain: function () {
                    mainNewIndex.value = String(index);
                    if (mainImageId) {
                        mainImageId.value = '0';
                    }
                    updateExistingMainBadges();
                    renderNewPreviews();
                },
                onLeft: function () {
                    if (index === 0) {
                        return;
                    }
                    swapArrayItems(newFiles, index, index - 1);
                    mainNewIndex.value = String(adjustMainIndex(mainNew, index, index - 1));
                    syncNewInputFiles();
                    renderNewPreviews();
                },
                onRight: function () {
                    if (index >= newFiles.length - 1) {
                        return;
                    }
                    swapArrayItems(newFiles, index, index + 1);
                    mainNewIndex.value = String(adjustMainIndex(mainNew, index, index + 1));
                    syncNewInputFiles();
                    renderNewPreviews();
                },
                onRemove: function () {
                    URL.revokeObjectURL(item.url);
                    newFiles.splice(index, 1);
                    var nextMain = mainNew;
                    if (mainNew === index) {
                        nextMain = -1;
                    } else if (mainNew > index) {
                        nextMain = mainNew - 1;
                    }
                    mainNewIndex.value = String(nextMain);
                    syncNewInputFiles();
                    renderNewPreviews();
                }
            });

            wrap.appendChild(img);
            wrap.appendChild(badge);
            wrap.appendChild(actions);
            newPreviewGrid.appendChild(wrap);
        });
    }

    function syncNewInputFiles() {
        if (!newImageInput) {
            return;
        }
        var dt = new DataTransfer();
        newFiles.forEach(function (item) {
            dt.items.add(item.file);
        });
        newImageInput.files = dt.files;
    }

    if (newImageInput) {
        newImageInput.addEventListener('change', function () {
            var incoming = Array.from(newImageInput.files || []);
            if (incoming.length === 0) {
                return;
            }

            var compressLabel = tr('compressing_photos') || 'Загрузка…';
            setUploadBusy(true, compressLabel);
            setUploadProgress(0, compressLabel);
            var done = 0;
            var total = Math.max(1, incoming.length);
            var queue = Promise.resolve();

            incoming.forEach(function (file) {
                queue = queue.then(function () {
                    function bump() {
                        done += 1;
                        setUploadProgress((done / total) * 100, compressLabel);
                    }
                    var existingCount = existingImages
                        ? existingImages.querySelectorAll('.preview-item:not(.marked-delete)').length
                        : 0;
                    if (existingCount + newFiles.length >= MAX_IMAGES) {
                        alert(tr('max_photos', { max: String(MAX_IMAGES) }));
                        bump();
                        return;
                    }
                    var err = validateFile(file);
                    if (err) {
                        alert(file.name + ': ' + err);
                        bump();
                        return;
                    }
                    return prepareImageFile(file).then(function (ready) {
                        var visibleCount = existingImages
                            ? existingImages.querySelectorAll('.preview-item:not(.marked-delete)').length
                            : 0;
                        if (visibleCount + newFiles.length >= MAX_IMAGES) {
                            return;
                        }
                        newFiles.push({ file: ready, url: URL.createObjectURL(ready) });
                    }).catch(function () {
                        alert(file.name + ': ' + tr('file_type'));
                    }).finally(bump);
                });
            });

            queue.finally(function () {
                syncNewInputFiles();
                renderNewPreviews();
                animateProgressTo(100, 180, compressLabel).then(function () {
                    setUploadBusy(false);
                    newImageInput.value = '';
                });
            });
        });
    }

    function getVisibleExistingItems() {
        if (!existingImages) {
            return [];
        }
        return Array.from(existingImages.querySelectorAll('.preview-item')).filter(function (item) {
            return !item.classList.contains('marked-delete');
        });
    }

    function updateExistingMainBadges() {
        if (!existingImages) {
            return;
        }

        existingImages.querySelectorAll('.preview-item').forEach(function (item) {
            item.classList.remove('is-main');
            var badge = item.querySelector('.main-badge');
            if (badge) {
                badge.style.display = 'none';
            }
        });

        var mainId = parseInt(mainImageId ? mainImageId.value : '0', 10);
        if (mainId > 0) {
            var mainItem = existingImages.querySelector('.preview-item[data-id="' + mainId + '"]');
            if (mainItem && !mainItem.classList.contains('marked-delete')) {
                mainItem.classList.add('is-main');
                var mainBadge = mainItem.querySelector('.main-badge');
                if (mainBadge) {
                    mainBadge.style.display = 'block';
                }
                return;
            }
        }

        var first = getVisibleExistingItems()[0];
        if (first && mainImageId) {
            mainImageId.value = first.getAttribute('data-id') || '0';
            first.classList.add('is-main');
            var firstBadge = first.querySelector('.main-badge');
            if (firstBadge) {
                firstBadge.style.display = 'block';
            }
        }
    }

    function moveExistingItem(item, direction) {
        if (!existingImages || item.classList.contains('marked-delete')) {
            return;
        }

        var visible = getVisibleExistingItems();
        var index = visible.indexOf(item);
        if (index === -1) {
            return;
        }

        var targetIndex = direction === 'left' ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= visible.length) {
            return;
        }

        var target = visible[targetIndex];
        if (direction === 'left') {
            existingImages.insertBefore(item, target);
        } else {
            existingImages.insertBefore(target, item);
        }

        updateExistingMainBadges();
    }

    function setExistingMain(item) {
        if (!item || item.classList.contains('marked-delete') || !mainImageId) {
            return;
        }

        mainImageId.value = item.getAttribute('data-id') || '0';
        mainNewIndex.value = '-1';

        var visible = getVisibleExistingItems();
        var first = visible[0];
        if (first && first !== item) {
            existingImages.insertBefore(item, first);
        }

        updateExistingMainBadges();
        renderNewPreviews();
    }

    if (existingImages) {
        existingImages.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            var item = target.closest('.preview-item');
            if (!item) {
                return;
            }

            if (target.closest('.set-main-existing') || target.closest('.set-main-btn')) {
                setExistingMain(item);
                return;
            }

            if (target.closest('.move-left')) {
                moveExistingItem(item, 'left');
                return;
            }

            if (target.closest('.move-right')) {
                moveExistingItem(item, 'right');
                return;
            }

            if (target.closest('.preview-actions') || target.closest('label') || target.closest('input')) {
                return;
            }

            setExistingMain(item);
        });

        existingImages.addEventListener('change', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') {
                return;
            }
            var item = target.closest('.preview-item');
            if (item) {
                item.classList.toggle('marked-delete', target.checked);
                item.style.opacity = target.checked ? '0.45' : '1';
                updateExistingMainBadges();
            }
        });
    }

    var publishInFlight = false;

    function handlePublishSubmit(form, event) {
        if (publishInFlight || compressBusy) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        publishInFlight = true;

        var publishLabel = tr('publishing_car') || 'Публикация…';
        setUploadBusy(true, publishLabel);
        setUploadProgress(0, publishLabel);

        animateProgressTo(8, 120, publishLabel)
            .then(function () {
                return submitFormWithProgress(form);
            })
            .then(function (nextUrl) {
                if (!nextUrl) {
                    publishInFlight = false;
                    setUploadBusy(false);
                    return;
                }
                return animateProgressTo(100, 160, publishLabel).then(function () {
                    window.location.href = nextUrl;
                });
            })
            .catch(function () {
                publishInFlight = false;
                setUploadBusy(false);
                form.submit();
            });
    }

    var carForm = document.getElementById('carForm');
    if (carForm) {
        carForm.addEventListener('submit', function (event) {
            if (selectedFiles.length < 1) {
                event.preventDefault();
                alert(tr('min_one_photo'));
                return;
            }
            syncAddInputFiles();
            handlePublishSubmit(carForm, event);
        });
    }

    var editForm = document.getElementById('editCarForm');
    if (editForm) {
        editForm.addEventListener('submit', function (event) {
            var remaining = existingImages
                ? existingImages.querySelectorAll('.preview-item:not(.marked-delete)').length
                : 0;
            if (remaining + newFiles.length < 1) {
                event.preventDefault();
                alert(tr('min_one_photo_remain'));
                return;
            }
            if (typeof syncNewInputFiles === 'function') {
                syncNewInputFiles();
            }
            handlePublishSubmit(editForm, event);
        });
    }

    if (isEdit) {
        updateExistingMainBadges();
    }

    function formatSheetDate(value) {
        if (!value) {
            return '—';
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return value;
        }
        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    function getLoadTypeState(form) {
        var vagonCheck = form.querySelector('[data-load-type="vagon"]');
        var treilerCheck = form.querySelector('[data-load-type="treiler"]');
        if (vagonCheck && vagonCheck.checked) {
            return 'vagon';
        }
        if (treilerCheck && treilerCheck.checked) {
            return 'treiler';
        }
        return '';
    }

    function formatUploadDisplayDate(value) {
        if (!value) {
            return '';
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return value;
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

    function uploadStatusLabel(vagon, treiler, uploadNumber, uploadDateValue, hasUploadDate, loadType) {
        if (loadType === 'vagon' || vagon !== '') {
            return uploadTypeParts('Вагон', uploadNumber, uploadDateValue);
        }
        if (loadType === 'treiler' || treiler !== '') {
            return uploadTypeParts('Трейлер', uploadNumber, uploadDateValue);
        }
        if (hasUploadDate) {
            return formatUploadDisplayDate(uploadDateValue) || '—';
        }
        return '—';
    }

    function updateLoadTypeVisibility(form) {
        var loadType = getLoadTypeState(form);
        var hasLoadType = loadType !== '';

        form.querySelectorAll('[data-preview-row="upload_date"]').forEach(function (row) {
            row.hidden = hasLoadType;
        });

        var logisticsRow = form.querySelector('.sheet-row-upload-logistics');
        if (logisticsRow) {
            logisticsRow.hidden = !hasLoadType;
        }
    }

    function updateUploadLogisticsPreview(form) {
        var uploadInput = form.querySelector('[data-sheet="upload_date"]');
        var vagonInput = form.querySelector('[name="vagon"]');
        var treilerInput = form.querySelector('[name="treiler"]');
        var target = form.querySelector('[data-preview="upload_logistics"]');
        if (!target) {
            return;
        }

        var loadType = getLoadTypeState(form);
        var vagon = vagonInput ? vagonInput.value.trim() : '';
        var treiler = treilerInput ? treilerInput.value.trim() : '';
        var uploadDateValue = uploadInput ? uploadInput.value.trim() : '';
        var uploadNumber = '';
        var hasUploadDate = uploadDateValue !== '';
        target.textContent = uploadStatusLabel(vagon, treiler, uploadNumber, uploadDateValue, hasUploadDate, loadType);
        updateLoadTypeVisibility(form);
    }

    function syncLoadTypeFields(form, changedType) {
        var vagonCheck = form.querySelector('[data-load-type="vagon"]');
        var treilerCheck = form.querySelector('[data-load-type="treiler"]');
        var vagonInput = form.querySelector('[name="vagon"]');
        var treilerInput = form.querySelector('[name="treiler"]');
        if (!vagonCheck || !treilerCheck || !vagonInput || !treilerInput) {
            return;
        }

        if (changedType === 'treiler' && treilerCheck.checked) {
            vagonCheck.checked = false;
            vagonInput.value = '';
            if (treilerInput.value.trim() === '') {
                treilerInput.value = 'трейлер';
            }
        } else if (changedType === 'vagon' && vagonCheck.checked) {
            treilerCheck.checked = false;
            treilerInput.value = '';
            if (vagonInput.value.trim() === '') {
                vagonInput.value = 'вагон';
            }
        } else if (changedType === 'vagon' && !vagonCheck.checked) {
            vagonInput.value = '';
        } else if (changedType === 'treiler' && !treilerCheck.checked) {
            treilerInput.value = '';
        }

        updateSheetPreview();
    }

    function initLoadTypePicker() {
        document.querySelectorAll('#carForm, #editCarForm').forEach(function (form) {
            form.querySelectorAll('[data-load-type]').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    syncLoadTypeFields(form, checkbox.getAttribute('data-load-type') || '');
                });
            });

            var initialType = getLoadTypeState(form);
            if (initialType !== '') {
                syncLoadTypeFields(form, initialType);
            } else {
                updateLoadTypeVisibility(form);
            }
        });
    }

    function applyLoadTypeFromCar(form, car) {
        var vagonCheck = form.querySelector('[data-load-type="vagon"]');
        var treilerCheck = form.querySelector('[data-load-type="treiler"]');
        var vagon = (car.vagon || '').trim();
        var treiler = (car.treiler || '').trim();

        if (vagonCheck) {
            vagonCheck.checked = vagon !== '';
        }
        if (treilerCheck) {
            treilerCheck.checked = treiler !== '';
        }

        if (vagon !== '') {
            syncLoadTypeFields(form, 'vagon');
        } else if (treiler !== '') {
            syncLoadTypeFields(form, 'treiler');
        } else {
            syncLoadTypeFields(form, '');
        }
    }

    function updateSheetPreview() {
        var form = document.getElementById('carForm') || document.getElementById('editCarForm');
        if (!form) {
            return;
        }

        form.querySelectorAll('[data-sheet]').forEach(function (input) {
            var key = input.getAttribute('data-sheet');
            if (key === 'receive_location' || key === 'receive_date') {
                return;
            }
            var previewTarget = form.querySelector('[data-preview="' + key + '"]');
            if (!previewTarget) {
                return;
            }
            var value = input.value.trim();
            if (input.type === 'date') {
                previewTarget.textContent = formatSheetDate(value);
            } else {
                previewTarget.textContent = value !== '' ? value : '—';
            }
        });

        updateReceivePreview(form);
        updateUploadLogisticsPreview(form);
    }

    function updateReceivePreview(form) {
        var locationSelect = form.querySelector('[name="receive_location"]');
        var dateInput = form.querySelector('[name="receive_date"]');
        var previewTarget = form.querySelector('[data-preview="receive_display"]');
        if (!previewTarget || !locationSelect) {
            return;
        }

        var locationText = locationSelect.options[locationSelect.selectedIndex].text;
        var dateValue = dateInput ? dateInput.value.trim() : '';
        previewTarget.textContent = dateValue !== ''
            ? locationText + ' · ' + formatSheetDate(dateValue)
            : locationText;
    }

    function hideVinLookupBanner() {
        var banner = document.getElementById('vinLookupBanner');
        if (banner) {
            banner.hidden = true;
            banner.textContent = '';
        }
    }

    function showVinLookupBanner(vin) {
        var banner = document.getElementById('vinLookupBanner');
        if (!banner) {
            return;
        }
        var template = tr('vin_found') || 'VIN: :vin';
        banner.textContent = template.split(':vin').join(vin);
        banner.hidden = false;
    }

    function applyCarLookup(car, autoFill) {
        var form = document.getElementById('carForm') || document.getElementById('editCarForm');
        if (!form || !car) {
            return;
        }

        var fields = [
            'name', 'receive_location', 'receive_date', 'upload_date',
            'vagon', 'treiler', 'contact_name', 'contact_phone', 'notes'
        ];

        if (autoFill) {
            fields.forEach(function (key) {
                var input = form.querySelector('[name="' + key + '"]');
                if (!input || car[key] === undefined) {
                    return;
                }
                if (car[key] !== '') {
                    input.value = car[key];
                }
            });
            applyLoadTypeFromCar(form, car);
            updateSheetPreview();
        } else if (Array.isArray(car.sheet)) {
            car.sheet.forEach(function (row, index) {
                var previewKey = index === 0 ? 'name' : (index === 1 ? 'receive_display' : 'upload_logistics');
                var preview = form.querySelector('[data-preview="' + previewKey + '"]');
                if (!preview) {
                    return;
                }
                if (index === 2 && car.upload_type_label) {
                    preview.textContent = car.upload_type_label;
                } else {
                    preview.textContent = row.value || '—';
                }
            });
        }

        showVinLookupBanner(car.vin_code || '');
    }

    function resolveVinLookupQuery(raw) {
        var query = raw.trim().toUpperCase();
        if (query === '') {
            return '';
        }
        if (/^\d{4}$/.test(query) || /^\d{5}$/.test(query)) {
            return query;
        }
        if (/^[A-HJ-NPR-Z0-9]{11,17}$/.test(query)) {
            return query;
        }
        if (/^\d+$/.test(query) && query.length >= 4) {
            return query.slice(-4);
        }
        return '';
    }

    function initVinLookup() {
        var lookupUrl = window.ADMIN_VIN_LOOKUP_URL || '';
        var vinInput = document.querySelector('input[name="vin_code"]');
        if (!lookupUrl || !vinInput) {
            return;
        }

        var timer = null;
        vinInput.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                var query = resolveVinLookupQuery(vinInput.value);
                if (query === '') {
                    hideVinLookupBanner();
                    return;
                }

                fetch(lookupUrl + '?q=' + encodeURIComponent(query), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || !data.found || !data.car) {
                            hideVinLookupBanner();
                            return;
                        }
                        applyCarLookup(data.car, !isEdit);
                    })
                    .catch(function () {
                        hideVinLookupBanner();
                    });
            }, 350);
        });
    }

    function initDatePickers() {
        document.querySelectorAll('.date-picker-field').forEach(function (input) {
            function openPicker() {
                if (typeof input.showPicker === 'function') {
                    try {
                        input.showPicker();
                    } catch (e) {
                        // Browser may block showPicker if not from a user gesture.
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

    document.querySelectorAll('#carForm, #editCarForm').forEach(function (form) {
        form.addEventListener('input', updateSheetPreview);
        form.addEventListener('change', updateSheetPreview);
        updateSheetPreview();
    });

    initLoadTypePicker();
    initVinLookup();
    initDatePickers();
})();
