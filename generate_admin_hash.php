<?php
/**
 * ═════════════════════════════════════════════════════════════════════════════════
 * generate_admin_hash.php - Password Hash Generator Utility
 * ═════════════════════════════════════════════════════════════════════════════════
 * 
 * PURPOSE
 * ───────
 * Generates a bcrypt password hash for inserting into the database.
 * Use this if you want to create a custom password hash.
 * 
 * USAGE
 * ─────
 * php generate_admin_hash.php
 * 
 * Or modify the $password variable below and run:
 * php generate_admin_hash.php
 * 
 * ═════════════════════════════════════════════════════════════════════════════════
 */

// ─────────────────────────────────────────────────────────────────────────────────
// CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────────

$password = 'admin123';  // Change this to your desired password
$username = 'admin';     // Change this to your desired username
$role = 'owner';         // Change this to your desired role

// ─────────────────────────────────────────────────────────────────────────────────
// GENERATE PASSWORD HASH
// ─────────────────────────────────────────────────────────────────────────────────

$hash = password_hash($password, PASSWORD_DEFAULT);

// ─────────────────────────────────────────────────────────────────────────────────
// OUTPUT SQL INSERT STATEMENT
// ─────────────────────────────────────────────────────────────────────────────────

echo "═════════════════════════════════════════════════════════════════════════════════\n";
echo "SQL INSERT Statement for Default Admin User\n";
echo "═════════════════════════════════════════════════════════════════════════════════\n\n";

echo "-- Default credentials:\n";
echo "-- Username: {$username}\n";
echo "-- Password: {$password}\n";
echo "-- Role: {$role}\n";
echo "-- \n";
echo "-- Password hash: {$hash}\n\n";

echo "INSERT INTO users (name, password_hash, role, is_active) VALUES\n";
echo "('{$username}', '{$hash}', '{$role}', 1)\n";
echo "ON DUPLICATE KEY UPDATE\n";
echo "    password_hash = VALUES(password_hash),\n";
echo "    role = VALUES(role),\n";
echo "    is_active = VALUES(is_active);\n\n";

echo "═════════════════════════════════════════════════════════════════════════════════\n";
echo "Copy the INSERT statement above and add it to your database_setup.sql file\n";
echo "or run it directly in your MySQL client.\n";
echo "═════════════════════════════════════════════════════════════════════════════════\n";

?>

