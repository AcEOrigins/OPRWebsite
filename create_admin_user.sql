-- ═════════════════════════════════════════════════════════════════════════════════
-- create_admin_user.sql - Default Admin User Creation
-- ═════════════════════════════════════════════════════════════════════════════════
-- 
-- PURPOSE
-- ───────
-- Inserts default administrative user with role "owner".
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
-- USAGE
-- ─────
-- Run this SQL after creating the users table, OR
-- Use setup_database.php which handles everything automatically.
-- 
-- ═════════════════════════════════════════════════════════════════════════════════

-- Insert default admin user
-- Password hash for "admin123" generated with PHP password_hash()
INSERT INTO users (name, password_hash, role, is_active) VALUES
('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'owner', 1)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    is_active = VALUES(is_active);

-- ═════════════════════════════════════════════════════════════════════════════════
-- ✓ ADMIN USER CREATED
-- ═════════════════════════════════════════════════════════════════════════════════
-- 
-- Default login credentials:
--   Username: admin
--   Password: admin123
--   Role: owner
-- 
-- ⚠️  IMPORTANT: Change password after first login!
-- 
-- ═════════════════════════════════════════════════════════════════════════════════

