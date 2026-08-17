/**
 * Confirms destructive form submissions before they go through.
 *
 * Markup contract:
 *   <form data-confirm="Archive this division?">…</form>
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
