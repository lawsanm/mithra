-- ============================================================================
--  MITHRA — 008 Demo disasters for the moderator's relief CRUD
--  (Plan §14, §14.1 step 5)
--
--  Relief can only be recorded while Disaster Mode is active in the
--  moderator's division, and the Admin's Disaster Mode toggle is not built
--  yet. This seeds two disasters in Kollupitiya (division 1, moderated by
--  J. Kavipriya after 009):
--
--  1. An ended flood with its relief records, which stay locked for the
--     relief report and CSV.
--  2. An active flood, started a few days before this migration runs, so the
--     moderator can create, edit and delete relief records. Its dates are
--     relative to NOW() so the records are never dated in the future or
--     before the disaster began.
--
--  Run after 007_create_disaster_relief_records.sql.
-- ============================================================================

USE mithra;

INSERT INTO disaster_events (id, gn_division_id, started_by, reason, started_at, planned_end_at, ended_at) VALUES
  (1, 1, 6, 'Flash flooding along Galle Road after two days of heavy rain',
      '2026-06-02 07:30:00', '2026-06-12 18:00:00', '2026-06-10 17:00:00'),
  (2, 1, 6, 'Monsoon flooding in the lanes behind Kollupitiya Junction',
      NOW() - INTERVAL 5 DAY, NOW() + INTERVAL 9 DAY, NULL);

UPDATE gn_divisions
   SET disaster_mode_active = 1,
       disaster_mode_until  = NOW() + INTERVAL 9 DAY
 WHERE id = 1;

INSERT INTO disaster_relief_records
  (disaster_event_id, moderator_id, sponsor_id, relief_type, description, location,
   households_reached, estimated_value, distributed_on, notes) VALUES
  -- Ended flood: locked for reporting
  (1, 1, 2, 'food',     '60 dry-ration packs (rice, dhal, tinned fish)', 'Kollupitiya community hall',
      60, 180000, '2026-06-03', 'Packed with volunteers from the temple society.'),
  (1, 1, 3, 'medical',  '35 first-aid kits and oral rehydration salts',  'Kollupitiya community hall',
      35,  52500, '2026-06-04', NULL),
  (1, 1, NULL, 'water', '120 five-litre bottles of drinking water',      'Lanes 3 to 7, Galle Road',
      48,  36000, '2026-06-05', 'Donated by residents; no sponsor involved.'),
  (1, 1, 1, 'shelter',  '20 tarpaulins and rope',                        'St. Michael''s school grounds',
      20,  40000, '2026-06-06', NULL),

  -- Active flood: editable while Disaster Mode is on
  (2, 1, 2, 'food',     '40 cooked lunch packets',                       'Kollupitiya community hall',
      40,  24000, CURDATE() - INTERVAL 4 DAY, NULL),
  (2, 1, NULL, 'clothing', 'Bags of dry clothes and bedsheets',          'Lane 5 temporary shelter',
      15, NULL,   CURDATE() - INTERVAL 2 DAY, 'Collected from neighbours in division 1.');
