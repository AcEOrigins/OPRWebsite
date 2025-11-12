<?php
// =========================================
// getServers.php
// Returns active servers as JSON
// =========================================

header('Content-Type: application/json');

require_once __DIR__ . '/dbconnect.php';

// Ensure servers table exists
$createTableSql = "
    CREATE TABLE IF NOT EXISTS servers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        battlemetrics_id VARCHAR(50) NOT NULL UNIQUE,
        display_name VARCHAR(255) NOT NULL,
        game_title VARCHAR(255) DEFAULT NULL,
        region VARCHAR(100) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_is_active (is_active),
        INDEX idx_sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
@$conn->query($createTableSql);

$sql = "SELECT id, battlemetrics_id, display_name, game_title, region
        FROM servers
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC";

$servers = [];

try {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $servers[] = $row;
        }
        $result->free();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load servers.',
        'details' => $e->getMessage()
    ]);
    $conn->close();
    exit;
}

$conn->close();

echo json_encode($servers);
