-- ============================================================================
--  MITHRA — 004 Align the schema with the revised project plan
--
--  Migration 001 was written against the earlier proposal. Migrations are
--  append-only (Rules/CONVENTIONS.md §3), so 001 stays as it was applied and
--  this file corrects it. Where a comment in 001 disagrees with this file,
--  this file and Rules/Mithra_Project_Plan_Revised.md win:
--
--    * Sponsor money becomes points at 1 rupee = 1 point with no deductions
--      (Plan §15.2). There is no overhead bucket, and every contribution is
--      recorded as a General / Aid split in points (§15.3).
--    * Points are never destroyed. The Retired Pool is one of the six pools,
--      so the nightly invariant is simply
--        SUM(point_pools.balance) = SUM(amount of every creation entry)
--      (§7.4) — nothing is subtracted for 'retired'.
--    * Damage claims take the simple path or the moderator path (§10.3).
--    * Members never carry debt: the Reserve Pool covers a shortfall at the
--      moment it happens (§7.7), so there are no write-offs.
--
--  Run after 003_add_identity_constraints.sql.
-- ============================================================================

USE mithra;

-- ----------------------------------------------------------------------------
-- 1. Registration documents (Plan §18.1, §25.3)
--    The proof of address lives on the home membership row
--    (user_divisions.proof_file_path); the NIC photograph belongs to the person.
-- ----------------------------------------------------------------------------

ALTER TABLE users
  ADD COLUMN nic_photo_path VARCHAR(255) NULL AFTER nic;

-- ----------------------------------------------------------------------------
-- 2. Sponsor contributions — 1:1, no deductions, General / Aid in points
--    (Plan §15.1–15.3, §18.6)
-- ----------------------------------------------------------------------------

ALTER TABLE sponsor_purchases
  ADD COLUMN general_points INT UNSIGNED NOT NULL DEFAULT 0 AFTER cash_amount,
  ADD COLUMN aid_points     INT UNSIGNED NOT NULL DEFAULT 0 AFTER general_points;

UPDATE sponsor_purchases
   SET general_points = ROUND(points_credited * sponsor_pool_pct / 100),
       aid_points     = points_credited - ROUND(points_credited * sponsor_pool_pct / 100);

-- The recorded cash is exactly the points it created: nothing was withheld.
UPDATE sponsor_purchases
   SET cash_amount = general_points + aid_points;

ALTER TABLE sponsor_purchases
  DROP CONSTRAINT chk_spp_split;

ALTER TABLE sponsor_purchases
  DROP COLUMN overhead_amount,
  DROP COLUMN points_credited,
  DROP COLUMN sponsor_pool_pct,
  DROP COLUMN aid_pool_pct,
  ADD CONSTRAINT chk_sc_one_to_one CHECK (general_points + aid_points = cash_amount),
  ADD CONSTRAINT chk_sc_positive   CHECK (cash_amount > 0);

RENAME TABLE sponsor_purchases TO sponsor_contributions;

ALTER TABLE sponsors
  CHANGE COLUMN total_injected total_contributed BIGINT UNSIGNED NOT NULL DEFAULT 0;

-- The ledger links each creation entry to the contribution that funded it.
ALTER TABLE point_ledger
  DROP FOREIGN KEY fk_pl_purchase;

ALTER TABLE point_ledger
  CHANGE COLUMN sponsor_purchase_id contribution_id INT UNSIGNED NULL,
  ADD CONSTRAINT fk_pl_contribution FOREIGN KEY (contribution_id) REFERENCES sponsor_contributions (id);

-- ----------------------------------------------------------------------------
-- 3. Ledger reasons — one per movement in Plan §7.3
--    Widen first, rename the old values, then drop them.
-- ----------------------------------------------------------------------------

ALTER TABLE point_ledger
  MODIFY COLUMN reason ENUM(
      'sponsor_injection','festival_drop','shortfall_writeoff',
      'sponsor_contribution','welcome_bonus','moderator_stipend','community_reward','reserve_topup',
      'rental_charge','buffer_hold','buffer_refund','rental_payout','late_fee','damage_penalty',
      'shortfall_cover','gift','aid_grant','aid_return',
      'bond_hold','bond_return','bond_forfeit','account_closure','parting_gift','recycle'
  ) NOT NULL;

