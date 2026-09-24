-- Read sources for screens that previously displayed records embedded in templates.
-- No sample rows are inserted. Empty queues remain empty until records are added.
USE mithra;

ALTER TABLE disaster_contributions
    ADD COLUMN handed_over_on DATE NULL,
    ADD COLUMN notes TEXT NULL,
    ADD COLUMN status ENUM('awaiting','ready','queried','verified','rejected') NOT NULL DEFAULT 'awaiting',
    ADD COLUMN received_description VARCHAR(500) NULL,
    ADD COLUMN received_value INT UNSIGNED NULL,
    ADD COLUMN received_on DATE NULL,
    ADD COLUMN ack_reference VARCHAR(100) NULL,
    ADD COLUMN confirmed_at DATETIME NULL,
    ADD COLUMN verified_value INT UNSIGNED NULL,
    ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ADD KEY idx_dcon_event_status (disaster_event_id, status);

UPDATE disaster_contributions SET status = 'verified', verified_value = estimated_value WHERE verified_at IS NOT NULL;

CREATE TABLE moderator_objections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    moderator_assignment_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','dismissed','upheld') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mo_assignment_status (moderator_assignment_id, status),
    CONSTRAINT fk_mo_assignment FOREIGN KEY (moderator_assignment_id) REFERENCES moderator_assignments(id),
    CONSTRAINT fk_mo_member FOREIGN KEY (member_id) REFERENCES users(id)
) ENGINE=InnoDB;
