<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * deleteServer.php - Server Soft-Delete Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Soft-deletes a server (marks as inactive, doesn't remove from database).
 * Called by: portal.html (admin portal to remove servers from view)
 * 
 * REQUEST
 * ───────
 * POST /deleteServer.php
 * Content-Type: application/json
 * Body: { "id": 1 }
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * { "success": true, "data": {} }
 * 
 * RESPONSE (ERROR)
 * ───────────────
 * HTTP 422 Unprocessable Entity
 * { "success": false, "message": "Invalid server ID." }
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
// SECTION 3: PARSE AND VALIDATE INPUT
// ─────────────────────────────────────────────────────────────────────────────────

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$serverId = isset($input['id']) ? (int)$input['id'] : 0;

if ($serverId <= 0) {
    ApiResponse::validationError('Invalid server ID.');
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$result = ServerController::deleteServer($serverId, $conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    ApiResponse::success();
} else {
    ApiResponse::error($result['message'], 500);
}

?>

