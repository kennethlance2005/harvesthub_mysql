<?php
/**
 * reports.php
 * The Reports page (admin checklist section 7). Read-only.
 *
 * Every report is built once by reportBuild() as:
 *   title, description, period text, summary [[label, value]], tables [[title, note, columns, rows]]
 * and then shown three ways from that same data: on the Reports page (JSON),
 * as a CSV download, and as a printable page that saves as a PDF.
 */

require_once __DIR__ . '/dashboard_data.php'; // dashboardRange(), dashboardData(), activity.php, roles.php

/** The reports, in menu order: key => [title, one-line description, permission needed]. */
const REPORTS = [
    'users_roles' => ['Users and roles', 'Everyone with an account, their roles and status, and role changes in the period.', 'accounts.view'],
    'user_activity' => ['User activity', 'What each person did in the period. Pick one person to see their full history.', 'accounts.view'],
    'audit' => ['Audit', 'Every logged action in the period: who did what and when.', 'audit.view'],
    'requests' => ['Requests', 'How many requests came in, how many were approved, and how long decisions took.', 'reports.view'],
    'rejections' => ['Rejections and reasons', 'Every declined request and sign-up in the period, with the reason given.', 'reports.view'],
    'resources' => ['Resources', 'Shared stock, who is borrowing what, overdue returns, and lending in the period.', 'reports.view'],
    'plots' => ['Plot assignments', 'Who holds each plot, and every plot given out or given up in the period.', 'reports.view'],
    'exchange_harvest' => ['Exchange and harvest', 'Exchange listings and claims, and harvests logged in the period.', 'reports.view'],
    'archived' => ['Archived and demoted accounts', 'Accounts that are archived, and coordinator roles removed, with the reasons.', 'accounts.view'],
];

/** Reports this person may open. */
function availableReports(): array {
    return array_filter(REPORTS, static fn ($report) => can($report[2]));
}

/** "Oct 11, 2026 9:03 PM" (or date only), straight from the database value. */
function reportDate(?string $value, bool $withTime = true): string {
    if (!$value) return '';
    $time = strtotime($value);
    return $time ? date($withTime ? 'M j, Y g:i A' : 'M j, Y', $time) : $value;
}

function reportHours(?float $hours): string {
    if ($hours === null) return 'Not recorded';
    if ($hours < 1) return 'Under an hour';
    if ($hours < 48) return 'About ' . round($hours) . ' ' . (round($hours) == 1 ? 'hour' : 'hours');
    return 'About ' . round($hours / 24) . ' days';
}

function reportPercent(int $part, int $whole): string {
    return $whole > 0 ? round($part / $whole * 100) . '%' : '—';
}

/**
 * Builds one report. $range comes from dashboardRange(); $options may hold
 * 'person' (e.g. "gardener-4") for user_activity and 'module' for audit.
 */
