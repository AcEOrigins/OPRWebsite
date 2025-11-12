# login.php Explanation

## Overview
`login.php` is a pure **authentication endpoint** that validates user credentials and creates a secure session. It accepts POST requests with username/password, verifies them against the database, and returns a JSON response with success/failure status.

**File Purpose:** Handle user login and session creation for the admin portal (`portal.html`).

---

## Request/Response Contract

### Request
```json
POST /login.php
Content-Type: application/json

{
  "name": "admin",
  "password": "mypassword123"
}
```

### Response (Success)
```json
HTTP/1.1 200 OK
Content-Type: application/json

{
  "success": true,
  "redirectUrl": "portal.html"
}
```

### Response (Failure)
```json
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{
  "success": false,
  "message": "Invalid credentials. Please try again."
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
| `declare(strict_types=1)` | Enforce type declarations; improves code safety |
| `session.cookie_httponly` | Prevent JavaScript from accessing session cookie (blocks XSS theft) |
| `session.cookie_samesite` | Only send cookie to same site (blocks CSRF) |
| `session.use_strict_mode` | Reject uninitialized session IDs (security hardening) |
| `session_start()` | Initialize the session before any output |
| `header('Content-Type: application/json')` | Tell client response is JSON |

---

### 2. HTTP Method Validation (Lines 12-16)

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
	http_response_code(405);
	exit;
}
```

- **Check:** Only allow POST requests (login is a state-changing operation)
- **Response:** HTTP 405 Method Not Allowed if GET/PUT/DELETE attempted
- **Why:** GET requests shouldn't authenticate (avoid accidental logins in URL history)

---

### 3. Parse & Validate JSON Payload (Lines 18-32)

```php
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
	exit;
}

$name = isset($payload['name']) ? trim((string)$payload['name']) : '';
$password = isset($payload['password']) ? (string)$payload['password'] : '';

if ($name === '' || $password === '') {
	http_response_code(422);
	echo json_encode(['success' => false, 'message' => 'Name and password are required.']);
	exit;
}
```

| Step | What It Does |
|------|--------------|
| Read raw input | Get JSON from request body |
| Decode to array | Convert JSON string → PHP array |
| Check not empty | Ensure valid JSON was sent (HTTP 400 if malformed) |
| Extract fields | Get `name` and `password` from payload |
| Trim & cast | Remove whitespace, ensure string types |
| Validate required | If either is empty, reject (HTTP 422 Unprocessable Entity) |

**HTTP Status Codes Used:**
- `400` = Malformed JSON
- `422` = Missing required fields

---

### 4. Connect to Database (Line 34)

```php
require_once __DIR__ . '/dbconnect.php';
```

- Loads `dbconnect.php` which creates `$conn` (MySQL connection)
- Uses `require_once` to prevent multiple includes if file is loaded again
- Exits with error message if database fails (handled by `dbconnect.php`)

---

### 5. Prepare & Execute Parameterized Query (Lines 36-47)

```php
$stmt = $conn->prepare("SELECT id, name, password_hash, role, is_active FROM users WHERE name = ? LIMIT 1");

if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Unable to process request.']);
	$conn->close();
	exit;
}

$stmt->bind_param('s', $name);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();
```

| Part | Explanation |
|------|-------------|
| `prepare()` | Precompile SQL with `?` placeholder (safe from SQL injection) |
| `bind_param('s', $name)` | Bind `$name` as **string** to the `?` placeholder |
| `execute()` | Run the query with bound value |
| `get_result()` | Get result set (MySQL native driver) |
| `fetch_assoc()` | Convert row to associative array: `['id' => 1, 'name' => 'admin', ...]` |
| `$stmt->close()` | Free prepared statement resources |

**What's Selected:**
- `id` — User's database ID (stored in session)
- `name` — Username (for display/logging)
- `password_hash` — Hashed password (compared with `password_verify()`)
- `role` — User's role: `owner`, `admin`, or `staff` (stored in session for authorization)
- `is_active` — Boolean flag; inactive accounts cannot log in

---

### 6. Verify Credentials (Lines 49-55)

```php
if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Invalid credentials. Please try again.']);
	$conn->close();
	exit;
}
```

This condition rejects login if **any** of these are true:

| Condition | Meaning |
|-----------|---------|
| `!$user` | No user found with that name |
| `(int)$user['is_active'] !== 1` | User exists but account is deactivated |
| `!password_verify(...)` | Password doesn't match hash |

**Why combined?** Prevents attacker from knowing which check failed (e.g., "user not found" vs "wrong password" would leak valid usernames).

**Response:** HTTP 401 Unauthorized, generic error message.

---

### 7. Create Secure Session (Lines 57-61)

```php
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];
```

