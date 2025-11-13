<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * getServers.php - Server List Retrieval Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Returns a list of all ACTIVE servers from the database.
 * Called by: portal.html (to populate server list on admin portal)
 * 
 * REQUEST
 * ───────
 * GET /getServers.php
 * (No parameters required)
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * [
 *   {
 *     "id": 1,
 *     "battlemetrics_id": "123456",
 *     "display_name": "US Server 1",
 *     "game_title": "Rust",
 *     "region": "us-east-1"
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

header('Content-Type: application/json; charset=utf-8');

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: LOAD DEPENDENCIES
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/api_common.php';
require_once __DIR__ . '/lib/db.php';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: FETCH SERVERS
// ─────────────────────────────────────────────────────────────────────────────────

$conn = get_db_conn();
$servers = fetch_active_servers($conn);
$conn->close();

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

// Return JSON (preserve original: raw array of objects for backward compatibility)
echo json_encode($servers);

?>

