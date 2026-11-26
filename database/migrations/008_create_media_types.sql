-- -------------------------------------------------------------------------------------------------
-- Media Types
--
-- The allow-list of file types media accepts, each with its own size limit (ADR 0017).
-- A type is the one detected from a file's content. The name a client sent and the type
-- it declared play no part, so neither has a column anywhere in the media schema.
--
-- Deactivating a type stops new files of that type; trg_media_files_before_insert reads
-- the flag. Files already stored keep their type, which is why the flag is not part of
-- the foreign key from media_files, as the active default locale is in locale_settings.
--
-- A type added later is a migration, so its size limit is reviewed together with it.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE media_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mime_type VARCHAR(100)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL
        COMMENT 'Type as detected from the content, e.g. image/png',
    max_bytes INT UNSIGNED NOT NULL COMMENT 'Largest accepted file of this type, in bytes',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'TRUE if new files of this type are accepted',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uk_media_types_mime_type (mime_type),
    CONSTRAINT chk_media_types_mime_type CHECK (mime_type REGEXP '^[a-z]+/[a-z0-9]+([.+-][a-z0-9]+)*$'),
    CONSTRAINT chk_media_types_max_bytes CHECK (max_bytes > 0),
    CONSTRAINT chk_media_types_is_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='File types media accepts, with a size limit per type';
