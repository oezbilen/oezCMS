-- -------------------------------------------------------------------------------------------------
-- Resolve Locale Fallback
--
-- Resolves the final locale by following the fallback chain.
-- Traversal stops when no further fallback exists, a self-reference is detected,
-- or the configured maximum depth is reached to guard against invalid fallback loops.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_i18n_locale_resolve(
    p_locale_id SMALLINT UNSIGNED,
    p_max_depth INT
)
RETURNS SMALLINT UNSIGNED
READS SQL DATA
BEGIN
    DECLARE v_current SMALLINT UNSIGNED;
    DECLARE v_next SMALLINT UNSIGNED;
    DECLARE v_depth INT DEFAULT 0;

    IF p_locale_id IS NULL THEN
        RETURN NULL;
    END IF;

    SET v_current = p_locale_id;

    WHILE v_depth < p_max_depth DO
        SELECT fallback_locale_id
          INTO v_next
          FROM locales
         WHERE id = v_current
         LIMIT 1;

        IF v_next IS NULL THEN
            RETURN v_current;
        END IF;

        IF v_next = v_current THEN
            RETURN v_current;
        END IF;

        SET v_current = v_next;
        SET v_depth = v_depth + 1;
    END WHILE;

    RETURN v_current;
END;
