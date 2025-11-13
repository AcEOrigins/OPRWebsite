<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/UserController.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Handles all user management operations:
 * - Listing users
 * - Creating users (with password hashing)
 * - Deactivating users
 * - Reactivating users
 * - Resetting passwords
 * 
 * SECURITY FEATURES
 * ─────────────────
 * ✓ Password hashing with PASSWORD_DEFAULT (bcrypt)
 * ✓ Duplicate username detection (409 Conflict)
 * ✓ Soft-delete pattern
 * ✓ Never returns password_hash in API
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

class UserController
{
    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 1: TABLE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ ENSURE USERS TABLE EXISTS                                               │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Idempotent: Creates if missing, no-op if exists.
     * 
     * TABLE SCHEMA:
     * - id: Primary key
     * - name: Username (UNIQUE)
     * - password_hash: Hashed password (PASSWORD_DEFAULT = bcrypt)
     * - role: 'owner', 'admin', or 'staff' (default='admin')
     * - is_active: Soft-delete flag (1=active, 0=inactive)
     * - created_at, updated_at: Timestamps
     * 
     * @param mysqli $conn Database connection
     * @return void
     */
    private static function ensureTableExists(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL DEFAULT 'admin',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        @$conn->query($sql);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 2: PUBLIC CRUD METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ LIST USERS - Get All Users (Active and Inactive)                       │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Returns all users including inactive ones (for admin management).
     * Never returns password_hash (security).
     * 
     * @param mysqli $conn Database connection
     * @return array Array of user objects (without password_hash)
     */
    public static function listUsers(mysqli $conn): array
    {
        self::ensureTableExists($conn);

        $result = $conn->query("
            SELECT id, name, role, is_active, created_at, updated_at
            FROM users
            ORDER BY name ASC
        ");

        $users = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $result->free();
        }

        return $users;
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ ADD USER - Create New User Account                                     │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Validates, hashes password, and inserts into database.
     * Handles duplicate username errors (409 Conflict).
     * 
     * @param string $name Username
     * @param string $password Plaintext password
     * @param string $role User role (owner, admin, staff)
     * @param mysqli $conn Database connection
     * @return array [
     *   'success' => bool,
     *   'user' => array | null,
     *   'message' => string (on failure),
     *   'code' => int (HTTP status code on failure)
     * ]
     */
    public static function addUser(
        string $name,
        string $password,
        string $role,
        mysqli $conn
    ): array {
        self::ensureTableExists($conn);

        // ────────────────────────────────────────────────────────────────────────
        // STEP 1: Validate Input
        // ────────────────────────────────────────────────────────────────────────
        
        $name = trim($name);
        $role = trim($role);

        if ($name === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Name and password are required.',
                'code' => 422,
            ];
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 2: Hash Password
        // ────────────────────────────────────────────────────────────────────────
        
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // ────────────────────────────────────────────────────────────────────────
        // STEP 3: Insert User into Database
        // ────────────────────────────────────────────────────────────────────────
        
        $stmt = $conn->prepare("
            INSERT INTO users (name, password_hash, role, is_active)
            VALUES (?, ?, ?, 1)
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
                'code' => 500,
            ];
        }

        $stmt->bind_param('sss', $name, $hash, $role);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            // Check for duplicate username
            if (strpos($error, 'Duplicate') !== false) {
                return [
                    'success' => false,
                    'message' => 'User already exists.',
                    'code' => 409,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to create user.',
                'code' => 500,
            ];
        }

        $userId = $stmt->insert_id;
        $stmt->close();

        // ────────────────────────────────────────────────────────────────────────
        // STEP 4: Retrieve Created User (Without Password Hash)
        // ────────────────────────────────────────────────────────────────────────
        
        $get = $conn->prepare("
            SELECT id, name, role, is_active, created_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $user = null;

        if ($get) {
            $get->bind_param('i', $userId);
            $get->execute();
            $result = $get->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $get->close();
        }

        return [
            'success' => true,
            'user' => $user,
        ];
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ DEACTIVATE USER - Soft-Delete User Account                            │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Sets is_active = 0 (user cannot log in).
     * 
     * @param int $userId User ID to deactivate
     * @param mysqli $conn Database connection
     * @return array ['success' => bool, 'message' => string (on failure)]
     */
    public static function deactivateUser(int $userId, mysqli $conn): array
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid user ID.',
            ];
        }

        $stmt = $conn->prepare('UPDATE users SET is_active = 0 WHERE id = ? LIMIT 1');

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
            ];
        }

        $stmt->bind_param('i', $userId);

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to deactivate user.',
            ];
        }

        $stmt->close();

        return ['success' => true];
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ REACTIVATE USER - Restore User Account                                 │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Sets is_active = 1 (user can log in again).
     * 
     * @param int $userId User ID to reactivate
     * @param mysqli $conn Database connection
     * @return array ['success' => bool, 'message' => string (on failure)]
     */
    public static function reactivateUser(int $userId, mysqli $conn): array
    {
        if ($userId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid user ID.',
            ];
        }

        $stmt = $conn->prepare('UPDATE users SET is_active = 1 WHERE id = ? LIMIT 1');

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
            ];
        }

        $stmt->bind_param('i', $userId);

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to reactivate user.',
            ];
        }

        $stmt->close();

        return ['success' => true];
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ RESET PASSWORD - Change User Password                                  │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Hashes new password and updates in database.
     * 
     * @param int $userId User ID
     * @param string $newPassword New plaintext password
     * @param mysqli $conn Database connection
     * @return array ['success' => bool, 'message' => string (on failure)]
     */
    public static function resetPassword(
        int $userId,
        string $newPassword,
        mysqli $conn
    ): array {
        if ($userId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid user ID.',
            ];
        }

        if (empty($newPassword)) {
            return [
                'success' => false,
                'message' => 'New password is required.',
            ];
        }

        // Hash New Password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update Password in Database
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
            ];
        }

        $stmt->bind_param('si', $hash, $userId);

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to reset password.',
            ];
        }

        $stmt->close();

        return ['success' => true];
    }
}

?>

