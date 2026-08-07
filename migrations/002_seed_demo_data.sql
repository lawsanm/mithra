-- ============================================================================
--  MITHRA — 002 Seed demo data
--
--  Populates a working Kollupitiya division so the Member screens render real
--  data instead of hard-coded arrays. Figures mirror the Figma "Member" canvas.
--
--  DEV/DEMO ONLY. Every account shares the password "password".
--  Run after 001_create_schema.sql:
--    mysql -u root < migrations/001_create_schema.sql
--    mysql -u root < migrations/002_seed_demo_data.sql
--
--  Known limitation: point_pools balances and point_ledger rows below are
--  illustrative figures taken from the design. They do NOT satisfy the nightly
--  accounting invariant (schema §7.5) — generating a ledger that reconciles is
--  the Points module's job, not seed data's.
-- ============================================================================

USE mithra;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE point_ledger;
TRUNCATE TABLE notifications;
TRUNCATE TABLE ratings;
TRUNCATE TABLE gift_usage_counters;
TRUNCATE TABLE gifts;
TRUNCATE TABLE aid_grants;
TRUNCATE TABLE sponsor_purchases;
TRUNCATE TABLE sponsors;
TRUNCATE TABLE member_wallets;
TRUNCATE TABLE damage_claims;
TRUNCATE TABLE return_records;
TRUNCATE TABLE handover_records;
TRUNCATE TABLE bookings;
TRUNCATE TABLE donation_requests;
TRUNCATE TABLE donations;
TRUNCATE TABLE items;
TRUNCATE TABLE user_divisions;
TRUNCATE TABLE moderator_assignments;
TRUNCATE TABLE gn_divisions;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Divisions and people
-- ---------------------------------------------------------------------------

INSERT INTO gn_divisions (id, name, district, status) VALUES
  (1, 'Kollupitiya',   'Colombo', 'active'),
  (2, 'Bambalapitiya', 'Colombo', 'active'),
  (3, 'Wellawatte',    'Colombo', 'active'),
  (4, 'Dehiwala',      'Colombo', 'active'),
  (5, 'Mount Lavinia', 'Colombo', 'active'),
  (6, 'Nugegoda',      'Colombo', 'active');

