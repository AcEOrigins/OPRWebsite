<?php
// ============================================
// BattleMetrics → servers.json updater
// ============================================

// Your BattleMetrics API key
$apiKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbiI6IjI4MDlmZjRkMDVjYWMxZjEiLCJpYXQiOjE3NjI2NTAwMTMsIm5iZiI6MTc2MjY1MDAxMywiaXNzIjoiaHR0cHM6Ly93d3cuYmF0dGxlbWV0cmljcy5jb20iLCJzdWIiOiJ1cm46dXNlcjoxMDMyMzk1In0.Xfol6h4NxOnufPP76UQFO6NM0bcw95hQLGcJ94V69QE";

// Your server IDs (comma separated)
$serverIds = ["36304076", "35428051", "35427848", "35428050", "35427845", "36208436"];

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

// Save JSON file locally (same folder as this script)
$filePath = __DIR__ . "/servers.json";
file_put_contents($filePath, json_encode($clusterData, JSON_PRETTY_PRINT));

// Output to browser as well
header("Content-Type: application/json");
echo json_encode($clusterData);
?>
