# auth_check.php Explanation

## Overview
`auth_check.php` is a **session verification endpoint** that checks if a user is currently authenticated and returns their session data. It's called by the frontend (JavaScript) to determine:
- Is the user logged in?
- If yes, who are they and what is their role?

**File Purpose:** Verify current session and provide user information for the admin portal (`portal.html`).

---

## Request/Response Contract

### Request
```
GET /auth_check.php
(no request body needed)
```

The browser automatically sends the session cookie (if one exists).

### Response (Authenticated)
```json
HTTP/1.1 200 OK
Content-Type: application/json

{
  "success": true,
  "authenticated": true,
  "userId": 1,
  "userName": "admin",
  "role": "admin"
}
```

### Response (Not Authenticated)
```json
HTTP/1.1 200 OK
Content-Type: application/json

{
  "success": true,
  "authenticated": false
}
```

---

## Code Breakdown

### 1. Security & Session Configuration (Lines 1-10)

```php
<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

session_start();
header('Content-Type: application/json');
```

| Setting | Purpose |
|---------|---------|
| `declare(strict_types=1)` | Enforce type safety in PHP |
| `session.cookie_httponly` | Prevent JavaScript theft of session cookie via XSS |
| `session.cookie_samesite` | Prevent CSRF attacks (cookie only sent same-origin) |
| `session.use_strict_mode` | Reject invalid/forged session IDs |
| `session_start()` | Restore session from cookie if it exists |
| `header('Content-Type: application/json')` | Tell client this is JSON response |

**Note:** These are the same session settings as `login.php` — consistent security across both endpoints.

---

### 2. Quick Session Check (Line 12)

```php
$isAuthenticated = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
```

**What it checks:**
- Does `$_SESSION['user_id']` exist?
- Is it greater than 0?

**Why both?**
- `isset()` checks key exists (was set by `login.php`)
- `(int)... > 0` ensures it's a valid positive integer (not 0, null, or negative)

**Result:** Boolean `true` or `false`

---

### 3. Return Early if Not Authenticated (Lines 14-19)

```php
if (!$isAuthenticated) {
	echo json_encode([
		'success' => true,
		'authenticated' => false
	]);
	exit;
}
```

| Part | Explanation |
|------|-------------|
| `if (!$isAuthenticated)` | If user is NOT logged in... |
| `'success' => true` | Request succeeded (no errors) |
| `'authenticated' => false` | But user is not authenticated |
| `exit` | Stop processing here, don't query database |

**Why stop here?**
- If no session exists, we know they're not logged in
- No need to query the database
- Return immediately (faster, less load)

**Frontend receives:** `{success: true, authenticated: false}` → Portal should redirect to login page.

---

### 4. Extract Session Data (Lines 21-24)

```php
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = (string)($_SESSION['user_name'] ?? '');
$role = 'admin';
```

| Line | What It Does |
|------|--------------|
| `(int)($_SESSION['user_id'] ?? 0)` | Get user ID from session, default to 0 if missing, cast to int |
| `(string)($_SESSION['user_name'] ?? '')` | Get username from session, default to empty string if missing, cast to string |
| `$role = 'admin'` | Set default role to 'admin' (will be overwritten if database has different role) |

**Null coalescing (`??`):** Returns left side if set, right side if not.

Example: `$_SESSION['user_id'] ?? 0` → If `user_id` exists, use it; otherwise use `0`.

---

### 5. Query Database for Latest Role (Lines 26-37)

```php
require_once __DIR__ . '/dbconnect.php';

$stmt = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
if ($stmt) {
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$stmt->close();
	if ($row && isset($row['role'])) {
		$role = (string)$row['role'];
	}
}

$conn->close();
```

**Why query the database?**
- Session data is stored on the client (cookie) — it could be stale or tampered
- Always verify the role from the database (source of truth)
- User's role might have changed since they logged in

**Process:**

| Step | Explanation |
|------|-------------|
| `require_once __DIR__ . '/dbconnect.php'` | Load database connection |
| `$conn->prepare(...)` | Prepare parameterized query (safe from SQL injection) |
| `bind_param('i', $userId)` | Bind `$userId` as integer to `?` placeholder |
| `$stmt->execute()` | Run the query |
| `get_result()` | Get result set from query |
| `fetch_assoc()` | Convert result to array: `['role' => 'admin']` |
| `if ($row && isset($row['role']))` | Safely check if role exists in result |
| `$role = (string)$row['role']` | Update role from database |

**Error Handling:**
- `if ($stmt)` — Only execute if prepare succeeded (connection okay)
- `$res ? $res->fetch_assoc() : null` — Only fetch if result exists
- If any step fails, `$role` stays as 'admin' (default)

---

### 6. Close Connection & Return Data (Lines 39-47)

```php
$conn->close();

echo json_encode([
	'success' => true,
	'authenticated' => true,
	'userId' => $userId,
	'userName' => $userName,
	'role' => $role
]);
```

| Field | Value | Used For |
|-------|-------|----------|
| `success` | `true` | Indicates request succeeded (no errors) |
| `authenticated` | `true` | User is logged in |
| `userId` | `1` | Internal user ID for database queries |
| `userName` | `"admin"` | Display in welcome message ("Welcome admin") |
| `role` | `"owner"` \| `"admin"` \| `"staff"` | Control UI visibility in portal |

---

## Call Flow in Frontend

### From `portal.js` (on page load):

