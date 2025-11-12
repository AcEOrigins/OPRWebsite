<?php
// =========================================
// getServers.php
// Returns active servers as JSON
// =========================================

require_once __DIR__ . '/dbconnect.php';

header('Content-Type: application/json');

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
