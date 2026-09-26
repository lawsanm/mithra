-- Store record references instead of maintaining copies of names in notification text.
USE mithra;

UPDATE notifications
   SET payload = JSON_SET(payload, '$.booking_id',
       CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.href')), '/bookings/', -1), '?', 1) AS UNSIGNED))
 WHERE type IN ('booking_accepted', 'return_due')
   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.href')) REGEXP '^/bookings/[0-9]+(\\?.*)?$';

UPDATE notifications
   SET payload = JSON_SET(payload, '$.aid_grant_id',
       CAST(SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.href')), '/', -1) AS UNSIGNED))
 WHERE type IN ('aid_grant_vouched', 'aid_grant_approved')
   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.href')) REGEXP '^/aid-grants/[0-9]+$';

UPDATE notifications
   SET payload = JSON_SET(payload, '$.item_id',
       CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.href')), '/items/', -1), '/', 1) AS UNSIGNED))
 WHERE type IN ('listing_approved', 'listing_adjusted', 'listing_rejected')
   AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.href')) REGEXP '^/items/[0-9]+(/edit)?$';

-- The original seed's received-gift notification describes gift 5.
UPDATE notifications n JOIN gifts g ON g.id = 5 AND g.recipient_id = n.user_id
   SET n.payload = JSON_SET(n.payload, '$.gift_id', g.id)
 WHERE n.type = 'gift_received'
   AND JSON_UNQUOTE(JSON_EXTRACT(n.payload, '$.title')) IN
       ('Kavipriya sent you a gift of 10 pts', 'Arun sent you a gift of 10 pts');
