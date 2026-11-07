-- Apply the three-month review date to ongoing trials created under the old rule.
UPDATE moderator_assignments
   SET trial_ends_at = appointed_at + INTERVAL 3 MONTH
 WHERE status = 'trial' AND confirmed_at IS NULL AND ended_at IS NULL
   AND (trial_ends_at IS NULL OR trial_ends_at = appointed_at + INTERVAL 6 MONTH);
