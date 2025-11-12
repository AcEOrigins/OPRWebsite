<?php
/**
 * =============================================================================
 * deleteAnnouncement.php - Announcement Soft-Delete Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Soft-deletes an announcement (marks as inactive, doesn't remove from database).
 * Also auto-sets ends_at to NOW() if not already set (graceful end).
 * Called by: portal.html (admin portal to remove announcements)
 * 
 * REQUEST:
 * --------
 * POST /deleteAnnouncement.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "id": 5
 * }
 * 
 * RESPONSE (SUCCESS):
 * -------------------
 * HTTP 200 OK
 * {
 *   "success": true
 * }
 * 
 * RESPONSE (VALIDATION ERROR):
 * ----------------------------
 * HTTP 422 Unprocessable Entity
 * {
 *   "success": false,
 *   "message": "Invalid announcement ID."
 * }
 * 
 * RESPONSE (NOT FOUND):
 * --------------------
 * HTTP 404 Not Found
 * {
 *   "success": false,
 *   "message": "Announcement not found."
 * }
 * 
 * RESPONSE (METHOD ERROR):
 * ------------------------
 * HTTP 405 Method Not Allowed
 * {
 *   "success": false,
 *   "message": "Method not allowed."
 * }
 * 
 * RESPONSE (SERVER ERROR):
 * -----------------------
 * HTTP 500 Internal Server Error
 * {
 *   "success": false,
 *   "message": "Failed to prepare statement."
 * }
 * 
 * DATA FLOW:
 * ----------
 * 1. Validate HTTP method (POST only)
 * 2. Create table if doesn't exist (idempotent)
 * 3. Parse JSON request body
 * 4. Extract and validate announcement ID
 * 5. Update announcement: is_active = 0, ends_at = NOW() (if not set)
 * 6. Check if any rows were actually updated
 * 7. Return success or not found error
 * 
 * SOFT-DELETE WITH GRACEFUL END:
 * ─────────────────────────────
 * Normal soft-delete:  UPDATE announcements SET is_active = 0 WHERE id = ?
 * This endpoint:       UPDATE announcements SET is_active = 0, ends_at = IFNULL(ends_at, NOW())
 * 
 * Difference:
 * - If announcement has ends_at: Leave it as-is (respects original end time)
 * - If announcement has no ends_at: Set to NOW() (graceful immediate end)
 * 
 * Example scenarios:
 * 
 * 1. Announcement with specific end time:
 *    Before: is_active=1, starts_at='2024-01-15 20:00', ends_at='2024-01-15 23:00'
 *    After:  is_active=0, starts_at='2024-01-15 20:00', ends_at='2024-01-15 23:00'
 *    (ends_at unchanged, respects original schedule)
 * 
 * 2. Announcement with no end time (indefinite):
 *    Before: is_active=1, starts_at='2024-01-15 20:00', ends_at=NULL
 *    After:  is_active=0, starts_at='2024-01-15 20:00', ends_at='2024-01-15 21:30'
 *    (ends_at set to NOW(), graceful stop)
 * 
 * WHY IFNULL?
 * IFNULL(ends_at, NOW()) returns:
 * - ends_at if it's NOT NULL
 * - NOW() if ends_at IS NULL
 * 
 * This prevents overwriting legitimate end times while providing sane default.
 * 
 * SOFT-DELETE PATTERN:
 * -------------------
 * Instead of DELETE FROM announcements WHERE id = ?, we use:
 * UPDATE announcements SET is_active = 0 WHERE id = ?
 * 
 * Benefits:
 * - Data never actually deleted (audit trail, recovery, history)
 * - Relationships intact
 * - Can reactivate if needed (change is_active = 1)
 * - getAnnouncements.php filters WHERE is_active = 1 (soft-deleted hidden)
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized query (prevents SQL injection)
 * ✓ Input validation (announcement ID must be > 0)
 * ✓ Verified update actually occurred (affected_rows check)
 * ✓ 404 response if announcement not found (correct semantics)
 * ✓ IFNULL prevents unintended overwrites of legitimate end times
 * 
 * =============================================================================
 */

declare(strict_types=1);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 1: REQUEST CONFIGURATION                                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * SET RESPONSE CONTENT TYPE
 * 
 * Tell browser: "This response is JSON"
 */
header('Content-Type: application/json');

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: HTTP METHOD VALIDATION                                       │
// │                                                                           │
// │ WHY VALIDATE METHOD?                                                     │
// │ ───────────────────                                                      │
// │ This endpoint modifies data, should ONLY accept POST requests.           │
// │ GET/HEAD are read-only, should not modify state.                        │
// │ Prevents accidental deletion from URL bar or browser prefetch.           │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK HTTP REQUEST METHOD
 * 
 * $_SERVER['REQUEST_METHOD'] contains: GET, POST, PUT, DELETE, PATCH, etc.
 * 
 * Allowed: POST (includes body with data)
 * Rejected: GET, HEAD, PUT, DELETE, PATCH, OPTIONS, etc.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	// ═════════════════════════════════════════════════════════════════════
	// INVALID HTTP METHOD
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 405 = Method Not Allowed
	 * 
	 * Tells client: "This endpoint doesn't support that HTTP method"
	 */
	http_response_code(405);
	
	/**
	 * RETURN ERROR RESPONSE
	 */
	echo json_encode([
		'success' => false,
		'message' => 'Method not allowed.'
	]);
	
	// ✓ Exit early
	exit;
}

