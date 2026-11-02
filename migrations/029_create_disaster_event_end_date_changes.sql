-- Record each Admin change to a disaster event's planned end date.
-- Entries are append-only; disaster_events remains the current-state record.

USE mithra;

CREATE TABLE disaster_event_end_date_changes (
  id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  disaster_event_id        INT UNSIGNED NOT NULL,
  changed_by               INT UNSIGNED NOT NULL,
  previous_planned_end_at  DATETIME NULL,
  new_planned_end_at       DATETIME NOT NULL,
  changed_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_deedc_event_changed (disaster_event_id, changed_at),
  CONSTRAINT fk_deedc_event FOREIGN KEY (disaster_event_id) REFERENCES disaster_events (id),
  CONSTRAINT fk_deedc_admin FOREIGN KEY (changed_by) REFERENCES users (id)
) ENGINE=InnoDB;
