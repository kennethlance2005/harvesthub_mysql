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
