/**
 * Ask before an irreversible form submits, in the site's own dialog.
 *
 * Markup contract:
 *   <form method="post" data-confirm="Archive this division? It will no longer accept new members.">
 *       … <button type="submit">Archive division</button> </form>
 *
 * The text up to the first "?" becomes the dialog's heading and the rest its
 * explanation; the confirm button repeats the submit button's own label, so
 * the person sees exactly what they are agreeing to. Cancel has the focus, so
 * a stray Enter or click never goes through. Escape, ✕ and the backdrop all
 * cancel.
 *
 * One dialog is shared by every such form on the page. Without JavaScript the
 * form still submits; the server re-checks everything.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-confirm]');

    if (forms.length === 0) {
        return;
    }

    const dialog = document.createElement('dialog');
    dialog.className = 'modal modal--sm';
    dialog.id = 'confirm-dialog';
    dialog.setAttribute('aria-labelledby', 'confirm-dialog-title');
    dialog.setAttribute('aria-describedby', 'confirm-dialog-body');
    dialog.innerHTML = `
        <div class="modal__head">
            <h2 class="modal__title" id="confirm-dialog-title"></h2>
            <button class="modal__close" type="button" aria-label="Cancel" data-confirm-cancel>✕</button>
        </div>
        <p class="page-intro__meta" id="confirm-dialog-body"></p>
        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-confirm-cancel>Cancel</button>
            <button class="btn btn--danger" type="button" data-confirm-accept></button>
        </div>`;
    document.body.append(dialog);

    const title  = dialog.querySelector('#confirm-dialog-title');
    const body   = dialog.querySelector('#confirm-dialog-body');
    const accept = dialog.querySelector('[data-confirm-accept]');
    const cancel = dialog.querySelector('.modal__footer [data-confirm-cancel]');

    let pending = null; // { form, submitter } waiting on the answer

    dialog.querySelectorAll('[data-confirm-cancel]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    // A click on the backdrop lands on the dialog element itself.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('close', () => {
        pending = null;
    });

    accept.addEventListener('click', () => {
        if (pending === null) {
            return;
        }
        const { form, submitter } = pending;
        dialog.close();
        form.dataset.confirmed = 'true';
        form.requestSubmit(submitter);
    });

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();

            const message = form.dataset.confirm;
            const split = message.indexOf('?');
            const submitter = event.submitter ?? form.querySelector('[type="submit"]');

            title.textContent = split === -1 ? 'Are you sure?' : message.slice(0, split + 1);
            body.textContent = split === -1 ? message : message.slice(split + 1).trim();
            body.hidden = body.textContent === '';
            accept.textContent = submitter?.textContent.trim() || 'Confirm';

            pending = { form, submitter };
            dialog.showModal();
            cancel.focus();
        });
    });
});
