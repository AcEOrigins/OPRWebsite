<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * listUsers.php - User List Retrieval Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Returns a list of ALL users (both active and inactive) from the database.
 * Called by: portal.html (admin portal, Manage Access tab to list users)
 * 
 * REQUEST
 * ───────
 * GET /listUsers.php
 * (No parameters required)
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * [
 *   {
 *     "id": 1,
 *     "name": "admin",
 *     "role": "owner",
 *     "is_active": 1,
 *     "created_at": "2024-01-01 09:00:00",
 *     "updated_at": "2024-01-01 09:00:00"
 *   },
 *   ...
 * ]
 * 
 * NOTE: Returns raw array (not wrapped in {success: true}) for backward compatibility
 * NOTE: Does NOT return password_hash (security)
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

require_once __DIR__ . '/lib/UserController.php';
require_once __DIR__ . '/dbconnect.php';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$users = UserController::listUsers($conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

// Return JSON (preserve original: raw array of objects for backward compatibility)
echo json_encode($users);

?>