-- Password for every seeded account is "password".
INSERT INTO users
  (id, role_id, full_name, nic, phone, email, address, password_hash,
   trust_score, gift_receive_enabled, status, joined_at) VALUES
  (1, 2, 'A. Akalvily',     '199012345671', '+94 77 111 1001', 'akalvily@example.lk',
      '11 Station Road, Colombo 03',  '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      96, 1, 'active', '2024-11-02 09:00:00'),
  (2, 1, 'T.H.K. Madushan', '199112345672', '+94 77 111 1002', 'madushan@example.lk',
      '58 Marine Drive, Colombo 03',  '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      96, 1, 'active', '2025-01-14 09:00:00'),
  (3, 1, 'J. Kavipriya',    '199212345673', '+94 77 111 1003', 'kavipriya@example.lk',
      '7/2 Bagatalle Road, Colombo 03', '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      97, 1, 'active', '2025-02-20 09:00:00'),
  (4, 1, 'M. Lawsan',       '200312345674', '+94 77 123 4567', 'lawsan@email.com',
      '24/3 Galle Road, Colombo 03',  '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      80, 1, 'active', '2025-03-08 09:00:00'),
  (5, 3, 'S. Perera',       '198812345675', '+94 77 111 1005', 'liaison@example.lk',
      'Mithra Office, Colombo 02',    '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      70, 0, 'active', '2024-09-01 09:00:00'),
  (6, 4, 'Hasith Kaveesha', '198512345676', '+94 77 111 1006', 'admin@example.lk',
      'Mithra Office, Colombo 02',    '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      70, 0, 'active', '2024-09-01 09:00:00');

UPDATE gn_divisions SET moderator_id = 1 WHERE id = 1;

INSERT INTO moderator_assignments (user_id, gn_division_id, appointed_by, bond_points, bond_status, status)
  VALUES (1, 1, 6, 500, 'held', 'active');

INSERT INTO user_divisions (user_id, gn_division_id, membership_type, verified_by, verified_at, status) VALUES
  (1, 1, 'home', 6, '2024-11-02 09:00:00', 'active'),
  (2, 1, 'home', 1, '2025-01-14 09:00:00', 'active'),
  (3, 1, 'home', 1, '2025-02-20 09:00:00', 'active'),
  (4, 1, 'home', 1, '2025-03-08 09:00:00', 'active');

-- ---------------------------------------------------------------------------
-- Items — ids 1-8 belong to neighbours (Browse), 9-13 to M. Lawsan (My Items)
-- ---------------------------------------------------------------------------

INSERT INTO items
  (id, owner_id, gn_division_id, category_id, title, description, listing_type,
   declared_value, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (1, 2, 1, 1, 'Bosch Cordless Drill GSB 120',
      'Lightly used cordless drill with two batteries, charger and a 20-piece bit set. Great for shelves, curtain rails and light masonry. Please return with both batteries charged.',
      'rental', 300, 15, 150, 'active', 1, '2025-06-01 10:00:00', '2025-06-01 09:00:00'),
  (2, 3, 1, 6, 'Camping Tent (4-person)', 'Sleeps four, poles and pegs included.',
      'rental', 400, 18, 180, 'active', 1, '2025-06-03 10:00:00', '2025-06-03 09:00:00'),
  (3, 1, 1, 5, 'Stand Mixer', 'Planetary stand mixer with dough hook and whisk.',
      'rental', 250, 12, 120, 'borrowed', 1, '2025-06-05 10:00:00', '2025-06-05 09:00:00'),
  (4, 1, 1, 3, 'Projector (Full HD)', '1080p projector with HDMI cable and tripod screen.',
      'rental', 600, 25, 250, 'active', 1, '2025-06-07 10:00:00', '2025-06-07 09:00:00'),
  (5, 3, 1, 1, 'Extension Ladder', 'Aluminium extension ladder, reaches 4.5 m.',
      'rental', 180, 8, 80, 'active', 1, '2025-06-09 10:00:00', '2025-06-09 09:00:00'),
  (6, 3, 1, 7, 'Baby Stroller', 'Foldable stroller with rain cover.',
      'rental', 220, 10, 100, 'active', 1, '2025-06-11 10:00:00', '2025-06-11 09:00:00'),
  (7, 2, 1, 2, 'Sewing Machine', 'Domestic sewing machine, straight and zigzag stitch.',
      'rental', 200, 9, 90, 'borrowed', 1, '2025-06-13 10:00:00', '2025-06-13 09:00:00'),
  (8, 1, 1, 8, 'Folding Tables ×2', 'Pair of 6 ft folding tables for events.',
      'rental', 150, 6, 60, 'active', 1, '2025-06-15 10:00:00', '2025-06-15 09:00:00'),
  (9, 4, 1, 2, 'Rice Cooker (1.8 L)', 'Family-size rice cooker with steamer tray.',
      'rental', 120, 5, 50, 'borrowed', 1, '2026-03-02 10:00:00', '2026-03-02 09:00:00'),
  (10, 4, 1, 1, 'Ladder (6 ft)', 'Six-foot A-frame step ladder.',
      'rental', 160, 8, 80, 'active', 1, '2026-04-14 10:00:00', '2026-04-14 09:00:00'),
  (11, 4, 1, 6, 'Badminton Racket Set', 'Four rackets, net and shuttles.',
      'rental', 90, 4, 40, 'borrowed', 1, '2026-05-20 10:00:00', '2026-05-20 09:00:00'),
  (12, 4, 1, 1, 'Pressure Washer', 'Electric pressure washer with patio attachment.',
      'rental', 500, 20, 200, 'pending_approval', NULL, NULL, '2026-07-16 09:00:00'),
  (13, 4, 1, 9, 'Baby Clothes Bundle', 'Bundle of 0–12 month clothing, freshly laundered.',
      'donation', 80, NULL, NULL, 'active', 1, '2026-07-08 10:00:00', '2026-07-08 09:00:00');

-- ---------------------------------------------------------------------------
-- Donation with three requests (Donations — Requests Received)
-- ---------------------------------------------------------------------------

INSERT INTO donations (id, item_id, donor_id, recipient_id, selection_mode, status, created_at)
  VALUES (1, 13, 4, NULL, 'donor_chooses', 'open', '2026-07-08 09:30:00');

INSERT INTO donation_requests (donation_id, requester_id, message, status, requested_at) VALUES
  (1, 3, 'Expecting our second in August — this would help so much. Can collect any evening.',
      'pending', '2026-07-09 18:20:00'),
  (1, 2, 'My sister just moved back with her baby. Happy to come to you this weekend.',
      'pending', '2026-07-10 08:05:00'),
  (1, 1, 'For my niece — we''re setting up on a tight budget. Thank you for donating!',
      'pending', '2026-07-10 19:40:00');

-- ---------------------------------------------------------------------------
-- Bookings — 1-3 Lawsan borrowing, 4-5 Lawsan lending, 6 a closed rental
-- ---------------------------------------------------------------------------

-- Dates are relative to CURDATE() so the demo always reads as "live": booking 1
-- is due tomorrow, 2 is on track, 3 is an open request, 6 closed last month.
INSERT INTO bookings
  (id, item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
   rental_charge, late_buffer, status, requested_at, accepted_at, closed_at) VALUES
  (1, 1, 4, 2, CURDATE() - INTERVAL 4 DAY,  CURDATE() + INTERVAL 1 DAY,  'daily', 15, 75, 15, 'in_progress',
      NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 5 DAY, NULL),
  (2, 2, 4, 3, CURDATE() - INTERVAL 2 DAY,  CURDATE() + INTERVAL 5 DAY,  'daily', 18, 126, 18, 'in_progress',
      NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 3 DAY, NULL),
  (3, 4, 4, 1, CURDATE() + INTERVAL 3 DAY,  CURDATE() + INTERVAL 5 DAY,  'daily', 25, 75, 25, 'requested',
      NOW() - INTERVAL 8 HOUR, NULL, NULL),
  (4, 9, 3, 4, CURDATE() - INTERVAL 3 DAY,  CURDATE() + INTERVAL 2 DAY,  'daily',  5, 25,  5, 'in_progress',
      NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY, NULL),
  (5, 11, 1, 4, CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 3 DAY,  'daily',  4, 20,  4, 'in_progress',
      NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY, NULL),
  (6, 3, 4, 1, CURDATE() - INTERVAL 40 DAY, CURDATE() - INTERVAL 35 DAY, 'daily', 12, 60, 12, 'completed',
      NOW() - INTERVAL 41 DAY, NOW() - INTERVAL 41 DAY, NOW() - INTERVAL 33 DAY);

INSERT INTO handover_records
  (booking_id, handover_at, lender_photos, borrower_photos, lender_notes,
   lender_accepted_at, borrower_accepted_at) VALUES
  (1, CURDATE() - INTERVAL 4 DAY + INTERVAL 8 HOUR,
      JSON_ARRAY('handover/1-l1.jpg','handover/1-l2.jpg','handover/1-l3.jpg','handover/1-l4.jpg'),
      JSON_ARRAY('handover/1-b1.jpg','handover/1-b2.jpg','handover/1-b3.jpg'),
      'Small scratch on the battery cover, noted by both parties.',
      NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY);

-- ---------------------------------------------------------------------------
-- Points: pools, wallets, gifts, an aid grant and an illustrative ledger
-- ---------------------------------------------------------------------------

UPDATE point_pools SET balance = 48200  WHERE pool_code = 'sponsor';
UPDATE point_pools SET balance = 12750  WHERE pool_code = 'aid';
UPDATE point_pools SET balance = 6400   WHERE pool_code = 'reserve';
UPDATE point_pools SET balance = 3180   WHERE pool_code = 'in_flight';
UPDATE point_pools SET balance = 121300 WHERE pool_code = 'member_wallets';
UPDATE point_pools SET balance = 1140   WHERE pool_code = 'retired';

INSERT INTO member_wallets (user_id, balance, bond_locked) VALUES
  (1, 2140, 500),
  (2, 1830, 0),
  (3, 960,  0),
  (4, 1250, 0),
  (5, 0,    0),
  (6, 0,    0);

INSERT INTO sponsors (id, company_name, contact_name, contact_phone, total_injected, active) VALUES
  (1, 'Lanka Hardware (Pvt) Ltd', 'R. Fernando', '+94 11 234 5001', 10000, 1),
  (2, 'Ceylon Fresh Mart',        'N. Silva',    '+94 11 234 5002',  5000, 1),
  (3, 'Sunrise Pharmacy',         'K. Jayawardena', '+94 11 234 5003', 2500, 1);

INSERT INTO sponsor_purchases
  (sponsor_id, cash_amount, overhead_amount, points_credited,
   sponsor_pool_pct, aid_pool_pct, receipt_number, recorded_by, recorded_at) VALUES
  (1, 11000, 1000, 10000, 70,  30, 'RCPT-2026-0041', 5, '2026-07-14 11:00:00'),
  (2,  5500,  500,  5000, 50,  50, 'RCPT-2026-0038', 5, '2026-06-30 11:00:00'),
  (3,  2750,  250,  2500,  0, 100, 'RCPT-2026-0031', 5, '2026-06-12 11:00:00');

INSERT INTO aid_grants
  (id, member_id, gn_division_id, requested_amount, purpose, moderator_id, moderator_vouch,
   vouched_at, status, created_at) VALUES
  (1042, 4, 1, 150, 'School supplies', 1,
      'Known family, genuine need for the new school term. No conflict of interest.',
      '2026-07-12 14:00:00', 'vouched', '2026-07-10 09:15:00');

INSERT INTO gifts (id, sender_id, recipient_id, amount, reason, sent_at) VALUES
  (1, 4, 3, 15, 'Thank you for the school run help!', NOW() - INTERVAL 1 DAY),
  (2, 4, 3, 10, 'Great neighbour — welcome gift',     NOW() - INTERVAL 15 DAY),
  (3, 4, 2, 20, 'For fixing the gate hinge',          NOW() - INTERVAL 37 DAY),
  (4, 4, 1,  5, 'Congrats on the new listing!',       NOW() - INTERVAL 56 DAY),
  (5, 3, 4, 10, 'Great neighbour — welcome gift',     NOW() - INTERVAL 15 DAY),
  (6, 2, 4, 12, 'Thanks for the jumper cables',       NOW() - INTERVAL 31 DAY);

INSERT INTO gift_usage_counters (user_id, day, day_total, year, year_total) VALUES
  (4, CURDATE() - INTERVAL 1 DAY, 15, YEAR(CURDATE()), 210);

INSERT INTO point_ledger
  (from_pool_code, from_user_id, to_pool_code, to_user_id, amount, reason, booking_id, gift_id, created_at) VALUES
  ('in_flight', NULL, NULL, 4,  25, 'rental_payout',  4,    NULL, NOW() - INTERVAL 1 DAY),
  (NULL, 4, 'in_flight', NULL,  75, 'rental_charge',  1,    NULL, NOW() - INTERVAL 4 DAY),
  (NULL, 4, NULL, 3,             15, 'gift',          NULL, 1,    NOW() - INTERVAL 1 DAY),
  (NULL, 3, NULL, 4,             10, 'gift',          NULL, 5,    NOW() - INTERVAL 15 DAY),
  (NULL, 4, 'in_flight', NULL,  10, 'late_fee',       6,    NULL, NOW() - INTERVAL 33 DAY);

-- ---------------------------------------------------------------------------
-- Ratings and notifications
-- ---------------------------------------------------------------------------

INSERT INTO ratings (booking_id, rater_id, ratee_id, context, stars, comment, created_at) VALUES
  (1, 2, 4, 'rental', 5, 'Returned the drill spotless and on time. Would lend again without hesitation.', NOW() - INTERVAL 1 DAY),
  (2, 3, 4, 'rental', 5, 'Careful with the tent, great communication about pickup.',                      NOW() - INTERVAL 15 DAY),
  (6, 1, 4, 'rental', 4, 'All good — slightly late confirming the return window.',                        NOW() - INTERVAL 37 DAY),
  (4, 1, 4, 'rental', 5, 'Textbook borrower. On time, item as handed over.',                              NOW() - INTERVAL 56 DAY),
  (1, 4, 2, 'rental', 5, 'Drill was in great shape, batteries fully charged. Smooth handover.',           NOW() - INTERVAL 1 DAY),
  (6, 1, 2, 'rental', 5, 'Lovely to deal with — flexible on pickup time.',                                NOW() - INTERVAL 27 DAY);

INSERT INTO notifications (user_id, type, payload, created_at, read_at) VALUES
  (4, 'booking_accepted',
      JSON_OBJECT('title','Madushan accepted your request for Bosch Cordless Drill',
                  'detail','Handover step is now open — upload your photos.',
                  'icon','handshake','href','/bookings/1?state=handover'),
      NOW() - INTERVAL 10 MINUTE, NULL),
  (4, 'return_due',
      JSON_OBJECT('title','Return due tomorrow: Bosch Cordless Drill',
                  'detail','Return by 17 Jul to keep your on-time streak.',
                  'icon','alert-triangle','href','/bookings/1'),
      NOW() - INTERVAL 2 HOUR, NULL),
  (4, 'gift_received',
      JSON_OBJECT('title','Kavipriya sent you a gift of 10 pts',
                  'detail','“Thanks for the jumper cables last week!”',
                  'icon','gift','href','/gifts?box=received'),
      NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
  (4, 'aid_grant_vouched',
      JSON_OBJECT('title','Your aid grant moved to liaison approval',
                  'detail','Moderator A. Akalvily vouched for request #A-1042.',
                  'icon','heart','href','/aid-grants/1042'),
      NOW() - INTERVAL 13 DAY, NOW() - INTERVAL 13 DAY),
  (4, 'listing_approved',
      JSON_OBJECT('title','Listing approved: Pressure Washer',
                  'detail','Your listing is now visible to Kollupitiya members.',
                  'icon','check-circle','href','/items/12/edit'),
      NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY);

INSERT INTO cron_runs (job_name, started_at, finished_at, status, notes) VALUES
  ('check_invariant', CURDATE() + INTERVAL 2 HOUR, CURDATE() + INTERVAL 2 HOUR + INTERVAL 14 SECOND, 'success',
   'total points in = total points out across all pools');
