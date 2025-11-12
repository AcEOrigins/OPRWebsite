<?php
/**
 * =============================================================================
 * login.php - User Authentication Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Authenticates users by verifying credentials (username/password).
 * Creates secure session on successful authentication.
 * Called by: portal_login.js on admin login page
 * 
 * REQUEST:
 * --------
 * POST /login.php
 * Content-Type: application/json
 * 
 * {
 *   "name": "admin",
 *   "password": "mypassword123"
 * }
 * 
 * RESPONSE (SUCCESS):
 * --------------------
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "redirectUrl": "portal.html"
 * }
 * 
 * RESPONSE (FAILURE):
 * --------------------
 * HTTP 401 Unauthorized
 * {
 *   "success": false,
 *   "message": "Invalid credentials. Please try again."
 * }
 * 
 * SECURITY:
 * ---------
 * ✓ Type declarations enabled (declare(strict_types=1))
 * ✓ HttpOnly cookies (prevents XSS theft)
 * ✓ SameSite=Lax (prevents CSRF)
 * ✓ Strict mode (prevents session fixation)
 * ✓ Session ID regenerated after login
 * ✓ Password hashed with password_hash() + password_verify()
 * ✓ Parameterized queries (prevents SQL injection)
 * ✓ Generic error messages (doesn't leak if username exists)
 * 
 * =============================================================================
 */

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 1: PHP CONFIGURATION & SESSION SETUP                            │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * STRICT TYPES
 * 
 * Enables type checking for function arguments.
 * Makes PHP more strict about type safety.
 */
declare(strict_types=1);

// ────────────────────────────────────────────────────────────────────────────
// CONFIGURE SESSION SECURITY
// ────────────────────────────────────────────────────────────────────────────

/**
 * HTTPONLY FLAG: true
 * 
 * Prevents JavaScript from accessing the session cookie.
 * Protects against XSS (Cross-Site Scripting) attacks.
 * 
 * If JavaScript steals session cookie (via XSS):
 * - Without HttpOnly: Attacker can use cookie to hijack session
 * - With HttpOnly:    Cookie is inaccessible to JS (server use only)
 */
ini_set('session.cookie_httponly', '1');

/**
 * SAMESITE FLAG: Lax
 * 
 * Prevents cookie from being sent in cross-site requests.
 * Protects against CSRF (Cross-Site Request Forgery) attacks.
 * 
 * Values:
 * - Strict: Cookie only sent in same-site requests (safest but may break some features)
 * - Lax:    Cookie sent in same-site requests + top-level cross-site (balanced)
 * - None:   Cookie sent everywhere (requires Secure flag, not safe)
 */
ini_set('session.cookie_samesite', 'Lax');

/**
 * STRICT MODE: true
 * 
 * Rejects uninitialized session IDs.
 * If attacker sends random PHPSESSID cookie:
 * - Without strict: Might create new session with that ID
 * - With strict:    PHP rejects it, creates legitimate new session
 */
ini_set('session.use_strict_mode', '1');

/**
 * START PHP SESSION
 * 
 * Initializes the $_SESSION array.
 * - If valid session cookie exists: Restores saved session data
 * - If no session cookie: Creates new session (later)
 * 
 * session_start() must be called BEFORE any output to browser
 */
session_start();

/**
 * SET RESPONSE CONTENT TYPE
 * 
 * Tells browser: "The response is JSON, not HTML"
 * Browser won't try to render it as a webpage.
 */
header('Content-Type: application/json');

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: HTTP METHOD VALIDATION                                       │
// │                                                                           │
// │ Only POST requests are allowed (login = state-changing operation)       │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK REQUEST METHOD
 * 
 * Login must be POST request because:
 * 1. State-changing operation (creates session)
 * 2. Prevents accidental logins from prefetch/preload
 * 3. Credentials in body (POST) not URL (GET)
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	// ═════════════════════════════════════════════════════════════════════
	// ✗ WRONG HTTP METHOD
	// ═════════════════════════════════════════════════════════════════════
	
	// HTTP 405: Method Not Allowed
	http_response_code(405);
	
	// Return error JSON
	echo json_encode([
		'success' => false,
		'message' => 'Method not allowed. Use POST.'
	]);
	
	exit;
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: PARSE & VALIDATE INPUT                                       │
// │                                                                           │
// │ Extract username/password from JSON request body                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * READ REQUEST BODY
 * 
 * Gets the raw JSON from request body.
 * file_get_contents('php://input') = read POST body as string
 */
