<?php
/**
 * =============================================================================
 * reactivateUser.php - User Reactivation Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Reactivates a previously deactivated user (restores access).
 * Called by: portal.html (admin portal, Manage Access tab)
 * 
 * REQUEST:
 * --------
 * POST /reactivateUser.php
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
 * 4. Update user record: is_active = 1
 * 5. Check if any rows were actually updated
 * 6. Return success or not found error
 * 
 * REACTIVATION PATTERN:
 * ────────────────────
 * Inverse of deactivateUser.php
 * 
 * deactivateUser: UPDATE users SET is_active = 0 WHERE id = ?
 * reactivateUser: UPDATE users SET is_active = 1 WHERE id = ?
 * 
 * Benefits of soft-delete pattern:
 * - Deactivated users can be restored instantly
 * - No data loss or recreation needed
 * - Audit trail preserved (can see when user was inactive)
 * - Password remains hashed (user doesn't need to reset)
 * 
 * WHEN TO USE:
 * ───────────
 * - Admin temporarily disables user (e.g., contractor leaving/returning)
 * - User regains access immediately upon reactivation
 * - Password unchanged (no reset required)
 * - All settings preserved
 * 
 * ALTERNATIVE: DELETE
 * ───────────────────
 * Would use: DELETE FROM users WHERE id = ?
 * 
 * Why NOT used here:
 * - Data permanently lost (cannot restore)
 * - No audit trail (who deleted when?)
 * - Cannot check if user was recently active
 * - If user wants to return: Must create new account
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized query (prevents SQL injection)
 * ✓ Input validation (user ID must be > 0)
 * ✓ Verified update actually occurred (affected_rows check)
 * ✓ 404 response if user not found (correct semantics)
 * ✓ Data preserved and restored instantly
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
// │ Prevents accidental reactivation from URL bar or browser prefetch.       │
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
// │ SECTION 4: PREPARE REACTIVATION STATEMENT                               │
// │                                                                           │
// │ INVERSE OF DEACTIVATEUSER:                                              │
// │ Instead of: UPDATE users SET is_active = 0 WHERE id = ?                 │
// │ We do:      UPDATE users SET is_active = 1 WHERE id = ?                 │
// │                                                                           │
// │ This restores user access instantly without any data loss.              │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE UPDATE STATEMENT
 * 
 * SQL: UPDATE users SET is_active = 1 WHERE id = ?
 * 
 * Sets:
 * - is_active = 1: Mark user as reactivated
 * 
 * Where:
 * - id = ?: Only the specified user
 * 
 * Result: User can log in again, is_active = 1 in database
 */
$stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");

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
 * - 1: WHERE clause matched one row (user exists, now reactivated)
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
// │ SECTION 5: CHECK IF USER WAS FOUND & REACTIVATED                        │
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

// ✓ UPDATE SUCCEEDED - USER WAS FOUND AND REACTIVATED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: SUCCESS RESPONSE                                             │
// │                                                                           │
// │ User has been reactivated (is_active = 1)                               │
// │ User can log in again, password unchanged                               │
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
 * 2. Shows user as "Active"
 * 3. Shows "Deactivate" button
 * 4. User can now log in
 */
echo json_encode([
	'success' => true
]);

// ═════════════════════════════════════════════════════════════════════════
// USER REACTIVATION COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation:
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated user ID (must be > 0)
// 3. ✓ Marked user as reactivated (is_active = 1)
// 4. ✓ Verified update occurred (affected_rows check)
// 5. ✓ Data preserved, no loss
// 
// Result: User can log in again, all settings preserved, password unchanged
// 
// ═════════════════════════════════════════════════════════════════════════

?>