// ✓ HTTP METHOD IS VALID (POST)

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: DATABASE CONNECTION & TABLE CREATION                         │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * LOAD DATABASE CONNECTION
 * 
 * Includes dbconnect.php which creates $conn (mysqli object).
 */
require_once __DIR__ . '/dbconnect.php';

/**
 * CREATE TABLE IF NOT EXISTS
 * 
 * (Same schema as in getAnnouncements.php and saveAnnouncement.php)
 * Idempotent: Creates if missing, no-op if exists
 */
$createSql = "
	CREATE TABLE IF NOT EXISTS announcements (
		id INT AUTO_INCREMENT PRIMARY KEY,
		server_id INT NULL,
		message TEXT NOT NULL,
		severity VARCHAR(16) NOT NULL DEFAULT 'info',
		starts_at DATETIME NULL,
		ends_at DATETIME NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		INDEX idx_server_id (server_id),
		INDEX idx_is_active (is_active),
		INDEX idx_starts_at (starts_at),
		INDEX idx_ends_at (ends_at),
		CONSTRAINT fk_announcements_server_id FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

/**
 * EXECUTE TABLE CREATION
 */
$conn->query($createSql);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: PARSE JSON REQUEST BODY                                      │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PARSE JSON REQUEST BODY
 * 
 * file_get_contents('php://input'): Read raw request body
 * json_decode(..., true):            Convert JSON to PHP array
 * 
 * Example input:
 * {
 *   "id": 5
 * }
 * 
 * Becomes:
 * [
 *   "id" => 5
 * ]
 */
$input = json_decode(file_get_contents('php://input'), true);

/**
 * EXTRACT & VALIDATE ANNOUNCEMENT ID
 * 
 * isset($input['id']):  Check key exists in array
 * (int)...:             Cast to integer (type safety)
 * 0:                    Default to 0 if key missing
 * 
 * Result: $id = 5 (if provided) or 0 (if not or invalid)
 */
$id = isset($input['id']) ? (int)$input['id'] : 0;

/**
 * VALIDATE ANNOUNCEMENT ID
 * 
 * Check: $id > 0 (must be positive integer)
 * 
 * Why validation:
 * - ID must exist in database
 * - ID must be positive (0 and negative are invalid/reserved)
 * - Prevents nonsensical queries
 */
if ($id <= 0) {
	// ═════════════════════════════════════════════════════════════════════
	// VALIDATION FAILED: INVALID ANNOUNCEMENT ID
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 422 = Unprocessable Entity
	 * 
	 * Tells client: "JSON is valid, but the data doesn't meet our rules"
	 */
	http_response_code(422);
	
	/**
	 * RETURN VALIDATION ERROR
	 */
	echo json_encode([
		'success' => false,
		'message' => 'Invalid announcement ID.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

// ✓ VALIDATION PASSED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: PREPARE SOFT-DELETE STATEMENT WITH GRACEFUL END              │
// │                                                                           │
// │ SPECIAL FEATURE: IFNULL(ends_at, NOW())                                 │
// │ ──────────────────────────────────────────                               │
// │ Sets ends_at to NOW() only if it's currently NULL.                      │
// │ If ends_at already has a value: Leaves it unchanged.                    │
// │ This provides graceful endpoint without overwriting explicit times.      │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE SOFT-DELETE STATEMENT WITH GRACEFUL END
 * 
 * SQL: UPDATE announcements
 *      SET is_active = 0,
 *          ends_at = IFNULL(ends_at, NOW()),
 *          updated_at = CURRENT_TIMESTAMP
 *      WHERE id = ?
 * 
 * Sets:
 * - is_active = 0                    → Mark announcement as deleted
 * - ends_at = IFNULL(ends_at, NOW()) → Set to NOW() if not already set
 * - updated_at = CURRENT_TIMESTAMP   → Record when this happened
 * 
 * Where:
 * - id = ?                           → Only the specified announcement
 * 
 * IFNULL EXPLANATION:
 * 
 * IFNULL(column, default_value) returns:
 * - column value if column is NOT NULL
 * - default_value if column IS NULL
 * 
 * Example:
 * IFNULL(ends_at, NOW())
 * 
 * Case 1: ends_at = '2024-01-15 23:00'
 * Result: '2024-01-15 23:00' (unchanged)
 * 
 * Case 2: ends_at = NULL
 * Result: NOW() (e.g., '2024-01-15 21:30:00')
 * 
 * WHY USE IFNULL?
 * 
 * When admin deletes an announcement:
 * - If it had specific end time: Respect that (don't change)
 * - If it had no end time: Set to NOW() (graceful stop)
 * 
 * This is more graceful than just setting is_active = 0.
 * Provides visual indicator of when deletion occurred.
 */
$stmt = $conn->prepare("UPDATE announcements SET is_active = 0, ends_at = IFNULL(ends_at, NOW()) WHERE id = ?");

/**
 * CHECK IF PREPARE SUCCEEDED
 * 
 * Might fail if:
 * - Database connection lost
 * - Syntax error in SQL
 * - Table doesn't exist (but we just created it)
 * - Permission denied
 */
if (!$stmt) {
	// ═════════════════════════════════════════════════════════════════════
	// QUERY PREPARATION FAILED
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 500 = Internal Server Error
	 */
	http_response_code(500);
	
	/**
	 * RETURN ERROR RESPONSE
	 */
	echo json_encode([
		'success' => false,
		'message' => 'Failed to prepare statement.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

/**
 * BIND PARAMETER
 * 
 * 'i' = integer type
 * Binds $id to ? placeholder
 * 
 * This is safe from SQL injection: treated as data, not code
 */
$stmt->bind_param('i', $id);

/**
 * EXECUTE THE STATEMENT
 * 
 * Sends query to database.
 * Runs: UPDATE announcements SET is_active = 0, ends_at = IFNULL(ends_at, NOW()) WHERE id = 5 (example)
 */
$stmt->execute();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: CHECK IF UPDATE AFFECTED ANY ROWS                            │
// │                                                                           │
// │ WHY CHECK AFFECTED ROWS?                                                 │
// │ ──────────────────────                                                   │
// │ Execute doesn't tell us if rows were found or not.                       │
// │ affected_rows tells us:                                                  │
// │ • 0 rows: ID doesn't exist (return 404 Not Found)                        │
// │ • 1+ rows: Delete succeeded (return 200 OK)                              │
// │ • -1: Error occurred (shouldn't happen, execute would throw)             │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * GET AFFECTED ROWS COUNT
 * 
 * $stmt->affected_rows returns number of rows modified by last query.
 * 
 * For UPDATE queries:
 * - 0: WHERE clause matched no rows (announcement ID not found)
 * - 1: WHERE clause matched one row (announcement exists, now marked deleted)
 * - >1: Multiple rows matched (shouldn't happen, id is primary key)
 */
$affected = $stmt->affected_rows;

/**
 * CLOSE PREPARED STATEMENT
 * 
 * Free resources.
 */
$stmt->close();

/**
 * CLOSE DATABASE CONNECTION
 */
$conn->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: CHECK IF ANNOUNCEMENT WAS FOUND & DELETED                    │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK AFFECTED ROWS
 * 
 * If $affected === 0: No rows matched the WHERE clause
 * Meaning: Announcement with this ID doesn't exist
 */
if ($affected === 0) {
	// ═════════════════════════════════════════════════════════════════════
	// ANNOUNCEMENT NOT FOUND
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 404 = Not Found
	 * 
	 * Tells client: "The resource you tried to delete doesn't exist"
	 * 
	 * Different from:
	 * - 400 (Bad Request) - request was malformed
	 * - 422 (Unprocessable Entity) - validation failed
	 * - 500 (Server Error) - server-side problem
	 */
	http_response_code(404);
	
	/**
	 * RETURN NOT FOUND ERROR
	 */
	echo json_encode([
		'success' => false,
		'message' => 'Announcement not found.'
	]);
	
	// ✓ Exit early
	exit;
}

// ✓ UPDATE SUCCEEDED - ANNOUNCEMENT WAS FOUND AND MARKED DELETED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 8: SUCCESS RESPONSE                                             │
// │                                                                           │
// │ Announcement has been soft-deleted (is_active = 0)                      │
// │ Will not appear in getAnnouncements.php list (with active=1 filter)     │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * RETURN SUCCESS RESPONSE
 * 
 * HTTP 200: OK
 * 
 * {
 *   "success": true
 * }
 * 
 * Frontend (portal.js) receives this and:
 * 1. Removes announcement from UI list
 * 2. Shows success message
 * 3. Refreshes announcement data if needed
 */
echo json_encode([
	'success' => true
]);

// ═════════════════════════════════════════════════════════════════════════
// ANNOUNCEMENT SOFT-DELETE COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation:
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated announcement ID (must be > 0)
// 3. ✓ Marked announcement as deleted (is_active = 0)
// 4. ✓ Set ends_at gracefully (IFNULL - respects existing, sets to NOW() if null)
// 5. ✓ Verified update occurred (affected_rows check)
// 6. ✓ Data preserved in database (not actually deleted)
// 
// Result: Announcement now hidden from getAnnouncements.php with active=1,
//         but data preserved in database for audit trail and recovery
// 
// ═════════════════════════════════════════════════════════════════════════

?>


