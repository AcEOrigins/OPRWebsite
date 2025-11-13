<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * dbconnect.php - Database Connection Handler
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Centralized database connection file used by ALL API endpoints.
 * Provides a single mysqli connection object ($conn).
 * Handles connection errors gracefully with JSON response.
 * 
 * SECURITY NOTES
 * ──────────────
 * ⚠️  NEVER commit credentials to git
 * ⚠️  On production: use environment variables instead
 * ⚠️  Keep this file permissions set to 644
 * ⚠️  This file should NOT be accessible from web (Apache config protects)
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: DATABASE CREDENTIALS
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * DATABASE CONFIGURATION
 * 
 * Update these for your hosting provider.
 * For production, use environment variables:
 * $DB_HOST = getenv('DB_HOST') ?: 'localhost';
 * $DB_NAME = getenv('DB_NAME') ?: '';
 * etc.
 */
$DB_HOST = 'localhost';
$DB_NAME = 'u775021278_battleMetrics';
$DB_USER = 'u775021278_OPRBM';
$DB_PASS = 'Pq8137!2';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: CREATE CONNECTION
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * CREATE NEW MYSQLI CONNECTION
 * 
 * The @ operator suppresses PHP warnings (we handle errors below).
 * Result: $conn object or failed connection (detected below).
 */
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: ERROR HANDLING
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * CHECK FOR CONNECTION ERRORS
 * 
 * If $conn->connect_error is set, the connection failed.
 * This could be caused by:
 * - Wrong hostname
 * - Invalid credentials (username/password)
 * - Database doesn't exist
 * - MySQL server is down/unreachable
 * - User doesn't have permissions
 */
if ($conn->connect_error) {
    // Set HTTP response code to 500 (Internal Server Error)
    http_response_code(500);
    
    // Set response format to JSON
    header('Content-Type: application/json');
    
    // Return error information
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed.',
        'details' => $conn->connect_error  // Raw MySQL error message (for debugging)
    ]);
    
    // STOP EXECUTION IMMEDIATELY
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CHARACTER ENCODING
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * SET CHARACTER ENCODING
 * 
 * Character set: utf8mb4 (UTF-8 with 4-byte encoding)
 * 
 * Why utf8mb4?
 * ✓ Supports all Unicode characters
 * ✓ Allows emojis in announcements 🎮
 * ✓ Handles international characters (áéíóú, 中文, العربية, etc.)
 * ✓ No character encoding mismatches
 * ✓ Industry standard
 * 
 * CRITICAL: This MUST match the CHARACTER SET in CREATE TABLE statements
 */
$conn->set_charset("utf8mb4");

// ═════════════════════════════════════════════════════════════════════════════════
// ✓ CONNECTION ESTABLISHED AND READY
// ═════════════════════════════════════════════════════════════════════════════════
// 
// At this point:
// - $conn is a valid mysqli object
// - Connected to database: $DB_NAME
// - Character set: utf8mb4
// - Ready to use in other files
// 
// ═════════════════════════════════════════════════════════════════════════════════

?>

