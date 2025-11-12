<?php
/**
 * =============================================================================
 * addUser.php - User Creation Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Creates a new user account in the database.
 * Called by: portal.html (admin portal to manage access, Manage Access tab)
 * 
 * REQUEST:
 * --------
 * POST /addUser.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "name": "john_doe",
 *   "password": "SecurePassword123!",
 *   "role": "admin"             (optional, default="admin")
 * }
 * 
 * RESPONSE (SUCCESS):
 * -------------------
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "user": {
 *     "id": 2,
 *     "name": "john_doe",
 *     "role": "admin",
 *     "is_active": 1,
 *     "created_at": "2024-01-15 10:30:45"
 *   }
 * }
 * 
 * RESPONSE (VALIDATION ERROR):
 * ----------------------------
 * HTTP 422 Unprocessable Entity
 * {
 *   "success": false,
 *   "message": "Name and password are required."
 * }
 * 
 * RESPONSE (DUPLICATE USER):
 * --------------------------
 * HTTP 409 Conflict
 * {
 *   "success": false,
 *   "message": "User already exists."
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
 *   "message": "Failed to create user."
 * }
 * 
 * DATA FLOW:
 * ----------
 * 1. Validate HTTP method (POST only)
 * 2. Create table if doesn't exist (idempotent)
 * 3. Parse and validate JSON request
 * 4. Validate required fields (name, password)
 * 5. Hash password using PASSWORD_DEFAULT algorithm
 * 6. Attempt to insert user into database
 * 7. Handle duplicate user error (409 Conflict)
 * 8. Retrieve and return created user data
 * 
 * PASSWORD HASHING:
 * -----------------
 * password_hash($password, PASSWORD_DEFAULT)
 * 
 * - PASSWORD_DEFAULT: Currently bcrypt, resistant to future attacks
 * - Adaptive: Cost factor increases as hardware improves
 * - Hash length: 60 characters
 * - Stored in: password_hash column (VARCHAR(255))
 * 
 * NEVER store plaintext passwords!
 * Used with password_verify() in login.php for comparison.
 * 
 * ROLE LEVELS:
 * -----------
 * 'owner':  Full access to everything (user management, all features)
 * 'admin':  Everything except user management (Manage Access tab hidden)
 * 'staff':  Only announcements (limited feature set)
 * 
 * UNIQUE USERNAME:
 * ----------------
 * Username must be unique (UNIQUE constraint in database).
 * If duplicate: MySQL returns 1062 error → catch and return 409 Conflict.
 * 
 * DUPLICATE DETECTION:
 * ------------------
 * When INSERT fails, MySQL error contains "Duplicate entry" text.
 * Code checks for this text and returns appropriate error code:
 * - 409 Conflict: User already exists
 * - 500 Server Error: Other database error
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized queries (prevents SQL injection)
 * ✓ Input validation (name and password required)
 * ✓ Password hashing (PASSWORD_DEFAULT, adaptive cost)
 * ✓ UNIQUE constraint on username (prevents duplicates)
 * ✓ Generic error messages (don't leak info)
 * ✓ Duplicate detection (409 vs 500 distinction)
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
// │ This endpoint creates data, should ONLY accept POST requests.            │
// │ GET/HEAD are read-only, should not create data.                         │
// │ Prevents accidental user creation from URL bar or browser prefetch.      │
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
 * TABLE: users
 * 
 * COLUMNS:
 * --------
 * id                - Unique identifier (primary key)
 * name              - Username (UNIQUE, must be unique across all users)
 * password_hash     - Hashed password (60+ chars, see PASSWORD_DEFAULT)
 * role              - 'owner', 'admin', or 'staff' (default='admin')
 * is_active         - Soft-delete flag (1=active, 0=inactive)
 * created_at        - Record creation timestamp
 * updated_at        - Last record update timestamp
 * 
 * CONSTRAINTS:
 * ────────────
 * UNIQUE on name: Prevents duplicate usernames
 * If attempted: MySQL error 1062 (Duplicate entry)
 */
