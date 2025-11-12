<?php
// =========================================
// deleteAnnouncement.php
// Soft-deletes (deactivates) an announcement
// =========================================

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
	exit;
}

require_once __DIR__ . '/dbconnect.php';

// Ensure table exists (idempotent)
$createSql = "
	CREATE TABLE IF NOT EXISTS announcements (
		id INT AUTO_INCREMENT PRIMARY KEY,
		server_id INT NULL,
		message TEXT NOT NULL,
		severity VARCHAR(16) NOT NULL DEFAULT 'info',
		starts_at DATETIME NULL,
		ends_at DATETIME NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		INDEX idx_server_id (server_id),
		INDEX idx_is_active (is_active),
		INDEX idx_starts_at (starts_at),
		INDEX idx_ends_at (ends_at),
		CONSTRAINT fk_announcements_server_id FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($createSql);

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
	http_response_code(422);
	echo json_encode(['success' => false, 'message' => 'Invalid announcement ID.']);
	$conn->close();
	exit;
}

$stmt = $conn->prepare("UPDATE announcements SET is_active = 0, ends_at = IFNULL(ends_at, NOW()) WHERE id = ?");
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
	echo json_encode(['success' => false, 'message' => 'Announcement not found.']);
	exit;
}

echo json_encode(['success' => true]);


