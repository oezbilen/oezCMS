-- -------------------------------------------------------------------------------------------------
-- Media Texts
--
-- Title and alternative text of a media entry, per locale (ADR 0018). Alt text describes
-- an image for readers of one language, so it is written per language like any other
-- text. It is content rather than an interface string, which is why it does not live in
-- translation_values.
--
-- A row carries at least one of the two, and neither may be blank. A text means nothing
-- without its entry, so it goes with it; a locale that still has texts cannot be deleted.
--
-- Reading a text for a locale walks the fallback chain from v_i18n_locale_chains. That
-- read belongs to the media library and arrives with it.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE media_texts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_id INT UNSIGNED NOT NULL,
    locale_id SMALLINT UNSIGNED NOT NULL,
    title VARCHAR(255) DEFAULT NULL COMMENT 'Title shown with the entry',
    alt_text VARCHAR(1000) DEFAULT NULL COMMENT 'Replaces an image for readers who cannot see it',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uk_media_texts_media_locale (media_id, locale_id),
    INDEX idx_media_texts_locale (locale_id),
    CONSTRAINT chk_media_texts_title CHECK (title IS NULL OR CHAR_LENGTH(TRIM(title)) > 0),
    CONSTRAINT chk_media_texts_alt_text CHECK (alt_text IS NULL OR CHAR_LENGTH(TRIM(alt_text)) > 0),
    CONSTRAINT chk_media_texts_not_empty CHECK (title IS NOT NULL OR alt_text IS NOT NULL),
    CONSTRAINT fk_media_texts_media
        FOREIGN KEY (media_id) REFERENCES media (id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    CONSTRAINT fk_media_texts_locale
        FOREIGN KEY (locale_id) REFERENCES locales (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='Title and alternative text of media entries per locale';
