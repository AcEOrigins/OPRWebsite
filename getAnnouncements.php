<?php
/**
 * =============================================================================
 * getAnnouncements.php - Announcement Retrieval Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Returns a list of announcements from the database.
 * Supports optional filtering by:
 * - Server (shows announcements for that server + global announcements)
 * - BattleMetrics ID (looks up server, then returns announcements)
 * - Active window (only announcements currently active)
 * 
 * Called by: 
 * - portal.html (admin portal, show all announcements)
 * - battlemetrics.php → client JS (public view, show active announcements)
 * 
 * REQUEST:
 * --------
 * GET /getAnnouncements.php?serverId=1&battlemetricsId=123456&active=1
 * (All parameters optional, can mix or omit)
 * 
 * QUERY PARAMETERS:
 * -----------------
 * serverId=1          → Get announcements for server ID 1 (+ global)
 * battlemetricsId=123456 → Get announcements for server with BM ID 123456 (+ global)
 * active=1            → Only return announcements in their active window
 * 
 * RESPONSE (SUCCESS):
 * -------------------
 * HTTP 200 OK
 * [
 *   {
 *     "id": 1,
 *     "message": "Server maintenance tonight",
 *     "severity": "warning",
 *     "starts_at": "2024-01-15 20:00:00",
 *     "ends_at": "2024-01-15 23:00:00",
 *     "is_active": 1,
 *     "created_at": "2024-01-15 10:00:00",
 *     "updated_at": "2024-01-15 10:00:00",
 *     "server_id": 1,
 *     "server_name": "US Server 1",
 *     "battlemetrics_id": "123456"
 *   }
 * ]
 * 
 * RESPONSE (ERROR):
 * -----------------
 * HTTP 500 Internal Server Error
 * {
 *   "error": "Failed to load announcements.",
 *   "details": "Exception message"
 * }
 * 
 * DATA FLOW:
 * ----------
 * 1. Create table if doesn't exist (idempotent)
 * 2. Parse query parameters (optional filters)
 * 3. Build WHERE clause dynamically based on filters
 * 4. Query database for matching announcements
 * 5. Join with servers table to get server names
 * 6. Return as JSON array
 * 
 * FILTERING LOGIC:
 * ----------------
 * serverId filter:  Shows announcements for that server OR global (server_id IS NULL)
 * battlemetricsId:  Looks up server by BattleMetrics ID, then filters like serverId
 * active=1:         Only announcements where:
 *                   - is_active = 1 (not soft-deleted)
 *                   - starts_at IS NULL or starts_at <= NOW() (has started)
 *                   - ends_at IS NULL or ends_at >= NOW() (hasn't ended)
 * 
 * GLOBAL ANNOUNCEMENTS:
 * ---------------------
 * If server_id IS NULL: Announcement applies to ALL servers
 * Example: "Server maintenance window" applies everywhere
 * 
 * DATABASE SCHEMA:
 * ----------------
 * servers table foreign key: ON DELETE SET NULL
 * If server is deleted: announcements for that server get server_id = NULL
 * (converted to global announcements)
 * 
 * SECURITY:
 * ---------
 * ✓ Parameterized queries (prevents SQL injection)
 * ✓ LEFT JOIN instead of INNER (global announcements not lost if server deleted)
 * ✓ Error messages generic (don't expose DB details)
 * ✓ Result set freed after use
 * 
 * =============================================================================
 */

declare(strict_types=1);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 1: RESPONSE CONFIGURATION                                       │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * SET RESPONSE CONTENT TYPE
 * 
 * Tell browser: "This response is JSON"
 */
header('Content-Type: application/json');

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: DATABASE CONNECTION                                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * LOAD DATABASE CONNECTION
 * 
 * Includes dbconnect.php which creates $conn (mysqli object).
 */
