-- ============================================================================
--  MITHRA — 016 Demo data for the walkthrough
--
--  Many screens read tables that no earlier seed filled, so they opened on an
--  empty state. This adds demo records so every area has something to show
--  and explain — disputes, damage cases, donation items, verifications,
--  address changes, aid requests, moderator candidates and objections,
--  disaster contributions, cron runs, notifications and wallet activity.
--
--  Screens read these rows the usual way; nothing is hard-coded in a view.
--  Queue dates are relative to NOW() so "days open" and timers stay sensible.
--
--  New accounts (password "password", like every seeded account):
--    S. Tharshini     tharshini@email.com   member, Kollupitiya
--    K. Nimal Perera  nimal@email.com       member, Kollupitiya
--    R. Dilani        dilani@email.com      member, Dehiwala
--    V. Kajan         kajan@email.com       member, Wellawatte (no moderator yet)
--    M. Farhan        farhan@email.com      applicant, Kollupitiya (pending)
--    P. Sanjeewani    sanjeewani@email.com  applicant, Kollupitiya (pending)
--    A. Rizwan        rizwan@email.com      applicant, Dehiwala (pending)
--
--  DEV/DEMO ONLY. Uploaded files are not committed (storage/uploads is
--  ignored), so proof and evidence rows point at no file.
--
--  Run after 015_remove_issued_reset_codes.sql.
-- ============================================================================

USE mithra;

SET NAMES utf8mb4;

SET @pw     = '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a';
SET @member = (SELECT id FROM roles WHERE code = 'member');

SET @kavipriya = (SELECT id FROM users WHERE nic = '199012345671');
SET @abishan   = (SELECT id FROM users WHERE nic = '199112345672');
SET @arun      = (SELECT id FROM users WHERE nic = '199212345673');
SET @lawsan    = (SELECT id FROM users WHERE nic = '200312345674');
SET @liaison   = (SELECT id FROM users WHERE nic = '198812345675');
SET @admin     = (SELECT id FROM users WHERE nic = '198512345676');
SET @sponsorU  = (SELECT id FROM users WHERE nic = '199812345677');
SET @fernando  = (SELECT id FROM users WHERE nic = '198712345678');

-- ---------------------------------------------------------------------------
-- People
-- ---------------------------------------------------------------------------

INSERT INTO users (role_id, full_name, nic, phone, phone_digits, email, address, password_hash,
                   trust_score, gift_receive_enabled, status, joined_at, created_at) VALUES
  (@member, 'S. Tharshini', '199512345681', '+94 77 222 2001', '772222001', 'tharshini@email.com',
      '32 Alfred Place, Colombo 03', @pw, 88, 1, 'active', '2025-05-12 09:00:00', '2025-05-10 10:00:00');
SET @tharshini = LAST_INSERT_ID();

INSERT INTO users (role_id, full_name, nic, phone, phone_digits, email, address, password_hash,
                   trust_score, gift_receive_enabled, status, joined_at, created_at) VALUES
  (@member, 'K. Nimal Perera', '198912345682', '+94 77 222 2002', '772222002', 'nimal@email.com',
      '9 Walukarama Road, Colombo 03', @pw, 74, 1, 'active', '2025-06-02 09:00:00', '2025-06-01 10:00:00');
SET @nimal = LAST_INSERT_ID();

INSERT INTO users (role_id, full_name, nic, phone, phone_digits, email, address, password_hash,
                   trust_score, gift_receive_enabled, status, joined_at, created_at) VALUES
  (@member, 'R. Dilani', '199412345683', '+94 77 222 2003', '772222003', 'dilani@email.com',
      '41 Station Road, Dehiwala', @pw, 81, 1, 'active', '2025-07-15 09:00:00', '2025-07-14 10:00:00');
SET @dilani = LAST_INSERT_ID();

INSERT INTO users (role_id, full_name, nic, phone, phone_digits, email, address, password_hash,
                   trust_score, gift_receive_enabled, status, joined_at, created_at) VALUES
  (@member, 'V. Kajan', '199312345684', '+94 77 222 2004', '772222004', 'kajan@email.com',
      '18 Vaverset Place, Wellawatte', @pw, 90, 1, 'active', '2025-03-20 09:00:00', '2025-03-19 10:00:00');
SET @kajan = LAST_INSERT_ID();

INSERT INTO users (role_id, full_name, nic, phone, phone_digits, email, address, password_hash,
                   trust_score, gift_receive_enabled, status, joined_at, created_at) VALUES
  (@member, 'M. Farhan', '200012345685', '+94 77 222 2005', '772222005', 'farhan@email.com',
      '5 Clifford Avenue, Colombo 03', @pw, 50, 1, 'pending', NULL, NOW() - INTERVAL 3 DAY);
SET @farhan = LAST_INSERT_ID();

