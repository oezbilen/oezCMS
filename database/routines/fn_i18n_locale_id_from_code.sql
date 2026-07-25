-- -------------------------------------------------------------------------------------------------
-- Locale ID from Code
--
-- Returns the ID of an active locale for the given locale code, or NULL when no
-- active locale matches. The input is normalized by trimming whitespace and
-- converting it to lowercase.
--
-- This is the restrictive form of fn_i18n_locale_lookup, kept as a single-argument
-- function because MariaDB has no default parameter values.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_i18n_locale_id_from_code(
    p_code VARCHAR(35) CHARACTER SET ascii COLLATE ascii_bin
)
RETURNS SMALLINT UNSIGNED
READS SQL DATA
COMMENT 'Returns the ID of an active locale by normalized code'
BEGIN
    RETURN fn_i18n_locale_lookup(p_code, TRUE);
END;
