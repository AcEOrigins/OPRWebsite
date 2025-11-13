<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * setup_database.php - Database Setup Script
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Creates all database tables and inserts default admin user.
 * Run this script once to set up your database.
 * 
 * USAGE
 * ─────
 * php setup_database.php
 * 
 * Or access via web browser: http://yoursite.com/setup_database.php
 * 
 * DEFAULT CREDENTIALS
 * ───────────────────
 * Username: admin
 * Password: admin123
 * Role: owner
 * 
 * ⚠️  SECURITY WARNING
 * ───────────────────
 * Change the default password immediately after first login!
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 1: LOAD DATABASE CONNECTION
// ─────────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/dbconnect.php';

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 2: CREATE TABLES
// ─────────────────────────────────────────────────────────────────────────────────

$tables = [];

// Users table
$tables['users'] = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Servers table
$tables['servers'] = "
CREATE TABLE IF NOT EXISTS servers (
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
";

// Announcements table
$tables['announcements'] = "
CREATE TABLE IF NOT EXISTS announcements (
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
";

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 3: EXECUTE TABLE CREATION
// ─────────────────────────────────────────────────────────────────────────────────

$errors = [];
$success = [];

foreach ($tables as $name => $sql) {
    if ($conn->query($sql)) {
        $success[] = "Table '{$name}' created successfully.";
    } else {
        $errors[] = "Error creating table '{$name}': " . $conn->error;
    }
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 4: INSERT DEFAULT ADMIN USER
// ─────────────────────────────────────────────────────────────────────────────────

$defaultUsername = 'admin';
$defaultPassword = 'admin123';
$defaultRole = 'owner';

// Generate password hash
$passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// Check if admin user already exists
$checkStmt = $conn->prepare('SELECT id FROM users WHERE name = ? LIMIT 1');
$checkStmt->bind_param('s', $defaultUsername);
$checkStmt->execute();
$result = $checkStmt->get_result();
$exists = $result->num_rows > 0;
$checkStmt->close();

if (!$exists) {
    // Insert default admin user
    $insertStmt = $conn->prepare('
        INSERT INTO users (name, password_hash, role, is_active)
        VALUES (?, ?, ?, 1)
    ');
    $insertStmt->bind_param('sss', $defaultUsername, $passwordHash, $defaultRole);
    
    if ($insertStmt->execute()) {
        $success[] = "Default admin user created successfully.";
        $success[] = "Username: {$defaultUsername}";
        $success[] = "Password: {$defaultPassword}";
        $success[] = "Role: {$defaultRole}";
    } else {
        $errors[] = "Error creating admin user: " . $insertStmt->error;
    }
    $insertStmt->close();
} else {
    $success[] = "Admin user already exists. Skipping creation.";
}

// ─────────────────────────────────────────────────────────────────────────────────
// SECTION 5: OUTPUT RESULTS
// ─────────────────────────────────────────────────────────────────────────────────

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - OPR Website</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #e0e0e0;
        }
        h1 { color: #4a9eff; }
        .success { 
            background: #2d5016; 
            color: #90ee90; 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px;
            border-left: 4px solid #90ee90;
        }
        .error { 
            background: #501616; 
            color: #ff6b6b; 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px;
            border-left: 4px solid #ff6b6b;
        }
        .warning {
            background: #4a3d1a;
            color: #ffd700;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid #ffd700;
        }
        code {
            background: #2a2a2a;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <h1>Database Setup</h1>
    
    <?php if (!empty($success)): ?>
        <?php foreach ($success as $msg): ?>
            <div class="success">✓ <?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $msg): ?>
            <div class="error">✗ <?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (empty($errors)): ?>
        <div class="warning">
            <strong>⚠️ Security Warning:</strong><br>
            Default admin credentials have been created:<br>
            <strong>Username:</strong> <code>admin</code><br>
            <strong>Password:</strong> <code>admin123</code><br>
            <strong>Role:</strong> <code>owner</code><br><br>
            <strong>Please change the password immediately after first login!</strong>
        </div>
        
        <p>
            <a href="portal_login.html" style="color: #4a9eff;">→ Go to Login Page</a>
        </p>
    <?php endif; ?>
</body>
</html>

