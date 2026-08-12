/**
 * Auto-submits a filter's <form> when one of its <select> values changes.
 *
 * Markup contract:
 *   <form method="get" action="/some/path"> … <select data-auto-submit> … </select> … </form>
 *
 * The form still submits normally without JavaScript — a manual submit
 * button is the JS-free fallback wherever a filter form needs one.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => {
            select.closest('form')?.submit();
        });
    });
});
