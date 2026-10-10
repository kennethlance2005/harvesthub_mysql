SET @has_rejection_reason = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'SIGNUP_REQUEST'
      AND COLUMN_NAME = 'RejectionReason'
);
SET @migration_sql = IF(
    @has_rejection_reason = 0,
    'ALTER TABLE SIGNUP_REQUEST ADD COLUMN RejectionReason VARCHAR(1000) NULL',
    'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_status_token = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'SIGNUP_REQUEST'
      AND COLUMN_NAME = 'StatusToken'
);
SET @has_status_token_unique_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'SIGNUP_REQUEST'
      AND COLUMN_NAME = 'StatusToken'
      AND NON_UNIQUE = 0
);
SET @migration_sql = IF(
    @has_status_token = 0,
    'ALTER TABLE SIGNUP_REQUEST ADD COLUMN StatusToken CHAR(64) NULL UNIQUE',
    IF(
        @has_status_token_unique_index = 0,
        'ALTER TABLE SIGNUP_REQUEST ADD UNIQUE INDEX uq_signup_request_status_token (StatusToken)',
        'SELECT 1'
    )
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
