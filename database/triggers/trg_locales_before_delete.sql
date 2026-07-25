-- -------------------------------------------------------------------------------------------------
-- Protect The Default Locale From Deletion
--
-- The default locale is the last-resort translation fallback, so it must not be
-- removed. The foreign key already blocks deleting any locale another row falls
-- back to, but the default may have no such referrer; this trigger guards it
-- independently.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_locales_before_delete
BEFORE DELETE ON locales
FOR EACH ROW
BEGIN
    IF OLD.is_default THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'The default locale cannot be deleted';
    END IF;
END;
