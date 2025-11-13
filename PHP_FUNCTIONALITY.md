## OPR Website - PHP Functionality Guide

### Overview
This document explains the PHP backend for the OPR Website: architecture, controllers, endpoints, helpers, database, responses, security, and external integrations (BattleMetrics). Use it to understand how requests are handled, how data is stored, and how to extend the system safely.


### Architecture
- Controller-based business logic in `lib/`:
  - `lib/ApiResponse.php`: Unified JSON responses and HTTP status helpers
  - `lib/AuthController.php`: Authentication, session handling
  - `lib/ServerController.php`: Server CRUD and BattleMetrics enrichment
  - `lib/AnnouncementController.php`: Announcement CRUD and filtering
  - `lib/UserController.php`: User CRUD and password operations
- Thin endpoint routers in project root (e.g., `getServers.php`, `saveServer.php`) call controllers and return JSON.
- Common helpers in `lib/` for parsing, auth stubs, DB access and API client.
- Central DB connection in `dbconnect.php` with utf8mb4 and JSON error on failure.

Reference: `lib/ARCHITECTURE.php` describes the target consolidated pattern and response standards.


### Controllers (lib/)

- `lib/ApiResponse.php`
  - success(array $data = [], int $status = 200): emit {"success": true, "data": {...}}
  - error(string $message, int $status = 400, array $extra = []): emit {"success": false, "message": "..."}
  - Shortcuts: methodNotAllowed(), unauthorized(), forbidden(), notFound(), validationError(), conflict(), serverError()
  - Always sets JSON headers and exits after sending.

- `lib/AuthController.php`
  - login(string $name, string $password, mysqli $conn): verifies user (active + password_verify), starts secure session, regenerates ID, stores user info; returns ['success'=>bool, 'user'=>{...}] or error.
  - checkAuth(mysqli $conn): ensures secure session settings, starts session, checks `$_SESSION['user_id']`, verifies role from DB, returns ['authenticated'=>bool, 'user'=>{...}|null].
  - configureSessionSecurity(): HttpOnly + SameSite=Lax + strict session mode.
  - logout(): clears and destroys session.

- `lib/ServerController.php`
  - getServers(mysqli $conn): returns active servers ordered by `sort_order, id`.
  - saveServer(string $battlemetricsId, mysqli $conn): trims input, fetches (optional) BattleMetrics details, upserts server with `ON DUPLICATE KEY UPDATE`, reactivates soft-deleted rows.
  - deleteServer(int $id, mysqli $conn): soft-deletes (`is_active = 0`).
  - fetchFromBattleMetricsAPI(string $serverId): curl to BM API using `BATTLEMETRICS_API_KEY`, returns displayName/gameTitle/region or null on failure.
  - Tables created idempotently (IF NOT EXISTS) on demand.

- `lib/AnnouncementController.php`
  - getAnnouncements(mysqli $conn, array $filters): optional filters `serverId`, `battlemetricsId`, `activeOnly`; returns announcements joined with server display name and BM ID. Active filter enforces `is_active=1`, time window.
  - saveAnnouncement(array $data, mysqli $conn): validates message, severity whitelist ['info','success','warning','error'], normalizes `startsAt/endsAt` (HTML datetime-local → MySQL DATETIME), inserts and returns saved row with server details.
  - deleteAnnouncement(int $id, mysqli $conn): soft-deletes (`is_active=0`) and sets `ends_at = COALESCE(ends_at, NOW())`.
  - Tables created idempotently.

- `lib/UserController.php`
  - listUsers(mysqli $conn): returns all users (active + inactive), never returns password_hash.
  - addUser(string $name, string $password, string $role, mysqli $conn): validates inputs, hashes password with PASSWORD_DEFAULT, inserts user, handles duplicates (409), returns created user (without hash).
  - deactivateUser(int $id, mysqli $conn): `is_active = 0`.
  - reactivateUser(int $id, mysqli $conn): `is_active = 1`.
  - resetPassword(int $id, string $newPassword, mysqli $conn): hashes new password and updates.


