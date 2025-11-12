<?php
/**
 * =============================================================================
 * auth_check.php - Session Verification Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Verifies if the current request has a valid session.
 * Returns user information (ID, name, role) if authenticated.
 * Called by: portal.html via portal.js on page load
 * 
 * REQUEST:
 * --------
 * GET /auth_check.php
 * (Browser automatically sends session cookie if one exists)
 * 
 * RESPONSE (AUTHENTICATED):
 * -------------------------
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "authenticated": true,
 *   "userId": 1,
 *   "userName": "admin",
 *   "role": "owner"
 * }
 * 
 * RESPONSE (NOT AUTHENTICATED):
 * -----------------------------
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "authenticated": false
 * }
 * 
 * USAGE FLOW:
 * -----------
 * 1. User goes to portal.html
 * 2. portal.js calls auth_check.php
 * 3. If authenticated:false → Redirect to portal_login.html
 * 4. If authenticated:true → Show portal with user data
 * 5. Role determines UI visibility (owner > admin > staff)
 * 
 * SECURITY:
 * ---------
 * ✓ Session cookie verification
 * ✓ Role always fetched from DB (never trust session data)
 * ✓ HttpOnly / SameSite / Strict Mode enabled
 * ✓ Parameterized query prevents SQL injection
 * ✓ Returns minimal info (no sensitive data)
 * 
 * =============================================================================
 */

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 1: PHP CONFIGURATION & SESSION SETUP                            │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * STRICT TYPES
 * 
 * Enable type checking for function arguments.
 * Makes code more predictable and catches bugs earlier.
 */
declare(strict_types=1);

// ────────────────────────────────────────────────────────────────────────────
// CONFIGURE SESSION SECURITY (SAME AS login.php)
// ────────────────────────────────────────────────────────────────────────────

/**
 * HTTPONLY FLAG: true
 * Prevents JavaScript from stealing session cookie via XSS
 */
ini_set('session.cookie_httponly', '1');

/**
 * SAMESITE FLAG: Lax
 * Prevents CSRF attacks by not sending cookie cross-origin
 */
ini_set('session.cookie_samesite', 'Lax');

/**
 * STRICT MODE: true
 * Rejects forged/uninitialized session IDs
 */
ini_set('session.use_strict_mode', '1');

/**
 * START SESSION
 * 
 * Restores session data from browser cookie (if cookie exists).
 * If no valid cookie: $_SESSION starts empty
 * 
 * MUST be called BEFORE any output to browser.
 */
session_start();

/**
 * SET RESPONSE CONTENT TYPE
 * 
 * Tell browser: "This is JSON, not HTML"
 * Prevents browser from trying to render JSON as webpage.
 */
header('Content-Type: application/json');

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: QUICK SESSION CHECK                                          │
// │                                                                           │
// │ Verify session exists and user_id is valid                              │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK IF SESSION IS AUTHENTICATED
 * 
 * For a valid session:
 * 1. $_SESSION['user_id'] must be SET (key exists in array)
 * 2. $_SESSION['user_id'] must be > 0 (positive integer)
 * 
 * Both conditions are required to avoid false positives:
 * - isset() alone: Would fail if user_id = 0 (valid but false)
 * - > 0 check alone: Would fail if key doesn't exist (error)
 * 
 * Result: $isAuthenticated = true or false
 */
$isAuthenticated = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: EARLY EXIT IF NOT AUTHENTICATED                              │
// │                                                                           │
// │ If no valid session, return immediately (don't query database)           │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * NOT AUTHENTICATED - EARLY EXIT
 * 
 * If the quick check above failed, user is not logged in.
 * No need to query database - we already know they're not authenticated.
 */
if (!$isAuthenticated) {
	// ═════════════════════════════════════════════════════════════════════
	// SESSION DOES NOT EXIST OR IS INVALID
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * Return minimal JSON response
	 * 
	 * {
	 *   "success": true,      ← Request succeeded (no server error)
	 *   "authenticated": false ← But user is not authenticated
	 * }
	 * 
	 * Frontend (portal.js) receives this and redirects to login page.
	 * HTTP 200 because: This is a valid response (not an error).
	 */
	echo json_encode([
		'success' => true,
		'authenticated' => false
	]);
	
	// ✓ Exit early - no database query needed
	exit;
}

// ✓ SESSION IS VALID - Continue to database verification

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: EXTRACT SESSION DATA                                         │
// │                                                                           │
// │ Get user ID, name, and set default role (will verify from DB)           │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * GET USER ID FROM SESSION
 * 
 * $_SESSION['user_id'] ?? 0:  Use session value if set, else default to 0
 * (int)(...):                  Cast to integer for type safety
 * 
 * We know this is > 0 from previous check, but re-cast for safety.
 */
$userId = (int)($_SESSION['user_id'] ?? 0);

