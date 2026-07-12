-- Bank Accounts Table
CREATE TABLE IF NOT EXISTS bank_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_name VARCHAR(100) NOT NULL COMMENT '账户名称',
  bank_name VARCHAR(100) NOT NULL COMMENT '银行名称',
  account_number VARCHAR(50) NOT NULL UNIQUE COMMENT '账号',
  account_type ENUM('general','special','temporary') DEFAULT 'general' COMMENT '账户类型',
  currency VARCHAR(10) DEFAULT 'CNY' COMMENT '币种',
  subject_id INT DEFAULT NULL COMMENT '关联会计科目',
  balance DECIMAL(15,2) DEFAULT 0 COMMENT '余额',
  status ENUM('active','inactive') DEFAULT 'active' COMMENT '状态',
  note TEXT COMMENT '备注',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES accounting_subjects(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_subject (subject_id)
) COMMENT='银行账户表';
