<?php
/**
 * db.php
 * Connects to the HarvestHub MySQL database. The schema itself (tables
 * + demo seed data) lives in schema.sql — import that once via
 * phpMyAdmin or the mysql CLI before running the app. This file no
 * longer creates or migrates tables at runtime (that was a SQLite-only
 * workaround from the earlier prototype); it just opens the connection.
 */

function getDb(): PDO {
    // 1. Check if we are on Render by looking for the DB_HOST environment variable
    $host = getenv('DB_HOST');
    
    if ($host) {
        // --- RENDER CLOUD CONNECTION ---
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');
        $ssl_ca = getenv('DB_SSL_CA'); // e.g., /var/www/html/ca.pem
        
        // Includes the required custom port for Aiven
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        
        // Injects the SSL certificate required by Aiven
        $options = [
            PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        try {
            return new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            // Prints the exact technical error if it fails on Render
            die("Render DB Error: " . $e->getMessage()); 
        }
        
    } else {
        // --- LOCAL XAMPP CONNECTION ---
        $config = require __DIR__ . '/db_config.php';
        
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['dbname'],
            $config['charset']
        );
        
        try {
            $pdo = new PDO($dsn, $config['user'], $config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            return $pdo;
        } catch (PDOException $e) {
            die("Local DB Error: " . $e->getMessage());
        }
    }
}
?>