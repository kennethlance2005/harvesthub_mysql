-- =========================================================
-- HarvestHub — MySQL schema + seed data
--
-- Import this once via phpMyAdmin (or `mysql -u root -p < schema.sql`)
-- to create the database and populate it with demo accounts and
-- sample data. After that, db.php connects to it directly — no
-- runtime table creation happens anymore (that was a SQLite-only
-- workaround for the earlier prototype).
--
-- All demo accounts use the password: demo1234
-- =========================================================

-- Insert code -- 

-- ---------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------

CREATE TABLE SYSTEM_ADMINISTRATOR (
    AdminID      INT AUTO_INCREMENT PRIMARY KEY,
    Name         VARCHAR(120)  NOT NULL,
    Email        VARCHAR(190)  NOT NULL UNIQUE,
    PasswordHash VARCHAR(255)  NOT NULL,
    Age          INT           NULL,
    Location     VARCHAR(60)   NULL,
    Status       VARCHAR(20)   NOT NULL DEFAULT 'Active',
    FailedLoginAttempts TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE GARDEN_COORDINATOR (
    CoordID      INT AUTO_INCREMENT PRIMARY KEY,
    Name         VARCHAR(120)  NOT NULL,
    Email        VARCHAR(190)  NOT NULL UNIQUE,
    PasswordHash VARCHAR(255)  NOT NULL,
    Shift        VARCHAR(20)   NOT NULL DEFAULT 'Morning',
    Location     VARCHAR(60)   NOT NULL DEFAULT 'Not provided',
    Status       VARCHAR(20)   NOT NULL DEFAULT 'Active',
    FailedLoginAttempts TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE COMMUNITY_GARDENER (
    GardenerID   INT AUTO_INCREMENT PRIMARY KEY,
    Name         VARCHAR(120)  NOT NULL,
    Email        VARCHAR(190)  NOT NULL UNIQUE,
    PasswordHash VARCHAR(255)  NOT NULL,
    Age          INT           NULL,
    Location     VARCHAR(60)   NULL,
    Status       VARCHAR(20)   NOT NULL DEFAULT 'Active',
    FailedLoginAttempts TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

ALTER TABLE GARDEN_COORDINATOR
    ADD COLUMN GardenerID INT NULL UNIQUE,
    ADD CONSTRAINT fk_coordinator_gardener
        FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID) ON DELETE SET NULL;

CREATE TABLE ACCOUNT_ARCHIVE_NOTICE (
    NoticeID   INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID INT          NOT NULL,
    AdminID    INT          NULL,
    Reason     VARCHAR(50)  NOT NULL,
    Details    VARCHAR(1000) NOT NULL,
    CreatedAt  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_archive_notice_gardener (GardenerID, CreatedAt),
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID) ON DELETE CASCADE,
    FOREIGN KEY (AdminID) REFERENCES SYSTEM_ADMINISTRATOR(AdminID) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE COORDINATOR_APPLICATION (
    ApplicationID INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID    INT          NOT NULL,
    Shift         VARCHAR(20)  NOT NULL,
    Motivation    VARCHAR(1000) NOT NULL,
    Status        VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    RejectionReason VARCHAR(1000) NULL,
    ReviewedAt    DATETIME     NULL,
    ReviewedBy    INT          NULL,
    RequestedAt   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coordinator_application_status (Status, RequestedAt),
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID) ON DELETE CASCADE,
    FOREIGN KEY (ReviewedBy) REFERENCES SYSTEM_ADMINISTRATOR(AdminID) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE PASSWORD_RESET (
    Email VARCHAR(255) NOT NULL,
    TokenHash VARCHAR(64) NOT NULL,
    ExpiresAt DATETIME NOT NULL,
    PRIMARY KEY (Email)
);

-- ---------------------------------------------------------    
-- Plots
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS GARDEN_PLOTS (
    PlotID INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID INT NOT NULL,
    CropName VARCHAR(100) NOT NULL,
    PlantedDate DATE NOT NULL,
    EstHarvestDate DATE NULL,
    Status ENUM('Planted', 'Growing', 'Harvested', 'Failed') DEFAULT 'Planted',
    Notes TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS COMMUNITY_PLOTS (
    PlotID INT AUTO_INCREMENT PRIMARY KEY,
    PlotName VARCHAR(20) NOT NULL,
    Status ENUM('Available', 'Pending Approval', 'Occupied') DEFAULT 'Available',
    OccupantID INT DEFAULT NULL,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Generate 8 standard plots for the map
INSERT INTO COMMUNITY_PLOTS (PlotName) VALUES 
('Plot A1'), ('Plot A2'), ('Plot A3'), ('Plot A4'), 
('Plot B1'), ('Plot B2'), ('Plot B3'), ('Plot B4');

CREATE TABLE PLOT (
    PltID      INT AUTO_INCREMENT PRIMARY KEY,
    Label      VARCHAR(80)  NOT NULL,
    GardenerID INT          NULL,
    Status     VARCHAR(20)  NOT NULL DEFAULT 'Available',
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID)
) ENGINE=InnoDB;

CREATE TABLE PLOT_APPLICATION (
    AppID       INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID  INT          NOT NULL,
    CoordID     INT          NULL,
    PltID       INT          NOT NULL,
    Status      VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    RequestType VARCHAR(20)  NOT NULL DEFAULT 'Apply',
    RejectionReason VARCHAR(1000) NULL,
    AppliedAt   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ProcessedAt DATETIME     NULL,
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID),
    FOREIGN KEY (CoordID) REFERENCES GARDEN_COORDINATOR(CoordID),
    FOREIGN KEY (PltID) REFERENCES PLOT(PltID)
) ENGINE=InnoDB;

CREATE TABLE PLOT_EVENT (
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

-- ---------------------------------------------------------
-- Crop log
-- ---------------------------------------------------------

CREATE TABLE CROP_LOG (
    LogID            INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID       INT          NOT NULL,
    GardenPlotID     INT          NULL,
    PltID            INT          NULL,
    CropName         VARCHAR(60)  NOT NULL,
    MaintenanceNotes TEXT         NULL,
    HarvestYield     VARCHAR(60)  NULL,
    LoggedAt         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID),
    FOREIGN KEY (GardenPlotID) REFERENCES GARDEN_PLOTS(PlotID),
    FOREIGN KEY (PltID) REFERENCES PLOT(PltID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Resources
-- ---------------------------------------------------------

CREATE TABLE RESOURCE (
    ResourceID   INT AUTO_INCREMENT PRIMARY KEY,
    Name         VARCHAR(80) NOT NULL,
    TotalQty     INT         NOT NULL,
    AvailableQty INT         NOT NULL
) ENGINE=InnoDB;

CREATE TABLE RESOURCE_TXN (
    TxnID       INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID  INT          NOT NULL,
    CoordID     INT          NULL,
    ResourceID  INT          NOT NULL,
    PltID       INT          NULL,
    Qty         INT          NOT NULL,
    RequestType VARCHAR(20)  NOT NULL DEFAULT 'Borrow',
    RequestNotes TEXT        NULL,
    SourcePersonalItemID INT NULL,
    ProcessedQty INT         NULL,
    Status      VARCHAR(20)  NOT NULL DEFAULT 'Requested',
    RequestedAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ApprovedAt  DATETIME     NULL,
    ReturnRequestedAt DATETIME NULL,
    ReturnedAt  DATETIME     NULL,
    RejectionReason VARCHAR(1000) NULL,
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID),
    FOREIGN KEY (CoordID) REFERENCES GARDEN_COORDINATOR(CoordID),
    FOREIGN KEY (ResourceID) REFERENCES RESOURCE(ResourceID),
    FOREIGN KEY (PltID) REFERENCES PLOT(PltID)
) ENGINE=InnoDB;

CREATE TABLE RESOURCE_EVENT (
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

CREATE TABLE IF NOT EXISTS PERSONAL_INVENTORY (
    ItemID INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID INT NOT NULL,
    ItemName VARCHAR(100) NOT NULL,
    Qty INT NOT NULL DEFAULT 1,
    AddedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Produce Exchange Board
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS EXCHANGE_BOARD (
    PostID INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID INT NOT NULL,
    ProduceName VARCHAR(100) NOT NULL,
    Qty VARCHAR(50) NOT NULL, -- e.g., "2 kg", "3 bundles"
    Description TEXT,
    Type ENUM('Offering', 'Seeking') DEFAULT 'Offering',
    Status ENUM('Active', 'Completed') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS EXCHANGE_CLAIMS (
    ClaimID INT AUTO_INCREMENT PRIMARY KEY,
    PostID INT NOT NULL,
    RequesterID INT NOT NULL,
    QtyWanted VARCHAR(50) NOT NULL,
    PickupDetails TEXT NOT NULL,
    Status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE EXCHANGE_LISTING (
    ListingID  INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID INT          NOT NULL,
    Crop       VARCHAR(60)  NOT NULL,
    Qty        INT          NOT NULL,
    Notes      VARCHAR(200) NULL,
    CreatedAt  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID)
) ENGINE=InnoDB;

CREATE TABLE EXCHANGE_ORDER (
    OrderID    INT AUTO_INCREMENT PRIMARY KEY,
    ListingID  INT      NOT NULL,
    GardenerID INT      NOT NULL,
    ClaimedAt  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ListingID) REFERENCES EXCHANGE_LISTING(ListingID),
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Public sign-up requests ("Create an Account" on the login page)
-- Sits here as 'Pending' until an admin approves/rejects it —
-- approval is what actually creates the account row.
-- ---------------------------------------------------------

CREATE TABLE SIGNUP_REQUEST (
    RequestID    INT AUTO_INCREMENT PRIMARY KEY,
    FirstName    VARCHAR(60)  NOT NULL,
    LastName     VARCHAR(60)  NOT NULL,
    Age          INT          NOT NULL,
    Location     VARCHAR(60)  NOT NULL,
    Email        VARCHAR(190) NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    Role         VARCHAR(20)  NOT NULL DEFAULT 'customer',
    Shift        VARCHAR(20)  NOT NULL DEFAULT 'Morning',
    Status       VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    RequestedAt  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ReviewedAt   DATETIME     NULL,
    ReviewedBy   INT          NULL,
    RejectionReason VARCHAR(1000) NULL,
    StatusToken  CHAR(64)     NULL UNIQUE
) ENGINE=InnoDB;

-- =========================================================
-- Seed data — demo accounts (password for all: demo1234)
-- =========================================================

INSERT INTO SYSTEM_ADMINISTRATOR (Name, Email, PasswordHash) VALUES
('Ana Bautista', 'admin@harvesthub.test', '$2y$10$kgUYrIbjaGh4VRY0mgupxeixHwMcnP/tsKVm2ODRX8nFf8oB/K5m2');

INSERT INTO GARDEN_COORDINATOR (Name, Email, PasswordHash, Shift, Location) VALUES
('Ramon Cruz', 'coordinator@harvesthub.test', '$2y$10$kgUYrIbjaGh4VRY0mgupxeixHwMcnP/tsKVm2ODRX8nFf8oB/K5m2', 'Morning', 'Manila');

INSERT INTO COMMUNITY_GARDENER (Name, Email, PasswordHash, Age, Location) VALUES
('Maria Santos', 'maria@harvesthub.test', '$2y$10$kgUYrIbjaGh4VRY0mgupxeixHwMcnP/tsKVm2ODRX8nFf8oB/K5m2', 34, 'Manila'),
('Jun Dela Cruz', 'jun@harvesthub.test', '$2y$10$kgUYrIbjaGh4VRY0mgupxeixHwMcnP/tsKVm2ODRX8nFf8oB/K5m2', 29, 'Quezon City'),
('Liza Ramos', 'liza@harvesthub.test', '$2y$10$kgUYrIbjaGh4VRY0mgupxeixHwMcnP/tsKVm2ODRX8nFf8oB/K5m2', 41, 'Pasig');

-- Plots (some assigned, some available)
INSERT INTO PLOT (Label, GardenerID, Status) VALUES
('Plot A1', 1, 'Occupied'),
('Plot A2', 2, 'Occupied'),
('Plot A3', NULL, 'Available'),
('Plot A4', NULL, 'Available'),
('Plot B1', NULL, 'Available'),
('Plot B2', NULL, 'Available'),
('Plot B3', NULL, 'Available'),
('Plot B4', NULL, 'Available');

-- A pending application, so the Staff dashboard has something to act on
INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES
(3, 3, 'Pending', 'Apply'); -- Liza applying for Plot A3

-- Crop logs for gardeners who already have plots
INSERT INTO CROP_LOG (GardenerID, PltID, CropName, MaintenanceNotes, HarvestYield) VALUES
(1, 1, 'Tomatoes', 'Watered daily, staked on week 3', '5 kg'),
(2, 2, 'Kangkong', 'Harvested twice this month', '10 bundles');

-- Resources + one pending request
INSERT INTO RESOURCE (Name, TotalQty, AvailableQty) VALUES
('Shovel', 5, 4),
('Wheelbarrow', 2, 2),
('Watering Can', 8, 8),
('Fertilizer (bag)', 20, 20);

INSERT INTO RESOURCE_TXN (GardenerID, ResourceID, Qty, Status) VALUES
(1, 1, 1, 'Requested'); -- Maria requesting a shovel

-- Exchange listings
INSERT INTO EXCHANGE_LISTING (GardenerID, Crop, Qty, Notes) VALUES
(1, 'Tomatoes', 5, 'Freshly picked this morning, kg basis'),
(2, 'Kangkong', 10, 'Bundle of 10, happy to trade for herbs'),
(3, 'Calamansi', 20, 'Small but juicy, pesticide-free'),
(1, 'Okra', 8, 'Great for sinigang'),
(2, 'Sili', 15, 'Labuyo, spicy variety');
