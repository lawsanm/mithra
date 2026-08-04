-- ============================================================================
--  MITHRA — Community Lending, Sharing & Caring Platform
--  MySQL 8.x DDL  |  Engine: InnoDB  |  Charset: utf8mb4 / utf8mb4_unicode_ci
--  Matches Mithra_EER.drawio. Run top-to-bottom on an empty database.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS mithra
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE mithra;

SET NAMES utf8mb4;

-- ============================================================================
-- 1. IDENTITY, ROLES & GOVERNANCE
-- ============================================================================

-- The five actors (Member, Moderator, Sponsor Liaison, Admin, Sponsor) are a
-- specialisation of `users` implemented as a role lookup (single-table
-- inheritance). Sponsor-specific attributes live in the `sponsors` subtype table.
CREATE TABLE roles (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(30)  NOT NULL,
  name        VARCHAR(60)  NOT NULL,
  description VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_roles_code (code)
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(60)  NOT NULL,
  description VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_permissions_code (code)
) ENGINE=InnoDB;

-- Role-action matrix for the 5 actor types (Module 1.5)
CREATE TABLE role_permissions (
  role_id       TINYINT UNSIGNED  NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id              TINYINT UNSIGNED NOT NULL,
  full_name            VARCHAR(150) NOT NULL,
  nic                  VARCHAR(20)  NOT NULL,
  phone                VARCHAR(20)  NOT NULL,
  email                VARCHAR(150) NULL,
  address              VARCHAR(255) NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  trust_score          TINYINT UNSIGNED NOT NULL DEFAULT 50,   -- cached; recomputed nightly + after each transaction
  gift_receive_enabled TINYINT(1)   NOT NULL DEFAULT 1,
  status               ENUM('pending','active','suspended','closed_standard','closed_donation')
                       NOT NULL DEFAULT 'pending',
  joined_at            DATETIME NULL,                          -- set when verification is approved
  closed_at            DATETIME NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_nic (nic),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB;

CREATE TABLE gn_divisions (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                 VARCHAR(120) NOT NULL,
  district             VARCHAR(100) NOT NULL,
  moderator_id         INT UNSIGNED NULL,                      -- current moderator (history in moderator_assignments)
  disaster_mode_active TINYINT(1)   NOT NULL DEFAULT 0,
  disaster_mode_until  DATETIME NULL,
  status               ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_gnd_name_district (name, district),
  CONSTRAINT fk_gnd_moderator FOREIGN KEY (moderator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Membership junction: one home community (never expires) + up to one active
-- temporary community per member (6-month window, extendable).
CREATE TABLE user_divisions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  gn_division_id  INT UNSIGNED NOT NULL,
  membership_type ENUM('home','temporary') NOT NULL,
  verified_by     INT UNSIGNED NULL,                           -- moderator who verified
  verified_at     DATETIME NULL,
  expires_at      DATETIME NULL,                               -- NULL for home
  proof_type      VARCHAR(50)  NULL,                           -- lease, enrolment letter, employer letter...
  proof_file_path VARCHAR(255) NULL,
  status          ENUM('pending','active','paused','expired','rejected','deactivated')
                  NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ud_user_division (user_id, gn_division_id),
  KEY idx_ud_expiry (status, expires_at),                      -- cron: auto-pause on expiry
  CONSTRAINT fk_ud_user     FOREIGN KEY (user_id)        REFERENCES users (id)        ON DELETE CASCADE,
  CONSTRAINT fk_ud_division FOREIGN KEY (gn_division_id) REFERENCES gn_divisions (id),
  CONSTRAINT fk_ud_verifier FOREIGN KEY (verified_by)    REFERENCES users (id)        ON DELETE SET NULL
) ENGINE=InnoDB;

-- Appointment history + 500-point conduct bond (Sections 16.2, 17)
CREATE TABLE moderator_assignments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  gn_division_id INT UNSIGNED NOT NULL,
  appointed_by   INT UNSIGNED NULL,                            -- admin
  appointed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bond_points    INT UNSIGNED NOT NULL DEFAULT 500,
  bond_status    ENUM('held','returned','forfeited') NOT NULL DEFAULT 'held',
  status         ENUM('trial','active','suspended','resigned','removed') NOT NULL DEFAULT 'trial',
  ended_at       DATETIME NULL,
  end_reason     VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_ma_division (gn_division_id, status),
  CONSTRAINT fk_ma_user      FOREIGN KEY (user_id)        REFERENCES users (id),
  CONSTRAINT fk_ma_division  FOREIGN KEY (gn_division_id) REFERENCES gn_divisions (id),
  CONSTRAINT fk_ma_appointer FOREIGN KEY (appointed_by)   REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Permanent audit log (Section 17.4, Rule 4)
CREATE TABLE moderator_conduct_history (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  moderator_id  INT UNSIGNED NOT NULL,
  incident_type VARCHAR(60)  NOT NULL,
  notes         TEXT NULL,
  recorded_by   INT UNSIGNED NULL,
  recorded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_mch_moderator FOREIGN KEY (moderator_id) REFERENCES users (id),
  CONSTRAINT fk_mch_recorder  FOREIGN KEY (recorded_by)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- 2. ITEMS, DISCOVERY & DONATIONS
-- ============================================================================

CREATE TABLE item_categories (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80)  NOT NULL,
  description   VARCHAR(255) NULL,
  display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_name (name)
) ENGINE=InnoDB;

CREATE TABLE items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id         INT UNSIGNED NOT NULL,
  gn_division_id   INT UNSIGNED NOT NULL,                      -- division where the item is listed
  category_id      INT UNSIGNED NOT NULL,
  title            VARCHAR(150) NOT NULL,
  description      TEXT NULL,
  listing_type     ENUM('rental','donation') NOT NULL DEFAULT 'rental',
  declared_value   INT UNSIGNED NOT NULL,                      -- points; 1 point = 1 rupee backing (internal)
  value_proof_type VARCHAR(50)  NULL,
  value_proof_path VARCHAR(255) NULL,
  photos           JSON NULL,                                  -- array of photo paths
  daily_rate       INT UNSIGNED NULL,                          -- lender-set; NULL if not offered
  monthly_rate     INT UNSIGNED NULL,
  status           ENUM('pending_approval','active','paused','borrowed','donated','rejected','archived')
                   NOT NULL DEFAULT 'pending_approval',
  approved_by      INT UNSIGNED NULL,                          -- moderator
  approved_at      DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_items_browse (gn_division_id, status, category_id),
  FULLTEXT KEY ft_items_search (title, description),           -- Month 6: MySQL FULLTEXT search
  CONSTRAINT fk_items_owner    FOREIGN KEY (owner_id)       REFERENCES users (id),
  CONSTRAINT fk_items_division FOREIGN KEY (gn_division_id) REFERENCES gn_divisions (id),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id)    REFERENCES item_categories (id),
  CONSTRAINT fk_items_approver FOREIGN KEY (approved_by)    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_rental_has_rate CHECK (listing_type = 'donation' OR daily_rate IS NOT NULL OR monthly_rate IS NOT NULL)
) ENGINE=InnoDB;

