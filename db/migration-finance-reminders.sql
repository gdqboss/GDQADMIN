-- 财务提醒系统数据库迁移
-- 创建时间: 2026-03-03

-- 财务提醒表
CREATE TABLE IF NOT EXISTS finance_reminders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  reminder_type ENUM('payable_due', 'receivable_overdue', 'expense_abnormal', 'monthly_report') NOT NULL COMMENT '提醒类型',
  title VARCHAR(255) NOT NULL COMMENT '提醒标题',
  content TEXT COMMENT '提醒内容',
  related_type VARCHAR(50) COMMENT '关联类型(payable/receivable/expense)',
  related_id INT COMMENT '关联记录ID',
  target_user_id INT NOT NULL COMMENT '目标用户ID',
  status ENUM('unread', 'read', 'dismissed') DEFAULT 'unread' COMMENT '状态',
  priority ENUM('low', 'medium', 'high') DEFAULT 'medium' COMMENT '优先级',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  read_at TIMESTAMP NULL COMMENT '已读时间',
  INDEX idx_target_user (target_user_id),
  INDEX idx_status (status),
  INDEX idx_reminder_type (reminder_type),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='财务提醒表';

-- 提醒设置表
CREATE TABLE IF NOT EXISTS reminder_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL COMMENT '用户ID',
  reminder_type ENUM('payable_due', 'receivable_overdue', 'expense_abnormal', 'monthly_report') NOT NULL COMMENT '提醒类型',
  enabled BOOLEAN DEFAULT TRUE COMMENT '是否启用',
  advance_days INT DEFAULT 3 COMMENT '提前提醒天数',
  threshold_amount DECIMAL(15,2) DEFAULT 10000.00 COMMENT '金额阈值',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  INDEX idx_user_id (user_id),
  UNIQUE KEY uk_user_reminder (user_id, reminder_type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提醒设置表';

-- 插入默认提醒设置（为所有现有用户创建默认设置）
INSERT INTO reminder_settings (user_id, reminder_type, enabled, advance_days, threshold_amount)
SELECT u.id, 'payable_due', TRUE, 3, 10000.00
FROM users u
WHERE NOT EXISTS (
  SELECT 1 FROM reminder_settings rs
  WHERE rs.user_id = u.id AND rs.reminder_type = 'payable_due'
);

INSERT INTO reminder_settings (user_id, reminder_type, enabled, advance_days, threshold_amount)
SELECT u.id, 'receivable_overdue', TRUE, 30, 10000.00
FROM users u
WHERE NOT EXISTS (
  SELECT 1 FROM reminder_settings rs
  WHERE rs.user_id = u.id AND rs.reminder_type = 'receivable_overdue'
);

INSERT INTO reminder_settings (user_id, reminder_type, enabled, advance_days, threshold_amount)
SELECT u.id, 'expense_abnormal', TRUE, 0, 50000.00
FROM users u
WHERE NOT EXISTS (
  SELECT 1 FROM reminder_settings rs
  WHERE rs.user_id = u.id AND rs.reminder_type = 'expense_abnormal'
);

INSERT INTO reminder_settings (user_id, reminder_type, enabled, advance_days, threshold_amount)
SELECT u.id, 'monthly_report', TRUE, 0, 0
FROM users u
WHERE NOT EXISTS (
  SELECT 1 FROM reminder_settings rs
  WHERE rs.user_id = u.id AND rs.reminder_type = 'monthly_report'
);
