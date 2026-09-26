/**
 * Upload tiles show which file was chosen, since the file input itself is
 * visually hidden inside the tile.
 *
 * Markup contract:
 *   <label class="upload-drop"> … <span data-upload-name>Prompt</span>
 *     <input type="file" class="visually-hidden"> </label>
 *
 * Without JavaScript the tile still opens the file picker; it just keeps its
 * prompt text.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.upload-drop input[type="file"]').forEach((input) => {
        const name = input.closest('.upload-drop').querySelector('[data-upload-name]');
        const prompt = name ? name.textContent : '';

        input.addEventListener('change', () => {
            if (!name) {
                return;
            }
            const files = Array.from(input.files).map((file) => file.name);
            name.textContent = files.length > 0 ? files.join(', ') : prompt;
            input.closest('.upload-drop').classList.toggle('upload-drop--chosen', files.length > 0);
        });
    });
});
