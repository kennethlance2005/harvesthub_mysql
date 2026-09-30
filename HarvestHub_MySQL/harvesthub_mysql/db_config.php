<?php
/**
 * db_config.php
 * MySQL connection settings. Edit these to match your environment:
 *
 * - Local XAMPP default: host 'localhost', user 'root', empty password.
 * - Real hosting: your host gives you these four values (often in a
 *   control panel like cPanel) — just paste them in here.
 *
 * Keep this file out of version control / public repos once you fill
 * in real production credentials, since it holds a live password.
 */

return [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: '3306',
    'dbname'   => getenv('DB_NAME') ?: 'harvesthub',
    'user'     => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
    'charset'  => 'utf8mb4',
    'ssl_ca'   => getenv('DB_SSL_CA') ?: '',
];
