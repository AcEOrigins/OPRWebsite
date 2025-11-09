<?php
// =========================================
// deleteServer.php
// Soft deletes (deactivates) a server record
// =========================================

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/dbconnect.php';

$input = json_decode(file_get_contents('php://input'), true);
$serverId = isset($input['id']) ? (int)$input['id'] : 0;

if ($serverId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid server ID.']);
    $conn->close();
    exit;
}

$statement = $conn->prepare("UPDATE servers SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");

if (!$statement) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
    $conn->close();
    exit;
}

$statement->bind_param('i', $serverId);
$statement->execute();

$affected = $statement->affected_rows;
$statement->close();
$conn->close();

if ($affected === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Server not found.']);
    exit;
}

echo json_encode(['success' => true]);

