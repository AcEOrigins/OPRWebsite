<?php
declare(strict_types=1);

// Configure session settings for better security and compatibility
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
	http_response_code(405);
	exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
	exit;
}

$name = isset($payload['name']) ? trim((string)$payload['name']) : '';
$password = isset($payload['password']) ? (string)$payload['password'] : '';

if ($name === '' || $password === '') {
	http_response_code(422);
	echo json_encode(['success' => false, 'message' => 'Name and password are required.']);
	exit;
}

require_once __DIR__ . '/dbconnect.php';

// Fetch user from database
$stmt = $conn->prepare("SELECT id, name, password_hash, role, is_active FROM users WHERE name = ? LIMIT 1");
if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Unable to process request.']);
	$conn->close();
	exit;
}
$stmt->bind_param('s', $name);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Invalid credentials. Please try again.']);
	$conn->close();
	exit;
}

// Authenticated — create session
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

$conn->close();

echo json_encode(['success' => true, 'redirectUrl' => 'portal.html']);

?>