$rawInput = file_get_contents('php://input');

/**
 * DECODE JSON
 * 
 * Converts JSON string to PHP array.
 * Second parameter (true) = return as array (not object)
 * 
 * If JSON is invalid: json_decode() returns NULL
 */
$payload = json_decode($rawInput, true);

/**
 * VALIDATE PAYLOAD FORMAT
 * 
 * Check if decoded payload is actually an array.
 * Catches:
 * - Malformed JSON
 * - Empty body
 * - Non-JSON content
 */
if (!is_array($payload)) {
	// ═════════════════════════════════════════════════════════════════════
	// ✗ INVALID JSON
	// ═════════════════════════════════════════════════════════════════════
	
	// HTTP 400: Bad Request
	http_response_code(400);
	
	echo json_encode([
		'success' => false,
		'message' => 'Invalid JSON format. Expected array.'
	]);
	
	exit;
}

// ────────────────────────────────────────────────────────────────────────────
// EXTRACT CREDENTIALS
// ────────────────────────────────────────────────────────────────────────────

/**
 * GET USERNAME
 * 
 * isset($payload['name']): Check if 'name' key exists
 * trim((string)...):       Remove whitespace and convert to string
 * '':                      Default to empty string if not provided
 * 
 * Result: $name = "" or "username"
 */
$name = isset($payload['name']) ? trim((string)$payload['name']) : '';

/**
 * GET PASSWORD
 * 
 * isset($payload['password']): Check if 'password' key exists
 * (string)...:                Convert to string
 * '':                         Default to empty string if not provided
 * 
 * Note: DON'T trim password (user may have intentional spaces)
 * Result: $password = "" or "userpassword123"
 */
$password = isset($payload['password']) ? (string)$payload['password'] : '';

/**
 * VALIDATE REQUIRED FIELDS
 * 
 * Both username and password MUST be provided.
 * Empty strings are rejected.
 */
