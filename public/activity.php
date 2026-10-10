<?php
/**
 * activity.php
 * Read-only views for administrators:
 *   - User Activity list and profile pages (admin checklist section 3)
 *   - Garden Overview: every plot, resource, request, listing and
 *     registration in one place (section 4)
 * Nothing in this file changes data.
 *
 * A "person" is one row per human: a gardener account (together with their
 * coordinator role, if they have one), a coordinator-only account, or an
 * administrator. Sign-in numbers come from AUDIT_LOG, so they only go back to
 * when the audit log was added.
 */

require_once __DIR__ . '/roles.php';

// A return request that is this many days old without the item coming back is "overdue".
const RETURN_OVERDUE_DAYS = 7;

const ACTIVE_STATUS_SQL = "COALESCE(NULLIF(Status, ''), 'Active')";

/** The date of the first audit log entry (when sign-in tracking began), or null. */
function auditTrackingSince(PDO $pdo): ?string {
    return $pdo->query('SELECT MIN(OccurredAt) FROM AUDIT_LOG')->fetchColumn() ?: null;
}

/**
 * Everyone, one row per person, with sign-in numbers:
 * key, type, id, name, email, location, status, roles[], accounts[[type, id]],
 * gardener_id, coord_id, admin_id, failed_now, last_login, login_count, failed_total, last_failed.
 */
