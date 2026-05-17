-- ============================================
-- 系统配置表 - 用于存储财务审批阈值等配置
-- ============================================

CREATE TABLE IF NOT EXISTS system_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
  config_value TEXT NOT NULL COMMENT '配置值',
  description VARCHAR(200) DEFAULT NULL COMMENT '配置说明',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_config_key (config_key)
) COMMENT='系统配置表';

-- 插入默认财务审批配置
INSERT INTO system_config (config_key, config_value, description) VALUES
  ('expense_approval_threshold', '5000', '费用支出审批阈值（元）'),
  ('payment_approval_threshold', '10000', '付款审批阈值（元）'),
  ('approval_workflow_level', '1', '审批流程级别（1=一级审批，2=二级审批）')
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);
