<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/battlemetrics_client.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Minimal BattleMetrics client wrapper. This keeps BattleMetrics details
 * (timeouts, headers, error handling) contained so endpoints can call
 * a simple function like `getServerById($id)`.
 * 
 * NOTE: This client expects the API key to be provided via an environment
 * variable (BATTLEMETRICS_API_KEY) or defined in the server's configuration.
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

class BattleMetricsClient
{
    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 1: PROPERTIES
    // ─────────────────────────────────────────────────────────────────────────────

    private string $base = 'https://api.battlemetrics.com';
    private ?string $apiKey;

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 2: CONSTRUCTOR
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ CONSTRUCTOR                                                             │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * @param string|null $apiKey Optional API key (defaults to env var)
     */
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? getenv('BATTLEMETRICS_API_KEY');
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 3: PRIVATE REQUEST METHOD
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ REQUEST - Execute HTTP Request to BattleMetrics API                    │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * @param string $path API path (e.g., "servers/123456")
     * @return array ['ok' => bool, 'status' => int, 'data' => array, 'error' => string]
     */
    private function request(string $path): array
    {
        $url = rtrim($this->base, '/') . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8); // short timeout to avoid blocking
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        
        if ($this->apiKey) {
            $headers[] = "Authorization: Bearer {$this->apiKey}";
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $body = curl_exec($ch);
        $errNo = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) {
            return ['ok' => false, 'error' => 'curl_error', 'errno' => $errNo];
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'error' => 'invalid_json', 'body' => $body, 'status' => $httpCode];
        }
        
        return ['ok' => true, 'status' => $httpCode, 'data' => $data];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 4: PUBLIC API METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ GET SERVER BY ID - Fetch Server by BattleMetrics ID                    │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * @param string $id BattleMetrics server ID
     * @return array ['ok' => bool, 'status' => int, 'data' => array, 'error' => string]
     */
    public function getServerById(string $id): array
    {
        return $this->request("servers/{$id}");
    }
}

?>