function reportBuild(PDO $pdo, string $key, array $range, array $options = []): array {
    [$title, $description] = REPORTS[$key];
    $window = [$range['from'], $range['end_exclusive']];
    $in = static fn (string $column) => "$column >= ? AND $column < ?";
    $rows = static function (string $sql, array $params = []) use ($pdo): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };
    $count = static function (string $sql, array $params = []) use ($pdo): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    };
    $period = $range['from'] === $range['to']
        ? reportDate($range['from'], false)
        : reportDate($range['from'], false) . ' to ' . reportDate($range['to'], false);
    $summary = [];
    $tables = [];
    $table = static function (string $title, array $columns, array $rows, string $note = '') use (&$tables): void {
        $tables[] = ['title' => $title, 'note' => $note, 'columns' => $columns, 'rows' => array_values($rows)];
    };

    switch ($key) {
        case 'users_roles': {
            $people = activityPeople($pdo);
            $current = array_filter($people, static fn ($p) => in_array($p['status'], ['Active', 'Disabled'], true));
            $summary = [
                ['Gardeners', count(array_filter($current, static fn ($p) => $p['type'] === 'gardener'))],
                ['Coordinators', count(array_filter($current, static fn ($p) => in_array('Coordinator', $p['roles'], true)))],
                ['Administrators', count(array_filter($current, static fn ($p) => $p['type'] === 'admin'))],
                ['Locked accounts', count(array_filter($people, static fn ($p) => $p['status'] === 'Disabled'))],
                ['Archived accounts', count(array_filter($people, static fn ($p) => $p['status'] === 'Archived'))],
            ];
            $accountLabel = ['gardener' => 'Gardener', 'coordinator' => 'Coordinator', 'admin' => 'Administrator'];
            $table('Everyone', ['Name', 'Email', 'Account', 'Roles', 'Status', 'Location', 'Last signed in'], array_map(static fn ($p) => [
                $p['name'], $p['email'], $accountLabel[$p['type']], implode(', ', $p['roles']) ?: 'None', $p['status'] === 'Disabled' ? 'Locked' : $p['status'],
                $p['location'], $p['last_login'] ? reportDate($p['last_login']) : 'None recorded',
            ], $people), 'Not affected by the period: this is everyone as of today. Sign-ins are counted since the audit log started.');

            $counts = roleMemberCounts($pdo);
            $grants = $pdo->query('SELECT RoleID, COUNT(*) FROM ROLE_PERMISSION GROUP BY RoleID')->fetchAll(PDO::FETCH_KEY_PAIR);
            $totalPermissions = (int) $pdo->query('SELECT COUNT(*) FROM PERMISSION')->fetchColumn();
            $roles = $pdo->query("SELECT RoleID, Code, Name, IsBuiltIn, Description FROM ROLE ORDER BY IsBuiltIn DESC, FIELD(Code, 'admin', 'coordinator', 'gardener'), Name")->fetchAll(PDO::FETCH_ASSOC);
            $table('Roles', ['Role', 'Kind', 'People', 'Permissions', 'What it is for'], array_map(static fn ($r) => [
                $r['Name'], $r['IsBuiltIn'] ? 'Built-in' : 'Custom', $counts[(int) $r['RoleID']] ?? 0,
                $r['Code'] === 'admin' ? 'All' : ((int) ($grants[$r['RoleID']] ?? 0)) . " of $totalPermissions", $r['Description'] ?? '',
            ], $roles));

            $changes = $rows('SELECT ChangedAt, AccountName, RoleName, ChangeType, ReasonCategory, ReasonDetails, ChangedByName FROM ROLE_HISTORY WHERE ' . $in('ChangedAt') . ' ORDER BY ChangedAt DESC', $window);
            $changeLabels = ['granted' => 'Given', 'removed' => 'Taken away', 'demoted' => 'Removed'];
            $table('Role changes in this period', ['Date', 'Person', 'Role', 'Change', 'Reason', 'Changed by'], array_map(static fn ($c) => [
                reportDate($c['ChangedAt']), $c['AccountName'], $c['RoleName'], $changeLabels[$c['ChangeType']] ?? $c['ChangeType'],
                implode(': ', array_filter([$c['ReasonCategory'], $c['ReasonDetails']])) ?: '—', $c['ChangedByName'] ?? '—',
            ], $changes));
            break;
        }

        case 'user_activity': {
            $people = activityPeople($pdo);
            $personKey = (string) ($options['person'] ?? '');
            $selected = $personKey !== '' ? (array_values(array_filter($people, static fn ($p) => $p['key'] === $personKey))[0] ?? null) : null;
            $list = $selected ? [$selected] : $people;
            $out = [];
            foreach ($list as $p) {
                [$actorWhere, $actorParams] = accountsCondition($p['accounts'], 'ActorType', 'ActorID');
                [$targetWhere, $targetParams] = accountsCondition($p['accounts'], 'TargetType', 'TargetID');
                $g = $p['gardener_id'] ?? -1;
                $row = [
                    'name' => $p['name'],
                    'signins' => $count("SELECT COUNT(*) FROM AUDIT_LOG WHERE Action = 'login' AND $actorWhere AND " . $in('OccurredAt'), [...$actorParams, ...$window]),
                    'failed' => $count("SELECT COUNT(*) FROM AUDIT_LOG WHERE Action = 'login_failed' AND $targetWhere AND " . $in('OccurredAt'), [...$targetParams, ...$window]),
                    'actions' => $count("SELECT COUNT(*) FROM AUDIT_LOG WHERE Action NOT IN ('login', 'logout', 'login_failed') AND (AfterData IS NULL OR AfterData NOT LIKE '%Copied from%') AND $actorWhere AND " . $in('OccurredAt'), [...$actorParams, ...$window]),
                    'plot_requests' => $count("SELECT COUNT(*) FROM PLOT_APPLICATION WHERE GardenerID = ? AND RequestType IN ('Apply', 'Unassign') AND " . $in('AppliedAt'), [$g, ...$window]),
                    'items_borrowed' => $count("SELECT COALESCE(SUM(Qty), 0) FROM RESOURCE_EVENT WHERE EventType = 'Borrowed' AND GardenerID = ? AND " . $in('OccurredAt'), [$g, ...$window]),
                    'crops' => $count('SELECT COUNT(*) FROM GARDEN_PLOTS WHERE GardenerID = ? AND ' . $in('CreatedAt'), [$g, ...$window]),
                    'maintenance' => $count('SELECT COUNT(*) FROM CROP_LOG WHERE GardenerID = ? AND ' . $in('LoggedAt'), [$g, ...$window]),
                    'listings' => $count('SELECT COUNT(*) FROM EXCHANGE_BOARD WHERE GardenerID = ? AND ' . $in('CreatedAt'), [$g, ...$window]),
                ];
                $row['total'] = $row['signins'] + $row['actions'] + $row['plot_requests'] + $row['crops'] + $row['maintenance'] + $row['listings'];
                $out[] = $row;
            }
            usort($out, static fn ($a, $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);
            $active = array_filter($out, static fn ($r) => $r['total'] > 0);
            $summary = $selected
                ? [['Sign-ins', $out[0]['signins']], ['Actions taken', $out[0]['actions']], ['Plot requests', $out[0]['plot_requests']], ['Crops planted', $out[0]['crops']], ['Maintenance entries', $out[0]['maintenance']]]
                : [['People active', count($active)], ['People with no activity', count($out) - count($active)], ['Sign-ins', array_sum(array_column($out, 'signins'))], ['Wrong-password attempts', array_sum(array_column($out, 'failed'))]];
            $table($selected ? 'Summary for ' . $selected['name'] : 'Everyone', ['Name', 'Sign-ins', 'Wrong passwords', 'Actions taken', 'Plot requests', 'Items borrowed', 'Crops planted', 'Maintenance entries', 'Listings posted'],
                array_map(static fn ($r) => [$r['name'], $r['signins'], $r['failed'], $r['actions'], $r['plot_requests'], $r['items_borrowed'], $r['crops'], $r['maintenance'], $r['listings']], $out),
                'Sign-ins and actions are counted since the audit log started.');
            if ($selected) {
                $timeline = userTimeline($pdo, $selected, $range['from'], $range['end_exclusive'], 1000);
                $areas = ['accounts' => 'Account', 'roles' => 'Roles', 'plots' => 'Plot', 'resources' => 'Resource', 'crops' => 'Crop', 'exchange' => 'Exchange', 'admin' => 'Admin'];
                $table('Everything ' . $selected['name'] . ' did or that happened to them', ['When', 'Area', 'What happened', 'Details'], array_map(static fn ($t) => [
                    reportDate($t['OccurredAt']), $areas[$t['Area']] ?? $t['Area'], $t['Text'], $t['Detail'] ?? '',
                ], $timeline));
            }
            break;
        }

        case 'audit': {
            [$where, $params] = auditLogFilters(['from' => $range['from'], 'to' => $range['to'], 'module' => $options['module'] ?? '']);
            $byArea = $rows("SELECT Module, COUNT(*) AS N FROM AUDIT_LOG $where GROUP BY Module ORDER BY N DESC", $params);
            $areaLabels = ['accounts' => 'Accounts and sign-in', 'roles' => 'Roles', 'plots' => 'Plots', 'resources' => 'Resources', 'exchange' => 'Exchange', 'crops' => 'Crops', 'admin' => 'Admin tools'];
            $summary = [['Entries', array_sum(array_column($byArea, 'N'))]];
            foreach (array_slice($byArea, 0, 4) as $a) $summary[] = [$areaLabels[$a['Module']] ?? $a['Module'], (int) $a['N']];
            $entries = $rows("SELECT OccurredAt, ActorName, ActorType, Module, Summary, Reason FROM AUDIT_LOG $where ORDER BY OccurredAt DESC, LogID DESC LIMIT 5000", $params);
            $who = static fn ($e) => $e['ActorName'] ?: (['guest' => 'Someone not logged in', 'system' => 'HarvestHub'][$e['ActorType']] ?? 'Unknown');
            $table('Entries', ['When', 'Who', 'Area', 'What happened', 'Reason'], array_map(static fn ($e) => [
                reportDate($e['OccurredAt']), $who($e), $areaLabels[$e['Module']] ?? $e['Module'], $e['Summary'], $e['Reason'] ?? '',
            ], $entries), count($entries) >= 5000 ? 'Only the 5,000 most recent entries are listed. Pick a shorter period to see the rest.' : '');
            break;
        }

        case 'requests': {
            $dash = dashboardData($pdo, $range);
            $madeIn = [
                'Plot requests' => array_sum($dash['requests']['over_time']['plot']),
                'Resource requests' => array_sum($dash['requests']['over_time']['resource']),
                'Coordinator applications' => array_sum($dash['requests']['over_time']['coordinator']),
                'Sign-ups' => $count('SELECT COUNT(*) FROM SIGNUP_REQUEST WHERE ' . $in('RequestedAt'), $window),
            ];
            $outcomes = $dash['requests']['outcomes'];
            $approved = array_sum(array_column($outcomes, 'approved'));
            $declined = array_sum(array_column($outcomes, 'rejected'));
            $summary = [
                ['Requests made', array_sum($madeIn)],
                ['Decisions made', $approved + $declined],
                ['Approval rate', reportPercent($approved, $approved + $declined)],
                ['Waiting right now', $count("SELECT (SELECT COUNT(*) FROM PLOT_APPLICATION WHERE Status = 'Pending' AND RequestType IN ('Apply', 'Unassign')) + (SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Requested') + (SELECT COUNT(*) FROM COORDINATOR_APPLICATION WHERE Status = 'Pending') + (SELECT COUNT(*) FROM SIGNUP_REQUEST WHERE Status = 'Pending')")],
            ];
            $table('By type', ['Type', 'Made in period', 'Approved', 'Declined', 'Cancelled', 'Approval rate', 'Typical time to decide'], array_map(static function ($type) use ($outcomes, $madeIn) {
                $o = $outcomes[$type];
                return [$type, $madeIn[$type], $o['approved'], $o['rejected'], $o['cancelled'], reportPercent($o['approved'], $o['approved'] + $o['rejected']), reportHours($o['avg_hours'])];
            }, array_keys($outcomes)), 'Approved, declined and cancelled count decisions made in the period. Fully approved resource requests are counted on the day they were lent out.');
            $periodLabel = static fn ($key) => ($range['bucket'] === 'week' ? 'Week of ' : '') . reportDate($key, false);
            $overTime = [];
            foreach ($range['keys'] as $i => $bucket) {
                $r = [$periodLabel($bucket), $dash['requests']['over_time']['plot'][$i], $dash['requests']['over_time']['resource'][$i], $dash['requests']['over_time']['coordinator'][$i]];
                if ($r[1] + $r[2] + $r[3] > 0) $overTime[] = $r;
            }
            $table('Requests made over time', ['Period', 'Plot requests', 'Resource requests', 'Coordinator applications'], $overTime, 'Periods with no requests are left out.');
            break;
        }

        case 'rejections': {
            $requests = array_filter(overviewRequests($pdo, ['plot', 'resource', 'coordinator', 'crop']), static function ($r) use ($range) {
                $when = substr((string) ($r['DecidedAt'] ?: $r['RequestedAt']), 0, 10);
                return $r['Outcome'] === 'Rejected' && $when >= $range['from'] && $when <= $range['to'];
            });
            $kindLabels = ['plot' => 'Plot request', 'resource' => 'Resource request', 'coordinator' => 'Coordinator application', 'crop' => 'Crop catalog request'];
            $list = array_map(static fn ($r) => [
                'when' => $r['DecidedAt'] ?: $r['RequestedAt'], 'type' => $kindLabels[$r['Kind']] ?? $r['Kind'], 'who' => $r['Who'],
                'what' => $r['What'], 'reason' => $r['Reason'] ?? '', 'by' => $r['DecidedBy'] ?? '',
            ], $requests);
            foreach ($rows("SELECT S.ReviewedAt, CONCAT(S.FirstName, ' ', S.LastName) AS Who, S.Email, S.RejectionReason, A.Name AS ByName FROM SIGNUP_REQUEST S LEFT JOIN SYSTEM_ADMINISTRATOR A ON A.AdminID = S.ReviewedBy WHERE S.Status = 'Rejected' AND " . $in('S.ReviewedAt'), $window) as $s) {
                $list[] = ['when' => $s['ReviewedAt'], 'type' => 'Sign-up', 'who' => $s['Who'], 'what' => 'Join HarvestHub (' . $s['Email'] . ')', 'reason' => $s['RejectionReason'] ?? '', 'by' => $s['ByName'] ?? ''];
            }
            usort($list, static fn ($a, $b) => strcmp((string) $b['when'], (string) $a['when']));
            $byType = array_count_values(array_column($list, 'type'));
            arsort($byType);
            $summary = [['Declined in period', count($list)]];
            foreach (array_slice($byType, 0, 4, true) as $type => $n) $summary[] = [$type . 's', $n];
            $reasons = dashboardData($pdo, $range)['requests']['top_reasons'];
            $table('Most common reasons', ['Reason', 'Times used'], array_map(static fn ($r) => [$r['reason'], $r['count']], $reasons), 'The same wording is counted together.');
            $table('Every declined request', ['Date', 'Type', 'Person', 'What they asked for', 'Reason', 'Declined by'], array_map(static fn ($r) => [
                reportDate($r['when']), $r['type'], $r['who'], $r['what'], $r['reason'] ?: '—', $r['by'] ?: 'Not recorded',
            ], $list));
            break;
        }

        case 'resources': {
            $overview = overviewResources($pdo);
            $holders = [];
            $stock = [];
            foreach ($overview['resources'] as $r) {
                $borrowed = array_sum(array_column($r['Holders'], 'Qty'));
                $stock[] = [$r['Name'], (int) $r['TotalQty'], max(0, (int) $r['TotalQty'] - $borrowed), $borrowed];
                foreach ($r['Holders'] as $h) {
                    $holders[] = [$r['Name'], $h['GardenerName'], $h['PlotLabel'] ?: '—', (int) $h['Qty'], reportDate($h['ApprovedAt'], false), $h['DaysHeld'] === null ? '—' : (int) $h['DaysHeld'],
                        $h['Status'] === 'Return Requested' ? ($h['Overdue'] ? 'Overdue (asked ' . reportDate($h['ReturnRequestedAt'], false) . ')' : 'Return requested ' . reportDate($h['ReturnRequestedAt'], false)) : 'Not asked yet'];
                }
            }
            $lent = $rows("SELECT E.OccurredAt, R.Name, E.GardenerName, E.Qty, E.EventType FROM RESOURCE_EVENT E JOIN RESOURCE R ON R.ResourceID = E.ResourceID WHERE E.EventType IN ('Borrowed', 'Returned', 'Added') AND " . $in('E.OccurredAt') . ' ORDER BY E.OccurredAt DESC', $window);
            $sumOf = static fn (string $type) => array_sum(array_map(static fn ($e) => $e['EventType'] === $type ? (int) $e['Qty'] : 0, $lent));
            $overdue = count(array_filter($holders, static fn ($h) => str_starts_with($h[6], 'Overdue')));
            $summary = [
                ['Items out now', array_sum(array_column($holders, 3))],
                ['Overdue returns', $overdue],
                ['Lent in period', $sumOf('Borrowed')],
                ['Returned in period', $sumOf('Returned')],
                ['Added to stock in period', $sumOf('Added')],
            ];
            $table('Stock right now', ['Item', 'Total', 'On the shelf', 'Borrowed'], $stock);
            $table('Out with gardeners right now', ['Item', 'Gardener', 'Plot', 'Quantity', 'Since', 'Days held', 'Return'], $holders,
                'A return is overdue when it was asked for ' . RETURN_OVERDUE_DAYS . ' or more days ago.');
            $labels = ['Borrowed' => 'Lent out', 'Returned' => 'Returned', 'Added' => 'Added to stock'];
            $table('Lending in this period', ['Date', 'Item', 'What happened', 'Gardener', 'Quantity'], array_map(static fn ($e) => [
                reportDate($e['OccurredAt']), $e['Name'], $labels[$e['EventType']], $e['GardenerName'] ?: '—', (int) $e['Qty'],
            ], $lent));
            break;
        }

        case 'plots': {
            $plots = overviewPlots($pdo);
            $events = $rows("
                SELECT E.OccurredAt, E.PlotLabel, E.EventType, E.GardenerName, E.ActorName, A.RequestType, A.RejectionReason, A.RequestReason
                FROM PLOT_EVENT E LEFT JOIN PLOT_APPLICATION A ON A.AppID = E.AppID
                WHERE (E.EventType = 'Plot Unassigned' OR (E.EventType = 'Request Accepted' AND A.RequestType IN ('Apply', 'Unassign')))
                  AND " . $in('E.OccurredAt') . " ORDER BY E.OccurredAt DESC", $window);
            $change = static function ($e) {
                if ($e['EventType'] === 'Request Accepted') return $e['RequestType'] === 'Apply' ? 'Plot given' : 'Gave up the plot';
                return $e['RequestType'] === 'Return' ? 'Returned when asked' : 'Removed from the plot';
            };
            $given = count(array_filter($events, static fn ($e) => $change($e) === 'Plot given'));
            $inUse = count(array_filter($plots, static fn ($p) => $p['Status'] === 'Occupied'));
            $summary = [['Plots in use now', "$inUse of " . count($plots)], ['Plots given out in period', $given], ['Plots given up in period', count($events) - $given]];
            $table('Every plot right now', ['Plot', 'City', 'Area (m²)', 'Status', 'Held by', 'Held since', 'Requests waiting'], array_map(static fn ($p) => [
                $p['Label'], $p['Location'], $p['AreaSqM'] ?? '—', $p['Status'] === 'Occupied' ? 'In use' : 'Available',
                $p['GardenerName'] ?? '—', $p['GardenerName'] ? (reportDate($p['HeldSince'], false) ?: 'Not recorded') : '—', (int) $p['PendingRequests'],
            ], $plots));
            $table('Plots given out and given up in this period', ['Date', 'Plot', 'What happened', 'Gardener', 'Done by', 'Reason or note'], array_map(static fn ($e) => [
                reportDate($e['OccurredAt']), $e['PlotLabel'], $change($e), $e['GardenerName'] ?? '—', $e['ActorName'] ?? '—', $e['RequestReason'] ?? '',
            ], $events), 'Plot history is recorded from October 7, 2026.');
            break;
        }

        case 'exchange_harvest': {
            $listings = $rows("
                SELECT B.CreatedAt, B.ProduceName, B.Qty, B.Type, B.Status, G.Name AS PostedBy,
                       (SELECT COUNT(*) FROM EXCHANGE_CLAIMS C WHERE C.PostID = B.PostID AND C.Status = 'Accepted') AS Accepted
                FROM EXCHANGE_BOARD B LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = B.GardenerID
                WHERE " . $in('B.CreatedAt') . ' ORDER BY B.CreatedAt DESC', $window);
            $claims = $rows("
                SELECT C.CreatedAt, B.ProduceName, G.Name AS RequestedBy, O.Name AS Owner, C.QtyWanted, C.Status
                FROM EXCHANGE_CLAIMS C JOIN EXCHANGE_BOARD B ON B.PostID = C.PostID
                LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = C.RequesterID
                LEFT JOIN COMMUNITY_GARDENER O ON O.GardenerID = B.GardenerID
                WHERE " . $in('C.CreatedAt') . ' ORDER BY C.CreatedAt DESC', $window);
            $harvests = $rows("
                SELECT L.LoggedAt, G.Name, L.CropName, L.HarvestYield, P.Label
                FROM CROP_LOG L JOIN COMMUNITY_GARDENER G ON G.GardenerID = L.GardenerID LEFT JOIN PLOT P ON P.PltID = L.PltID
                WHERE (L.MaintenanceNotes LIKE '%to Harvested.%' OR (L.HarvestYield IS NOT NULL AND L.HarvestYield <> '')) AND " . $in('L.LoggedAt') . '
                ORDER BY L.LoggedAt DESC', $window);
            $summary = [
                ['Listings posted', count($listings)],
                ['Requests for produce', count($claims)],
                ['Trades agreed', count(array_filter($claims, static fn ($c) => $c['Status'] === 'Accepted'))],
                ['Harvests logged', count($harvests)],
            ];
            $table('Listings posted in this period', ['Date', 'Produce', 'Quantity left', 'Offering or seeking', 'Posted by', 'Status', 'Trades agreed'], array_map(static fn ($l) => [
                reportDate($l['CreatedAt']), $l['ProduceName'], $l['Qty'], $l['Type'], $l['PostedBy'] ?? '—', $l['Status'] === 'Active' ? 'Open' : 'Closed', (int) $l['Accepted'],
            ], $listings));
            $table('Requests for produce in this period', ['Date', 'Produce', 'Asked by', 'From', 'Quantity wanted', 'Answer'], array_map(static fn ($c) => [
                reportDate($c['CreatedAt']), $c['ProduceName'], $c['RequestedBy'] ?? '—', $c['Owner'] ?? '—', $c['QtyWanted'], ['Pending' => 'Waiting', 'Accepted' => 'Accepted', 'Rejected' => 'Declined'][$c['Status']] ?? $c['Status'],
            ], $claims));
            $table('Harvests logged in this period', ['Date', 'Gardener', 'Crop', 'Yield', 'Plot'], array_map(static fn ($h) => [
                reportDate($h['LoggedAt']), $h['Name'], $h['CropName'], $h['HarvestYield'] ?: 'Not recorded', $h['Label'] ?? '—',
            ], $harvests));
            break;
        }

        case 'archived': {
            // Latest archive entry in the audit log for each account (date, who, reason)
            $archiveInfo = [];
            foreach ($rows("SELECT TargetType, TargetID, OccurredAt, ActorName, Reason FROM AUDIT_LOG WHERE Action = 'account_archived' ORDER BY OccurredAt") as $a) {
                $archiveInfo[$a['TargetType'] . '-' . $a['TargetID']] = $a;
            }
            $notices = [];
            foreach ($rows('SELECT N.GardenerID, N.Reason, N.Details, N.CreatedAt FROM ACCOUNT_ARCHIVE_NOTICE N ORDER BY N.NoticeID') as $n) {
                $notices[(int) $n['GardenerID']] = $n;
            }
            $archived = [];
            foreach ([['gardener', 'COMMUNITY_GARDENER', 'GardenerID', 'Gardener'], ['coordinator', 'GARDEN_COORDINATOR', 'CoordID', 'Coordinator'], ['admin', 'SYSTEM_ADMINISTRATOR', 'AdminID', 'Administrator']] as [$type, $tbl, $col, $label]) {
                foreach ($rows("SELECT $col AS id, Name, Email FROM $tbl WHERE Status = 'Archived' ORDER BY Name") as $acc) {
                    $info = $archiveInfo["$type-{$acc['id']}"] ?? null;
                    $notice = $type === 'gardener' ? ($notices[(int) $acc['id']] ?? null) : null;
                    $reason = $info['Reason'] ?? ($notice ? trim($notice['Reason'] . ': ' . $notice['Details'], ': ') : '');
                    $archived[] = [$acc['Name'], $acc['Email'], $label, $info ? reportDate($info['OccurredAt']) : 'Before the audit log', $info['ActorName'] ?? '—', $reason ?: 'Not recorded',
                        $info && substr($info['OccurredAt'], 0, 10) >= $range['from'] && substr($info['OccurredAt'], 0, 10) <= $range['to'] ? 'Yes' : 'No'];
                }
            }
            $demotions = $rows("SELECT ChangedAt, AccountName, ReasonCategory, ReasonDetails, ChangedByName FROM ROLE_HISTORY WHERE ChangeType = 'demoted' AND " . $in('ChangedAt') . ' ORDER BY ChangedAt DESC', $window);
            $summary = [
                ['Archived accounts', count($archived)],
                ['Archived in period', count(array_filter($archived, static fn ($a) => $a[6] === 'Yes'))],
                ['Coordinator roles removed in period', count($demotions)],
            ];
            $table('Archived accounts', ['Name', 'Email', 'Account', 'Archived on', 'Archived by', 'Reason', 'In this period'], $archived,
                'Every account that is archived today. Accounts archived before the audit log started have no date.');
            $table('Coordinator roles removed in this period', ['Date', 'Person', 'Reason', 'Details', 'Removed by'], array_map(static fn ($d) => [
                reportDate($d['ChangedAt']), $d['AccountName'], $d['ReasonCategory'] ?? '—', $d['ReasonDetails'] ?? '', $d['ChangedByName'] ?? '—',
            ], $demotions));
            break;
        }
    }

    return [
        'key' => $key,
        'title' => $title,
        'description' => $description,
        'period' => $period,
        'range' => ['from' => $range['from'], 'to' => $range['to']],
        'summary' => array_map(static fn ($s) => ['label' => $s[0], 'value' => $s[1]], $summary),
        'tables' => $tables,
    ];
}

/** The same report as CSV lines (each a list of cells). */
function reportCsvLines(array $report): array {
    $lines = [['HarvestHub report: ' . $report['title']], ['Period: ' . $report['period']], []];
    if ($report['summary']) {
        $lines[] = ['Summary'];
        foreach ($report['summary'] as $s) $lines[] = [$s['label'], $s['value']];
    }
    foreach ($report['tables'] as $t) {
        $lines[] = [];
        $lines[] = [$t['title']];
        if ($t['note'] !== '') $lines[] = [$t['note']];
        $lines[] = $t['columns'];
        foreach ($t['rows'] as $row) $lines[] = $row;
        if (!$t['rows']) $lines[] = ['Nothing in this period.'];
    }
    return $lines;
}
