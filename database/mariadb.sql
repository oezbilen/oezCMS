-- -------------------------------------------------------------------------------------------------
-- RBAC-System (Role Based Access Control)
-- -------------------------------------------------------------------------------------------------
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid BINARY(16) NOT NULL,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    two_factor_secret VARBINARY(128) NULL,
    two_factor_confirmed_at DATETIME NULL,
    force_password_change BOOLEAN NOT NULL DEFAULT TRUE,
    password_changed_at DATETIME NULL,
    email_verified_at DATETIME NULL,  -- Denormalization shortcut
    locked_until DATETIME NULL,
    lock_reason VARCHAR(255) NULL,
    disabled_at DATETIME NULL,
    disable_reason VARCHAR(255) NULL,
    deleted_at DATETIME NULL,
    delete_reason VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_uuid (uuid),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    INDEX idx_users_locked_until (locked_until),
    INDEX idx_users_email_verified_at (email_verified_at),
    INDEX idx_users_deleted_at (deleted_at),
    CONSTRAINT chk_users_email_lowercase CHECK (email = LOWER(email)),
    CONSTRAINT chk_users_username CHECK(
        username NOT LIKE '%.%.'
        AND username REGEXP '^[a-z][a-z0-9]*(?:[._][a-z0-9]+)*$'
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = uca1400_ai_ci;