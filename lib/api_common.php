<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * lib/api_common.php
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Common API helpers: JSON response helpers, method validation and input parsing.
 * Keep these functions small, well-documented and dependency-free so endpoints
 * can include just this file and rely on consistent response formatting.
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: TIMEZONE CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────────

// Set a sane default timezone if not already configured (doesn't override PHP ini)
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: JSON RESPONSE HELPERS
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ JSON RESPONSE - Send JSON with HTTP Status Code                       │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Ensures proper header and encodes data safely.
 * 
 * @param mixed $data Data to encode as JSON
 * @param int $code HTTP status code (default: 200)
 * @return void
 */
function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ JSON SUCCESS - Shortcut for Success Response                          │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Returns a boolean success response with optional extra data.
 * 
 * @param array $extra Optional extra data to include
 * @return void
 */
function json_success(array $extra = []): void
{
    json_response(array_merge(['success' => true], $extra), 200);
}

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ JSON ERROR - Shortcut for Error Response                               │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Keeps messages generic for production; callers can opt to log details.
 * 
 * @param string $message Error message
 * @param int $code HTTP status code (default: 400)
 * @param array $extra Optional extra data
 * @return void
 */
function json_error(string $message, int $code = 400, array $extra = []): void
{
    $payload = array_merge(['success' => false, 'message' => $message], $extra);
    json_response($payload, $code);
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: HTTP METHOD VALIDATION
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ REQUIRE METHOD - Enforce Expected HTTP Method                          │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Returns nothing on success, sends a 405 response and exits on mismatch.
 * 
 * @param string $expected Expected HTTP method (e.g., 'POST', 'GET')
 * @return void (exits on mismatch)
 */
function require_method(string $expected): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $expected) {
        json_error('Method not allowed.', 405);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: INPUT PARSING
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ PARSE JSON BODY - Parse JSON Request Body                             │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Returns array on success, or sends a 422 response and exits when body
 * is missing or invalid.
 * 
 * @return array Parsed JSON body as associative array
 */
function parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        json_error('Empty request body.', 422);
        exit;
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        json_error('Invalid JSON body.', 422);
        exit;
    }
    return $data;
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: DATETIME NORMALIZATION
// ─────────────────────────────────────────────────────────────────────────────────

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ NORMALIZE DATETIME LOCAL - Convert HTML to MySQL Format                │
 * └─────────────────────────────────────────────────────────────────────────┘
 * 
 * Normalizes an HTML datetime-local (e.g. "2020-12-31T13:45") into MySQL
 * DATETIME string (YYYY-MM-DD HH:MM:SS) or return null for empty input.
 * 
 * @param string|null $value HTML datetime-local string
 * @return string|null MySQL DATETIME string or null
 */
function normalize_datetime_local(?string $value): ?string
{
    if ($value === null || trim($value) === '') return null;
    $ts = strtotime($value);
    if ($ts === false) return null;
    return date('Y-m-d H:i:s', $ts);
}

?>

