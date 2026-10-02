/**
 * Light polling for pages that wait on the other member (Plan §21.1, §21.4).
 *
 * Markup contract:
 *   <section data-handover-poll="/bookings/12/handover-status"> … </section>
 *       → every 8 seconds, fetch the JSON status; when it differs from the
 *         first answer (the other side accepted, or the booking moved on),
 *         reload so the page shows it.
 *
 *   <span data-unread-poll="/notifications/unread-count"> … </span>
 *       → every 8 seconds, put the unread count in the element's text and
 *         hide it at zero.
 *
 * Polling stops while the tab is hidden. Without JavaScript, a reload shows
 * the same information.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const INTERVAL = 8000;

    const every = (task) => {
        const tick = async () => {
            if (document.visibilityState === 'visible') {
                await task();
            }
        };

        window.setInterval(tick, INTERVAL);
    };

    const getJson = async (url) => {
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });

        if (!response.ok) {
            throw new Error('Status ' + response.status);
        }

        return response.json();
    };

    document.querySelectorAll('[data-handover-poll]').forEach((section) => {
        const url = section.dataset.handoverPoll;
        let first = null;

        every(async () => {
            try {
                const current = JSON.stringify(await getJson(url));

                if (first === null) {
                    first = current;
                } else if (current !== first) {
                    window.location.reload();
                }
            } catch (error) {
                // A failed poll changes nothing; the next one tries again.
            }
        });
    });

    document.querySelectorAll('[data-unread-poll]').forEach((badge) => {
        const url = badge.dataset.unreadPoll;

        every(async () => {
            try {
                const data = await getJson(url);
                const count = Number(data.unread) || 0;

                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = count === 0;
            } catch (error) {
                // Keep the last count shown.
            }
        });
    });
});