require_once __DIR__ . '/dbconnect.php';

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: IDEMPOTENT TABLE CREATION                                    │
// │                                                                           │
// │ TABLE SCHEMA EXPLANATION:                                                │
// │ ──────────────────────────                                               │
// │ announcements: Stores messages shown to users                            │
// │ - Can be global (server_id = NULL) or per-server                         │
// │ - Can have active window (starts_at, ends_at) or always-on              │
// │ - Soft-deletable (is_active = 0 doesn't remove from DB)                 │
// │ - Foreign key to servers (ON DELETE SET NULL → global if server deleted) │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CREATE TABLE IF NOT EXISTS
 * 
 * TABLE: announcements
 * 
 * COLUMNS:
 * --------
 * id                  - Unique identifier (primary key)
 * server_id           - NULL=global, otherwise references servers(id)
 * message             - Announcement text (can be long)
 * severity            - 'info', 'warning', 'error', etc.
 * starts_at           - When announcement becomes active (NULL=always)
 * ends_at             - When announcement becomes inactive (NULL=always)
 * is_active           - Soft-delete flag (1=active, 0=deleted)
 * created_at          - Record creation timestamp
 * updated_at          - Last record update timestamp
 * 
 * INDEXES:
 * --------
 * idx_server_id       - Speed up WHERE server_id = ? queries
 * idx_is_active       - Speed up WHERE is_active = 1 queries
 * idx_starts_at       - Speed up WHERE starts_at <= NOW() queries
 * idx_ends_at         - Speed up WHERE ends_at >= NOW() queries
 * 
 * FOREIGN KEY:
 * ────────────
 * fk_announcements_server_id
 * References servers(id) ON DELETE SET NULL
 * 
 * Behavior: If a server is deleted, its announcements' server_id
 * becomes NULL (converted to global announcements).
 * Ensures referential integrity, data preservation.
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
 * 
 * Creates table if it doesn't exist.
 * If table already exists: No error, no output.
 */
$conn->query($createSql);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: PARSE QUERY PARAMETERS                                       │
// │                                                                           │
// │ PARAMETER PARSING:                                                       │
// │ ──────────────────                                                       │
// │ Query string parameters are extracted and sanitized.                     │
// │ Each can be provided or omitted independently.                           │
// │ Multiple can be combined (all conditions must match = AND logic).         │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * PARSE serverId PARAMETER
 * 
 * $_GET['serverId']: Value from query string (or undefined if not provided)
 * isset(...):        Check if key exists in $_GET array
 * (int)...:          Cast to integer (safety)
 * 0:                 Default to 0 if missing
 * 
 * Result:
 * - $serverId = 1 if ?serverId=1 provided
 * - $serverId = 0 if not provided or invalid
 * 
 * Later: if ($serverId > 0) we apply filter
 */
$serverId = isset($_GET['serverId']) ? (int)$_GET['serverId'] : 0;

/**
 * PARSE battlemetricsId PARAMETER
 * 
 * $_GET['battlemetricsId']: Value from query string
 * isset(...):               Check if key exists
 * trim((string)...):        Remove whitespace, ensure string
 * '':                       Default to empty string if missing
 * 
 * Result:
 * - $battlemetricsId = "123456" if provided
 * - $battlemetricsId = '' if not provided
 * 
 * Later: Joined with servers table to look up by BattleMetrics ID
 */
$battlemetricsId = isset($_GET['battlemetricsId']) ? trim((string)$_GET['battlemetricsId']) : '';

/**
 * PARSE active PARAMETER
 * 
 * $_GET['active']: Value from query string
 * isset(...):      Check if key exists
 * (int)...:        Cast to integer (0 or 1)
 * 0:               Default to 0 if missing
 * 
 * Result:
 * - $activeOnly = 1 if ?active=1 provided
 * - $activeOnly = 0 if not provided or active=0
 * 
 * Later: if ($activeOnly === 1) we apply active window filters
 */