/**
 * GET USERNAME FROM SESSION
 * 
 * $_SESSION['user_name'] ?? '': Use session value if set, else default to ""
 * (string)(...):                Cast to string for type safety
 * 
 * This is used for display in the portal ("Welcome {name}").
 */
$userName = (string)($_SESSION['user_name'] ?? '');

/**
 * SET DEFAULT ROLE
 * 
 * Default to 'admin' role while we query the database.
 * If database query fails, this default is returned.
 * If database query succeeds, role is updated below.
 * 
 * Roles:
 * - 'owner':  Full access to everything
 * - 'admin':  Everything except user management (Manage Access)
 * - 'staff':  Only announcements (limited access)
 */
$role = 'admin';  // ← Temporary default, will be updated from DB

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: DATABASE QUERY FOR CURRENT ROLE                              │
// │                                                                           │
// │ WHY QUERY THE DATABASE?                                                 │
// │ ─────────────────────────────                                           │
// │ Session data is stored on the CLIENT (in cookie).                       │
// │ Client could be stale or tampered with:                                 │
// │ - User's role might have changed since they logged in                   │
// │ - Someone could modify the cookie (though encrypted)                    │
// │ - Session might be replayed from old data                               │
// │                                                                           │
// │ Database is the SOURCE OF TRUTH.                                        │
// │ Always verify role from database on every request.                      │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * LOAD DATABASE CONNECTION
 * 
 * Includes dbconnect.php which creates $conn.
 * If connection fails, $conn will have error (handled below).
 */
require_once __DIR__ . '/dbconnect.php';

/**
 * PREPARE QUERY TO GET LATEST ROLE
 * 
 * SQL: SELECT role FROM users WHERE id = ? LIMIT 1
 * 
 * Why this query:
 * - Simple (just get one column)
 * - Fast (uses primary key index)
 * - Safe (parameterized query prevents SQL injection)
 * - Gives us the current role from database
 */
$stmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');

/**
 * CHECK IF PREPARE SUCCEEDED
 * 
 * Might fail if:
 * - Database connection lost
 * - Syntax error in SQL
 * - Table doesn't exist
 */
if ($stmt) {
	// ═════════════════════════════════════════════════════════════════════
	// QUERY PREPARATION SUCCEEDED - PROCEED WITH LOOKUP
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * BIND USER ID PARAMETER
	 * 
	 * 'i' = integer type
	 * Binds $userId to the ? placeholder
	 * 
	 * This is safe from SQL injection because $userId
	 * is treated as data, not executable code.
	 */
	$stmt->bind_param('i', $userId);
	
	/**
	 * EXECUTE THE QUERY
	 * 
	 * Actually runs the query on the database.
	 * Fetches the user row (if found).
	 */
	$stmt->execute();
	
	/**
	 * GET RESULT SET
	 * 
	 * Returns the results from execution.
	 * Might be empty (user not found) or have one row.
	 */
	$res = $stmt->get_result();
	
	/**
	 * FETCH THE ROW
	 * 
	 * Converts result to array: ['role' => 'admin']
	 * Returns NULL if no rows found.
	 */
	$row = $res ? $res->fetch_assoc() : null;
	
	/**
	 * CLOSE STATEMENT
	 * 
	 * Free prepared statement resources.
	 * Good memory management practice.
	 */
	$stmt->close();
	
	/**
	 * UPDATE ROLE IF FOUND
	 * 
	 * If database returned a row with a role:
	 * Replace the default 'admin' role with the actual role from DB.
	 */
	if ($row && isset($row['role'])) {
		$role = (string)$row['role'];  // Update role to current value
	}
	// If no row found: Keep default 'admin' role
}
// If prepare failed: Keep default 'admin' role (graceful fallback)

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: CLEANUP & RESPONSE                                           │
// │                                                                           │
// │ Close database connection and return user data                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CLOSE DATABASE CONNECTION
 * 
 * Frees database connection resources.
 * Good practice to close immediately when done.
 */
$conn->close();

/**
 * RETURN SUCCESS RESPONSE WITH USER DATA
 * 
 * HTTP 200: OK (success)
 * 
 * Response JSON:
 * {
 *   "success": true,
 *   "authenticated": true,
 *   "userId": 1,           ← Used internally for DB queries
 *   "userName": "admin",   ← Displayed in portal ("Welcome admin")
 *   "role": "owner"        ← Controls UI visibility
 * }
 * 
 * Frontend (portal.js) uses this data to:
 * 1. Show portal content (authenticated: true)
 * 2. Display welcome message (userName)
 * 3. Show/hide features based on role (role)
 */
echo json_encode([
	'success' => true,
	'authenticated' => true,
	'userId' => $userId,
	'userName' => $userName,
	'role' => $role
]);

// ═════════════════════════════════════════════════════════════════════════
// SESSION VERIFICATION COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Frontend receives:
// 1. Session exists ✓
// 2. User info (ID, name) ✓
// 3. Current role from database ✓
// 4. Role determines UI visibility
// 
// ═════════════════════════════════════════════════════════════════════════

?>