| Line | Purpose |
|------|---------|
| `session_regenerate_id(true)` | Create new session ID, delete old one (prevents session fixation attacks) |
| `$_SESSION['user_id']` | Store user ID for `auth_check.php` to retrieve |
| `$_SESSION['user_name']` | Store username for display ("Welcome admin") |
| `$_SESSION['user_role']` | Store role for role-based UI visibility in portal |

These values persist across requests (via secure HttpOnly cookie).

---

### 8. Close Database & Return Success (Lines 63-66)

```php
$conn->close();

echo json_encode(['success' => true, 'redirectUrl' => 'portal.html']);
```

- Close MySQL connection to free resources
- Return HTTP 200 + JSON with redirect URL
- Browser/JavaScript receives `redirectUrl` and navigates to `portal.html`

---

## Security Features

| Feature | Benefit |
|---------|---------|
| **Parameterized queries** | Prevents SQL injection |
| **HttpOnly cookies** | Prevents JavaScript from stealing session token |
| **SameSite=Lax** | Prevents CSRF attacks |
| **Session regeneration** | Prevents session fixation attacks |
| **password_verify()** | Safe comparison of password hashes (timing-safe) |
| **Generic error messages** | Don't leak whether username exists |
| **POST only** | Prevent accidental login from URL history/prefetch |
| **Strict mode** | Reject forged/invalid session IDs |

---

## Client-Side Integration (From `portal_login.js`)

```javascript
// User submits login form
const response = await fetch('login.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ name: 'admin', password: 'password123' })
});

const result = await response.json();

if (result.success) {
  // Session cookie automatically sent with future requests
  window.location.replace(result.redirectUrl); // → portal.html
} else {
  // Show error message
  console.error(result.message);
}
```

---

## Flow Diagram

```
┌─────────────────────────────┐
│ User submits login form      │
│ (portal_login.html)          │
└──────────────┬──────────────┘
               │
               ↓
┌─────────────────────────────┐
│ POST /login.php             │
│ {name, password}            │
└──────────────┬──────────────┘
               │
               ↓
        ✓ POST request?
        ✓ Valid JSON?
        ✓ Required fields?
               │
               ↓
        Query DB for user
               │
        ┌──────┴──────┐
        │             │
    User found?    Not found?
        │             │
        ↓             ↓
   Check:        401 Unauthorized
   - Active?     (Invalid credentials)
   - Password?   
        │
   ┌────┴────┐
   ✓         ✗
   │         │
   ↓         ↓
 Create   401 Unauthorized
 Session  (Invalid credentials)
   │
   ↓
200 OK
{success: true, redirectUrl: 'portal.html'}
   │
   ↓
Browser receives cookie + redirect
   │
   ↓
Navigate to portal.html
   │
   ↓
portal.html calls auth_check.php
(verified by cookie)
```

---

## What login.php Does NOT Do

- ❌ Does **not** create user accounts (use `addUser.php` for that)
- ❌ Does **not** reset passwords (use `resetUserPassword.php` for that)
- ❌ Does **not** manage user roles (use database directly or admin panel)
- ❌ Does **not** log failed attempts (consider adding audit logging)
- ❌ Does **not** implement rate limiting (consider adding for brute-force protection)

---

## Common Issues & Debugging

### Issue: "Invalid credentials" even with correct password
**Check:**
1. User exists in database: `SELECT * FROM users WHERE name = 'admin';`
2. User is active: `is_active` column = `1`
3. Password was hashed correctly: `password_hash('password', PASSWORD_DEFAULT)`

### Issue: Session not persisting
**Check:**
1. Browser has cookies enabled
2. PHP session storage path is writable (`/tmp` or `sys_get_temp_dir()`)
3. `.htaccess` isn't blocking `.PHPSESSID` cookies
4. Server isn't setting `Set-Cookie: SameSite=Strict` (too restrictive)

### Issue: "Unable to process request" (HTTP 500)
**Check:**
1. Database connection is working (test with `auth_check.php`)
2. `users` table exists
3. MySQL user has SELECT permissions

---

## Testing with curl

```bash
# Successful login
curl -X POST http://localhost/login.php \
  -H "Content-Type: application/json" \
  -d '{"name":"admin","password":"mypassword"}'

# Expected response:
# {"success":true,"redirectUrl":"portal.html"}

# Failed login
curl -X POST http://localhost/login.php \
  -H "Content-Type: application/json" \
  -d '{"name":"admin","password":"wrongpassword"}'

# Expected response:
# {"success":false,"message":"Invalid credentials. Please try again."}
```

---

## Summary

`login.php` is a **single-responsibility endpoint** that:

1. ✅ Validates request format (POST, JSON, required fields)
2. ✅ Queries database safely (parameterized query)
3. ✅ Verifies user exists, is active, and password matches
4. ✅ Creates secure session with role info
5. ✅ Returns JSON response with redirect URL

It does **not** handle table creation, user management, or business logic — it exists purely to authenticate and create a session.
