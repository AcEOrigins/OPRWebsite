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
// SECTION 1: ERROR HANDLING
// ─────────────────────────────────────────────────────────────────────────────────

// Set error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors to user, but log them

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: LOAD DEPENDENCIES
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/AuthController.php';
require_once __DIR__ . '/lib/ApiResponse.php';
require_once __DIR__ . '/dbconnect.php';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: VALIDATE HTTP METHOD
// ─────────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed();
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: PARSE AND VALIDATE INPUT
// ─────────────────────────────────────────────────────────────────────────────────

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    ApiResponse::error('Invalid JSON in request body.', 400);
}

if (!is_array($input)) {
    $input = [];
}

$name = isset($input['name']) ? trim((string)$input['name']) : '';
$password = isset($input['password']) ? (string)$input['password'] : '';

if ($name === '' || $password === '') {
    ApiResponse::validationError('Username and password are required.');
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: CALL CONTROLLER
// ─────────────────────────────────────────────────────────────────────────────────

try {
    $result = AuthController::login($name, $password, $conn);
} catch (Throwable $e) {
    // Log error but don't expose details to client
    error_log('Login error: ' . $e->getMessage());
    ApiResponse::error('An error occurred during login. Please try again.', 500);
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 6: RETURN RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

if ($result['success']) {
    // Return redirectUrl at top level (not in data) for backward compatibility
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'redirectUrl' => 'portal.html'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    ApiResponse::error($result['message'] ?? 'Invalid credentials. Please try again.', 401);
}

?>