UPDATE point_ledger SET reason = 'sponsor_contribution' WHERE reason = 'sponsor_injection';
UPDATE point_ledger SET reason = 'community_reward'     WHERE reason = 'festival_drop';
UPDATE point_ledger SET reason = 'shortfall_cover'      WHERE reason = 'shortfall_writeoff';

ALTER TABLE point_ledger
  MODIFY COLUMN reason ENUM(
      'sponsor_contribution',   -- creation: sponsor cash -> Sponsor / Aid Pool (§7.2)
      'welcome_bonus',          -- Sponsor Pool -> member, once, after verification (§6.4)
      'moderator_stipend',      -- Sponsor Pool -> moderator, monthly (§16.6)
      'community_reward',       -- Sponsor Pool -> members, approved reward (§7.3)
      'reserve_topup',          -- Sponsor Pool -> Reserve Pool (§7.7)
      'rental_charge',          -- borrower -> In-Flight
      'buffer_hold',            -- borrower -> In-Flight (late-fee buffer)
      'buffer_refund',          -- In-Flight -> borrower
      'rental_payout',          -- In-Flight -> lender
      'late_fee',               -- borrower / buffer -> lender (§7.6)
      'damage_penalty',         -- borrower -> lender (§10.4, §10.5)
      'shortfall_cover',        -- Reserve Pool -> lender (§7.7)
      'gift',                   -- member -> member (§11)
      'aid_grant',              -- Aid Pool -> member (§12)
      'aid_return',             -- member -> Aid Pool, half of unused grant (§12.2)
      'bond_hold',              -- Sponsor Pool -> moderator, locked (§16.7)
      'bond_return',            -- moderator bond -> Sponsor Pool (resignation)
      'bond_forfeit',           -- moderator bond -> Reserve Pool (removal)
      'account_closure',        -- Type A: member -> Retired Pool (§17)
      'parting_gift',           -- Type B: member -> Aid Pool (§17)
      'recycle'                 -- Retired Pool -> Sponsor Pool (scheduled)
  ) NOT NULL;

-- ----------------------------------------------------------------------------
-- 4. Two-track damage handling (Plan §10.3–10.5)
-- ----------------------------------------------------------------------------

ALTER TABLE damage_claims
  ADD COLUMN proposed_penalty  INT UNSIGNED NULL AFTER description,
  ADD COLUMN track             ENUM('simple','moderator') NULL AFTER evidence_path,
  ADD COLUMN borrower_response ENUM('accepted','contested') NULL AFTER track,
  ADD COLUMN responded_at      DATETIME NULL AFTER borrower_response,
  MODIFY COLUMN status ENUM('open','awaiting_borrower','pending_moderator','resolved','escalated','closed')
                NOT NULL DEFAULT 'open';

-- ----------------------------------------------------------------------------
-- 5. Moderator involvement flag — Rule 1, auto-escalation (Plan §16.5)
-- ----------------------------------------------------------------------------

ALTER TABLE bookings
  ADD COLUMN moderator_involved TINYINT(1) NOT NULL DEFAULT 0 AFTER late_buffer;

UPDATE bookings b
  JOIN items i        ON i.id = b.item_id
  JOIN gn_divisions d ON d.id = i.gn_division_id
   SET b.moderator_involved = 1
 WHERE d.moderator_id IN (b.borrower_id, b.lender_id);

-- ----------------------------------------------------------------------------
-- 6. Declared-value validation and its audit trail (Plan §9)
--    value_proof_type: receipt | warranty | price_reference | inspection
-- ----------------------------------------------------------------------------

ALTER TABLE items
  ADD COLUMN value_status ENUM('pending','validated','adjusted','rejected') NOT NULL DEFAULT 'pending'
    AFTER value_proof_path;

UPDATE items SET value_status = 'validated' WHERE status IN ('active','paused','borrowed','donated');
UPDATE items SET value_status = 'rejected'  WHERE status = 'rejected';
UPDATE items SET value_proof_type = 'receipt' WHERE value_proof_type = 'photo';