INSERT INTO users (role_id, full_name, nic, phone, phone_digits, email, address, password_hash,
                   trust_score, gift_receive_enabled, status, joined_at, created_at) VALUES
  (@member, 'P. Sanjeewani', '199712345686', '+94 77 222 2006', '772222006', 'sanjeewani@email.com',
      '27 Charles Drive, Colombo 03', @pw, 50, 1, 'pending', NULL, NOW() - INTERVAL 1 DAY);
SET @sanjeewani = LAST_INSERT_ID();

INSERT INTO users (role_id, full_name, nic, phone, phone_digits, email, address, password_hash,
                   trust_score, gift_receive_enabled, status, joined_at, created_at) VALUES
  (@member, 'A. Rizwan', '199612345687', '+94 77 222 2007', '772222007', 'rizwan@email.com',
      '63 Hill Street, Dehiwala', @pw, 50, 1, 'pending', NULL, NOW() - INTERVAL 2 DAY);
SET @rizwan = LAST_INSERT_ID();

INSERT INTO user_divisions (user_id, gn_division_id, membership_type, verified_by, verified_at, status, created_at) VALUES
  (@tharshini,  1, 'home', @kavipriya, '2025-05-12 09:00:00', 'active',  '2025-05-10 10:00:00'),
  (@nimal,      1, 'home', @kavipriya, '2025-06-02 09:00:00', 'active',  '2025-06-01 10:00:00'),
  (@dilani,     4, 'home', @fernando,  '2025-07-15 09:00:00', 'active',  '2025-07-14 10:00:00'),
  (@kajan,      3, 'home', @admin,     '2025-03-20 09:00:00', 'active',  '2025-03-19 10:00:00'),
  (@farhan,     1, 'home', NULL,       NULL,                  'pending', NOW() - INTERVAL 3 DAY),
  (@sanjeewani, 1, 'home', NULL,       NULL,                  'pending', NOW() - INTERVAL 1 DAY),
  (@rizwan,     4, 'home', NULL,       NULL,                  'pending', NOW() - INTERVAL 2 DAY);

INSERT INTO member_wallets (user_id, balance, bond_locked) VALUES
  (@tharshini, 1120, 0), (@nimal, 640, 0), (@dilani, 890, 0), (@kajan, 1410, 0);

-- ---------------------------------------------------------------------------
-- Listings: donation items and a few more rentals
-- ---------------------------------------------------------------------------

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (@arun, 1, 7, 'School Uniform Set (Grade 5)', 'Two shirts, two shorts and a tie. Washed and ironed, fits ages 9-10.',
      'donation', 2500, 'validated', NULL, NULL, 'active', @kavipriya, NOW() - INTERVAL 9 DAY, NOW() - INTERVAL 10 DAY);
SET @uniform = LAST_INSERT_ID();

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (@abishan, 1, 8, 'Wooden Study Desk', 'Solid wood desk with one drawer. Small scratch on the top, otherwise sturdy.',
      'donation', 9000, 'validated', NULL, NULL, 'active', @kavipriya, NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 7 DAY);
SET @desk = LAST_INSERT_ID();

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (@tharshini, 1, 4, 'Box of Children''s Story Books', 'About 40 English and Tamil story books for ages 5-10.',
      'donation', 4000, 'validated', NULL, NULL, 'active', @kavipriya, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 5 DAY);
SET @books = LAST_INSERT_ID();

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (@nimal, 1, 7, 'Kids'' Bicycle (16 inch)', 'Blue bicycle with training wheels. New tyres fitted last year.',
      'donation', 7500, 'validated', NULL, NULL, 'donated', @kavipriya, '2026-08-20 10:00:00', '2026-08-19 10:00:00');
SET @bicycle = LAST_INSERT_ID();

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, created_at) VALUES
  (@nimal, 1, 8, 'Plastic Chairs ×6', 'Six white stacking chairs, good for a small function.',
      'donation', 6000, 'pending', NULL, NULL, 'pending_approval', NOW() - INTERVAL 1 DAY);

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (@dilani, 4, 5, 'Rice Cooker (1 L)', 'Barely used, too small for our family now.',
      'donation', 3500, 'validated', NULL, NULL, 'active', @fernando, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 4 DAY);
SET @dehiwalaCooker = LAST_INSERT_ID();

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (@tharshini, 1, 3, 'Portable Bluetooth Speaker', 'JBL speaker, about 8 hours of battery. Charger included.',
      'rental', 18000, 'validated', 12, 250, 'active', @kavipriya, '2026-07-02 10:00:00', '2026-07-01 10:00:00');
SET @speaker = LAST_INSERT_ID();

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, created_at) VALUES
  (@tharshini, 1, 2, 'Wet & Dry Vacuum Cleaner', 'Handy after the floods. Comes with two nozzles.',
      'rental', 22000, 'pending', 15, 300, 'pending_approval', NOW() - INTERVAL 2 DAY);