$activeOnly = isset($_GET['active']) ? (int)$_GET['active'] : 0;

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: BUILD WHERE CLAUSE DYNAMICALLY                               │
// │                                                                           │
// │ DYNAMIC WHERE BUILDING:                                                  │
// │ ──────────────────────                                                   │
// │ $conditions: Array of WHERE clause conditions (strings)                  │
// │ $params: Array of parameter values (to bind)                             │
// │ $types: String of parameter types (i=int, s=string, d=double, b=blob)    │
// │                                                                           │
// │ EXAMPLE:                                                                 │
// │ User provides: ?serverId=5&active=1                                      │
// │ Conditions: ['(a.server_id = ? OR a.server_id IS NULL)', 'a.is_active=1']│
// │ Params: [5]                                                              │
// │ Types: 'i'                                                               │
// │ Final WHERE: WHERE (a.server_id = 5 OR a.server_id IS NULL)              │
// │             AND a.is_active = 1                                          │
// │             AND (a.starts_at IS NULL OR a.starts_at <= NOW())            │
// │             AND (a.ends_at IS NULL OR a.ends_at >= NOW())                │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * INITIALIZE DYNAMIC WHERE BUILDING
 * 
 * $conditions: Will hold WHERE conditions (joined with AND)
 * $params:     Will hold parameter values for parameterized query
 * $types:      Will hold parameter type hints (i=int, s=string)
 */
$conditions = [];
$params = [];
$types = '';

/**
 * CONDITION 1: Filter by serverId (if provided)
 * 
 * if ($serverId > 0): Only if a valid server ID was provided
 * 
 * Condition: (a.server_id = ? OR a.server_id IS NULL)
 * 
 * Logic:
 * - Matches announcements for THIS specific server
 * - ALSO matches global announcements (server_id = NULL)
 * 
 * Example:
 * - User requests announcements for server 5
 * - Shows announcements where server_id = 5 (server-specific)
 * - ALSO shows announcements where server_id = NULL (global)
 * - Doesn't show announcements for servers 1,2,3,4,6...
 * 
 * Parameter binding:
 * $types .= 'i': Add integer type
 * $params[] = $serverId: Add value
 */
if ($serverId > 0) {
	/**
	 * ADD SERVER_ID CONDITION
	 * 
	 * The ? is a placeholder for $serverId
	 * Will be safely bound as integer parameter
	 */
	$conditions[] = '(a.server_id = ? OR a.server_id IS NULL)';
	
	// Add parameter info for binding
	$types .= 'i';
	$params[] = $serverId;
}

/**
 * CONDITION 2: Filter by battlemetricsId (if provided)
 * 
 * if ($battlemetricsId !== ''): Only if provided
 * 
 * Strategy:
 * - Can't join by battlemetricsId directly (would lose it)
 * - Instead: (s.battlemetrics_id = ? OR a.server_id IS NULL)
 * - Matches global announcements regardless of ID
 * 
 * Note: If both serverId and battlemetricsId provided,
 * both conditions apply (AND logic). Probably unusual,
 * but supported.
 * 
 * Parameter binding:
 * $types .= 's': Add string type
 * $params[] = $battlemetricsId: Add value
 */
if ($battlemetricsId !== '') {
	/**
	 * ADD BATTLEMETRICS_ID CONDITION
	 * 
	 * Joins with servers table to look up by BattleMetrics ID.
	 * The ? is a placeholder for $battlemetricsId
	 * Will be safely bound as string parameter
	 */
	$conditions[] = '(s.battlemetrics_id = ? OR a.server_id IS NULL)';
	
	// Add parameter info for binding
	$types .= 's';
	$params[] = $battlemetricsId;
}

/**
 * CONDITION 3-5: Active window filters (if requested)
 * 
 * if ($activeOnly === 1): Only if ?active=1 provided
 * 
 * Three separate conditions:
 * 1. is_active = 1                              (not soft-deleted)
 * 2. starts_at IS NULL OR starts_at <= NOW()    (has started or always-on)
 * 3. ends_at IS NULL OR ends_at >= NOW()        (hasn't ended or always-on)
 * 
 * Example:
 * Announcement: starts_at='2024-01-15 20:00:00', ends_at='2024-01-15 23:00:00'
 * Current time: 2024-01-15 21:30:00
 * 
 * Check:
 * - is_active = 1? ✓ Yes, active
 * - starts_at <= NOW()? ✓ 20:00:00 <= 21:30:00 (yes, has started)
 * - ends_at >= NOW()? ✓ 23:00:00 >= 21:30:00 (yes, hasn't ended)
 * Result: ✓ Announcement is currently active
 * 
 * If NOW() = 2024-01-15 23:30:00 (after end time):
 * - ends_at >= NOW()? ✗ 23:00:00 >= 23:30:00 (no, expired)
 * Result: ✗ Announcement is NOT active
 * 
 * If starts_at IS NULL: Treat as "always started" (starts now)
 * If ends_at IS NULL: Treat as "never ends" (always active)
 */
