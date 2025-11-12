<?php
/**
 * =============================================================================
 * dbconnect.php - Database Connection Handler
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * Centralized database connection file used by ALL API endpoints.
 * Provides a single mysqli connection object ($conn).
 * Handles connection errors gracefully with JSON response.
 * 
 * INCLUDE IN OTHER FILES:
 * -------
 * require_once __DIR__ . '/dbconnect.php';
 * 
 * USED BY (ALL THESE FILES):
 * -------
 * ✓ login.php, auth_check.php
 * ✓ getServers.php, saveServer.php, deleteServer.php
 * ✓ getAnnouncements.php, saveAnnouncement.php, deleteAnnouncement.php
 * ✓ addUser.php, listUsers.php, deactivateUser.php, reactivateUser.php, resetUserPassword.php
 * 
 * SECURITY NOTES:
 * ----------------
 * ⚠️  NEVER commit credentials to git
 * ⚠️  On production: use environment variables instead
 * ⚠️  Keep this file permissions set to 644
 * ⚠️  This file should NOT be accessible from web (Apache config protects)
 * 
 * =============================================================================
 */

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 1: DATABASE CREDENTIALS                                         │
// │                                                                           │
// │ UPDATE THESE FOR YOUR HOSTING PROVIDER                                  │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * DATABASE HOST
 * 
 * The server where MySQL is running.
 * 
 * Common values:
 * - 'localhost'           ← Local/shared hosting (99% of cases)
 * - '127.0.0.1'           ← Loopback (same as localhost)
 * - 'mysql.example.com'   ← Remote database server
 * 
 * Hostinger: Always 'localhost'
 * Most shared hosts: Always 'localhost'
 * Cloud database: Varies (ask provider)
 */
$DB_HOST = 'localhost';

/**
 * DATABASE NAME
 * 
 * The specific database where all tables are stored.
 * Each website gets its own database.
 * 
 * Format (Hostinger/most hosts): u[numbers]_[name]
 * Example: u775021278_battleMetrics
 * 
 * ⚠️  IMPORTANT: This database must already exist (created in cPanel)
 * If the database doesn't exist, connection will fail
 */
$DB_NAME = 'u775021278_battleMetrics';

/**
 * DATABASE USER
 * 
 * MySQL user account credentials.
 * Usually created in cPanel > MySQL Databases.
 * 
 * Format (Hostinger): u[numbers]_[username]
 * Example: u775021278_OPRBM
 * 
 * REQUIRED PERMISSIONS:
 * - CREATE TABLE (for first run - creates tables if missing)
 * - SELECT       (read data)
 * - INSERT       (add data)
 * - UPDATE       (modify data)
 * - DELETE       (remove data)
 * 
 * The hosting provider usually grants all these by default.
 */
$DB_USER = 'u775021278_OPRBM';

/**
 * DATABASE PASSWORD
 * 
 * Password for the $DB_USER account.
 * Created when you add the user in cPanel.
 * 
 * ⚠️  SECURITY CRITICAL:
 * - NEVER share this password
 * - NEVER commit to public repository
 * - On production: load from environment variable
 * - On local: keep in .gitignore
 * 
 * Better approach (production):
 * $DB_PASS = getenv('DB_PASS') ?: 'fallback_password';
 */
$DB_PASS = 'Pq8137!2';

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: CREATE CONNECTION                                            │
// │                                                                           │
// │ Attempt to connect to MySQL server                                      │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CREATE NEW MYSQLI CONNECTION
 * 
 * Parameters (in order):
 * 1. $DB_HOST  - Server hostname/IP
 * 2. $DB_USER  - MySQL username
 * 3. $DB_PASS  - MySQL password
 * 4. $DB_NAME  - Database name
 * 
 * The @ operator: Suppresses PHP warnings (we handle errors below)
 * Result: $conn object or failed connection (detected below)
 * 
 * This $conn object is now available to all files that include this
 */
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 3: ERROR HANDLING                                               │
// │                                                                           │
// │ If connection failed, report error and stop                             │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * CHECK FOR CONNECTION ERRORS
 * 
 * If $conn->connect_error is set, the connection failed.
 * This could be caused by:
 * 
 * ❌ Wrong hostname
 * ❌ Invalid credentials (username/password)
 * ❌ Database doesn't exist
 * ❌ MySQL server is down/unreachable
 * ❌ User doesn't have permissions
 * ❌ Wrong port
 */
if ($conn->connect_error) {
	// ═══════════════════════════════════════════════════════════════════════
	// ✗ CONNECTION FAILED - RETURN ERROR RESPONSE
	// ═══════════════════════════════════════════════════════════════════════
	
	// Set HTTP response code to 500 (Internal Server Error)
	// Tells client: Server error, not client's fault
	http_response_code(500);
	
	// Set response format to JSON
	// Matches the format used by all other API endpoints
	header('Content-Type: application/json');
	
	// Return error information
	// Format: { success: false, error: "...", details: "..." }
	echo json_encode([
		'success' => false,
		'error' => 'Database connection failed.',
		'details' => $conn->connect_error  // Raw MySQL error message (for debugging)
	]);
	
	// STOP EXECUTION IMMEDIATELY
	// Do not load any other code
	// The endpoint stops here
	exit;
}

// ✓ Connection successful - continue

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 4: CHARACTER ENCODING                                           │
// │                                                                           │
// │ Configure UTF-8 for international character support                     │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * SET CHARACTER ENCODING
 * 
 * Character set: utf8mb4 (UTF-8 with 4-byte encoding)
 * 
 * Why utf8mb4?
 * ───────────
 * ✓ Supports all Unicode characters
 * ✓ Allows emojis in announcements 🎮
 * ✓ Handles international characters (áéíóú, 中文, العربية, etc.)
 * ✓ No character encoding mismatches
 * ✓ Industry standard
 * 
 * CRITICAL: This MUST match the CHARACTER SET in CREATE TABLE statements
 * (See: getServers.php, getAnnouncements.php, etc.)
 * 
 * If charset doesn't match:
 * - Characters display as ??? or garbage
 * - Emojis corrupt
 * - International text breaks
 */
$conn->set_charset("utf8mb4");

// ═════════════════════════════════════════════════════════════════════════
// ✓ CONNECTION ESTABLISHED AND READY
// ═════════════════════════════════════════════════════════════════════════
// 
// At this point:
// - $conn is a valid mysqli object
// - Connected to database: $DB_NAME
// - Character set: utf8mb4
// - Ready to use in other files
// 
// USAGE IN OTHER FILES:
// ────────────────────
// 
// 1. Include this file at the top:
//    require_once __DIR__ . '/dbconnect.php';
// 
// 2. Now you can use $conn for queries:
//    
//    // Simple query:
//    $result = $conn->query("SELECT * FROM servers");
//    $row = $result->fetch_assoc();
//    
//    // Prepared statement (SAFER - prevents SQL injection):
//    $stmt = $conn->prepare("SELECT * FROM servers WHERE id = ?");
//    $stmt->bind_param("i", $serverId);
//    $stmt->execute();
//    $result = $stmt->get_result();
// 
// 3. Always close the connection when done:
//    $conn->close();
// 
// ═════════════════════════════════════════════════════════════════════════