INSERT INTO items (owner_id, gn_division_id, category_id, title, description, listing_type,
                   declared_value, value_status, daily_rate, monthly_rate, status, approved_by, approved_at, created_at) VALUES
  (@dilani, 4, 1, 'Hedge Trimmer', 'Electric trimmer with a 10 m extension cable.',
      'rental', 14000, 'validated', 10, NULL, 'active', @fernando, '2026-08-05 10:00:00', '2026-08-04 10:00:00');

-- Value checks behind some of the approvals above.
INSERT INTO item_value_reviews (item_id, reviewer_id, decision, previous_value, new_value, reason, created_at) VALUES
  (@uniform, @kavipriya, 'approved', 2500, 2500, NULL, NOW() - INTERVAL 9 DAY),
  (@desk,    @kavipriya, 'adjusted', 12000, 9000, 'Similar desks sell for about Rs 9,000 second hand.', NOW() - INTERVAL 6 DAY),
  (@speaker, @kavipriya, 'approved', 18000, 18000, NULL, '2026-07-02 10:00:00');

-- ---------------------------------------------------------------------------
-- Donations and requests for them
-- ---------------------------------------------------------------------------

INSERT INTO donations (item_id, donor_id, recipient_id, selection_mode, status, handover_at, created_at) VALUES
  (@uniform, @arun, NULL, 'donor_chooses', 'open', NULL, NOW() - INTERVAL 9 DAY);
SET @donUniform = LAST_INSERT_ID();

INSERT INTO donations (item_id, donor_id, recipient_id, selection_mode, status, handover_at, created_at) VALUES
  (@desk, @abishan, @tharshini, 'donor_chooses', 'recipient_selected', NULL, NOW() - INTERVAL 6 DAY);
SET @donDesk = LAST_INSERT_ID();

INSERT INTO donations (item_id, donor_id, recipient_id, selection_mode, status, handover_at, created_at) VALUES
  (@books, @tharshini, NULL, 'first_come', 'open', NULL, NOW() - INTERVAL 4 DAY);
SET @donBooks = LAST_INSERT_ID();

INSERT INTO donations (item_id, donor_id, recipient_id, selection_mode, status, handover_at, created_at) VALUES
  (@bicycle, @nimal, @lawsan, 'donor_chooses', 'completed', '2026-08-24 16:00:00', '2026-08-20 10:00:00');
SET @donBicycle = LAST_INSERT_ID();

INSERT INTO donations (item_id, donor_id, recipient_id, selection_mode, status, handover_at, created_at) VALUES
  (@dehiwalaCooker, @dilani, NULL, 'donor_chooses', 'open', NULL, NOW() - INTERVAL 3 DAY);

INSERT INTO donation_requests (donation_id, requester_id, message, status, requested_at) VALUES
  (@donUniform, @lawsan,    'My nephew starts Grade 5 in January — this would really help.', 'pending', NOW() - INTERVAL 8 DAY),
  (@donUniform, @nimal,     'For my son, he is in Grade 4 now.', 'pending', NOW() - INTERVAL 7 DAY),
  (@donUniform, @tharshini, 'Could pass it to a family on our lane who lost clothes in the flood.', 'pending', NOW() - INTERVAL 5 DAY),
  (@donDesk,    @tharshini, 'My daughter studies at the kitchen table. Can collect this weekend.', 'selected', NOW() - INTERVAL 5 DAY),
  (@donDesk,    @arun,      'Would use it for evening tuition classes.', 'declined', NOW() - INTERVAL 5 DAY),
  (@donBooks,   @abishan,   'For the reading corner at the temple Sunday school.', 'pending', NOW() - INTERVAL 2 DAY),
  (@donBicycle, @lawsan,    'For my younger cousin''s birthday.', 'selected', '2026-08-21 09:00:00');

INSERT INTO ratings (donation_id, rater_id, ratee_id, context, stars, comment, created_at) VALUES
  (@donBicycle, @lawsan, @nimal, 'donation', 5, 'Bicycle was spotless and Nimal even pumped the tyres. Thank you!', '2026-08-25 09:00:00');

-- ---------------------------------------------------------------------------
-- Bookings behind the damage cases and disputes
-- ---------------------------------------------------------------------------

-- 1. Extension Ladder, rung bent — with the moderator, lender signed, borrower pending.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at) VALUES
  (5, @abishan, @arun, CURDATE() - INTERVAL 12 DAY, CURDATE() - INTERVAL 8 DAY, 'daily', 8, 40, 8, 1,
      'pending_moderator', NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 13 DAY);
SET @bkLadder = LAST_INSERT_ID();

