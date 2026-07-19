-- -------------------------------------------------------------------------------------------------
-- Locales
--
-- Defines the available locales used by the translation system.
-- Supports locale fallback chains for missing translations.
-- Fallback cycles are prevented by database constraints and application logic.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE locales (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(35)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NOT NULL
    COMMENT 'Normalized lowercase locale tag, e.g. de, en, de-at, es-419',
    english_name VARCHAR(64) NOT NULL COMMENT 'Locale name in English',
    native_name VARCHAR(64) NOT NULL COMMENT 'Locale name in its native language',
    fallback_locale_id SMALLINT UNSIGNED DEFAULT NULL
        COMMENT 'Fallback locale used when translation is missing',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'TRUE if locale is enabled for use',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uk_locales_code (code),
    INDEX idx_locales_fallback (fallback_locale_id),
    INDEX idx_locales_active (is_active, sort_order, code),
    CONSTRAINT chk_locales_code CHECK (code REGEXP '^[a-z]{2,3}(-[a-z0-9]{2,8})*$'),
    CONSTRAINT chk_locales_is_active CHECK (is_active IN (0,1)),
    CONSTRAINT fk_locales_fallback_locale
        FOREIGN KEY (fallback_locale_id) REFERENCES locales(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='Available locales; fallback cycles are prevented by database and application logic';
