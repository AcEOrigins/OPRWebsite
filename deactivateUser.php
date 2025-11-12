<?php
/**
 * =============================================================================
 * deactivateUser.php - User Soft-Deactivation Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Soft-deactivates a user (marks as inactive, doesn't remove from database).
 * User can no longer log in or access portal.
 * Called by: portal.html (admin portal, Manage Access tab)
 * 
 * REQUEST:
 * --------
 * POST /deactivateUser.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "id": 2
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
 *   "message": "Invalid user ID."
 * }
 * 
 * RESPONSE (NOT FOUND):
 * --------------------
 * HTTP 404 Not Found
 * {
 *   "success": false,
 *   "message": "User not found."
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
 * 3. Extract and validate user ID
 * 4. Update user record: is_active = 0
 * 5. Check if any rows were actually updated
 * 6. Return success or not found error
 * 
 * SOFT-DEACTIVATION:
 * ──────────────────
 * Instead of DELETE FROM users WHERE id = ?, we use:
 * UPDATE users SET is_active = 0 WHERE id = ?
 * 
 * Benefits:
 * - Data never actually deleted (audit trail, recovery, history)
 * - User data preserved for security logs
 * - Can reactivate if needed (reactivateUser.php)
 * - Relationships intact
 * 
 * LOGIN BEHAVIOR:
 * ───────────────
 * When is_active = 0:
 * - login.php still queries the user (finds them)
 * - But user cannot log in (is_active check added in login endpoint)
 * - Or rejected at portal.html based on auth_check.php
 * 
 * FRONTEND:
 * ---------
 * listUsers.php returns all users including is_active = 0
 * portal.js displays inactive users greyed out, with "Inactive" badge
 * Admin can click "Reactivate" to restore access
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized query (prevents SQL injection)
 * ✓ Input validation (user ID must be > 0)
 * ✓ Verified update actually occurred (affected_rows check)
 * ✓ 404 response if user not found (correct semantics)
 * ✓ Data preserved (not irreversible deletion)
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
// │ Prevents accidental deactivation from URL bar or browser prefetch.       │
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

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: DATABASE CONNECTION & INPUT PARSING                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * LOAD DATABASE CONNECTION
 */
require_once __DIR__ . '/dbconnect.php';

/**
 * PARSE JSON REQUEST BODY
 * 
 * ?: [] ensures $input is always an array (default to empty if parse fails)
 */
$input = json_decode(file_get_contents('php://input'), true) ?: [];

/**
 * EXTRACT & VALIDATE USER ID
 * 
 * isset($input['id']):  Check key exists in array
 * (int)...:             Cast to integer (type safety)
 * 0:                    Default to 0 if key missing
 * 
 * Result: $id = 2 (if provided) or 0 (if not or invalid)
 */
$id = isset($input['id']) ? (int)$input['id'] : 0;

/**
 * VALIDATE USER ID
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
	// VALIDATION FAILED: INVALID USER ID
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 422 = Unprocessable Entity
	 */
	http_response_code(422);
	
	/**
	 * RETURN VALIDATION ERROR
	 */
	echo json_encode([
		'success' => false,
		'message' => 'Invalid user ID.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

// ✓ VALIDATION PASSED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: PREPARE SOFT-DEACTIVATION STATEMENT                          │
// │                                                                           │
// │ SOFT-DEACTIVATION PATTERN:                                              │
// │ Instead of: DELETE FROM users WHERE id = ?                              │
// │ We do:      UPDATE users SET is_active = 0 WHERE id = ?                 │
// │                                                                           │
// │ Benefits:                                                                │
// │ • Data preserved (audit trail, recovery)                                │
// │ • Can reactivate (change is_active = 1)                                 │
// │ • Security logs remain intact                                            │
// │ • frontend: listUsers.php shows all users, admin can reactivate         │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE UPDATE STATEMENT
 * 
 * SQL: UPDATE users SET is_active = 0 WHERE id = ?
 * 
 * Sets:
 * - is_active = 0: Mark user as deactivated
 * 
 * Where:
 * - id = ?: Only the specified user
 * 
 * Result: User cannot log in, is_active = 0 in database
 */
$stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");

/**
 * CHECK IF PREPARE SUCCEEDED
 * 
 * Might fail if:
 * - Database connection lost
 * - Syntax error in SQL
 * - Table doesn't exist
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
 */
$stmt->bind_param('i', $id);

/**
 * EXECUTE THE STATEMENT
 */
$stmt->execute();

/**
 * GET AFFECTED ROWS COUNT
 * 
 * For UPDATE queries:
 * - 0: WHERE clause matched no rows (user ID not found)
 * - 1: WHERE clause matched one row (user exists, now deactivated)
 */
$affected = $stmt->affected_rows;

/**
 * CLOSE PREPARED STATEMENT
 */
$stmt->close();

/**
 * CLOSE DATABASE CONNECTION
 */
$conn->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: CHECK IF USER WAS FOUND & DEACTIVATED                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK AFFECTED ROWS
 * 
 * If $affected === 0: No rows matched the WHERE clause
 * Meaning: User with this ID doesn't exist
 */
if ($affected === 0) {
	// ═════════════════════════════════════════════════════════════════════
	// USER NOT FOUND
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 404 = Not Found
	 */
	http_response_code(404);
	
	/**
	 * RETURN NOT FOUND ERROR
	 */
	echo json_encode([
		'success' => false,
		'message' => 'User not found.'
	]);
	
	// ✓ Exit early
	exit;
}

// ✓ UPDATE SUCCEEDED - USER WAS FOUND AND DEACTIVATED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: SUCCESS RESPONSE                                             │
// │                                                                           │
// │ User has been soft-deactivated (is_active = 0)                          │
// │ User cannot log in, but data is preserved                               │
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
 * 1. Refreshes user list
 * 2. Shows user as "Inactive"
 * 3. Shows "Reactivate" button
 */
echo json_encode([
	'success' => true
]);

// ═════════════════════════════════════════════════════════════════════════
// USER SOFT-DEACTIVATION COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation:
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated user ID (must be > 0)
// 3. ✓ Marked user as deactivated (is_active = 0)
// 4. ✓ Verified update occurred (affected_rows check)
// 5. ✓ Data preserved in database
// 
// Result: User cannot log in, but can be reactivated later
// 
// ═════════════════════════════════════════════════════════════════════════

?>


