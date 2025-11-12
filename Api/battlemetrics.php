<?php
// =========================================
// battlemetrics.php
// Secure proxy for BattleMetrics API
// =========================================

// IMPORTANT: Keep this file server-side only and never expose API key in JS

$apiKey = getenv('BATTLEMETRICS_API_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbiI6IjI4MDlmZjRkMDVjYWMxZjEiLCJpYXQiOjE3NjI2NTAwMTMsIm5iZiI6MTc2MjY1MDAxMywiaXNzIjoiaHR0cHM6Ly93d3cuYmF0dGxlbWV0cmljcy5jb20iLCJzdWIiOiJ1cm46dXNlcjoxMDMyMzk1In0.Xfol6h4NxOnufPP76UQFO6NM0bcw95hQLGcJ94V69QE';

if (!$apiKey) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'BattleMetrics API key is not configured.']);
    exit;
}

// Validate input
$serverId = isset($_GET['serverId']) ? trim($_GET['serverId']) : null;

if (!$serverId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing serverId']);
    exit;
}

$apiUrl = sprintf(
    'https://api.battlemetrics.com/servers/%s?include=player,serverSettings,conflict',
    urlencode($serverId)
);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiKey}",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL error', 'details' => $error]);
    exit;
}

curl_close($ch);

http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;
