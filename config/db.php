<?php
$host = 'ballast.proxy.rlwy.net';
$dbname = 'railway';
$username = 'admin';
$password = 'MyNewPass123!';
$port = 14518;

// MySQLi connection (used by most files)
$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// PDO connection (used by some files)
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'PDO failed: ' . $e->getMessage()]));
}
?>
