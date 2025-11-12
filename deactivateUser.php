<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
	exit;
}

require_once __DIR__ . '/dbconnect.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
	http_response_code(422);
	echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
	$conn->close();
	exit;
}

$stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
	$conn->close();
	exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

if ($affected === 0) {
	http_response_code(404);
	echo json_encode(['success' => false, 'message' => 'User not found.']);
	exit;
}

echo json_encode(['success' => true]);


