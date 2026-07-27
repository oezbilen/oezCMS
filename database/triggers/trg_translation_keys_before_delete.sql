-- -------------------------------------------------------------------------------------------------
-- Protect System Translation Keys From Deletion
--
-- Soft-deleting a system key is already rejected by a CHECK constraint, but a
-- constraint never sees a DELETE: it validates the rows that remain. Removing the row
-- outright was therefore the one way to make a key the code depends on disappear.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_translation_keys_before_delete
BEFORE DELETE ON translation_keys
FOR EACH ROW
BEGIN
    IF OLD.is_system THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A system translation key cannot be deleted';
    END IF;
END;
