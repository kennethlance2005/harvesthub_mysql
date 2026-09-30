<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
header('Location: ' . ($user ? loginRedirectFor($user['role']) : 'login.php'));
exit;