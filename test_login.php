<?php
/**
 * Test script to verify login functionality
 * This file can be accessed directly to check database connection and users table
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/dbconnect.php';

echo "<h1>Login Test</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
if (isset($conn) && $conn instanceof mysqli) {
    echo "✓ Database connection successful<br>";
    echo "Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "<br>";
} else {
    echo "✗ Database connection failed<br>";
    exit;
}

// Test 2: Check if users table exists
echo "<h2>2. Users Table Check</h2>";
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result && $result->num_rows > 0) {
    echo "✓ Users table exists<br>";
    
    // Test 3: Check table structure
    echo "<h2>3. Table Structure</h2>";
    $result = $conn->query("DESCRIBE users");
    if ($result) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 4: Check for users
    echo "<h2>4. Users in Database</h2>";
    $result = $conn->query("SELECT id, name, role, is_active FROM users");
    if ($result) {
        $count = $result->num_rows;
        echo "Total users: " . $count . "<br><br>";
        
        if ($count > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Active</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "⚠ No users found in database. You need to create a user first.<br>";
        }
    }
} else {
    echo "✗ Users table does not exist<br>";
    echo "You need to run the database setup script first.<br>";
}

// Test 5: Test password verification
echo "<h2>5. Password Verification Test</h2>";
$testUser = $conn->query("SELECT name, password_hash FROM users WHERE is_active = 1 LIMIT 1");
if ($testUser && $testUser->num_rows > 0) {
    $user = $testUser->fetch_assoc();
    echo "Testing password verification for user: " . htmlspecialchars($user['name']) . "<br>";
    echo "Password hash exists: " . (empty($user['password_hash']) ? 'No' : 'Yes') . "<br>";
    
    if (!empty($user['password_hash'])) {
        // Test with a common password
        $testPasswords = ['admin123', 'password', 'admin'];
        foreach ($testPasswords as $testPwd) {
            $match = password_verify($testPwd, $user['password_hash']);
            echo "Password '{$testPwd}': " . ($match ? '✓ MATCHES' : '✗ No match') . "<br>";
        }
    }
} else {
    echo "No active users found to test password verification.<br>";
}

echo "<hr>";
echo "<p><strong>Note:</strong> Delete this file after testing for security.</p>";

?>

