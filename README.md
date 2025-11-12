# OPR Fargo Website - Documentation

## Overview

This is a full-stack web application for managing and displaying OPR Fargo gaming server information. The site integrates with the BattleMetrics API to show real-time server status, player counts, and server details. It includes a public-facing homepage and an authenticated admin portal for managing servers, announcements, and user access.

---

## Project Structure

```
OPRWebsite/
├── assets/              # Images and static assets
├── css/                 # Stylesheets
├── js/                  # JavaScript files
├── *.html               # Frontend pages
├── *.php                # Backend API endpoints
├── dbconnect.php        # Database configuration
└── .htaccess            # Apache server configuration
```

---

## Frontend Files

### `index.html`
**Purpose:** Public-facing homepage

**Features:**
- Hero slideshow with auto-rotation (5-second intervals)
- Server information section with platform icons (Xbox, PlayStation, PC)
- Dynamic server cards displaying real-time BattleMetrics data
- Footer with social media links and Discord integration
- Floating Discord button for quick access

**JavaScript Dependencies:**
- `js/main.js` - Slideshow and server rendering
- `js/battlemetrics.js` - BattleMetrics API integration
- `js/Watermark.js` - Greyline Studio watermark component

---

### `portal.html`
**Purpose:** Admin portal dashboard (requires authentication)

**Features:**
- Side navigation with expandable sub-menus
- Tab-based content management:
  - **Manage Site:** Slideshow, Server Info, Our Servers, Footer, Navigation, Server Announcements
  - **BattleMetrics:** Server analytics and monitoring
  - **Manage Access:** User account management (owner/admin only)
- Real-time authentication check on page load
- Role-based access control (owner/admin/staff)
- Modal dialogs for adding servers and announcements

**Authentication Flow:**
1. Page loads → Shows loading screen
2. JavaScript checks `auth_check.php`
3. If not authenticated → Redirects to `portal_login.html`
4. If authenticated → Shows portal content based on user role

**JavaScript Dependencies:**
- `js/portal.js` - Portal functionality and navigation
- `js/battlemetrics.js` - Server card rendering

---

### `portal_login.html`
**Purpose:** Admin login page

**Features:**
- Centered login modal with OPR branding
- Username and password input fields
- Error message display
- Loading animation during authentication
- Auto-redirect if already logged in

**Authentication Flow:**
1. User enters credentials
2. POST request to `login.php` with JSON payload
3. On success → Redirects to `portal.html`
4. On failure → Displays error message

**JavaScript Dependencies:**
- `js/portal_login.js` - Login form handling

---

## Backend API Files

### Authentication

#### `auth_check.php`
**Purpose:** Verifies if a user session is authenticated

**Method:** GET

**Response:**
```json
{
  "success": true,
  "authenticated": true|false,
  "userId": 1,
  "userName": "admin",
  "role": "admin"
}
```

**Session Configuration:**
- HttpOnly cookies
- SameSite=Lax
- Strict mode enabled

---

#### `login.php`
**Purpose:** Authenticates users and creates session

**Method:** POST

**Request Body:**
```json
{
  "name": "username",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "redirectUrl": "portal.html"
}
```

**Process:**
1. Validates username and password
2. Checks user exists and is active
3. Verifies password hash
4. Creates PHP session with `user_id` and `user_name`
5. Regenerates session ID for security
6. Returns success with redirect URL

**Error Responses:**
- `405` - Method not allowed
- `400` - Invalid request payload
- `422` - Missing credentials
- `401` - Invalid credentials
- `500` - Server error

---

### Server Management

#### `getServers.php`
**Purpose:** Retrieves all active servers from database

**Method:** GET

**Response:**
```json
[
  {
    "id": 1,
    "battlemetrics_id": "12345678",
    "display_name": "OPR Server 1",
    "game_title": "Arma Reforger",
    "region": "US-East"
  }
]
```

**Features:**
- Auto-creates `servers` table if missing
- Returns only active servers (`is_active = 1`)
- Ordered by `sort_order` then `id`

---

#### `saveServer.php`
**Purpose:** Adds or updates a server record

**Method:** POST

**Request Body:**
```json
{
  "battlemetricsId": "12345678"
}
```

**Response:**
```json
{
  "success": true,
  "server": {
    "id": 1,
    "battlemetrics_id": "12345678",
    "display_name": "OPR Server 1",
    "game_title": "Arma Reforger",
    "region": "US-East"
  }
}
```