-- 2. Camping Tent, torn and a pole missing — the borrower refused the ruling; escalated 10 days ago.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at) VALUES
  (2, @tharshini, @arun, CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 25 DAY, 'daily', 18, 108, 18, 1,
      'escalated', NOW() - INTERVAL 32 DAY, NOW() - INTERVAL 31 DAY);
SET @bkTent = LAST_INSERT_ID();

-- 3. Projector, dead pixels found on return — escalated 3 days ago.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at) VALUES
  (4, @nimal, @kavipriya, CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 15 DAY, 'daily', 25, 100, 25, 1,
      'escalated', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 19 DAY);
SET @bkProjector = LAST_INSERT_ID();

-- 4. Sewing Machine, needle plate cracked — resolved by the moderator, both signed.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at, closed_at) VALUES
  (7, @nimal, @abishan, '2026-08-01', '2026-08-06', 'daily', 9, 54, 9, 1,
      'completed', '2026-07-30 10:00:00', '2026-07-30 18:00:00', '2026-08-14 12:00:00');
SET @bkSewing = LAST_INSERT_ID();

-- 5. Bluetooth Speaker, returned without its charger — simple track, borrower accepted.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at, closed_at) VALUES
  (@speaker, @abishan, @tharshini, '2026-08-22', '2026-08-24', 'daily', 12, 36, 12, 0,
      'completed', '2026-08-20 10:00:00', '2026-08-20 15:00:00', '2026-08-26 10:00:00');
SET @bkSpeaker = LAST_INSERT_ID();

-- 6. Folding Tables, a clean rental — ruled on by the Admin in an older dispute over late fees.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at, closed_at) VALUES
  (8, @arun, @kavipriya, '2026-07-18', '2026-07-19', 'daily', 6, 12, 6, 0,
      'completed', '2026-07-15 10:00:00', '2026-07-15 12:00:00', '2026-08-02 10:00:00');
SET @bkTables = LAST_INSERT_ID();

-- 7. Abishan borrowing the Baby Stroller right now, so his dashboard has an active borrowing.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at, accepted_at) VALUES
  (6, @abishan, @arun, CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 3 DAY, 'daily', 10, 60, 10, 0,
      'in_progress', NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 3 DAY);
SET @bkStroller = LAST_INSERT_ID();

-- 8. A request waiting on Abishan as lender.
INSERT INTO bookings (item_id, borrower_id, lender_id, start_date, end_date, rate_basis, agreed_rate,
                      rental_charge, late_buffer, moderator_involved, status, requested_at) VALUES
  (1, @tharshini, @abishan, CURDATE() + INTERVAL 4 DAY, CURDATE() + INTERVAL 6 DAY, 'daily', 15, 45, 15, 0,
      'requested', NOW() - INTERVAL 5 HOUR);

INSERT INTO return_records (booking_id, return_at, lender_decision, borrower_decision) VALUES
  (@bkLadder,    NOW() - INTERVAL 8 DAY,  'claim_raised', 'contested'),
  (@bkTent,      NOW() - INTERVAL 25 DAY, 'claim_raised', 'contested'),
  (@bkProjector, NOW() - INTERVAL 15 DAY, 'claim_raised', 'contested'),
  (@bkSewing,    '2026-08-06 17:00:00',  'claim_raised', 'contested'),
  (@bkSpeaker,   '2026-08-24 18:00:00',  'claim_raised', 'accepted'),
  (@bkTables,    '2026-07-21 10:00:00',  'accepted',     'accepted');

INSERT INTO ratings (booking_id, rater_id, ratee_id, context, stars, comment, created_at) VALUES
  (@bkTables,  @arun,      @kavipriya, 'rental', 5, 'Tables were clean and ready on time.', '2026-07-21 12:00:00'),
  (@bkTables,  @kavipriya, @arun,      'rental', 4, 'Returned two days late but in perfect condition.', '2026-07-21 12:30:00'),
  (@bkSpeaker, @tharshini, @abishan,   'rental', 4, 'Forgot the charger, but replaced it the same week.', '2026-08-26 11:00:00'),
  (@bkSpeaker, @abishan,   @tharshini, 'rental', 5, 'Great speaker, easy pickup.', '2026-08-26 11:30:00');

-- ---------------------------------------------------------------------------
-- Damage cases (moderator) and disputes (Admin)
-- ---------------------------------------------------------------------------

INSERT INTO damage_claims (booking_id, raised_by, severity, description, proposed_penalty, track,
                           borrower_response, responded_at, status, created_at) VALUES
  (@bkLadder, @arun, 'moderate',
      'Third rung is bent and the lock clip no longer catches. Photos taken at return.',
      120, 'moderator', 'contested', NOW() - INTERVAL 7 DAY, 'pending_moderator', NOW() - INTERVAL 8 DAY);
SET @dcLadder = LAST_INSERT_ID();

