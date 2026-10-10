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

// ---------- Permissions (admin checklist section 6) ----------
//
// What someone may do comes from their roles: the built-in role of each
// account they are logged in with (Administrator / Coordinator / Gardener)
// plus any custom roles in USER_ROLE. Administrators always have every
// permission. Self-service gardener actions (own plots, crops, exchange)
// still just need a gardener account; see requireRole('customer').

// Session role names -> built-in ROLE codes.
const SESSION_ROLE_CODES = ['admin' => 'admin', 'staff' => 'coordinator', 'customer' => 'gardener'];

// Which permissions open each admin/coordinator page (any one is enough).
const PAGE_PERMISSIONS = [
    'staff_dashboard.php' => ['plots.view', 'resources.view', 'crops.review_catalog'],
    'staff_plots.php' => ['plots.view'],
    'staff_inventory.php' => ['resources.view', 'crops.review_catalog'],
    'staff_records.php' => ['plots.view', 'resources.view'],
    'admin_dashboard.php' => ['reports.view'],
    'admin_manage_gardeners.php' => ['accounts.view', 'registrations.review'],
    'admin_manage_coordinators.php' => ['accounts.view', 'coordinator_applications.review'],
    'admin_manage_admins.php' => ['accounts.view', 'accounts.create_admin'],
    'admin_archived_accounts.php' => ['accounts.view'],
    'admin_audit_log.php' => ['audit.view'],
    'admin_roles.php' => ['roles.manage'],
    'admin_overview.php' => ['overview.view'],
    'admin_activity.php' => ['accounts.view'],
    'admin_user.php' => ['accounts.view'],
];

/** Permission codes the logged-in person has, read fresh from the database once per request. */
function currentPermissions(): array {
    static $cacheKey = null;
    static $cache = [];
    $user = currentUser();
    if (!$user) return [];
    $key = json_encode([$user['roles'], $user['ids']]);
    if ($cacheKey === $key) return $cache;

    try {
        require_once __DIR__ . '/../db.php';
        $pdo = getDb();
        if (in_array('admin', $user['roles'], true)) {
            // Everything in the database, plus every permission a page needs,
            // so a not-yet-run migration never locks administrators out.
            $codes = array_values(array_unique(array_merge(
                $pdo->query('SELECT Code FROM PERMISSION')->fetchAll(PDO::FETCH_COLUMN),
                ...array_values(PAGE_PERMISSIONS)
            )));
        } else {
            $builtIn = array_values(array_intersect_key(SESSION_ROLE_CODES, array_flip($user['roles'])));
            $placeholders = implode(',', array_fill(0, count($builtIn), '?')) ?: "''";
            $stmt = $pdo->prepare("
                SELECT DISTINCT P.Code
                FROM ROLE_PERMISSION RP
                JOIN PERMISSION P ON P.PermissionID = RP.PermissionID
                JOIN ROLE R ON R.RoleID = RP.RoleID
                WHERE (R.IsBuiltIn = 1 AND R.Code IN ($placeholders))
                   OR R.RoleID IN (
                        SELECT RoleID FROM USER_ROLE
                        WHERE (AccountType = 'gardener' AND AccountID = ?) OR (AccountType = 'coordinator' AND AccountID = ?)
                   )
            ");
            $stmt->execute(array_merge($builtIn, [(int) ($user['ids']['customer'] ?? 0), (int) ($user['ids']['staff'] ?? 0)]));
            $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Throwable $e) {
        error_log('HarvestHub: could not load permissions: ' . $e->getMessage());
        $codes = []; // fail closed
    }
    $cacheKey = $key;
    $cache = $codes;
    return $codes;
}

function can(string $permission): bool {
    return in_array($permission, currentPermissions(), true);
}

function canAny(array $permissions): bool {
    return (bool) array_intersect($permissions, currentPermissions());
}

/** Whether the logged-in person may open an admin/coordinator page. */
function canOpen(string $page): bool {
    return canAny(PAGE_PERMISSIONS[$page] ?? []);
}

/**
 * Page guard for admin and coordinator pages: sends visitors to the login
 * page and shows a friendly "no access" page to people without permission.
 */
function requirePageAccess(): array {
    $user = currentUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    if (!canOpen(basename($_SERVER['PHP_SELF']))) {
        showNoAccessPage($user);
    }
    return $user;
}

function showNoAccessPage(array $user): void {
    http_response_code(403);
    $home = loginRedirectFor($user['role']);
    $homeUsable = !isset(PAGE_PERMISSIONS[$home]) || canOpen($home);
    $name = htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — No access</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=50">
</head>
<body class="account-page no-access-page">
  <main class="panel no-access-box">
    <p class="eyebrow">No access</p>
    <h1>You can't open this page</h1>
    <p class="text-muted">Hi $name, your roles don't include permission for this page. If you think you should have it, ask an administrator to check your roles.</p>
    <div class="no-access-actions">
HTML;
    if ($homeUsable) {
        echo '<a class="btn btn-accent" href="' . htmlspecialchars($home, ENT_QUOTES, 'UTF-8') . '">Go to my dashboard</a>';
    }
    echo '<a class="btn btn-ghost" href="logout.php">Log out</a></div></main></body></html>';
    exit;
}

/** The first page in the list the person may open, or null. */
function firstOpenablePage(array $pages): ?string {
    foreach ($pages as $page) {
        if (canOpen($page)) return $page;
    }
    return null;
}

/** Hands the permission list to page scripts as window.HH_CAN / hhCan('plots.add'). */
function permissionsScript(): string {
    $codes = json_encode(array_values(currentPermissions()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return "<script>window.HH_CAN = $codes; window.hhCan = function (p) { return window.HH_CAN.indexOf(p) !== -1; };</script>";
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
