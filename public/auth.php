<?php
/**
 * auth.php
 * Minimal session-based auth shared by login.php and every dashboard.
 * A logged-in user has $_SESSION['user'] = ['role' => ..., 'id' => ..., 'name' => ...]
 * role is one of: 'admin', 'staff', 'customer'
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentUser(): ?array {
    $user = $_SESSION['user'] ?? null;
    if (!$user) return null;

    $user['roles'] = $user['roles'] ?? [$user['role']];
    $user['ids'] = $user['ids'] ?? [$user['role'] => $user['id']];

    // Coordinator access can be removed by an administrator while someone is
    // logged in, so check it against the database once per request instead
    // of trusting what was true at login.
    static $coordinatorChecked = false;
    if (!$coordinatorChecked && in_array('staff', $user['roles'], true)) {
        $coordinatorChecked = true;
        $user = refreshCoordinatorAccess($user);
        if ($user === null) return null;
    }
    return $user;
}

/**
 * Drops the coordinator role from the session when that coordinator record
 * is no longer active. A coordinator-only account that lost its role is
 * logged out entirely (they sign in again as a gardener if converted).
 */
function refreshCoordinatorAccess(array $user): ?array {
    try {
        require_once __DIR__ . '/../db.php';
        $stmt = getDb()->prepare("SELECT COALESCE(NULLIF(Status, ''), 'Active') FROM GARDEN_COORDINATOR WHERE CoordID = ?");
        $stmt->execute([(int) ($user['ids']['staff'] ?? 0)]);
        $status = $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('HarvestHub: could not re-check coordinator access: ' . $e->getMessage());
        return $user; // database trouble: keep the session as it was
    }
    if ($status === 'Active') return $user;

    if ($user['role'] === 'staff') {
        $_SESSION = [];
        return null;
    }
    $user['roles'] = array_values(array_diff($user['roles'], ['staff']));
    unset($user['ids']['staff']);
    $_SESSION['user']['roles'] = $user['roles'];
    $_SESSION['user']['ids'] = $user['ids'];
    return $user;
}

function hasRole(string $role): bool {
    $user = currentUser();
    return $user !== null && in_array($role, $user['roles'], true);
}

function requireRole(string $role): array {
    $user = currentUser();
    if (!$user || !in_array($role, $user['roles'], true)) {
        header('Location: login.php');
        exit;
    }
    $user['id'] = $user['ids'][$role] ?? $user['id'];
    $user['role'] = $role;
    return $user;
}

function loginRedirectFor(string $role): string {
    return match ($role) {
        'admin' => 'admin_dashboard.php',
        'staff' => 'staff_dashboard.php',
        'customer' => 'customer_dashboard.php',
        default => 'login.php',
    };
}
