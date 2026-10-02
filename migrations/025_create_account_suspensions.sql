-- ============================================================================
--  MITHRA — 025 Where past suspensions are recorded (Plan §6.3.3)
--
--  The trust score takes 10 points off for every past suspension. users.status
--  only says whether an account is suspended now, so each suspension gets a
--  row here: who suspended whom, why, and when it ended. The Admin portal
--  writes these rows when it suspends and reinstates an account; until it
--  does, the trust-score term is 0 and the breakdown page says so.
-- ============================================================================

USE mithra;

CREATE TABLE account_suspensions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  suspended_by  INT UNSIGNED NULL,                            -- the Admin
  reason        VARCHAR(255) NOT NULL,
  started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at      DATETIME NULL,                                -- NULL while still suspended
  PRIMARY KEY (id),
  KEY idx_as_user (user_id, started_at),
  CONSTRAINT fk_as_user  FOREIGN KEY (user_id)      REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_as_admin FOREIGN KEY (suspended_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;