INSERT INTO damage_claims (booking_id, raised_by, severity, description, proposed_penalty, track,
                           borrower_response, responded_at, status, created_at) VALUES
  (@bkTent, @arun, 'major',
      'Flysheet torn along one side and a pole is missing. The tent cannot be pitched.',
      450, 'moderator', 'contested', NOW() - INTERVAL 24 DAY, 'escalated', NOW() - INTERVAL 25 DAY);
SET @dcTent = LAST_INSERT_ID();

INSERT INTO damage_claims (booking_id, raised_by, severity, description, proposed_penalty, track,
                           borrower_response, responded_at, status, created_at) VALUES
  (@bkProjector, @kavipriya, 'major',
      'A row of dead pixels across the image that was not there at handover.',
      600, 'moderator', 'contested', NOW() - INTERVAL 14 DAY, 'escalated', NOW() - INTERVAL 15 DAY);
SET @dcProjector = LAST_INSERT_ID();

INSERT INTO damage_claims (booking_id, raised_by, severity, description, proposed_penalty, track,
                           borrower_response, responded_at, status, created_at) VALUES
  (@bkSewing, @abishan, 'minor',
      'Needle plate cracked; the machine still sews but snags thin fabric.',
      60, 'moderator', 'contested', '2026-08-07 10:00:00', 'resolved', '2026-08-06 18:00:00');
SET @dcSewing = LAST_INSERT_ID();

INSERT INTO damage_claims (booking_id, raised_by, severity, description, proposed_penalty, track,
                           borrower_response, responded_at, status, created_at) VALUES
  (@bkSpeaker, @tharshini, 'minor', 'Returned without the charging cable.',
      20, 'simple', 'accepted', '2026-08-25 09:00:00', 'closed', '2026-08-24 19:00:00');

INSERT INTO moderator_resolutions (damage_claim_id, moderator_id, outcome_category, penalty_points,
                                   points_movement, notes, met_at, lender_signoff_at, borrower_signoff_at, closed_at) VALUES
  (@dcLadder, @kavipriya, 'shared_fault', 80, '{"borrower_to_lender": 80}',
      'Met both at the community hall. The clip was already loose; the bent rung is new. Proposed 80 pts.',
      NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 4 DAY, NULL, NULL),
  (@dcTent, @kavipriya, 'borrower_fault', 450, '{"borrower_to_lender": 450}',
      'Borrower says the pole was missing at handover; the handover photos show all poles. Borrower refused to sign.',
      NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 19 DAY, NULL, NULL),
  (@dcProjector, @fernando, 'borrower_fault', 600, '{"borrower_to_lender": 600}',
      'Handled by the Dehiwala moderator because the lender moderates Kollupitiya. Borrower refused to sign.',
      NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 10 DAY, NULL, NULL),
  (@dcSewing, @kavipriya, 'minor_wear', 40, '{"borrower_to_lender": 40}',
      'Agreed a 40 pt contribution towards a new needle plate.',
      '2026-08-10 17:00:00', '2026-08-12 09:00:00', '2026-08-14 12:00:00', '2026-08-14 12:00:00');

INSERT INTO disputes (booking_id, damage_claim_id, raised_by, admin_id, reason, status, resolution, ruling_at, created_at) VALUES
  (@bkTent, @dcTent, @tharshini, NULL,
      'Borrower says the pole was missing when she collected the tent and refuses the 450 pt penalty.',
      'open', NULL, NULL, NOW() - INTERVAL 10 DAY),
  (@bkProjector, @dcProjector, @nimal, NULL,
      'Borrower says the dead pixels were there before and asks for the handover photos to be checked.',
      'open', NULL, NULL, NOW() - INTERVAL 3 DAY),
  (@bkTables, NULL, @arun, @admin,
      'Borrower disputed the late fee, saying the lender was away on the return date.',
      'ruled', 'Late fee waived: messages show the lender asked for the later return.', '2026-08-02 10:00:00', '2026-07-24 10:00:00');

-- ---------------------------------------------------------------------------
-- Address changes waiting for the moderators
-- ---------------------------------------------------------------------------

INSERT INTO address_changes (user_id, gn_division_id, new_address, proof_file_path, status, decided_by, decided_at, reason, created_at) VALUES
  (@arun,   1, '14/1 Dharmapala Mawatha, Colombo 03', 'identity-documents/00000000000000000000000000000016.jpg', 'pending', NULL, NULL, NULL, NOW() - INTERVAL 2 DAY),
  (@nimal,  1, '22 Kinsey Road, Colombo 03',          'identity-documents/00000000000000000000000000000017.jpg', 'pending', NULL, NULL, NULL, NOW() - INTERVAL 6 HOUR),
  (@dilani, 4, '8 Galle Road, Dehiwala',              'identity-documents/00000000000000000000000000000018.jpg', 'pending', NULL, NULL, NULL, NOW() - INTERVAL 1 DAY),
  (@tharshini, 1, '32 Alfred Place, Colombo 03',      'identity-documents/00000000000000000000000000000019.jpg', 'rejected', @kavipriya,
      '2026-06-01 10:00:00', 'The utility bill is in another person''s name.', '2026-05-30 10:00:00');

