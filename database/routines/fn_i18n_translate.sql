-- -------------------------------------------------------------------------------------------------
-- Translate
--
-- Resolves a translation for the requested domain, key, and locale code.
--
-- Resolution order:
--   1. Requested locale (matched even when inactive, so its chain still applies)
--   2. Each locale along the fallback chain, at most three locales in total
--   3. Default locale (installation-configurable via sp_i18n_set_default_locale)
--   4. Technical identifier in the form "domain.key"
--
-- Translation values are only returned from active locales and non-deleted
-- translation keys.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_i18n_translate(
    p_domain      VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin,
    p_key         VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin,
    p_locale_code VARCHAR(35)  CHARACTER SET ascii COLLATE ascii_bin
)
RETURNS TEXT
READS SQL DATA
COMMENT 'Resolves a translation via the fallback chain, then the default locale, then the key'
BEGIN
    DECLARE v_value     TEXT;
    DECLARE v_locale_id SMALLINT UNSIGNED;
    DECLARE v_depth     TINYINT UNSIGNED DEFAULT 0;

    -- Chain start: match the code even when the locale is inactive,
    -- so a disabled locale still falls through its configured chain.
    SET v_locale_id = fn_i18n_locale_lookup(p_locale_code, FALSE);

    -- 1 + 2) Requested locale, then each fallback hop
    WHILE v_locale_id IS NOT NULL AND v_depth < 3 DO
        SET v_value = NULL;

        SELECT tv.value
          INTO v_value
          FROM translation_keys AS tk
          JOIN translation_values AS tv
            ON tv.translation_key_id = tk.id
           AND tv.locale_id = v_locale_id
          JOIN locales AS l
            ON l.id = tv.locale_id
           AND l.is_active = TRUE
         WHERE tk.domain = p_domain
           AND tk.translation_key = p_key
           AND tk.deleted_at IS NULL
         LIMIT 1;

        IF v_value IS NOT NULL THEN
            RETURN v_value;
        END IF;

        SELECT fallback_locale_id
          INTO v_locale_id
          FROM locales
         WHERE id = v_locale_id
         LIMIT 1;

        SET v_depth = v_depth + 1;
    END WHILE;

    -- 3) Default locale
    SET v_value = NULL;

    SELECT tv.value
      INTO v_value
      FROM translation_keys AS tk
      JOIN translation_values AS tv
        ON tv.translation_key_id = tk.id
      JOIN locales AS l
        ON l.id = tv.locale_id
       AND l.is_default = TRUE
       AND l.is_active = TRUE
     WHERE tk.domain = p_domain
       AND tk.translation_key = p_key
       AND tk.deleted_at IS NULL
     LIMIT 1;

    IF v_value IS NOT NULL THEN
        RETURN v_value;
    END IF;

    -- 4) Technical identifier
    RETURN CONCAT(p_domain, '.', p_key);
END;
