/**
 * Narrows a District dropdown to the districts of the chosen Province.
 *
 * Markup contract:
 *   <select name="province" data-province-select="district-id"> … </select>
 *   <select id="district-id"> <optgroup label="Western Province"> … </optgroup> … </select>
 *
 * The server renders every district, grouped by province, so the form works
 * without JavaScript. This keeps a copy of those groups and shows only the
 * chosen province's; with no province chosen the District list is disabled.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-province-select]').forEach((provinceSelect) => {
        const districtSelect = document.getElementById(provinceSelect.dataset.provinceSelect);

        if (!(districtSelect instanceof HTMLSelectElement)) {
            return;
        }

        const placeholder = districtSelect.querySelector('option[value=""]');
        const groups      = Array.from(districtSelect.querySelectorAll('optgroup'));

        const showDistricts = () => {
            const current = districtSelect.value;
            const group   = groups.find((candidate) => candidate.label === provinceSelect.value);

            districtSelect.replaceChildren(placeholder);

            if (group) {
                group.querySelectorAll('option').forEach((option) => {
                    districtSelect.append(option.cloneNode(true));
                });
            }

            // Keep the district only if it belongs to the newly chosen province.
            districtSelect.value = group && Array.from(districtSelect.options).some((option) => option.value === current)
                ? current
                : '';
            districtSelect.disabled = !group;
        };

        provinceSelect.addEventListener('change', showDistricts);
        showDistricts();
    });
});
