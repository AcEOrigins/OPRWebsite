<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * battlemetrics.php - Secure Proxy for BattleMetrics API
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Secure server-side proxy to BattleMetrics API using BATTLEMETRICS_API_KEY.
 * Returns raw API JSON with original status code.
 * 
 * IMPORTANT: Keep this file server-side only and never expose API key in JS
 * 
 * REQUEST
 * ───────
 * GET /battlemetrics.php?serverId=123456
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK (or original API status code)
 * { ... BattleMetrics API response ... }
 * 
 * RESPONSE (ERROR)
 * ───────────────
 * HTTP 400 Bad Request
 * { "error": "Missing serverId" }
 * 
 * HTTP 500 Internal Server Error
 * { "error": "BattleMetrics API key is not configured." }
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: GET API KEY
// ─────────────────────────────────────────────────────────────────────────────────

$apiKey = getenv('BATTLEMETRICS_API_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbiI6IjI4MDlmZjRkMDVjYWMxZjEiLCJpYXQiOjE3NjI2NTAwMTMsIm5iZiI6MTc2MjY1MDAxMywiaXNzIjoiaHR0cHM6Ly93d3cuYmF0dGxlbWV0cmljcy5jb20iLCJzdWIiOiJ1cm46dXNlcjoxMDMyMzk1In0.Xfol6h4NxOnufPP76UQFO6NM0bcw95hQLGcJ94V69QE';

if (!$apiKey) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'BattleMetrics API key is not configured.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: VALIDATE INPUT
// ─────────────────────────────────────────────────────────────────────────────────

$serverId = isset($_GET['serverId']) ? trim($_GET['serverId']) : null;

if (!$serverId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing serverId']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: BUILD API URL
// ─────────────────────────────────────────────────────────────────────────────────

$apiUrl = sprintf(
    'https://api.battlemetrics.com/servers/%s?include=player,serverSettings,conflict',
    urlencode($serverId)
);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: EXECUTE CURL REQUEST
// ─────────────────────────────────────────────────────────────────────────────────

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiKey}",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: HANDLE ERRORS
// ─────────────────────────────────────────────────────────────────────────────────

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL error', 'details' => $error]);
    exit;
}

curl_close($ch);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 6: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;

?>

