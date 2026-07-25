-- -------------------------------------------------------------------------------------------------
-- Translate
--
-- Resolves a translation for the requested domain, key, and locale code.
--
-- Resolution order:
--   1. Requested locale, matched even when inactive so its chain still applies
--   2. Each locale along its fallback chain, capped by fn_i18n_max_depth()
--   3. Default locale, appended one step behind the deepest possible chain step
--   4. Technical identifier in the form "domain.key"
--
-- Steps 1 to 3 form a single candidate list ordered by depth, so the resolution order
-- is data rather than control flow and cannot drift from fn_i18n_locale_resolve.
--
-- Values are read from active locales and non-deleted keys only. A step whose locale
-- is inactive stays in the chain but contributes nothing, which lets a disabled locale
-- be skipped without breaking the chain behind it.
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

    -- Chain start: match the code even when the locale is inactive,
    -- so a disabled locale still falls through its configured chain.
    SET v_locale_id = fn_i18n_locale_lookup(p_locale_code, FALSE);

    SELECT tv.value
      INTO v_value
      FROM (
          SELECT c.locale_id, c.depth
            FROM v_i18n_locale_chains AS c
           WHERE c.root_locale_id = v_locale_id

          UNION ALL

          SELECT d.id, fn_i18n_max_depth() + 1
            FROM locales AS d
           WHERE d.is_default = TRUE
      ) AS candidate
      JOIN locales AS l
        ON l.id = candidate.locale_id
       AND l.is_active = TRUE
      JOIN translation_values AS tv
        ON tv.locale_id = candidate.locale_id
      JOIN translation_keys AS tk
        ON tk.id = tv.translation_key_id
     WHERE tk.domain = p_domain
       AND tk.translation_key = p_key
       AND tk.deleted_at IS NULL
     ORDER BY candidate.depth
     LIMIT 1;

    RETURN COALESCE(v_value, CONCAT(p_domain, '.', p_key));
END;