-- ---------------------------------------------------------------------------
-- Aid requests at each stage
-- ---------------------------------------------------------------------------

INSERT INTO aid_grants (member_id, gn_division_id, requested_amount, purpose, moderator_id, moderator_vouch,
                        vouched_at, liaison_id, approved_amount, status, expires_at, created_at) VALUES
  (@arun, 1, 300, 'Replacing school books lost in the flood', NULL, NULL,
      NULL, NULL, NULL, 'requested', NULL, NOW() - INTERVAL 2 DAY),
  (@nimal, 1, 500, 'Gas cylinder and dry rations after losing a month''s wages', NULL, NULL,
      NULL, NULL, NULL, 'info_requested', NULL, NOW() - INTERVAL 4 DAY),
  (@tharshini, 1, 400, 'Clinic fees for her mother''s check-ups', @kavipriya,
      'Known to me for two years; the clinic card confirms the visits.',
      NOW() - INTERVAL 12 DAY, @liaison, 400, 'disbursed', NOW() + INTERVAL 60 DAY, NOW() - INTERVAL 14 DAY),
  (@abishan, 1, 250, 'Bus fares to a new job for the first month', @kavipriya,
      'Offer letter seen. Good lending record.',
      '2026-07-10 10:00:00', @liaison, 250, 'closed', '2026-09-10 10:00:00', '2026-07-08 10:00:00'),
  (@nimal, 1, 800, 'New television', @kavipriya,
      'Not an essential need under the aid rules.',
      '2026-06-15 10:00:00', NULL, NULL, 'rejected_moderator', NULL, '2026-06-14 10:00:00'),
  (@dilani, 4, 350, 'Repairing a roof damaged in the storm', NULL, NULL,
      NULL, NULL, NULL, 'requested', NULL, NOW() - INTERVAL 1 DAY);

-- ---------------------------------------------------------------------------
-- Moderator appointments: candidates, objections, conduct, expenses
-- ---------------------------------------------------------------------------

INSERT INTO moderator_candidates (gn_division_id, user_id, candidate_name, nominated_by, endorsement,
                                  nic_verified, residence_verified, reference_1, reference_2, declaration_signed,
                                  interview_score, involvement_score, references_score, competency_score, total_score, status) VALUES
  (3, @kajan, 'V. Kajan', @admin, 'Endorsed by the Wellawatte Grama Niladhari, 12 Sep 2026.',
      1, 1, 'Temple society secretary', 'Wellawatte Tamil Vidyalayam principal', 1,
      8, 9, 8, 7, 80.00, 'evaluated'),
  (1, @tharshini, 'S. Tharshini', @kavipriya, 'Endorsed by the Kollupitiya Grama Niladhari as deputy.',
      1, 1, 'Women''s society chairperson', NULL, 0,
      NULL, NULL, NULL, NULL, NULL, 'checks_passed'),
  (5, NULL, 'H. Wickramasinghe', @admin, NULL,
      1, 0, NULL, NULL, 0,
      NULL, NULL, NULL, NULL, NULL, 'nominated');

SET @fernandoAssignment = (SELECT id FROM moderator_assignments WHERE user_id = @fernando AND gn_division_id = 4 LIMIT 1);

INSERT INTO moderator_objections (moderator_assignment_id, member_id, reason, status, created_at) VALUES
  (@fernandoAssignment, @dilani,
      'He is my landlord''s brother, so I worry about fairness in any case that involves me.',
      'dismissed', '2025-04-14 10:00:00'),
  (@fernandoAssignment, @rizwan,
      'He lives on the Mount Lavinia side of the boundary for part of the year.',
      'pending', NOW() - INTERVAL 2 DAY);

INSERT INTO moderator_conduct_history (moderator_id, incident_type, notes, recorded_by, recorded_at) VALUES
  (@kavipriya, 'commendation', 'Ran relief distribution for 60 households during the June floods.', @admin, '2026-06-12 10:00:00'),
  (@kavipriya, 'late_review',  'Three listings waited more than 72 hours for review in July.', @admin, '2026-07-31 10:00:00'),
  (@fernando,  'recusal',      'Stepped in on a Kollupitiya case where the local moderator was a party.', @admin, NOW() - INTERVAL 10 DAY);

