<?php
/**
 * dashboard_data.php
 * Numbers behind the admin dashboard (admin checklist section 1). Read-only.
 *
 * Everything is for one date range (from/to, inclusive). Time charts are
 * bucketed by day, or by week (starting Monday) for ranges over 60 days.
 * "Right now" figures (stock levels, crops by status, locations) ignore the range.
 */

require_once __DIR__ . '/activity.php'; // RETURN_OVERDUE_DAYS

const DASHBOARD_MAX_DAYS = 366;

/** Validates a from/to pair (Y-m-d). Defaults to the last 30 days. */
function dashboardRange(PDO $pdo, ?string $from, ?string $to): array {
    $today = new DateTimeImmutable($pdo->query('SELECT CURDATE()')->fetchColumn());
    $parse = static function (?string $value): ?DateTimeImmutable {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    };
    $end = $parse($to) ?? $today;
    $start = $parse($from) ?? $end->modify('-29 days');
    if ($start > $end) [$start, $end] = [$end, $start];
    if ($start->diff($end)->days >= DASHBOARD_MAX_DAYS) $start = $end->modify('-' . (DASHBOARD_MAX_DAYS - 1) . ' days');

    $days = $start->diff($end)->days + 1;
    $bucket = $days > 60 ? 'week' : 'day';
    $keys = [];
    $cursor = $bucket === 'week' ? $start->modify('-' . ((int) $start->format('N') - 1) . ' days') : $start;
    while ($cursor <= $end) {
        $keys[] = $cursor->format('Y-m-d');
        $cursor = $cursor->modify($bucket === 'week' ? '+7 days' : '+1 day');
    }
    return [
        'from' => $start->format('Y-m-d'),
        'to' => $end->format('Y-m-d'),
        'end_exclusive' => $end->modify('+1 day')->format('Y-m-d'),
        'days' => $days,
        'bucket' => $bucket,
        'keys' => $keys,
    ];
}

/** SQL that turns a datetime column into its bucket key. */
function bucketSql(string $column, string $bucket): string {
    return $bucket === 'week' ? "DATE(DATE_SUB($column, INTERVAL WEEKDAY($column) DAY))" : "DATE($column)";
}

/** Runs a "bucket, value" query and returns one number per bucket key, zeros filled in. */
function bucketSeries(PDO $pdo, string $sql, array $params, array $keys): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $found = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return array_map(static fn ($key) => (int) ($found[$key] ?? 0), $keys);
}

