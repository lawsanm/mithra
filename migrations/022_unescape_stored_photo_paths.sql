-- ============================================================================
--  MITHRA — 022 Store photo paths without escaped slashes
--
--  PHP's json_encode() writes "item-photos/abc.jpg" as "item-photos\/abc.jpg".
--  MySQL 8 treats the two as the same JSON string, but MariaDB 10.4 (XAMPP)
--  compares the stored text, so JSON_CONTAINS() never matched an uploaded
--  photo and the photo proxy answered 404 for it. The models now write paths
--  with JSON_UNESCAPED_SLASHES; this rewrites the rows already stored.
--
--  '\\/' in these literals is a backslash followed by a slash.
-- ============================================================================

USE mithra;

UPDATE items
   SET photos = REPLACE(photos, '\\/', '/')
 WHERE photos LIKE '%\\\\/%';

UPDATE handover_records
   SET lender_photos   = REPLACE(lender_photos, '\\/', '/'),
       borrower_photos = REPLACE(borrower_photos, '\\/', '/')
 WHERE lender_photos LIKE '%\\\\/%' OR borrower_photos LIKE '%\\\\/%';

UPDATE return_records
   SET lender_photos   = REPLACE(lender_photos, '\\/', '/'),
       borrower_photos = REPLACE(borrower_photos, '\\/', '/')
 WHERE lender_photos LIKE '%\\\\/%' OR borrower_photos LIKE '%\\\\/%';
