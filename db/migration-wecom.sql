-- Migration: WeCom integration tables
-- Run this on existing databases to add the new tables and columns.

-- Add wecom_synced column to attendance
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS wecom_synced BOOLEAN DEFAULT FALSE;

-- Add unique key on attendance (user_id, date) — safe: ignore error if exists
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND INDEX_NAME = 'uk_user_date');
SET @sql = IF(@exists = 0, 'ALTER TABLE attendance ADD UNIQUE KEY uk_user_date (user_id, date)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unique key on leave_records (user_id, type, start_date, end_date) — safe: ignore if exists
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND INDEX_NAME = 'uk_user_leave');
SET @sql = IF(@exists = 0, 'ALTER TABLE leave_records ADD UNIQUE KEY uk_user_leave (user_id, type, start_date, end_date)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add wecom_chat_id to wecom_conversations
ALTER TABLE wecom_conversations ADD COLUMN IF NOT EXISTS wecom_chat_id VARCHAR(100);

-- WeCom contacts
CREATE TABLE IF NOT EXISTS wecom_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wecom_userid VARCHAR(100) UNIQUE NOT NULL,
  user_id INT,
  name VARCHAR(100),
  department_ids JSON,
  position VARCHAR(100),
  mobile VARCHAR(30),
  email VARCHAR(100),
  status INT DEFAULT 1,
  synced_at DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- WeCom departments
CREATE TABLE IF NOT EXISTS wecom_departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wecom_dept_id INT UNIQUE NOT NULL,
  name VARCHAR(200),
  parentid INT DEFAULT 0,
  dept_order INT DEFAULT 0,
  synced_at DATETIME
);

-- WeCom approvals
CREATE TABLE IF NOT EXISTS wecom_approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sp_no VARCHAR(50) UNIQUE NOT NULL,
  sp_name VARCHAR(200),
  sp_status VARCHAR(20),
  apply_time DATETIME,
  applicant_userid VARCHAR(100),
  data_json JSON,
  synced_at DATETIME
);

-- WeCom sync log
CREATE TABLE IF NOT EXISTS wecom_sync_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('attendance','approvals','contacts') NOT NULL,
  status ENUM('success','failed') NOT NULL,
  records_synced INT DEFAULT 0,
  error_message TEXT,
  started_at DATETIME,
  finished_at DATETIME
);
