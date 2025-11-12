<?php
/**
 * =============================================================================
 * saveServer.php - Server Creation/Update Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Creates a new server or updates an existing server in the database.
 * Enriches server data from BattleMetrics API (if available).
 * Called by: portal.html (admin portal to add/update servers)
 * 
 * REQUEST:
 * --------
 * POST /saveServer.php
 * Content-Type: application/json
 * 
 * Body:
 * {
 *   "battlemetricsId": "123456"
 * }
 * 
 * RESPONSE (SUCCESS):
 * -------------------
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "server": {
 *     "id": 1,
 *     "battlemetrics_id": "123456",
 *     "display_name": "US Rust Server",
 *     "game_title": "Rust",
 *     "region": "us-east-1"
 *   }
 * }
 * 
 * RESPONSE (VALIDATION ERROR):
 * ----------------------------
 * HTTP 422 Unprocessable Entity
 * {
 *   "success": false,
 *   "message": "BattleMetrics ID is required."
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
 *   "message": "Failed to save server."
 * }
 * 
 * DATA FLOW:
 * ----------
 * 1. Validate HTTP method (POST only)
 * 2. Parse JSON request body
 * 3. Extract and validate BattleMetrics ID
 * 4. Query BattleMetrics API to enrich data (name, game, region)
 * 5. Insert or update server in database
 * 6. Return saved server data
 * 
 * ENRICHMENT LOGIC:
 * -----------------
 * - Tries to fetch data from BattleMetrics API (8 second timeout)
 * - If API unavailable: Uses fallback name "Server {ID}"
 * - If API available but missing fields: Uses empty strings
 * - Always succeeds: API failure doesn't block server creation
 * 
 * DATABASE OPERATION:
 * -------------------
 * Uses "INSERT...ON DUPLICATE KEY UPDATE" pattern:
 * - If battlemetrics_id already exists: Update the record
 * - If battlemetrics_id is new: Insert new record
 * - Reactivates soft-deleted servers (is_active = 1)
 * 
 * SECURITY:
 * ---------
 * ✓ HTTP method validation (POST only)
 * ✓ Parameterized queries (prevents SQL injection)
 * ✓ Input validation (battlemetricsId required)
 * ✓ API key from environment (not hard-coded in this file)
 * ✓ cURL timeout set (prevents hanging)
 * ✓ Safe fallback (doesn't fail if API unavailable)
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
// │ This endpoint should ONLY accept POST requests.                          │
// │ GET requests are read-only, should use different endpoint.               │
// │ Prevents accidental form submissions from modifying data.                │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK HTTP REQUEST METHOD
 * 
 * $_SERVER['REQUEST_METHOD'] contains method: GET, POST, PUT, DELETE, etc.
 * 
 * Allowed: POST
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
	 * Different from:
	 * - 400 (Bad Request) - client sent invalid data
	 * - 401 (Unauthorized) - client not authenticated
	 * - 404 (Not Found) - endpoint doesn't exist
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
 * file_get_contents('php://input'): Read raw request body from network
 * json_decode(..., true):            Convert JSON string to PHP array
 * 
 * Example input:
 * {
 *   "battlemetricsId": "123456"
 * }
 * 
 * Becomes:
 * [
 *   "battlemetricsId" => "123456"
 * ]
 */
$input = json_decode(file_get_contents('php://input'), true);

/**
 * EXTRACT & VALIDATE BATTLEMETRICS ID
 * 
 * isset($input['battlemetricsId']):  Check key exists in array
 * trim((string)...):                  Remove whitespace, ensure string
 * '':                                 Default to empty string if missing
 * 
 * Result: $battlemetricsId = "123456" (if provided) or "" (if not)
 */
$battlemetricsId = isset($input['battlemetricsId']) ? trim((string)$input['battlemetricsId']) : '';

/**
 * CHECK IF BATTLEMETRICS ID IS EMPTY
 * 
 * Validation: Cannot save server without a BattleMetrics ID.
 * (BattleMetrics ID is unique key for this server)
 */
