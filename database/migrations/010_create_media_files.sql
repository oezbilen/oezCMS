-- -------------------------------------------------------------------------------------------------
-- Media Files
--
-- One row per stored file, that is per distinct content. The file lives on disk under
-- its SHA-256, so the hash is both the identity of the content and its path (ADR 0016).
-- chk_media_files_sha256 admits exactly 64 lower-case hex digits, and with that no name
-- that could climb out of the storage directory. The unique key stores identical bytes
-- once, however many media entries use them.
--
-- A row describes bytes that never change, so the row does not change either:
-- trg_media_files_before_update refuses every update, and there is no updated_at.
-- trg_media_files_before_insert refuses a type that is not active and a size above the
-- type's limit. Width and height are known for images only, and then both are.
--
-- A file is removed only once no media entry refers to it (fk_media_file restricts).
-- Removing the bytes on disk is a separate step outside the database.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE media_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sha256 CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL
        COMMENT 'SHA-256 of the content in lower-case hex; also the file name',
    media_type_id SMALLINT UNSIGNED NOT NULL COMMENT 'Type detected from the content',
    size_bytes INT UNSIGNED NOT NULL COMMENT 'Size of the content in bytes',
    width INT UNSIGNED DEFAULT NULL COMMENT 'Width in pixels, images only',
    height INT UNSIGNED DEFAULT NULL COMMENT 'Height in pixels, images only',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uk_media_files_sha256 (sha256),
    INDEX idx_media_files_type (media_type_id),
    CONSTRAINT chk_media_files_sha256 CHECK (sha256 REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT chk_media_files_size CHECK (size_bytes > 0),
    CONSTRAINT chk_media_files_dimensions_paired CHECK ((width IS NULL) = (height IS NULL)),
    CONSTRAINT chk_media_files_dimensions_positive CHECK (width IS NULL OR (width > 0 AND height > 0)),
    CONSTRAINT fk_media_files_type
        FOREIGN KEY (media_type_id) REFERENCES media_types (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='Stored file contents, one row per distinct SHA-256';
