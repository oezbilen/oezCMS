-- -------------------------------------------------------------------------------------------------
-- Lock Locale ID from Code
--
-- Returns the ID of a locale for the given locale code through p_locale_id and
-- locks the matching row using FOR UPDATE.
--
-- When p_require_active is TRUE, only active locales are considered.
-- When p_require_active is FALSE, the active state is ignored.
-- NULL is treated as TRUE to preserve the restrictive default behavior.
--
-- This procedure must be called inside an active transaction for the row lock
-- to remain effective after the procedure returns.
--
-- Returns NULL through p_locale_id if no matching locale exists.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE PROCEDURE sp_i18n_lock_locale_id_from_code(
    IN p_code VARCHAR(35) CHARACTER SET ascii COLLATE ascii_bin,
    IN p_require_active BOOLEAN,
    OUT p_locale_id SMALLINT UNSIGNED
)
MODIFIES SQL DATA
COMMENT 'Returns and locks a locale row by normalized code'
BEGIN
    SET p_locale_id = NULL;

    SELECT l.id
      INTO p_locale_id
      FROM locales AS l
     WHERE l.code = LOWER(TRIM(p_code))
       AND (
               COALESCE(p_require_active, TRUE) = FALSE
               OR l.is_active = TRUE
           )
     LIMIT 1
       FOR UPDATE;
END;
