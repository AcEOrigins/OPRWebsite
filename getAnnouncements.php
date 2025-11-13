<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * getAnnouncements.php - Announcement Retrieval Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Returns a list of announcements from the database.
 * Supports optional filtering by server, BattleMetrics ID, and active window.
 * 
 * REQUEST
 * ───────
 * GET /getAnnouncements.php?serverId=1&battlemetricsId=123456&active=1
 * (All parameters optional)
 * 
 * QUERY PARAMETERS
 * ────────────────
 * - serverId: Filter by server ID (+ global announcements)
 * - battlemetricsId: Filter by BattleMetrics ID (+ global announcements)
 * - active: 1 = only currently active announcements
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * [
 *   {
 *     "id": 1,
 *     "message": "Server maintenance tonight",
 *     "severity": "warning",
 *     "starts_at": "2024-01-15 20:00:00",
 *     "ends_at": "2024-01-15 23:00:00",
 *     "server_id": 1,
 *     "server_name": "US Server 1",
 *     "battlemetrics_id": "123456"
 *   },
 *   ...
 * ]
 * 
 * NOTE: Returns raw array (not wrapped in {success: true}) for backward compatibility
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: RESPONSE CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: LOAD DEPENDENCIES
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/AnnouncementController.php';
require_once __DIR__ . '/dbconnect.php';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: PARSE QUERY PARAMETERS
// ─────────────────────────────────────────────────────────────────────────────────

$filters = [];

if (isset($_GET['serverId'])) {
    $filters['serverId'] = (int)$_GET['serverId'];
}

if (isset($_GET['battlemetricsId'])) {
    $filters['battlemetricsId'] = trim((string)$_GET['battlemetricsId']);
}

if (isset($_GET['active'])) {
    $filters['activeOnly'] = (int)$_GET['active'];
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$announcements = AnnouncementController::getAnnouncements($conn, $filters);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

// Return JSON (preserve original: raw array of objects for backward compatibility)
echo json_encode($announcements);

?>

