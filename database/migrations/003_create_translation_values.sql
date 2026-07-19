-- -------------------------------------------------------------------------------------------------
-- Translation Values
--
-- Stores the localized text for each translation key and locale combination.
-- Each translation key can have at most one translation per locale.
-- Translation values are automatically removed when their translation key is deleted.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE translation_values (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    translation_key_id INT UNSIGNED NOT NULL,
    locale_id SMALLINT UNSIGNED NOT NULL,
    value TEXT NOT NULL COMMENT 'Translated content',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uk_translation_values_key_locale (translation_key_id, locale_id),
    INDEX idx_translation_values_locale (locale_id),
    CONSTRAINT chk_translation_values_not_empty CHECK (CHAR_LENGTH(TRIM(value)) > 0),
    CONSTRAINT fk_translation_values_key
        FOREIGN KEY (translation_key_id) REFERENCES translation_keys (id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    CONSTRAINT fk_translation_values_locale
        FOREIGN KEY (locale_id) REFERENCES locales (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='Translated values per key and locale';
