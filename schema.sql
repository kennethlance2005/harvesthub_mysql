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
    AvailabilityDays VARCHAR(100) NOT NULL DEFAULT '',
    Motivation    VARCHAR(1000) NOT NULL,
    GardeningExperience VARCHAR(30) NOT NULL DEFAULT 'Not provided',
    LeadershipExperience VARCHAR(1000) NULL,
    AgreedToDuties TINYINT(1) NOT NULL DEFAULT 0,
    AgreedToRules TINYINT(1) NOT NULL DEFAULT 0,
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
    LastSentAt DATETIME NULL,
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
    Location   VARCHAR(40)  NULL,
    AreaSqM    DECIMAL(10,2) NULL,
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
    RequestReason VARCHAR(1000) NULL,
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

CREATE TABLE CROP_CATALOG (
    CropID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(60) NOT NULL,
    ScientificName VARCHAR(120) NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crop_catalog_name (Name)
) ENGINE=InnoDB;

INSERT INTO CROP_CATALOG (Name, ScientificName) VALUES
    -- Endemic & Native Fruits / Trees (Philippines)
    ('Abaca', 'Musa textilis'),
    ('Alupag', 'Dimocarpus longan subsp. malesianus'),
    ('Batuan', 'Garcinia binucao'),
    ('Bignay', 'Antidesma bunius'),
    ('Calamansi', 'Citrus × microcarpa'),
    ('Bitter Orange', 'Citrus aurantium'),
    ('Java Plum', 'Syzygium cumini'),
    ('Galo', 'Anacolosa frutescens'),
    ('Ylang-Ylang', 'Cananga odorata'),
    ('Kalumpit', 'Terminalia microcarpa'),
    ('Breadnut', 'Artocarpus camansi'),
    ('Bilimbi', 'Averrhoa bilimbi'),
    ('Elephant Apple', 'Dillenia philippinensis'),
    ('Lanzones', 'Lansium parasiticum'),
    ('Lipote', 'Syzygium polycephaloides'),
    ('Lubeg', 'Syzygium lineatum'),
    ('Velvet Apple', 'Diospyros blancoi'),
    ('Marang', 'Artocarpus odoratissimus'),
    ('Nipa Palm', 'Nypa fruticans'),
    ('Coconut', 'Cocos nucifera'),
    ('Horse Mango', 'Mangifera altissima'),
    ('Pili Nut', 'Canarium ovatum'),
    ('Rambutan', 'Nephelium lappaceum'),
    ('Saba Banana', 'Musa acuminata × balbisiana'),
    ('Cotton Fruit', 'Sandoricum koetjape'),
    ('Wild Raspberry', 'Rubus rosifolius'),
    ('Pummelo', 'Citrus maxima'),
    ('Tabon-tabon', 'Atuna racemosa'),
    -- Native & Heritage Vegetables / Root Crops
    ('Malabar Spinach', 'Basella alba'),
    ('Bitter Melon', 'Momordica charantia'),
    ('Hyacinth Bean', 'Lablab purpureus'),
    ('Taro', 'Colocasia esculenta'),
    ('Sweet Potato', 'Ipomoea batatas'),
    ('Water Spinach', 'Ipomoea aquatica'),
    ('Winter Melon', 'Benincasa hispida'),
    ('Jackfruit', 'Artocarpus heterophyllus'),
    ('Ginger', 'Zingiber officinale'),
    ('Moringa', 'Moringa oleifera'),
    ('Carabao Mango', 'Mangifera indica'),
    ('Mustard Greens', 'Brassica juncea'),
    ('Fiddlehead Fern', 'Diplazium esculentum'),
    ('Fragrant Pandan', 'Pandanus amaryllifolius'),
    ('Lima Bean', 'Phaseolus lunatus'),
    ('Ridge Gourd', 'Luffa acutangula'),
    ('Jute Mallow', 'Corchorus olitorius'),
    ('Winged Bean', 'Psophocarpus tetragonolobus'),
    ('Bird''s Eye Chili', 'Capsicum frutescens'),
    ('Jicama', 'Pachyrhizus erosus'),
    ('Yardlong Bean', 'Vigna unguiculata subsp. sesquipedalis'),
    ('Eggplant', 'Solanum melongena'),
    ('Lesser Yam', 'Dioscorea esculenta'),
    ('Purple Yam', 'Dioscorea alata'),
    ('Bottle Gourd', 'Lagenaria siceraria'),
    -- Common & Global Vegetables, Root Crops & Legumes
    ('Green Beans', 'Phaseolus vulgaris'),
    ('Bell Pepper', 'Capsicum annuum'),
    ('Broccoli', 'Brassica oleracea var. italica'),
    ('Cabbage', 'Brassica oleracea var. capitata'),
    ('Carrot', 'Daucus carota subsp. sativus'),
    ('Cauliflower', 'Brassica oleracea var. botrytis'),
    ('Celery', 'Apium graveolens'),
    ('Corn', 'Zea mays'),
    ('Cucumber', 'Cucumis sativus'),
    ('Garlic', 'Allium sativum'),
    ('Lettuce', 'Lactuca sativa'),
    ('Onion', 'Allium cepa'),
    ('Peanut', 'Arachis hypogaea'),
    ('Peas', 'Pisum sativum'),
    ('Potato', 'Solanum tuberosum'),
    ('Squash', 'Cucurbita maxima'),
    ('Radish', 'Raphanus sativus'),
    ('Spinach', 'Spinacia oleracea'),
    ('Tomato', 'Solanum lycopersicum'),
    -- Common & Global Fruits
    ('Apple', 'Malus domestica'),
    ('Avocado', 'Persea americana'),
    ('Banana (Cavendish)', 'Musa acuminata'),
    ('Dragon Fruit', 'Selenicereus undatus'),
    ('Grapes', 'Vitis vinifera'),
    ('Guava', 'Psidium guajava'),
    ('Lemon', 'Citrus limon'),
    ('Melon', 'Cucumis melo'),
    ('Orange', 'Citrus × sinensis'),
    ('Papaya', 'Carica papaya'),
    ('Pineapple', 'Ananas comosus'),
    ('Strawberry', 'Fragaria × ananassa'),
    ('Watermelon', 'Citrullus lanatus'),
    -- Common Herbs & Spices
    ('Basil', 'Ocimum basilicum'),
    ('Chives', 'Allium schoenoprasum'),
    ('Cilantro / Coriander', 'Coriandrum sativum'),
    ('Lemongrass', 'Cymbopogon citratus'),
    ('Mint', 'Mentha'),
    ('Oregano', 'Origanum vulgare'),
    ('Parsley', 'Petroselinum crispum'),
    ('Rosemary', 'Salvia rosmarinus')
