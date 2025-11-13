<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/ApiResponse.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Unified API response formatting and HTTP status code handling.
 * Ensures consistent JSON response structure across all endpoints.
 * 
 * USAGE
 * ─────
 * ApiResponse::success(['user' => $userData]);
 * ApiResponse::error('Invalid input', 422);
 * ApiResponse::unauthorized();
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

class ApiResponse
{
    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 1: CORE RESPONSE METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ SUCCESS RESPONSE                                                        │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Sends a successful JSON response with optional data payload.
     * 
     * @param array $data Optional data payload to include
     * @param int $statusCode HTTP status code (default: 200)
     * @return void (exits script)
     */
    public static function success(array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        
        $response = ['success' => true];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        self::sendJson($response);
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ ERROR RESPONSE                                                          │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Sends an error JSON response with message.
     * Uses generic messages for security (prevents info leakage).
     * 
     * @param string $message Error message to display
     * @param int $statusCode HTTP status code (default: 400)
     * @param array $extra Optional extra data for debugging
     * @return void (exits script)
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        array $extra = []
    ): void {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'message' => $message,
        ];
        
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }
        
        self::sendJson($response);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 2: HTTP STATUS CODE SHORTCUTS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ METHOD NOT ALLOWED (405)                                                │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public static function methodNotAllowed(): void
    {
        self::error('Method not allowed.', 405);
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ UNAUTHORIZED (401) - Not authenticated                                  │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public static function unauthorized(): void
    {
        self::error('Unauthorized.', 401);
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ FORBIDDEN (403) - Authenticated but no permission                       │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public static function forbidden(): void
    {
        self::error('Forbidden.', 403);
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ NOT FOUND (404)                                                         │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public static function notFound(string $message = 'Resource not found.'): void
    {
        self::error($message, 404);
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ VALIDATION ERROR (422)                                                  │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public static function validationError(string $message): void
    {
        self::error($message, 422);
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ CONFLICT (409) - Duplicate resource                                     │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public static function conflict(string $message): void
    {
        self::error($message, 409);
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ INTERNAL SERVER ERROR (500)                                             │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public static function serverError(string $message = 'Internal server error.'): void
    {
        self::error($message, 500);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SECTION 3: INTERNAL HELPER METHODS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ SEND JSON RESPONSE                                                     │
     * └─────────────────────────────────────────────────────────────────────────┘
     * 
     * Encodes array as JSON, sets headers, and outputs.
     * Exits immediately after sending.
     * 
     * @param array $data Array to encode as JSON
     * @return void (exits script)
     */
    private static function sendJson(array $data): void
    {
        // Clear any buffered output to ensure clean JSON response
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Flush output buffer if it exists
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        
        exit;
    }
}

?>