**Process:**
1. Validates BattleMetrics ID
2. Fetches server details from BattleMetrics API
3. Extracts: display name, game title, region
4. Inserts or updates database record (ON DUPLICATE KEY UPDATE)
5. Returns saved server data

**Features:**
- Auto-enriches data from BattleMetrics API
- Falls back to default name if API fails
- Sets server as active automatically

---

#### `deleteServer.php`
**Purpose:** Soft-deletes (deactivates) a server

**Method:** POST

**Request Body:**
```json
{
  "id": 1
}
```

**Response:**
```json
{
  "success": true
}
```

**Process:**
- Sets `is_active = 0` instead of deleting record
- Preserves data for historical purposes

---

### Announcement Management

#### `getAnnouncements.php`
**Purpose:** Retrieves announcements with optional filtering

**Method:** GET

**Query Parameters:**
- `serverId` (int) - Filter by server ID
- `battlemetricsId` (string) - Filter by BattleMetrics ID
- `active` (int) - 1 = active only, 0 = all

**Response:**
```json
[
  {
    "id": 1,
    "message": "Server maintenance scheduled",
    "severity": "warning",
    "starts_at": "2024-01-15 10:00:00",
    "ends_at": "2024-01-15 12:00:00",
    "is_active": 1,
    "server_id": 1,
    "server_name": "OPR Server 1",
    "battlemetrics_id": "12345678"
  }
]
```

**Features:**
- Auto-creates `announcements` table if missing
- Supports filtering by server or global announcements
- Active filter checks time windows (`starts_at`/`ends_at`)
- Returns server name via LEFT JOIN

---

#### `saveAnnouncement.php`
**Purpose:** Creates a new announcement

**Method:** POST

**Request Body:**
```json
{
  "message": "Server maintenance scheduled",
  "severity": "warning",
  "serverId": 1,
  "startsAt": "2024-01-15T10:00",
  "endsAt": "2024-01-15T12:00",
  "isActive": 1
}
```

**Response:**
```json
{
  "success": true,
  "announcement": { ... },
  "id": 1
}
```

**Severity Options:**
- `info` (default)
- `success`
- `warning`
- `error`

**Features:**
- Converts datetime-local format to MySQL DATETIME
- Validates severity against allowed values
- `serverId: null` = global announcement (all servers)

---

#### `deleteAnnouncement.php`
**Purpose:** Soft-deletes (deactivates) an announcement

**Method:** POST

**Request Body:**
```json
{
  "id": 1
}
```

**Response:**
```json
{
  "success": true
}
```

**Process:**
- Sets `is_active = 0`
- Sets `ends_at = NOW()` if not already set

---

### User Management

#### `listUsers.php`
**Purpose:** Lists all users in the system

**Method:** GET

**Response:**
```json
[
  {
    "id": 1,
    "name": "admin",
    "role": "admin",
    "is_active": 1,
    "created_at": "2024-01-01 00:00:00",
    "updated_at": "2024-01-01 00:00:00"
  }
]
```

**Features:**
- Auto-creates `users` table if missing
- Returns all users (active and inactive)
- Ordered alphabetically by name

---

#### `addUser.php`
**Purpose:** Creates a new user account

**Method:** POST

**Request Body:**
```json
{
  "name": "newuser",
  "password": "securepassword",
  "role": "admin"
}
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 2,
    "name": "newuser",
    "role": "admin",
    "is_active": 1,
    "created_at": "2024-01-01 00:00:00"
  }
}
```

**Process:**
1. Validates name and password
2. Hashes password using `password_hash()` with `PASSWORD_DEFAULT`
3. Inserts into database
4. Returns created user (without password hash)

**Error Responses:**
- `409` - User already exists (duplicate name)
- `422` - Missing required fields
- `500` - Database error

---

#### `deactivateUser.php`
**Purpose:** Deactivates a user account

**Method:** POST

**Request Body:**
```json
{
  "id": 1
}
```

**Response:**
```json
{
  "success": true
}
```

**Process:**
- Sets `is_active = 0`
- User cannot log in while deactivated

---

#### `reactivateUser.php`
**Purpose:** Reactivates a deactivated user account

**Method:** POST

**Request Body:**
```json
{
  "id": 1
}
```

**Response:**
```json
{
  "success": true
}
```

**Process:**
- Sets `is_active = 1`
- User can log in again

---

