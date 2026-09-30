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
    return $_SESSION['user'] ?? null;
}

function requireRole(string $role): array {
    $user = currentUser();
    if (!$user || $user['role'] !== $role) {
        header('Location: login.php');
        exit;
    }
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
