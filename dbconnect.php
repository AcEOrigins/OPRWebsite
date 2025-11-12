<?php
// =========================================
// db_connect.php
// Shared database connection (JSON-safe)
// =========================================

$DB_HOST = 'localhost';              // Usually 'localhost' on Hostinger
$DB_NAME = 'u775021278_battleMetrics';           // e.g. u123456_serverdb
$DB_USER = 'u775021278_OPRBM';           // e.g. u123456_serveruser
$DB_PASS = 'Pq8137!2';       // your MySQL user password

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode([
		'error' => 'Database connection failed.',
		'details' => $conn->connect_error
	]);
	exit;
}
$conn->set_charset("utf8mb4");