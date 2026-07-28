-- -------------------------------------------------------------------------------------------------
-- Translation Keys
--
-- Technical translation key definitions used as stable identifiers for localized texts.
-- Supports domains, soft deletion, ordering, and protected system keys.
--
-- A domain and key pair identifies one row for the lifetime of the installation. The
-- unique index is unconditional on purpose: soft deletion hides a key, it does not
-- release its name. Translation values hang off the id, so a second row under the same
-- identity would strand every value the first one had collected.
--
-- Restoring a key is therefore clearing deleted_at, not inserting it again.
-- -------------------------------------------------------------------------------------------------

CREATE TABLE translation_keys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    domain VARCHAR(100)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL
        COMMENT 'Technical namespace, e.g. core or plugin name',
    translation_key VARCHAR(255)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL
        COMMENT 'Technical key identifier within the domain',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_system BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'TRUE = protected from renaming and deletion',
    deleted_at DATETIME(3) DEFAULT NULL COMMENT 'Soft-delete timestamp',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uk_translation_keys_identity (domain, translation_key),
    INDEX idx_translation_keys_domain_active_order (domain, deleted_at, sort_order, translation_key),
    CONSTRAINT chk_translation_keys_domain CHECK (domain REGEXP '^[a-z][a-z0-9]*([._-][a-z0-9]+)*$'),
    CONSTRAINT chk_translation_keys_key CHECK (translation_key REGEXP '^[a-z0-9]+([._-][a-z0-9]+)*$'),
    CONSTRAINT chk_translation_keys_is_system CHECK (is_system IN (0,1)),
    CONSTRAINT chk_translation_keys_system_not_deleted CHECK (is_system = 0 OR deleted_at IS NULL)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  COMMENT='Technical identifiers for translatable texts';
