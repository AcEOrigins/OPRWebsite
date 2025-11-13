<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * resetUserPassword.php - Password Reset Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Resets a user's password to a new value (admin-initiated).
 * Called by: portal.html (admin portal, Manage Access tab)
 * 
 * REQUEST
 * ───────
 * POST /resetUserPassword.php
 * Content-Type: application/json
 * Body: {
 *   "id": 2,
 *   "password": "NewPassword123!"
 * }
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * { "success": true, "data": {} }
 * 
 * RESPONSE (ERROR)
 * ───────────────
 * HTTP 422 Unprocessable Entity
 * { "success": false, "message": "User ID and new password are required." }
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
// SECTION 3: PARSE AND VALIDATE INPUT
// ─────────────────────────────────────────────────────────────────────────────────

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$userId = isset($input['id']) ? (int)$input['id'] : 0;
$password = isset($input['password']) ? (string)$input['password'] : '';

if ($userId <= 0 || $password === '') {
    ApiResponse::validationError('User ID and new password are required.');
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$result = UserController::resetPassword($userId, $password, $conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    ApiResponse::success();
} else {
    ApiResponse::error($result['message'], 500);
}

?>

