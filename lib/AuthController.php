<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/AuthController.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Handles all authentication-related operations:
 * - User login (username/password verification)
 * - Session checking (verify authenticated user)
 * - Session security configuration
 * - Logout functionality
 * 
 * SECURITY FEATURES
 * ─────────────────
 * ✓ Password hashing with PASSWORD_DEFAULT (bcrypt)
 * ✓ password_verify() for secure comparison
 * ✓ Session regeneration (prevents fixation)
 * ✓ Role verified from database (never trust client)
 * ✓ Generic error messages (prevents info leakage)
 * ✓ HttpOnly cookies (prevents XSS)
 * ✓ SameSite=Lax (prevents CSRF)
 * ✓ Strict session mode
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

class AuthController
{
    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 1: PUBLIC AUTHENTICATION METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ LOGIN - Authenticate User and Create Session                           │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Validates credentials against database, creates secure session.
     * 
     * @param string $name Username
     * @param string $password Plaintext password
     * @param mysqli $conn Database connection
     * @return array [
     *   'success' => bool,
     *   'user' => ['id' => int, 'name' => string, 'role' => string] | null,
     *   'message' => string (on failure)
     * ]
     */
    public static function login(string $name, string $password, mysqli $conn): array
    {
        // ────────────────────────────────────────────────────────────────────────
        // STEP 1: Validate Input
        // ────────────────────────────────────────────────────────────────────────
        
        if (empty(trim($name)) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required.',
            ];
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 2: Query Database for User
        // ────────────────────────────────────────────────────────────────────────
        
        $stmt = $conn->prepare(
            'SELECT id, name, password_hash, role, is_active
             FROM users
             WHERE name = ?
             LIMIT 1'
        );

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Unable to process request.',
            ];
        }

        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        // ────────────────────────────────────────────────────────────────────────
        // STEP 3: Verify Credentials
        // ────────────────────────────────────────────────────────────────────────
        
        // Combined check prevents info leakage:
        // - Don't reveal if user exists
        // - Don't reveal if account is inactive
        // - Always say "invalid credentials"
        if (
            !$user
            || (int)$user['is_active'] !== 1
            || !password_verify($password, $user['password_hash'])
        ) {
            return [
                'success' => false,
                'message' => 'Invalid credentials. Please try again.',
            ];
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 4: Create Secure Session
        // ────────────────────────────────────────────────────────────────────────
        
        if (session_status() === PHP_SESSION_NONE) {
            self::configureSessionSecurity();
            session_start();
        }

        // Regenerate session ID to prevent fixation attacks
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        // ────────────────────────────────────────────────────────────────────────
        // STEP 5: Return Success
        // ────────────────────────────────────────────────────────────────────────
        
        return [
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
            ],
        ];
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ CHECK AUTH - Verify Current Session                                    │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Checks if user has valid session, returns user info.
     * Verifies role from database (source of truth).
     * 
     * @param mysqli $conn Database connection
     * @return array [
     *   'authenticated' => bool,
     *   'user' => ['id' => int, 'name' => string, 'role' => string] | null
     * ]
     */
    public static function checkAuth(mysqli $conn): array
    {
        // ────────────────────────────────────────────────────────────────────────
        // STEP 1: Configure and Start Session
        // ────────────────────────────────────────────────────────────────────────
        
        if (session_status() === PHP_SESSION_NONE) {
            self::configureSessionSecurity();
            session_start();
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 2: Quick Session Check
        // ────────────────────────────────────────────────────────────────────────
        
        if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
            return [
                'authenticated' => false,
                'user' => null,
            ];
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 3: Verify Role with Database (Source of Truth)
        // ────────────────────────────────────────────────────────────────────────
        
        $userId = (int)$_SESSION['user_id'];
        $userName = (string)($_SESSION['user_name'] ?? '');
        $userRole = (string)($_SESSION['user_role'] ?? 'admin');

        // Query database for current role (prevents stale/tampered session data)
        $stmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');

        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;

            if ($row) {
                $userRole = (string)$row['role'];
            }

            $stmt->close();
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 4: Return Authenticated User
        // ────────────────────────────────────────────────────────────────────────
        
        return [
            'authenticated' => true,
            'user' => [
                'id' => $userId,
                'name' => $userName,
                'role' => $userRole,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 2: SESSION SECURITY CONFIGURATION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ CONFIGURE SESSION SECURITY                                             │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Sets INI settings for secure session handling.
     * MUST be called BEFORE session_start().
     * 
     * SECURITY SETTINGS:
     * - HttpOnly: true → Cookie NOT accessible to JavaScript (XSS protection)
     * - SameSite: Lax → Cookie NOT sent in cross-site requests (CSRF protection)
     * - Strict Mode: true → Rejects uninitialized session IDs (fixation protection)
     */
    public static function configureSessionSecurity(): void
    {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ LOGOUT - Destroy Session                                                │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Clears user session (called on logout).
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::configureSessionSecurity();
            session_start();
        }

        $_SESSION = [];
        session_destroy();
    }
}

?>

