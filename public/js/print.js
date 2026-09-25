/**
 * Print buttons: <button type="button" data-print>Print</button>.
 *
 * The browser's own print dialog also offers "Save as PDF", so reports need no
 * PDF library. The @media print rules in main.css hide the page chrome.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
});
