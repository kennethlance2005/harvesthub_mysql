<?php
/**
 * audit.php
 * Writes entries to AUDIT_LOG, the permanent "who did what, when" history
 * that administrators review. Entries are never edited or deleted (the
 * database blocks it, see migrations/20261011_audit_log_lock.sql).
 *
 *   logAudit($pdo, 'accounts', 'registration_approved',
 *       "Ana Bautista approved Maria Santos's registration.", [
 *           'actor'  => auditActor($user),             // defaults to whoever is logged in
 *           'target' => ['gardener', 12, 'Maria Santos'],
 *           'reason' => $reason,                        // optional
 *           'before' => ['Status' => 'Pending'],        // optional, stored as JSON
 *           'after'  => ['Status' => 'Approved'],       // optional, stored as JSON
 *       ]);
 *
 * Logging must never break the action being logged, so failures are only
 * written to the server error log.
 */

require_once __DIR__ . '/auth.php';

// Session role names -> the account types used in AUDIT_LOG and ROLE_HISTORY.
const AUDIT_ACCOUNT_TYPES = ['customer' => 'gardener', 'staff' => 'coordinator', 'admin' => 'admin'];

/**
 * The person acting, in audit form. Pass what requireJsonRole() returned so
 * a gardener acting as coordinator is recorded as the coordinator.
 */
function auditActor(?array $user): array {
    if (!$user) {
        return ['type' => 'guest', 'id' => null, 'name' => null];
    }
    return [
        'type' => AUDIT_ACCOUNT_TYPES[$user['role']] ?? (string) $user['role'],
        'id' => isset($user['id']) ? (int) $user['id'] : null,
        'name' => $user['name'] ?? null,
    ];
}

/** An account as an audit actor/target, from its login table row. */
function auditAccount(string $sessionRole, ?int $id, ?string $name): array {
    return ['type' => AUDIT_ACCOUNT_TYPES[$sessionRole] ?? $sessionRole, 'id' => $id, 'name' => $name];
}

function logAudit(PDO $pdo, string $module, string $action, string $summary, array $details = []): void {
    try {
        $actor = $details['actor'] ?? auditActor(currentUser());
        [$targetType, $targetId, $targetName] = array_pad($details['target'] ?? [], 3, null);
        $encode = static fn ($data) => $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE);
        $clip = static fn (?string $text, int $max) => $text === null ? null : mb_substr($text, 0, $max);

        $pdo->prepare("
            INSERT INTO AUDIT_LOG
                (ActorType, ActorID, ActorName, Module, Action, TargetType, TargetID, TargetName,
                 Summary, Reason, BeforeData, AfterData, IpAddress)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $actor['type'] ?? null,
            $actor['id'] ?? null,
            $clip($actor['name'] ?? null, 120),
            $module,
            $action,
            $targetType,
            $targetId === null ? null : (int) $targetId,
            $clip($targetName, 160),
            $clip($summary, 500),
            $clip($details['reason'] ?? null, 1000),
            $encode($details['before'] ?? null),
            $encode($details['after'] ?? null),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log("HarvestHub: could not write audit entry ($module/$action): " . $e->getMessage());
    }
}

/**
 * The account an email belongs to, as an audit target [type, id, name],
 * or null. Checks gardeners, then coordinators, then administrators.
 */
function auditAccountByEmail(PDO $pdo, string $email): ?array {
    foreach ([
        ['COMMUNITY_GARDENER', 'GardenerID', 'gardener'],
        ['GARDEN_COORDINATOR', 'CoordID', 'coordinator'],
        ['SYSTEM_ADMINISTRATOR', 'AdminID', 'admin'],
    ] as [$table, $idColumn, $type]) {
        $stmt = $pdo->prepare("SELECT $idColumn AS id, Name FROM $table WHERE Email = ?");
        $stmt->execute([$email]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [$type, (int) $row['id'], $row['Name']];
        }
    }
    return null;
}

/**
 * WHERE clause + parameters for the admin audit log filters:
 * q (search), module, actor_type, action_type, from / to (YYYY-MM-DD, inclusive).
 */
function auditLogFilters(array $input): array {
    $conditions = [];
    $params = [];

    $search = trim((string) ($input['q'] ?? ''));
    if ($search !== '') {
        $like = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
        $conditions[] = '(Summary LIKE ? OR ActorName LIKE ? OR TargetName LIKE ? OR Reason LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    $modules = ['accounts', 'roles', 'plots', 'resources', 'exchange', 'crops', 'admin'];
    if (in_array($input['module'] ?? '', $modules, true)) {
        $conditions[] = 'Module = ?';
        $params[] = $input['module'];
    }

    $actorTypes = ['admin', 'coordinator', 'gardener', 'guest', 'system'];
    if (in_array($input['actor_type'] ?? '', $actorTypes, true)) {
        $conditions[] = 'ActorType = ?';
        $params[] = $input['actor_type'];
    }

    $action = (string) ($input['action_type'] ?? '');
    if (preg_match('/^[a-z_]{1,50}$/', $action)) {
        $conditions[] = 'Action = ?';
        $params[] = $action;
    }

    foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
        $date = (string) ($input[$key] ?? '');
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        if ($parsed && $parsed->format('Y-m-d') === $date) {
            if ($key === 'to') $parsed->modify('+1 day'); // include the whole "to" day
            $conditions[] = "OccurredAt $operator ?";
            $params[] = $parsed->format('Y-m-d H:i:s');
        }
    }

    return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
}

/** Stops spreadsheet apps from treating a cell like "=SUM(...)" as a formula. */
function csvSafe($value): string {
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

/** "Maria Santos" -> "Maria Santos's", "Juan Cruz" -> "Juan Cruz's" (plain-English summaries). */
function possessive(string $name): string {
    return $name . (preg_match('/s$/i', $name) ? "'" : "'s");
}
