<?php
header('Content-Type: application/json');

require_once __DIR__ . '/dbconnect.php';

$createSql = "
	CREATE TABLE IF NOT EXISTS users (
		id INT AUTO_INCREMENT PRIMARY KEY,
		name VARCHAR(100) NOT NULL UNIQUE,
		password_hash VARCHAR(255) NOT NULL,
		role VARCHAR(32) NOT NULL DEFAULT 'admin',
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($createSql);

$result = $conn->query("SELECT id, name, role, is_active, created_at, updated_at FROM users ORDER BY name ASC");

$rows = [];
if ($result) {
	while ($row = $result->fetch_assoc()) {
		$rows[] = $row;
	}
	$result->free();
}

$conn->close();

echo json_encode($rows);


