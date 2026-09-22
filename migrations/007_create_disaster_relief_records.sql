-- ============================================================================
--  MITHRA — 007 Disaster relief records for the moderator's relief CRUD
--  (Plan §14.1 step 5, §16.3)
--
--  During Disaster Mode sponsors hand cash or goods to the division's
--  Moderator, who arranges relief on the ground. This table is the
--  Moderator's record of what was handed out, where, to how many households
--  and from which sponsor's help. It is record-keeping only: no points move
--  and nothing here touches the ledger (§14).
--
--  Rows can be edited or removed only while their disaster is still active;
--  once the Admin ends it the records are kept as they are for CSR reporting.
--
--  Run after 006_add_sponsor_profiles.sql.
-- ============================================================================

USE mithra;

CREATE TABLE disaster_relief_records (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  disaster_event_id  INT UNSIGNED NOT NULL,
  moderator_id       INT UNSIGNED NOT NULL,                    -- who handed it out and recorded it
  sponsor_id         INT UNSIGNED NULL,                        -- whose help paid for it, when known
  relief_type        ENUM('food','water','shelter','medical','clothing','cash','other') NOT NULL,
  description        VARCHAR(255) NOT NULL,                    -- what was given, e.g. 40 dry-ration packs
  location           VARCHAR(150) NOT NULL,                    -- lane, shelter or community hall
  households_reached SMALLINT UNSIGNED NOT NULL,
  estimated_value    INT UNSIGNED NULL,                        -- rupees, record-keeping only; no points move
  distributed_on     DATE NOT NULL,
  notes              VARCHAR(500) NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_drr_event (disaster_event_id, distributed_on),
  KEY idx_drr_moderator (moderator_id),
  KEY idx_drr_sponsor (sponsor_id),
  CONSTRAINT fk_drr_event     FOREIGN KEY (disaster_event_id) REFERENCES disaster_events (id),
  CONSTRAINT fk_drr_moderator FOREIGN KEY (moderator_id)      REFERENCES users (id),
  CONSTRAINT fk_drr_sponsor   FOREIGN KEY (sponsor_id)        REFERENCES sponsors (id)
) ENGINE=InnoDB;
