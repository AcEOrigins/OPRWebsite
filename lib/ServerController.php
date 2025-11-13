<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/ServerController.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Handles all server-related operations:
 * - Listing servers
 * - Creating/updating servers
 * - Deleting servers (soft-delete)
 * - BattleMetrics API integration
 * 
 * FEATURES
 * ────────
 * ✓ Idempotent table creation (IF NOT EXISTS)
 * ✓ BattleMetrics API enrichment (graceful fallback)
 * ✓ Soft-delete pattern (is_active = 0)
 * ✓ Parameterized queries (SQL injection prevention)
 * ✓ INSERT...ON DUPLICATE KEY UPDATE (upsert pattern)
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

class ServerController
{
    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 1: TABLE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ ENSURE SERVERS TABLE EXISTS                                             │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Idempotent: Creates if missing, no-op if exists.
     * 
     * TABLE SCHEMA:
     * - id: Primary key (auto-increment)
     * - battlemetrics_id: Unique BattleMetrics server ID
     * - display_name: User-friendly name
     * - game_title: Game name (Rust, Ark, etc.)
     * - region: Geographic region
     * - is_active: Soft-delete flag (1=active, 0=deleted)
     * - sort_order: Custom sort order for admin
     * - created_at, updated_at: Timestamps
     * 
     * INDEXES:
     * - idx_is_active: Speed up WHERE is_active = 1 queries
     * - idx_sort_order: Speed up ORDER BY sort_order
     * 
     * @param mysqli $conn Database connection
     * @return void
     */
    private static function ensureTableExists(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS servers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                battlemetrics_id VARCHAR(50) NOT NULL UNIQUE,
                display_name VARCHAR(255) NOT NULL,
                game_title VARCHAR(255) DEFAULT NULL,
                region VARCHAR(100) DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_is_active (is_active),
                INDEX idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        @$conn->query($sql);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 2: PUBLIC CRUD METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ GET SERVERS - Retrieve All Active Servers                               │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Returns list of active servers, ordered by sort_order.
     * 
     * @param mysqli $conn Database connection
     * @return array Array of server objects
     */
    public static function getServers(mysqli $conn): array
    {
        self::ensureTableExists($conn);

        $result = $conn->query("
            SELECT id, battlemetrics_id, display_name, game_title, region
            FROM servers
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");

        $servers = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $servers[] = $row;
            }
            $result->free();
        }

        return $servers;
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ SAVE SERVER - Create or Update Server                                   │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Enriches from BattleMetrics API, inserts/updates in database.
     * Uses INSERT...ON DUPLICATE KEY UPDATE pattern (upsert).
     * 
     * @param string $battlemetricsId BattleMetrics server ID
     * @param mysqli $conn Database connection
     * @return array [
     *   'success' => bool,
     *   'server' => array | null,
     *   'message' => string (on failure)
     * ]
     */
    public static function saveServer(string $battlemetricsId, mysqli $conn): array
    {
        self::ensureTableExists($conn);

        // ────────────────────────────────────────────────────────────────────────
        // STEP 1: Validate Input
        // ────────────────────────────────────────────────────────────────────────
        
        $battlemetricsId = trim($battlemetricsId);

        if ($battlemetricsId === '') {
            return [
                'success' => false,
                'message' => 'BattleMetrics ID is required.',
            ];
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 2: Enrich from BattleMetrics API (Optional)
        // ────────────────────────────────────────────────────────────────────────
        
        $displayName = '';
        $gameTitle = '';
        $region = '';

        $apiData = self::fetchFromBattleMetricsAPI($battlemetricsId);

        if ($apiData) {
            $displayName = $apiData['displayName'];
            $gameTitle = $apiData['gameTitle'];
            $region = $apiData['region'];
        }

        // Fallback if API didn't return a name
        if ($displayName === '') {
            $displayName = 'Server ' . $battlemetricsId;
        }

        // ────────────────────────────────────────────────────────────────────────
        // STEP 3: Insert or Update in Database (Upsert Pattern)
        // ────────────────────────────────────────────────────────────────────────
        
        $stmt = $conn->prepare("
            INSERT INTO servers (battlemetrics_id, display_name, game_title, region, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name),
                game_title = VALUES(game_title),
                region = VALUES(region),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
            ];
        }

        $stmt->bind_param('ssss', $battlemetricsId, $displayName, $gameTitle, $region);

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to save server.',
            ];
        }

        $stmt->close();

        // ────────────────────────────────────────────────────────────────────────
        // STEP 4: Retrieve Saved Server
        // ────────────────────────────────────────────────────────────────────────
        
        $select = $conn->prepare("
            SELECT id, battlemetrics_id, display_name, game_title, region
            FROM servers
            WHERE battlemetrics_id = ?
            LIMIT 1
        ");

        $server = null;

        if ($select) {
            $select->bind_param('s', $battlemetricsId);
            $select->execute();
            $result = $select->get_result();
            $server = $result ? $result->fetch_assoc() : null;
            $select->close();
        }

        return [
            'success' => true,
            'server' => $server,
        ];
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ DELETE SERVER - Soft-Delete Server                                     │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Sets is_active = 0 (preserves data for history).
     * 
     * @param int $serverId Server ID to delete
     * @param mysqli $conn Database connection
     * @return array ['success' => bool, 'message' => string (on failure)]
     */
    public static function deleteServer(int $serverId, mysqli $conn): array
    {
        if ($serverId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid server ID.',
            ];
        }

        $stmt = $conn->prepare('UPDATE servers SET is_active = 0 WHERE id = ? LIMIT 1');

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare statement.',
            ];
        }

        $stmt->bind_param('i', $serverId);

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Failed to delete server.',
            ];
        }

        $stmt->close();

        return ['success' => true];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 3: BATTLEMETRICS API INTEGRATION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ FETCH FROM BATTLEMETRICS API                                            │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Enriches server data from external API.
     * Returns null if API unavailable (graceful degradation).
     * 
     * @param string $serverId BattleMetrics server ID
     * @return array|null [
     *   'displayName' => string,
     *   'gameTitle' => string,
     *   'region' => string
     * ] or null if API unavailable
     */
    private static function fetchFromBattleMetricsAPI(string $serverId): ?array
    {
        $apiKey = getenv('BATTLEMETRICS_API_KEY') ?: '';

        if (empty($apiKey)) {
            return null;
        }

        $url = 'https://api.battlemetrics.com/servers/' . urlencode($serverId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$apiKey}",
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return null;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['data']['attributes'])) {
            return null;
        }

        $attrs = $data['data']['attributes'];
        $details = $attrs['details'] ?? [];

        return [
            'displayName' => $attrs['name'] ?? $attrs['hostname'] ?? '',
            'gameTitle' => $attrs['game'] ?? $details['gameMode'] ?? $details['mode'] ?? '',
            'region' => $attrs['region'] ?? $details['region'] ?? '',
        ];
    }
}

?>

