-- ═════════════════════════════════════════════════════════════════════════════════
-- database_setup.sql - Database Schema and Default Admin User
-- ═════════════════════════════════════════════════════════════════════════════════
-- 
-- PURPOSE
-- ───────
-- Creates the users table and inserts a default administrative account.
-- Run this SQL file to set up your database with initial admin credentials.
-- 
-- DEFAULT CREDENTIALS
-- ───────────────────
-- Username: admin
-- Password: admin123
-- Role: owner
-- 
-- ⚠️  SECURITY WARNING
-- ───────────────────
-- Change the default password immediately after first login!
-- 
-- ═════════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────────
-- SECTION 1: CREATE USERS TABLE
-- ─────────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────────
-- SECTION 2: CREATE SERVERS TABLE
-- ─────────────────────────────────────────────────────────────────────────────────

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

-- ─────────────────────────────────────────────────────────────────────────────────
-- SECTION 3: CREATE ANNOUNCEMENTS TABLE
-- ─────────────────────────────────────────────────────────────────────────────────

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

-- ─────────────────────────────────────────────────────────────────────────────────
-- SECTION 4: INSERT DEFAULT ADMIN USER
-- ─────────────────────────────────────────────────────────────────────────────────

-- Default credentials:
-- Username: admin
-- Password: admin123
-- Role: owner
-- 
-- ⚠️  IMPORTANT: The password hash below is a placeholder.
-- For security, run setup_database.php instead, which will generate a proper hash.
-- Or use generate_admin_hash.php to generate a new hash for a custom password.
-- 
-- To generate a proper hash, run:
--   php generate_admin_hash.php
-- 
-- Then copy the INSERT statement output and replace this section.

-- PLACEHOLDER HASH (DO NOT USE IN PRODUCTION - Run setup_database.php instead)
-- INSERT INTO users (name, password_hash, role, is_active) VALUES
-- ('admin', 'GENERATE_HASH_USING_setup_database.php', 'owner', 1)
-- ON DUPLICATE KEY UPDATE
--     password_hash = VALUES(password_hash),
--     role = VALUES(role),
--     is_active = VALUES(is_active);

-- ═════════════════════════════════════════════════════════════════════════════════
-- ✓ DATABASE SETUP COMPLETE
-- ═════════════════════════════════════════════════════════════════════════════════
-- 
-- You can now log in with:
--   Username: admin
--   Password: admin123
-- 
-- ⚠️  IMPORTANT: Change the password immediately after first login!
-- 
-- ═════════════════════════════════════════════════════════════════════════════════

