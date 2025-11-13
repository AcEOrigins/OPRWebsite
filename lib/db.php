<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/db.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Small database wrapper helpers to centralize the idempotent table creation
 * and common fetch patterns. This keeps endpoint files short and consistent.
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ GET DB CONN - Return Active mysqli Connection                          │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Returns an active mysqli connection.
 * This simply requires the project's `dbconnect.php` which sets up $conn.
 * Ensures charset is set to utf8mb4.
 * 
 * @return mysqli Database connection object
 * @throws RuntimeException If connection not available
 */
function get_db_conn()
{
    require_once __DIR__ . '/../dbconnect.php';
    if (!isset($conn)) {
        throw new RuntimeException('Database connection not available.');
    }
    // Ensure charset is set to utf8mb4
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ FETCH ACTIVE SERVERS - Get Active Servers with Table Creation        │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Fetch active servers, creating table if necessary (idempotent).
 * Returns an array of associative rows like the legacy endpoint.
 * 
 * @param mysqli $conn Database connection
 * @return array Array of server objects
 */
function fetch_active_servers($conn): array
{
    // ────────────────────────────────────────────────────────────────────────────
    // STEP 1: Create Table if Not Exists (Idempotent)
    // ────────────────────────────────────────────────────────────────────────────
    
    $createTableSql = "
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

    // Idempotent table creation - suppress warnings but allow failure to surface later
    @$conn->query($createTableSql);

    // ────────────────────────────────────────────────────────────────────────────
    // STEP 2: Query Active Servers
    // ────────────────────────────────────────────────────────────────────────────
    
    $sql = "SELECT id, battlemetrics_id, display_name, game_title, region
            FROM servers
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC";

    $servers = [];
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $servers[] = $row;
        }
        $result->free();
    }
    
    return $servers;
}

?>