```javascript
// Step 1: Check authentication
const auth = await API.checkAuth();

// Step 2: Examine response
if (!auth.authenticated) {
  // User not logged in → redirect to login page
  window.location.replace('portal_login.html');
  return;
}

// Step 3: Use session data
State.setCurrentUser(auth);
Auth.insertWelcome(auth);           // "Welcome admin"
Auth.applyRoleVisibility(auth.role); // Show/hide features based on role

// Step 4: Show portal
portalRoot.style.display = '';
```

---

## Session Security Flow

```
┌──────────────────────────────────┐
│ Browser makes request to         │
│ auth_check.php                   │
└──────────────┬───────────────────┘
               │
      ┌────────┴────────┐
      │                 │
   Session        No session
   exists?        (new browser)
      │                 │
      ↓                 ↓
   Restore       Return
   session       authenticated: false
   from cookie        │
      │               ↓
      ↓          Redirect to
   Check if      portal_login.html
   $user_id > 0
      │
   ┌──┴──┐
   ✓     ✗
   │     │
   ↓     ↓
Query OK Error/No session
DB for
role      Return
   │    authenticated: false
   ↓
Return
authenticated: true
+ user data
+ role from DB
```

---

## What auth_check.php Does

✅ Checks if session exists
✅ Verifies `user_id` is valid (> 0)
✅ Extracts `userId`, `userName` from session
✅ Queries database to get latest `role` (source of truth)
✅ Returns JSON with authentication status
✅ Returns user data if authenticated
✅ Uses secure session config (HttpOnly, SameSite)

---

## What auth_check.php Does NOT Do

❌ Does **not** create sessions (that's `login.php`)
❌ Does **not** delete sessions (that's logout/session_destroy)
❌ Does **not** modify user data
❌ Does **not** verify passwords
❌ Does **not** enforce permissions (portal.js does that client-side)

---

## Role Handling

The `role` field determines UI visibility in the portal:

| Role | Can Access |
|------|-----------|
| `owner` | All tabs (Dashboard, Server Control, Manage Site, Manage Access, BattleMetrics) |
| `admin` | All except Manage Access (can't create/delete/reset users) |
| `staff` | Only Manage Site → Server Announcements (limited to announcements) |

**Example:** When `portal.js` receives `role: "staff"`, it hides all tabs except announcements.

---

## Why Always Query the Database?

Consider this scenario:

1. **Day 1:** User logs in as `admin`
   - Session created with `role: 'admin'`
   - Cookie stored in browser

2. **Day 2:** User is logged in, browser still has old cookie
   - `auth_check.php` is called
   - Session is restored from cookie
   - But what if admin changed their role to `staff`?

**Without DB query:**
- `$_SESSION['role']` would still be `'admin'` (stale)
- User would have access they shouldn't

**With DB query:**
- Always fetches `role` from `users` table
- User sees their current permissions
- Role changes take effect immediately

---

## Testing with curl

```bash
# Test as unauthenticated user
curl -X GET http://localhost/auth_check.php

# Response:
# {"success":true,"authenticated":false}

# Test as authenticated user (need cookie from login)
curl -X GET http://localhost/auth_check.php \
  -b "PHPSESSID=abc123xyz..."

# Response:
# {"success":true,"authenticated":true,"userId":1,"userName":"admin","role":"admin"}
```

---

## Common Issues & Debugging

### Issue: Always returns `authenticated: false`
**Check:**
1. Did user actually log in? (POST to `login.php` first)
2. Is session being saved? PHP session path writable
3. Browser has cookies enabled
4. Not using incognito/private mode (sessions may not persist)

**Debug:**
```php
// Add to auth_check.php temporarily
error_log('Session data: ' . print_r($_SESSION, true));
```

### Issue: Role stays as 'admin' even after change
**Check:**
1. Database `users` table has correct role
2. Verify: `SELECT role FROM users WHERE id = 1;`
3. Check if database query is failing silently (error logs)

**Debug:**
```php
// Add temporary logging
error_log('User ID: ' . $userId);
error_log('Query result: ' . ($row ? $row['role'] : 'NULL'));
```

### Issue: CORS error when calling from different origin
**This won't happen** because:
- `auth_check.php` doesn't set CORS headers
- Frontend must be same-origin (same domain as backend)
- `XMLHttpRequest` / `fetch` in browser respects same-origin policy

---

## Relationship to Other Files

| File | Relationship |
|------|--------------|
| `login.php` | Creates session; `auth_check.php` verifies it |
| `portal.html` | Calls `auth_check.php` on page load (via `portal.js`) |
| `portal.js` | Uses response to show/hide UI based on role |
| `dbconnect.php` | Provides database connection for role query |
| `users` table | Source of truth for roles |

---

## Summary

`auth_check.php` is a **lightweight verification endpoint** that:

1. ✅ Checks if a session exists
2. ✅ Validates `user_id` > 0
3. ✅ Extracts user info from session
4. ✅ Queries database for current role (always fresh)
5. ✅ Returns JSON with auth status + user data
6. ✅ Uses secure session settings (HttpOnly, SameSite)

It's called by `portal.js` on every portal page load to determine:
- Is user logged in? → If no, redirect to login
- What is their role? → If staff, show only announcements
- Who are they? → Display welcome message

The endpoint is **read-only** — it checks existing data without modifying anything. It's the **entry point** for the admin portal.
