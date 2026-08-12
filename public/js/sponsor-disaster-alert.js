/**
 * Auto-opens the "Disaster Mode activated" modal once per new disaster for a
 * sponsor whose division has an active event. Depends on modal.js for close
 * behaviour (Escape, backdrop click, [data-modal-close]).
 *
 * The dashboard's notifications row already shows the same information, so a
 * page loaded without JavaScript still communicates the alert.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('modal-sponsor-disaster-alert');

    if (dialog instanceof HTMLDialogElement) {
        dialog.showModal();
    }
});
