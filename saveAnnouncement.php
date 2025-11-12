<?php
/**
 * =============================================================================
 * saveAnnouncement.php - Announcement Creation Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Creates a new announcement in the database.
 * Called by: portal.html (admin portal to create announcements)
 * 
 * REQUEST:
 * --------
 * POST /saveAnnouncement.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "message": "Server maintenance tonight",
 *   "severity": "warning",           (optional, default="info")
 *   "serverId": 1,                   (optional, null=global announcement)
 *   "startsAt": "2024-01-15T20:00",  (optional, datetime-local format)
 *   "endsAt": "2024-01-15T23:00",    (optional, datetime-local format)
 *   "isActive": 1                    (optional, default=1)
 * }
 * 
 * RESPONSE (SUCCESS):
 * -------------------
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "announcement": {
 *     "id": 5,
 *     "message": "Server maintenance tonight",
 *     "severity": "warning",
 *     "starts_at": "2024-01-15 20:00:00",
 *     "ends_at": "2024-01-15 23:00:00",
 *     "is_active": 1,
 *     "created_at": "2024-01-15 10:30:45",
 *     "updated_at": "2024-01-15 10:30:45",
 *     "server_id": 1,
 *     "server_name": "US Server 1",
 *     "battlemetrics_id": "123456"
 *   },
 *   "id": 5
 * }
 * 
 * RESPONSE (VALIDATION ERROR):
 * ----------------------------
 * HTTP 422 Unprocessable Entity
 * {
 *   "success": false,
 *   "message": "Message is required."
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
 *   "message": "Failed to save announcement."
 * }
 * 
 * DATA FLOW:
 * ----------
 * 1. Validate HTTP method (POST only)
 * 2. Create table if doesn't exist (idempotent)
 * 3. Parse and validate JSON request
 * 4. Normalize datetime fields
 * 5. Validate severity (whitelist)
 * 6. Insert announcement into database
 * 7. Retrieve and return saved announcement
 * 
 * SEVERITY LEVELS:
 * ----------------
 * 'info':     Informational (default, blue)
 * 'success':  Positive announcement (green)
 * 'warning':  Important alert (yellow/orange)
 * 'error':    Critical alert (red)
 * 
 * Any other value is converted to 'info' (safety).
 * 
 * DATETIME HANDLING:
 * ------------------
 * Input format:  HTML datetime-local (YYYY-MM-DDTHH:mm)
 * Example:       "2024-01-15T20:00"
 * Stored format: MySQL DATETIME (YYYY-MM-DD HH:mm:ss)
 * Example:       "2024-01-15 20:00:00"
 * 
 * Conversion:    Replace 'T' with ' ' and parse with strtotime()
 * 
 * NULL HANDLING:
 * - If startsAt is empty/null: Announcement shows immediately
 * - If endsAt is empty/null: Announcement shows indefinitely
 * - Both NULL: Announcement always visible (permanent)
 * 
 * GLOBAL ANNOUNCEMENTS:
 * ---------------------
 * If serverId is null or not provided: Announcement applies to ALL servers
 * Example: "All servers will restart at midnight" (no specific server)
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized queries (prevents SQL injection)
 * ✓ Input validation (message required)
 * ✓ Severity whitelisting (prevents injection via severity field)
 * ✓ DateTime normalization (prevents invalid dates)
 * ✓ NULL handling for optional fields
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
// │ Prevents accidental form submissions from creating announcements.        │
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
 * (Same schema as in getAnnouncements.php)
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
 */
$input = json_decode(file_get_contents('php://input'), true);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: EXTRACT AND NORMALIZE INPUT FIELDS                           │
// │                                                                           │
// │ EXTRACTION STRATEGY:                                                     │
// │ ────────────────────                                                     │
// │ isset():  Check if key exists (avoid "undefined key" errors)             │
// │ trim():   Remove leading/trailing whitespace                             │
// │ (type)(): Cast to appropriate type                                       │
// │ ?:        Use default if missing/falsy                                   │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * MESSAGE (REQUIRED)
 * 
 * The announcement text.
 * Trimmed to remove whitespace.
 * Empty string if not provided (will be validated below).
 */
$message = isset($input['message']) ? trim((string)$input['message']) : '';

/**
 * SEVERITY (OPTIONAL)
 * 
 * One of: 'info', 'success', 'warning', 'error'
 * Default: 'info' (if not provided or invalid)
 * 
 * Converted to lowercase for consistency.
 * Will be validated against whitelist below.
 */
$severity = isset($input['severity']) ? strtolower(trim((string)$input['severity'])) : 'info';

