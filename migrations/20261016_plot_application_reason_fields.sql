-- Repair the two plot-application reason columns reported missing by Render.
-- Safe to rerun; each column is added only when it is absent.

SET @plot_request_reason_migration = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'PLOT_APPLICATION'
          AND COLUMN_NAME = 'RequestReason'
    ),
    'SELECT 1',
    'ALTER TABLE PLOT_APPLICATION ADD COLUMN RequestReason VARCHAR(1000) NULL AFTER RequestType'
);

PREPARE plot_request_reason_statement FROM @plot_request_reason_migration;
EXECUTE plot_request_reason_statement;
DEALLOCATE PREPARE plot_request_reason_statement;

SET @plot_rejection_reason_migration = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'PLOT_APPLICATION'
          AND COLUMN_NAME = 'RejectionReason'
    ),
    'SELECT 1',
    'ALTER TABLE PLOT_APPLICATION ADD COLUMN RejectionReason VARCHAR(1000) NULL'
);

PREPARE plot_rejection_reason_statement FROM @plot_rejection_reason_migration;
EXECUTE plot_rejection_reason_statement;
DEALLOCATE PREPARE plot_rejection_reason_statement;
