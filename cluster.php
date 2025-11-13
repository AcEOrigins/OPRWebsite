<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * cluster.php - BattleMetrics Server Snapshot Utility
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Utility script to fetch a fixed set of BattleMetrics server IDs and write
 * a local servers.json snapshot file. Also echoes JSON to browser.
 * 
 * USAGE
 * ─────
 * Run via cron or manual execution to update servers.json snapshot.
 * 
 * OUTPUT
 * ──────
 * - Creates/updates servers.json file in same directory
 * - Outputs JSON array to browser
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────────

// BattleMetrics API key
$apiKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbiI6IjI4MDlmZjRkMDVjYWMxZjEiLCJpYXQiOjE3NjI2NTAwMTMsIm5iZiI6MTc2MjY1MDAxMywiaXNzIjoiaHR0cHM6Ly93d3cuYmF0dGxlbWV0cmljcy5jb20iLCJzdWIiOiJ1cm46dXNlcjoxMDMyMzk1In0.Xfol6h4NxOnufPP76UQFO6NM0bcw95hQLGcJ94V69QE";

// Server IDs to fetch (comma separated)
$serverIds = ["36304076", "35428051", "35427848", "35428050", "35427845", "36208436"];

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: FETCH SERVER DATA
// ─────────────────────────────────────────────────────────────────────────────────

$clusterData = [];

foreach ($serverIds as $id) {
    $url = "https://api.battlemetrics.com/servers/$id";
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "Authorization: Bearer $apiKey"
        ]
    ];
    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);

    if ($result) {
        $json = json_decode($result, true);
        $srv = $json["data"]["attributes"];

        $clusterData[] = [
            "name" => $srv["name"],
            "players" => $srv["players"],
            "maxPlayers" => $srv["maxPlayers"],
            "status" => $srv["status"],
            "map" => $srv["details"]["map"],
            "ip" => $srv["ip"],
            "port" => $srv["port"]
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: SAVE TO FILE
// ─────────────────────────────────────────────────────────────────────────────────

// Save JSON file locally (same folder as this script)
$filePath = __DIR__ . "/servers.json";
file_put_contents($filePath, json_encode($clusterData, JSON_PRETTY_PRINT));

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: OUTPUT TO BROWSER
// ─────────────────────────────────────────────────────────────────────────────────

// Output to browser as well
header("Content-Type: application/json");
echo json_encode($clusterData);

?>