### Endpoints (root)

Authentication
- `login.php` (POST JSON: { name, password }):
  - Creates a secure session on success and returns `{ success: true, redirectUrl: "portal.html" }`.
  - Note: The file currently compares `$password` directly to `password_hash` in DB. For security, this should use `password_verify($password, $user['password_hash'])` (as in `AuthController::login`).

- `auth_check.php` (GET):
  - Uses `AuthController::checkAuth($conn)` and returns `{ authenticated: boolean, user... }` via `ApiResponse::success`.
  - File includes an older, duplicated section below; the first section (controller + ApiResponse) is the intended path.

Servers
- `getServers.php` (GET):
  - Returns an array of active servers (raw array, not wrapped in `{success: true}`). Uses `lib/db.php` helpers.

- `saveServer.php` (POST JSON: { battlemetricsId }):
  - Validates method/body, optionally enriches from BattleMetrics API (env key), upserts server, and returns `{success: true, server: {...}}`.

- `deleteServer.php` (POST JSON: { id }):
  - Soft-deletes server (`is_active = 0`), 404 if not found.

Announcements
- `getAnnouncements.php` (GET with optional `serverId`, `battlemetricsId`, `active=1`):
  - Builds dynamic WHERE clause; returns announcements with server_name and battlemetrics_id.

- `saveAnnouncement.php` (POST JSON: { message, severity?, serverId?, startsAt?, endsAt?, isActive? }):
  - Validates, normalizes datetimes, inserts, and returns full announcement object.

- `deleteAnnouncement.php` (POST JSON: { id }):
  - Soft-deletes and sets `ends_at = IFNULL(ends_at, NOW())`, 404 if not found.

Users
- `listUsers.php` (GET): Returns all users (active + inactive) without password hashes.
- `addUser.php` (POST JSON: { name, password, role? }):
  - Hashes password, enforces unique name (409 on duplicate), returns created user (no hash).
- `deactivateUser.php` (POST JSON: { id }): Sets `is_active=0`, 404 if not found.
- `reactivateUser.php` (POST JSON: { id }): Sets `is_active=1`, 404 if not found.
- `resetUserPassword.php` (POST JSON: { id, password }): Hashes and updates password, 404 if not found.

External/BattleMetrics Utilities
- `battlemetrics.php` (GET `?serverId=...`):
  - Secure server-side proxy to BattleMetrics API using `BATTLEMETRICS_API_KEY`. Returns raw API JSON with original status code.

- `cluster.php`:
  - Script to fetch a fixed set of BattleMetrics server IDs and write a local `servers.json` snapshot; also echoes JSON. Uses a hard-coded API key and IDs (dev/utility usage).


### Common Libraries (lib/)
- `lib/api_common.php`
  - json_response(), json_success(), json_error()
  - require_method('POST'|'GET'), parse_json_body(), normalize_datetime_local()

- `lib/auth.php` (stub)
  - require_auth(), require_role([...]) – session-based stubs for PoC.

- `lib/db.php`
  - get_db_conn(): includes `dbconnect.php`, sets utf8mb4.
  - fetch_active_servers($conn): creates table idempotently and returns active servers.

- `lib/battlemetrics_client.php`
  - Simple wrapper class using curl. getServerById($id) for direct consumption if desired.


### Database
Connection
- `dbconnect.php`: Centralized `mysqli` connection using configured host, db, user, pass. Sets charset utf8mb4. On failure, returns HTTP 500 with JSON `{"success": false, "error": "Database connection failed."}`

Schemas (created idempotently by controllers/endpoints as needed)
- `users`
  - id (PK, AI), name (UNIQUE), password_hash, role ('owner'|'admin'|'staff'), is_active (TINYINT), created_at, updated_at

- `servers`
  - id (PK, AI), battlemetrics_id (UNIQUE), display_name, game_title, region, is_active, sort_order, created_at, updated_at
  - Indexes: idx_is_active, idx_sort_order

