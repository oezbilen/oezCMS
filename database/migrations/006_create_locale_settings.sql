-- -------------------------------------------------------------------------------------------------
-- Locale Settings
--
-- Holds the installation-wide locale settings in exactly one row. Today that is the
-- default locale: the last-resort translation fallback that fn_i18n_translate appends
-- behind every fallback chain.
--
-- One row, held in place three ways. The primary key is pinned to 1 by a check, so a
-- second row has nowhere to go. 007 inserts the row and trg_locale_settings_before_delete
-- refuses removing it. default_locale_id is NOT NULL, so the row cannot be emptied
-- instead. A flag on locales could only bound the default to at most one; this leaves
-- no state without one (ADR 0015).
--
-- The foreign key references (id, is_active) rather than id alone, and the referencing
-- state column is pinned to TRUE. The default is therefore always an active locale, and
-- a locale cannot be deactivated or deleted while it is the default: either change would
-- leave the reference pointing at a key that no longer exists. As a foreign key the rule
-- also holds between concurrent transactions, where a trigger reading the other table
-- would need locking reads to be safe.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE locale_settings (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Always 1: the table holds exactly one row',
    default_locale_id SMALLINT UNSIGNED NOT NULL
        COMMENT 'Locale used as the last-resort translation fallback',
    is_default_locale_active BOOLEAN NOT NULL DEFAULT TRUE
        COMMENT 'Always TRUE, so the foreign key accepts only an active default locale',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    INDEX idx_locale_settings_default_locale (default_locale_id, is_default_locale_active),
    CONSTRAINT chk_locale_settings_single_row CHECK (id = 1),
    CONSTRAINT chk_locale_settings_is_default_locale_active CHECK (is_default_locale_active = 1),
    CONSTRAINT fk_locale_settings_active_default_locale
        FOREIGN KEY (default_locale_id, is_default_locale_active)
        REFERENCES locales (id, is_active)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='Installation-wide locale settings in exactly one row';
