ALTER TABLE RESOURCE_TXN
    ADD COLUMN RequestType VARCHAR(20) NOT NULL DEFAULT 'Borrow' AFTER Qty,
    ADD COLUMN RequestNotes TEXT NULL AFTER RequestType,
    ADD COLUMN SourcePersonalItemID INT NULL AFTER RequestNotes,
    ADD COLUMN ProcessedQty INT NULL AFTER SourcePersonalItemID;
