/**
 * Prefills the edit-division modal (partials/modal-edit-division.php) from
 * the data attributes on whichever "Edit" trigger was clicked, and points
 * the form at that division's update route.
 *
 * Markup contract:
 *   <button data-modal-open="modal-edit-division"
 *           data-division-id="4" data-division-name="Bambalapitiya"
 *           data-division-district="Colombo">Edit</button>
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-edit-division');

    if (!form) {
        return;
    }

    const nameInput = document.getElementById('edit_division_name');
    const districtInput = document.getElementById('edit_district');

    document.querySelectorAll('[data-modal-open="modal-edit-division"]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = `/admin/divisions/${trigger.dataset.divisionId}`;
            nameInput.value = trigger.dataset.divisionName ?? '';
            districtInput.value = trigger.dataset.divisionDistrict ?? '';
        });
    });
});
