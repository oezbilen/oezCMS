-- -------------------------------------------------------------------------------------------------
-- Seed Data: Locale Settings
--
-- Inserts the one settings row and makes English the default. English is the root of
-- the seeded fallback tree (004, 005), so right after deployment every seeded chain ends
-- at the default. Switching the default later leaves the chains as they are; the default
-- is appended behind them rather than becoming their root (sp_i18n_set_default_locale).
--
-- English is selected by code (ADR 0006). If it were missing, this statement inserts
-- nothing and says so through a row count, and the seed test fails on the missing
-- default.
-- -------------------------------------------------------------------------------------------------

INSERT INTO locale_settings (default_locale_id)
SELECT l.id
FROM locales AS l
WHERE l.code = 'en';