- `announcements`
  - id (PK, AI), server_id (NULL=global), message (TEXT), severity ('info'|'success'|'warning'|'error'), starts_at (NULL ok), ends_at (NULL ok), is_active, created_at, updated_at
  - Indexes: idx_server_id, idx_is_active, idx_starts_at, idx_ends_at
  - FK: `server_id` → `servers(id)` ON DELETE SET NULL


### Responses
- Standardized via `lib/ApiResponse.php` for most endpoints:
  - Success: HTTP 200 `{"success": true, "data": {...}}`
  - Error: HTTP {4xx|5xx} `{"success": false, "message": "..."}`
- Exceptions (legacy compatibility):
  - `getServers.php`, `getAnnouncements.php`, `listUsers.php` return raw arrays (frontend expects top-level array).


### Security
- Passwords
  - Created/updated with `password_hash(..., PASSWORD_DEFAULT)`.
  - Should be verified with `password_verify()` on login (implemented in `AuthController`; see note in `login.php`).

- Sessions
  - HttpOnly cookie, SameSite=Lax, strict mode; regenerate session ID at login.
  - Role verified against DB per request (prevents stale/tampered session role).

- Input/Queries
  - Parameterized queries everywhere; input normalized/validated.

- Soft-delete pattern
  - `is_active = 0` used for servers, users, announcements.
  - Announcements also set `ends_at = NOW()` if not previously set when deleting.

- Error handling
  - Generic messages to avoid information leakage; debug details limited to safe contexts.


### BattleMetrics Integration
- Enrichment during `saveServer`: optional API lookup (8s timeout) via env var `BATTLEMETRICS_API_KEY`; falls back gracefully.
- `battlemetrics.php` provides a server-side proxy endpoint to avoid exposing API keys in client-side JS.
- `cluster.php` utility fetches multiple server snapshots and writes `servers.json`.


### Endpoint-to-Controller Mapping (current intent)
- Auth: `login.php`, `auth_check.php` → `AuthController`
- Servers: `getServers.php`, `saveServer.php`, `deleteServer.php` → `ServerController`
- Announcements: `getAnnouncements.php`, `saveAnnouncement.php`, `deleteAnnouncement.php` → `AnnouncementController`
- Users: `listUsers.php`, `addUser.php`, `deactivateUser.php`, `reactivateUser.php`, `resetUserPassword.php` → `UserController`

Notes:
- Some endpoints currently implement their own logic inline (e.g., `login.php`); refer to `lib/ARCHITECTURE.php` for the refactor pattern to centralize into controllers consistently.


### Configuration
- Environment
  - `BATTLEMETRICS_API_KEY`: Required for API enrichment and proxy endpoints in production. If missing, enrichment is skipped or a fallback token may be used in utility scripts.

- Database
  - `dbconnect.php` contains the DB credentials. For production, prefer environment variables and keep credentials private.


### Adding/Changing Functionality
1) Add/modify logic in the appropriate controller under `lib/*Controller.php`.
2) Ensure request validation, parameterized queries, and error handling conform to patterns above.
3) Expose via a thin endpoint (if needed) that loads the controller, parses inputs, calls the controller method, and returns a JSON response (ideally via `ApiResponse` unless legacy array is required).
4) If new tables/columns are needed, update the controller’s `ensureTableExists` logic and indexes.
5) For any authentication-sensitive changes, verify `auth_check.php` and session handling as needed.


### Known Inconsistencies To Address
- `login.php` compares the submitted password with the stored `password_hash` directly. It should use `password_verify($password, $user['password_hash'])` for correctness and security (as implemented in `AuthController::login`). Aligning the endpoint to call `AuthController::login` will resolve this.


### Quick Reference
- Controllers: `lib/*Controller.php`
- Helpers: `lib/ApiResponse.php`, `lib/api_common.php`, `lib/auth.php`, `lib/db.php`, `lib/battlemetrics_client.php`
- Endpoints: `*Server*.php`, `*Announcement*.php`, `*User*.php`, `login.php`, `auth_check.php`, `battlemetrics.php`
- DB Connection: `dbconnect.php`


