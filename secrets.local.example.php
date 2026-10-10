<?php
/**
 * Local secrets for XAMPP development.
 *
 * Copy this file to secrets.local.php (same folder) and fill in real values.
 * secrets.local.php is listed in .gitignore, so it is never committed.
 * On the live server, set the SMTP_* environment variables instead.
 */
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from_email' => '',
    'smtp_from_name' => 'HarvestHub',
    'smtp_secure' => 'tls',
];
