<?php
// =========================================
// getAnnouncements.php
// Returns announcements as JSON (optionally filtered)
// =========================================

header('Content-Type: application/json');

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

$serverId = isset($_GET['serverId']) ? (int)$_GET['serverId'] : 0;
$battlemetricsId = isset($_GET['battlemetricsId']) ? trim((string)$_GET['battlemetricsId']) : '';
$activeOnly = isset($_GET['active']) ? (int)$_GET['active'] : 0;

$conditions = [];
$params = [];
$types = '';

// Filter by serverId if provided
if ($serverId > 0) {
	$conditions[] = '(a.server_id = ? OR a.server_id IS NULL)'; // match specific or global
	$types .= 'i';
	$params[] = $serverId;
}

// Filter by battlemetricsId if provided (map to server)
if ($battlemetricsId !== '') {
	$conditions[] = '(s.battlemetrics_id = ? OR a.server_id IS NULL)';
	$types .= 's';
	$params[] = $battlemetricsId;
}

// Active window filter
if ($activeOnly === 1) {
	$conditions[] = 'a.is_active = 1';
	$conditions[] = '(a.starts_at IS NULL OR a.starts_at <= NOW())';
	$conditions[] = '(a.ends_at IS NULL OR a.ends_at >= NOW())';
}

$where = '';
if (!empty($conditions)) {
	$where = 'WHERE ' . implode(' AND ', $conditions);
}

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
	{$where}
	ORDER BY a.created_at DESC, a.id DESC
";

try {
	if ($types !== '') {
		$stmt = $conn->prepare($sql);
		if (!$stmt) {
			throw new Exception('Failed to prepare statement.');
		}
		$stmt->bind_param($types, ...$params);
		$stmt->execute();
		$result = $stmt->get_result();
	} else {
		$result = $conn->query($sql);
	}

	$rows = [];
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		if ($result instanceof mysqli_result) {
			$result->free();
		}
		if (isset($stmt)) {
			$stmt->close();
		}
	}

	echo json_encode($rows);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error' => 'Failed to load announcements.',
		'details' => $e->getMessage()
	]);
} finally {
	$conn->close();
}

