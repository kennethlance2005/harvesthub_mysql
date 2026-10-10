<?php
/**
 * roles.php
 * Roles and permissions (admin checklist section 6).
 *
 * Built-in roles come from the three account tables: an account in
 * SYSTEM_ADMINISTRATOR is an Administrator, an active GARDEN_COORDINATOR is a
 * Coordinator, a COMMUNITY_GARDENER is a Gardener. USER_ROLE only holds the
 * extra custom roles (e.g. "Inventory Clerk") an administrator gives someone.
 *
 * The Administrator role always has every permission and can't be edited,
 * so there is always someone who can manage roles.
 */

require_once __DIR__ . '/audit.php';

const ROLE_ACCOUNT_TYPES = ['gardener', 'coordinator'];   // who can be given custom roles
const ROLE_ACCOUNT_TABLES = [
    'admin' => ['SYSTEM_ADMINISTRATOR', 'AdminID'],
    'coordinator' => ['GARDEN_COORDINATOR', 'CoordID'],
    'gardener' => ['COMMUNITY_GARDENER', 'GardenerID'],
];

function findRole(PDO $pdo, int $roleId, bool $lock = false): ?array {
    $stmt = $pdo->prepare('SELECT RoleID, Code, Name, Description, IsBuiltIn FROM ROLE WHERE RoleID = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$roleId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Permission codes a role has, sorted the same way as the matrix. */
function rolePermissionCodes(PDO $pdo, int $roleId): array {
    $stmt = $pdo->prepare('SELECT P.Code FROM ROLE_PERMISSION RP JOIN PERMISSION P ON P.PermissionID = RP.PermissionID WHERE RP.RoleID = ? ORDER BY P.SortOrder');
    $stmt->execute([$roleId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Code => Name for every permission, in matrix order. */
function permissionNames(PDO $pdo): array {
    return $pdo->query('SELECT Code, Name FROM PERMISSION ORDER BY SortOrder')->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** How many people hold each role, keyed by RoleID. */
function roleMemberCounts(PDO $pdo): array {
    $builtIn = [
        'admin' => "SELECT COUNT(*) FROM SYSTEM_ADMINISTRATOR WHERE COALESCE(NULLIF(Status, ''), 'Active') IN ('Active', 'Disabled')",
        'coordinator' => "SELECT COUNT(*) FROM GARDEN_COORDINATOR WHERE COALESCE(NULLIF(Status, ''), 'Active') IN ('Active', 'Disabled')",
        'gardener' => "SELECT COUNT(*) FROM COMMUNITY_GARDENER WHERE COALESCE(NULLIF(Status, ''), 'Active') IN ('Active', 'Disabled')",
    ];
    $counts = [];
    foreach ($pdo->query('SELECT RoleID, Code, IsBuiltIn FROM ROLE') as $role) {
        $counts[(int) $role['RoleID']] = $role['IsBuiltIn'] && isset($builtIn[$role['Code']])
            ? (int) $pdo->query($builtIn[$role['Code']])->fetchColumn()
            : 0;
    }
    foreach ($pdo->query('SELECT RoleID, COUNT(*) FROM USER_ROLE GROUP BY RoleID')->fetchAll(PDO::FETCH_KEY_PAIR) as $roleId => $count) {
        $counts[(int) $roleId] = (int) $count;
    }
    return $counts;
}

/**
 * An account that can hold custom roles: [type, id, name, email]. With
 * $activeOnly, archived/demoted accounts don't count (they can't be given a
 * role, but a role can still be taken away from them).
 */
function findRoleAccount(PDO $pdo, string $type, int $id, bool $activeOnly = true): ?array {
    if (!in_array($type, ROLE_ACCOUNT_TYPES, true)) return null;
    [$table, $key] = ROLE_ACCOUNT_TABLES[$type];
    $where = "$key = ?" . ($activeOnly ? " AND COALESCE(NULLIF(Status, ''), 'Active') IN ('Active', 'Disabled')" : '');
    $stmt = $pdo->prepare("SELECT $key AS id, Name, Email FROM $table WHERE $where");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ['type' => $type, 'id' => (int) $row['id'], 'name' => $row['Name'], 'email' => $row['Email']] : null;
}

/**
 * Which account custom roles live on: a coordinator who is also a gardener
 * keeps them on their gardener account, so the person has one set of roles.
 */
function roleAccountFor(PDO $pdo, string $type, int $id): array {
    if ($type === 'coordinator') {
        $stmt = $pdo->prepare('SELECT GardenerID FROM GARDEN_COORDINATOR WHERE CoordID = ?');
        $stmt->execute([$id]);
        $gardenerId = $stmt->fetchColumn();
        if ($gardenerId) return ['gardener', (int) $gardenerId];
    }
    return [$type, $id];
}

/** Custom roles held by one account: [[RoleID, Name], ...]. */
function accountCustomRoles(PDO $pdo, string $type, int $id): array {
    $stmt = $pdo->prepare('SELECT R.RoleID, R.Name FROM USER_ROLE UR JOIN ROLE R ON R.RoleID = UR.RoleID WHERE UR.AccountType = ? AND UR.AccountID = ? ORDER BY R.Name');
    $stmt->execute([$type, $id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Gives a custom role to an account or takes it away, with a role history
 * row and an audit entry. Returns false when nothing changed (they already
 * had it / didn't have it). Call inside a transaction.
 */
function setAccountCustomRole(PDO $pdo, array $admin, array $account, array $role, bool $grant, string $reason = ''): bool {
    if ($grant) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO USER_ROLE (AccountType, AccountID, RoleID, AssignedBy) VALUES (?, ?, ?, ?)');
        $stmt->execute([$account['type'], $account['id'], (int) $role['RoleID'], $admin['admin_id']]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM USER_ROLE WHERE AccountType = ? AND AccountID = ? AND RoleID = ?');
        $stmt->execute([$account['type'], $account['id'], (int) $role['RoleID']]);
    }
    if ($stmt->rowCount() === 0) return false;

    $pdo->prepare("
        INSERT INTO ROLE_HISTORY (AccountType, AccountID, AccountName, RoleID, RoleName, ChangeType, ReasonDetails, ChangedBy, ChangedByName)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $account['type'], $account['id'], $account['name'], (int) $role['RoleID'], $role['Name'],
        $grant ? 'granted' : 'removed', $reason !== '' ? $reason : null, $admin['admin_id'], $admin['name'],
    ]);

    $summary = $grant
        ? "{$admin['name']} gave {$account['name']} the {$role['Name']} role."
        : "{$admin['name']} removed the {$role['Name']} role from {$account['name']}.";
    logAudit($pdo, 'roles', $grant ? 'role_assigned' : 'role_removed', $summary, [
        'actor' => auditActor($admin),
        'target' => [$account['type'], $account['id'], $account['name']],
        'reason' => $reason !== '' ? $reason : null,
        'before' => ['Has role' => $grant ? 'No' : 'Yes', 'Role' => $role['Name']],
        'after' => ['Has role' => $grant ? 'Yes' : 'No', 'Role' => $role['Name']],
    ]);
    return true;
}

/** A unique code for a new custom role, e.g. "Inventory Clerk" -> "inventory_clerk". */
function newRoleCode(PDO $pdo, string $name): string {
    $base = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_') ?: 'role';
    $base = substr($base, 0, 32);
    $code = $base;
    $check = $pdo->prepare('SELECT 1 FROM ROLE WHERE Code = ?');
    for ($n = 2; ; $n++) {
        $check->execute([$code]);
        if (!$check->fetchColumn()) return $code;
        $code = "{$base}_$n";
    }
}
