-- -------------------------------------------------------------------------------------------------
-- Seed Data: The Default Locale
--
-- English is the root of the fallback tree: it has no fallback of its own, and it is the
-- seeded default that fn_i18n_translate falls through to before returning the technical
-- key.
--
-- It is seeded alone and first so every later seed can reference it by code. The id is
-- left to AUTO_INCREMENT for the same reason the fallback is: nothing may depend on
-- which number English happened to receive.
-- -------------------------------------------------------------------------------------------------

INSERT INTO locales (
    code,
    english_name,
    native_name,
    fallback_locale_id,
    sort_order,
    is_active,
    is_default
) VALUES
    ('en', 'English', 'English', NULL, 10, TRUE, TRUE);
