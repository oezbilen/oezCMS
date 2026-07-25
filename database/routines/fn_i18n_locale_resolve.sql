-- -------------------------------------------------------------------------------------------------
-- Resolve Locale Fallback
--
-- Returns the last locale of the fallback chain starting at the given locale, or NULL
-- when no such locale exists.
--
-- The chain comes from v_i18n_locale_chains, so the traversal, the depth cap, and the
-- cycle handling are shared with translation instead of reimplemented. A chain
-- truncated by the cap resolves to the deepest locale still within it.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_i18n_locale_resolve(
    p_locale_id SMALLINT UNSIGNED
)
RETURNS SMALLINT UNSIGNED
READS SQL DATA
COMMENT 'Returns the final locale of a fallback chain'
BEGIN
    DECLARE v_id SMALLINT UNSIGNED DEFAULT NULL;

    SELECT c.locale_id
      INTO v_id
      FROM v_i18n_locale_chains AS c
     WHERE c.root_locale_id = p_locale_id
     ORDER BY c.depth DESC
     LIMIT 1;

    RETURN v_id;
END;
