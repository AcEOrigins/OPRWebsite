<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * addUser.php - User Creation Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Creates a new user account in the database.
 * Called by: portal.html (admin portal to manage access, Manage Access tab)
 * 
 * REQUEST
 * ───────
 * POST /addUser.php
 * Content-Type: application/json
 * Body: {
 *   "name": "john_doe",
 *   "password": "SecurePassword123!",
 *   "role": "admin"  (optional, default="admin")
 * }
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "data": {
 *     "user": {
 *       "id": 2,
 *       "name": "john_doe",
 *       "role": "admin",
 *       "is_active": 1,
 *       "created_at": "2024-01-15 10:30:45"
 *     }
 *   }
 * }
 * 
 * RESPONSE (ERROR)
 * ───────────────
 * HTTP 422 Unprocessable Entity
 * { "success": false, "message": "Name and password are required." }
 * 
 * HTTP 409 Conflict
 * { "success": false, "message": "User already exists." }
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: LOAD DEPENDENCIES
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/UserController.php';
require_once __DIR__ . '/lib/ApiResponse.php';
require_once __DIR__ . '/dbconnect.php';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: VALIDATE HTTP METHOD
// ─────────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed();
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: PARSE INPUT
// ─────────────────────────────────────────────────────────────────────────────────

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$name = isset($input['name']) ? trim((string)$input['name']) : '';
$password = isset($input['password']) ? (string)$input['password'] : '';
$role = isset($input['role']) ? trim((string)$input['role']) : 'admin';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$result = UserController::addUser($name, $password, $role, $conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    ApiResponse::success(['user' => $result['user']]);
} else {
    $code = $result['code'] ?? 400;
    ApiResponse::error($result['message'], $code);
}

?>

