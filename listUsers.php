<?php
/**
 * =============================================================================
 * listUsers.php - User List Retrieval Endpoint
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Returns a list of ALL users (both active and inactive) from the database.
 * Called by: portal.html (admin portal, Manage Access tab to list users)
 * 
 * REQUEST:
 * --------
 * GET /listUsers.php
 * (No parameters required)
 * 
 * RESPONSE (SUCCESS):
 * -------------------
 * HTTP 200 OK
 * [
 *   {
 *     "id": 1,
 *     "name": "admin",
 *     "role": "owner",
 *     "is_active": 1,
 *     "created_at": "2024-01-01 09:00:00",
 *     "updated_at": "2024-01-01 09:00:00"
 *   },
 *   {
 *     "id": 2,
 *     "name": "john_doe",
 *     "role": "admin",
 *     "is_active": 0,
 *     "created_at": "2024-01-15 10:30:45",
 *     "updated_at": "2024-01-15 11:00:00"
 *   }
 * ]
 * 
 * DATA FLOW:
 * ----------
 * 1. Create table if doesn't exist (idempotent)
 * 2. Query database for ALL users (active and inactive)
 * 3. Sort by name alphabetically
 * 4. Return as JSON array
 * 5. Frontend displays with status indicator for is_active
 * 
 * INCLUDES INACTIVE USERS:
 * ───────────────────────
 * Unlike getServers/getAnnouncements (which filter by is_active = 1),
 * this endpoint returns ALL users including soft-deleted ones.
 * 
 * Reason: Admin needs to see inactive users to reactivate them.
 * Frontend highlights inactive users (greyed out, "Inactive" badge).
 * 
 * SECURITY:
 * ---------
 * ✓ Table creation is safe (IF NOT EXISTS)
 * ✓ Simple query (no parameters, no injection risk)
 * ✓ Result set freed after use
 * ✓ No authentication check (portal.html already checks auth)
 * ✓ Does NOT return password_hash (security)
 * 
 * SORTING:
 * --------
 * ORDER BY name ASC: Alphabetical order, easier for admin to find users
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
// │ SECTION 2: DATABASE CONNECTION & TABLE CREATION                         │
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
 * (Same schema as in addUser.php)
 * Idempotent: Creates if missing, no-op if exists
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
// │ SECTION 3: QUERY FOR ALL USERS                                          │
// │                                                                           │
// │ INCLUDES BOTH ACTIVE AND INACTIVE:                                      │
// │ ─────────────────────────────────                                        │
// │ Unlike getServers/getAnnouncements which filter WHERE is_active = 1,    │
// │ this endpoint returns ALL users for admin management.                    │
// │ Admin needs to see inactive users to restore/manage them.               │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * QUERY FOR ALL USERS
 * 
 * SQL: SELECT id, name, role, is_active, created_at, updated_at
 *      FROM users
 *      ORDER BY name ASC
 * 
 * Columns:
 * - id: User ID (primary key)
 * - name: Username
 * - role: 'owner', 'admin', or 'staff'
 * - is_active: 1=active, 0=inactive (soft-deleted)
 * - created_at: When user was created
 * - updated_at: When user was last updated
 * 
 * IMPORTANT: Does NOT select password_hash
 * Never return password hash to client!
 * 
 * WHERE clause: NONE
 * Returns ALL users, including is_active = 0 (inactive)
 * 
 * Reason: Admin needs to see inactive users to:
 * - Understand who's been disabled
 * - Reactivate users if needed (deactivateUser → reactivateUser)
 * 
 * ORDER BY name ASC:
 * - Alphabetical order
 * - Easier for admin to find users
 * - Stable and predictable sort
 */
$result = $conn->query("SELECT id, name, role, is_active, created_at, updated_at FROM users ORDER BY name ASC");

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: COLLECT RESULTS INTO ARRAY                                   │
// │                                                                           │
// │ Loop through all rows and build JSON response array                     │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * INITIALIZE EMPTY ARRAY
 * 
 * Will be populated with user rows (one per user).
 * If query fails, remains empty array.
 * If query succeeds, contains all users (active and inactive).
 */
$rows = [];

/**
 * CHECK IF QUERY SUCCEEDED
 * 
 * $result is truthy if query succeeded.
 * $result is false if query failed (syntax error, table missing, etc.).
 */
if ($result) {
	// ═════════════════════════════════════════════════════════════════════
	// QUERY SUCCEEDED - PROCESS RESULTS
	// ═════════════════════════════════════════════════════════════════════
	
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
		 * ADD ROW TO ROWS ARRAY
		 * 
		 * $rows[] appends row to array.
		 * 
		 * Example row:
		 * {
		 *   "id": 2,
		 *   "name": "john_doe",
		 *   "role": "admin",
		 *   "is_active": 0,
		 *   "created_at": "2024-01-15 10:30:45",
		 *   "updated_at": "2024-01-15 11:00:00"
		 * }
		 */
		$rows[] = $row;
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
// If $result is false: Query failed, $rows stays empty

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 5: CLEANUP & RESPONSE                                           │
// │                                                                           │
// │ Close database connection and return user data                          │
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
 * RETURN USER LIST AS JSON
 * 
 * json_encode() converts PHP array to JSON format.
 * 
 * If rows is empty: Returns []
 * If rows has data: Returns [{...}, {...}, ...]
 * 
 * Example output:
 * [
 *   {
 *     "id": 1,
 *     "name": "admin",
 *     "role": "owner",
 *     "is_active": 1,
 *     "created_at": "2024-01-01 09:00:00",
 *     "updated_at": "2024-01-01 09:00:00"
 *   },
 *   {
 *     "id": 2,
 *     "name": "john_doe",
 *     "role": "admin",
 *     "is_active": 0,
 *     "created_at": "2024-01-15 10:30:45",
 *     "updated_at": "2024-01-15 11:00:00"
 *   }
 * ]
 * 
 * Frontend (portal.js) receives this and:
 * 1. Populates user list in "Manage Access" tab
 * 2. Uses is_active to show/grey out users
 * 3. Uses role to display access level
 * 4. Provides action buttons (deactivate/reactivate/reset password)
 */
echo json_encode($rows);

// ═════════════════════════════════════════════════════════════════════════
// USER LIST RETRIEVAL COMPLETE
// ═════════════════════════════════════════════════════════════════════════
// 
// Response: HTTP 200 with JSON array of ALL users
// Includes: Active and inactive users
// Sorted by: Name (alphabetical)
// NOT included: password_hash (security)
// 
// ═════════════════════════════════════════════════════════════════════════

?>


