-- ============================================================================
--  MITHRA — 014 Divisions record their province and postal code
--
--  The Admin now picks a province, then a district from that province, when
--  creating or editing a GN division, and enters its postal code. Every seeded
--  division's province is derived from its district below.
--
--  Run after 013_seed_dehiwala_moderator.sql.
-- ============================================================================

USE mithra;

ALTER TABLE gn_divisions
  ADD COLUMN province    VARCHAR(40) NULL AFTER id,
  ADD COLUMN postal_code CHAR(5)     NULL AFTER name;

UPDATE gn_divisions SET province = CASE
  WHEN district IN ('Colombo', 'Gampaha', 'Kalutara')                              THEN 'Western Province'
  WHEN district IN ('Kandy', 'Matale', 'Nuwara Eliya')                             THEN 'Central Province'
  WHEN district IN ('Galle', 'Matara', 'Hambantota')                               THEN 'Southern Province'
  WHEN district IN ('Jaffna', 'Kilinochchi', 'Mannar', 'Mullaitivu', 'Vavuniya')   THEN 'Northern Province'
  WHEN district IN ('Ampara', 'Batticaloa', 'Trincomalee')                         THEN 'Eastern Province'
  WHEN district IN ('Kurunegala', 'Puttalam')                                      THEN 'North Western Province'
  WHEN district IN ('Anuradhapura', 'Polonnaruwa')                                 THEN 'North Central Province'
  WHEN district IN ('Badulla', 'Monaragala')                                       THEN 'Uva Province'
  WHEN district IN ('Kegalle', 'Ratnapura')                                        THEN 'Sabaragamuwa Province'
END;

UPDATE gn_divisions SET postal_code = '00300' WHERE id = 1;  -- Kollupitiya (Colombo 3)
UPDATE gn_divisions SET postal_code = '00400' WHERE id = 2;  -- Bambalapitiya (Colombo 4)
UPDATE gn_divisions SET postal_code = '00600' WHERE id = 3;  -- Wellawatte (Colombo 6)
UPDATE gn_divisions SET postal_code = '10350' WHERE id = 4;  -- Dehiwala
UPDATE gn_divisions SET postal_code = '10370' WHERE id = 5;  -- Mount Lavinia
UPDATE gn_divisions SET postal_code = '10250' WHERE id = 6;  -- Nugegoda

-- Fails if a hand-added row's district is misspelt (no province matched):
-- fix that row's district to one of the 25 names above and re-run.
ALTER TABLE gn_divisions MODIFY province VARCHAR(40) NOT NULL;
