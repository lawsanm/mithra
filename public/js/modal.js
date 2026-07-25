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
    const openModal = (id) => {
        const dialog = document.getElementById(id);

        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    };

    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openModal(trigger.dataset.modalOpen);
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
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
});
