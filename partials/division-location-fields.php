<?php

declare(strict_types=1);

/**
 * Province and District dropdowns for the create and edit division dialogs.
 *
 * Every district is rendered, grouped by province, so the form still works
 * without JavaScript; district-select.js narrows the District list to the
 * chosen province. The server checks the pairing either way.
 *
 * @var string $fieldPrefix      id prefix, so two dialogs can share a page
 * @var string $selectedProvince province to preselect ('' for none)
 * @var string $selectedDistrict district to preselect ('' for none)
 */

$provinceId = $fieldPrefix . 'province';
$districtId = $fieldPrefix . 'district';

?>
<div class="field">
    <label class="field__label" for="<?= e($provinceId) ?>">Province</label>
    <select class="input" id="<?= e($provinceId) ?>" name="province" data-province-select="<?= e($districtId) ?>">
        <option value="">Select a province</option>
        <?php foreach (Province::names() as $province): ?>
            <option value="<?= e($province) ?>"<?= $province === $selectedProvince ? ' selected' : '' ?>><?= e($province) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="field">
    <label class="field__label" for="<?= e($districtId) ?>">District</label>
    <select class="input" id="<?= e($districtId) ?>" name="district">
        <option value="">Select a district</option>
        <?php foreach (Province::districtsByProvince() as $province => $districts): ?>
            <optgroup label="<?= e($province) ?>">
                <?php foreach ($districts as $district): ?>
                    <option value="<?= e($district) ?>"<?= $district === $selectedDistrict ? ' selected' : '' ?>><?= e($district) ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
</div>
