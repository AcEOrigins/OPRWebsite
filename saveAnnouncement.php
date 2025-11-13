<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * saveAnnouncement.php - Announcement Creation Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Creates a new announcement in the database.
 * Called by: portal.html (admin portal to create announcements)
 * 
 * REQUEST
 * ───────
 * POST /saveAnnouncement.php
 * Content-Type: application/json
 * Body: {
 *   "message": "Server maintenance tonight",
 *   "severity": "warning",        (optional, default="info")
 *   "serverId": 1,                 (optional, null=global announcement)
 *   "startsAt": "2024-01-15T20:00", (optional, datetime-local format)
 *   "endsAt": "2024-01-15T23:00",   (optional, datetime-local format)
 *   "isActive": 1                  (optional, default=1)
 * }
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "data": {
 *     "announcement": { ... },
 *     "id": 5
 *   }
 * }
 * 
 * RESPONSE (ERROR)
 * ───────────────
 * HTTP 422 Unprocessable Entity
 * { "success": false, "message": "Message is required." }
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
// SECTION 3: PARSE INPUT
// ─────────────────────────────────────────────────────────────────────────────────

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$result = AnnouncementController::saveAnnouncement($input, $conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    ApiResponse::success([
        'announcement' => $result['announcement'],
        'id' => $result['id']
    ]);
} else {
    ApiResponse::error($result['message'], 422);
}

?>