INSERT INTO moderator_expense_claims (moderator_id, purpose, amount, receipt_reference, checked_by, approved_amount, paid_at, created_at) VALUES
  (@kavipriya, 'Three-wheeler hire to deliver relief packs', 1800, 'TW-2026-0611', @liaison, 1800, '2026-06-20 10:00:00', '2026-06-13 10:00:00'),
  (@kavipriya, 'Printing notices for the verification day', 650, 'PR-2026-0904', NULL, NULL, NULL, NOW() - INTERVAL 5 DAY),
  (@fernando,  'Bus fares to the Kollupitiya case meeting', 240, 'BUS-2026-0916', NULL, NULL, NULL, NOW() - INTERVAL 9 DAY);

-- ---------------------------------------------------------------------------
-- Disaster contributions for the active Kollupitiya flood (event 2)
-- ---------------------------------------------------------------------------

INSERT INTO disaster_contributions (disaster_event_id, sponsor_id, contribution_kind, description, estimated_value,
                                    receipt_reference, verified_by, verified_at, recorded_at, handed_over_on, notes, status,
                                    received_description, received_value, received_on, ack_reference, confirmed_at, verified_value) VALUES
  (2, 1, 'goods', '40 buckets, mops and bleach for flood clean-up', 60000,
      'LH-DN-2026-118', @liaison, NULL, NOW() - INTERVAL 4 DAY, CURDATE() - INTERVAL 4 DAY,
      'Delivered by the Lanka Hardware van to the community hall.', 'awaiting',
      NULL, NULL, NULL, NULL, NULL, NULL),
  (2, 2, 'goods', '120 dry-ration packs (rice, dhal, sugar, tea)', 240000,
      'CFM-2026-5521', @liaison, NULL, NOW() - INTERVAL 3 DAY, CURDATE() - INTERVAL 3 DAY,
      NULL, 'ready',
      '118 dry-ration packs; two were water-damaged in transport', 236000, CURDATE() - INTERVAL 3 DAY, 'KOL-ACK-0921', NOW() - INTERVAL 2 DAY, NULL),
  (2, 3, 'goods', 'First-aid kits and oral rehydration salts for 50 families', 75000,
      'SP-INV-7730', @liaison, NULL, NOW() - INTERVAL 2 DAY, CURDATE() - INTERVAL 2 DAY,
      'Invoice amount does not match the delivery note.', 'queried',
      NULL, NULL, NULL, NULL, NULL, NULL),
  (2, 1, 'cash', 'Cash donation to the relief fund', 50000,
      'LH-CHQ-00931', @liaison, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 5 DAY, CURDATE() - INTERVAL 5 DAY,
      NULL, 'verified',
      'Cheque for Rs 50,000 received and banked', 50000, CURDATE() - INTERVAL 5 DAY, 'KOL-ACK-0918', NOW() - INTERVAL 4 DAY, 50000),
  (1, 2, 'goods', 'Bottled water for the June flood', 30000,
      'CFM-2026-4410', @liaison, NULL, '2026-06-05 10:00:00', '2026-06-04',
      'No delivery reached the division; the sponsor has been told.', 'rejected',
      NULL, NULL, NULL, NULL, NULL, NULL);

-- ---------------------------------------------------------------------------
-- Scheduled jobs
-- ---------------------------------------------------------------------------

INSERT INTO cron_runs (job_name, started_at, finished_at, status, notes) VALUES
  ('check_invariant',       NOW() - INTERVAL 1 DAY - INTERVAL 22 HOUR, NOW() - INTERVAL 1 DAY - INTERVAL 22 HOUR + INTERVAL 11 SECOND, 'success', 'total points in = total points out across all pools'),
  ('expire_requests',       NOW() - INTERVAL 1 DAY - INTERVAL 21 HOUR, NOW() - INTERVAL 1 DAY - INTERVAL 21 HOUR + INTERVAL 3 SECOND,  'success', '2 unanswered booking requests auto-cancelled'),
  ('send_return_reminders', NOW() - INTERVAL 1 DAY - INTERVAL 18 HOUR, NOW() - INTERVAL 1 DAY - INTERVAL 18 HOUR + INTERVAL 5 SECOND,  'success', '3 return-due reminders sent'),
  ('check_invariant',       NOW() - INTERVAL 22 HOUR, NOW() - INTERVAL 22 HOUR + INTERVAL 12 SECOND, 'success', 'total points in = total points out across all pools'),
  ('expire_requests',       NOW() - INTERVAL 21 HOUR, NOW() - INTERVAL 21 HOUR + INTERVAL 2 SECOND,  'success', 'No requests past 48 hours'),
  ('dispute_timers',        NOW() - INTERVAL 20 HOUR, NOW() - INTERVAL 20 HOUR + INTERVAL 4 SECOND,  'success', '1 dispute passed the 7-day sign-off timer'),
  ('send_return_reminders', NOW() - INTERVAL 18 HOUR, NOW() - INTERVAL 18 HOUR + INTERVAL 30 SECOND, 'failed',  'Mail server did not answer; 3 reminders will retry on the next run'),
  ('expire_aid_grants',     NOW() - INTERVAL 17 HOUR, NOW() - INTERVAL 17 HOUR + INTERVAL 2 SECOND,  'success', '1 disbursed grant closed after its return window');

