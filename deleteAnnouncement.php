<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * deleteAnnouncement.php - Announcement Soft-Delete Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Soft-deletes an announcement (marks as inactive, doesn't remove from database).
 * Also auto-sets ends_at to NOW() if not already set (graceful end).
 * 
 * REQUEST
 * ───────
 * POST /deleteAnnouncement.php
 * Content-Type: application/json
 * Body: { "id": 5 }
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * { "success": true, "data": {} }
 * 
 * RESPONSE (ERROR)
 * ───────────────
 * HTTP 422 Unprocessable Entity
 * { "success": false, "message": "Invalid announcement ID." }
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: LOAD DEPENDENCIES
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/AnnouncementController.php';
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
$announcementId = isset($input['id']) ? (int)$input['id'] : 0;

if ($announcementId <= 0) {
    ApiResponse::validationError('Invalid announcement ID.');
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$result = AnnouncementController::deleteAnnouncement($announcementId, $conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    ApiResponse::success();
} else {
    ApiResponse::error($result['message'], 500);
}

?>

