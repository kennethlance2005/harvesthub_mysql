-- Plot assignment and management timeline used by staff_records.php.
-- Fresh installs already create this table in schema.sql.
USE harvesthub;

CREATE TABLE IF NOT EXISTS PLOT_EVENT (
    EventID      INT AUTO_INCREMENT PRIMARY KEY,
    AppID        INT          NULL,
    PltID        INT          NULL,
    PlotLabel    VARCHAR(80)  NOT NULL,
    EventType    ENUM('Plot Added', 'Request Assignment', 'Request Unassign', 'Request Accepted', 'Request Rejected', 'Plot Unassigned') NOT NULL,
    ActorType    ENUM('customer', 'staff', 'admin', 'system') NOT NULL,
    ActorName    VARCHAR(120) NOT NULL,
    GardenerID   INT          NULL,
    GardenerName VARCHAR(120) NULL,
    CoordID      INT          NULL,
    OccurredAt   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plot_event_timeline (OccurredAt, EventID),
    FOREIGN KEY (AppID) REFERENCES PLOT_APPLICATION(AppID) ON DELETE SET NULL,
    FOREIGN KEY (PltID) REFERENCES PLOT(PltID) ON DELETE SET NULL,
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID) ON DELETE SET NULL,
    FOREIGN KEY (CoordID) REFERENCES GARDEN_COORDINATOR(CoordID) ON DELETE SET NULL
) ENGINE=InnoDB;
