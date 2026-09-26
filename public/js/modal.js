/**
 * Modal open/close for the native <dialog> elements rendered by /partials.
 *
 * Markup contract:
 *   <button data-modal-open="request-borrow">…</button>
 *   <dialog class="modal" id="request-borrow"> … [data-modal-close] … </dialog>
 *
 * A dialog element marked data-modal-field="member" is filled from the
 * trigger's data-member attribute, so one dialog serves every row.
 *
 * The dialog element handles Escape, focus trapping and the backdrop itself, so
 * this only wires the triggers. Forms still submit normally without JavaScript;
 * a trigger that is a link (href) still works without it, landing on a page
 * that renders the dialog open.
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

    // A dialog the server rendered open (a form returned with errors, or a
    // page whose address is the dialog) becomes a true modal: backdrop, focus
    // trap and Escape. Without JavaScript it simply shows in the page.
    document.querySelectorAll('dialog.modal[open]').forEach((dialog) => {
        dialog.close();
        dialog.showModal();
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
