-- ============================================================================
--  MITHRA — 009 Demo accounts named after the team member who built each role
--
--  Each seeded login now belongs to the teammate who built that actor's
--  screens, so every presenter signs in as themselves:
--
--    id 1  Moderator (Kollupitiya)  J. Kavipriya     kavipriya@email.com
--    id 2  Member                   N. Abishan       abishan@email.com
--    id 3  Member                   N. Arun          arun@email.com
--    id 4  Member                   M. Lawsan        lawsan@email.com      (unchanged)
--    id 5  Sponsor Liaison          A. Akalvily      akalvily@email.com
--    id 6  Admin                    T.H.K. Madushan  madushan@email.com
--    id 7  Sponsor (new)            P. Thineka       thineka@email.com
--
--  Ids and roles stay as they were, so items, bookings, ratings, the
--  moderator appointment and the ledger keep pointing at the same accounts;
--  only the person behind each account changes. The password for every
--  account is still "password".
--
--  Run after 008_seed_disaster_demo_data.sql.
-- ============================================================================

USE mithra;

UPDATE users SET full_name = 'N. Abishan',      email = 'abishan@email.com'   WHERE id = 2;
UPDATE users SET full_name = 'N. Arun',         email = 'arun@email.com'      WHERE id = 3;
UPDATE users SET full_name = 'J. Kavipriya',    email = 'kavipriya@email.com' WHERE id = 1;
UPDATE users SET full_name = 'A. Akalvily',     email = 'akalvily@email.com'  WHERE id = 5;
UPDATE users SET full_name = 'T.H.K. Madushan', email = 'madushan@email.com'  WHERE id = 6;

-- Password hash below is "password", the same as every seeded account.
INSERT INTO users
  (id, role_id, full_name, nic, phone, email, address, password_hash,
   trust_score, gift_receive_enabled, status, joined_at) VALUES
  (7, 5, 'P. Thineka', '199812345677', '+94 77 111 1007', 'thineka@email.com',
      'Lanka Hardware (Pvt) Ltd, Colombo 10', '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a',
      70, 0, 'active', '2024-10-01 09:00:00');

-- The Sponsor login is Lanka Hardware's account.
UPDATE sponsors SET user_id = 7 WHERE id = 1;

-- Seeded notifications that named the old people behind these accounts.
UPDATE notifications
   SET payload = JSON_SET(payload, '$.title', 'Abishan accepted your request for Bosch Cordless Drill')
 WHERE user_id = 4 AND type = 'booking_accepted'
   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.title')) = 'Madushan accepted your request for Bosch Cordless Drill';

UPDATE notifications
   SET payload = JSON_SET(payload, '$.title', 'Arun sent you a gift of 10 pts')
 WHERE user_id = 4 AND type = 'gift_received'
   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.title')) = 'Kavipriya sent you a gift of 10 pts';

UPDATE notifications
   SET payload = JSON_SET(payload, '$.detail', 'Moderator J. Kavipriya vouched for request #A-1042.')
 WHERE user_id = 4 AND type = 'aid_grant_vouched'
   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.detail')) = 'Moderator A. Akalvily vouched for request #A-1042.';
