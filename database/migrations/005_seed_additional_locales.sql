-- -------------------------------------------------------------------------------------------------
-- Seed Data: Additional Locales
--
-- The locales the translation system ships with. All fall back to English, and all are
-- inactive except German; enabling a locale is an operational decision, not a schema
-- one.
--
-- English is joined by code rather than written as a literal id, which is what makes the
-- relationship checkable. If the default locale were missing, this statement inserts
-- nothing and says so through a row count; a literal 1 would instead point 22 locales at
-- whichever row happens to carry that id.
--
-- The first branch of the union names the columns; the rest inherit those names by
-- position. ORDER BY is not about the ids themselves but about reproducibility: the same
-- migrations should produce the same database twice, so dumps stay comparable.
--
-- is_default is deliberately absent. The column defaults to FALSE, so this migration
-- cannot create a second default even by accident; that decision is made once, in 004.
-- -------------------------------------------------------------------------------------------------

INSERT INTO locales (
    code,
    english_name,
    native_name,
    fallback_locale_id,
    sort_order,
    is_active
)
SELECT seed.code, seed.english_name, seed.native_name, en.id, seed.sort_order, seed.is_active
FROM (
    SELECT 'de' AS code, 'German' AS english_name, 'Deutsch' AS native_name,
           20 AS sort_order, TRUE AS is_active
    UNION ALL SELECT 'fr', 'French',     'Français',     30,  FALSE
    UNION ALL SELECT 'es', 'Spanish',    'Español',      40,  FALSE
    UNION ALL SELECT 'it', 'Italian',    'Italiano',     50,  FALSE
    UNION ALL SELECT 'nl', 'Dutch',      'Nederlands',   60,  FALSE
    UNION ALL SELECT 'pl', 'Polish',     'Polski',       70,  FALSE
    UNION ALL SELECT 'sv', 'Swedish',    'Svenska',      80,  FALSE
    UNION ALL SELECT 'da', 'Danish',     'Dansk',        90,  FALSE
    UNION ALL SELECT 'fi', 'Finnish',    'Suomi',        100, FALSE
    UNION ALL SELECT 'no', 'Norwegian',  'Norsk',        110, FALSE
    UNION ALL SELECT 'pt', 'Portuguese', 'Português',    120, FALSE
    UNION ALL SELECT 'ru', 'Russian',    'Русский',      130, FALSE
    UNION ALL SELECT 'cs', 'Czech',      'Čeština',      140, FALSE
    UNION ALL SELECT 'sk', 'Slovak',     'Slovenčina',   150, FALSE
    UNION ALL SELECT 'hu', 'Hungarian',  'Magyar',       160, FALSE
    UNION ALL SELECT 'ro', 'Romanian',   'Română',       170, FALSE
    UNION ALL SELECT 'bg', 'Bulgarian',  'Български',    180, FALSE
    UNION ALL SELECT 'hr', 'Croatian',   'Hrvatski',     190, FALSE
    UNION ALL SELECT 'sr', 'Serbian',    'Српски',       200, FALSE
    UNION ALL SELECT 'sl', 'Slovenian',  'Slovenščina',  210, FALSE
    UNION ALL SELECT 'uk', 'Ukrainian',  'Українська',   220, FALSE
    UNION ALL SELECT 'tr', 'Turkish',    'Türkçe',       230, FALSE
) AS seed
CROSS JOIN locales AS en
WHERE en.code = 'en'
ORDER BY seed.sort_order;