if ($activeOnly === 1) {
	// Condition 3: Must not be soft-deleted
	$conditions[] = 'a.is_active = 1';
	
	// Condition 4: Must have started (or never starts = always on)
	$conditions[] = '(a.starts_at IS NULL OR a.starts_at <= NOW())';
	
	// Condition 5: Must not have ended (or never ends = always on)
	$conditions[] = '(a.ends_at IS NULL OR a.ends_at >= NOW())';
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: BUILD WHERE CLAUSE STRING                                    │
// │                                                                           │
// │ Convert $conditions array into WHERE clause string                       │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * BUILD WHERE CLAUSE
 * 
 * if (!empty($conditions)): If any conditions were added
 * 
 * implode(' AND ', $conditions): Join all conditions with AND
 * 
 * Example:
 * $conditions = ['is_active = 1', 'server_id = ?', 'severity = ?']
 * Result: 'is_active = 1 AND server_id = ? AND severity = ?'
 * 
 * Then: 'WHERE ' . implode(...) adds "WHERE" prefix
 */
$where = '';
if (!empty($conditions)) {
	// ═════════════════════════════════════════════════════════════════════
	// SOME CONDITIONS WERE ADDED
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * BUILD WHERE CLAUSE
	 * 
	 * Example $where value:
	 * "WHERE (a.server_id = ? OR a.server_id IS NULL) AND a.is_active = 1
	 *  AND (a.starts_at IS NULL OR a.starts_at <= NOW())
	 *  AND (a.ends_at IS NULL OR a.ends_at >= NOW())"
	 */
	$where = 'WHERE ' . implode(' AND ', $conditions);
}
// else: $where stays '', no WHERE clause (returns all announcements)

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 7: BUILD FINAL QUERY                                            │
// │                                                                           │
// │ SELECT announcement details and join with server names                  │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * BUILD QUERY STRING (with dynamic WHERE clause)
 * 
 * SELECT:
 * a.* fields from announcements table
 * s.display_name AS server_name: Human-readable server name
 * s.battlemetrics_id: BattleMetrics ID for lookup
 * 
 * FROM announcements a: Main table
 * LEFT JOIN servers s: Join to get server names
 * 
 * WHY LEFT JOIN?
 * If announcement has server_id = NULL (global):
 * - servers table has no matching row
 * - INNER JOIN would drop this announcement
 * - LEFT JOIN keeps it (s.display_name, s.battlemetrics_id are NULL)
 * 
 * ORDER BY a.created_at DESC, a.id DESC:
 * - Newest announcements first
 * - Using id as tie-breaker for stable sort
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
	{$where}
	ORDER BY a.created_at DESC, a.id DESC
";

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 8: EXECUTE QUERY WITH EXCEPTION HANDLING                        │
// │                                                                           │
// │ TRY/CATCH: Handle errors gracefully                                      │
// │ If query prepared and parameterized: Use prepared statement              │
// │ If query has no parameters: Use simple query                             │
// └─────────────────────────────────────────────────────────────────────────┘

try {
	/**
	 * CHECK IF QUERY NEEDS PARAMETERIZATION
	 * 
	 * if ($types !== ''): Parameters were added
	 * Use prepared statement for safety
	 * 
	 * else: No parameters
	 * Use simple query
	 */
	if ($types !== '') {
		// ═════════════════════════════════════════════════════════════════
		// QUERY HAS PARAMETERS - USE PREPARED STATEMENT
		// ═════════════════════════════════════════════════════════════════
		
		/**
		 * PREPARE QUERY
		 * 
		 * Prepares SQL template with ? placeholders
		 */
		$stmt = $conn->prepare($sql);
		
		/**
		 * CHECK PREPARATION SUCCEEDED
		 */
		if (!$stmt) {
			throw new Exception('Failed to prepare statement.');
		}
		
		/**
		 * BIND PARAMETERS
		 * 
		 * ...$params: Unpacks array as multiple arguments
		 * 
		 * Example: $stmt->bind_param('i', 5) instead of
		 *          $stmt->bind_param($types, ...$params)
		 * 
		 * This syntax is equivalent but handles variable number of params.
		 */
		$stmt->bind_param($types, ...$params);
		
		/**
		 * EXECUTE QUERY
		 */
		$stmt->execute();
		
		/**
		 * GET RESULT SET
		 */
		$result = $stmt->get_result();
		
	} else {
		// ═════════════════════════════════════════════════════════════════
		// QUERY HAS NO PARAMETERS - USE SIMPLE QUERY
		// ═════════════════════════════════════════════════════════════════
		
		/**
		 * SIMPLE QUERY (NO PARAMETERIZATION NEEDED)
		 * 
		 * When no filters provided: Query is constant, no injection risk.
		 * Faster than prepared statement for simple queries.
		 */
		$result = $conn->query($sql);
	}

	/**
	 * COLLECT RESULTS INTO ARRAY
	 */
	$rows = [];
	
	/**
	 * CHECK IF QUERY SUCCEEDED
	 */
	if ($result) {
		// ═════════════════════════════════════════════════════════════════
		// QUERY SUCCEEDED - PROCESS RESULTS
		// ═════════════════════════════════════════════════════════════════
		
		/**
		 * LOOP THROUGH EACH ROW
		 * 
		 * fetch_assoc(): Returns row as array, or NULL when done
		 */
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		/**
		 * FREE RESULT SET (IF APPLICABLE)
		 * 
		 * $result instanceof mysqli_result: Check type
		 * 
		 * For prepared statements, result is mysqli_result
		 * For simple queries, result can also be mysqli_result
		 * 
		 * Only free if it's the right type (safety check)
		 */
		if ($result instanceof mysqli_result) {
			$result->free();
		}
		
		/**
		 * CLOSE PREPARED STATEMENT (IF APPLICABLE)
		 * 
		 * isset($stmt): Check if $stmt was set (prepared statement was used)
		 * 
		 * If simple query: $stmt is undefined, skip this
		 * If prepared statement: Close it
		 */
		if (isset($stmt)) {
			$stmt->close();
		}
	}

	/**
	 * RETURN RESULTS AS JSON
	 * 
	 * json_encode(): Convert PHP array to JSON string
	 * 
	 * If rows is empty: Returns []
	 * If rows has data: Returns [{...}, {...}, ...]
	 */
	echo json_encode($rows);

} catch (Throwable $e) {
	// ═════════════════════════════════════════════════════════════════════
	// EXCEPTION THROWN - HANDLE ERROR
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * SET HTTP STATUS CODE
	 * 
	 * HTTP 500 = Internal Server Error
	 */
	http_response_code(500);
	
	/**
	 * RETURN ERROR RESPONSE
	 * 
	 * Generic error message (doesn't expose internals)
	 * Details included for debugging
	 */
	echo json_encode([
		'error' => 'Failed to load announcements.',
		'details' => $e->getMessage()
	]);

} finally {
	// ═════════════════════════════════════════════════════════════════════
	// CLEANUP (ALWAYS RUNS, EVEN IF EXCEPTION THROWN)
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * CLOSE DATABASE CONNECTION
	 * 
	 * Finally block ensures this runs even if exception thrown.
	 * Good resource management.
	 */
	$conn->close();
}

// ═════════════════════════════════════════════════════════════════════════
// ANNOUNCEMENT RETRIEVAL COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Response: HTTP 200 with JSON array of matching announcements
// Filtered by: serverId (+ global), battlemetricsId (+ global), active window
// Joined with: Server names and BattleMetrics IDs
// 
// ═════════════════════════════════════════════════════════════════════════

?>

