'use strict';

(function () {
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
            return 'Только JPG, PNG, WEBP';
        }
        if (file.size > MAX_SIZE) {
            return 'Максимум 5 MB';
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
        var mainBtn = createActionButton('set-main-btn', 'Главное', 'Сделать главным фото');
        mainBtn.addEventListener('click', options.onMain);

        var leftBtn = createActionButton('move-left', '←', 'Передвинуть назад');
        leftBtn.addEventListener('click', options.onLeft);

        var rightBtn = createActionButton('move-right', '→', 'Передвинуть вперёд');
        rightBtn.addEventListener('click', options.onRight);

        actions.appendChild(mainBtn);
        actions.appendChild(leftBtn);
        actions.appendChild(rightBtn);

        if (options.onRemove) {
            var removeBtn = createActionButton('', '✕', 'Удалить');
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
            badge.textContent = 'Главное';

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
            badge.textContent = 'Главное';

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
                    alert('Максимум ' + MAX_IMAGES + ' фото');
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
                alert('Добавьте минимум 1 фото');
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
                alert('Должно остаться минимум 1 фото');
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
    }

    document.querySelectorAll('#carForm, #editCarForm').forEach(function (form) {
        form.addEventListener('input', updateSheetPreview);
        form.addEventListener('change', updateSheetPreview);
        updateSheetPreview();
    });
})();
