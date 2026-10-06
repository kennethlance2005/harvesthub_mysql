-- Store when a plot application is accepted or rejected.
-- Fresh installs already include this column in schema.sql.
USE harvesthub;

ALTER TABLE PLOT_APPLICATION
    ADD COLUMN ProcessedAt DATETIME NULL AFTER AppliedAt;
