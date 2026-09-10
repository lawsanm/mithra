'use strict';

document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    const nav = button.closest('.nav');
    const links = document.getElementById(button.getAttribute('aria-controls'));
    if (!nav || !links) return;
    button.hidden = false;
    nav.dataset.expanded = 'false';
    button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(expanded));
        nav.dataset.expanded = String(expanded);
    });
    nav.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && nav.dataset.expanded === 'true') {
            button.setAttribute('aria-expanded', 'false');
            nav.dataset.expanded = 'false';
            button.focus();
        }
    });
});

document.querySelectorAll('.account-menu').forEach((menu) => {
    const trigger = menu.querySelector('summary');
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            menu.open = false;
            trigger.focus();
            event.stopPropagation();
        }
    });
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
    menu.addEventListener('focusout', (event) => {
        if (event.relatedTarget && !menu.contains(event.relatedTarget)) menu.open = false;
    });
});
