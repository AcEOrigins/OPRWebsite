<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * auth_check.php - Session Verification Endpoint
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Verifies if the current request has a valid session and returns user info.
 * Uses centralized AuthController for all authentication logic.
 * 
 * REQUEST
 * ───────
 * GET /auth_check.php
 * (Browser automatically sends session cookie if one exists)
 * 
 * RESPONSE (AUTHENTICATED)
 * ────────────────────────
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "data": {
 *     "authenticated": true,
 *     "userId": 1,
 *     "userName": "admin",
 *     "role": "owner"
 *   }
 * }
 * 
 * RESPONSE (NOT AUTHENTICATED)
 * ────────────────────────────
 * HTTP 200 OK
 * {
 *   "success": true,
 *   "data": {
 *     "authenticated": false
 *   }
 * }
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
// SECTION 2: CHECK AUTHENTICATION
// ─────────────────────────────────────────────────────────────────────────────────

$result = AuthController::checkAuth($conn);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: BUILD RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

// Return authenticated at top level (not in data) for backward compatibility
$response = [
    'success' => true,
    'authenticated' => $result['authenticated'],
];

if ($result['authenticated'] && $result['user']) {
    $response['userId'] = $result['user']['id'];
    $response['userName'] = $result['user']['name'];
    $response['role'] = $result['user']['role'];
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: SEND RESPONSE
// ─────────────────────────────────────────────────────────────────────────────────

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

?>

