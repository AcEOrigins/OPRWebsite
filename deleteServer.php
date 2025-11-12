<?php
/**
 * =============================================================================
 * deleteServer.php - Server Soft-Delete Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Soft-deletes a server (marks as inactive, doesn't remove from database).
 * Called by: portal.html (admin portal to remove servers from view)
 * 
 * REQUEST:
 * --------
 * POST /deleteServer.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "id": 1
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
 *   "message": "Invalid server ID."
 * }
 * 
 * RESPONSE (NOT FOUND):
 * --------------------
 * HTTP 404 Not Found
 * {
 *   "success": false,
 *   "message": "Server not found."
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
 * 2. Parse JSON request body
 * 3. Extract and validate server ID
 * 4. Update server record: is_active = 0
 * 5. Check if any rows were actually updated
 * 6. Return success or not found error
 * 
 * SOFT-DELETE PATTERN:
 * -------------------
 * Instead of DELETE FROM servers WHERE id = ?, we use:
 * UPDATE servers SET is_active = 0 WHERE id = ?
 * 
 * Benefits:
 * - Data never actually deleted (audit trail, recovery)
 * - getServers.php filters WHERE is_active = 1 (user sees deleted as gone)
 * - Can reactivate if needed
 * - Foreign key relationships preserved
 * 
 * AFFECTED ROWS CHECK:
 * -------------------
 * affected_rows indicates how many rows were modified:
 * - 0: Server ID not found (no rows matched WHERE clause)
 * - 1: Server was successfully updated
 * - -1: Error during execution
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized query (prevents SQL injection)
 * ✓ Input validation (server ID must be > 0)
 * ✓ Verified update actually occurred (affected_rows check)
 * ✓ 404 response if server not found (correct semantics)
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
// │ Prevents accidental deletion from URL bar or prefetch.                   │
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
// │ SECTION 3: DATABASE CONNECTION & INPUT PARSING                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * LOAD DATABASE CONNECTION
 * 
 * Includes dbconnect.php which creates $conn (mysqli object).
 */
require_once __DIR__ . '/dbconnect.php';

/**
 * PARSE JSON REQUEST BODY
 * 
 * file_get_contents('php://input'): Read raw request body
 * json_decode(..., true):            Convert JSON to PHP array
 * 
 * Example input:
 * {
 *   "id": 1
 * }
 * 
 * Becomes:
 * [
 *   "id" => 1
 * ]
 */
$input = json_decode(file_get_contents('php://input'), true);

/**
 * EXTRACT & VALIDATE SERVER ID
 * 
 * isset($input['id']):  Check key exists in array
 * (int)...:             Cast to integer (type safety)
 * 0:                    Default to 0 if key missing
 * 
 * Result: $serverId = 1 (if provided) or 0 (if not or invalid)
 */
$serverId = isset($input['id']) ? (int)$input['id'] : 0;

/**
 * VALIDATE SERVER ID
 * 
 * Check: $serverId > 0 (must be positive integer)
 * 
 * Why validation:
 * - ID must exist in database
 * - ID must be positive (0 and negative are invalid/reserved)
 * - Prevents nonsensical queries
 */
if ($serverId <= 0) {
	// ═════════════════════════════════════════════════════════════════════
	// VALIDATION FAILED: INVALID SERVER ID
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
		'message' => 'Invalid server ID.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

// ✓ VALIDATION PASSED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: PREPARE SOFT-DELETE STATEMENT                                │
// │                                                                           │
// │ SOFT-DELETE PATTERN EXPLANATION:                                         │
// │ ────────────────────────────────                                         │
// │ Instead of: DELETE FROM servers WHERE id = ?                             │
// │ We do:      UPDATE servers SET is_active = 0 WHERE id = ?                │
// │                                                                           │
// │ BENEFITS:                                                                │
// │ • Data preserved (audit trail, recovery, history)                        │
// │ • Relationships intact (foreign keys still valid)                        │
// │ • Can reactivate if needed                                               │
// │ • Frontend: Filters WHERE is_active = 1 (soft-deleted hidden)            │
// │                                                                           │
// │ EXAMPLE:                                                                 │
// │ Before: servers table has { id: 1, is_active: 1, name: "US-1" }        │
// │ After:  servers table has { id: 1, is_active: 0, name: "US-1" }        │
// │ Result: Server doesn't appear in getServers.php list (WHERE is_active=1)│
// │ But:    Data still in database for audit/recovery                       │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE UPDATE STATEMENT
 * 
 * SQL: UPDATE servers SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?
 * 
 * Sets:
 * - is_active = 0          → Mark server as deleted
 * - updated_at = CURRENT_TIMESTAMP → Record when this happened
 * 
 * Where:
 * - id = ?                 → Only the specified server
 */
$statement = $conn->prepare("UPDATE servers SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");

/**
 * CHECK IF PREPARE SUCCEEDED
 * 
 * Might fail if:
 * - Database connection lost
 * - Syntax error in SQL
 * - Table doesn't exist
 * - Permission denied
 */
if (!$statement) {
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
 * Binds $serverId to ? placeholder
 * 
 * This is safe from SQL injection: treated as data, not code
 */
$statement->bind_param('i', $serverId);

/**
 * EXECUTE THE STATEMENT
 * 
 * Sends query to database.
 * Runs: UPDATE servers SET is_active = 0 WHERE id = 1 (example)
 */
$statement->execute();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: CHECK IF UPDATE AFFECTED ANY ROWS                            │
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
 * $statement->affected_rows returns number of rows modified by last query.
 * 
 * For UPDATE queries:
 * - 0: WHERE clause matched no rows (server ID not found)
 * - 1: WHERE clause matched one row (server exists, now marked deleted)
 * - >1: Multiple rows matched (shouldn't happen, id is primary key)
 */
$affected = $statement->affected_rows;

/**
 * CLOSE PREPARED STATEMENT
 * 
 * Free resources.
 */
$statement->close();

/**
 * CLOSE DATABASE CONNECTION
 */
$conn->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: CHECK IF SERVER WAS FOUND & DELETED                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK AFFECTED ROWS
 * 
 * If $affected === 0: No rows matched the WHERE clause
 * Meaning: Server with this ID doesn't exist
 */
if ($affected === 0) {
	// ═════════════════════════════════════════════════════════════════════
	// SERVER NOT FOUND
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
		'message' => 'Server not found.'
	]);
	
	// ✓ Exit early
	exit;
}

// ✓ UPDATE SUCCEEDED - SERVER WAS FOUND AND MARKED DELETED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: SUCCESS RESPONSE                                             │
// │                                                                           │
// │ Server has been soft-deleted (is_active = 0)                            │
// │ Will not appear in getServers.php list                                  │
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
 * 1. Removes server from UI list
 * 2. Shows success message
 * 3. Refreshes server data if needed
 */
echo json_encode([
	'success' => true
]);

// ═════════════════════════════════════════════════════════════════════════
// SERVER SOFT-DELETE COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation: 
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated server ID (must be > 0)
// 3. ✓ Marked server as deleted (is_active = 0)
// 4. ✓ Verified update occurred (affected_rows check)
// 5. ✓ Data preserved in database (not actually deleted)
// 
// Result: Server now hidden from getServers.php, but data preserved
// 
// ═════════════════════════════════════════════════════════════════════════

?>

