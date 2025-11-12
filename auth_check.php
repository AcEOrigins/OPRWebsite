<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

$isAuthenticated = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

if (!$isAuthenticated) {
	echo json_encode([
		'success' => true,
		'authenticated' => false
	]);
	exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = (string)($_SESSION['user_name'] ?? '');
$role = 'admin';

require_once __DIR__ . '/dbconnect.php';

$stmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
if ($stmt) {
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$stmt->close();
	if ($row && isset($row['role'])) {
		$role = (string)$row['role'];
	}
}

$conn->close();

echo json_encode([
	'success' => true,
	'authenticated' => true,
	'userId' => $userId,
	'userName' => $userName,
	'role' => $role
]);


