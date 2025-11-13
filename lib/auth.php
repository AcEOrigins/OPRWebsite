<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/auth.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Lightweight auth helpers. For the PoC we expose a require_auth() stub that
 * endpoints can call; later this will replace duplicated auth_check.php logic.
 * 
 * NOTE: These are stub functions. For production, use AuthController instead.
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ REQUIRE AUTH - Require that a User is Authenticated                    │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Placeholder: require that a user is authenticated.
 * For the PoC this will check PHP session values set by login.php.
 * If not authenticated, sends a 401 JSON response and exits.
 * 
 * @return array User data from session
 */
function require_auth()
{
    session_start();
    if (empty($_SESSION['user']) || empty($_SESSION['role'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    return $_SESSION['user'];
}

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ REQUIRE ROLE - Require Specific Role(s)                                 │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Require a role (or set of roles) be present in the session.
 * Sends 403 on fail.
 * 
 * @param array $allowedRoles Array of allowed role strings
 * @return void (exits on failure)
 */
function require_role(array $allowedRoles)
{
    session_start();
    $role = $_SESSION['role'] ?? null;
    if ($role === null || !in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

?>

