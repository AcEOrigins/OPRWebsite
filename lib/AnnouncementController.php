<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/AnnouncementController.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Handles all announcement-related operations:
 * - Listing announcements (with filtering)
 * - Creating announcements
 * - Deleting announcements (soft-delete)
 * - Time-window validation
 * 
 * FEATURES
 * ────────
 * ✓ Filtering by serverId, battlemetricsId, activeOnly
 * ✓ Time-window filtering (starts_at/ends_at)
 * ✓ Severity whitelist validation
 * ✓ HTML datetime-local to MySQL DATETIME conversion
 * ✓ Soft-delete with graceful end time
 * ✓ Global announcements (server_id = NULL)
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

class AnnouncementController
{
    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 1: CONSTANTS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ ALLOWED SEVERITY LEVELS                                                 │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Whitelist of valid severity values (prevents injection).
     */
    private const ALLOWED_SEVERITIES = ['info', 'success', 'warning', 'error'];

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 2: TABLE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ ENSURE ANNOUNCEMENTS TABLE EXISTS                                       │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Idempotent: Creates if missing, no-op if exists.
     * 
     * TABLE SCHEMA:
     * - id: Primary key
     * - server_id: NULL=global, otherwise references servers(id)
     * - message: Announcement text
     * - severity: 'info', 'success', 'warning', 'error'
     * - starts_at, ends_at: Time window (NULL=always active)
     * - is_active: Soft-delete flag
     * - created_at, updated_at: Timestamps
     * 
     * FOREIGN KEY: server_id → servers(id) ON DELETE SET NULL
     * (If server deleted, announcement becomes global)
     * 
     * @param mysqli $conn Database connection
     * @return void
     */
    private static function ensureTableExists(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                server_id INT NULL,
                message TEXT NOT NULL,
                severity VARCHAR(16) NOT NULL DEFAULT 'info',
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_server_id (server_id),
                INDEX idx_is_active (is_active),
                INDEX idx_starts_at (starts_at),
                INDEX idx_ends_at (ends_at),
                CONSTRAINT fk_announcements_server_id FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        @$conn->query($sql);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 3: PUBLIC CRUD METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ GET ANNOUNCEMENTS - Retrieve Announcements with Filtering              │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Supports filtering by:
     * - serverId: Shows announcements for that server + global
     * - battlemetricsId: Looks up server, then filters like serverId
     * - activeOnly: Only announcements in their active time window
     * 
     * @param mysqli $conn Database connection
     * @param array $filters ['serverId' => int, 'battlemetricsId' => string, 'activeOnly' => bool]
     * @return array Array of announcement objects
     */
    public static function getAnnouncements(mysqli $conn, array $filters = []): array
    {
        self::ensureTableExists($conn);

        // ────────────────────────────────────────────────────────────────────────
        // STEP 1: Extract Filter Parameters
        // ────────────────────────────────────────────────────────────────────────
        
        $serverId = isset($filters['serverId']) ? (int)$filters['serverId'] : 0;
        $battlemetricsId = isset($filters['battlemetricsId']) ? trim((string)$filters['battlemetricsId']) : '';
        $activeOnly = isset($filters['activeOnly']) ? (int)$filters['activeOnly'] : 0;

        // ────────────────────────────────────────────────────────────────────────
        // STEP 2: Build WHERE Clause Dynamically
        // ────────────────────────────────────────────────────────────────────────
        
        $conditions = [];
        $params = [];
        $types = '';

        if ($serverId > 0) {
            $conditions[] = '(a.server_id = ? OR a.server_id IS NULL)';
            $types .= 'i';
            $params[] = $serverId;
        }

        if ($battlemetricsId !== '') {
            $conditions[] = '(s.battlemetrics_id = ? OR a.server_id IS NULL)';
            $types .= 's';
            $params[] = $battlemetricsId;
        }

        if ($activeOnly === 1) {
            $conditions[] = 'a.is_active = 1';
            $conditions[] = '(a.starts_at IS NULL OR a.starts_at <= NOW())';
            $conditions[] = '(a.ends_at IS NULL OR a.ends_at >= NOW())';
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 3: Build and Execute Query
        // ────────────────────────────────────────────────────────────────────────
        
        $sql = "
            SELECT
                a.id,
                a.message,
                a.severity,
                a.starts_at,
                a.ends_at,
                a.is_active,
                a.created_at,
                a.updated_at,
                a.server_id,
                s.display_name AS server_name,
                s.battlemetrics_id
            FROM announcements a
            LEFT JOIN servers s ON s.id = a.server_id
            {$where}
            ORDER BY a.created_at DESC, a.id DESC
        ";

        $announcements = [];

        try {
            if ($types !== '') {
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception('Failed to prepare statement.');
                }

                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($sql);
            }

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $announcements[] = $row;
                }
                $result->free();
            }

            if (isset($stmt)) {
                $stmt->close();
            }
        } catch (Throwable $e) {
            // Graceful degradation: return empty array on error
            return [];
        }

