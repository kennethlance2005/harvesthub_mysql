<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/../db.php';

// Record the logout before the session is cleared. If the database is down,
// logging out must still work, so failures here are ignored.
if ($user = currentUser()) {
    try {
        logAudit(getDb(), 'accounts', 'logout', "{$user['name']} logged out.", [
            'target' => [AUDIT_ACCOUNT_TYPES[$user['role']] ?? $user['role'], $user['id'], $user['name']],
        ]);
    } catch (Throwable $e) {
        error_log('HarvestHub: could not record logout: ' . $e->getMessage());
    }
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
