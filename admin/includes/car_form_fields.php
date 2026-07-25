<?php

declare(strict_types=1);

/** @var array<string, string> $input */
$labels = carFieldLabels();
$hasVagon = trim($input['vagon']) !== '';
$hasTreiler = trim($input['treiler']) !== '';
?>
<div class="car-form-sheet">
    <div id="vinLookupBanner" class="vin-lookup-banner" hidden></div>
    <div class="car-form-preview">
        <div class="sheet-row">
            <span class="sheet-label"><?= e($labels['name']) ?> :</span>
            <span class="sheet-value" data-preview="name"><?= e($input['name'] !== '' ? $input['name'] : '—') ?></span>
        </div>
        <div class="sheet-row">
            <span class="sheet-label"><?= e($labels['receive_date']) ?> :</span>
            <span class="sheet-value" data-preview="receive_date"><?= e($input['receive_date'] !== '' ? formatDate($input['receive_date']) : '—') ?></span>
        </div>
        <div class="sheet-row">
            <span class="sheet-label"><?= e($labels['upload_date']) ?> :</span>
            <span class="sheet-value" data-preview="upload_date"><?= e($input['upload_date'] !== '' ? formatDate($input['upload_date']) : '—') ?></span>
        </div>
        <div class="sheet-row sheet-row-upload-logistics">
            <span class="sheet-label"><?= e(carUploadSheetLabel()) ?> :</span>
            <span class="sheet-value" data-preview="upload_logistics" id="uploadLogisticsPreview"><?php
                echo e(carUploadStatusLabel(array_merge(carDefaultFormInput(), $input)));
            ?></span>
        </div>
        <div class="sheet-row">
            <span class="sheet-label"><?= e($labels['upload_number']) ?></span>
            <span class="sheet-value" data-preview="upload_number"><?= e($input['upload_number'] !== '' ? $input['upload_number'] : '—') ?></span>
        </div>
    </div>

    <div class="form-grid car-form-grid">
        <label class="field">
            <span><?= e($labels['name']) ?> *</span>
            <input type="text" name="name" required value="<?= e($input['name']) ?>" placeholder="Wish-15" data-sheet="name">
        </label>

        <label class="field">
            <span><?= e($labels['vin_code']) ?> *</span>
            <input type="text" name="vin_code" maxlength="17" required
                   value="<?= e($input['vin_code']) ?>" placeholder="02000212020" style="text-transform: uppercase">
        </label>

        <label class="field">
            <span><?= e($labels['receive_date']) ?> *</span>
            <input type="date" name="receive_date" required value="<?= e($input['receive_date']) ?>" data-sheet="receive_date">
        </label>

        <label class="field">
            <span><?= e($labels['upload_date']) ?></span>
            <input type="date" name="upload_date" value="<?= e($input['upload_date']) ?>" data-sheet="upload_date">
        </label>

        <label class="field">
            <span><?= e($labels['upload_number']) ?></span>
            <input type="text" name="upload_number" maxlength="50" value="<?= e($input['upload_number']) ?>"
                   placeholder="12345" data-sheet="upload_number">
        </label>

        <label class="field full">
            <span><?= e(__('field.load_type')) ?></span>
            <div class="load-type-options">
                <label class="load-type-option">
                    <input type="checkbox" data-load-type="vagon"<?= $hasVagon ? ' checked' : '' ?>>
                    <span><?= e($labels['vagon']) ?></span>
                </label>
                <label class="load-type-option">
                    <input type="checkbox" data-load-type="treiler"<?= $hasTreiler ? ' checked' : '' ?>>
                    <span><?= e($labels['treiler']) ?></span>
                </label>
            </div>
        </label>

        <label class="field load-type-detail" data-load-detail="vagon"<?= $hasVagon ? '' : ' hidden' ?>>
            <span><?= e($labels['vagon']) ?></span>
            <input type="text" name="vagon" maxlength="50" value="<?= e($input['vagon']) ?>"
                   placeholder="<?= e(__('placeholder.vagon')) ?>" data-sheet="vagon">
        </label>

        <label class="field load-type-detail" data-load-detail="treiler"<?= $hasTreiler ? '' : ' hidden' ?>>
            <span><?= e($labels['treiler']) ?></span>
            <input type="text" name="treiler" maxlength="50" value="<?= e($input['treiler']) ?>"
                   placeholder="<?= e(__('placeholder.treiler')) ?>" data-sheet="treiler">
        </label>

        <label class="field">
            <span><?= e($labels['status']) ?></span>
            <select name="status">
                <?php foreach (carStatusLabels() as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $input['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span><?= e($labels['contact_name']) ?></span>
            <input type="text" name="contact_name" value="<?= e($input['contact_name']) ?>" placeholder="<?= e(__('placeholder.contact')) ?>">
        </label>

        <label class="field">
            <span><?= e($labels['contact_phone']) ?></span>
            <input type="text" name="contact_phone" value="<?= e($input['contact_phone']) ?>" placeholder="+992...">
        </label>

        <label class="field full">
            <span><?= e($labels['notes']) ?></span>
            <textarea name="notes" rows="2" placeholder="<?= e(__('placeholder.notes')) ?>"><?= e($input['notes']) ?></textarea>
        </label>
    </div>
</div>