#### `resetUserPassword.php`
**Purpose:** Resets a user's password

**Method:** POST

**Request Body:**
```json
{
  "id": 1,
  "password": "newpassword"
}
```

**Response:**
```json
{
  "success": true
}
```

**Process:**
1. Validates user ID and new password
2. Hashes new password
3. Updates password hash in database

---

### BattleMetrics Integration

#### `battlemetrics.php`
**Purpose:** Secure proxy for BattleMetrics API requests

**Method:** GET

**Query Parameters:**
- `serverId` (required) - BattleMetrics server ID

**Response:**
- Returns raw BattleMetrics API response

**Features:**
- Hides API key from client-side JavaScript
- Includes server details, player info, settings, conflicts
- 12-second timeout
- Returns proper HTTP status codes

**Security:**
- API key stored server-side only
- Never exposed to client

---

#### `cluster.php`
**Purpose:** Batch fetches multiple servers and saves to `servers.json`

**Method:** GET

**Features:**
- Fetches data for multiple server IDs
- Saves aggregated data to `servers.json` file
- Returns JSON array of server data
- Used for cluster/server group management

**Note:** This appears to be a utility script, not a standard API endpoint.

---

## Configuration Files

### `dbconnect.php`
**Purpose:** Database connection configuration

**Variables:**
- `$DB_HOST` - Database hostname (usually 'localhost')
- `$DB_NAME` - Database name
- `$DB_USER` - Database username
- `$DB_PASS` - Database password

**Features:**
- Creates mysqli connection
- Sets UTF-8 charset
- Returns JSON error on connection failure
- Used by all PHP API endpoints

**Security:**
- ⚠️ Contains database credentials - keep secure
- Should not be publicly accessible

---

### `.htaccess`
**Purpose:** Apache server configuration and security

**Features:**

1. **HTTPS Enforcement**
   - Redirects all HTTP traffic to HTTPS

2. **Trailing Slash Handling**
   - Adds trailing slashes to URLs (except files)

3. **Security Headers**
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `X-XSS-Protection: 1; mode=block`
   - `Referrer-Policy: same-origin`
   - `Strict-Transport-Security` (HSTS)
   - `Permissions-Policy` restrictions

4. **Directory Listing**
   - Disabled (`Options -Indexes`)

5. **File Protection**
   - Blocks access to `.env`, `.json`, `composer.*` files

6. **Compression**
   - Gzip compression for text/html/css/js/json/xml

7. **Caching**
   - 30-day cache for CSS, JS, images, fonts
   - 12-hour default cache

---

## JavaScript Files

### `js/main.js`
**Purpose:** Homepage functionality

**Features:**
- Slideshow auto-rotation (5-second intervals)
- Manual slide navigation via dots
- Pause on hover
- Server rendering from localStorage
- Listens for `serversUpdated` event

---

### `js/battlemetrics.js`
**Purpose:** BattleMetrics API integration and server card rendering

**Features:**
- Creates server cards with real-time data
- Fetches server info from `battlemetrics.php` proxy
- Updates player counts, status, map, uptime
- Displays server mods and active conflicts
- IP address copy-to-clipboard functionality
- Auto-refreshes server data periodically
- Handles online/offline status indicators

**Key Functions:**
- `renderCards(container, servers)` - Renders server cards
- `updateServerCard(card, data)` - Updates individual card
- `fetchServerData(serverId)` - Fetches from API proxy

---

### `js/portal.js`
**Purpose:** Admin portal functionality

**Features:**
- Authentication check on page load
- Tab navigation with sub-tabs
- Server management (add/delete)
- Announcement management (create/delete)
- User management (view/add/deactivate/reactivate/reset password)
- Role-based UI visibility
- Modal dialogs for forms
- Welcome message with username

**API Endpoints Used:**
- `auth_check.php` - Authentication verification
- `getServers.php` - List servers
- `saveServer.php` - Add server
- `deleteServer.php` - Remove server
- `getAnnouncements.php` - List announcements
- `saveAnnouncement.php` - Create announcement
- `deleteAnnouncement.php` - Delete announcement
- `listUsers.php` - List users
- `addUser.php` - Create user
- `deactivateUser.php` - Deactivate user
- `reactivateUser.php` - Reactivate user
- `resetUserPassword.php` - Reset password

**Role-Based Access:**
- **Owner:** Full access to all features
- **Admin:** All features except "Manage Access"
- **Staff:** Only "Server Announcements" sub-tab

