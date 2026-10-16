-- Ensure Admin Reports can read rejection reasons across request types.
-- Safe to re-run; each column is added only when it is absent.

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'RESOURCE_TXN'
          AND COLUMN_NAME = 'RejectionReason'
    ),
    'SELECT 1',
    'ALTER TABLE RESOURCE_TXN ADD COLUMN RejectionReason VARCHAR(1000) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'PLOT_APPLICATION'
          AND COLUMN_NAME = 'RejectionReason'
    ),
    'SELECT 1',
    'ALTER TABLE PLOT_APPLICATION ADD COLUMN RejectionReason VARCHAR(1000) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'COORDINATOR_APPLICATION'
          AND COLUMN_NAME = 'RejectionReason'
    ),
    'SELECT 1',
    'ALTER TABLE COORDINATOR_APPLICATION ADD COLUMN RejectionReason VARCHAR(1000) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'SIGNUP_REQUEST'
          AND COLUMN_NAME = 'RejectionReason'
    ),
    'SELECT 1',
    'ALTER TABLE SIGNUP_REQUEST ADD COLUMN RejectionReason VARCHAR(1000) NULL'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