-- Append-only: every approve / adjust / reject, by whom and why (§9.2).
CREATE TABLE item_value_reviews (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id        INT UNSIGNED NOT NULL,
  reviewer_id    INT UNSIGNED NOT NULL,                       -- the moderator, or the Admin (§16.5)
  decision       ENUM('approved','adjusted','rejected') NOT NULL,
  previous_value INT UNSIGNED NOT NULL,
  new_value      INT UNSIGNED NOT NULL,
  reason         VARCHAR(255) NULL,                           -- required for adjust and reject
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ivr_item (item_id, created_at),
  KEY idx_ivr_reviewer (reviewer_id),
  CONSTRAINT fk_ivr_item     FOREIGN KEY (item_id)     REFERENCES items (id),
  CONSTRAINT fk_ivr_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 7. Moderator selection, appointment and expenses (Plan §16)
-- ----------------------------------------------------------------------------

ALTER TABLE moderator_assignments
  ADD COLUMN objection_window_ends DATETIME NULL AFTER appointed_at,
  ADD COLUMN trial_ends_at         DATETIME NULL AFTER objection_window_ends,
  ADD COLUMN confirmed_at          DATETIME NULL AFTER trial_ends_at;

UPDATE moderator_assignments
   SET objection_window_ends = appointed_at + INTERVAL 7 DAY,
       trial_ends_at         = appointed_at + INTERVAL 6 MONTH;

CREATE TABLE moderator_candidates (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gn_division_id      INT UNSIGNED NOT NULL,
  user_id             INT UNSIGNED NULL,                      -- NULL for a launch nominee without an account
  candidate_name      VARCHAR(150) NOT NULL,
  nominated_by        INT UNSIGNED NULL,
  endorsement         VARCHAR(255) NULL,                      -- GN Officer or community organisation (§16.1)
  nic_verified        TINYINT(1) NOT NULL DEFAULT 0,
  residence_verified  TINYINT(1) NOT NULL DEFAULT 0,
  reference_1         VARCHAR(150) NULL,                      -- two different households
  reference_2         VARCHAR(150) NULL,
  declaration_signed  TINYINT(1) NOT NULL DEFAULT 0,
  interview_score     TINYINT UNSIGNED NULL,                  -- each 0-100; weights 35 / 35 / 20 / 10
  involvement_score   TINYINT UNSIGNED NULL,
  references_score    TINYINT UNSIGNED NULL,
  competency_score    TINYINT UNSIGNED NULL,
  total_score         DECIMAL(5,2) NULL,
  status              ENUM('nominated','checks_passed','evaluated','provisional','appointed','withdrawn','rejected')
                      NOT NULL DEFAULT 'nominated',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mc_division (gn_division_id, status),
  CONSTRAINT fk_mc_division  FOREIGN KEY (gn_division_id) REFERENCES gn_divisions (id),
  CONSTRAINT fk_mc_user      FOREIGN KEY (user_id)        REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_mc_nominator FOREIGN KEY (nominated_by)   REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Claimed in person against original receipts, paid in cash from operational
-- funds — never in points (§16.6, §15.9).
CREATE TABLE moderator_expense_claims (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  moderator_id      INT UNSIGNED NOT NULL,
  purpose           VARCHAR(255) NOT NULL,
  amount            INT UNSIGNED NOT NULL,                    -- rupees claimed
  receipt_reference VARCHAR(100) NOT NULL,
  checked_by        INT UNSIGNED NULL,
  approved_amount   INT UNSIGNED NULL,
  paid_at           DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mec_moderator (moderator_id, created_at),
  CONSTRAINT fk_mec_moderator FOREIGN KEY (moderator_id) REFERENCES users (id),
  CONSTRAINT fk_mec_checker   FOREIGN KEY (checked_by)   REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 8. Disaster Mode contributions — cash or goods, with receipts (Plan §14.1)
-- ----------------------------------------------------------------------------

ALTER TABLE disaster_contributions
  ADD COLUMN contribution_kind ENUM('cash','goods') NOT NULL DEFAULT 'cash' AFTER sponsor_id,
  ADD COLUMN receipt_reference VARCHAR(100) NULL AFTER estimated_value,
  ADD COLUMN verified_at       DATETIME NULL AFTER verified_by;

UPDATE disaster_contributions SET verified_at = recorded_at;
