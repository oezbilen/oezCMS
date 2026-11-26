-- -------------------------------------------------------------------------------------------------
-- Keep The Locale Settings Row
--
-- locale_settings holds exactly one row, and removing it would leave translation without
-- a default locale. The check on the primary key stops a second row and NOT NULL stops
-- the default being cleared; only a DELETE is left, and it is refused here.
--
-- TRUNCATE does not fire triggers, but it needs the DROP privilege, which the runtime
-- account does not hold (database-privileges.md).
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_locale_settings_before_delete
BEFORE DELETE ON locale_settings
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'The locale settings row cannot be deleted';
END;