$createSql = "
	CREATE TABLE IF NOT EXISTS users (
		id INT AUTO_INCREMENT PRIMARY KEY,
		name VARCHAR(100) NOT NULL UNIQUE,
		password_hash VARCHAR(255) NOT NULL,
		role VARCHAR(32) NOT NULL DEFAULT 'admin',
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
 * ?: []                              Default to empty array if decode fails
 * 
 * Safety: Ensures $input is always an array (prevents type errors)
 */
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: EXTRACT AND NORMALIZE INPUT FIELDS                           │
// │                                                                           │
// │ EXTRACTION STRATEGY:                                                     │
// │ ────────────────────                                                     │
// │ isset():  Check if key exists (avoid "undefined key" errors)             │
// │ trim():   Remove leading/trailing whitespace                             │
// │ (type)(): Cast to appropriate type                                       │
// │ '':       Use default if missing                                         │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * USERNAME (REQUIRED)
 * 
 * Trimmed to remove whitespace.
 * Empty string if not provided (will be validated below).
 * 
 * Must be unique across all users (UNIQUE constraint).
 */
$name = isset($input['name']) ? trim((string)$input['name']) : '';

/**
 * PASSWORD (REQUIRED)
 * 
 * Plaintext password from user.
 * Will be hashed with PASSWORD_DEFAULT before storage.
 * 
 * NEVER stored in plaintext!
 * NEVER trimmed (spaces might be intentional in password)
 */
$password = isset($input['password']) ? (string)$input['password'] : '';

/**
 * ROLE (OPTIONAL)
 * 
 * One of: 'owner', 'admin', 'staff'
 * Default: 'admin' (if not provided)
 * 
 * Trimmed to remove whitespace.
 * No validation here (database stores as-is, UI can validate).
 * 
 * ROLE LEVELS:
 * - 'owner':  Full access to everything
 * - 'admin':  No user management tab
 * - 'staff':  Only announcements
 */
$role = isset($input['role']) ? trim((string)$input['role']) : 'admin';

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: VALIDATE REQUIRED FIELDS                                     │
// │                                                                           │
// │ VALIDATION: Name and password are required                              │
// │ Role is optional (has default)                                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK IF NAME AND PASSWORD ARE PROVIDED AND NON-EMPTY
 * 
 * Validation: Both are REQUIRED.
 */
if ($name === '' || $password === '') {
	// ═════════════════════════════════════════════════════════════════════
	// VALIDATION FAILED: NAME OR PASSWORD IS EMPTY
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 422 = Unprocessable Entity
	 * 
	 * Tells client: "JSON is valid, but validation failed"
	 */
	http_response_code(422);
	
	/**
	 * RETURN VALIDATION ERROR
	 */
	echo json_encode([
		'success' => false,
		'message' => 'Name and password are required.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

// ✓ VALIDATION PASSED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: HASH PASSWORD USING PASSWORD_DEFAULT                         │
// │                                                                           │
// │ SECURITY: Never store plaintext passwords!                               │
// │ Use PASSWORD_DEFAULT for automatic algorithm selection and upgrades     │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * HASH PASSWORD
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
 * Verification:
 * - login.php uses password_verify($password, $hash)
 * - Also safe from timing attacks
 * 
 * WHY NOT JUST md5() or SHA1()?
 * - md5/SHA1: Fast hashes, vulnerable to brute force
 * - bcrypt: Deliberately slow (configurable cost factor)
 * - Takes ~100ms per hash (good for security, bad for attackers)
 * 
 * WHY NOT strcmp()?
 * - strcmp() is fast and vulnerable to timing attacks
 * - Attacker can measure hash time to guess correct hash
 * - password_verify() is constant-time (immune to timing attacks)
 */
$hash = password_hash($password, PASSWORD_DEFAULT);

// ✓ $hash IS NOW SAFELY HASHED PASSWORD

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 8: PREPARE INSERT STATEMENT                                     │
// │                                                                           │
// │ INSERT user record into database                                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE INSERT STATEMENT
 * 
 * SQL: INSERT INTO users (name, password_hash, role, is_active)
 *      VALUES (?, ?, ?, 1)
 * 
 * Columns:
 * - name: Username (will be checked for uniqueness by UNIQUE constraint)
 * - password_hash: Hashed password from PASSWORD_DEFAULT
 * - role: Role level ('owner', 'admin', 'staff')
 * - is_active: 1 (all new users start active)
 * 
 * created_at, updated_at: Auto-populated by database (CURRENT_TIMESTAMP)
 */
$stmt = $conn->prepare("INSERT INTO users (name, password_hash, role, is_active) VALUES (?, ?, ?, 1)");

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
 * BIND PARAMETERS
 * 
 * Format string: 'sss' (three strings)
 * - s: name (string)
 * - s: password_hash (string, output of password_hash())
 * - s: role (string)
 * 
 * All passed by reference (&$var).
 * This is safe from SQL injection: values treated as data, not code.
 */
$stmt->bind_param('sss', $name, $hash, $role);

/**
 * EXECUTE THE STATEMENT
 * 
 * Sends INSERT query to database.
 * Returns true on success, false on failure.
 * 
 * Possible outcomes:
 * - Success: User inserted, is_active = 1
 * - Failure: Duplicate username (Duplicate entry error)
 * - Failure: Other database error
 */
if (!$stmt->execute()) {
	// ═════════════════════════════════════════════════════════════════════
	// QUERY EXECUTION FAILED
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * GET ERROR MESSAGE
	 * 
	 * $stmt->error contains MySQL error message.
	 * Example: "Duplicate entry 'john_doe' for key 'name'"
	 */
	$err = $stmt->error;
	
	/**
	 * CLOSE STATEMENT FIRST
	 */
	$stmt->close();
	
	/**
	 * CLOSE CONNECTION
	 */
	$conn->close();

	/**
	 * CHECK IF ERROR IS DUPLICATE ENTRY
	 * 
	 * strpos($err, 'Duplicate') !== false:
	 * Checks if error message contains "Duplicate" text.
	 * 
	 * If yes: Username already exists (UNIQUE constraint violation)
	 * If no: Some other database error
	 */
	if (strpos($err, 'Duplicate') !== false) {
		// ═════════════════════════════════════════════════════════════════
		// DUPLICATE USERNAME
		// ═════════════════════════════════════════════════════════════════
		
		/**
		 * HTTP 409 = Conflict
		 * 
		 * Tells client: "Resource already exists"
		 * Appropriate for duplicate key violations.
		 */
		http_response_code(409);
		
		/**
		 * RETURN DUPLICATE ERROR
		 */
		echo json_encode([
			'success' => false,
			'message' => 'User already exists.'
		]);
	} else {
		// ═════════════════════════════════════════════════════════════════
		// OTHER DATABASE ERROR
		// ═════════════════════════════════════════════════════════════════
		
		/**
		 * HTTP 500 = Internal Server Error
		 */
		http_response_code(500);
		
		/**
		 * RETURN ERROR RESPONSE
		 */
		echo json_encode([
			'success' => false,
			'message' => 'Failed to create user.'
		]);
	}
	
	// ✓ Exit early
	exit;
}

// ✓ INSERT SUCCEEDED - USER WAS CREATED

/**
 * GET INSERT ID
 * 
 * $stmt->insert_id returns the auto-generated ID of inserted row.
 * Used to fetch and return the created user.
 * 
 * Example: If this is the 3rd user:
 * $newId = 3
 */
$newId = $stmt->insert_id;

/**
 * CLOSE PREPARED STATEMENT
 * 
 * Free statement resources.
 */
$stmt->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 9: RETRIEVE CREATED USER (WITHOUT PASSWORD)                     │
// │                                                                           │
// │ Query the freshly created user to return to client                      │
// │ IMPORTANT: Don't return password hash!                                   │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE SELECT STATEMENT
 * 
 * SQL: SELECT id, name, role, is_active, created_at
 *      FROM users
 *      WHERE id = ?
 *      LIMIT 1
 * 
 * IMPORTANT: Does NOT select password_hash
 * Never return password or hash to client!
 */
$get = $conn->prepare("SELECT id, name, role, is_active, created_at FROM users WHERE id = ? LIMIT 1");

/**
 * CHECK IF PREPARE SUCCEEDED
 * 
 * If failed: $row stays null, but we still return success.
 * Graceful fallback: User was created, just can't return full data.
 */
if ($get) {
	/**
	 * BIND USER ID PARAMETER
	 * 
	 * 'i' = integer type
	 * Binds $newId to ? placeholder
	 */
	$get->bind_param('i', $newId);
	
	/**
	 * EXECUTE QUERY
	 */
	$get->execute();
	
	/**
	 * GET RESULT
	 */
	$res = $get->get_result();
	
	/**
	 * FETCH ROW
	 * 
	 * Retrieves the user as array.
	 * Does not include password_hash (for security).
	 */
	$row = $res ? $res->fetch_assoc() : null;
	
	/**
	 * CLOSE STATEMENT
	 */
	$get->close();
} else {
	/**
	 * PREPARE FAILED - SET ROW TO NULL
	 * 
	 * Will return success but without full user details.
	 * User was already created in database.
	 */
	$row = null;
}

/**
 * CLOSE DATABASE CONNECTION
 */
$conn->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 10: SUCCESS RESPONSE                                            │
// │                                                                           │
// │ Return created user to the client (WITHOUT password)                    │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * RETURN SUCCESS RESPONSE
 * 
 * HTTP 200: OK
 * 
 * {
 *   "success": true,
 *   "user": {
 *     "id": 2,
 *     "name": "john_doe",
 *     "role": "admin",
 *     "is_active": 1,
 *     "created_at": "2024-01-15 10:30:45"
 *   }
 * }
 * 
 * SECURITY NOTE: password_hash NOT included in response
 * 
 * Frontend (portal.js) receives this and:
 * 1. Adds user to list
 * 2. Shows success message
 * 3. Uses user data for display
 */
echo json_encode([
	'success' => true,
	'user' => $row
]);

// ═════════════════════════════════════════════════════════════════════════
// USER CREATION COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation Flow:
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated required fields (name, password)
// 3. ✓ Hashed password using PASSWORD_DEFAULT (bcrypt, adaptive)
// 4. ✓ Inserted user into database with UNIQUE constraint
// 5. ✓ Handled duplicate username error (409 Conflict)
// 6. ✓ Retrieved and returned user data (WITHOUT password)
// 
// Result: User is now in database, ready to log in and access portal
// 
// ═════════════════════════════════════════════════════════════════════════

?>


