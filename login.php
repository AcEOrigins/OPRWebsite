<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * login.php - User Authentication Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Authenticates users by verifying credentials and creating a secure session.
 * Uses centralized AuthController for all authentication logic.
 * 
 * REQUEST
 * ───────
 * POST /login.php
 * Content-Type: application/json
 * Body: { "name": "username", "password": "password123" }
 * 
 * RESPONSE (SUCCESS)
 * ──────────────────
 * HTTP 200 OK
 * { "success": true, "data": { "redirectUrl": "portal.html" } }
 * 
 * RESPONSE (FAILURE)
 * ──────────────────
 * HTTP 401 Unauthorized
 * { "success": false, "message": "Invalid credentials. Please try again." }
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: LOAD DEPENDENCIES
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/AuthController.php';
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

$name = isset($input['name']) ? trim((string)$input['name']) : '';
$password = isset($input['password']) ? (string)$input['password'] : '';

if ($name === '' || $password === '') {
    ApiResponse::validationError('Username and password are required.');
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

$result = AuthController::login($name, $password, $conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    ApiResponse::success(['redirectUrl' => 'portal.html']);
} else {
    ApiResponse::error($result['message'], 401);
}

?>

