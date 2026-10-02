-- ============================================================================
--  MITHRA — 018 A ledger reason for refunding a cancelled booking
--
--  A booking cancelled after acceptance but before handover returns the whole
--  rental charge from the In-Flight Pool to the borrower (Plan §10.1). The
--  buffer goes back as 'buffer_refund'; the charge itself had no reason of its
--  own until now. Adding a value at the end of an ENUM keeps every stored row.
-- ============================================================================

USE mithra;

ALTER TABLE point_ledger
  MODIFY COLUMN reason ENUM(
      'sponsor_contribution','welcome_bonus','moderator_stipend','community_reward','reserve_topup',
      'rental_charge','buffer_hold','buffer_refund','rental_payout','late_fee','damage_penalty',
      'shortfall_cover','gift','aid_grant','aid_return',
      'bond_hold','bond_return','bond_forfeit','account_closure','parting_gift','recycle',
      'booking_refund'          -- In-Flight -> borrower, rental charge of a cancelled booking (§10.1)
  ) NOT NULL;
