CREATE DATABASE IF NOT EXISTS nzbgeek
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nzbgeek;

-- Member status reference table
CREATE TABLE IF NOT EXISTS member_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default statuses
INSERT INTO member_statuses (name, display_name) VALUES
    ('active_trial', 'active trial'),
    ('active', 'active'),
    ('staff', 'staff'),
    ('expired', 'expired'),
    ('closed', 'closed')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_using_generated_password TINYINT(1) NOT NULL DEFAULT 1,
    status_id INT UNSIGNED NOT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    account_close_token VARCHAR(255) DEFAULT NULL,
    account_close_token_expires DATETIME DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (status_id) REFERENCES member_statuses(id),
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;