        return $announcements;
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ SAVE ANNOUNCEMENT - Create New Announcement                            │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Validates, normalizes datetime, and inserts into database.
     * 
     * @param array $data Announcement data
     * @param mysqli $conn Database connection
     * @return array [
     *   'success' => bool,
     *   'announcement' => array | null,
     *   'id' => int,
     *   'message' => string (on failure)
     * ]
     */
    public static function saveAnnouncement(array $data, mysqli $conn): array
    {
        self::ensureTableExists($conn);

        // ────────────────────────────────────────────────────────────────────────
        // STEP 1: Extract and Validate Input
        // ────────────────────────────────────────────────────────────────────────
        
        $message = isset($data['message']) ? trim((string)$data['message']) : '';

        if ($message === '') {
            return [
                'success' => false,
                'message' => 'Message is required.',
            ];
        }

        $severity = isset($data['severity']) ? strtolower(trim((string)$data['severity'])) : 'info';

        // Validate severity against whitelist
        if (!in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            $severity = 'info';
        }

        $serverId = isset($data['serverId']) && $data['serverId'] !== '' ? (int)$data['serverId'] : null;

        $startsAt = isset($data['startsAt']) ? trim((string)$data['startsAt']) : '';
        $endsAt = isset($data['endsAt']) ? trim((string)$data['endsAt']) : '';

        // Normalize datetime fields (HTML datetime-local → MySQL DATETIME)
        $startsAtDb = self::normalizeDateTime($startsAt);
        $endsAtDb = self::normalizeDateTime($endsAt);

        $isActive = isset($data['isActive']) ? (int)(!!$data['isActive']) : 1;

        // ────────────────────────────────────────────────────────────────────────
        // STEP 2: Insert Announcement
        // ────────────────────────────────────────────────────────────────────────
        
        $stmt = $conn->prepare("
            INSERT INTO announcements (server_id, message, severity, starts_at, ends_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
            ];
        }

        $stmt->bind_param('issssi', $serverId, $message, $severity, $startsAtDb, $endsAtDb, $isActive);

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to save announcement.',
            ];
        }

        $insertId = $stmt->insert_id;
        $stmt->close();

        // ────────────────────────────────────────────────────────────────────────
        // STEP 3: Retrieve Saved Announcement
        // ────────────────────────────────────────────────────────────────────────
        
        $select = $conn->prepare("
            SELECT
                a.id,
                a.message,
                a.severity,
                a.starts_at,
                a.ends_at,
                a.is_active,
                a.created_at,
                a.updated_at,
                a.server_id,
                s.display_name AS server_name,
                s.battlemetrics_id
            FROM announcements a
            LEFT JOIN servers s ON s.id = a.server_id
            WHERE a.id = ?
            LIMIT 1
        ");

        $announcement = null;

        if ($select) {
            $select->bind_param('i', $insertId);
            $select->execute();
            $result = $select->get_result();
            $announcement = $result ? $result->fetch_assoc() : null;
            $select->close();
        }

        return [
            'success' => true,
            'announcement' => $announcement,
            'id' => $insertId,
        ];
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ DELETE ANNOUNCEMENT - Soft-Delete Announcement                         │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Sets is_active = 0 and ends_at = COALESCE(ends_at, NOW()).
     * Gracefully ends announcements that don't have an end time.
     * 
     * @param int $announcementId Announcement ID to delete
     * @param mysqli $conn Database connection
     * @return array ['success' => bool, 'message' => string (on failure)]
     */
    public static function deleteAnnouncement(int $announcementId, mysqli $conn): array
    {
        if ($announcementId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid announcement ID.',
            ];
        }

        $stmt = $conn->prepare('
            UPDATE announcements
            SET is_active = 0, ends_at = COALESCE(ends_at, NOW())
            WHERE id = ?
            LIMIT 1
        ');

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
            ];
        }

        $stmt->bind_param('i', $announcementId);

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to delete announcement.',
            ];
        }

        $stmt->close();

        return ['success' => true];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 4: HELPER METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ NORMALIZE DATETIME - Convert HTML to MySQL Format                      │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Input:  "2024-01-15T20:00" (HTML datetime-local)
     * Output: "2024-01-15 20:00:00" (MySQL DATETIME) or null
     * 
     * @param string|null $value Datetime string in HTML format
     * @return string|null Datetime string in MySQL format or null
     */
    private static function normalizeDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        $ts = strtotime($value);

        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}

?>

