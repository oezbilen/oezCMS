-- -------------------------------------------------------------------------------------------------
-- Keep The Public Id Of A Media Entry
--
-- public_id is what other systems, and later URLs, refer to. A changed value would leave
-- every such reference pointing at nothing or at an entry it was never meant for.
-- Everything else about an entry may change, including the file it points at.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_media_before_update
BEFORE UPDATE ON media
FOR EACH ROW
BEGIN
    IF NEW.public_id <> OLD.public_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'The public id of a media entry cannot change';
    END IF;
END;
