<?php
// dbtest.php - DELETE after debugging
require __DIR__ . '/db.php';
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "<br>";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "<br>";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'NOT SET') . "<br>";
echo "DB_USER: " . (getenv('DB_USER') ?: 'NOT SET') . "<br>";
echo "DB_SSL_CA: " . (getenv('DB_SSL_CA') ?: 'NOT SET') . " (exists: " . (file_exists(getenv('DB_SSL_CA') ?: '') ? 'yes' : 'no') . ")<br>";
$pdo = getDb();
echo "Connected!<br>";
foreach ($pdo->query("SHOW TABLES") as $r) echo $r[0] . "<br>";