<?php

$host     = "localhost";
$port     = "5432";
$dbname   = "expense_tracker";
$user     = "postgres";
$password = "salinas";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $db  = new PDO($dsn, $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}