-- -------------------------------------------------------------------------------------------------
-- Seed Data: Locales
--
-- Inserts the default locales available to the translation system.
-- English is the root locale without a fallback and the seeded default. All
-- other locales initially fall back to English and can be enabled as needed.
-- -------------------------------------------------------------------------------------------------

INSERT INTO locales (
    id,
    code,
    english_name,
    native_name,
    fallback_locale_id,
    sort_order,
    is_active,
    is_default
) VALUES
    (1,  'en', 'English',    'English',      NULL, 10,  TRUE,  TRUE),
    (2,  'de', 'German',     'Deutsch',      1,    20,  TRUE,  FALSE),
    (3,  'fr', 'French',     'Français',     1,    30,  FALSE, FALSE),
    (4,  'es', 'Spanish',    'Español',      1,    40,  FALSE, FALSE),
    (5,  'it', 'Italian',    'Italiano',     1,    50,  FALSE, FALSE),
    (6,  'nl', 'Dutch',      'Nederlands',   1,    60,  FALSE, FALSE),
    (7,  'pl', 'Polish',     'Polski',       1,    70,  FALSE, FALSE),
    (8,  'sv', 'Swedish',    'Svenska',      1,    80,  FALSE, FALSE),
    (9,  'da', 'Danish',     'Dansk',        1,    90,  FALSE, FALSE),
    (10, 'fi', 'Finnish',    'Suomi',        1,    100, FALSE, FALSE),
    (11, 'no', 'Norwegian',  'Norsk',        1,    110, FALSE, FALSE),
    (12, 'pt', 'Portuguese', 'Português',    1,    120, FALSE, FALSE),
    (13, 'ru', 'Russian',    'Русский',      1,    130, FALSE, FALSE),
    (14, 'cs', 'Czech',      'Čeština',      1,    140, FALSE, FALSE),
    (15, 'sk', 'Slovak',     'Slovenčina',   1,    150, FALSE, FALSE),
    (16, 'hu', 'Hungarian',  'Magyar',       1,    160, FALSE, FALSE),
    (17, 'ro', 'Romanian',   'Română',       1,    170, FALSE, FALSE),
    (18, 'bg', 'Bulgarian',  'Български',    1,    180, FALSE, FALSE),
    (19, 'hr', 'Croatian',   'Hrvatski',     1,    190, FALSE, FALSE),
    (20, 'sr', 'Serbian',    'Српски',       1,    200, FALSE, FALSE),
    (21, 'sl', 'Slovenian',  'Slovenščina',  1,    210, FALSE, FALSE),
    (22, 'uk', 'Ukrainian',  'Українська',   1,    220, FALSE, FALSE),
    (23, 'tr', 'Turkish',    'Türkçe',       1,    230, FALSE, FALSE);
