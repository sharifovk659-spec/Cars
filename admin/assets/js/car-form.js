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

    var MAX_IMAGES = 5;
    var MAX_SIZE = 5 * 1024 * 1024;
    var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
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

    function validateFile(file) {
        if (!ALLOWED.includes(file.type)) {
            return tr('file_type');
        }
        if (file.size > MAX_SIZE) {
            return tr('file_size');
        }
        return null;
    }

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
        imageInput.addEventListener('change', function () {
            Array.from(imageInput.files || []).forEach(function (file) {
                if (selectedFiles.length >= MAX_IMAGES) {
                    return;
                }
                var err = validateFile(file);
                if (err) {
                    alert(file.name + ': ' + err);
                    return;
                }
                selectedFiles.push({ file: file, url: URL.createObjectURL(file) });
            });
            if (selectedFiles.length === 1) {
                mainImageInput.value = '0';
            }
            syncAddInputFiles();
            renderAddPreviews();
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
            var existingCount = existingImages
                ? existingImages.querySelectorAll('.preview-item:not(.marked-delete)').length
                : 0;
            Array.from(newImageInput.files || []).forEach(function (file) {
                if (existingCount + newFiles.length >= MAX_IMAGES) {
                    alert(tr('max_photos', { max: String(MAX_IMAGES) }));
                    return;
                }
                var err = validateFile(file);
                if (err) {
                    alert(file.name + ': ' + err);
                    return;
                }
                newFiles.push({ file: file, url: URL.createObjectURL(file) });
            });
            syncNewInputFiles();
            renderNewPreviews();
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
            }
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

    var carForm = document.getElementById('carForm');
    if (carForm) {
        carForm.addEventListener('submit', function (event) {
            if (selectedFiles.length < 1) {
                event.preventDefault();
                alert(tr('min_one_photo'));
                return;
            }
            syncAddInputFiles();
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
            }
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

    function uploadStatusLabel(vagon, treiler, hasUploadDate) {
        if (vagon !== '') {
            return tr('upload_status_vagon') || 'Боргир шуд дар вагон';
        }
        if (treiler !== '') {
            return tr('upload_status_treiler') || 'Боргир шуд дар трейлер';
        }
        if (hasUploadDate) {
            return 'Боргирии шуд';
        }
        return '—';
    }

    function updateUploadLogisticsPreview(form) {
        var uploadInput = form.querySelector('[data-sheet="upload_date"]');
        var vagonInput = form.querySelector('[name="vagon"]');
        var treilerInput = form.querySelector('[name="treiler"]');
        var target = form.querySelector('[data-preview="upload_logistics"]');
        if (!target) {
            return;
        }

        var vagon = vagonInput ? vagonInput.value.trim() : '';
        var treiler = treilerInput ? treilerInput.value.trim() : '';
        var hasUploadDate = !!(uploadInput && uploadInput.value);
        target.textContent = uploadStatusLabel(vagon, treiler, hasUploadDate);
    }

    function syncLoadTypeFields(form, changedType) {
        var vagonCheck = form.querySelector('[data-load-type="vagon"]');
        var treilerCheck = form.querySelector('[data-load-type="treiler"]');
        var vagonInput = form.querySelector('[name="vagon"]');
        var treilerInput = form.querySelector('[name="treiler"]');
        var vagonDetail = form.querySelector('[data-load-detail="vagon"]');
        var treilerDetail = form.querySelector('[data-load-detail="treiler"]');
        if (!vagonCheck || !treilerCheck || !vagonInput || !treilerInput) {
            return;
        }

        if (changedType === 'vagon' && vagonCheck.checked) {
            treilerCheck.checked = false;
            treilerInput.value = '';
            if (vagonInput.value.trim() === '') {
                vagonInput.value = 'вагон';
            }
        } else if (changedType === 'treiler' && treilerCheck.checked) {
            vagonCheck.checked = false;
            vagonInput.value = '';
        } else if (changedType === 'vagon' && !vagonCheck.checked) {
            vagonInput.value = '';
        } else if (changedType === 'treiler' && !treilerCheck.checked) {
            treilerInput.value = '';
        }

        if (vagonDetail) {
            vagonDetail.hidden = !vagonCheck.checked;
        }
        if (treilerDetail) {
            treilerDetail.hidden = !treilerCheck.checked;
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

            form.addEventListener('submit', function () {
                var vagonCheck = form.querySelector('[data-load-type="vagon"]');
                var treilerCheck = form.querySelector('[data-load-type="treiler"]');
                var vagonInput = form.querySelector('[name="vagon"]');
                var treilerInput = form.querySelector('[name="treiler"]');
                if (vagonCheck && vagonCheck.checked && vagonInput && vagonInput.value.trim() === '') {
                    vagonInput.value = 'вагон';
                }
                if (treilerCheck && !treilerCheck.checked && treilerInput) {
                    treilerInput.value = '';
                }
                if (vagonCheck && !vagonCheck.checked && vagonInput) {
                    vagonInput.value = '';
                }
            });
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

        updateUploadLogisticsPreview(form);
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
            'name', 'receive_date', 'upload_date', 'upload_number',
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
                var previewKey = index === 0 ? 'name' : (index === 1 ? 'receive_date' : 'upload_logistics');
                var preview = form.querySelector('[data-preview="' + previewKey + '"]');
                if (!preview) {
                    return;
                }
                if (index === 2 && car.upload_status_label) {
                    preview.textContent = car.upload_status_label;
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

    document.querySelectorAll('#carForm, #editCarForm').forEach(function (form) {
        form.addEventListener('input', updateSheetPreview);
        form.addEventListener('change', updateSheetPreview);
        updateSheetPreview();
    });

    initLoadTypePicker();
    initVinLookup();
})();
