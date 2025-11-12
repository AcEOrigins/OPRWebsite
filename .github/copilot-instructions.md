## Copilot / AI contributor quick instructions

Purpose: Make codebase-specific patterns, workflows, and guardrails obvious so an AI coding agent can be productive immediately.

- Repo snapshot: PHP backend + static frontend. Key files: `README.md`, `dbconnect.php`, `battlemetrics.php`, `cluster.php`, `*.php` API endpoints, `index.html`, `portal.html`, `portal_login.html`, and `js/*` (notably `js/battlemetrics.js` and `js/portal.js`).

What to read first
- `README.md` — high-level architecture, DB schema, and setup steps (contains most of the contextual info used below).
- `dbconnect.php` — contains DB connection variables and is used by every endpoint. Don't commit credentials.
- `battlemetrics.php` — server-side BattleMetrics proxy (API key lives here). Client JS calls this file, not the external API.

High-level architecture & important boundaries
- Frontend: static HTML/JS in root and `js/`. `index.html` (public) and `portal.html` (admin) are the main pages. Client code talks to PHP endpoints.
- Backend: single-process PHP endpoints (one file per endpoint, e.g. `getServers.php`, `saveServer.php`, `getAnnouncements.php`, `login.php`, `auth_check.php`). Each endpoint returns JSON with the common shape: `{"success": true|false, ...}`.
- External integration: BattleMetrics API is proxied via `battlemetrics.php` to keep the API key server-side. `cluster.php` aggregates multiple servers and writes `servers.json` (requires write permission).

Project-specific patterns & examples
- Soft-delete: records are deactivated via `is_active` instead of being removed. Example: `deleteServer.php` and `deleteAnnouncement.php` set `is_active = 0`.
- API contract: JSON + HTTP codes. Success responses include `success: true`. Error responses include `success: false` and use standard codes (400, 401, 405, 422, 500). Follow this shape when adding endpoints.
- Role strings: use `owner`, `admin`, `staff`. Role-based feature toggles are implemented in `js/portal.js` (UI visibility) and enforced server-side via `auth_check.php` session values.
- Passwords: hashed with PHP `password_hash()` on creation and `password_verify()` on login (see `addUser.php` / `login.php`).
- Announcements datetime: frontend sends `datetime-local`; backend converts to MySQL `DATETIME` (see `saveAnnouncement.php`). Preserve that format when interacting with announcement endpoints.

Developer setup & common workflows (discoverable in README)
- Configure DB: update `dbconnect.php` with credentials before running. Prefer using environment variables outside of commits.
- Configure BattleMetrics: set API key in `battlemetrics.php` or via an environment variable referenced there. Client code expects `battlemetrics.php?serverId=<id>`.
- Apache: `.htaccess` enforces HTTPS, headers, compression, and requires `AllowOverride All`. Make sure `mod_rewrite`, `mod_headers`, `mod_deflate`, and `mod_expires` are enabled.
- File permissions: `cluster.php` writes `servers.json` — ensure PHP has write access to the repo folder or adjust the path.

Debugging & quick checks
- If portal redirects or auth fails: open `auth_check.php` in browser (it returns JSON). Check session cookies and PHP session config.
- If servers/cards are missing: call `getServers.php` and check `js/battlemetrics.js` fetches `battlemetrics.php`. Ensure the API key is valid and `battlemetrics.php` isn't returning 4xx/5xx.
- Check browser console for client-side errors and network tab for failing API calls.
- Check PHP/Apache error logs for stack traces. Many endpoints return JSON; inspect the raw response body on failure.

Safe editing rules for AI agents
- Never hard-code credentials or API keys into committed files. If a fix requires a secret, leave a TODO and instructions to add via environment variables or local config.
- Prefer non-destructive changes: follow the `is_active` pattern instead of deleting rows. Use transactions for multi-step DB updates.
- Preserve the JSON response contract. New endpoints should return `{"success": true|false, ...}` with appropriate HTTP status codes.
- Small, focused PRs: this repo is not a monorepo—change only the files necessary and keep frontend and backend changes separated when possible.

Files you will frequently edit / inspect
- `dbconnect.php` — DB host/user/pass (sensitive)
- `battlemetrics.php` — BattleMetrics proxy, API key handling
- `getServers.php`, `saveServer.php`, `deleteServer.php`
- `getAnnouncements.php`, `saveAnnouncement.php`, `deleteAnnouncement.php`
- `login.php`, `auth_check.php`, `addUser.php`, `listUsers.php`
- `cluster.php` — utility that writes `servers.json`
- `js/battlemetrics.js`, `js/portal.js`, `js/main.js` — client behavior and API usage patterns

If something is unclear, ask the repo owner for:
- Intended deployment environment (PHP version, Apache/PHP-FPM, document root path)
- Whether storing configs (DB, API keys) in environment variables is acceptable and where they prefer to keep them

End of instructions — ask for clarification before making changes that touch `dbconnect.php` or other secrets.