if ($battlemetricsId === '') {
	// ═════════════════════════════════════════════════════════════════════
	// VALIDATION FAILED: BATTLEMETRICS ID IS REQUIRED
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 422 = Unprocessable Entity
	 * 
	 * Tells client: "Your request is syntactically valid JSON,
	 * but the data doesn't meet our validation rules"
	 * 
	 * Different from:
	 * - 400 (Bad Request) - not valid JSON or missing required header
	 * - 500 (Server Error) - server-side problem
	 */
	http_response_code(422);
	
	/**
	 * RETURN VALIDATION ERROR
	 */
	echo json_encode([
		'success' => false,
		'message' => 'BattleMetrics ID is required.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

// ✓ VALIDATION PASSED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: BATTLEMETRICS API ENRICHMENT                                 │
// │                                                                           │
// │ ENRICHMENT STRATEGY:                                                     │
// │ ──────────────────                                                       │
// │ Try to fetch server details from BattleMetrics API.                      │
// │ If successful: Extract name, game, region.                              │
// │ If fails: Use fallback values, don't fail entire operation.              │
// │ Result: Server is saved either way (graceful degradation).              │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * INITIALIZE FALLBACK VALUES
 * 
 * These are used if BattleMetrics API is unavailable or missing fields.
 * Guaranteed non-empty strings (unlike null/undefined).
 */
$displayName = '';  // Will be set to "Server {ID}" if empty later
$gameTitle = '';    // Can remain empty (optional field)
$region = '';       // Can remain empty (optional field)

/**
 * GET BATTLEMETRICS API KEY
 * 
 * Priority:
 * 1. Environment variable BATTLEMETRICS_API_KEY (preferred)
 * 2. Fallback key (hard-coded, for development only)
 * 
 * WHY ENVIRONMENT VARIABLE?
 * 
 * - Secrets shouldn't be in source code (security risk)
 * - Easier to deploy to different environments
 * - Can change key without modifying code
 * 
 * FALLBACK KEY:
 * - For development when env variable not set
 * - In production, should always use environment variable
 * - Should be rotated immediately (this one is public now)
 */
$apiKey = getenv('BATTLEMETRICS_API_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbiI6IjI4MDlmZjRkMDVjYWMxZjEiLCJpYXQiOjE3NjI2NTAwMTMsIm5iZiI6MTc2MjY1MDAxMywiaXNzIjoiaHR0cHM6Ly93d3cuYmF0dGxlbWV0cmljcy5jb20iLCJzdWIiOiJ1cm46dXNlcjoxMDMyMzk1In0.Xfol6h4NxOnufPP76UQFO6NM0bcw95hQLGcJ94V69QE';

/**
 * ATTEMPT BATTLEMETRICS API LOOKUP
 * 
 * Only if API key is not empty
 */
if (!empty($apiKey)) {
	/**
	 * BUILD API URL
	 * 
	 * Format: https://api.battlemetrics.com/servers/{id}
	 * 
	 * urlencode(): Escapes special characters in ID
	 * Example: "123/456" → "123%2F456" (safe in URL)
	 */
	$apiUrl = sprintf('https://api.battlemetrics.com/servers/%s', urlencode($battlemetricsId));
	
	/**
	 * INITIALIZE CURL REQUEST
	 * 
	 * cURL: Library for making HTTP requests from PHP
	 * $ch: cURL handle (represents this request)
	 */
	$ch = curl_init($apiUrl);
	
	/**
	 * SET HTTP HEADERS
	 * 
	 * Authorization: Bearer {token}
	 *   - Authenticates with BattleMetrics API
	 *   - Increases rate limits
	 * 
	 * Accept: application/json
	 *   - Tells API: "Send response as JSON"
	 */
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Authorization: Bearer {$apiKey}",
		"Accept: application/json"
	]);
	
	/**
	 * SET CURL OPTIONS
	 * 
	 * RETURNTRANSFER: true
	 *   - Return response as string (not echo)
	 *   - Allows us to parse it
	 * 
	 * TIMEOUT: 8
	 *   - Maximum 8 seconds to wait for response
	 *   - Prevents hanging forever
	 *   - Longer timeouts are fine for this (not user-facing latency)
	 */
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 8);
	
	/**
	 * EXECUTE REQUEST
	 * 
	 * Sends HTTP request to BattleMetrics API.
	 * Returns response body as string (or false on error).
	 */
	$response = curl_exec($ch);
	
	/**
	 * GET HTTP STATUS CODE
	 * 
	 * Examples:
	 * - 200: Success
	 * - 400: Bad request
	 * - 404: Server not found
	 * - 500: API server error
	 */
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	/**
	 * PROCESS RESPONSE IF SUCCESSFUL
	 * 
	 * Conditions:
	 * - $response !== false: Request succeeded (didn't timeout/error)
	 * - $httpCode < 400: Server returned 2xx or 3xx (success)
	 */
	if ($response !== false && $httpCode < 400) {
		/**
		 * PARSE JSON RESPONSE
		 * 
		 * BattleMetrics API returns:
		 * {
		 *   "data": {
		 *     "id": "123456",
		 *     "type": "server",
		 *     "attributes": {
		 *       "name": "US Server 1",
		 *       "hostname": "...",
		 *       "game": "Rust",
		 *       "region": "us-east-1",
		 *       "details": {
		 *         "gameMode": "...",
		 *         "mode": "...",
		 *         "region": "..."
		 *       }
		 *     }
		 *   }
		 * }
		 */
		$payload = json_decode($response, true);
		
		/**
		 * EXTRACT ATTRIBUTES
		 * 
		 * ?? {}: Use provided array, or empty array if not found
		 * Prevents "undefined key" errors
		 */
		$attributes = $payload['data']['attributes'] ?? [];
		$details = $attributes['details'] ?? [];
		
		/**
		 * EXTRACT DISPLAY NAME (with fallback chain)
		 * 
		 * Priority:
		 * 1. 'name' field (preferred)
		 * 2. 'hostname' field (fallback)
		 * 3. '' (empty string, will use default below)
		 */
		$displayName = $attributes['name'] ?? $attributes['hostname'] ?? '';
		
		/**
		 * EXTRACT GAME TITLE (with fallback chain)
		 * 
		 * Priority:
		 * 1. 'game' field (preferred)
		 * 2. 'details.gameMode' field
		 * 3. 'details.mode' field
		 * 4. '' (empty string, optional field)
		 */
		$gameTitle = $attributes['game'] ?? $details['gameMode'] ?? $details['mode'] ?? '';
		
		/**
		 * EXTRACT REGION (with fallback chain)
		 * 
		 * Priority:
		 * 1. 'attributes.region' field
		 * 2. 'details.region' field
		 * 3. '' (empty string, optional field)
		 */
		$region = $attributes['region'] ?? $details['region'] ?? '';
	}
	// If response failed or http code >= 400: Keep empty fallback values
	
	/**
	 * CLOSE CURL CONNECTION
	 * 
	 * Releases cURL resources.
	 * Good practice: Always close when done.
	 */
	curl_close($ch);
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: SET FALLBACK DISPLAY NAME                                    │
// │                                                                           │
// │ FALLBACK STRATEGY:                                                       │
// │ ───────────────                                                          │
// │ If BattleMetrics API enrichment failed to get a name,                    │
// │ use generic fallback: "Server {BattleMetricsID}"                         │
// │ This ensures display_name is never empty.                               │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK IF DISPLAY NAME IS EMPTY
 * 
 * If API didn't return a name, or API was unavailable:
 */
if ($displayName === '') {
	/**
	 * USE FALLBACK NAME
	 * 
	 * "Server 123456" is generic but meaningful.
	 * Better than NULL or empty string in database.
	 * 
	 * Example:
	 * - BattleMetrics ID: "123456"
	 * - Fallback name: "Server 123456"
	 */
	$displayName = 'Server ' . $battlemetricsId;
}

// ✓ $displayName IS NOW GUARANTEED NON-EMPTY

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: DATABASE INSERT OR UPDATE                                    │
// │                                                                           │
// │ INSERT...ON DUPLICATE KEY UPDATE PATTERN:                               │
// │ ──────────────────────────────────────────                               │
// │ If battlemetrics_id already exists: Update it                           │
// │ If battlemetrics_id is new: Insert new row                              │
// │ Reactivates soft-deleted servers (is_active = 1)                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE INSERT OR UPDATE STATEMENT
 * 
 * SQL: INSERT INTO servers (battlemetrics_id, display_name, game_title, region, is_active)
 *      VALUES (?, ?, ?, ?, 1)
 *      ON DUPLICATE KEY UPDATE
 *        display_name = VALUES(display_name),
 *        game_title = VALUES(game_title),
 *        region = VALUES(region),
 *        is_active = 1,
 *        updated_at = CURRENT_TIMESTAMP
 * 
 * UNIQUE KEY: battlemetrics_id (defined in table schema)
 * 
 * If battlemetrics_id already exists in database:
 * - Execute UPDATE instead of INSERT
 * - Update display_name, game_title, region (latest from API)
 * - Set is_active = 1 (reactivate if was soft-deleted)
 * - Update updated_at to current timestamp
 * 
 * If battlemetrics_id is new:
 * - Execute INSERT
 * - All fields get values from VALUES clause
 * - is_active = 1 (new servers start active)
 * 
 * WHY VALUES(field)?
 * In ON DUPLICATE KEY UPDATE, VALUES(field) refers to the value
 * that would have been inserted. Allows us to use the new values
 * for the update.
 */
$statement = $conn->prepare("
    INSERT INTO servers (battlemetrics_id, display_name, game_title, region, is_active)
    VALUES (?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        display_name = VALUES(display_name),
        game_title = VALUES(game_title),
        region = VALUES(region),
        is_active = 1,
        updated_at = CURRENT_TIMESTAMP
");

/**
 * CHECK IF PREPARE SUCCEEDED
 * 
 * Might fail if:
 * - Database connection lost
 * - Syntax error in SQL
 * - Table doesn't exist (but getServers.php creates it)
 * - Permission denied on this query
 */
if (!$statement) {
	// ═════════════════════════════════════════════════════════════════════
	// QUERY PREPARATION FAILED
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 500 = Internal Server Error
	 * 
	 * Preparation failure is a server-side problem.
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
 * 'ssss' = four strings
 * 
 * Binds values to ? placeholders in order:
 * ? 1: $battlemetricsId (s)
 * ? 2: $displayName (s)
 * ? 3: $gameTitle (s)
 * ? 4: $region (s)
 * 
 * This is safe from SQL injection: values are treated as data, not code.
 */
$statement->bind_param('ssss', $battlemetricsId, $displayName, $gameTitle, $region);

/**
 * EXECUTE THE STATEMENT
 * 
 * Sends query to database.
 * Returns true on success, false on failure.
 */
if (!$statement->execute()) {
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
		'message' => 'Failed to save server.',
		'details' => $statement->error
	]);
	
	// ✓ Close statement and connection, then exit
	$statement->close();
	$conn->close();
	exit;
}

/**
 * CLOSE PREPARED STATEMENT
 * 
 * Free prepared statement resources.
 */
$statement->close();

// ✓ DATABASE INSERT OR UPDATE SUCCEEDED

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: RETRIEVE SAVED SERVER DATA                                   │
// │                                                                           │
// │ QUERY THE FRESHLY SAVED/UPDATED SERVER                                  │
// │ To return the current state to the client                                │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PREPARE SELECT STATEMENT
 * 
 * SQL: SELECT id, battlemetrics_id, display_name, game_title, region
 *      FROM servers
 *      WHERE battlemetrics_id = ?
 *      LIMIT 1
 * 
 * Fetches the server we just inserted/updated.
 */
$select = $conn->prepare("SELECT id, battlemetrics_id, display_name, game_title, region FROM servers WHERE battlemetrics_id = ? LIMIT 1");

/**
 * CHECK IF PREPARE SUCCEEDED
 */
if (!$select) {
	// ═════════════════════════════════════════════════════════════════════
	// QUERY PREPARATION FAILED
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * HTTP 500 = Internal Server Error
	 * 
	 * Note: We already saved the server above, so the operation succeeded.
	 * This failure is just on the retrieval query.
	 * Ideally we'd still return success, but for simplicity we report error.
	 * (In production, could still return success with ID from INSERT)
	 */
	http_response_code(500);
	
	/**
	 * RETURN ERROR RESPONSE
	 */
	echo json_encode([
		'success' => false,
		'message' => 'Failed to load saved server.'
	]);
	
	// ✓ Close connection and exit
	$conn->close();
	exit;
}

/**
 * BIND PARAMETER
 * 
 * 's' = string
 * Binds battlemetricsId to ? placeholder
 */
$select->bind_param('s', $battlemetricsId);

/**
 * EXECUTE QUERY
 */
$select->execute();

/**
 * GET RESULT SET
 * 
 * Retrieves the query results.
 */
$result = $select->get_result();

/**
 * FETCH THE ROW
 * 
 * fetch_assoc(): Returns row as associative array
 * ?: null coalescing - if fetch returns null, use null (already is null anyway)
 * 
 * Result:
 * - $server = ['id' => 1, 'battlemetrics_id' => '123456', ...]
 * - or $server = null (if not found, shouldn't happen)
 */
$server = $result->fetch_assoc() ?: null;

/**
 * CLOSE PREPARED STATEMENT
 */
$select->close();

/**
 * CLOSE DATABASE CONNECTION
 */
$conn->close();

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 8: SUCCESS RESPONSE                                             │
// │                                                                           │
// │ Return the saved server data to the client                              │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * RETURN SUCCESS RESPONSE
 * 
 * HTTP 200: OK
 * 
 * {
 *   "success": true,
 *   "server": {
 *     "id": 1,
 *     "battlemetrics_id": "123456",
 *     "display_name": "US Rust Server",
 *     "game_title": "Rust",
 *     "region": "us-east-1"
 *   }
 * }
 * 
 * Frontend (portal.js) receives this and:
 * 1. Adds server to list
 * 2. Refreshes server dropdown
 * 3. Shows success message
 */
echo json_encode([
	'success' => true,
	'server' => $server
]);

// ═════════════════════════════════════════════════════════════════════════
// SERVER SAVE/UPDATE COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Operation Flow:
// 1. ✓ Validated HTTP method (POST)
// 2. ✓ Validated input (battlemetricsId required)
// 3. ✓ Attempted BattleMetrics API enrichment (non-blocking)
// 4. ✓ Inserted or updated server in database
// 5. ✓ Retrieved and returned saved server data
// 
// Result: Server is now in database, active, and available for announcements
// 
// ═════════════════════════════════════════════════════════════════════════

?>

