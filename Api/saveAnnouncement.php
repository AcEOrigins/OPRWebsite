<?php
// =========================================
// saveAnnouncement.php
// Creates a new announcement
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

$message = isset($input['message']) ? trim((string)$input['message']) : '';
$severity = isset($input['severity']) ? strtolower(trim((string)$input['severity'])) : 'info';
$serverId = isset($input['serverId']) && $input['serverId'] !== '' ? (int)$input['serverId'] : null; // null = all servers
$startsAt = isset($input['startsAt']) ? trim((string)$input['startsAt']) : '';
$endsAt = isset($input['endsAt']) ? trim((string)$input['endsAt']) : '';
$isActive = isset($input['isActive']) ? (int)(!!$input['isActive']) : 1;

if ($message === '') {
	http_response_code(422);
	echo json_encode(['success' => false, 'message' => 'Message is required.']);
	$conn->close();
	exit;
}

$allowedSeverities = ['info', 'success', 'warning', 'error'];
if (!in_array($severity, $allowedSeverities, true)) {
	$severity = 'info';
}

// Normalize datetime-local to 'Y-m-d H:i:s'
function normalizeDateTime(?string $val): ?string {
	if ($val === null || $val === '') {
		return null;
	}
	$val = str_replace('T', ' ', $val);
	$ts = strtotime($val);
	if ($ts === false) {
		return null;
	}
	return date('Y-m-d H:i:s', $ts);
}

$startsAtDb = normalizeDateTime($startsAt);
$endsAtDb = normalizeDateTime($endsAt);

$stmt = $conn->prepare("
	INSERT INTO announcements (server_id, message, severity, starts_at, ends_at, is_active)
	VALUES (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
	$conn->close();
	exit;
}

// bind_param requires variables passed by reference
$serverIdParam = $serverId; // may be null
$messageParam = $message;
$severityParam = $severity;
$startsAtParam = $startsAtDb;
$endsAtParam = $endsAtDb;
$isActiveParam = $isActive;

$stmt->bind_param(
	'issssi',
	$serverIdParam,
	$messageParam,
	$severityParam,
	$startsAtParam,
	$endsAtParam,
	$isActiveParam
);

if (!$stmt->execute()) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to save announcement.', 'details' => $stmt->error]);
	$stmt->close();
	$conn->close();
	exit;
}

$insertId = $stmt->insert_id;
$stmt->close();

// Fetch inserted row
$sql = "
	SELECT
		a.id,
		a.message,
		a.severity,
		a.starts_at,
		a.ends_at,
		a.is_active,
		a.created_at,
		a.updated_at,
		a.server_id,
		s.display_name AS server_name,
		s.battlemetrics_id
	FROM announcements a
	LEFT JOIN servers s ON s.id = a.server_id
	WHERE a.id = ?
	LIMIT 1
";
$stmt2 = $conn->prepare($sql);
if ($stmt2) {
	$stmt2->bind_param('i', $insertId);
	$stmt2->execute();
	$res = $stmt2->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$stmt2->close();
} else {
	$row = null;
}

$conn->close();

echo json_encode([
	'success' => true,
	'announcement' => $row,
	'id' => $insertId
]);


