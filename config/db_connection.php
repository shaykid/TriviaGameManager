<?php
// Database Connection
$config = json_decode(file_get_contents(__DIR__ . "/db_connection.json"), true);

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8",
        $config['user'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode([
        "status" => "error", 
        "message" => "Database connection failed: " . $e->getMessage()
    ]));
}
