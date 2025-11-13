<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/ARCHITECTURE.php - PHP Backend Architecture Documentation
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * This file serves as consolidated documentation of the PHP backend architecture.
 * It outlines the controller-based approach, directory structure, and patterns.
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * ARCHITECTURE OVERVIEW
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * The PHP backend follows a CONTROLLER-BASED architecture:
 * 
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ Endpoints (Thin Routers)                                                   │
 * │ - login.php, auth_check.php, getServers.php, etc.                         │
 * │ - Handle HTTP requests, parse input, call controllers, return responses   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *                            │
 *                            ▼
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ Controllers (Business Logic)                                               │
 * │ - AuthController, ServerController, AnnouncementController, UserController│
 * │ - Contain all business logic, database operations, validation             │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *                            │
 *                            ▼
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ Common Libraries (Utilities)                                               │
 * │ - ApiResponse: Unified JSON response formatting                            │
 * │ - api_common.php: JSON helpers, method validation, input parsing          │
 * │ - db.php: Database connection helpers                                      │
 * │ - auth.php: Authentication helper stubs                                    │
 * │ - battlemetrics_client.php: BattleMetrics API client wrapper              │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *                            │
 *                            ▼
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ Database (dbconnect.php)                                                  │
 * │ - Centralized mysqli connection                                           │
 * │ - Error handling, charset configuration                                    │
 * └─────────────────────────────────────────────────────────────────────────────┘
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * DIRECTORY STRUCTURE
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * OPRWebsite/
 * ├── lib/
 * │   ├── ARCHITECTURE.php          ← This file (architecture docs)
 * │   ├── ApiResponse.php            ← Unified JSON response formatting
 * │   ├── AuthController.php         ← Authentication logic
 * │   ├── ServerController.php       ← Server CRUD operations
 * │   ├── AnnouncementController.php ← Announcement CRUD operations
 * │   ├── UserController.php         ← User management operations
 * │   ├── api_common.php             ← Common API helpers
 * │   ├── auth.php                   ← Auth helper stubs
 * │   ├── db.php                     ← Database helpers
 * │   └── battlemetrics_client.php   ← BattleMetrics API client
 * ├── dbconnect.php                  ← Database connection handler
 * ├── login.php                      ← Authentication endpoint
 * ├── auth_check.php                 ← Session verification endpoint
 * ├── getServers.php                 ← List servers endpoint
 * ├── saveServer.php                 ← Create/update server endpoint
 * ├── deleteServer.php               ← Soft-delete server endpoint
 * ├── getAnnouncements.php           ← List announcements endpoint
 * ├── saveAnnouncement.php           ← Create announcement endpoint
 * ├── deleteAnnouncement.php         ← Soft-delete announcement endpoint
 * ├── listUsers.php                  ← List users endpoint
 * ├── addUser.php                    ← Create user endpoint
 * ├── deactivateUser.php             ← Deactivate user endpoint
 * ├── reactivateUser.php             ← Reactivate user endpoint
 * ├── resetUserPassword.php          ← Reset password endpoint
 * ├── battlemetrics.php              ← BattleMetrics API proxy
 * └── cluster.php                    ← Server snapshot utility
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * CONTROLLER PATTERNS
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * All controllers follow these patterns:
 * 
 * 1. STATIC METHODS
 *    - All controller methods are static (no instantiation needed)
 *    - Example: AuthController::login($name, $password, $conn)
 * 
 * 2. RETURN ARRAYS
 *    - Controllers return arrays with 'success' key
 *    - Example: ['success' => true, 'user' => $userData]
 *    - Example: ['success' => false, 'message' => 'Error message']
 * 
 * 3. IDEMPOTENT TABLE CREATION
 *    - Controllers create tables if missing (CREATE TABLE IF NOT EXISTS)
 *    - Ensures database schema exists without manual setup
 * 
 * 4. PARAMETERIZED QUERIES
 *    - All database queries use prepared statements
 *    - Prevents SQL injection attacks
 *    - Example: $stmt->bind_param('s', $name)
 * 
 * 5. SOFT-DELETE PATTERN
 *    - Deletes set is_active = 0 (never actually delete)
 *    - Preserves data for history/audit
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * ENDPOINT PATTERNS
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * All endpoints follow this structure:
 * 
 * 1. LOAD DEPENDENCIES
 *    require_once __DIR__ . '/lib/ControllerName.php';
 *    require_once __DIR__ . '/lib/ApiResponse.php';
 *    require_once __DIR__ . '/dbconnect.php';
 * 
 * 2. VALIDATE HTTP METHOD
 *    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 *        ApiResponse::methodNotAllowed();
 *    }
 * 
 * 3. PARSE INPUT
 *    $input = json_decode(file_get_contents('php://input'), true) ?? [];
 *    $param = $input['param'] ?? '';
 * 
 * 4. CALL CONTROLLER
 *    $result = ControllerName::method($param, $conn);
 * 
 * 5. RETURN RESPONSE
 *    if ($result['success']) {
 *        ApiResponse::success(['data' => $result['data']]);
 *    } else {
 *        ApiResponse::error($result['message'], $result['code'] ?? 400);
 *    }
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * SECURITY FEATURES
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * ✓ Password Hashing
 *   - Uses PASSWORD_DEFAULT (bcrypt)
 *   - password_hash() for creation
 *   - password_verify() for validation
 * 
 * ✓ SQL Injection Prevention
 *   - All queries use prepared statements
 *   - Parameterized queries with bind_param()
 * 
 * ✓ Session Security
 *   - HttpOnly cookies (prevents XSS)
 *   - SameSite=Lax (prevents CSRF)
 *   - Session regeneration (prevents fixation)
 *   - Strict mode enabled
 * 
 * ✓ Input Validation
 *   - All input sanitized/validated
 *   - Type casting (int, string, trim)
 *   - Whitelist validation (severity levels)
 * 
 * ✓ Error Messages
 *   - Generic messages (prevents info leakage)
 *   - No stack traces in production
 *   - Proper HTTP status codes
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * DATABASE SCHEMA
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * TABLES:
 * 
 * users
 *   - id (PK, auto-increment)
 *   - name (UNIQUE, username)
 *   - password_hash (hashed password)
 *   - role (owner, admin, staff)
 *   - is_active (soft-delete flag)
 *   - created_at, updated_at
 * 
 * servers
 *   - id (PK, auto-increment)
 *   - battlemetrics_id (UNIQUE, BattleMetrics server ID)
 *   - display_name (user-friendly name)
 *   - game_title (game name)
 *   - region (geographic region)
 *   - is_active (soft-delete flag)
 *   - sort_order (custom sort)
 *   - created_at, updated_at
 *   - INDEX idx_is_active, idx_sort_order
 * 
 * announcements
 *   - id (PK, auto-increment)
 *   - server_id (FK → servers.id, NULL=global)
 *   - message (announcement text)
 *   - severity (info, success, warning, error)
 *   - starts_at, ends_at (time window, NULL=always active)
 *   - is_active (soft-delete flag)
 *   - created_at, updated_at
 *   - FOREIGN KEY fk_announcements_server_id
 *   - INDEX idx_server_id, idx_is_active, idx_starts_at, idx_ends_at
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * API RESPONSE FORMAT
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * SUCCESS RESPONSE:
 * {
 *   "success": true,
 *   "data": { ... }
 * }
 * 
 * ERROR RESPONSE:
 * {
 *   "success": false,
 *   "message": "Error message here"
 * }
 * 
 * HTTP STATUS CODES:
 * - 200: Success
 * - 400: Bad Request (validation error)
 * - 401: Unauthorized (not authenticated)
 * - 403: Forbidden (no permission)
 * - 404: Not Found
 * - 405: Method Not Allowed
 * - 409: Conflict (duplicate resource)
 * - 422: Unprocessable Entity (validation error)
 * - 500: Internal Server Error
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 * LABELING STANDARDS
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * All PHP files follow this labeling structure:
 * 
 * 1. FILE HEADER
 *    - Purpose description
 *    - Request/Response examples
 *    - Security notes
 * 
 * 2. SECTION COMMENTS
 *    - Clear section dividers (═══════)
 *    - Descriptive section names
 *    - Step-by-step comments
 * 
 * 3. METHOD DOCUMENTATION
 *    - Purpose of method
 *    - Parameters with types
 *    - Return value structure
 *    - Usage examples
 * 
 * 4. INLINE COMMENTS
 *    - Explain WHY, not WHAT
 *    - Security considerations
 *    - Edge cases handled
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

// This file is documentation only - no executable code
// It can be included in other files for reference, but serves primarily as documentation

?>

