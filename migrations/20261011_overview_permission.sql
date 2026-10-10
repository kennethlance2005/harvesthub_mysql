-- =========================================================
-- Garden Overview page (admin checklist section 4)
-- Adds the permission that opens it. Administrators get it automatically.
-- Safe to run more than once.
-- =========================================================
INSERT INTO PERMISSION (Code, Module, Name, Description, SortOrder) VALUES
('overview.view', 'Audit and reports', 'View garden overview', 'Open the Garden Overview: every plot, resource, request, exchange listing and registration in one place.', 73)
ON DUPLICATE KEY UPDATE Module = VALUES(Module), Name = VALUES(Name), Description = VALUES(Description), SortOrder = VALUES(SortOrder);

INSERT IGNORE INTO ROLE_PERMISSION (RoleID, PermissionID)
SELECT R.RoleID, P.PermissionID FROM ROLE R JOIN PERMISSION P ON P.Code = 'overview.view' WHERE R.Code = 'admin';
