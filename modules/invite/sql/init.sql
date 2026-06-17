-- ============================================
-- 邀请返现/红包系统 - 数据库设计
-- 参考: Saleor Invite + 微信红包设计
-- ============================================

-- 邀请返现配置
CREATE TABLE IF NOT EXISTS invite_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '活动名称',
  reward_type ENUM('cash', 'coupon', 'points', 'balance') DEFAULT 'cash' COMMENT '奖励类型',
  reward_amount DECIMAL(12,2) DEFAULT 0 COMMENT '奖励金额/积分',
  reward_coupon_id INT DEFAULT NULL COMMENT '奖励优惠券ID',
  min_recharge DECIMAL(12,2) DEFAULT 0 COMMENT '最低充值门槛',
  status ENUM('active', 'inactive') DEFAULT 'active',
  start_time DATETIME DEFAULT NULL,
  end_time DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请返现配置';

-- 邀请关系表
CREATE TABLE IF NOT EXISTS invite_relation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inviter_id INT NOT NULL COMMENT '邀请人ID',
  invitee_id INT NOT NULL COMMENT '被邀请人ID',
  invite_code VARCHAR(20) NOT NULL COMMENT '邀请码',
  reward_status ENUM('pending', 'rewarded', 'expired') DEFAULT 'pending' COMMENT '奖励状态',
  reward_amount DECIMAL(12,2) DEFAULT 0 COMMENT '已奖励金额',
  rewarded_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_invitee (invitee_id),
  INDEX idx_inviter (inviter_id),
  INDEX idx_invite_code (invite_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请关系';

-- 红包配置
CREATE TABLE IF NOT EXISTS redpacket_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '活动名称',
  type ENUM('random', 'fixed', 'balance') DEFAULT 'random' COMMENT '红包类型: 随机/固定/余额',
  total_amount DECIMAL(12,2) NOT NULL COMMENT '红包总金额',
  total_count INT NOT NULL COMMENT '红包总个数',
  per_min DECIMAL(12,2) DEFAULT 0.01 COMMENT '单个最低金额',
  per_max DECIMAL(12,2) DEFAULT NULL COMMENT '单个最高金额',
  total_remain DECIMAL(12,2) DEFAULT 0 COMMENT '剩余金额',
  count_remain INT DEFAULT 0 COMMENT '剩余个数',
  threshold DECIMAL(12,2) DEFAULT 0 COMMENT '使用门槛',
  applicable_type ENUM('all', 'categories', 'products') DEFAULT 'all' COMMENT '适用范围',
  status ENUM('active', 'paused', 'finished') DEFAULT 'active',
  start_time DATETIME DEFAULT NULL,
  end_time DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包配置';

-- 红包发放记录
CREATE TABLE IF NOT EXISTS redpacket_record (
  id INT AUTO_INCREMENT PRIMARY KEY,
  config_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL COMMENT '领取金额',
  order_id INT DEFAULT NULL COMMENT '使用的订单',
  used_at DATETIME DEFAULT NULL,
  status ENUM('unused', 'used', 'expired') DEFAULT 'unused',
  expire_time DATETIME NOT NULL COMMENT '过期时间',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (config_id) REFERENCES redpacket_config(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包领取记录';

-- 用户邀请码
CREATE TABLE IF NOT EXISTS user_invite_code (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  code VARCHAR(20) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户邀请码';

-- 邀请返现日志
CREATE TABLE IF NOT EXISTS invite_reward_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inviter_id INT NOT NULL,
  invitee_id INT NOT NULL,
  type ENUM('cash', 'coupon', 'points', 'balance') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  order_id INT DEFAULT NULL COMMENT '触发订单',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_inviter (inviter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请返现日志';

-- 初始化：默认邀请配置
INSERT INTO invite_config (name, reward_type, reward_amount, min_recharge, status) VALUES
('新用户邀请奖励', 'balance', 10.00, 100.00, 'active'),
('老带新返现', 'balance', 5.00, 50.00, 'active');

-- 初始化：所有用户生成邀请码
INSERT IGNORE INTO user_invite_code (user_id, code)
SELECT id, CONCAT('INV', LPAD(id, 6, '0'), SUBSTRING(MD5(RAND()), 1, 4)) FROM users;