<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * saveServer.php - Server Creation/Update Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Creates a new server or updates an existing server in the database.
 * Enriches server data from BattleMetrics API (if available).
 * 
 * REQUEST
 * ───────
 * POST /saveServer.php
 * Content-Type: application/json
 * Body: { "battlemetricsId": "123456" }
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "data": {
 *     "server": {
 *       "id": 1,
 *       "battlemetrics_id": "123456",
 *       "display_name": "US Rust Server",
 *       "game_title": "Rust",
 *       "region": "us-east-1"
 *     }
 *   }
 * }
 * 
 * RESPONSE (ERROR)
 * ───────────────
 * HTTP 400 Bad Request
 * { "success": false, "message": "BattleMetrics ID is required." }
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: LOAD DEPENDENCIES
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/ServerController.php';
require_once __DIR__ . '/lib/ApiResponse.php';
require_once __DIR__ . '/dbconnect.php';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: VALIDATE HTTP METHOD
// ─────────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed();
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: PARSE INPUT
// ─────────────────────────────────────────────────────────────────────────────────

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$battlemetricsId = $input['battlemetricsId'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$result = ServerController::saveServer($battlemetricsId, $conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    ApiResponse::success(['server' => $result['server']]);
} else {
    ApiResponse::error($result['message'], $result['code'] ?? 400);
}

?>

