'use strict';

document.querySelectorAll('[data-filter-list]').forEach((input) => {
    const list = document.getElementById(input.dataset.filterList);
    if (!list) return;
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        Array.from(list.children).forEach((row) => {
            row.hidden = !row.textContent.toLowerCase().includes(query);
        });
    });
});
