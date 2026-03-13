<?php
define('DB_DRIVER',  'pgsql');
define('DB_HOST',    'localhost');
define('DB_PORT',    '5432');
define('DB_USER',    'postgres');
define('DB_PASS',    'salinas');
define('DB_NAME',    'expense_tracker');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (DB_DRIVER === 'pgsql') {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        } else {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        }
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h2>Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</h2>');
        }
    }
    return $pdo;
}