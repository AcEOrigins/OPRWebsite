<?php
// =========================================
// saveServer.php
// Adds or updates a server record
// =========================================

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/dbconnect.php';

$input = json_decode(file_get_contents('php://input'), true);
$battlemetricsId = isset($input['battlemetricsId']) ? trim((string)$input['battlemetricsId']) : '';

if ($battlemetricsId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'BattleMetrics ID is required.']);
    $conn->close();
    exit;
}

// Try to enrich from BattleMetrics, but do NOT fail if unavailable
$displayName = '';
$gameTitle = '';
$region = '';

$apiKey = getenv('BATTLEMETRICS_API_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbiI6IjI4MDlmZjRkMDVjYWMxZjEiLCJpYXQiOjE3NjI2NTAwMTMsIm5iZiI6MTc2MjY1MDAxMywiaXNzIjoiaHR0cHM6Ly93d3cuYmF0dGxlbWV0cmljcy5jb20iLCJzdWIiOiJ1cm46dXNlcjoxMDMyMzk1In0.Xfol6h4NxOnufPP76UQFO6NM0bcw95hQLGcJ94V69QE';
if (!empty($apiKey)) {
    $apiUrl = sprintf('https://api.battlemetrics.com/servers/%s', urlencode($battlemetricsId));
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$apiKey}",
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response !== false && $httpCode < 400) {
        $payload = json_decode($response, true);
        $attributes = $payload['data']['attributes'] ?? [];
        $details = $attributes['details'] ?? [];
        $displayName = $attributes['name'] ?? $attributes['hostname'] ?? '';
        $gameTitle = $attributes['game'] ?? $details['gameMode'] ?? $details['mode'] ?? '';
        $region = $attributes['region'] ?? $details['region'] ?? '';
    }
    curl_close($ch);
}

// Fallbacks if API enrichment failed
if ($displayName === '') {
    $displayName = 'Server ' . $battlemetricsId;
}

$statement = $conn->prepare("
    INSERT INTO servers (battlemetrics_id, display_name, game_title, region, is_active)
    VALUES (?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        display_name = VALUES(display_name),
        game_title = VALUES(game_title),
        region = VALUES(region),
        is_active = 1,
        updated_at = CURRENT_TIMESTAMP
");

if (!$statement) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
    $conn->close();
    exit;
}

$statement->bind_param('ssss', $battlemetricsId, $displayName, $gameTitle, $region);

if (!$statement->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save server.', 'details' => $statement->error]);
    $statement->close();
    $conn->close();
    exit;
}

$statement->close();

$select = $conn->prepare("SELECT id, battlemetrics_id, display_name, game_title, region FROM servers WHERE battlemetrics_id = ? LIMIT 1");

if (!$select) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load saved server.']);
    $conn->close();
    exit;
}

$select->bind_param('s', $battlemetricsId);
$select->execute();
$result = $select->get_result();
$server = $result->fetch_assoc() ?: null;
$select->close();
$conn->close();

echo json_encode([
    'success' => true,
    'server' => $server
]);