/**
 * SERVER_ID (OPTIONAL)
 * 
 * Integer ID of server for announcement.
 * NULL if not provided (= global announcement for all servers)
 * 
 * Logic:
 * - isset($input['serverId']): Key must exist
 * - $input['serverId'] !== '': Value must not be empty string
 * - (int)...: Cast to integer
 * - null: Otherwise NULL (global)
 * 
 * Example:
 * serverId: 1 → Announcement for server 1 only
 * serverId: null → Announcement for all servers
 * (not provided) → Announcement for all servers
 */
$serverId = isset($input['serverId']) && $input['serverId'] !== '' ? (int)$input['serverId'] : null;

/**
 * STARTS_AT (OPTIONAL)
 * 
 * When announcement becomes visible.
 * Format: HTML datetime-local (YYYY-MM-DDTHH:mm)
 * Example: "2024-01-15T20:00"
 * 
 * Empty string if not provided.
 * Will be normalized to MySQL DATETIME format below.
 */
$startsAt = isset($input['startsAt']) ? trim((string)$input['startsAt']) : '';

/**
 * ENDS_AT (OPTIONAL)
 * 
 * When announcement becomes invisible.
 * Format: HTML datetime-local (YYYY-MM-DDTHH:mm)
 * Example: "2024-01-15T23:00"
 * 
 * Empty string if not provided.
 * Will be normalized to MySQL DATETIME format below.
 */
$endsAt = isset($input['endsAt']) ? trim((string)$input['endsAt']) : '';

/**
 * IS_ACTIVE (OPTIONAL)
 * 
 * Boolean: Is announcement active (not soft-deleted)?
 * Default: 1 (yes, active)
 * 
 * (!!$input['isActive']): Double negation converts to boolean
 * (int)(...): Cast boolean to 1 or 0
 * 
 * Example:
 * isActive: true → 1
 * isActive: false → 0
 * (not provided) → 1 (default to active)
 */
$isActive = isset($input['isActive']) ? (int)(!!$input['isActive']) : 1;

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: VALIDATE REQUIRED FIELDS                                     │
// │                                                                           │
// │ VALIDATION: Only message is required                                     │
// │ All other fields are optional (have sensible defaults)                   │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK IF MESSAGE IS PROVIDED AND NON-EMPTY
 * 
 * Validation: Message is the ONLY required field.
 * All other fields have defaults or are optional.
 */