-- Availability calendar: lender-blocked date ranges (Module 2.4)
CREATE TABLE item_availability_blocks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id    INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  note       VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_iab_item_dates (item_id, start_date, end_date),
  CONSTRAINT fk_iab_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
  CONSTRAINT chk_iab_range CHECK (end_date >= start_date)
) ENGINE=InnoDB;

-- Saved searches (Module 2.3)
CREATE TABLE saved_searches (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(80) NOT NULL,
  filters    JSON NOT NULL,                                    -- category, rate range, listing type...
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Permanent item transfers — no points move (Section 13)
CREATE TABLE donations (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id        INT UNSIGNED NOT NULL,
  donor_id       INT UNSIGNED NOT NULL,
  recipient_id   INT UNSIGNED NULL,                            -- NULL until selected / FCFS claimed
  selection_mode ENUM('donor_chooses','first_come') NOT NULL DEFAULT 'donor_chooses',
  status         ENUM('open','recipient_selected','completed','cancelled') NOT NULL DEFAULT 'open',
  handover_at    DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_don_item      FOREIGN KEY (item_id)      REFERENCES items (id),
  CONSTRAINT fk_don_donor     FOREIGN KEY (donor_id)     REFERENCES users (id),
  CONSTRAINT fk_don_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE donation_requests (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  donation_id  INT UNSIGNED NOT NULL,
  requester_id INT UNSIGNED NOT NULL,
  message      VARCHAR(255) NULL,
  status       ENUM('pending','selected','declined','withdrawn') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_dr_once (donation_id, requester_id),
  CONSTRAINT fk_dr_donation  FOREIGN KEY (donation_id)  REFERENCES donations (id) ON DELETE CASCADE,
  CONSTRAINT fk_dr_requester FOREIGN KEY (requester_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- ============================================================================
-- 3. RENTALS, CONDITION PHOTOS & DISPUTES
-- ============================================================================

CREATE TABLE bookings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id       INT UNSIGNED NOT NULL,
  borrower_id   INT UNSIGNED NOT NULL,
  lender_id     INT UNSIGNED NOT NULL,
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  rate_basis    ENUM('daily','monthly') NOT NULL,
  agreed_rate   INT UNSIGNED NOT NULL,                         -- per day or per month, in points
  rental_charge INT UNSIGNED NOT NULL,                         -- total charge held in In-Flight
  late_buffer   INT UNSIGNED NOT NULL,                         -- one daily rate (Section 7.6)
  status        ENUM('requested','accepted','rejected','awaiting_handover','in_progress',
                     'awaiting_return','pending_moderator','escalated','completed',
                     'cancelled','auto_cancelled') NOT NULL DEFAULT 'requested',
  requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at   DATETIME NULL,
  closed_at     DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_bk_item (item_id, status),
  KEY idx_bk_borrower (borrower_id, status),
  KEY idx_bk_lender (lender_id, status),
  KEY idx_bk_overdue (status, end_date),                       -- cron: late-fee sweep
  CONSTRAINT fk_bk_item     FOREIGN KEY (item_id)     REFERENCES items (id),
  CONSTRAINT fk_bk_borrower FOREIGN KEY (borrower_id) REFERENCES users (id),
  CONSTRAINT fk_bk_lender   FOREIGN KEY (lender_id)   REFERENCES users (id),
  CONSTRAINT chk_bk_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB;

-- Immutable handover baseline once both parties accept (Section 10.0.1)
CREATE TABLE handover_records (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id           INT UNSIGNED NOT NULL,
  handover_at          DATETIME NULL,
  lender_photos        JSON NULL,                              -- 1-5 photo paths, GD-timestamped
  borrower_photos      JSON NULL,
  lender_notes         TEXT NULL,
  borrower_notes       TEXT NULL,
  lender_accepted_at   DATETIME NULL,
  borrower_accepted_at DATETIME NULL,                          -- both set => baseline locked
  PRIMARY KEY (id),
  UNIQUE KEY uk_hr_booking (booking_id),                       -- 1:1 with booking
  CONSTRAINT fk_hr_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE return_records (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id        INT UNSIGNED NOT NULL,
  return_at         DATETIME NULL,
  lender_photos     JSON NULL,
  borrower_photos   JSON NULL,
  lender_decision   ENUM('accepted','claim_raised') NULL,
  borrower_decision ENUM('accepted','contested') NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_rr_booking (booking_id),                       -- 1:1 with booking
  CONSTRAINT fk_rr_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- All claims resolve via the moderator path (Section 10)
CREATE TABLE damage_claims (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id    INT UNSIGNED NOT NULL,
  raised_by     INT UNSIGNED NOT NULL,                         -- normally the lender
  severity      ENUM('minor','moderate','major','total_loss') NOT NULL,
  description   TEXT NULL,
  evidence_path VARCHAR(255) NULL,
  status        ENUM('open','pending_moderator','resolved','escalated','closed') NOT NULL DEFAULT 'open',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dc_status (status),
  CONSTRAINT fk_dc_booking FOREIGN KEY (booking_id) REFERENCES bookings (id),
  CONSTRAINT fk_dc_raiser  FOREIGN KEY (raised_by)  REFERENCES users (id)
) ENGINE=InnoDB;

-- In-person resolution with three-party signoff (Section 10.2)
CREATE TABLE moderator_resolutions (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  damage_claim_id     INT UNSIGNED NOT NULL,
  moderator_id        INT UNSIGNED NOT NULL,
  outcome_category    VARCHAR(60) NULL,                        -- e.g. 'no_penalty', 'partial', 'full'
  penalty_points      INT UNSIGNED NOT NULL DEFAULT 0,
  points_movement     JSON NULL,                               -- structured movement decided by moderator
  notes               TEXT NULL,
  met_at              DATETIME NULL,
  lender_signoff_at   DATETIME NULL,
  borrower_signoff_at DATETIME NULL,
  closed_at           DATETIME NULL,                           -- set only when all three have signed
  PRIMARY KEY (id),
  UNIQUE KEY uk_mr_claim (damage_claim_id),                    -- 1:1 with claim
  CONSTRAINT fk_mr_claim     FOREIGN KEY (damage_claim_id) REFERENCES damage_claims (id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_moderator FOREIGN KEY (moderator_id)    REFERENCES users (id)
) ENGINE=InnoDB;

-- Admin escalations (refused signoffs, moderator-involved bookings, vanished borrowers)
CREATE TABLE disputes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id      INT UNSIGNED NULL,
  damage_claim_id INT UNSIGNED NULL,
  raised_by       INT UNSIGNED NOT NULL,
  admin_id        INT UNSIGNED NULL,
  reason          VARCHAR(255) NOT NULL,
  status          ENUM('open','ruled','closed') NOT NULL DEFAULT 'open',
  resolution      TEXT NULL,
  ruling_at       DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dsp_booking FOREIGN KEY (booking_id)      REFERENCES bookings (id)      ON DELETE SET NULL,
  CONSTRAINT fk_dsp_claim   FOREIGN KEY (damage_claim_id) REFERENCES damage_claims (id) ON DELETE SET NULL,
  CONSTRAINT fk_dsp_raiser  FOREIGN KEY (raised_by)       REFERENCES users (id),
  CONSTRAINT fk_dsp_admin   FOREIGN KEY (admin_id)        REFERENCES users (id)         ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- 4. POINTS, SPONSORS & AID  (append-only ledger core)
-- ============================================================================

-- Exactly six rows, seeded below. Balances verified by the nightly invariant check.
CREATE TABLE point_pools (
  pool_code  VARCHAR(30) NOT NULL,                             -- sponsor | aid | reserve | in_flight | member_wallets | retired
  name       VARCHAR(60) NOT NULL,
  balance    BIGINT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pool_code)
) ENGINE=InnoDB;

-- Cached per-member balance (source of truth is point_ledger; verified nightly).
-- Locked with SELECT ... FOR UPDATE inside every point-movement transaction.
CREATE TABLE member_wallets (
  user_id     INT UNSIGNED NOT NULL,
  balance     INT UNSIGNED NOT NULL DEFAULT 0,                 -- UNSIGNED enforces "never negative" (Section 7.8)
  bond_locked INT UNSIGNED NOT NULL DEFAULT 0,                 -- moderator conduct bond, non-spendable
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_mw_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sponsors (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NULL,                            -- optional sponsor login account
  company_name   VARCHAR(150) NOT NULL,
  contact_name   VARCHAR(100) NULL,
  contact_phone  VARCHAR(20)  NULL,
  branding_path  VARCHAR(255) NULL,
  total_injected BIGINT UNSIGNED NOT NULL DEFAULT 0,           -- cached sum of purchases
  active         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sp_user (user_id),
  CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Cash recorded offline by the Liaison; points created at 1:1 after overhead split
CREATE TABLE sponsor_purchases (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sponsor_id       INT UNSIGNED NOT NULL,
  cash_amount      INT UNSIGNED NOT NULL,                      -- rupees received
  overhead_amount  INT UNSIGNED NOT NULL DEFAULT 0,            -- 10% operating overhead bucket
  points_credited  INT UNSIGNED NOT NULL,                      -- points created into pools
  sponsor_pool_pct TINYINT UNSIGNED NOT NULL DEFAULT 85,       -- sponsor-directed allocation (15.3)
  aid_pool_pct     TINYINT UNSIGNED NOT NULL DEFAULT 15,
  receipt_number   VARCHAR(50) NOT NULL,
  recorded_by      INT UNSIGNED NOT NULL,                      -- the Sponsor Liaison
  recorded_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_spp_receipt (receipt_number),
  CONSTRAINT fk_spp_sponsor  FOREIGN KEY (sponsor_id)  REFERENCES sponsors (id),
  CONSTRAINT fk_spp_recorder FOREIGN KEY (recorded_by) REFERENCES users (id),
  CONSTRAINT chk_spp_split CHECK (sponsor_pool_pct + aid_pool_pct = 100)
) ENGINE=InnoDB;

-- Request -> vouch -> approve -> use -> expiry -> audit (Section 12)
CREATE TABLE aid_grants (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id        INT UNSIGNED NOT NULL,
  gn_division_id   INT UNSIGNED NOT NULL,
  requested_amount INT UNSIGNED NOT NULL,
  purpose          VARCHAR(255) NOT NULL,                      -- purpose-tied (12.1)
  evidence_path    VARCHAR(255) NULL,
  moderator_id     INT UNSIGNED NULL,
  moderator_vouch  TEXT NULL,
  vouched_at       DATETIME NULL,
  liaison_id       INT UNSIGNED NULL,
  approved_amount  INT UNSIGNED NULL,
  status           ENUM('requested','vouched','rejected_moderator','info_requested','approved',
                        'rejected_liaison','disbursed','partially_returned','expired','closed')
                   NOT NULL DEFAULT 'requested',
  expires_at       DATETIME NULL,                              -- 30-day use window
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ag_member_year (member_id, created_at),              -- 500 pts / member / year cap
  CONSTRAINT fk_ag_member    FOREIGN KEY (member_id)      REFERENCES users (id),
  CONSTRAINT fk_ag_division  FOREIGN KEY (gn_division_id) REFERENCES gn_divisions (id),
  CONSTRAINT fk_ag_moderator FOREIGN KEY (moderator_id)   REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_ag_liaison   FOREIGN KEY (liaison_id)     REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE gifts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id    INT UNSIGNED NOT NULL,
  recipient_id INT UNSIGNED NOT NULL,
  amount       INT UNSIGNED NOT NULL,
  reason       VARCHAR(100) NOT NULL,                          -- required, max 100 chars (11.1)
  sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gifts_sender (sender_id, sent_at),
  KEY idx_gifts_pattern (sender_id, recipient_id, sent_at),    -- round-trip pattern detection
  CONSTRAINT fk_g_sender    FOREIGN KEY (sender_id)    REFERENCES users (id),
  CONSTRAINT fk_g_recipient FOREIGN KEY (recipient_id) REFERENCES users (id),
  CONSTRAINT chk_g_not_self CHECK (sender_id <> recipient_id)
) ENGINE=InnoDB;

-- Rate-limiter counters: 200/day, 2000/year per sender (Section 11.1)
CREATE TABLE gift_usage_counters (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  day        DATE NOT NULL,
  day_total  INT UNSIGNED NOT NULL DEFAULT 0,
  year       SMALLINT UNSIGNED NOT NULL,
  year_total INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_guc_user_day (user_id, day),
  CONSTRAINT fk_guc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- THE append-only ledger. INSERT-only, inside SERIALIZABLE transactions.
-- Each side of a movement is either a pool (pool_code set) or a member wallet
-- (user_id set) — never both. A fully-NULL "from" side = point creation
-- (sponsor injection); points exit only by moving into the 'retired' pool.
CREATE TABLE point_ledger (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_pool_code      VARCHAR(30)  NULL,
  from_user_id        INT UNSIGNED NULL,
  to_pool_code        VARCHAR(30)  NULL,
  to_user_id          INT UNSIGNED NULL,
  amount              INT UNSIGNED NOT NULL,
  reason              ENUM('sponsor_injection','welcome_bonus','moderator_stipend','festival_drop',
                           'rental_charge','buffer_hold','buffer_refund','rental_payout','late_fee',
                           'damage_penalty','gift','aid_grant','aid_return','shortfall_writeoff',
                           'bond_hold','bond_return','bond_forfeit','account_closure','recycle')
                      NOT NULL,
  booking_id          INT UNSIGNED NULL,
  aid_grant_id        INT UNSIGNED NULL,
  gift_id             INT UNSIGNED NULL,
  sponsor_purchase_id INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pl_created (created_at),
  KEY idx_pl_from_user (from_user_id, created_at),
  KEY idx_pl_to_user (to_user_id, created_at),
  CONSTRAINT fk_pl_from_pool FOREIGN KEY (from_pool_code)      REFERENCES point_pools (pool_code),
  CONSTRAINT fk_pl_to_pool   FOREIGN KEY (to_pool_code)        REFERENCES point_pools (pool_code),
  CONSTRAINT fk_pl_from_user FOREIGN KEY (from_user_id)        REFERENCES users (id),
  CONSTRAINT fk_pl_to_user   FOREIGN KEY (to_user_id)          REFERENCES users (id),
  CONSTRAINT fk_pl_booking   FOREIGN KEY (booking_id)          REFERENCES bookings (id),
  CONSTRAINT fk_pl_aid       FOREIGN KEY (aid_grant_id)        REFERENCES aid_grants (id),
  CONSTRAINT fk_pl_gift      FOREIGN KEY (gift_id)             REFERENCES gifts (id),
  CONSTRAINT fk_pl_purchase  FOREIGN KEY (sponsor_purchase_id) REFERENCES sponsor_purchases (id),
  CONSTRAINT chk_pl_amount    CHECK (amount > 0),
  CONSTRAINT chk_pl_from_side CHECK (NOT (from_pool_code IS NOT NULL AND from_user_id IS NOT NULL)),
  CONSTRAINT chk_pl_to_side   CHECK (
      (to_pool_code IS NOT NULL AND to_user_id IS NULL) OR
      (to_pool_code IS NULL     AND to_user_id IS NOT NULL))
) ENGINE=InnoDB;

-- ============================================================================
-- 5. RATINGS, NOTIFICATIONS, DISASTER MODE & OPERATIONS
-- ============================================================================

-- Both parties rate after every rental, donation, gift, and cancellation (3.5)
CREATE TABLE ratings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id  INT UNSIGNED NULL,
  donation_id INT UNSIGNED NULL,
  gift_id     INT UNSIGNED NULL,
  rater_id    INT UNSIGNED NOT NULL,
  ratee_id    INT UNSIGNED NOT NULL,
  context     ENUM('rental','donation','gift','cancellation') NOT NULL,
  stars       TINYINT UNSIGNED NOT NULL,
  comment     VARCHAR(500) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rt_ratee (ratee_id),                                 -- trust-score aggregation
  CONSTRAINT fk_rt_booking  FOREIGN KEY (booking_id)  REFERENCES bookings (id)  ON DELETE SET NULL,
  CONSTRAINT fk_rt_donation FOREIGN KEY (donation_id) REFERENCES donations (id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_gift     FOREIGN KEY (gift_id)     REFERENCES gifts (id)     ON DELETE SET NULL,
  CONSTRAINT fk_rt_rater    FOREIGN KEY (rater_id)    REFERENCES users (id),
  CONSTRAINT fk_rt_ratee    FOREIGN KEY (ratee_id)    REFERENCES users (id),
  CONSTRAINT chk_rt_stars   CHECK (stars BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- Polled every 8 seconds by vanilla JS (Section 22.4)
CREATE TABLE notifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(50) NOT NULL,
  payload    JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at    DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_nt_unread (user_id, read_at, created_at),
  CONSTRAINT fk_nt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Alert-and-connect bridge, per division (Section 14)
CREATE TABLE disaster_events (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gn_division_id INT UNSIGNED NOT NULL,
  started_by     INT UNSIGNED NOT NULL,                        -- admin
  reason         VARCHAR(255) NOT NULL,
  started_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  planned_end_at DATETIME NULL,
  ended_at       DATETIME NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_de_division FOREIGN KEY (gn_division_id) REFERENCES gn_divisions (id),
  CONSTRAINT fk_de_starter  FOREIGN KEY (started_by)     REFERENCES users (id)
) ENGINE=InnoDB;

-- Off-platform relief verified & recorded by the Liaison for CSR reports (14.1)
CREATE TABLE disaster_contributions (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  disaster_event_id INT UNSIGNED NOT NULL,
  sponsor_id        INT UNSIGNED NOT NULL,
  description       VARCHAR(255) NOT NULL,
  estimated_value   INT UNSIGNED NULL,                         -- rupees, record-keeping only; no points move
  verified_by       INT UNSIGNED NOT NULL,                     -- the Sponsor Liaison
  recorded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dcn_event    FOREIGN KEY (disaster_event_id) REFERENCES disaster_events (id),
  CONSTRAINT fk_dcn_sponsor  FOREIGN KEY (sponsor_id)        REFERENCES sponsors (id),
  CONSTRAINT fk_dcn_verifier FOREIGN KEY (verified_by)       REFERENCES users (id)
) ENGINE=InnoDB;

-- Every cron job logs here; admin dashboard alerts if a job is >24h overdue
CREATE TABLE cron_runs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_name    VARCHAR(60) NOT NULL,                            -- check_invariant, pay_stipends, charge_late_fees...
  started_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  status      ENUM('running','success','failed') NOT NULL DEFAULT 'running',
  notes       TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_cr_job (job_name, started_at)
) ENGINE=InnoDB;

-- ============================================================================
-- 6. SEED DATA
-- ============================================================================

INSERT INTO roles (code, name, description) VALUES
  ('member',          'Member',          'Verified resident: lends, borrows, donates, gifts, requests aid'),
  ('moderator',       'Moderator',       'One per GN division: verifies members, approves listings, resolves damage'),
  ('sponsor_liaison', 'Sponsor Liaison', 'Platform-wide: onboards sponsors, records purchases, approves aid grants'),
  ('admin',           'Admin',           'Platform operator: divisions, appointments, escalations, Disaster Mode'),
  ('sponsor',         'Sponsor',         'Company funding the points pool for CSR visibility');

INSERT INTO point_pools (pool_code, name, balance) VALUES
  ('sponsor',        'Sponsor Pool',   0),
  ('aid',            'Aid Pool',       0),
  ('reserve',        'Reserve Pool',   0),
  ('in_flight',      'In-Flight Pool', 0),
  ('member_wallets', 'Member Wallets', 0),
  ('retired',        'Retired Pool',   0);

INSERT INTO item_categories (name, description, display_order) VALUES
  ('Tools & Hardware',      'Drills, ladders, pressure washers, hand tools', 1),
  ('Household Appliances',  'Fans, sewing machines, rice cookers, irons',    2),
  ('Electronics',           'Projectors, speakers, cameras, cables',         3),
  ('Books & Media',         'Books, schoolbooks, board games',               4),
  ('Kitchen & Cooking',     'Large pots, blenders, party cookware',          5),
  ('Travel & Outdoors',     'Suitcases, camping tents, cool boxes',          6),
  ('Baby & Kids',           'Cots, prams, high chairs, toys',                7),
  ('Furniture & Events',    'Plastic chairs, folding tables, canopies',      8),
  ('Clothing & Textiles',   'Formal wear, costumes, linens',                 9),
  ('Other',                 'Everything else',                               10);

-- ============================================================================
-- Nightly invariant check (Section 7.5), run by scripts/check_invariant.php:
--   SUM(point_pools.balance)  must equal
--   (SELECT COALESCE(SUM(amount),0) FROM point_ledger
--     WHERE from_pool_code IS NULL AND from_user_id IS NULL)   -- total ever created
--   minus points parked in 'retired'                            -- total exited
-- Any drift => critical-bug alert.
-- ============================================================================
