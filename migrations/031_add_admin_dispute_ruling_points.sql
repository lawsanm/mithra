-- ============================================================================
-- MITHRA — 031 persist Admin dispute awards and their refund ledger movement
-- Proposal §10.2: the Admin's ruling is final and can move points.
-- ============================================================================

USE mithra;

ALTER TABLE disputes
  ADD COLUMN ruling_points INT UNSIGNED NULL AFTER resolution;

ALTER TABLE point_ledger
  MODIFY COLUMN reason ENUM(
      'sponsor_contribution','welcome_bonus','moderator_stipend','community_reward','reserve_topup',
      'rental_charge','buffer_hold','buffer_refund','rental_payout','late_fee','damage_penalty',
      'shortfall_cover','gift','aid_grant','aid_return',
      'bond_hold','bond_return','bond_forfeit','account_closure','parting_gift','recycle',
      'booking_refund','dispute_refund'
  ) NOT NULL;