if ($message === '') {
	// ═════════════════════════════════════════════════════════════════════
	// VALIDATION FAILED: MESSAGE IS REQUIRED
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
		'message' => 'Message is required.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

// ✓ VALIDATION PASSED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: VALIDATE SEVERITY (WHITELIST)                                │
// │                                                                           │
// │ SECURITY: Severity must be one of known values                          │
// │ Prevents injection if severity is ever used in HTML or other context    │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * DEFINE ALLOWED SEVERITY VALUES
 * 
 * Whitelist approach: Only allow known values.
 * Any other value is rejected or defaulted.
 */
$allowedSeverities = ['info', 'success', 'warning', 'error'];

/**
 * CHECK IF PROVIDED SEVERITY IS IN WHITELIST
 * 
 * in_array($severity, $allowedSeverities, true):
 * - $severity: Value to check
 * - $allowedSeverities: Allowed values
 * - true: Strict type checking (string 'info' != integer 0)
 * 
 * If not in list: Default to 'info'
 */
if (!in_array($severity, $allowedSeverities, true)) {
	/**
	 * SEVERITY NOT IN WHITELIST
	 * 
	 * Default to 'info' for safety.
	 * Prevents potential issues from malicious input.
	 */
	$severity = 'info';
}

// ✓ $severity IS NOW GUARANTEED TO BE IN WHITELIST

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 8: NORMALIZE DATETIME FIELDS                                    │
// │                                                                           │
// │ DATETIME CONVERSION:                                                     │
// │ ──────────────────                                                       │
// │ Input format:  datetime-local HTML (YYYY-MM-DDTHH:mm)                   │
// │ Output format: MySQL DATETIME (YYYY-MM-DD HH:mm:ss)                     │
// │ Helper:        normalizeDateTime() function below                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * HELPER FUNCTION: normalizeDateTime()
 * 
 * Converts HTML datetime-local format to MySQL DATETIME format.
 * 
 * INPUT EXAMPLES:
 * - "2024-01-15T20:00" (datetime-local from HTML <input type="datetime-local">)
 * - "" (empty string)
 * - null
 * 
 * OUTPUT:
 * - "2024-01-15 20:00:00" (MySQL DATETIME format, success)
 * - null (invalid input, could not parse)
 * 
 * SECURITY:
 * - Uses strtotime() to parse (handles various formats safely)
 * - Returns null if parsing fails (invalid input rejected)
 * - Always returns valid format or null (no injection risk)
 */
function normalizeDateTime(?string $val): ?string {
	/**
	 * CHECK IF VALUE IS NULL OR EMPTY
	 * 
	 * If $val is null or empty string: Return null
	 * Null in database = "no time restriction" (always on)
	 */
	if ($val === null || $val === '') {
		return null;
	}
	
	/**
	 * REPLACE 'T' WITH SPACE
	 * 
	 * HTML datetime-local format: "2024-01-15T20:00"
	 * MySQL format needs: "2024-01-15 20:00:00"
	 * 
	 * Replace T separator with space: "2024-01-15 20:00"
	 */
	$val = str_replace('T', ' ', $val);
	
	/**
	 * PARSE WITH strtotime()
	 * 
	 * Flexible time parser. Returns Unix timestamp or false.
	 * 
	 * Example:
	 * strtotime("2024-01-15 20:00") → 1705347600
	 * strtotime("invalid") → false
	 */
	$ts = strtotime($val);
	
	/**
	 * CHECK IF PARSING SUCCEEDED
	 */
	if ($ts === false) {
		/**
		 * PARSING FAILED - INVALID DATETIME
		 * 
		 * Return null instead of error.
		 * In database: NULL = "no time restriction"
		 * 
		 * This is graceful: Invalid times just become "always on"
		 * Alternative: Could return error, but this is simpler.
		 */
		return null;
	}
	
	/**
	 * FORMAT UNIX TIMESTAMP AS MYSQL DATETIME
	 * 
	 * date() with format 'Y-m-d H:i:s' produces MySQL format.
	 * 
	 * Example:
	 * date('Y-m-d H:i:s', 1705347600) → "2024-01-15 20:00:00"
	 */
	return date('Y-m-d H:i:s', $ts);
}

/**
 * NORMALIZE STARTS_AT
 * 
 * Convert HTML datetime-local format to MySQL DATETIME.
 * Result: $startsAtDb = "2024-01-15 20:00:00" or null
 */
$startsAtDb = normalizeDateTime($startsAt);

/**
 * NORMALIZE ENDS_AT
 * 
 * Convert HTML datetime-local format to MySQL DATETIME.
 * Result: $endsAtDb = "2024-01-15 23:00:00" or null
 */
$endsAtDb = normalizeDateTime($endsAt);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 9: PREPARE INSERT STATEMENT                                     │
// │                                                                           │
// │ INSERT announcement record into database                                │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE INSERT STATEMENT
 * 
 * SQL: INSERT INTO announcements (server_id, message, severity, starts_at, ends_at, is_active)
 *      VALUES (?, ?, ?, ?, ?, ?)
 * 
 * Columns:
 * - server_id: NULL for global, or integer server ID
 * - message: Announcement text
 * - severity: 'info', 'warning', etc.
 * - starts_at: NULL or MySQL DATETIME
 * - ends_at: NULL or MySQL DATETIME
 * - is_active: 1 or 0
 * 
 * created_at, updated_at: Auto-populated by database (CURRENT_TIMESTAMP)
 */
$stmt = $conn->prepare("
	INSERT INTO announcements (server_id, message, severity, starts_at, ends_at, is_active)
	VALUES (?, ?, ?, ?, ?, ?)
");

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

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 10: BIND PARAMETERS (SPECIAL: NULL VALUES)                      │
// │                                                                           │
// │ IMPORTANT: bind_param requires variables by reference (&$var)            │
// │ NULL values need special handling                                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CREATE LOCAL VARIABLES FOR BINDING
 * 
 * bind_param() requires variables passed by REFERENCE (&$var).
 * Can't bind literals like bind_param('i', 5) or bind_param('i', $var, 10).
 * 
 * Solution: Create local variables, bind those, then execute.
 * 
 * Variables:
 * - $serverIdParam: May be null or integer
 * - $messageParam: String
 * - $severityParam: String (in whitelist)
 * - $startsAtParam: May be null or datetime string
 * - $endsAtParam: May be null or datetime string
 * - $isActiveParam: Integer (1 or 0)
 */
$serverIdParam = $serverId;
$messageParam = $message;
$severityParam = $severity;
$startsAtParam = $startsAtDb;
$endsAtParam = $endsAtDb;
$isActiveParam = $isActive;

/**
 * BIND PARAMETERS
 * 
 * Format string: 'issssi'
 * - i: integer (server_id)
 * - s: string (message)
 * - s: string (severity)
 * - s: string (starts_at)
 * - s: string (ends_at)
 * - i: integer (is_active)
 * 
 * Variable list: $serverIdParam, $messageParam, ..., $isActiveParam
 * All passed by reference (&$var).
 * 
 * NULL HANDLING:
 * If $serverIdParam = null: bind_param converts to NULL in query
 * If $startsAtParam = null: bind_param converts to NULL in query
 * This is correct: NULL stays NULL in database.
 */
$stmt->bind_param(
	'issssi',
	$serverIdParam,
	$messageParam,
	$severityParam,
	$startsAtParam,
	$endsAtParam,
	$isActiveParam
);

/**
 * EXECUTE THE STATEMENT
 * 
 * Sends INSERT query to database.
 * Returns true on success, false on failure.
 */
if (!$stmt->execute()) {
	// ═════════════════════════════════════════════════════════════════════
	// QUERY EXECUTION FAILED
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
		'message' => 'Failed to save announcement.',
		'details' => $stmt->error
	]);
	
	// ✓ Close statement and connection, then exit
	$stmt->close();
	$conn->close();
	exit;
}