-- ---------------------------------------------------------------------------
-- Points movements and notifications for the other demo accounts
-- ---------------------------------------------------------------------------

INSERT INTO point_ledger (from_pool_code, from_user_id, to_pool_code, to_user_id, amount, reason, booking_id, created_at) VALUES
  ('sponsor', NULL, NULL, @abishan, 100, 'welcome_bonus',  NULL,          '2025-01-14 09:05:00'),
  ('in_flight', NULL, NULL, @abishan, 54, 'rental_payout', @bkSewing,     '2026-08-14 12:00:00'),
  (NULL, @nimal, NULL, @abishan, 40, 'damage_penalty',     @bkSewing,     '2026-08-14 12:00:00'),
  (NULL, @abishan, 'in_flight', NULL, 36, 'rental_charge', @bkSpeaker,    '2026-08-20 15:00:00'),
  (NULL, @abishan, NULL, @tharshini, 20, 'damage_penalty', @bkSpeaker,    '2026-08-25 09:00:00'),
  (NULL, @abishan, 'in_flight', NULL, 60, 'rental_charge', @bkStroller,   NOW() - INTERVAL 3 DAY),
  ('sponsor', NULL, NULL, @kavipriya, 200, 'moderator_stipend', NULL,     '2026-09-01 02:00:00'),
  ('sponsor', NULL, NULL, @kavipriya, 200, 'moderator_stipend', NULL,     '2026-08-01 02:00:00'),
  ('sponsor', NULL, NULL, @tharshini, 100, 'welcome_bonus', NULL,         '2025-05-12 09:05:00'),
  ('sponsor', NULL, NULL, @nimal,     100, 'welcome_bonus', NULL,         '2025-06-02 09:05:00');

INSERT INTO point_ledger (from_pool_code, from_user_id, to_pool_code, to_user_id, amount, reason, aid_grant_id, created_at)
  SELECT 'aid', NULL, NULL, member_id, approved_amount, 'aid_grant', id, vouched_at + INTERVAL 2 DAY
    FROM aid_grants
   WHERE (member_id = @abishan AND status = 'closed') OR (member_id = @tharshini AND status = 'disbursed');

INSERT INTO notifications (user_id, type, payload, created_at, read_at) VALUES
  (@abishan, 'booking_accepted', JSON_OBJECT('title', 'Your booking was accepted', 'icon', 'handshake', 'booking_id', @bkStroller), NOW() - INTERVAL 3 DAY, NULL),
  (@abishan, 'booking_requested', JSON_OBJECT('title', 'S. Tharshini wants to borrow your Bosch Cordless Drill', 'detail', 'Reply within 48 hours.', 'icon', 'clock', 'href', '/bookings'), NOW() - INTERVAL 5 HOUR, NULL),
  (@abishan, 'account_notice', JSON_OBJECT('title', 'Moderator proposed a resolution: Extension Ladder', 'detail', '80 pts to the lender · N. Arun has signed, your sign-off is pending.', 'icon', 'alert-triangle'), NOW() - INTERVAL 1 DAY, NULL),
  (@abishan, 'account_notice', JSON_OBJECT('title', 'S. Tharshini was chosen for your Wooden Study Desk', 'detail', 'Arrange the handover with her.', 'icon', 'heart'), NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 3 DAY),
  (@sponsorU, 'account_notice', JSON_OBJECT('title', 'Disaster Mode is active in Kollupitiya', 'detail', 'Monsoon flooding behind Kollupitiya Junction. The Sponsor Liaison is recording contributions.', 'icon', 'alert-triangle', 'href', '/sponsor/disasters/2'), NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 4 DAY),
  (@sponsorU, 'account_notice', JSON_OBJECT('title', 'Your cash donation was verified', 'detail', 'Rs 50,000 to the Kollupitiya relief fund · acknowledgement KOL-ACK-0918.', 'icon', 'check-circle'), NOW() - INTERVAL 1 DAY, NULL),
  (@sponsorU, 'account_notice', JSON_OBJECT('title', 'Clean-up supplies awaiting the moderator', 'detail', '40 buckets, mops and bleach · the Kollupitiya moderator will confirm receipt.', 'icon', 'info'), NOW() - INTERVAL 4 DAY, NULL),
  (@sponsorU, 'account_notice', JSON_OBJECT('title', 'Quarterly CSR report ready', 'detail', 'July–September 2026 impact summary is available.', 'icon', 'award', 'href', '/sponsor/csr-reports'), NOW() - INTERVAL 2 DAY, NULL);
