-- Existing databases only; fresh installs get this column from schema.sql.
ALTER TABLE CROP_LOG
    ADD COLUMN GardenPlotID INT NULL AFTER GardenerID,
    ADD CONSTRAINT fk_crop_log_garden_plot
        FOREIGN KEY (GardenPlotID) REFERENCES GARDEN_PLOTS(PlotID);
