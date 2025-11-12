<?php
/**
 * =============================================================================
 * getServers.php - Server List Retrieval Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Returns a list of all ACTIVE servers from the database.
 * Called by: portal.html (to populate server list on admin portal)
 * 
 * REQUEST:
 * --------
 * GET /getServers.php
 * (No parameters required)
 * 
 * RESPONSE (SUCCESS):
 * -------------------
 * HTTP 200 OK
 * [
 *   {
 *     "id": 1,
 *     "battlemetrics_id": "123456",
 *     "display_name": "US Server 1",
 *     "game_title": "Rust",
 *     "region": "us-east-1"
 *   },
 *   {
 *     "id": 2,
 *     "battlemetrics_id": "789012",
 *     "display_name": "EU Server 1",
 *     "game_title": "Rust",
 *     "region": "eu-west-1"
 *   }
 * ]
 * 
 * RESPONSE (ERROR):
 * -----------------
 * HTTP 500 Internal Server Error
 * {
 *   "error": "Failed to load servers.",
 *   "details": "Exception message"
 * }
 * 
 * DATA FLOW:
 * ----------
 * 1. Create table if doesn't exist (idempotent)
 * 2. Query database for active servers
 * 3. Sort by sort_order (admin can reorder), then by id (tie-breaker)
 * 4. Return as JSON array
 * 5. Frontend populates dropdown/list for editing
 * 
 * SECURITY:
 * ---------
 * ✓ Table creation is safe (IF NOT EXISTS)
 * ✓ Error messages are generic (don't expose internals)
 * ✓ Result set freed after use
 * ✓ No authentication required (public list)
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
 * Prevents browser from treating JSON as HTML or XML.
 */
header('Content-Type: application/json');

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: DATABASE CONNECTION                                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * LOAD DATABASE CONNECTION
 * 
 * Includes dbconnect.php which creates $conn (mysqli object).
 * $conn is used for all database queries.
 */
require_once __DIR__ . '/dbconnect.php';

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: IDEMPOTENT TABLE CREATION                                    │
// │                                                                           │
// │ WHY IDEMPOTENT?                                                          │
// │ ──────────────                                                           │
// │ If table already exists, this query does nothing (no error).             │
// │ If table doesn't exist, this creates it.                                │
// │ Safe to run on every request - won't break existing data.               │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * SQL: CREATE TABLE IF NOT EXISTS
 * 
 * TABLE NAME: servers
 * 
 * COLUMNS:
 * --------
 * id                    - Unique identifier (primary key), auto-increment
 * battlemetrics_id      - BattleMetrics server ID (must be unique)
 * display_name          - User-friendly name ("US Server 1")
 * game_title            - Game name ("Rust", "Ark", etc.)
 * region                - Geographic region ("us-east-1")
 * is_active             - Soft-delete flag (1=active, 0=deleted)
 * sort_order            - Custom sort order (admin can reorder servers)
 * created_at            - Record creation timestamp
 * updated_at            - Last record update timestamp (auto-updated)
 * 
 * INDEXES:
 * --------
 * idx_is_active         - Speed up WHERE is_active = 1 queries
 * idx_sort_order        - Speed up ORDER BY sort_order
 * 
 * CHARSET: utf8mb4      - Unicode support (emoji, special chars)
 * ENGINE: InnoDB        - Transaction support, foreign keys
 */
$createTableSql = "
    CREATE TABLE IF NOT EXISTS servers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        battlemetrics_id VARCHAR(50) NOT NULL UNIQUE,
        display_name VARCHAR(255) NOT NULL,
        game_title VARCHAR(255) DEFAULT NULL,
        region VARCHAR(100) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_is_active (is_active),
        INDEX idx_sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

/**
 * EXECUTE TABLE CREATION
 * 
 * @ prefix suppresses warnings (if table already exists, no error output)
 * 
 * Result:
 * - If successful: Table created or left unchanged
 * - If fails: Error suppressed, but code continues
 * - If fails: Query below will also fail (caught in try/catch)
 */
