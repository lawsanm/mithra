/**
 * Upload targets show what was chosen, since the file input itself is
 * visually hidden inside them.
 *
 * Markup contract:
 *   <label class="upload-drop"> … <span data-upload-name>Prompt</span>
 *     <input type="file" class="visually-hidden"> </label>
 *       → the prompt text becomes the chosen file names.
 *
 *   <label class="upload-tile"> … <input type="file" class="visually-hidden"> </label>
 *       → a thumbnail of each chosen photo appears before the tile. Picking
 *         again replaces the previews, as the browser replaces the files.
 *
 * Without JavaScript the targets still open the file picker; they just don't
 * show the choice until the form is submitted.
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

    document.querySelectorAll('.upload-tile input[type="file"]').forEach((input) => {
        const tile = input.closest('.upload-tile');
        let previews = [];

        input.addEventListener('change', () => {
            previews.forEach((img) => {
                URL.revokeObjectURL(img.src);
                img.remove();
            });

            previews = Array.from(input.files).map((file) => {
                const img = document.createElement('img');
                img.className = 'thumb thumb--sm thumb__img upload-tile__preview';
                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                img.title = file.name + ' (uploads when you submit)';
                tile.before(img);
                return img;
            });

            tile.classList.toggle('upload-tile--chosen', previews.length > 0);
        });
    });
});
