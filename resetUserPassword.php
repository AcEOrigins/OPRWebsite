<?php
/**
 * =============================================================================
 * resetUserPassword.php - Password Reset Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Resets a user's password to a new value (admin-initiated).
 * Called by: portal.html (admin portal, Manage Access tab)
 * 
 * REQUEST:
 * --------
 * POST /resetUserPassword.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "id": 2,
 *   "password": "NewPassword123!"
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
 *   "message": "User ID and new password are required."
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
 * 3. Extract and validate user ID and new password
 * 4. Hash new password using PASSWORD_DEFAULT
 * 5. Update user record: password_hash = hashed_password
 * 6. Check if any rows were actually updated
 * 7. Return success or not found error
 * 
 * PASSWORD HASHING:
 * -----------------
 * password_hash($password, PASSWORD_DEFAULT)
 * 
 * - PASSWORD_DEFAULT: Currently bcrypt, resistant to future attacks
 * - Adaptive: Cost factor increases as hardware improves
 * - Hash length: 60+ characters
 * - Stored in: password_hash column (VARCHAR(255))
 * 
 * Same algorithm as in addUser.php, verified with password_verify() in login.php
 * 
 * USE CASES:
 * ----------
 * 1. Admin resets user's password (user forgot theirs)
 * 2. Admin changes password for security reasons
 * 3. Admin sets temporary password (user logs in and changes it)
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized query (prevents SQL injection)
 * ✓ Input validation (user ID must be > 0, password required)
 * ✓ Password hashing (PASSWORD_DEFAULT, adaptive cost)
 * ✓ Old password not needed (admin power)
 * ✓ Verified update actually occurred (affected_rows check)
 * ✓ 404 response if user not found (correct semantics)
 * ✓ Generic error messages (don't leak info)
 * 
 * NOTE: Admin can reset ANY user's password (no authentication check)
 * Assumes portal authentication (auth_check.php) already verified admin access
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
// │ Prevents accidental password reset from URL bar or browser prefetch.     │
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
 * EXTRACT USER ID
 * 
 * isset($input['id']):  Check key exists in array
 * (int)...:             Cast to integer (type safety)
 * 0:                    Default to 0 if key missing
 * 
 * Result: $id = 2 (if provided) or 0 (if not or invalid)
 */
$id = isset($input['id']) ? (int)$input['id'] : 0;

/**
 * EXTRACT NEW PASSWORD (PLAINTEXT)
 * 
 * isset($input['password']):  Check key exists
 * (string)...:                Ensure string type
 * '':                         Default to empty if missing
 * 
 * IMPORTANT: NOT TRIMMED
 * Spaces might be intentional in password.
 * Never trim passwords (user might have leading/trailing spaces)
 */
$password = isset($input['password']) ? (string)$input['password'] : '';

/**
 * VALIDATE USER ID AND PASSWORD
 * 
 * Check:
 * - $id > 0 (must be positive integer)
 * - $password !== '' (must be non-empty)
 * 
 * Both are REQUIRED.
 */
if ($id <= 0 || $password === '') {
	// ═════════════════════════════════════════════════════════════════════
	// VALIDATION FAILED: USER ID OR PASSWORD IS EMPTY
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
		'message' => 'User ID and new password are required.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

// ✓ VALIDATION PASSED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: HASH NEW PASSWORD                                            │
// │                                                                           │
// │ SECURITY: Never store plaintext passwords!                               │
// │ Use PASSWORD_DEFAULT for automatic algorithm selection and upgrades     │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * HASH NEW PASSWORD
 * 
 * password_hash($password, PASSWORD_DEFAULT)
 * 
 * PASSWORD_DEFAULT:
 * - Currently: bcrypt (y$2y$ prefix)
 * - Adaptive: Cost factor increases as hardware improves
 * - Future-proof: Algorithm can change without code changes
 * - Against timing attacks: Safe from direct comparison
 * 
 * Result:
 * - $hash = 60+ character string
 * - Example: "$2y$10$abcdefghijklmnopqrstuvwxyz1234567890..."
 * 
 * Same algorithm as in addUser.php
 * Verified with password_verify() in login.php
 */
$hash = password_hash($password, PASSWORD_DEFAULT);

// ✓ $hash IS NOW SAFELY HASHED PASSWORD

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: PREPARE PASSWORD UPDATE STATEMENT                            │
// │                                                                           │
// │ UPDATE user's password_hash in database                                 │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE UPDATE STATEMENT
 * 
 * SQL: UPDATE users SET password_hash = ? WHERE id = ?
 * 
 * Sets:
 * - password_hash: New hashed password
 * 
 * Where:
 * - id: Only the specified user
 * 
 * Result: User can log in with new password
 */
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");

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
 * BIND PARAMETERS
 * 
 * Format string: 'si'
 * - s: password_hash (string, output of password_hash())
 * - i: id (integer)
 * 
 * Order matters: Must match ? placeholders in SQL query
 * 1st ?: $hash (string)
 * 2nd ?: $id (integer)
 */
$stmt->bind_param('si', $hash, $id);

/**
 * EXECUTE THE STATEMENT
 * 
 * Sends UPDATE query to database.
 * Returns true on success, false on failure.
 */
$stmt->execute();

/**
 * GET AFFECTED ROWS COUNT
 * 
 * For UPDATE queries:
 * - 0: WHERE clause matched no rows (user ID not found)
 * - 1: WHERE clause matched one row (user exists, password updated)
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
// │ SECTION 6: CHECK IF USER WAS FOUND & PASSWORD RESET                     │
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

// ✓ UPDATE SUCCEEDED - USER PASSWORD WAS RESET

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: SUCCESS RESPONSE                                             │
// │                                                                           │
// │ User's password has been reset to new value                             │
// │ User can log in with new password                                       │
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
 * 1. Shows success message
 * 2. Closes password reset dialog
 * 3. No list refresh needed (password not displayed)
 */
echo json_encode([
	'success' => true
]);

// ═════════════════════════════════════════════════════════════════════════
// PASSWORD RESET COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation:
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated required fields (user ID, password)
// 3. ✓ Hashed password using PASSWORD_DEFAULT (bcrypt, adaptive)
// 4. ✓ Updated user's password_hash in database
// 5. ✓ Verified update occurred (affected_rows check)
// 
// Result: User's password changed, can log in with new password
// 
// ═════════════════════════════════════════════════════════════════════════

?>


