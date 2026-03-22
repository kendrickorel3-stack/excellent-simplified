<?php
$host = 'ballast.proxy.rlwy.net';
$dbname = 'railway';
$username = 'admin';
$password = 'MyNewPass123!';
$port = 14518;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    echo "Database connected successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
