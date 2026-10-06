-- Existing crop maintenance records may not map to an assigned community plot.
ALTER TABLE CROP_LOG
    MODIFY PltID INT NULL;