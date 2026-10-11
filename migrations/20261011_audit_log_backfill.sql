-- =========================================================
-- Copy older history into the audit log (admin checklist section 2)
--
-- Plot events (PLOT_EVENT), resource events (RESOURCE_EVENT) and crop
-- maintenance entries (CROP_LOG) recorded before HarvestHub started writing
-- these areas to AUDIT_LOG become audit entries, worded like the new ones.
-- Copied entries say so in their details ("Source").
--
-- Only history from BEFORE the first live audit entry of each area is copied,
-- so running this twice, or after the new code has been used, never creates
-- duplicates. Run it after 20261011_audit_log_and_roles.sql.
-- =========================================================

SET @plots_cutoff = (SELECT COALESCE(MIN(OccurredAt), '9999-12-31') FROM AUDIT_LOG WHERE Module = 'plots');
SET @resources_cutoff = (SELECT COALESCE(MIN(OccurredAt), '9999-12-31') FROM AUDIT_LOG WHERE Module = 'resources');
SET @crops_cutoff = (SELECT COALESCE(MIN(OccurredAt), '9999-12-31') FROM AUDIT_LOG WHERE Module = 'crops');

-- ---------------------------------------------------------
-- Plots
-- ---------------------------------------------------------
INSERT INTO AUDIT_LOG (OccurredAt, ActorType, ActorID, ActorName, Module, Action, TargetType, TargetID, TargetName, Summary, Reason, AfterData)
SELECT
    H.OccurredAt, H.ActorType, H.ActorID, H.ActorName, 'plots', H.Action, 'plot', H.PltID, H.PlotLabel,
    CASE H.Action
        WHEN 'plot_added'              THEN CONCAT(H.ActorName, ' added ', H.PlotLabel, ' to the garden map.')
        WHEN 'plot_requested'          THEN CONCAT(H.Gardener, ' asked to be given ', H.PlotLabel, '.')
        WHEN 'plot_return_requested'   THEN CONCAT(H.ActorName, ' asked ', H.Gardener, ' to give back ', H.PlotLabel, '.')
        WHEN 'plot_unassign_requested' THEN CONCAT(H.Gardener, ' asked to give up ', H.PlotLabel, '.')
        WHEN 'plot_request_approved'   THEN CONCAT(H.ActorName, ' gave ', H.PlotLabel, ' to ', H.Gardener, '.')
        WHEN 'plot_return_declined'    THEN CONCAT(H.Gardener, ' declined to give back ', H.PlotLabel, '.')
        WHEN 'plot_request_rejected'   THEN CONCAT(H.ActorName, ' declined ', H.GardenerPossessive, ' request for ', H.PlotLabel, '.')
        WHEN 'plot_returned'           THEN CONCAT(H.Gardener, ' gave back ', H.PlotLabel, ' after a coordinator asked for it.')
        WHEN 'plot_unassigned'         THEN IF(H.EventType = 'Request Accepted',
                                               CONCAT(H.ActorName, ' approved ', H.GardenerPossessive, ' request to give up ', H.PlotLabel, '. The plot is now available.'),
                                               CONCAT(H.ActorName, ' removed ', H.Gardener, ' from ', H.PlotLabel, '.'))
    END,
    H.Reason,
    '{"Source":"Copied from the older plot history"}'
