-- ============================================================================
--  MITHRA — 025 Rating tags, edits and one rating per record (Plan 3.5)
--
--    tags        the quick tags chosen in the rating popup
--    updated_at  ratings can be edited for 7 days
--
--  One rating per rater per record: the unique keys include the ratee because
--  every record has exactly two parties, so (rater, record, ratee) is one
--  rating per record — and it applies cleanly to databases whose seed data
--  already holds a rater with two rows on one booking. MySQL and MariaDB both
--  allow many NULLs in a unique key, so each key only bites on its own kind
--  of record.
-- ============================================================================

USE mithra;

ALTER TABLE ratings
  ADD COLUMN tags       JSON NULL AFTER comment,
  ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD UNIQUE KEY uk_rt_booking  (rater_id, booking_id, ratee_id),
  ADD UNIQUE KEY uk_rt_donation (rater_id, donation_id, ratee_id),
  ADD UNIQUE KEY uk_rt_gift     (rater_id, gift_id, ratee_id);
