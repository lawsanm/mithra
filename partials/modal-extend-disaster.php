<?php

declare(strict_types=1);

/** Admin form to extend an active disaster event's planned end date. */

?>
<dialog aria-labelledby="modal-extend-disaster-title" class="modal modal--sm" id="modal-extend-disaster">
    <form method="POST" action="<?= base_url() ?>/admin/disaster/events/end-date" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="event_id" data-modal-field="eventId" value="">

        <div class="modal__head">
            <h2 class="modal__title" id="modal-extend-disaster-title">Extend Disaster Mode</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <div class="field">
            <label class="field__label" for="disaster_planned_end">New planned end date</label>
            <input class="input" id="disaster_planned_end" name="end_date" type="date" data-modal-field="plannedEndDate">
        </div>

        <p class="list-row__meta u-mb-4">The new date must be later than the current one. Sponsors and the liaison will be notified, and this change will be recorded.</p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Save new date</button>
        </div>
    </form>
</dialog>
