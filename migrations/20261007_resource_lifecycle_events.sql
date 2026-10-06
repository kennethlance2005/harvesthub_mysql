CREATE TABLE IF NOT EXISTS RESOURCE_EVENT (
    EventID      INT AUTO_INCREMENT PRIMARY KEY,
    ResourceID   INT          NOT NULL,
    EventType    ENUM('Added', 'Borrowed', 'Returned', 'Return Requested') NOT NULL,
    Qty          INT          NOT NULL,
    ActorType    ENUM('staff', 'customer') NOT NULL,
    ActorName    VARCHAR(120) NOT NULL,
    GardenerID   INT          NULL,
    GardenerName VARCHAR(120) NULL,
    CoordID      INT          NULL,
    PltID        INT          NULL,
    PlotLabel    VARCHAR(80)  NULL,
    OccurredAt   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resource_event_timeline (OccurredAt, EventID),
    FOREIGN KEY (ResourceID) REFERENCES RESOURCE(ResourceID),
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID) ON DELETE SET NULL,
    FOREIGN KEY (CoordID) REFERENCES GARDEN_COORDINATOR(CoordID) ON DELETE SET NULL,
    FOREIGN KEY (PltID) REFERENCES PLOT(PltID) ON DELETE SET NULL
) ENGINE=InnoDB;
