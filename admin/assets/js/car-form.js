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

    function renderAddPreviews() {
        if (!previewGrid) {
            return;
        }

        previewGrid.innerHTML = '';
        selectedFiles.forEach(function (item, index) {
            var wrap = document.createElement('div');
            wrap.className = 'preview-item';
            if (index === parseInt(mainImageInput.value, 10)) {
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

            var mainBtn = document.createElement('button');
            mainBtn.type = 'button';
            mainBtn.className = 'btn-ghost xs';
            mainBtn.textContent = 'Главное';
            mainBtn.addEventListener('click', function () {
                mainImageInput.value = String(index);
                renderAddPreviews();
            });

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-ghost xs';
            removeBtn.textContent = '✕';
            removeBtn.addEventListener('click', function () {
                URL.revokeObjectURL(item.url);
                selectedFiles.splice(index, 1);
                if (parseInt(mainImageInput.value, 10) >= selectedFiles.length) {
                    mainImageInput.value = '0';
                }
                syncAddInputFiles();
                renderAddPreviews();
            });

            actions.appendChild(mainBtn);
            actions.appendChild(removeBtn);
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
            syncAddInputFiles();
            renderAddPreviews();
        });
    }

    function renderNewPreviews() {
        if (!newPreviewGrid) {
            return;
        }

        newPreviewGrid.innerHTML = '';
        newFiles.forEach(function (item, index) {
            var wrap = document.createElement('div');
            wrap.className = 'preview-item';
            if (parseInt(mainNewIndex.value, 10) === index) {
                wrap.classList.add('is-main');
            }

            var img = document.createElement('img');
            img.src = item.url;

            var badge = document.createElement('span');
            badge.className = 'main-badge';
            badge.textContent = 'Главное';

            var actions = document.createElement('div');
            actions.className = 'preview-actions';

            var mainBtn = document.createElement('button');
            mainBtn.type = 'button';
            mainBtn.className = 'btn-ghost xs';
            mainBtn.textContent = 'Главное';
            mainBtn.addEventListener('click', function () {
                mainNewIndex.value = String(index);
                if (mainImageId) {
                    mainImageId.value = '0';
                }
                updateExistingMainBadges();
                renderNewPreviews();
            });

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-ghost xs';
            removeBtn.textContent = '✕';
            removeBtn.addEventListener('click', function () {
                URL.revokeObjectURL(item.url);
                newFiles.splice(index, 1);
                if (parseInt(mainNewIndex.value, 10) === index) {
                    mainNewIndex.value = '-1';
                } else if (parseInt(mainNewIndex.value, 10) > index) {
                    mainNewIndex.value = String(parseInt(mainNewIndex.value, 10) - 1);
                }
                syncNewInputFiles();
                renderNewPreviews();
            });

            actions.appendChild(mainBtn);
            actions.appendChild(removeBtn);
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
            var existingCount = existingImages ? existingImages.querySelectorAll('.preview-item:not(.marked-delete)').length : 0;
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
            }
        }
    }

    if (existingImages) {
        existingImages.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            var item = target.closest('.preview-item');
            if (!item) {
                return;
            }

            if (target.classList.contains('set-main-existing')) {
                mainImageId.value = target.getAttribute('data-id') || '0';
                mainNewIndex.value = '-1';
                updateExistingMainBadges();
                renderNewPreviews();
            }

            if (target.classList.contains('move-left')) {
                var prev = item.previousElementSibling;
                if (prev) {
                    existingImages.insertBefore(item, prev);
                    syncImageOrderInputs();
                }
            }

            if (target.classList.contains('move-right')) {
                var next = item.nextElementSibling;
                if (next) {
                    existingImages.insertBefore(next, item);
                    syncImageOrderInputs();
                }
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
            }
        });
    }

    function syncImageOrderInputs() {
        if (!existingImages) {
            return;
        }
        existingImages.querySelectorAll('.preview-item').forEach(function (item) {
            var input = item.querySelector('input[name="image_order[]"]');
            if (input) {
                item.appendChild(input);
            }
        });
    }

    var carForm = document.getElementById('carForm');
    if (carForm) {
        carForm.addEventListener('submit', function (event) {
            if (selectedFiles.length < 1) {
                event.preventDefault();
                alert('Добавьте минимум 1 фото');
            }
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
})();
