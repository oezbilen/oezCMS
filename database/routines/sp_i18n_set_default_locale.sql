-- -------------------------------------------------------------------------------------------------
-- Set Default Locale
--
-- Atomically switches the default locale to an existing active locale.
--
-- The current default locale is locked first to serialize concurrent default
-- locale changes. The requested target locale is then resolved and locked.
--
-- Opens its own transaction, so it must not be called from inside an application
-- transaction: START TRANSACTION would implicitly commit the enclosing one and
-- leave the caller believing it can still roll back.
--
-- An exception is raised if the requested locale does not exist or is inactive.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE PROCEDURE sp_i18n_set_default_locale(
    IN p_code VARCHAR(35) CHARACTER SET ascii COLLATE ascii_bin
)
MODIFIES SQL DATA
COMMENT 'Atomically switches the single default locale to an existing active locale'
BEGIN
    DECLARE v_current_id SMALLINT UNSIGNED DEFAULT NULL;
    DECLARE v_target_id SMALLINT UNSIGNED DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

        -- Serialize concurrent attempts to change the default locale.
        SELECT l.id
          INTO v_current_id
          FROM locales AS l
         WHERE l.is_default = TRUE
         LIMIT 1
           FOR UPDATE;

        -- Resolve and lock the requested active locale.
        CALL sp_i18n_lock_locale_id_from_code(p_code, TRUE, v_target_id);

        IF v_target_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'The default locale must be an existing active locale';
        END IF;

        -- Skip unnecessary writes when the locale is already the default.
        IF v_current_id IS NULL OR v_current_id <> v_target_id THEN
            UPDATE locales
               SET is_default = FALSE
             WHERE is_default = TRUE;

            UPDATE locales
               SET is_default = TRUE
             WHERE id = v_target_id;
        END IF;

    COMMIT;
END;
