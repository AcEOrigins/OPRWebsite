<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
	exit;
}

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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$name = isset($input['name']) ? trim((string)$input['name']) : '';
$password = isset($input['password']) ? (string)$input['password'] : '';
$role = isset($input['role']) ? trim((string)$input['role']) : 'admin';

if ($name === '' || $password === '') {
	http_response_code(422);
	echo json_encode(['success' => false, 'message' => 'Name and password are required.']);
	$conn->close();
	exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, password_hash, role, is_active) VALUES (?, ?, ?, 1)");
if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
	$conn->close();
	exit;
}
$stmt->bind_param('sss', $name, $hash, $role);

if (!$stmt->execute()) {
	$err = $stmt->error;
	$stmt->close();
	$conn->close();

	if (strpos($err, 'Duplicate') !== false) {
		http_response_code(409);
		echo json_encode(['success' => false, 'message' => 'User already exists.']);
	} else {
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Failed to create user.']);
	}
	exit;
}

$newId = $stmt->insert_id;
$stmt->close();

$get = $conn->prepare("SELECT id, name, role, is_active, created_at FROM users WHERE id = ? LIMIT 1");
if ($get) {
	$get->bind_param('i', $newId);
	$get->execute();
	$res = $get->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$get->close();
} else {
	$row = null;
}

$conn->close();

echo json_encode(['success' => true, 'user' => $row]);


