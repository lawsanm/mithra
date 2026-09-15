-- ============================================================================
--  MITHRA — 005 Account security: password reset, sign-in throttling and
--  re-verified address changes (Plan §20.1 module 1.1, §21.1, §25.3)
--
--  1. password_resets — one-time secrets that let a member choose a new
--     password: an emailed link token, or a short code their moderator (or the
--     Admin) issues in person after checking who they are. Only a SHA-256 hash
--     is stored, so a database leak does not leak working secrets.
--  2. login_attempts — recent sign-in and reset attempts, keyed by a hash of
--     the identifier and by IP, so repeated guessing is slowed down.
--  3. address_changes — a new home address waits, with its proof, for the
--     division moderator before it replaces the verified one.
--  4. users.password_changed_at — sessions that signed in before the latest
--     password change are ended on their next request.
--
--  Run after 004_align_schema_with_plan.sql.
-- ============================================================================

USE mithra;

ALTER TABLE users
  ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash;

CREATE TABLE password_resets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  channel     ENUM('email','issued_code') NOT NULL,
  secret_hash CHAR(64) NOT NULL,                              -- SHA-256 of the token / code
  issued_by   INT UNSIGNED NULL,                              -- moderator or admin, for issued codes
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pr_secret (secret_hash),
  KEY idx_pr_user (user_id, used_at, expires_at),
  CONSTRAINT fk_pr_user   FOREIGN KEY (user_id)   REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_issuer FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Append-only log; old rows can be pruned by a cron job without harm.
CREATE TABLE login_attempts (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier_hash CHAR(64)    NOT NULL,                       -- SHA-256 of the lower-cased identifier
  ip_address      VARCHAR(45) NOT NULL,
  succeeded       TINYINT(1)  NOT NULL DEFAULT 0,
  attempted_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_identifier (identifier_hash, attempted_at),
  KEY idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE address_changes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  gn_division_id  INT UNSIGNED NOT NULL,                      -- the home division reviewing it
  new_address     VARCHAR(255) NOT NULL,
  proof_file_path VARCHAR(255) NOT NULL,
  status          ENUM('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  decided_by      INT UNSIGNED NULL,
  decided_at      DATETIME NULL,
  reason          VARCHAR(255) NULL,                          -- shown to the member on rejection
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ac_user (user_id, status),
  KEY idx_ac_division (gn_division_id, status, created_at),
  CONSTRAINT fk_ac_user     FOREIGN KEY (user_id)        REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ac_division FOREIGN KEY (gn_division_id) REFERENCES gn_divisions (id),
  CONSTRAINT fk_ac_decider  FOREIGN KEY (decided_by)     REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;
