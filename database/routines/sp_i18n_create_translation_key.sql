-- -------------------------------------------------------------------------------------------------
-- Create Or Restore A Translation Key
--
-- Returns the id of the row carrying the given domain and key: creating it when it does
-- not exist, and clearing deleted_at when it does. A key has one row for good, so
-- creating and restoring are the same request made at different times, and a caller does
-- not have to know which of the two it is making.
--
-- The decision is a single statement. ON DUPLICATE KEY UPDATE settles the conflict
-- inside the engine, which closes the read-then-write window a SELECT followed by an
-- INSERT would leave open. That is also why this procedure needs no transaction of its
-- own, and therefore -- unlike sp_i18n_set_default_locale -- may be called from within
-- one. A key is usually created in the middle of a larger operation, which is exactly
-- where that restriction would have hurt.
--
-- LAST_INSERT_ID(id) makes the existing id readable on the conflict path. Measured on
-- MariaDB 11 before this was written: the expression is evaluated even when the update
-- changes nothing, so a key that is already active reports its own id rather than a
-- leftover from an earlier statement.
--
-- Input is not normalized. The CHECK constraints on the table demand lowercase, so a
-- miscased key is rejected rather than quietly turned into a different one.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE PROCEDURE sp_i18n_create_translation_key(
    IN p_domain          VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin,
    IN p_translation_key VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin
)
MODIFIES SQL DATA
COMMENT 'Creates a translation key or restores a soft-deleted one and returns its id'
BEGIN
    -- Declared with the column's own type so the result set reports an integer
    -- rather than the BIGINT UNSIGNED that LAST_INSERT_ID() returns.
    DECLARE v_id INT UNSIGNED DEFAULT NULL;

    INSERT INTO translation_keys (domain, translation_key)
    VALUES (p_domain, p_translation_key)
    ON DUPLICATE KEY UPDATE
        deleted_at = NULL,
        id = LAST_INSERT_ID(id);

    SET v_id = LAST_INSERT_ID();

    SELECT v_id AS id;
END;
