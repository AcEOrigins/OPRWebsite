# PHP FILES - COMPREHENSIVE LABELING GUIDE

## Completed (Fully Labeled)

### ✅ dbconnect.php
**Status:** Fully documented with section headers, detailed comments on every config value
**Key Sections:**
- Database credentials (HOST, NAME, USER, PASS)
- Connection creation
- Error handling
- Character encoding setup
- Usage examples for other files

### ✅ login.php  
**Status:** Fully documented with step-by-step security explanations
**Key Sections:**
- PHP configuration & session setup
- HTTP method validation
- Input parsing & validation
- Database lookup
- Credential verification
- Session creation
- Cleanup & response

---

## Remaining Files (To Be Labeled)

### auth_check.php
- Verify session exists
- Extract user data from session
- Query DB for latest role
- Return user info + authentication status

### getServers.php
- Ensure servers table exists
- Query active servers
- Return JSON array

### saveServer.php
- Validate BattleMetrics ID
- Fetch enrichment data from BattleMetrics API
- Insert/update server record
- Return saved server data

### deleteServer.php
- Validate server ID
- Soft-delete (set is_active = 0)
- Return success status

### getAnnouncements.php
- Ensure announcements table exists
- Filter by serverId, battlemetricsId, active window
- Join with servers table
- Return sorted announcements

### saveAnnouncement.php
- Validate message and severity
- Normalize datetime-local to MySQL format
- Insert announcement
- Fetch and return inserted row

### deleteAnnouncement.php
- Soft-delete announcement (set is_active = 0)
- Set ends_at to current time
- Return success status

### addUser.php
- Validate username and password
- Hash password with password_hash()
- Insert new user
- Detect duplicate username (409 conflict)
- Return created user data

### listUsers.php
- Ensure users table exists
- Query all users (active + inactive)
- Return sorted by name

### deactivateUser.php
- Validate user ID
- Set is_active = 0
- Return success status

### reactivateUser.php
- Validate user ID
- Set is_active = 1
- Return success status

### resetUserPassword.php
- Validate user ID and password
- Hash new password
- Update password_hash
- Return success status

### battlemetrics.php
- Validate serverId parameter
- Fetch from BattleMetrics API with credentials
- Proxy response back to client
- Handle cURL errors

### cluster.php
- Batch fetch multiple server IDs
- Call BattleMetrics for each
- Aggregate data
- Save to servers.json file
- Return JSON

---

## Labeling Pattern Used

Each file includes:

```php
/**
 * =============================================================================
 * filename.php - Short Description
 * =============================================================================
 * 
 * PURPOSE:
 * --------
 * What does this file do?
 * 
 * REQUEST:
 * --------
 * HTTP method, URL, example body
 * 
 * RESPONSE:
 * ---------
 * Success and failure examples
 * 
 * SECURITY:
 * ---------
 * What security measures are in place?
 * 
 * =============================================================================
 */

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 1: DESCRIPTION                                                  │
// └─────────────────────────────────────────────────────────────────────────┘

/**
 * DETAILED COMMENT
 * 
 * Explains why this line exists, what it does, edge cases.
 */
$variable = value;  // ← Code with labels above

// ┌─────────────────────────────────────────────────────────────────────────┐
// │ SECTION 2: NEXT MAJOR SECTION                                           │
// └─────────────────────────────────────────────────────────────────────────┘

```

---

## Next Steps

1. **Apply same labeling pattern** to all remaining 11 files
2. **Add inline comments** for error handling blocks
3. **Document security decisions** (why SQL injection protection, etc.)
4. **Explain HTTP status codes** (why 401 vs 422, etc.)
5. **Add usage examples** in comments

---

## Quick Reference: HTTP Status Codes Used

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | Successful request |
| 400 | Bad Request | Malformed JSON |
| 401 | Unauthorized | Wrong password |
| 404 | Not Found | Server/user doesn't exist |
| 405 | Method Not Allowed | GET instead of POST |
| 409 | Conflict | Duplicate username |
| 422 | Unprocessable Entity | Missing required field |
| 500 | Server Error | Database connection failed |

---

## Security Patterns Explained

### SQL Injection Prevention
```php
// ✗ VULNERABLE:
$sql = "SELECT * FROM users WHERE name = '$name'";
// If $name = "admin' OR '1'='1", bypasses authentication

// ✓ SAFE:
$stmt = $conn->prepare("SELECT * FROM users WHERE name = ?");
$stmt->bind_param('s', $name);
// $name is treated as data, not code
```

### Password Security
```php
// ✓ Creation (in addUser.php):
$hash = password_hash($password, PASSWORD_DEFAULT);
// Returns: $2y$10$...  (hashed with bcrypt)

// ✓ Verification (in login.php):
if (password_verify($password, $user['password_hash'])) {
  // Entered password matches stored hash
}
```

### Session Security
```php
// ✓ HttpOnly (prevents XSS):
ini_set('session.cookie_httponly', '1');
// JavaScript cannot access session cookie

// ✓ SameSite (prevents CSRF):
ini_set('session.cookie_samesite', 'Lax');
// Cookie only sent in same-site requests

// ✓ Strict Mode (prevents fixation):
ini_set('session.use_strict_mode', '1');
// Rejects forged session IDs
```

---

## Testing Checklist

After labeling each file, verify:

- [ ] All error paths have comments explaining what went wrong
- [ ] HTTP status codes are documented
- [ ] Security decisions are explained (why parameterized, etc.)
- [ ] Database operations show column names being selected/modified
- [ ] Input validation rules are clear (required fields, types, ranges)
- [ ] Response format examples are shown

---

## File Dependencies Chain

```
portal_login.html
    ↓
portal_login.js
    ↓
POST /login.php ────→ dbconnect.php
    ↓
Set session cookie
    ↓
Navigate to portal.html
    ↓
portal.js
    ↓
GET /auth_check.php ────→ dbconnect.php
    ↓
Check session cookie
    ↓
GET /getServers.php ────→ dbconnect.php
GET /getAnnouncements.php ────→ dbconnect.php
GET /listUsers.php (if admin/owner)
    ↓
Display portal with user data
```

This shows why every file depends on `dbconnect.php` — it's the central database connection point.