ON DUPLICATE KEY UPDATE ScientificName = VALUES(ScientificName);

CREATE TABLE CROP_CATALOG_REQUEST (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    GardenerID INT NOT NULL,
    CropName VARCHAR(60) NOT NULL,
    ScientificName VARCHAR(120) NULL,
    Notes VARCHAR(500) NULL,
    Status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    ReviewReason VARCHAR(1000) NULL,
    ReviewedBy INT NULL,
    RequestedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ReviewedAt DATETIME NULL,
    INDEX idx_crop_catalog_request_status (Status, RequestedAt),
    FOREIGN KEY (GardenerID) REFERENCES COMMUNITY_GARDENER(GardenerID) ON DELETE CASCADE,
    FOREIGN KEY (ReviewedBy) REFERENCES GARDEN_COORDINATOR(CoordID) ON DELETE SET NULL
) ENGINE=InnoDB;

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

CREATE TABLE SIGNUP_EMAIL_VERIFICATION (
    Email        VARCHAR(190)  NOT NULL PRIMARY KEY,
    FirstName    VARCHAR(60)   NOT NULL,
    LastName     VARCHAR(60)   NOT NULL,
    Age          INT           NOT NULL,
    Location     VARCHAR(60)   NOT NULL,
    PasswordHash VARCHAR(255)  NOT NULL,
    CodeHash     VARCHAR(255)  NOT NULL,
    ExpiresAt    DATETIME      NOT NULL,
    Attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    LastSentAt   DATETIME      NOT NULL,
    RequestedAt  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_signup_email_verification_expiry (ExpiresAt)
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
INSERT INTO PLOT (Label, Location, AreaSqM, GardenerID, Status) VALUES
('Plot A1', 'Manila', 12.00, 1, 'Occupied'),
('Plot A2', 'Quezon City', 10.50, 2, 'Occupied'),
('Plot A3', 'Makati', 8.00, NULL, 'Available'),
('Plot A4', 'Pasig', 9.50, NULL, 'Available'),
('Plot B1', 'Marikina', 11.00, NULL, 'Available'),
('Plot B2', 'Taguig', 8.50, NULL, 'Available'),
('Plot B3', 'Pasay', 10.00, NULL, 'Available'),
('Plot B4', 'Mandaluyong', 9.00, NULL, 'Available');

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


-- =========================================================
-- Audit log + roles and permissions
-- (same as migrations/20261011_audit_log_and_roles.sql and
--  migrations/20261011_audit_log_lock.sql, for fresh installs)
-- =========================================================
-- ---------------------------------------------------------
-- Audit log: one row per thing that happened. The app only ever INSERTs;
-- 20261011_audit_log_lock.sql adds triggers that block UPDATE/DELETE.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS AUDIT_LOG (
    LogID       BIGINT AUTO_INCREMENT PRIMARY KEY,
    OccurredAt  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ActorType   VARCHAR(20)   NULL,      -- admin | coordinator | gardener | system | guest
    ActorID     INT           NULL,
    ActorName   VARCHAR(120)  NULL,      -- kept so entries still read well after renames/archives
    Module      VARCHAR(30)   NOT NULL,  -- accounts | roles | plots | resources | exchange | crops | admin
    Action      VARCHAR(50)   NOT NULL,  -- e.g. login, login_failed, role_demoted, plot_request_rejected
    TargetType  VARCHAR(30)   NULL,      -- e.g. gardener, plot, resource_request, role
    TargetID    INT           NULL,
    TargetName  VARCHAR(160)  NULL,
    Summary     VARCHAR(500)  NOT NULL,  -- plain English: "Maria rejected Juan's request for Plot B."
    Reason      VARCHAR(1000) NULL,
    BeforeData  TEXT          NULL,      -- JSON text of the relevant fields before the change
    AfterData   TEXT          NULL,      -- JSON text of the relevant fields after the change
    IpAddress   VARCHAR(45)   NULL,
    INDEX idx_audit_time (OccurredAt),
    INDEX idx_audit_module_action (Module, Action, OccurredAt),
    INDEX idx_audit_actor (ActorType, ActorID, OccurredAt),
    INDEX idx_audit_target (TargetType, TargetID, OccurredAt)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Roles
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS ROLE (
    RoleID       INT AUTO_INCREMENT PRIMARY KEY,
    Code         VARCHAR(40)  NOT NULL UNIQUE,  -- admin | coordinator | gardener | custom slug
    Name         VARCHAR(60)  NOT NULL UNIQUE,
    Description  VARCHAR(255) NULL,
    IsBuiltIn    TINYINT(1)   NOT NULL DEFAULT 0, -- built-in roles can't be renamed or deleted
    CreatedAt    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy    INT          NULL,               -- AdminID
    UpdatedAt    DATETIME     NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS PERMISSION (
    PermissionID INT AUTO_INCREMENT PRIMARY KEY,
    Code         VARCHAR(60)  NOT NULL UNIQUE,  -- e.g. plots.approve
    Module       VARCHAR(30)  NOT NULL,         -- groups the tick-box matrix
    Name         VARCHAR(80)  NOT NULL,
    Description  VARCHAR(255) NOT NULL,         -- plain English, shown next to the tick box
    SortOrder    INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ROLE_PERMISSION (
    RoleID       INT NOT NULL,
    PermissionID INT NOT NULL,
    PRIMARY KEY (RoleID, PermissionID),
    FOREIGN KEY (RoleID) REFERENCES ROLE(RoleID) ON DELETE CASCADE,
    FOREIGN KEY (PermissionID) REFERENCES PERMISSION(PermissionID) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Extra (custom) roles given to an account. Multiple roles per account allowed.
CREATE TABLE IF NOT EXISTS USER_ROLE (
    AccountType    VARCHAR(20) NOT NULL,  -- admin | coordinator | gardener (the account they log in with)
    AccountID      INT         NOT NULL,
    RoleID         INT         NOT NULL,
    AssignedAt     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AssignedBy     INT         NULL,      -- AdminID
    PRIMARY KEY (AccountType, AccountID, RoleID),
    INDEX idx_user_role_role (RoleID),
    FOREIGN KEY (RoleID) REFERENCES ROLE(RoleID) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Who gained or lost which role, when and why (built-in and custom roles).
CREATE TABLE IF NOT EXISTS ROLE_HISTORY (
    HistoryID      INT AUTO_INCREMENT PRIMARY KEY,
    AccountType    VARCHAR(20)   NOT NULL,
    AccountID      INT           NOT NULL,
    AccountName    VARCHAR(120)  NOT NULL,
    RoleID         INT           NULL,     -- NULL if the role was later deleted
    RoleName       VARCHAR(60)   NOT NULL, -- kept so history survives role deletion/renames
    ChangeType     VARCHAR(20)   NOT NULL, -- granted | removed | demoted
    ReasonCategory VARCHAR(40)   NULL,     -- Inactive | Policy violation | Performance | Requested by user | Other
    ReasonDetails  VARCHAR(1000) NULL,
    ChangedBy      INT           NULL,     -- AdminID
    ChangedByName  VARCHAR(120)  NULL,
    ChangedAt      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_history_account (AccountType, AccountID, ChangedAt),
    FOREIGN KEY (RoleID) REFERENCES ROLE(RoleID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed: built-in roles
-- ---------------------------------------------------------
INSERT INTO ROLE (Code, Name, Description, IsBuiltIn) VALUES
('admin',       'Administrator', 'Runs HarvestHub: manages accounts, roles, approvals and reports.', 1),
('coordinator', 'Coordinator',   'Runs the garden day to day: plots, shared resources and crop catalog requests.', 1),
('gardener',    'Gardener',      'Community member who tends plots, borrows resources and trades produce.', 1)
ON DUPLICATE KEY UPDATE Description = VALUES(Description), IsBuiltIn = 1;

-- ---------------------------------------------------------
-- Seed: permissions (the tick-box matrix)
-- ---------------------------------------------------------
INSERT INTO PERMISSION (Code, Module, Name, Description, SortOrder) VALUES
('plots.view',                     'Plots',        'View plots',                 'See the plot map, who holds each plot, and plot requests.', 10),
('plots.add',                      'Plots',        'Add plots',                  'Create new plots on the garden map.', 11),
('plots.delete',                   'Plots',        'Delete plots',               'Remove empty plots from the garden map.', 12),
('plots.approve',                  'Plots',        'Approve plot requests',      'Accept requests to be given or to give up a plot.', 13),
('plots.reject',                   'Plots',        'Reject plot requests',       'Decline plot requests, with a reason the gardener can see.', 14),
('resources.view',                 'Resources',    'View resources',             'See shared stock, who is borrowing what, and resource requests.', 20),
('resources.add_stock',            'Resources',    'Add stock',                  'Add new resources or increase how many are available.', 21),
('resources.approve',              'Resources',    'Approve resource requests',  'Lend out resources and accept donations.', 22),
('resources.reject',               'Resources',    'Reject resource requests',   'Decline resource requests, with a reason the gardener can see.', 23),
('resources.request_return',       'Resources',    'Ask for items back',         'Ask a gardener to return something they borrowed.', 24),
('crops.review_catalog',           'Crops',        'Review crop catalog requests','Approve or decline gardeners'' requests to add a crop to the catalog.', 30),
('registrations.review',           'Registrations','Review registrations',       'See pending sign-up requests and the applicant''s details.', 40),
('registrations.approve',          'Registrations','Approve registrations',      'Create gardener accounts from sign-up requests.', 41),
('registrations.reject',           'Registrations','Reject registrations',       'Decline sign-up requests, with a reason the applicant can see.', 42),
('coordinator_applications.review','Coordinator applications','Review coordinator applications','Approve or decline gardeners who apply to become coordinators.', 50),
('accounts.view',                  'Accounts',     'View accounts',              'See the lists of gardeners, coordinators and administrators.', 60),
('accounts.archive',               'Accounts',     'Archive accounts',           'Archive or restore accounts, and send archive warnings.', 61),
('accounts.enable',                'Accounts',     'Unlock accounts',            'Unlock accounts that were locked after failed logins.', 62),
('accounts.demote',                'Accounts',     'Remove coordinator role',    'Take coordinator tools away from someone, with a required reason.', 63),
('accounts.create_admin',          'Accounts',     'Create administrators',      'Register new administrator accounts.', 64),
('audit.view',                     'Audit and reports','View audit log',         'See the full history of who did what and when.', 70),
('reports.view',                   'Audit and reports','View reports',           'Open the dashboard charts and reports.', 71),
('reports.export',                 'Audit and reports','Export reports',         'Download reports and the audit log as CSV or PDF.', 72),
('roles.manage',                   'Roles',        'Manage roles',               'Create, edit and delete roles, change their permissions, and give roles to people.', 80)
ON DUPLICATE KEY UPDATE Module = VALUES(Module), Name = VALUES(Name), Description = VALUES(Description), SortOrder = VALUES(SortOrder);

-- ---------------------------------------------------------
-- Seed: what each built-in role can do today
-- ---------------------------------------------------------
-- Administrators: everything.
INSERT IGNORE INTO ROLE_PERMISSION (RoleID, PermissionID)
SELECT R.RoleID, P.PermissionID FROM ROLE R CROSS JOIN PERMISSION P WHERE R.Code = 'admin';

-- Coordinators: plots, resources and crop catalog requests (matches current behaviour).
INSERT IGNORE INTO ROLE_PERMISSION (RoleID, PermissionID)
SELECT R.RoleID, P.PermissionID FROM ROLE R JOIN PERMISSION P
  ON P.Code IN ('plots.view', 'plots.add', 'plots.delete', 'plots.approve', 'plots.reject',
                'resources.view', 'resources.add_stock', 'resources.approve', 'resources.reject', 'resources.request_return',
                'crops.review_catalog')
WHERE R.Code = 'coordinator';

-- Gardeners: no admin/coordinator permissions; their own self-service
-- actions (requesting plots, logging crops, trading) need none.

DROP TRIGGER IF EXISTS audit_log_no_update;
DROP TRIGGER IF EXISTS audit_log_no_delete;

DELIMITER $$

CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON AUDIT_LOG
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log entries cannot be changed.';
END$$

CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON AUDIT_LOG
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log entries cannot be deleted.';
END$$

DELIMITER ;

-- ---------------------------------------------------------
-- Garden Overview permission (same as migrations/20261011_overview_permission.sql)
-- ---------------------------------------------------------
INSERT INTO PERMISSION (Code, Module, Name, Description, SortOrder) VALUES
('overview.view', 'Audit and reports', 'View garden overview', 'Open the Garden Overview: every plot, resource, request, exchange listing and registration in one place.', 73)
ON DUPLICATE KEY UPDATE Module = VALUES(Module), Name = VALUES(Name), Description = VALUES(Description), SortOrder = VALUES(SortOrder);

INSERT IGNORE INTO ROLE_PERMISSION (RoleID, PermissionID)
SELECT R.RoleID, P.PermissionID FROM ROLE R JOIN PERMISSION P ON P.Code = 'overview.view' WHERE R.Code = 'admin';