function activityPeople(PDO $pdo): array {
    $status = ACTIVE_STATUS_SQL;
    $people = [];

    $gardeners = $pdo->query("
        SELECT G.GardenerID, G.Name, G.Email, G.Location, COALESCE(NULLIF(G.Status, ''), 'Active') AS Status, G.FailedLoginAttempts,
               C.CoordID, COALESCE(NULLIF(C.Status, ''), 'Active') AS CoordStatus, C.FailedLoginAttempts AS CoordFailed
        FROM COMMUNITY_GARDENER G
        LEFT JOIN GARDEN_COORDINATOR C ON C.GardenerID = G.GardenerID
        ORDER BY G.Name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($gardeners as $g) {
        $key = 'gardener-' . $g['GardenerID'];
        $people[$key] ??= [
            'key' => $key, 'type' => 'gardener', 'id' => (int) $g['GardenerID'],
            'name' => $g['Name'], 'email' => $g['Email'], 'location' => $g['Location'] ?: 'Not provided',
            'status' => $g['Status'], 'roles' => ['Gardener'],
            'accounts' => [['gardener', (int) $g['GardenerID']]],
            'gardener_id' => (int) $g['GardenerID'], 'coord_id' => null, 'admin_id' => null,
            'coord_status' => null, 'failed_now' => (int) $g['FailedLoginAttempts'],
        ];
        if ($g['CoordID'] !== null) {
            $people[$key]['coord_id'] = (int) $g['CoordID'];
            $people[$key]['coord_status'] = $g['CoordStatus'];
            $people[$key]['accounts'][] = ['coordinator', (int) $g['CoordID']];
            $people[$key]['failed_now'] = max($people[$key]['failed_now'], (int) $g['CoordFailed']);
            if (in_array($g['CoordStatus'], ['Active', 'Disabled'], true)) $people[$key]['roles'][] = 'Coordinator';
        }
    }

    foreach ($pdo->query("SELECT CoordID, Name, Email, Location, $status AS Status, FailedLoginAttempts FROM GARDEN_COORDINATOR WHERE GardenerID IS NULL ORDER BY Name") as $c) {
        $key = 'coordinator-' . $c['CoordID'];
        $people[$key] = [
            'key' => $key, 'type' => 'coordinator', 'id' => (int) $c['CoordID'],
            'name' => $c['Name'], 'email' => $c['Email'], 'location' => $c['Location'] ?: 'Not provided',
            'status' => $c['Status'], 'roles' => in_array($c['Status'], ['Active', 'Disabled'], true) ? ['Coordinator'] : [],
            'accounts' => [['coordinator', (int) $c['CoordID']]],
            'gardener_id' => null, 'coord_id' => (int) $c['CoordID'], 'admin_id' => null,
            'coord_status' => $c['Status'], 'failed_now' => (int) $c['FailedLoginAttempts'],
        ];
    }

    foreach ($pdo->query("SELECT AdminID, Name, Email, Location, $status AS Status, FailedLoginAttempts FROM SYSTEM_ADMINISTRATOR ORDER BY Name") as $a) {
        $key = 'admin-' . $a['AdminID'];
        $people[$key] = [
            'key' => $key, 'type' => 'admin', 'id' => (int) $a['AdminID'],
            'name' => $a['Name'], 'email' => $a['Email'], 'location' => $a['Location'] ?: 'Not provided',
            'status' => $a['Status'], 'roles' => ['Administrator'],
            'accounts' => [['admin', (int) $a['AdminID']]],
            'gardener_id' => null, 'coord_id' => null, 'admin_id' => (int) $a['AdminID'],
            'coord_status' => null, 'failed_now' => (int) $a['FailedLoginAttempts'],
        ];
    }

    // Custom roles (a dual-role person's live on their gardener account)
    foreach ($pdo->query('SELECT UR.AccountType, UR.AccountID, R.Name FROM USER_ROLE UR JOIN ROLE R ON R.RoleID = UR.RoleID ORDER BY R.Name') as $row) {
        $key = $row['AccountType'] . '-' . $row['AccountID'];
        if (isset($people[$key])) $people[$key]['roles'][] = $row['Name'];
    }

    // Sign-in numbers from the audit log, added up across a person's accounts
    $logins = [];
    foreach ($pdo->query("SELECT ActorType, ActorID, COUNT(*) AS n, MAX(OccurredAt) AS last FROM AUDIT_LOG WHERE Action = 'login' AND ActorID IS NOT NULL GROUP BY ActorType, ActorID") as $row) {
        $logins[$row['ActorType'] . '-' . $row['ActorID']] = $row;
    }
    $failed = [];
    foreach ($pdo->query("SELECT TargetType, TargetID, COUNT(*) AS n, MAX(OccurredAt) AS last FROM AUDIT_LOG WHERE Action = 'login_failed' AND TargetID IS NOT NULL GROUP BY TargetType, TargetID") as $row) {
        $failed[$row['TargetType'] . '-' . $row['TargetID']] = $row;
    }
    foreach ($people as &$person) {
        $person['login_count'] = 0;
        $person['last_login'] = null;
        $person['failed_total'] = 0;
        $person['last_failed'] = null;
        foreach ($person['accounts'] as [$type, $id]) {
            $k = "$type-$id";
            if (isset($logins[$k])) {
                $person['login_count'] += (int) $logins[$k]['n'];
                $person['last_login'] = max((string) $person['last_login'], $logins[$k]['last']);
            }
            if (isset($failed[$k])) {
                $person['failed_total'] += (int) $failed[$k]['n'];
                $person['last_failed'] = max((string) $person['last_failed'], $failed[$k]['last']);
            }
        }
    }
    unset($person);

    return array_values($people);
}

/** The person an account belongs to (a coordinator ID finds its linked gardener too), or null. */
function findActivityPerson(PDO $pdo, string $type, int $id): ?array {
    foreach (activityPeople($pdo) as $person) {
        if (in_array([$type, $id], $person['accounts'], true)) return $person;
    }
    return null;
}

/** SQL "(Type = ? AND ID = ?) OR ..." for a person's accounts, with its parameters. */
function accountsCondition(array $accounts, string $typeColumn, string $idColumn): array {
    $parts = [];
    $params = [];
    foreach ($accounts as [$type, $id]) {
        $parts[] = "($typeColumn = ? AND $idColumn = ?)";
        array_push($params, $type, $id);
    }
    return ['(' . implode(' OR ', $parts) . ')', $params];
}

/** Everything the profile page shows for one person. */
function userProfile(PDO $pdo, array $person): array {
    $one = static function (string $sql, array $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };
    $all = static function (string $sql, array $params) use ($pdo): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };
    $value = static function (string $sql, array $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    };

    $g = $person['gardener_id'];
    $c = $person['coord_id'];
    [$table, $key] = ROLE_ACCOUNT_TABLES[$person['type']];
    $details = $one("SELECT Age, Location FROM $table WHERE $key = ?", [$person['id']]) ?? [];
    $coordinator = $c !== null ? $one("SELECT Shift, COALESCE(NULLIF(Status, ''), 'Active') AS Status FROM GARDEN_COORDINATOR WHERE CoordID = ?", [$c]) : null;

    [$historyWhere, $historyParams] = accountsCondition($person['accounts'], 'AccountType', 'AccountID');
    $roleHistory = $all("SELECT RoleName, ChangeType, ReasonCategory, ReasonDetails, ChangedByName, ChangedAt FROM ROLE_HISTORY WHERE $historyWhere ORDER BY ChangedAt DESC, HistoryID DESC", $historyParams);

    $summary = [];
    $crops = [];
    $maintenance = [];
    if ($g !== null) {
        $count = static fn (string $sql) => (int) $value($sql, [$g]);
        $summary['plots'] = array_column($all("SELECT Label FROM PLOT WHERE GardenerID = ? ORDER BY Label", [$g]), 'Label');
        $summary['crops_growing'] = $count("SELECT COUNT(*) FROM GARDEN_PLOTS WHERE GardenerID = ? AND Status NOT IN ('Harvested', 'Failed')");
        $summary['crops_total'] = $count('SELECT COUNT(*) FROM GARDEN_PLOTS WHERE GardenerID = ?');
        $summary['maintenance_entries'] = $count('SELECT COUNT(*) FROM CROP_LOG WHERE GardenerID = ?');
        $summary['items_borrowed'] = $count("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_TXN WHERE GardenerID = ? AND Status IN ('Approved', 'Return Requested')");
        $summary['listings_active'] = $count("SELECT COUNT(*) FROM EXCHANGE_BOARD WHERE GardenerID = ? AND Status = 'Active'");
        $summary['listings_total'] = $count('SELECT COUNT(*) FROM EXCHANGE_BOARD WHERE GardenerID = ?');
        $summary['claims_made'] = $count('SELECT COUNT(*) FROM EXCHANGE_CLAIMS WHERE RequesterID = ?');
        $crops = $all("SELECT CropName, PlantedDate, EstHarvestDate, Status, Notes FROM GARDEN_PLOTS WHERE GardenerID = ? ORDER BY PlantedDate DESC, PlotID DESC", [$g]);
        $maintenance = $all("
            SELECT L.LoggedAt, L.CropName, L.MaintenanceNotes, L.HarvestYield, P.Label AS PlotLabel
            FROM CROP_LOG L LEFT JOIN PLOT P ON P.PltID = L.PltID
            WHERE L.GardenerID = ? ORDER BY L.LoggedAt DESC, L.LogID DESC LIMIT 100
        ", [$g]);
    }
    if ($c !== null) {
        $summary['plot_requests_decided'] = (int) $value("SELECT COUNT(*) FROM PLOT_APPLICATION WHERE CoordID = ? AND Status <> 'Pending'", [$c]);
        $summary['resource_requests_decided'] = (int) $value("SELECT COUNT(*) FROM RESOURCE_TXN WHERE CoordID = ? AND Status NOT IN ('Requested', 'Cancelled')", [$c]);
    }

    return [
        'person' => $person,
        'details' => [
            'age' => $details['Age'] ?? null,
            'member_since' => $value("SELECT ReviewedAt FROM SIGNUP_REQUEST WHERE Email = ? AND Status = 'Approved' ORDER BY ReviewedAt DESC LIMIT 1", [$person['email']]) ?: null,
            'coordinator' => $coordinator,
        ],
        'role_history' => $roleHistory,
        'summary' => $summary,
        'crops' => $crops,
        'maintenance' => $maintenance,
        'requests' => $g !== null ? overviewRequests($pdo, ['plot', 'resource', 'coordinator', 'crop'], $g) : [],
        'timeline' => userTimeline($pdo, $person),
        'tracking_since' => auditTrackingSince($pdo),
    ];
}

/** Newest-first list of what a person did or what happened to them (max 200 items). */
function userTimeline(PDO $pdo, array $person): array {
    $parts = [];
    $params = [];

    [$actorWhere, $actorParams] = accountsCondition($person['accounts'], 'ActorType', 'ActorID');
    [$targetWhere, $targetParams] = accountsCondition($person['accounts'], 'TargetType', 'TargetID');
    $parts[] = "SELECT OccurredAt, 'audit' AS Source, Module AS Area, Action, Summary AS Text, Reason AS Detail FROM AUDIT_LOG WHERE $actorWhere OR $targetWhere";
    array_push($params, ...$actorParams, ...$targetParams);

    $g = $person['gardener_id'] ?? -1;
    $c = $person['coord_id'] ?? -1;
    $parts[] = "SELECT E.OccurredAt, 'plot', 'plots', E.EventType,
                       CONCAT(E.PlotLabel, ': ', E.EventType, CASE WHEN E.ActorName IS NOT NULL THEN CONCAT(' (by ', E.ActorName, ')') ELSE '' END),
                       COALESCE(A.RejectionReason, A.RequestReason)
                FROM PLOT_EVENT E LEFT JOIN PLOT_APPLICATION A ON A.AppID = E.AppID
                WHERE E.GardenerID = ? OR E.CoordID = ?";
    array_push($params, $g, $c);
    $parts[] = "SELECT E.OccurredAt, 'resource', 'resources', E.EventType,
                       CONCAT(E.EventType, ': ', E.Qty, 'x ', R.Name, CASE WHEN E.GardenerName IS NOT NULL AND E.GardenerID <> ? THEN CONCAT(' for ', E.GardenerName) ELSE '' END,
                              CASE WHEN E.ActorName IS NOT NULL THEN CONCAT(' (by ', E.ActorName, ')') ELSE '' END),
                       E.PlotLabel
                FROM RESOURCE_EVENT E JOIN RESOURCE R ON R.ResourceID = E.ResourceID
                WHERE E.GardenerID = ? OR E.CoordID = ?";
    array_push($params, $g, $g, $c);
    $parts[] = "SELECT L.LoggedAt, 'crop', 'crops', 'maintenance_logged',
                       CONCAT('Logged maintenance for ', L.CropName),
                       CONCAT_WS(' · ', NULLIF(L.MaintenanceNotes, ''), CASE WHEN L.HarvestYield <> '' THEN CONCAT('Yield: ', L.HarvestYield) END)
                FROM CROP_LOG L WHERE L.GardenerID = ?";
    $params[] = $g;

    $stmt = $pdo->prepare('SELECT * FROM (' . implode("\nUNION ALL\n", $parts) . ') T ORDER BY OccurredAt DESC LIMIT 200');
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Garden Overview (section 4) ----------

/** Every plot with who holds it, since when, and how many requests are waiting. */
function overviewPlots(PDO $pdo): array {
    return $pdo->query("
        SELECT P.PltID, P.Label, COALESCE(NULLIF(P.Location, ''), 'Not set') AS Location, P.AreaSqM, P.Status,
               P.GardenerID, G.Name AS GardenerName,
               (SELECT MAX(E.OccurredAt) FROM PLOT_EVENT E WHERE E.PltID = P.PltID AND E.GardenerID = P.GardenerID AND E.EventType = 'Request Accepted') AS HeldSince,
               (SELECT COUNT(*) FROM PLOT_APPLICATION A WHERE A.PltID = P.PltID AND A.Status = 'Pending') AS PendingRequests,
               (SELECT COUNT(*) FROM PLOT_APPLICATION A WHERE A.PltID = P.PltID) AS TotalRequests
        FROM PLOT P
        LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = P.GardenerID
        ORDER BY P.Label
    ")->fetchAll(PDO::FETCH_ASSOC);
}

/** Everything that happened to one plot, newest first, with the reasons given. */
function overviewPlotHistory(PDO $pdo, int $plotId): array {
    $stmt = $pdo->prepare("
        SELECT E.OccurredAt, E.EventType, E.ActorType, E.ActorName, E.GardenerName,
               A.RequestType, A.RequestReason, A.RejectionReason
        FROM PLOT_EVENT E
        LEFT JOIN PLOT_APPLICATION A ON A.AppID = E.AppID
        WHERE E.PltID = ?
        ORDER BY E.OccurredAt DESC, E.EventID DESC
    ");
    $stmt->execute([$plotId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Requests made before plot events were recorded still show up here.
    $stmt = $pdo->prepare("
        SELECT A.AppID, A.RequestType, A.Status, A.AppliedAt, A.ProcessedAt, A.RequestReason, A.RejectionReason,
               G.Name AS GardenerName, C.Name AS CoordinatorName
        FROM PLOT_APPLICATION A
        LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = A.GardenerID
        LEFT JOIN GARDEN_COORDINATOR C ON C.CoordID = A.CoordID
        WHERE A.PltID = ?
        ORDER BY A.AppliedAt DESC, A.AppID DESC
    ");
    $stmt->execute([$plotId]);
    return ['events' => $events, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/** Every resource with who is holding it, for how long, and which returns are overdue. */
function overviewResources(PDO $pdo): array {
    $resources = $pdo->query('SELECT ResourceID, Name, TotalQty, AvailableQty FROM RESOURCE ORDER BY Name')->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("
        SELECT T.TxnID, T.ResourceID, T.GardenerID, G.Name AS GardenerName, T.Qty, T.Status, P.Label AS PlotLabel,
               T.ApprovedAt, T.ReturnRequestedAt,
               DATEDIFF(NOW(), T.ApprovedAt) AS DaysHeld,
               CASE WHEN T.Status = 'Return Requested' AND T.ReturnRequestedAt IS NOT NULL
                    THEN DATEDIFF(NOW(), T.ReturnRequestedAt) END AS DaysSinceReturnRequest
        FROM RESOURCE_TXN T
        JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
        LEFT JOIN PLOT P ON P.PltID = T.PltID
        WHERE T.Status IN ('Approved', 'Return Requested')
        ORDER BY T.ApprovedAt
    ");
    $stmt->execute();
    $holders = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['Overdue'] = $row['DaysSinceReturnRequest'] !== null && (int) $row['DaysSinceReturnRequest'] >= RETURN_OVERDUE_DAYS;
        $holders[(int) $row['ResourceID']][] = $row;
    }
    foreach ($resources as &$resource) {
        $resource['Holders'] = $holders[(int) $resource['ResourceID']] ?? [];
    }
    unset($resource);
    return ['resources' => $resources, 'overdue_days' => RETURN_OVERDUE_DAYS];
}

/**
 * Plot, resource, coordinator and crop catalog requests in every status, with
 * reasons, newest first. $kinds limits the types; $gardenerId limits it to one person.
 * Outcome is one of Pending, Approved, Rejected, Cancelled.
 */
function overviewRequests(PDO $pdo, array $kinds, ?int $gardenerId = null): array {
    $parts = [];
    $params = [];
    $who = static function (string $column) use ($gardenerId, &$params): string {
        if ($gardenerId === null) return '';
        $params[] = $gardenerId;
        return " AND $column = ?";
    };
    $outcome = static fn (string $column) => "CASE
        WHEN $column IN ('Pending', 'Requested') THEN 'Pending'
        WHEN $column IN ('Approved', 'Return Requested', 'Returned', 'Donated', 'Accepted') THEN 'Approved'
        WHEN $column = 'Cancelled' THEN 'Cancelled'
        ELSE $column END";

    if (in_array('plot', $kinds, true)) {
        $parts[] = "SELECT 'plot' AS Kind, A.AppID AS RequestID, A.GardenerID, G.Name AS Who,
                CASE A.RequestType WHEN 'Unassign' THEN CONCAT('Give up ', P.Label)
                                   WHEN 'Return' THEN CONCAT('Asked to return ', P.Label)
                                   ELSE CONCAT('Be given ', P.Label) END AS What,
                A.Status, {$outcome('A.Status')} AS Outcome, A.AppliedAt AS RequestedAt, A.ProcessedAt AS DecidedAt,
                COALESCE(C.Name, (SELECT E.ActorName FROM PLOT_EVENT E WHERE E.AppID = A.AppID AND E.EventType IN ('Request Accepted', 'Request Rejected', 'Plot Unassigned') ORDER BY E.EventID DESC LIMIT 1)) AS DecidedBy,
                A.RequestReason AS Notes, A.RejectionReason AS Reason
            FROM PLOT_APPLICATION A
            JOIN COMMUNITY_GARDENER G ON G.GardenerID = A.GardenerID
            LEFT JOIN PLOT P ON P.PltID = A.PltID
            LEFT JOIN GARDEN_COORDINATOR C ON C.CoordID = A.CoordID
            WHERE 1 = 1" . $who('A.GardenerID');
    }
    if (in_array('resource', $kinds, true)) {
        $parts[] = "SELECT 'resource' AS Kind, T.TxnID AS RequestID, T.GardenerID, G.Name AS Who,
                CONCAT(CASE WHEN T.RequestType = 'Donation' THEN 'Donate ' ELSE 'Borrow ' END, T.Qty, 'x ', R.Name) AS What,
                T.Status, {$outcome('T.Status')} AS Outcome, T.RequestedAt, T.ApprovedAt AS DecidedAt, C.Name AS DecidedBy,
                T.RequestNotes AS Notes, T.RejectionReason AS Reason
            FROM RESOURCE_TXN T
            JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
            JOIN RESOURCE R ON R.ResourceID = T.ResourceID
            LEFT JOIN GARDEN_COORDINATOR C ON C.CoordID = T.CoordID
            WHERE T.RequestType IN ('Borrow', 'Donation')" . $who('T.GardenerID');
    }
    if (in_array('coordinator', $kinds, true)) {
        $parts[] = "SELECT 'coordinator' AS Kind, A.ApplicationID AS RequestID, A.GardenerID, G.Name AS Who,
                CONCAT('Become a coordinator (', A.Shift, ' shift)') AS What,
                A.Status, {$outcome('A.Status')} AS Outcome, A.RequestedAt, A.ReviewedAt AS DecidedAt, S.Name AS DecidedBy,
                A.Motivation AS Notes, A.RejectionReason AS Reason
            FROM COORDINATOR_APPLICATION A
            JOIN COMMUNITY_GARDENER G ON G.GardenerID = A.GardenerID
            LEFT JOIN SYSTEM_ADMINISTRATOR S ON S.AdminID = A.ReviewedBy
            WHERE 1 = 1" . $who('A.GardenerID');
    }
    if (in_array('crop', $kinds, true)) {
        $parts[] = "SELECT 'crop' AS Kind, Q.RequestID AS RequestID, Q.GardenerID, G.Name AS Who,
                CONCAT('Add ', Q.CropName, ' to the crop catalog') AS What,
                Q.Status, {$outcome('Q.Status')} AS Outcome, Q.RequestedAt, Q.ReviewedAt AS DecidedAt, C.Name AS DecidedBy,
                Q.Notes AS Notes, Q.ReviewReason AS Reason
            FROM CROP_CATALOG_REQUEST Q
            JOIN COMMUNITY_GARDENER G ON G.GardenerID = Q.GardenerID
            LEFT JOIN GARDEN_COORDINATOR C ON C.CoordID = Q.ReviewedBy
            WHERE 1 = 1" . $who('Q.GardenerID');
    }
    if (!$parts) return [];

    $stmt = $pdo->prepare('SELECT * FROM (' . implode("\nUNION ALL\n", $parts) . ') R ORDER BY RequestedAt DESC LIMIT 1000');
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Every exchange listing with the claims made on it. */
function overviewExchange(PDO $pdo): array {
    $listings = $pdo->query("
        SELECT B.PostID, B.GardenerID, G.Name AS GardenerName, B.ProduceName, B.Qty, B.Description, B.Type, B.Status, B.CreatedAt
        FROM EXCHANGE_BOARD B
        LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = B.GardenerID
        ORDER BY B.CreatedAt DESC, B.PostID DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $claims = [];
    foreach ($pdo->query("
        SELECT C.ClaimID, C.PostID, C.RequesterID, G.Name AS RequesterName, C.QtyWanted, C.PickupDetails, C.Status, C.CreatedAt
        FROM EXCHANGE_CLAIMS C
        LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = C.RequesterID
        ORDER BY C.CreatedAt DESC
    ") as $claim) {
        $claims[(int) $claim['PostID']][] = $claim;
    }
    foreach ($listings as &$listing) {
        $listing['Claims'] = $claims[(int) $listing['PostID']] ?? [];
    }
    unset($listing);
    return $listings;
}

/** Every sign-up request, including rejected ones and their reasons (never the password or status link). */
function overviewRegistrations(PDO $pdo): array {
    return $pdo->query("
        SELECT S.RequestID, CONCAT(S.FirstName, ' ', S.LastName) AS Name, S.Email, S.Age, S.Location, S.Role,
               S.Status, S.RequestedAt, S.ReviewedAt, A.Name AS ReviewedBy, S.RejectionReason
        FROM SIGNUP_REQUEST S
        LEFT JOIN SYSTEM_ADMINISTRATOR A ON A.AdminID = S.ReviewedBy
        ORDER BY S.RequestedAt DESC, S.RequestID DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