---

### `js/portal_login.js`
**Purpose:** Login page functionality

**Features:**
- Form validation
- Error display
- Loading state management
- Auto-redirect if already logged in
- Password visibility toggle (if implemented)
- Credential submission to `login.php`

**Process:**
1. Checks if user already logged in → redirects if yes
2. Validates form inputs
3. Submits credentials via POST
4. Shows loading animation
5. Redirects on success or shows error

---

### `js/Watermark.js`
**Purpose:** Greyline Studio watermark component

**Features:**
- Custom web component (`<greyline-studio-watermark>`)
- Fixed position bottom-left
- Hover effects
- Responsive design
- Links to Greyline Studio website

---

## Database Schema

### `users` Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Roles:**
- `owner` - Full access
- `admin` - Most features (no user management)
- `staff` - Limited access (announcements only)

---

### `servers` Table
```sql
CREATE TABLE servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    battlemetrics_id VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    game_title VARCHAR(255) DEFAULT NULL,
    region VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### `announcements` Table
```sql
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NULL,
    message TEXT NOT NULL,
    severity VARCHAR(16) NOT NULL DEFAULT 'info',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_server_id (server_id),
    INDEX idx_is_active (is_active),
    INDEX idx_starts_at (starts_at),
    INDEX idx_ends_at (ends_at),
    CONSTRAINT fk_announcements_server_id FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Severity Values:**
- `info` (blue)
- `success` (green)
- `warning` (yellow)
- `error` (red)

---

## Setup Instructions

### 1. Database Configuration
Edit `dbconnect.php` with your database credentials:
```php
$DB_HOST = 'localhost';
$DB_NAME = 'your_database_name';
$DB_USER = 'your_username';
$DB_PASS = 'your_password';
```

### 2. BattleMetrics API Key
Set environment variable or edit `battlemetrics.php`:
```php
$apiKey = 'your_battlemetrics_api_key';
```

### 3. File Permissions
Ensure PHP can write to directory (for `cluster.php`):
```bash
chmod 755 /path/to/OPRWebsite
```

### 4. Apache Configuration
- Ensure `.htaccess` is enabled (`AllowOverride All`)
- Enable mod_rewrite, mod_headers, mod_deflate, mod_expires
- SSL certificate configured for HTTPS

### 5. First User Creation
Create first admin user via database:
```sql
INSERT INTO users (name, password_hash, role, is_active) 
VALUES ('admin', '$2y$10$...', 'owner', 1);
```

Or use `addUser.php` endpoint after creating table manually.

---

## Security Considerations

### ✅ Implemented
- Password hashing with `password_hash()`
- Prepared statements (SQL injection prevention)
- Session security (HttpOnly, SameSite)
- HTTPS enforcement
- Security headers
- API key hidden server-side
- Input validation and sanitization
- Role-based access control

### ⚠️ Recommendations
- Move `dbconnect.php` outside web root if possible
- Use environment variables for sensitive config
- Implement rate limiting on login endpoint
- Add CSRF tokens for state-changing operations
- Regular security audits
- Keep PHP and dependencies updated

---

## API Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "details": "Optional details"
}
```

### HTTP Status Codes
- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `405` - Method Not Allowed
- `409` - Conflict (duplicate)
- `422` - Validation Error
- `500` - Server Error

---

## File Dependencies

### Frontend Dependencies
- Font Awesome 6.4.0 (CDN)
- Custom CSS files
- Custom JavaScript modules

### Backend Dependencies
- PHP 7.4+ (recommended 8.0+)
- MySQL/MariaDB
- cURL extension (for BattleMetrics API)
- Apache with mod_rewrite

---

## Troubleshooting

### Login Not Working
1. Check PHP session configuration
2. Verify database connection
3. Check browser console for errors
4. Ensure cookies are enabled
5. Verify `auth_check.php` returns correct JSON

### Servers Not Displaying
1. Check BattleMetrics API key is valid
2. Verify `getServers.php` returns data
3. Check browser console for JavaScript errors
4. Verify database table exists

### Portal Redirect Loop
1. Clear browser cookies
2. Check session storage path permissions
3. Verify `.htaccess` isn't blocking sessions
4. Check PHP error logs

---

## Version History

- **Current Version:** 1.0
- **Last Updated:** 2024

---

## Support

For issues or questions, contact the development team or refer to the codebase documentation.

