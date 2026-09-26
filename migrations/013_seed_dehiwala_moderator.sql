-- ============================================================================
--  MITHRA — 013 Dehiwala gets a moderator
--
--  Dehiwala (GN division 4) had no moderator. This adds one demo account and
--  appoints it, mirroring the Kollupitiya appointment in 002:
--
--    id 8  Moderator (Dehiwala)  R. Fernando  dehiwala.moderator@email.com
--
--  A separate account is used because a moderator's division is resolved
--  from gn_divisions.moderator_id (one division per moderator). The password
--  is "password", the same as every seeded account.
--
--  Run after 012_create_preview_record_sources.sql.
-- ============================================================================

USE mithra;

-- Password hash below is "password", the same as every seeded account.
INSERT INTO users
  (id, role_id, full_name, nic, phone, email, address, password_hash,
   trust_score, gift_receive_enabled, status, joined_at) VALUES
  (8, 2, 'R. Fernando', '198712345678', '+94 77 111 1008', 'dehiwala.moderator@email.com',
      '15 Hill Street, Dehiwala', '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      92, 1, 'active', '2025-04-10 09:00:00');

-- A moderator must live in the division they moderate.
INSERT INTO user_divisions (user_id, gn_division_id, membership_type, verified_by, verified_at, status)
  VALUES (8, 4, 'home', 6, '2025-04-10 09:00:00', 'active');

UPDATE gn_divisions SET moderator_id = 8 WHERE id = 4;

-- Appointed by the admin (id 6) with the 500-point conduct bond held.
INSERT INTO moderator_assignments
  (user_id, gn_division_id, appointed_by, appointed_at,
   objection_window_ends, trial_ends_at, bond_points, bond_status, status)
  VALUES (8, 4, 6, '2025-04-10 09:00:00',
          '2025-04-17 09:00:00', '2025-10-10 09:00:00', 500, 'held', 'active');

INSERT INTO member_wallets (user_id, balance, bond_locked) VALUES (8, 500, 500);
