<?php
/**
 * Database Connection Handler
 *
 * Supports local configuration (includes/db_config.local.php) and environment variables
 * without exposing production credentials in version control.
 */

$localConfigFile = __DIR__ . '/db_config.local.php';
$config = file_exists($localConfigFile) ? include $localConfigFile : [];

$host = $config['host'] ?? getenv('DB_HOST') ?? $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $config['dbname'] ?? getenv('DB_NAME') ?? $_ENV['DB_NAME'] ?? 'notigolfo_db';
$username = $config['username'] ?? getenv('DB_USER') ?? $_ENV['DB_USER'] ?? 'root';
$password = $config['password'] ?? getenv('DB_PASS') ?? $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("No se pudo conectar a la base de datos. Verifique la configuración del servidor.");
}
