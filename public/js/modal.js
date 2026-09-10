/**
 * Modal open/close for the native <dialog> elements rendered by /partials.
 *
 * Markup contract:
 *   <button data-modal-open="request-borrow">…</button>
 *   <dialog class="modal" id="request-borrow"> … [data-modal-close] … </dialog>
 *
 * The dialog element handles Escape, focus trapping and the backdrop itself, so
 * this only wires the triggers. Forms still submit normally without JavaScript;
 * a page that must work JS-free should link to a full page instead of a modal.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const openModal = (id, trigger) => {
        const dialog = document.getElementById(id);

        if (dialog instanceof HTMLDialogElement) {
            dialog.querySelectorAll('[data-modal-field]').forEach((field) => {
                const value = trigger.dataset[field.dataset.modalField] ?? '';
                if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) {
                    field.value = value;
                } else {
                    field.textContent = value;
                }
            });
            if (id === 'modal-edit-category') {
                dialog.querySelector('#category-modal-title').textContent = trigger.dataset.mode === 'create' ? 'Add category' : 'Edit category';
                dialog.querySelector('#category_name').value = trigger.dataset.categoryName ?? '';
            }
            dialog.showModal();
        }
    };

    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openModal(trigger.dataset.modalOpen, trigger);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((closer) => {
        closer.addEventListener('click', (event) => {
            event.preventDefault();
            closer.closest('dialog').close();
        });
    });

    // Clicking the backdrop (outside the dialog box) dismisses it.
    document.querySelectorAll('dialog.modal').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right
                || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                dialog.close();
            }
        });
    });
});
