-- ============================================
-- GDQ Log System Migration
-- Created: 2026-03-03
-- Description: 工作日志、拜访日志、分享日志、投诉建议系统
-- ============================================

-- 1. 工作日志模板表
CREATE TABLE IF NOT EXISTS work_log_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '模板名称',
  creator_id INT NOT NULL COMMENT '创建人',
  fields JSON NOT NULL COMMENT '[{name:"今日进展",type:"textarea",required:true}]',
  is_default TINYINT(1) DEFAULT 0 COMMENT '是否默认模板',
  status ENUM('active','archived') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_creator (creator_id),
  INDEX idx_status (status),
  INDEX idx_is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工作日志模板表';

-- 插入默认模板
INSERT INTO work_log_templates (id, name, creator_id, fields, is_default) VALUES
(1, '标准日报模板', 1, JSON_ARRAY(
  JSON_OBJECT('name', '今日完成', 'type', 'textarea', 'required', true),
  JSON_OBJECT('name', '明日计划', 'type', 'textarea', 'required', true),
  JSON_OBJECT('name', '问题反馈', 'type', 'textarea', 'required', false)
), 1)
ON DUPLICATE KEY UPDATE name=name;

-- 2. 扩展工作日志表 (work_logs 已存在，添加新字段)
-- 检查并添加字段
SET @dbname = DATABASE();
SET @tablename = 'work_logs';

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'template_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE work_logs ADD COLUMN template_id INT DEFAULT NULL COMMENT ''使用的模板ID''',
  'SELECT ''Column template_id already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'content');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE work_logs ADD COLUMN content JSON DEFAULT NULL COMMENT ''日志内容JSON''',
  'SELECT ''Column content already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'recipients');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE work_logs ADD COLUMN recipients JSON DEFAULT NULL COMMENT ''发送对象[user_id]''',
  'SELECT ''Column recipients already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'attachments');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE work_logs ADD COLUMN attachments JSON DEFAULT NULL COMMENT ''附件[{name,url}]''',
  'SELECT ''Column attachments already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'status');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE work_logs ADD COLUMN status ENUM(''draft'',''submitted'') DEFAULT ''submitted''',
  'SELECT ''Column status already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加索引（如果不存在）
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_template');
SET @sql = IF(@index_exists = 0,
  'ALTER TABLE work_logs ADD INDEX idx_template (template_id)',
  'SELECT ''Index idx_template already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_status');
SET @sql = IF(@index_exists = 0,
  'ALTER TABLE work_logs ADD INDEX idx_status (status)',
  'SELECT ''Index idx_status already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加外键约束（如果不存在）
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = 'fk_work_logs_template');
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE work_logs ADD CONSTRAINT fk_work_logs_template FOREIGN KEY (template_id) REFERENCES work_log_templates(id) ON DELETE SET NULL',
  'SELECT ''Foreign key fk_work_logs_template already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. 工作日志互动表
CREATE TABLE IF NOT EXISTS work_log_interactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  log_id INT NOT NULL COMMENT '日志ID',
  user_id INT NOT NULL COMMENT '操作人',
  type ENUM('read','comment','like') NOT NULL COMMENT '互动类型',
  content TEXT DEFAULT NULL COMMENT '评论内容',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (log_id) REFERENCES work_logs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_log_user_type (log_id, user_id, type),
  INDEX idx_log (log_id),
  INDEX idx_user (user_id),
  INDEX idx_type (type),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工作日志互动表';

-- 4. 拜访日志表
CREATE TABLE IF NOT EXISTS visit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '拜访人',
  customer_type ENUM('supplier','dealer','store') NOT NULL COMMENT '客户类型',
  customer_id INT NOT NULL COMMENT '客户ID',
  visit_date DATETIME NOT NULL COMMENT '拜访时间',
  purpose VARCHAR(200) DEFAULT NULL COMMENT '拜访目的',
  content TEXT NOT NULL COMMENT '拜访内容',
  result TEXT DEFAULT NULL COMMENT '拜访结果',
  next_plan TEXT DEFAULT NULL COMMENT '下次计划',
  photos JSON DEFAULT NULL COMMENT '照片[url]',
  location VARCHAR(200) DEFAULT NULL COMMENT '拜访地点',
  gps_lat DECIMAL(10,7) DEFAULT NULL COMMENT 'GPS纬度',
  gps_lng DECIMAL(10,7) DEFAULT NULL COMMENT 'GPS经度',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_customer (customer_type, customer_id),
  INDEX idx_visit_date (visit_date),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拜访日志表';

-- 5. 分享日志表
CREATE TABLE IF NOT EXISTS share_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL COMMENT '分享人（系统用户）',
  h5_user_id INT DEFAULT NULL COMMENT '分享人（H5用户）',
  share_type ENUM('product','qrcode','page') NOT NULL COMMENT '分享类型',
  share_target_id INT DEFAULT NULL COMMENT '分享目标ID',
  channel ENUM('wechat','whatsapp','link','poster') NOT NULL COMMENT '分享渠道',
  share_url VARCHAR(500) DEFAULT NULL COMMENT '分享链接',
  view_count INT DEFAULT 0 COMMENT '浏览次数',
  conversion_count INT DEFAULT 0 COMMENT '转化次数',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (h5_user_id) REFERENCES h5_users(id) ON DELETE SET NULL,
  INDEX idx_user (user_id),
  INDEX idx_h5_user (h5_user_id),
  INDEX idx_share_type (share_type, share_target_id),
  INDEX idx_channel (channel),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分享日志表';

-- 6. 投诉建议表
CREATE TABLE IF NOT EXISTS feedback_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL COMMENT '提交人（系统用户）',
  h5_user_id INT DEFAULT NULL COMMENT '提交人（H5用户）',
  type ENUM('complaint','suggestion','bug','other') NOT NULL COMMENT '反馈类型',
  title VARCHAR(200) NOT NULL COMMENT '标题',
  content TEXT NOT NULL COMMENT '内容',
  images JSON DEFAULT NULL COMMENT '图片[url]',
  contact_phone VARCHAR(20) DEFAULT NULL COMMENT '联系电话',
  status ENUM('pending','processing','resolved','closed') DEFAULT 'pending',
  assigned_to INT DEFAULT NULL COMMENT '处理人',
  reply TEXT DEFAULT NULL COMMENT '回复内容',
  resolved_at DATETIME DEFAULT NULL COMMENT '解决时间',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (h5_user_id) REFERENCES h5_users(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user (user_id),
  INDEX idx_h5_user (h5_user_id),
  INDEX idx_status (status),
  INDEX idx_type (type),
  INDEX idx_assigned (assigned_to),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投诉建议表';

-- ============================================
-- Migration Complete
-- ============================================
