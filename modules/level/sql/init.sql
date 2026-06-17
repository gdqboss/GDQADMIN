-- ============================================
-- 会员等级体系 - 数据库设计
-- 参考: 麦当劳/星巴克会员体系
-- ============================================

-- 会员等级配置表
CREATE TABLE IF NOT EXISTS member_level (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL COMMENT '等级名称',
  icon VARCHAR(255) DEFAULT NULL COMMENT '等级图标URL',
  min_points INT DEFAULT 0 COMMENT '最低成长值',
  max_points INT DEFAULT NULL COMMENT '最高成长值（NULL表示无上限）',
  discount_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT '购物折扣率（0.05=95折）',
  points_ratio DECIMAL(5,2) DEFAULT 1.00 COMMENT '积分获取比例',
  birthday_double TINYINT(1) DEFAULT 0 COMMENT '生日双倍积分',
  free_shipping TINYINT(1) DEFAULT 0 COMMENT '免运费',
  exclusive_access TINYINT(1) DEFAULT 0 COMMENT '专属活动参与权',
  priority_customer TINYINT(1) DEFAULT 0 COMMENT '优先客服',
  status ENUM('active', 'inactive') DEFAULT 'active',
  is_default TINYINT(1) DEFAULT 0 COMMENT '是否为默认等级',
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员等级配置';

-- 用户等级信息
CREATE TABLE IF NOT EXISTS member_level_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  level_id INT NOT NULL COMMENT '当前等级ID',
  current_points INT DEFAULT 0 COMMENT '当前成长值',
  total_points INT DEFAULT 0 COMMENT '历史累计成长值',
  level_up_growth DECIMAL(12,2) DEFAULT 0 COMMENT '距离下一级还需',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (level_id) REFERENCES member_level(id),
  INDEX idx_level (level_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户等级信息';

-- 成长值变动记录
CREATE TABLE IF NOT EXISTS member_growth_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('order', 'comment', 'sign', 'review', 'birthday', 'manual', 'expire') NOT NULL COMMENT '来源类型',
  change_amount INT NOT NULL COMMENT '变动值（正负）',
  points_before INT NOT NULL COMMENT '变动前',
  points_after INT NOT NULL COMMENT '变动后',
  order_id INT DEFAULT NULL COMMENT '关联订单',
  remark VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_type (user_id, type),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='成长值变动记录';

-- 会员权益日志
CREATE TABLE IF NOT EXISTS member_benefit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  benefit_type ENUM('discount', 'points', 'free_shipping', 'birthday_gift', 'exclusive') NOT NULL,
  order_id INT DEFAULT NULL,
  amount DECIMAL(12,2) DEFAULT 0 COMMENT '优惠/获得金额',
  remark VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_type (user_id, benefit_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员权益使用记录';

-- 初始化默认等级
INSERT INTO member_level (name, min_points, max_points, discount_rate, points_ratio, birthday_double, free_shipping, exclusive_access, priority_customer, status, is_default, sort_order) VALUES
('普通会员', 0, 999, 0.00, 1.00, 0, 0, 0, 0, 'active', 1, 1),
('银卡会员', 1000, 4999, 0.03, 1.20, 1, 0, 0, 0, 'active', 0, 2),
('金卡会员', 5000, 19999, 0.05, 1.50, 1, 1, 0, 0, 'active', 0, 3),
('黑金会员', 20000, NULL, 0.10, 2.00, 1, 1, 1, 1, 'active', 0, 4);

-- 初始化所有用户的等级信息
INSERT IGNORE INTO member_level_info (user_id, level_id, current_points, total_points)
SELECT u.id, (SELECT id FROM member_level WHERE is_default = 1 LIMIT 1), 0, 0 
FROM users u WHERE u.id NOT IN (SELECT user_id FROM member_level_info);

-- 成长值规则配置
CREATE TABLE IF NOT EXISTS growth_rule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50) NOT NULL UNIQUE COMMENT '行为类型',
  name VARCHAR(100) NOT NULL COMMENT '行为名称',
  points INT NOT NULL COMMENT '获得成长值',
  daily_limit INT DEFAULT NULL COMMENT '每日上限',
  status ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='成长值规则';

-- 初始化成长值规则
INSERT INTO growth_rule (type, name, points, daily_limit, status) VALUES
('order', '消费获得', 100, NULL, 'active'),
('comment', '评价商品', 20, 5, 'active'),
('sign', '每日签到', 5, 1, 'active'),
('review', '晒图评价', 50, 3, 'active'),
('birthday', '生日福利', 500, 1, 'active');