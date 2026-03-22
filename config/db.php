<?php

$host = "sql201.infinityfree.com";
$user = "if0_41390057";
$password = "83pbNoXy2dceA";
$database = "if0_41390057_academy";
$port = 3306;

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    // Return JSON so the browser gets a readable error instead of a 500 crash
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

$conn->set_charset("utf8mb4");

?>

