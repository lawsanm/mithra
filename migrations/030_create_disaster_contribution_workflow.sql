-- Disaster Mode contribution evidence, confirmation, audit and relief links.
-- The original disaster_contributions table and its preview fields predate the
-- two-party workflow in Rules/Mithra_Project_Plan_Revised.md §14.3.

USE mithra;

-- Preserve the identity of the Liaison who recorded each row separately from
-- the Liaison who verifies it. Older rows used verified_by for both roles.
ALTER TABLE disaster_contributions
  ADD COLUMN recorded_by INT UNSIGNED NULL AFTER verified_by,
  ADD COLUMN sponsor_proof JSON NULL AFTER receipt_reference;

UPDATE disaster_contributions
   SET recorded_by = verified_by;

ALTER TABLE disaster_contributions
  MODIFY COLUMN recorded_by INT UNSIGNED NOT NULL,
  MODIFY COLUMN verified_by INT UNSIGNED NULL,
  ADD CONSTRAINT fk_dcn_recorder FOREIGN KEY (recorded_by) REFERENCES users (id);

-- Keep every Moderator confirmation attempt. The parent row remains the
-- current workflow state; this table retains the evidence for each attempt.
CREATE TABLE disaster_contribution_confirmations (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contribution_id       INT UNSIGNED NOT NULL,
  moderator_id          INT UNSIGNED NULL, -- NULL only for legacy rows with no stored confirmer id
  outcome               ENUM('received','partial','none') NOT NULL,
  received_description  VARCHAR(500) NULL,
  received_value        INT UNSIGNED NULL,
  received_on           DATE NULL,
  ack_reference         VARCHAR(100) NULL,
  proof_files           JSON NULL,
  note                  TEXT NULL,
  confirmed_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dcc_contribution (contribution_id, confirmed_at, id),
  CONSTRAINT fk_dcc_contribution FOREIGN KEY (contribution_id) REFERENCES disaster_contributions (id),
  CONSTRAINT fk_dcc_moderator FOREIGN KEY (moderator_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- Retain the confirmation data that the preview schema stored on the parent.
INSERT INTO disaster_contribution_confirmations
  (contribution_id, moderator_id, outcome, received_description, received_value,
   received_on, ack_reference, confirmed_at)
SELECT c.id, d.moderator_id,
       CASE
         WHEN c.received_description IS NULL THEN 'none'
         WHEN c.received_description = c.description
          AND c.received_value <=> c.estimated_value THEN 'received'
         ELSE 'partial'
       END,
       c.received_description, c.received_value, c.received_on,
       c.ack_reference, c.confirmed_at
  FROM disaster_contributions c
  JOIN disaster_events de ON de.id = c.disaster_event_id
  JOIN gn_divisions d ON d.id = de.gn_division_id
 WHERE c.confirmed_at IS NOT NULL;

CREATE TABLE disaster_contribution_relief_links (
  contribution_id  INT UNSIGNED NOT NULL,
  relief_record_id INT UNSIGNED NOT NULL,
  linked_by        INT UNSIGNED NOT NULL,
  linked_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (contribution_id, relief_record_id),
  KEY idx_dcrl_relief (relief_record_id),
  CONSTRAINT fk_dcrl_contribution FOREIGN KEY (contribution_id) REFERENCES disaster_contributions (id),
  CONSTRAINT fk_dcrl_relief FOREIGN KEY (relief_record_id) REFERENCES disaster_relief_records (id),
  CONSTRAINT fk_dcrl_actor FOREIGN KEY (linked_by) REFERENCES users (id)
) ENGINE=InnoDB;

-- Append-only workflow history, including the existing records' known events.
CREATE TABLE disaster_contribution_events (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contribution_id INT UNSIGNED NOT NULL,
  actor_id        INT UNSIGNED NOT NULL,
  action          VARCHAR(40) NOT NULL,
  note            TEXT NULL,
  occurred_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dce_contribution (contribution_id, occurred_at, id),
  CONSTRAINT fk_dce_contribution FOREIGN KEY (contribution_id) REFERENCES disaster_contributions (id),
  CONSTRAINT fk_dce_actor FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE=InnoDB;

INSERT INTO disaster_contribution_events (contribution_id, actor_id, action, occurred_at)
SELECT id, recorded_by, 'recorded', recorded_at
  FROM disaster_contributions;

INSERT INTO disaster_contribution_events (contribution_id, actor_id, action, occurred_at)
SELECT id, verified_by, 'verified', verified_at
  FROM disaster_contributions
 WHERE status = 'verified' AND verified_by IS NOT NULL AND verified_at IS NOT NULL;
