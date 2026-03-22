<?php

$host = 'mysql.railway.internal';
$dbname = 'railway';
$username = 'admin';
$password = 'MyNewPass123!';
$port = 3306;

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'error'   => 'DB connection failed: ' . $conn->connect_error
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

?>

