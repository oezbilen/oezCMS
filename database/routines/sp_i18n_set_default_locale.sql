-- -------------------------------------------------------------------------------------------------
-- Set Default Locale
--
-- Makes the active locale with the given code the default: the last-resort fallback that
-- fn_i18n_translate appends behind every chain. Only the setting moves. Fallback chains
-- stay as configured, so the new default does not become the root of anything.
--
-- The switch is a single UPDATE of the one row in locale_settings, so there is no moment
-- without a default and no need for a transaction of its own. The procedure may
-- therefore be called from inside an application transaction, and rolls back with it.
--
-- The lookup only turns an unknown or inactive code into a readable refusal. The rule
-- itself is fk_locale_settings_active_default_locale, which also refuses a locale that is
-- deactivated between the lookup and the update.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE PROCEDURE sp_i18n_set_default_locale(
    IN p_code VARCHAR(35) CHARACTER SET ascii COLLATE ascii_bin
)
MODIFIES SQL DATA
SQL SECURITY INVOKER
COMMENT 'Switches the default locale to an existing active locale'
BEGIN
    DECLARE v_locale_id SMALLINT UNSIGNED DEFAULT NULL;

    SET v_locale_id = fn_i18n_locale_id_from_code(p_code);

    IF v_locale_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'The default locale must be an existing active locale';
    END IF;

    UPDATE locale_settings
       SET default_locale_id = v_locale_id
     WHERE id = 1;
END;