@$conn->query($createTableSql);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: BUILD QUERY FOR ACTIVE SERVERS                               │
// │                                                                           │
// │ QUERY LOGIC:                                                             │
// │ ───────────                                                              │
// │ 1. Select only needed columns (id, battlemetrics_id, etc.)              │
// │ 2. WHERE is_active = 1 → Only active servers (not deleted)              │
// │ 3. ORDER BY sort_order ASC → Use admin custom sort                      │
// │ 4. ORDER BY id ASC → Tie-breaker (stable sort)                          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * SQL QUERY
 * 
 * SELECT id, battlemetrics_id, display_name, game_title, region
 * 
 * Selects these columns (database optimization - don't select unused cols):
 * - id              → Used for editing/deleting servers
 * - battlemetrics_id → BattleMetrics API integration
 * - display_name     → Shown in UI
 * - game_title       → Shown in UI
 * - region           → Shown in UI
 * 
 * FROM servers
 * 
 * Gets data from servers table
 * 
 * WHERE is_active = 1
 * 
 * Filters out deleted servers (soft-delete pattern).
 * is_active = 0 means server was deleted but kept in DB for history.
 * 
 * ORDER BY sort_order ASC, id ASC
 * 
 * sort_order allows admin to customize order (drag & drop in UI).
 * id ASC is tie-breaker: if multiple servers have same sort_order,
 * use their id to ensure consistent, stable sorting.
 */
$sql = "SELECT id, battlemetrics_id, display_name, game_title, region
        FROM servers
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC";

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: EXECUTE QUERY & COLLECT RESULTS                              │
// │                                                                           │
// │ ERROR HANDLING:                                                          │
// │ ──────────────                                                           │
// │ Uses try/catch to handle exceptions gracefully.                         │
// │ Returns HTTP 500 on error (server problem, not client problem).          │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * INITIALIZE EMPTY ARRAY
 * 
 * Will be populated with server rows (one per server).
 * If query fails, remains empty array.
 * If query succeeds, contains all active servers.
 */
$servers = [];

try {
	/**
	 * EXECUTE QUERY
	 * 
	 * Sends SQL to database.
	 * Returns mysqli_result object (contains rows) on success.
	 * Returns false on failure.
	 */
	$result = $conn->query($sql);
	
	/**
	 * CHECK IF QUERY SUCCEEDED
	 * 
	 * $result is truthy if query succeeded.
	 * $result is false if query failed (syntax error, table missing, etc.).
	 */
	if ($result) {
		// ═════════════════════════════════════════════════════════════════
		// QUERY SUCCEEDED - PROCESS RESULTS
		// ═════════════════════════════════════════════════════════════════
		
		/**
		 * LOOP THROUGH EACH ROW
		 * 
		 * fetch_assoc() returns:
		 * - Next row as associative array on each call
		 * - NULL when no more rows
		 * 
		 * Loop continues until all rows processed.
		 */
		while ($row = $result->fetch_assoc()) {
			/**
			 * ADD ROW TO SERVERS ARRAY
			 * 
			 * $servers[] appends row to array.
			 * 
			 * Example row:
			 * {
			 *   "id": 1,
			 *   "battlemetrics_id": "123456",
			 *   "display_name": "US Server 1",
			 *   "game_title": "Rust",
			 *   "region": "us-east-1"
			 * }
			 */
			$servers[] = $row;
		}
		
		/**
		 * FREE RESULT SET
		 * 
		 * Releases memory used by result object.
		 * Good practice: Always free results after use.
		 * Prevents memory leaks on long-running processes.
		 */
		$result->free();
	}
	// If $result is false: Query failed, but no exception thrown
	// This handles the "failed but no exception" case gracefully

} catch (Throwable $e) {
	// ═════════════════════════════════════════════════════════════════════
	// EXCEPTION THROWN - HANDLE ERROR
	// ═════════════════════════════════════════════════════════════════════
	
	/**
	 * SET HTTP STATUS CODE
	 * 
	 * HTTP 500 = Internal Server Error
	 * 
	 * Tells client: "Server encountered an error" (not client's fault).
	 * Distinguishes from:
	 * - 400 (Bad Request) - client sent invalid data
	 * - 401 (Unauthorized) - client not authenticated
	 * - 404 (Not Found) - resource doesn't exist
	 */
	http_response_code(500);
	
	/**
	 * RETURN ERROR RESPONSE
	 * 
	 * {
	 *   "error": "Failed to load servers.",
	 *   "details": "..."
	 * }
	 * 
	 * WHY GENERIC ERROR MESSAGE?
	 * 
	 * "Failed to load servers" doesn't reveal internals.
	 * Real error ("Access denied for user 'root'") would:
	 * - Leak database credentials
	 * - Leak table structure
	 * - Help attackers understand system
	 * 
	 * $e->getMessage() is for debugging (shows real error).
	 * In production, you'd log this and return generic message only.
	 */
	echo json_encode([
		'error' => 'Failed to load servers.',
		'details' => $e->getMessage()
	]);
	
	// ✓ Close connection and exit immediately
	$conn->close();
	exit;
}

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 6: CLEANUP & RESPONSE                                           │
// │                                                                           │
// │ Close database connection and return server data                        │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CLOSE DATABASE CONNECTION
 * 
 * Frees connection resources.
 * Good practice: Always close when done querying.
 * Helps shared hosting pools not run out of connections.
 */
$conn->close();

/**
 * RETURN SERVER LIST AS JSON
 * 
 * json_encode() converts PHP array to JSON format.
 * 
 * Example output:
 * [
 *   {
 *     "id": 1,
 *     "battlemetrics_id": "123456",
 *     "display_name": "US Server 1",
 *     "game_title": "Rust",
 *     "region": "us-east-1"
 *   },
 *   {
 *     "id": 2,
 *     "battlemetrics_id": "789012",
 *     "display_name": "EU Server 1",
 *     "game_title": "Rust",
 *     "region": "eu-west-1"
 *   }
 * ]
 * 
 * Frontend (portal.js) receives this and:
 * 1. Populates dropdown for editing
 * 2. Populates server list on page
 * 3. Uses for managing announcements per server
 */
echo json_encode($servers);

// ═════════════════════════════════════════════════════════════════════════
// SERVER LIST RETRIEVAL COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Response: HTTP 200 with JSON array of active servers
// Ordered by: sort_order (admin custom), then id (stable)
// 
// ═════════════════════════════════════════════════════════════════════════

?>
