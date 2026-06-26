-- ══════════════════════════════════════════════════════════════════════════
--  MCA Student Planner — Database Setup
--
--  IT INSTRUCTIONS:
--  1. Open your MySQL / MariaDB client (phpMyAdmin, MySQL Workbench, or terminal)
--  2. Run this entire file once.
--  3. Then create a database user with access to mca_planner:
--
--       CREATE USER 'planner_user'@'localhost' IDENTIFIED BY 'your_password';
--       GRANT ALL PRIVILEGES ON mca_planner.* TO 'planner_user'@'localhost';
--       FLUSH PRIVILEGES;
--
--  4. Update config.php with the database name, username, and password.
-- ══════════════════════════════════════════════════════════════════════════

-- Create the database (skip if it already exists)
CREATE DATABASE IF NOT EXISTS mca_planner
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mca_planner;

-- ── Table: accounts ────────────────────────────────────────────────────────
-- One row per student. Name must be unique (used as login identifier).
CREATE TABLE IF NOT EXISTS accounts (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)   NOT NULL,
    grade         VARCHAR(50)    NOT NULL DEFAULT '',
    homeroom      VARCHAR(100)   NOT NULL DEFAULT '',
    password_hash VARCHAR(255)   NOT NULL,
    created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: planner_data ────────────────────────────────────────────────────
-- One row per student (one-to-one with accounts).
-- Stores the entire planner state as JSON (assignments, grades, notes, etc.)
CREATE TABLE IF NOT EXISTS planner_data (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    account_id  INT UNSIGNED   NOT NULL,
    data_json   LONGTEXT       NOT NULL DEFAULT '{}',
    updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account (account_id),
    CONSTRAINT fk_pd_account FOREIGN KEY (account_id)
        REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: sessions ────────────────────────────────────────────────────────
-- Login tokens. Each login creates a row; logout or expiry removes it.
CREATE TABLE IF NOT EXISTS sessions (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    account_id  INT UNSIGNED   NOT NULL,
    token       CHAR(64)       NOT NULL,
    expires_at  TIMESTAMP      NOT NULL,
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    INDEX idx_account (account_id),
    CONSTRAINT fk_sess_account FOREIGN KEY (account_id)
        REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_sessions ─────────────────────────────────────────────────
-- Separate token table for admin/teacher logins. No account_id — admin is
-- a single shared password defined in config.php, not a database user.
CREATE TABLE IF NOT EXISTS admin_sessions (
    id         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    token      CHAR(64)       NOT NULL,
    expires_at TIMESTAMP      NOT NULL,
    created_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table: admin_audit_log ────────────────────────────────────────────────
-- Records every admin action (password resets, deletions, sign-outs).
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    action      VARCHAR(50)    NOT NULL,
    target_name VARCHAR(100),
    detail      VARCHAR(255),
    performed_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════════════════
--  OPTIONAL: Teacher / Admin reset tool
--  Run this to delete a specific student's account (they can re-register):
--
--    DELETE FROM accounts WHERE name = 'Smith, Jane';
--
--  Run this to see all registered students:
--
--    SELECT id, name, grade, homeroom, created_at FROM accounts ORDER BY name;
--
--  Run this to clean up expired sessions (add to a nightly cron job):
--
--    DELETE FROM sessions WHERE expires_at < NOW();
-- ══════════════════════════════════════════════════════════════════════════
