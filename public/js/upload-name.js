/**
 * Upload targets show what was chosen, since the file input itself is
 * visually hidden inside them.
 *
 * Markup contract:
 *   <label class="upload-drop"> … <span data-upload-name>Prompt</span>
 *     <input type="file" class="visually-hidden"> </label>
 *       → the prompt text becomes the chosen file names.
 *
 *   <label class="upload-tile"> … <input type="file" class="visually-hidden"
 *       multiple data-max-files="5" [data-kept-by="keep_photos[]"]> </label>
 *       → each pick is added to the photos already chosen instead of
 *         replacing them (a browser file picker replaces its selection every
 *         time), up to data-max-files. Photos the listing already keeps —
 *         checked boxes named by data-kept-by — count against that limit.
 *         Every chosen photo shows as a thumbnail with a button to drop it.
 *
 * Without JavaScript the targets still open the file picker; they just don't
 * show the choice until the form is submitted. The server enforces the limit
 * either way.
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
        const form = input.closest('form');
        const max = input.dataset.maxFiles !== undefined
            ? Number(input.dataset.maxFiles)
            : (input.multiple ? Infinity : 1);
        const keptBy = input.dataset.keptBy
            ? Array.from(form.querySelectorAll(`input[type="checkbox"][name="${input.dataset.keptBy}"]`))
            : [];

        const notice = document.createElement('p');
        notice.className = 'field__hint upload-tile__notice';
        notice.setAttribute('aria-live', 'polite');
        tile.parentElement.after(notice);

        let chosen = [];
        let previews = [];

        const room = () => max - keptBy.filter((box) => box.checked).length;
        const sameFile = (a, b) => a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;

        const render = () => {
            // Hand the whole accumulated list back to the input, so all of it submits.
            const transfer = new DataTransfer();
            chosen.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;

            previews.forEach((item) => {
                URL.revokeObjectURL(item.querySelector('img').src);
                item.remove();
            });

            previews = chosen.map((file, index) => {
                const item = document.createElement('span');
                item.className = 'upload-tile__item';

                const img = document.createElement('img');
                img.className = 'thumb thumb--sm thumb__img upload-tile__preview';
                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                img.title = file.name + ' (uploads when you submit)';

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'upload-tile__remove';
                remove.textContent = '✕';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.addEventListener('click', () => {
                    chosen.splice(index, 1);
                    notice.textContent = '';
                    render();
                });

                item.append(img, remove);
                tile.before(item);
                return item;
            });

            tile.classList.toggle('upload-tile--chosen', chosen.length > 0);
            tile.hidden = chosen.length >= room();
        };

        input.addEventListener('change', () => {
            const picked = Array.from(input.files).filter((file) => !chosen.some((held) => sameFile(held, file)));
            const space = Math.max(room() - chosen.length, 0);

            chosen = chosen.concat(picked.slice(0, space));
            const left = picked.length - space;
            notice.textContent = left > 0
                ? `The listing is full (5 photos at most), so ${left} photo${left === 1 ? ' was' : 's were'} not added.`
                : '';
            render();
        });

        // Unticking "Keep" on an existing photo frees a place, and ticking it again
        // takes one back — dropping the newest choice if the listing is over the limit.
        keptBy.forEach((box) => box.addEventListener('change', () => {
            if (chosen.length > room()) {
                chosen = chosen.slice(0, Math.max(room(), 0));
                notice.textContent = 'The listing is full (5 photos at most), so the newest choice was dropped.';
            } else {
                notice.textContent = '';
            }
            render();
        }));

        render();
    });
});
