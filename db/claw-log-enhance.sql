-- ============================================
-- GDQ Log System Enhanced Migration
-- Created: 2026-03-12
-- Description: 增强日志系统 - 支持日志类型、超级管理员模板设计
-- ============================================

-- 1. 添加日志类型字段到 work_logs 表
ALTER TABLE work_logs ADD COLUMN log_type ENUM('work', 'complaint', 'share') DEFAULT 'work' COMMENT '日志类型: work=工作日志, complaint=投诉日志, share=分享日志';
ALTER TABLE work_logs ADD COLUMN submit_date DATE DEFAULT NULL COMMENT '填报日期';
ALTER TABLE work_logs ADD COLUMN定位 VARCHAR(200) DEFAULT NULL COMMENT '位置信息';
ALTER TABLE work_logs ADD COLUMN定位_lat DECIMAL(10,7) DEFAULT NULL COMMENT 'GPS纬度';
ALTER TABLE work_logs ADD COLUMN定位_lng DECIMAL(10,7) DEFAULT NULL COMMENT 'GPS经度';

-- 2. 扩展 work_log_templates 表支持更多字段类型
ALTER TABLE work_log_templates ADD COLUMN log_type ENUM('work', 'complaint', 'share') DEFAULT 'work' COMMENT '适用日志类型';
ALTER TABLE work_log_templates ADD COLUMN description VARCHAR(500) DEFAULT NULL COMMENT '模板描述';
ALTER TABLE work_log_templates ADD COLUMN require_participants TINYINT(1) DEFAULT 0 COMMENT '是否要求填写参与人';
ALTER TABLE work_log_templates ADD COLUMN require_recipients TINYINT(1) DEFAULT 1 COMMENT '是否自动计算接受人';

-- 3. 创建参与人表
CREATE TABLE IF NOT EXISTS work_log_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  log_id INT NOT NULL COMMENT '日志ID',
  user_id INT NOT NULL COMMENT '参与人ID',
  user_name VARCHAR(100) DEFAULT NULL COMMENT '参与人姓名',
  role ENUM('creator', 'participant') DEFAULT 'participant' COMMENT '角色: creator=创建人, participant=参与人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (log_id) REFERENCES work_logs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_log (log_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='日志参与人表';

-- 4. 扩展 users 表添加上级关系（如果不存在）
-- 检查上级字段是否存在
SET @dbname = DATABASE();
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'parent_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN parent_id INT DEFAULT NULL COMMENT ''上级用户ID''',
  'SELECT ''Column parent_id already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加索引
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_parent');
SET @sql = IF(@index_exists = 0,
  'ALTER TABLE users ADD INDEX idx_parent (parent_id)',
  'SELECT ''Index idx_parent already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. 更新现有模板，添加波哥要求的标准字段
UPDATE work_log_templates SET 
  fields = JSON_ARRAY(
    JSON_OBJECT('name', 'title', 'label', '标题', 'type', 'text', 'required', true, 'maxLength', 255),
    JSON_OBJECT('name', 'submit_date', 'label', '日期', 'type', 'date', 'required', true),
    JSON_OBJECT('name', 'single_line', 'label', '单行文本', 'type', 'text', 'required', false, 'maxLength', 255),
    JSON_OBJECT('name', 'multi_line', 'label', '多行文本', 'type', 'textarea', 'required', false, 'maxLength', 2550),
    JSON_OBJECT('name', 'location', 'label', '定位', 'type', 'location', 'required', false),
    JSON_OBJECT('name', 'images', 'label', '图片', 'type', 'image', 'required', false, 'maxCount', 9),
    JSON_OBJECT('name', 'participants', 'label', '参与人', 'type', 'participants', 'required', false)
  ),
  log_type = 'work',
  description = '标准工作日志模板 - 包含标题、日期、文本、定位、图片、参与人',
  require_participants = 0,
  require_recipients = 1,
  creator_id = 8
WHERE id = 1;

-- 6. 创建投诉日志模板
INSERT INTO work_log_templates (name, creator_id, fields, log_type, description, require_participants, require_recipients, is_default, status) VALUES
('投诉建议模板', 8, JSON_ARRAY(
  JSON_OBJECT('name', 'title', 'label', '标题', 'type', 'text', 'required', true, 'maxLength', 255),
  JSON_OBJECT('name', 'submit_date', 'label', '日期', 'type', 'date', 'required', true),
  JSON_OBJECT('name', 'content', 'label', '投诉内容', 'type', 'textarea', 'required', true, 'maxLength', 2550),
  JSON_OBJECT('name', 'images', 'label', '图片证据', 'type', 'image', 'required', false, 'maxCount', 9),
  JSON_OBJECT('name', 'contact', 'label', '联系方式', 'type', 'text', 'required', false, 'maxLength', 50)
), 'complaint', '投诉建议模板 - 用于提交投诉和建议', 0, 1, 0, 'active')
ON DUPLICATE KEY UPDATE name=name;

-- 7. 创建分享日志模板
INSERT INTO work_log_templates (name, creator_id, fields, log_type, description, require_participants, require_recipients, is_default, status) VALUES
('分享日志模板', 8, JSON_ARRAY(
  JSON_OBJECT('name', 'title', 'label', '标题', 'type', 'text', 'required', true, 'maxLength', 255),
  JSON_OBJECT('name', 'submit_date', 'label', '日期', 'type', 'date', 'required', true),
  JSON_OBJECT('name', 'content', 'label', '分享内容', 'type', 'textarea', 'required', true, 'maxLength', 2550),
  JSON_OBJECT('name', 'images', 'label', '配图', 'type', 'image', 'required', false, 'maxCount', 9),
  JSON_OBJECT('name', 'participants', 'label', '参与人', 'type', 'participants', 'required', false)
), 'share', '分享日志模板 - 用于分享工作心得', 0, 1, 0, 'active')
ON DUPLICATE KEY UPDATE name=name;

-- 8. 添加索引
ALTER TABLE work_logs ADD INDEX idx_log_type (log_type);
ALTER TABLE work_logs ADD INDEX idx_submit_date (submit_date);

-- ============================================
-- Migration Complete
-- ============================================
