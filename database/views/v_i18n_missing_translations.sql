-- -------------------------------------------------------------------------------------------------
-- Missing Translations
--
-- Lists translation key and active locale combinations that do not yet have
-- a corresponding translation value.
-- Useful for identifying incomplete localization coverage.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE VIEW v_i18n_missing_translations AS
SELECT
    tk.domain,
    tk.translation_key,
    l.code AS locale_code
FROM translation_keys tk
CROSS JOIN locales l
LEFT JOIN translation_values tv
       ON tv.translation_key_id = tk.id
      AND tv.locale_id = l.id
WHERE tk.deleted_at IS NULL
  AND l.is_active = TRUE
  AND tv.id IS NULL;
