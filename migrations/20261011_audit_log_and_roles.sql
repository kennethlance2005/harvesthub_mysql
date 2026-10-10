-- =========================================================
-- Audit log + roles and permissions (checklist sections 2, 5, 6, 9)
--
-- Design decision: the three account tables (SYSTEM_ADMINISTRATOR,
-- GARDEN_COORDINATOR, COMMUNITY_GARDENER) stay as they are. Accounts are
-- referred to everywhere below as (AccountType, AccountID), where
-- AccountType is 'admin', 'coordinator' or 'gardener'.
--
-- Built-in roles (Administrator, Coordinator, Gardener) come from those
-- tables: having a row in SYSTEM_ADMINISTRATOR is what makes someone an
-- administrator. USER_ROLE only holds extra custom roles such as
-- "Inventory Clerk", so the two can never disagree.
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
