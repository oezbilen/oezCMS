-- -------------------------------------------------------------------------------------------------
-- Media
--
-- The entries of the media library. An entry points at its stored file, and several
-- entries may point at the same one: importing the same bytes twice gives two entries
-- and one file.
--
-- public_id is the identifier that leaves the system; the internal id never does
-- (ADR 0004). The application supplies it as a random version 4 UUID. A default from
-- UUID() would be a version 1 value, which carries the time it was created.
-- trg_media_before_update keeps the value fixed once others may hold it.
--
-- original_name is the file name the client sent, kept as display text. It never takes
-- part in a path (ADR 0016).
--
-- Soft deletion hides an entry and keeps its file referenced, as schema-conventions.md
-- describes. Deleting the row removes its texts with it.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE media (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id UUID NOT NULL COMMENT 'Random identifier that leaves the system',
    media_file_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL COMMENT 'File name as the client sent it, display only',
    deleted_at DATETIME(3) DEFAULT NULL COMMENT 'Soft-delete timestamp',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uk_media_public_id (public_id),
    INDEX idx_media_file (media_file_id),
    INDEX idx_media_listing (deleted_at, created_at),
    CONSTRAINT chk_media_original_name CHECK (CHAR_LENGTH(TRIM(original_name)) > 0),
    CONSTRAINT fk_media_file
        FOREIGN KEY (media_file_id) REFERENCES media_files (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='Entries of the media library';
