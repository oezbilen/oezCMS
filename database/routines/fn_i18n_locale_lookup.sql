-- -------------------------------------------------------------------------------------------------
-- Locale Lookup
--
-- Returns the ID of a locale for the given locale code, or NULL when no locale matches.
-- The input is normalized by trimming whitespace and converting it to lowercase.
--
-- When p_require_active is TRUE, only active locales are considered.
-- When p_require_active is FALSE, the active state is ignored, which lets a caller
-- start a fallback chain at a disabled locale.
-- NULL is treated as TRUE to preserve the restrictive default behavior.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_i18n_locale_lookup(
    p_code VARCHAR(35) CHARACTER SET ascii COLLATE ascii_bin,
    p_require_active BOOLEAN
)
RETURNS SMALLINT UNSIGNED
READS SQL DATA
COMMENT 'Returns a locale ID by normalized code with optional active-state filtering'
BEGIN
    DECLARE v_id SMALLINT UNSIGNED DEFAULT NULL;

    SELECT l.id
      INTO v_id
      FROM locales AS l
     WHERE l.code = LOWER(TRIM(p_code))
       AND (
               COALESCE(p_require_active, TRUE) = FALSE
               OR l.is_active = TRUE
           )
     LIMIT 1;

    RETURN v_id;
END;