FROM (
    SELECT E.EventID, E.OccurredAt, E.EventType, E.PltID, E.PlotLabel,
           CASE E.ActorType WHEN 'customer' THEN 'gardener' WHEN 'staff' THEN 'coordinator' ELSE E.ActorType END AS ActorType,
           CASE E.ActorType WHEN 'customer' THEN E.GardenerID WHEN 'staff' THEN E.CoordID END AS ActorID,
           COALESCE(E.ActorName, 'HarvestHub') AS ActorName,
           COALESCE(E.GardenerName, 'a gardener') AS Gardener,
           IF(RIGHT(COALESCE(E.GardenerName, 'a gardener'), 1) = 's',
              CONCAT(COALESCE(E.GardenerName, 'a gardener'), ''''),
              CONCAT(COALESCE(E.GardenerName, 'a gardener'), '''s')) AS GardenerPossessive,
           CASE
               WHEN E.EventType = 'Plot Added' THEN 'plot_added'
               WHEN E.EventType = 'Request Assignment' THEN 'plot_requested'
               WHEN E.EventType = 'Request Unassign' AND A.RequestType = 'Return' THEN 'plot_return_requested'
               WHEN E.EventType = 'Request Unassign' THEN 'plot_unassign_requested'
               WHEN E.EventType = 'Request Accepted' AND A.RequestType = 'Unassign' THEN 'plot_unassigned'
               WHEN E.EventType = 'Request Accepted' THEN 'plot_request_approved'
               WHEN E.EventType = 'Request Rejected' AND A.RequestType = 'Return' THEN 'plot_return_declined'
               WHEN E.EventType = 'Request Rejected' THEN 'plot_request_rejected'
               WHEN E.EventType = 'Plot Unassigned' AND A.RequestType = 'Return' THEN 'plot_returned'
               ELSE 'plot_unassigned'
           END AS Action,
           CASE
               WHEN E.EventType = 'Request Rejected' THEN A.RejectionReason
               WHEN E.EventType = 'Request Unassign' AND A.RequestType = 'Return' THEN A.RequestReason
           END AS Reason
    FROM PLOT_EVENT E
    LEFT JOIN PLOT_APPLICATION A ON A.AppID = E.AppID
    WHERE E.OccurredAt < @plots_cutoff
) H
ORDER BY H.OccurredAt, H.EventID;

-- ---------------------------------------------------------
-- Resources
-- ---------------------------------------------------------
INSERT INTO AUDIT_LOG (OccurredAt, ActorType, ActorID, ActorName, Module, Action, TargetType, TargetID, TargetName, Summary, AfterData)
SELECT
    E.OccurredAt,
    CASE E.ActorType WHEN 'customer' THEN 'gardener' ELSE 'coordinator' END,
    CASE E.ActorType WHEN 'customer' THEN E.GardenerID ELSE E.CoordID END,
    E.ActorName, 'resources',
    CASE
        WHEN E.EventType = 'Added' AND E.GardenerID IS NOT NULL THEN 'donation_accepted'
        WHEN E.EventType = 'Added' THEN 'resource_added'
        WHEN E.EventType = 'Borrowed' THEN 'resource_request_approved'
        WHEN E.EventType = 'Returned' THEN 'resource_returned'
        ELSE 'resource_return_requested'
    END,
    'resource', E.ResourceID, R.Name,
    CASE
        WHEN E.EventType = 'Added' AND E.GardenerID IS NOT NULL
            THEN CONCAT(E.ActorName, ' accepted ', E.GardenerName, IF(RIGHT(E.GardenerName, 1) = 's', '''', '''s'), ' donation of ', E.Qty, 'x ', R.Name, '.')
        WHEN E.EventType = 'Added' THEN CONCAT(E.ActorName, ' added ', E.Qty, 'x ', R.Name, ' to the shared stock.')
        WHEN E.EventType = 'Borrowed' THEN CONCAT(E.GardenerName, ' was lent ', E.Qty, 'x ', R.Name, '.')
        WHEN E.EventType = 'Returned' THEN CONCAT(E.GardenerName, ' returned ', E.Qty, 'x ', R.Name, '.')
        ELSE CONCAT(E.ActorName, ' asked ', E.GardenerName, ' to return ', E.Qty, 'x ', R.Name, '.')
    END,
    '{"Source":"Copied from the older resource history"}'
FROM RESOURCE_EVENT E
JOIN RESOURCE R ON R.ResourceID = E.ResourceID
WHERE E.OccurredAt < @resources_cutoff
ORDER BY E.OccurredAt, E.EventID;

-- ---------------------------------------------------------
-- Crop maintenance
-- ---------------------------------------------------------
INSERT INTO AUDIT_LOG (OccurredAt, ActorType, ActorID, ActorName, Module, Action, TargetType, TargetID, TargetName, Summary, Reason, AfterData)
SELECT
    L.LoggedAt, 'gardener', L.GardenerID, G.Name, 'crops',
    IF(L.MaintenanceNotes LIKE 'Crop status changed from %', 'crop_status_changed', 'maintenance_logged'),
    'crop', L.GardenPlotID, L.CropName,
    IF(L.MaintenanceNotes LIKE 'Crop status changed from %',
       CONCAT(G.Name, ' changed the status of their ', L.CropName, '.'),
       CONCAT(G.Name, ' logged maintenance for their ', L.CropName,
              IF(L.HarvestYield IS NOT NULL AND L.HarvestYield <> '', CONCAT(' (yield: ', L.HarvestYield, ').'), '.'))),
    NULLIF(L.MaintenanceNotes, ''),
    '{"Source":"Copied from the older crop log"}'
FROM CROP_LOG L
JOIN COMMUNITY_GARDENER G ON G.GardenerID = L.GardenerID
WHERE L.LoggedAt < @crops_cutoff
ORDER BY L.LoggedAt, L.LogID;
