-- -------------------------------------------------------------------------------------------------
-- Locale ID from Code
--
-- Returns the ID of an active locale for the given locale code.
-- The input is normalized by trimming whitespace and converting it to lowercase.
-- Returns NULL if no matching active locale exists.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_i18n_locale_id_from_code(
    p_code VARCHAR(35) CHARACTER SET ascii COLLATE ascii_bin
)
RETURNS SMALLINT UNSIGNED
READS SQL DATA
BEGIN
    DECLARE v_id SMALLINT UNSIGNED;

    SELECT id
      INTO v_id
      FROM locales
     WHERE code = LOWER(TRIM(p_code))
       AND is_active = TRUE
     LIMIT 1;

    RETURN v_id;
END;
