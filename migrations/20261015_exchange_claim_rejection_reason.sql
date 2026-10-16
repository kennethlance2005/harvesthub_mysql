SET @exchange_claim_reason_migration = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'EXCHANGE_CLAIMS'
          AND COLUMN_NAME = 'RejectionReason'
    ),
    'SELECT 1',
    'ALTER TABLE EXCHANGE_CLAIMS ADD COLUMN RejectionReason VARCHAR(1000) NULL'
);

PREPARE exchange_claim_reason_statement FROM @exchange_claim_reason_migration;
EXECUTE exchange_claim_reason_statement;
DEALLOCATE PREPARE exchange_claim_reason_statement;
