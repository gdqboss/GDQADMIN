-- ============================================
-- 会员储值/钱包系统 - 数据库设计
-- 参考: Saleor Wallet + 国内储值卡设计
-- ============================================

-- 会员钱包表
CREATE TABLE IF NOT EXISTS member_wallet (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  balance DECIMAL(12,2) DEFAULT 0.00 COMMENT '余额',
  total_recharge DECIMAL(12,2) DEFAULT 0.00 COMMENT '累计充值',
  total_withdraw DECIMAL(12,2) DEFAULT 0.00 COMMENT '累计提现',
  total_consume DECIMAL(12,2) DEFAULT 0.00 COMMENT '累计消费',
  frozen_amount DECIMAL(12,2) DEFAULT 0.00 COMMENT '冻结金额',
  password VARCHAR(64) DEFAULT NULL COMMENT '支付密码',
  status ENUM('normal', 'frozen', 'closed') DEFAULT 'normal',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员钱包';

-- 钱包变动记录表
CREATE TABLE IF NOT EXISTS wallet_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('recharge', 'withdraw', 'consume', 'refund', 'reward', 'adjust', 'freeze', 'unfreeze') NOT NULL COMMENT '类型',
  amount DECIMAL(12,2) NOT NULL COMMENT '变动金额（正负）',
  balance_before DECIMAL(12,2) NOT NULL COMMENT '变动前余额',
  balance_after DECIMAL(12,2) NOT NULL COMMENT '变动后余额',
  order_id INT DEFAULT NULL COMMENT '关联订单',
  order_type VARCHAR(50) DEFAULT NULL COMMENT '订单类型',
  remark VARCHAR(255) DEFAULT NULL COMMENT '备注',
  operator_id INT DEFAULT NULL COMMENT '操作人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_type (user_id, type),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='钱包变动记录';

-- 充值配置表
CREATE TABLE IF NOT EXISTS recharge_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '配置名称',
  amount DECIMAL(12,2) NOT NULL COMMENT '充值金额',
  gift_amount DECIMAL(12,2) DEFAULT 0.00 COMMENT '赠送金额',
  gift_ratio DECIMAL(5,2) DEFAULT 0.00 COMMENT '充值送比例（如0.1=10%）',
  points INTEGER DEFAULT 0 COMMENT '赠送积分',
  status ENUM('active', 'inactive') DEFAULT 'active',
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值配置';

-- 充值订单表
CREATE TABLE IF NOT EXISTS recharge_order (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(32) NOT NULL UNIQUE COMMENT '订单号',
  user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL COMMENT '充值金额',
  gift_amount DECIMAL(12,2) DEFAULT 0.00 COMMENT '赠送金额',
  total_amount DECIMAL(12,2) NOT NULL COMMENT '到账金额',
  payment_method ENUM('wechat', 'alipay', 'balance') DEFAULT 'wechat' COMMENT '支付方式',
  payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
  paid_at DATETIME DEFAULT NULL,
  transaction_id VARCHAR(64) DEFAULT NULL COMMENT '支付流水号',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_status (payment_status),
  INDEX idx_order_no (order_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值订单';

-- 提现申请表
CREATE TABLE IF NOT EXISTS withdraw_application (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL COMMENT '提现金额',
  fee DECIMAL(12,2) DEFAULT 0.00 COMMENT '手续费',
  real_amount DECIMAL(12,2) NOT NULL COMMENT '实际到账',
  bank_name VARCHAR(100) DEFAULT NULL COMMENT '银行名称',
  bank_account VARCHAR(50) DEFAULT NULL COMMENT '银行账号',
  bank_username VARCHAR(100) DEFAULT NULL COMMENT '开户名',
  status ENUM('pending', 'approved', 'rejected', 'paid', 'failed') DEFAULT 'pending',
  reject_reason VARCHAR(255) DEFAULT NULL,
  operator_id INT DEFAULT NULL,
  operated_at DATETIME DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  transaction_id VARCHAR(64) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现申请';

-- 初始化默认充值配置
INSERT INTO recharge_config (name, amount, gift_amount, gift_ratio, points, status, sort_order) VALUES
('充100', 100.00, 0.00, 0.00, 0, 'active', 1),
('充200', 200.00, 10.00, 0.00, 0, 'active', 2),
('充500', 500.00, 30.00, 0.00, 0, 'active', 3),
('充1000', 1000.00, 100.00, 0.00, 0, 'active', 4);

-- 初始化所有用户的钱包（如果没有的话）
INSERT IGNORE INTO member_wallet (user_id, balance, total_recharge, total_withdraw, total_consume)
SELECT id, 0.00, 0.00, 0.00, 0.00 FROM users WHERE id NOT IN (SELECT user_id FROM member_wallet);