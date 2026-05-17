-- 资金账户管理系统迁移脚本
-- 执行时间: 2026-03-03

-- 1. 创建资金账户表
CREATE TABLE IF NOT EXISTS fund_accounts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  account_name VARCHAR(100) NOT NULL COMMENT '账户名称',
  account_type ENUM('cash', 'bank', 'alipay', 'wechat', 'other') NOT NULL DEFAULT 'cash' COMMENT '账户类型',
  account_number VARCHAR(50) COMMENT '账号',
  bank_name VARCHAR(100) COMMENT '银行名称',
  balance DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT '状态',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_account_type (account_type),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资金账户表';

-- 2. 创建资金流水表
CREATE TABLE IF NOT EXISTS fund_transactions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  account_id INT NOT NULL COMMENT '账户ID',
  transaction_type ENUM('income', 'expense', 'transfer_in', 'transfer_out') NOT NULL COMMENT '交易类型',
  amount DECIMAL(15,2) NOT NULL COMMENT '金额',
  balance_after DECIMAL(15,2) NOT NULL COMMENT '交易后余额',
  related_type ENUM('purchase', 'sale', 'expense', 'payment', 'receipt', 'transfer', 'adjustment') COMMENT '关联业务类型',
  related_id INT COMMENT '关联业务ID',
  description VARCHAR(500) NOT NULL COMMENT '说明',
  transaction_date DATE NOT NULL COMMENT '交易日期',
  creator_id INT COMMENT '创建人ID',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES fund_accounts(id) ON DELETE RESTRICT,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_account_id (account_id),
  INDEX idx_transaction_date (transaction_date),
  INDEX idx_transaction_type (transaction_type),
  INDEX idx_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资金流水表';

-- 3. 修改现有表，添加 account_id 字段

-- 采购成本表
ALTER TABLE purchase_costs
ADD COLUMN account_id INT COMMENT '支付账户ID' AFTER payment_method,
ADD FOREIGN KEY (account_id) REFERENCES fund_accounts(id) ON DELETE SET NULL;

-- 销售收入表
ALTER TABLE sales_revenues
ADD COLUMN account_id INT COMMENT '收款账户ID' AFTER payment_method,
ADD FOREIGN KEY (account_id) REFERENCES fund_accounts(id) ON DELETE SET NULL;

-- 费用支出表
ALTER TABLE expense_records
ADD COLUMN account_id INT COMMENT '支付账户ID' AFTER payment_method,
ADD FOREIGN KEY (account_id) REFERENCES fund_accounts(id) ON DELETE SET NULL;

-- 付款记录表
ALTER TABLE payment_records
ADD COLUMN account_id INT COMMENT '支付账户ID' AFTER payment_method,
ADD FOREIGN KEY (account_id) REFERENCES fund_accounts(id) ON DELETE SET NULL;

-- 收款记录表
ALTER TABLE receipt_records
ADD COLUMN account_id INT COMMENT '收款账户ID' AFTER payment_method,
ADD FOREIGN KEY (account_id) REFERENCES fund_accounts(id) ON DELETE SET NULL;

-- 4. 插入默认账户（可选）
INSERT INTO fund_accounts (account_name, account_type, balance, status) VALUES
('现金账户', 'cash', 0.00, 'active'),
('公司银行账户', 'bank', 0.00, 'active'),
('支付宝账户', 'alipay', 0.00, 'active'),
('微信账户', 'wechat', 0.00, 'active');
