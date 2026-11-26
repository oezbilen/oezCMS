-- -------------------------------------------------------------------------------------------------
-- Seed Data: The Default Locale
--
-- English is the root of the seeded fallback tree: it has no fallback of its own, and
-- every locale seeded in 005 falls back to it. 007 makes it the default as well.
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
    is_active
) VALUES
    ('en', 'English', 'English', NULL, 10, TRUE);
