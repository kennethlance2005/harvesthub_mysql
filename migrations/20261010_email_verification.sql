CREATE TABLE IF NOT EXISTS SIGNUP_EMAIL_VERIFICATION (
    Email        VARCHAR(190)  NOT NULL PRIMARY KEY,
    FirstName    VARCHAR(60)   NOT NULL,
    LastName     VARCHAR(60)   NOT NULL,
    Age          INT           NOT NULL,
    Location     VARCHAR(60)   NOT NULL,
    PasswordHash VARCHAR(255)  NOT NULL,
    CodeHash     VARCHAR(255)  NOT NULL,
    ExpiresAt    DATETIME      NOT NULL,
    Attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    LastSentAt   DATETIME      NOT NULL,
    RequestedAt  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_signup_email_verification_expiry (ExpiresAt)
) ENGINE=InnoDB;

SET @has_password_reset_last_sent_at = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'PASSWORD_RESET'
      AND COLUMN_NAME = 'LastSentAt'
);
SET @migration_sql = IF(
    @has_password_reset_last_sent_at = 0,
    'ALTER TABLE PASSWORD_RESET ADD COLUMN LastSentAt DATETIME NULL',
    'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
