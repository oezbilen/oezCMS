-- -------------------------------------------------------------------------------------------------
-- Protect System Translation Keys From Being Rewritten
--
-- A system key is addressed from code, so its domain and key must stay put for as long
-- as the row exists. The flag itself is immutable as well: without that rule the
-- protection could simply be cleared first and everything else done afterwards.
--
-- Promotion is not restricted. An ordinary key may become a system key, because the
-- guard reads OLD.is_system and stays out of the way when the row was never protected.
--
-- Soft deletion is deliberately not repeated here. It is a property of the resulting
-- row, which chk_translation_keys_system_not_deleted enforces on every write; the one
-- path that constraint cannot see is the flag being cleared in the same statement, and
-- that is refused above.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_translation_keys_before_update
BEFORE UPDATE ON translation_keys
FOR EACH ROW
BEGIN
    IF OLD.is_system THEN
        IF NEW.domain <> OLD.domain OR NEW.translation_key <> OLD.translation_key THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'A system translation key cannot be renamed';
        END IF;

        IF NOT NEW.is_system THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'A system translation key cannot lose its protection';
        END IF;
    END IF;
END;
