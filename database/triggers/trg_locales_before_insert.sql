-- -------------------------------------------------------------------------------------------------
-- Prevent Locale Self-Fallback On Insert
--
-- On insert a locale can only reference rows that already exist, so it can never
-- close a longer cycle through its own brand-new id; only a direct self-reference
-- is possible (with an explicitly assigned id), and the foreign key does not catch
-- it because a single-row self-reference satisfies the constraint. The full chain
-- walk lives in trg_locales_before_update, where repointing an existing row can
-- close a multi-node cycle.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_locales_before_insert
BEFORE INSERT ON locales
FOR EACH ROW
BEGIN
    IF NEW.fallback_locale_id IS NOT NULL AND NEW.fallback_locale_id = NEW.id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A locale cannot be its own fallback';
    END IF;
END;