function dashboardData(PDO $pdo, array $r): array {
    $b = $r['bucket'];
    $in = static fn (string $column) => "$column >= ? AND $column < ?";
    $window = [$r['from'], $r['end_exclusive']];
    $count = static function (string $sql, array $params = []) use ($pdo): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    };
    $rows = static function (string $sql, array $params = []) use ($pdo): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };
    $active = "COALESCE(NULLIF(Status, ''), 'Active')";

    // ---------- Headline numbers ----------
    $plotsTotal = $count('SELECT COUNT(*) FROM PLOT');
    $plotsInUse = $count("SELECT COUNT(*) FROM PLOT WHERE Status = 'Occupied'");
    $locked = $count("SELECT (SELECT COUNT(*) FROM COMMUNITY_GARDENER WHERE Status = 'Disabled') + (SELECT COUNT(*) FROM GARDEN_COORDINATOR WHERE Status = 'Disabled') + (SELECT COUNT(*) FROM SYSTEM_ADMINISTRATOR WHERE Status = 'Disabled')");
    $overdue = $count("SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Return Requested' AND ReturnRequestedAt IS NOT NULL AND DATEDIFF(NOW(), ReturnRequestedAt) >= " . RETURN_OVERDUE_DAYS);
    $kpis = [
        'gardeners' => $count("SELECT COUNT(*) FROM COMMUNITY_GARDENER WHERE $active = 'Active'"),
        'coordinators' => $count("SELECT COUNT(*) FROM GARDEN_COORDINATOR WHERE $active = 'Active'"),
        'plots_in_use' => $plotsInUse,
        'plots_total' => $plotsTotal,
        'items_on_loan' => $count("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_TXN WHERE Status IN ('Approved', 'Return Requested')"),
        'active_listings' => $count("SELECT COUNT(*) FROM EXCHANGE_BOARD WHERE Status = 'Active'"),
        'trades_in_range' => $count("SELECT COUNT(*) FROM EXCHANGE_CLAIMS WHERE Status = 'Accepted' AND " . $in('CreatedAt'), $window),
    ];

    // ---------- Needs attention (right now) ----------
    $attention = [
        ['key' => 'plot_requests', 'label' => 'Plot requests waiting for a decision', 'count' => $count("SELECT COUNT(*) FROM PLOT_APPLICATION WHERE Status = 'Pending' AND RequestType IN ('Apply', 'Unassign')"), 'link' => 'staff_plots.php', 'level' => 'warning', 'permission' => 'plots.view'],
        ['key' => 'resource_requests', 'label' => 'Resource requests and donations waiting', 'count' => $count("SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Requested'"), 'link' => 'staff_inventory.php', 'level' => 'warning', 'permission' => 'resources.view'],
        ['key' => 'signups', 'label' => 'Sign-ups waiting for approval', 'count' => $count("SELECT COUNT(*) FROM SIGNUP_REQUEST WHERE Status = 'Pending'"), 'link' => 'admin_manage_gardeners.php', 'level' => 'warning', 'permission' => 'registrations.review'],
        ['key' => 'coordinator_applications', 'label' => 'Coordinator applications waiting', 'count' => $count("SELECT COUNT(*) FROM COORDINATOR_APPLICATION WHERE Status = 'Pending'"), 'link' => 'admin_manage_coordinators.php', 'level' => 'warning', 'permission' => 'coordinator_applications.review'],
        ['key' => 'crop_requests', 'label' => 'Crop catalog requests waiting', 'count' => $count("SELECT COUNT(*) FROM CROP_CATALOG_REQUEST WHERE Status = 'Pending'"), 'link' => 'staff_inventory.php', 'level' => 'warning', 'permission' => 'crops.review_catalog'],
        ['key' => 'overdue', 'label' => 'Overdue returns (asked ' . RETURN_OVERDUE_DAYS . '+ days ago)', 'count' => $overdue, 'link' => 'admin_overview.php#resources', 'level' => 'serious', 'permission' => 'overview.view'],
        ['key' => 'locked', 'label' => 'Locked accounts (too many wrong passwords)', 'count' => $locked, 'link' => 'admin_activity.php', 'level' => 'serious', 'permission' => 'accounts.view'],
    ];

    // ---------- Plots: occupancy over time, rebuilt backwards from today ----------
    // Each bucket shows the state at the END of that bucket: today's numbers
    // minus whatever happened after it.
    $plotEvents = $rows("
        SELECT E.OccurredAt,
               CASE
                   WHEN E.EventType = 'Request Accepted' AND A.RequestType = 'Apply' THEN 1
                   WHEN E.EventType = 'Request Accepted' AND A.RequestType = 'Unassign' THEN -1
                   WHEN E.EventType = 'Plot Unassigned' THEN -1
                   ELSE 0
               END AS InUseChange,
               CASE WHEN E.EventType = 'Plot Added' THEN 1 ELSE 0 END AS TotalChange
        FROM PLOT_EVENT E LEFT JOIN PLOT_APPLICATION A ON A.AppID = E.AppID
        WHERE E.OccurredAt >= ?
    ", [$r['from']]);
    foreach ($rows("SELECT OccurredAt FROM AUDIT_LOG WHERE Action = 'plot_deleted' AND OccurredAt >= ?", [$r['from']]) as $deleted) {
        $plotEvents[] = ['OccurredAt' => $deleted['OccurredAt'], 'InUseChange' => 0, 'TotalChange' => -1];
    }
    $occupancy = ['in_use' => [], 'total' => []];
    foreach (array_keys($r['keys']) as $i) {
        $bucketEnd = $r['keys'][$i + 1] ?? $r['end_exclusive'];
        $inUse = $plotsInUse;
        $total = $plotsTotal;
        foreach ($plotEvents as $event) {
            if ($event['OccurredAt'] >= $bucketEnd) {
                $inUse -= (int) $event['InUseChange'];
                $total -= (int) $event['TotalChange'];
            }
        }
        $occupancy['in_use'][] = max(0, $inUse);
        $occupancy['total'][] = max(0, $total);
    }

    // ---------- Resources ----------
    $levels = $rows("
        SELECT R.Name,
               COALESCE(SUM(CASE WHEN T.Status IN ('Approved', 'Return Requested') THEN T.Qty END), 0) AS Borrowed,
               R.TotalQty
        FROM RESOURCE R LEFT JOIN RESOURCE_TXN T ON T.ResourceID = R.ResourceID
        GROUP BY R.ResourceID, R.Name, R.TotalQty
        ORDER BY R.Name
    ");
    $levels = array_map(static fn ($l) => ['name' => $l['Name'], 'borrowed' => (int) $l['Borrowed'], 'on_shelf' => max(0, (int) $l['TotalQty'] - (int) $l['Borrowed'])], $levels);
    $mostBorrowed = array_map(static fn ($m) => ['name' => $m['Name'], 'qty' => (int) $m['Qty']], $rows("
        SELECT R.Name, SUM(E.Qty) AS Qty FROM RESOURCE_EVENT E JOIN RESOURCE R ON R.ResourceID = E.ResourceID
        WHERE E.EventType = 'Borrowed' AND " . $in('E.OccurredAt') . "
        GROUP BY R.ResourceID, R.Name ORDER BY Qty DESC, R.Name LIMIT 6
    ", $window));
    $held = $rows("
        SELECT CASE WHEN DATEDIFF(NOW(), ApprovedAt) <= 7 THEN 0 WHEN DATEDIFF(NOW(), ApprovedAt) <= 30 THEN 1
                    WHEN DATEDIFF(NOW(), ApprovedAt) <= 60 THEN 2 ELSE 3 END AS Bucket,
               SUM(Qty) AS Qty
        FROM RESOURCE_TXN WHERE Status IN ('Approved', 'Return Requested') AND ApprovedAt IS NOT NULL
        GROUP BY Bucket
    ");
    $heldBuckets = [0, 0, 0, 0];
    foreach ($held as $h) $heldBuckets[(int) $h['Bucket']] = (int) $h['Qty'];

    // ---------- People ----------
    $newUsers = bucketSeries($pdo, "
        SELECT K, COUNT(*) FROM (
            SELECT " . bucketSql('ReviewedAt', $b) . " AS K FROM SIGNUP_REQUEST WHERE Status = 'Approved' AND " . $in('ReviewedAt') . "
            UNION ALL
            SELECT " . bucketSql('OccurredAt', $b) . " FROM AUDIT_LOG WHERE Action = 'admin_created' AND " . $in('OccurredAt') . "
        ) N GROUP BY K
    ", [...$window, ...$window], $r['keys']);
    $registrations = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
    foreach ($rows("SELECT Status, COUNT(*) AS N FROM SIGNUP_REQUEST WHERE " . $in('RequestedAt') . " GROUP BY Status", $window) as $row) {
        $registrations[$row['Status']] = (int) $row['N'];
    }
    $locations = $rows("
        SELECT COALESCE(NULLIF(Location, ''), 'Not provided') AS Location, COUNT(*) AS N
        FROM COMMUNITY_GARDENER WHERE $active IN ('Active', 'Disabled')
        GROUP BY Location ORDER BY N DESC, Location
    ");
    $byLocation = array_slice($locations, 0, 7);
    $otherLocations = array_sum(array_column(array_slice($locations, 7), 'N'));
    $byLocation = array_map(static fn ($l) => ['location' => $l['Location'], 'count' => (int) $l['N']], $byLocation);
    if ($otherLocations > 0) $byLocation[] = ['location' => 'Other places', 'count' => (int) $otherLocations];

    $activeUsers = bucketSeries($pdo, "
        SELECT " . bucketSql('OccurredAt', $b) . " AS K, COUNT(DISTINCT CONCAT(ActorType, '-', ActorID))
        FROM AUDIT_LOG WHERE ActorType IN ('gardener', 'coordinator', 'admin') AND ActorID IS NOT NULL
          AND (AfterData IS NULL OR AfterData NOT LIKE '%Copied from%') AND " . $in('OccurredAt') . "
        GROUP BY K
    ", $window, $r['keys']);
    $failedLogins = bucketSeries($pdo, "
        SELECT " . bucketSql('OccurredAt', $b) . " AS K, COUNT(*) FROM AUDIT_LOG WHERE Action = 'login_failed' AND " . $in('OccurredAt') . " GROUP BY K
    ", $window, $r['keys']);

    // ---------- Requests ----------
    $requestsOverTime = [
        'plot' => bucketSeries($pdo, "SELECT " . bucketSql('AppliedAt', $b) . " AS K, COUNT(*) FROM PLOT_APPLICATION WHERE " . $in('AppliedAt') . " GROUP BY K", $window, $r['keys']),
        // Fully approved resource requests are folded into the borrower's record, so they are
        // counted on the day they were lent; every other request keeps its own row.
        'resource' => bucketSeries($pdo, "
            SELECT K, COUNT(*) FROM (
                SELECT " . bucketSql('RequestedAt', $b) . " AS K FROM RESOURCE_TXN
                WHERE RequestType IN ('Borrow', 'Donation') AND Status IN ('Requested', 'Rejected', 'Cancelled', 'Donated') AND " . $in('RequestedAt') . "
                UNION ALL
                SELECT " . bucketSql('OccurredAt', $b) . " FROM RESOURCE_EVENT WHERE EventType = 'Borrowed' AND " . $in('OccurredAt') . "
            ) Q GROUP BY K
        ", [...$window, ...$window], $r['keys']),
        'coordinator' => bucketSeries($pdo, "SELECT " . bucketSql('RequestedAt', $b) . " AS K, COUNT(*) FROM COORDINATOR_APPLICATION WHERE " . $in('RequestedAt') . " GROUP BY K", $window, $r['keys']),
    ];

    // Outcomes of decisions made in the range, and how long they took
    $outcome = static function (array $row): array {
        return [
            'approved' => (int) ($row['Approved'] ?? 0),
            'rejected' => (int) ($row['Rejected'] ?? 0),
            'cancelled' => (int) ($row['Cancelled'] ?? 0),
            'avg_hours' => isset($row['AvgMinutes']) && $row['AvgMinutes'] !== null ? round((float) $row['AvgMinutes'] / 60, 1) : null,
        ];
    };
    $outcomes = [
        'Plot requests' => $outcome($rows("
            SELECT SUM(Status = 'Approved') AS Approved, SUM(Status = 'Rejected') AS Rejected,
                   AVG(TIMESTAMPDIFF(MINUTE, AppliedAt, ProcessedAt)) AS AvgMinutes
            FROM PLOT_APPLICATION WHERE Status IN ('Approved', 'Rejected') AND RequestType IN ('Apply', 'Unassign') AND " . $in('ProcessedAt'), $window)[0]),
        'Resource requests' => $outcome([
            'Approved' => $count("SELECT COUNT(*) FROM RESOURCE_EVENT WHERE EventType = 'Borrowed' AND " . $in('OccurredAt'), $window)
                + $count("SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Donated' AND " . $in('ApprovedAt'), $window),
            'Rejected' => $count("SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Rejected' AND " . $in('RequestedAt'), $window),
            'Cancelled' => $count("SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Cancelled' AND " . $in('RequestedAt'), $window),
        ]),
        'Coordinator applications' => $outcome($rows("
            SELECT SUM(Status = 'Approved') AS Approved, SUM(Status = 'Rejected') AS Rejected,
                   AVG(TIMESTAMPDIFF(MINUTE, RequestedAt, ReviewedAt)) AS AvgMinutes
            FROM COORDINATOR_APPLICATION WHERE Status IN ('Approved', 'Rejected') AND " . $in('ReviewedAt'), $window)[0]),
        'Sign-ups' => $outcome($rows("
            SELECT SUM(Status = 'Approved') AS Approved, SUM(Status = 'Rejected') AS Rejected,
                   AVG(TIMESTAMPDIFF(MINUTE, RequestedAt, ReviewedAt)) AS AvgMinutes
            FROM SIGNUP_REQUEST WHERE Status IN ('Approved', 'Rejected') AND " . $in('ReviewedAt'), $window)[0]),
    ];

    // Most common rejection reasons (same wording counted together)
    $reasons = $rows("
        SELECT MIN(Reason) AS Reason, COUNT(*) AS N FROM (
            SELECT RejectionReason AS Reason FROM PLOT_APPLICATION WHERE Status = 'Rejected' AND RejectionReason <> '' AND " . $in('ProcessedAt') . "
            UNION ALL SELECT RejectionReason FROM RESOURCE_TXN WHERE Status = 'Rejected' AND RejectionReason <> '' AND " . $in('RequestedAt') . "
            UNION ALL SELECT RejectionReason FROM COORDINATOR_APPLICATION WHERE Status = 'Rejected' AND RejectionReason <> '' AND " . $in('ReviewedAt') . "
            UNION ALL SELECT RejectionReason FROM SIGNUP_REQUEST WHERE Status = 'Rejected' AND RejectionReason <> '' AND " . $in('ReviewedAt') . "
            UNION ALL SELECT ReviewReason FROM CROP_CATALOG_REQUEST WHERE Status = 'Rejected' AND ReviewReason <> '' AND " . $in('ReviewedAt') . "
        ) R GROUP BY LOWER(TRIM(Reason)) ORDER BY N DESC, Reason LIMIT 6
    ", [...$window, ...$window, ...$window, ...$window, ...$window]);
    $topReasons = array_map(static fn ($x) => ['reason' => trim($x['Reason']), 'count' => (int) $x['N']], $reasons);

    // Coordinator workload: decisions each coordinator made in the range
    $workload = $rows("
        SELECT C.Name,
               (SELECT COUNT(*) FROM PLOT_APPLICATION A WHERE A.CoordID = C.CoordID AND A.Status IN ('Approved', 'Rejected') AND " . $in('A.ProcessedAt') . ") AS Plots,
               (SELECT COUNT(*) FROM RESOURCE_TXN T WHERE T.CoordID = C.CoordID AND (
                    (T.Status IN ('Approved', 'Return Requested', 'Returned', 'Donated') AND " . $in('T.ApprovedAt') . ")
                    OR (T.Status = 'Rejected' AND " . $in('T.RequestedAt') . "))) AS Resources,
               (SELECT COUNT(*) FROM CROP_CATALOG_REQUEST Q WHERE Q.ReviewedBy = C.CoordID AND " . $in('Q.ReviewedAt') . ") AS Crops
        FROM GARDEN_COORDINATOR C
        ORDER BY C.Name
    ", [...$window, ...$window, ...$window, ...$window]);
    $workload = array_values(array_filter(array_map(static fn ($w) => [
        'name' => $w['Name'], 'plots' => (int) $w['Plots'], 'resources' => (int) $w['Resources'], 'crops' => (int) $w['Crops'],
    ], $workload), static fn ($w) => $w['plots'] + $w['resources'] + $w['crops'] > 0));

    // ---------- Crops ----------
    $cropStatus = ['Planted' => 0, 'Growing' => 0, 'Harvested' => 0, 'Failed' => 0];
    foreach ($rows('SELECT Status, COUNT(*) AS N FROM GARDEN_PLOTS GROUP BY Status') as $row) {
        $cropStatus[$row['Status']] = (int) $row['N'];
    }
    $harvests = bucketSeries($pdo, "
        SELECT " . bucketSql('LoggedAt', $b) . " AS K, COUNT(*) FROM CROP_LOG
        WHERE (MaintenanceNotes LIKE '%to Harvested.%' OR (HarvestYield IS NOT NULL AND HarvestYield <> '')) AND " . $in('LoggedAt') . "
        GROUP BY K
    ", $window, $r['keys']);

    return [
        'range' => ['from' => $r['from'], 'to' => $r['to'], 'days' => $r['days'], 'bucket' => $r['bucket'], 'keys' => $r['keys']],
        'tracking' => [
            'audit_since' => auditTrackingSince($pdo),
            'plot_history_since' => $pdo->query('SELECT MIN(OccurredAt) FROM PLOT_EVENT')->fetchColumn() ?: null,
        ],
        'kpis' => $kpis,
        'attention' => $attention,
        'plots' => ['occupancy' => $occupancy],
        'resources' => ['levels' => $levels, 'most_borrowed' => $mostBorrowed, 'held_buckets' => $heldBuckets, 'overdue' => $overdue, 'overdue_days' => RETURN_OVERDUE_DAYS],
        'people' => ['new_users' => $newUsers, 'registrations' => $registrations, 'by_location' => $byLocation, 'active_users' => $activeUsers, 'failed_logins' => $failedLogins, 'locked' => $locked],
        'requests' => ['over_time' => $requestsOverTime, 'outcomes' => $outcomes, 'top_reasons' => $topReasons, 'workload' => $workload],
        'crops' => ['by_status' => $cropStatus, 'harvests' => $harvests],
    ];
}