/**
 * GET INSERT ID
 * 
 * $stmt->insert_id returns the auto-generated ID of inserted row.
 * Used to fetch and return the created announcement.
 * 
 * Example: If announcement.id is auto_increment,
 * and this is the 5th announcement:
 * $insertId = 5
 */
$insertId = $stmt->insert_id;

/**
 * CLOSE PREPARED STATEMENT
 * 
 * Free statement resources.
 */
$stmt->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 11: RETRIEVE SAVED ANNOUNCEMENT                                 │
// │                                                                           │
// │ Query the freshly saved announcement to return to client                 │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * BUILD SELECT QUERY
 * 
 * SQL: SELECT a.*, s.display_name, s.battlemetrics_id
 *      FROM announcements a
 *      LEFT JOIN servers s ON s.id = a.server_id
 *      WHERE a.id = ?
 *      LIMIT 1
 * 
 * Fetches the announcement we just created, with server details.
 * LEFT JOIN: Keeps global announcements (server_id = NULL).
 * LIMIT 1: Only one row (id is primary key).
 */
$sql = "
	SELECT
		a.id,
		a.message,
		a.severity,
		a.starts_at,
		a.ends_at,
		a.is_active,
		a.created_at,
		a.updated_at,
		a.server_id,
		s.display_name AS server_name,
		s.battlemetrics_id
	FROM announcements a
	LEFT JOIN servers s ON s.id = a.server_id
	WHERE a.id = ?
	LIMIT 1
";

/**
 * PREPARE SELECT STATEMENT
 */
$stmt2 = $conn->prepare($sql);

/**
 * CHECK IF PREPARE SUCCEEDED
 * 
 * If failed: $row stays null, we return announcement with id but no details.
 * Graceful fallback: Always return success, just without full data.
 */
if ($stmt2) {
	/**
	 * BIND INSERT ID PARAMETER
	 * 
	 * 'i' = integer type
	 * Binds $insertId to ? placeholder
	 */
	$stmt2->bind_param('i', $insertId);
	
	/**
	 * EXECUTE SELECT
	 */
	$stmt2->execute();
	
	/**
	 * GET RESULT
	 */
	$res = $stmt2->get_result();
	
	/**
	 * FETCH ROW
	 * 
	 * Retrieves the announcement as array.
	 * Includes server_name and battlemetrics_id from JOIN.
	 */
	$row = $res ? $res->fetch_assoc() : null;
	
	/**
	 * CLOSE STATEMENT
	 */
	$stmt2->close();
} else {
	/**
	 * PREPARE FAILED - SET ROW TO NULL
	 * 
	 * Will return success but without full announcement details.
	 * Will still include id in response.
	 */
	$row = null;
}

/**
 * CLOSE DATABASE CONNECTION
 */
$conn->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 12: SUCCESS RESPONSE                                            │
// │                                                                           │
// │ Return created announcement to the client                               │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * RETURN SUCCESS RESPONSE
 * 
 * HTTP 200: OK
 * 
 * {
 *   "success": true,
 *   "announcement": { ... },  (full announcement object if query succeeded)
 *   "id": 5                   (announcement ID, always provided)
 * }
 * 
 * Frontend (portal.js) receives this and:
 * 1. Adds announcement to list
 * 2. Shows success message
 * 3. Can use id for future references
 */
echo json_encode([
	'success' => true,
	'announcement' => $row,
	'id' => $insertId
]);

// ═════════════════════════════════════════════════════════════════════════
// ANNOUNCEMENT CREATION COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation Flow:
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated required fields (message)
// 3. ✓ Validated severity (whitelist)
// 4. ✓ Normalized datetime fields (HTML → MySQL)
// 5. ✓ Inserted announcement into database
// 6. ✓ Retrieved and returned saved announcement
// 
// Result: Announcement is now in database, ready to display to users
// 
// ═════════════════════════════════════════════════════════════════════════

?>


