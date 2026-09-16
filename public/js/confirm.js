/**
 * Ask before an irreversible form submits.
 *
 * Markup contract:
 *   <form method="post" data-confirm="Archive this division?"> … </form>
 *
 * Without JavaScript the form still submits; the server re-checks everything.
 */

'use strict';

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
