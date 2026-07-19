-- -------------------------------------------------------------------------------------------------
-- Prevent Locale Self-Fallback
--
-- Prevents a locale from referencing itself as its fallback locale.
-- The trigger raises an explicit error before an invalid update is applied.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_locales_before_update
BEFORE UPDATE ON locales
FOR EACH ROW
BEGIN
    IF NEW.fallback_locale_id IS NOT NULL AND NEW.fallback_locale_id = NEW.id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A locale cannot be its own fallback';
    END IF;
END;
