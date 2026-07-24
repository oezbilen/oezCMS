-- -------------------------------------------------------------------------------------------------
-- Prevent Locale Fallback Cycles
--
-- Rejects a fallback that would make a locale its own fallback, either directly
-- (a -> a) or by closing a longer chain (a -> b -> a). The chain starting at the
-- proposed fallback is walked; if it leads back to the row being written, the
-- change forms a cycle and is rejected. A hop cap terminates the walk on
-- unexpectedly deep chains or pre-existing cyclic data.
--
-- Reads additionally cap their traversal depth (fn_i18n_translate) as an
-- unconditional termination guarantee, independent of how the data was produced.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_locales_before_update
BEFORE UPDATE ON locales
FOR EACH ROW
BEGIN
    DECLARE v_current SMALLINT UNSIGNED;
    DECLARE v_hops SMALLINT UNSIGNED DEFAULT 0;

    IF NEW.fallback_locale_id IS NOT NULL AND NEW.fallback_locale_id = NEW.id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A locale cannot be its own fallback';
    END IF;

    SET v_current = NEW.fallback_locale_id;

    WHILE v_current IS NOT NULL DO
        IF v_current = NEW.id THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'A locale fallback chain must not form a cycle';
        END IF;

        SET v_hops = v_hops + 1;

        IF v_hops > 32 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'A locale fallback chain is too deep';
        END IF;

        SELECT fallback_locale_id
          INTO v_current
          FROM locales
         WHERE id = v_current
         LIMIT 1;
    END WHILE;
END;