if ($name === '' || $password === '') {
	// ═════════════════════════════════════════════════════════════════════
	// ✗ MISSING CREDENTIALS
	// ═════════════════════════════════════════════════════════════════════
	
	// HTTP 422: Unprocessable Entity (validation error)
	http_response_code(422);
	
	echo json_encode([
		'success' => false,
		'message' => 'Username and password are required.'
	]);
	
	exit;
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: DATABASE CONNECTION & USER LOOKUP                            │
// │                                                                           │
// │ Load database and query for user by username                            │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * LOAD DATABASE CONNECTION
 * 
 * Includes dbconnect.php which creates $conn object.
 * If connection fails, exit with error (handled in dbconnect.php).
 */
require_once __DIR__ . '/dbconnect.php';

/**
 * PREPARE SQL STATEMENT
 * 
 * Query to find user by username.
 * Uses parameterized query (?) for SQL injection protection.
 * 
 * SQL:
 * SELECT id, name, password_hash, role, is_active
 * FROM users
 * WHERE name = ?
 * LIMIT 1
 * 
 * Returns:
 * - id: User's database ID
 * - name: Username
 * - password_hash: Hashed password (compared with password_verify())
 * - role: 'owner', 'admin', or 'staff'
 * - is_active: 1 (active) or 0 (deactivated)
 */
$stmt = $conn->prepare("SELECT id, name, password_hash, role, is_active FROM users WHERE name = ? LIMIT 1");

/**
 * CHECK PREPARE SUCCESS
 * 
 * $conn->prepare() can fail if:
 * - SQL syntax error
 * - Database connection lost
 * - Table doesn't exist
 */
if (!$stmt) {
	// ═════════════════════════════════════════════════════════════════════
	// ✗ QUERY PREPARATION FAILED
	// ═════════════════════════════════════════════════════════════════════
	
	// HTTP 500: Server Error
	http_response_code(500);
	
	echo json_encode([
		'success' => false,
		'message' => 'Unable to process request.'
	]);
	
	$conn->close();
	exit;
}

/**
 * BIND PARAMETER
 * 
 * Binds the username variable ($name) to the ? placeholder.
 * 's' = string type
 * 
 * This prevents SQL injection because $name is treated as data,
 * not executable SQL code.
 */
$stmt->bind_param('s', $name);

/**
 * EXECUTE QUERY
 * 
 * Runs the query with the bound parameter.
 * Fetches the user row (if any) from database.
 */
$stmt->execute();

/**
 * GET RESULT
 * 
 * Retrieves the result set from execution.
 * Result may be empty (user not found) or contain one row.
 */
$result = $stmt->get_result();

/**
 * FETCH ROW
 * 
 * Converts result row to associative array.
 * fetch_assoc() returns:
 * - Array with keys: id, name, password_hash, role, is_active
 * - NULL if no rows found
 */
$user = $result ? $result->fetch_assoc() : null;

/**
 * CLOSE STATEMENT
 * 
 * Frees prepared statement resources.
 * Important for memory management.
 */
$stmt->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: CREDENTIAL VERIFICATION                                      │
// │                                                                         │
// │ Check if user exists, is active, and password matches                   │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * VALIDATE CREDENTIALS
 * 
 * All three conditions must be true:
 * 1. $user: User found in database (not null)
 * 2. is_active = 1: Account is active (not deactivated)
 * 3. password_verify(...): Entered password matches hash
 * 
 * If ANY condition fails: Login denied.
 * 
 * WHY COMBINE CONDITIONS?
 * Prevents attackers from knowing:
 * - "User not found" (leaks valid usernames)
 * - "Account deactivated" (leaks deactivation)
 * Instead: Always say "Invalid credentials"
 */
if (!$user || (int)$user['is_active'] !== 1 || $password !== $user['password_hash']) {
	// ═════════════════════════════════════════════════════════════════════
	// ✗ AUTHENTICATION FAILED
	// ═════════════════════════════════════════════════════════════════════
	// HTTP 401: Unauthorized
	http_response_code(401);
	
	echo json_encode([
		'success' => false,
		'message' => 'Invalid credentials. Please try again. TwT'
	]);
	
	$conn->close();
	exit;
}

// ✓ CREDENTIALS VERIFIED - User is legitimate

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: SESSION CREATION                                             │
// │                                                                           │
// │ Create secure session for authenticated user                            │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * REGENERATE SESSION ID
 * 
 * Creates new session ID and invalidates old one.
 * Parameter (true): Delete old session data
 * 
 * SECURITY: Prevents session fixation attacks
 * Example attack blocked:
 * 1. Attacker gets session ID (PHPSESSID=ABC123)
 * 2. Attacker tricks user into logging in with that ID
 * 3. Session regeneration: Old ID (ABC123) is destroyed
 * 4. New legitimate ID is created
 * 5. Attacker's ID no longer works
 */
session_regenerate_id(true);

// ────────────────────────────────────────────────────────────────────────────
// POPULATE SESSION DATA
// ────────────────────────────────────────────────────────────────────────────

/**
 * STORE USER ID
 * 
 * Cast to int for type safety.
 * Used by auth_check.php to verify session is valid.
 */
$_SESSION['user_id'] = (int)$user['id'];

/**
 * STORE USERNAME
 * 
 * Used for display ("Welcome admin").
 * Retrieved by portal.js for welcome message.
 */
$_SESSION['user_name'] = $user['name'];

/**
 * STORE USER ROLE
 * 
 * Determines portal UI visibility:
 * - 'owner':  Full access to everything
 * - 'admin':  All except user management
 * - 'staff':  Only announcements
 * 
 * Retrieved by auth_check.php and sent to portal.js.
 */
$_SESSION['user_role'] = $user['role'];

// ✓ Session data stored in $_SESSION (persists across requests via cookie)

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: CLEANUP & RESPONSE                                           │
// │                                                                           │
// │ Close database connection and return success                            │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CLOSE DATABASE CONNECTION
 * 
 * Frees database connection resources.
 * Good practice to clean up immediately.
 */
$conn->close();

/**
 * RETURN SUCCESS RESPONSE
 * 
 * HTTP 200: OK
 * 
 * Response JSON:
 * {
 *   "success": true,
 *   "redirectUrl": "portal.html"
 * }
 * 
 * Frontend (portal_login.js) receives:
 * 1. Session cookie automatically (set by PHP)
 * 2. Redirect URL from JSON
 * 3. Navigates to portal.html
 * 4. Portal.html calls auth_check.php with cookie
 * 5. Portal loads with user data
 */
echo json_encode([
	'success' => true,
	'redirectUrl' => 'portal.html'
]);

// ═════════════════════════════════════════════════════════════════════════
// LOGIN PROCESS COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Session is now created and stored in browser cookie.
// Future requests to portal.html will include this cookie.
// auth_check.php will verify the cookie and allow access.
// 
// ═════════════════════════════════════════════════════════════════════════

?>

