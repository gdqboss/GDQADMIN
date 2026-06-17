-- ============================================
-- 优惠券/满减促销系统 - 数据库设计
-- 参考: MedusaJS Promotion Engine + Saleor GiftCard
-- ============================================

-- 优惠券主表
CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE COMMENT '优惠券码',
  name VARCHAR(100) NOT NULL COMMENT '优惠券名称',
  description TEXT COMMENT '描述',
  type ENUM('percentage', 'fixed', 'buy_x_get_y', 'manjian') DEFAULT 'fixed' COMMENT '类型: 折扣/立减/买X送Y/满减',
  value DECIMAL(10,2) NOT NULL COMMENT '优惠值(百分比或金额)',
  min_order_amount DECIMAL(10,2) DEFAULT 0 COMMENT '最低订单金额',
  max_discount_amount DECIMAL(10,2) DEFAULT NULL COMMENT '最高优惠金额',
  start_time DATETIME NOT NULL COMMENT '开始时间',
  end_time DATETIME NOT NULL COMMENT '结束时间',
  total_quantity INT DEFAULT NULL COMMENT '发放总量，NULL=不限',
  used_count INT DEFAULT 0 COMMENT '已使用数量',
  per_user_limit INT DEFAULT 1 COMMENT '每人限领次数',
  applicable_type ENUM('all', 'categories', 'products', 'stores') DEFAULT 'all' COMMENT '适用范围',
  status ENUM('active', 'paused', 'expired') DEFAULT 'active' COMMENT '状态',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_status_time (status, start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券主表';

-- 促销规则表（满X条件）
CREATE TABLE IF NOT EXISTS coupon_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL,
  rule_type ENUM('min_quantity', 'min_amount', 'first_order', 'new_user') DEFAULT 'min_amount' COMMENT '规则类型',
  rule_value DECIMAL(10,2) NOT NULL COMMENT '规则阈值',
  reward_type ENUM('discount', 'gift', 'free_shipping', 'points') DEFAULT 'discount' COMMENT '奖励类型',
  reward_value DECIMAL(10,2) DEFAULT NULL COMMENT '奖励值',
  reward_gift_product_id INT DEFAULT NULL COMMENT '赠品商品ID',
  sort_order INT DEFAULT 0 COMMENT '优先级',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  INDEX idx_coupon_id (coupon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='促销规则表';

-- 适用范围关联表（品类/商品/门店）
CREATE TABLE IF NOT EXISTS coupon_applicable (
  id INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL,
  applicable_type ENUM('category', 'product', 'store') NOT NULL COMMENT '类型',
  applicable_id INT NOT NULL COMMENT '对应ID（分类ID/商品ID/门店ID）',
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  INDEX idx_coupon_type (coupon_id, applicable_type),
  UNIQUE KEY uk_coupon_applicable (coupon_id, applicable_type, applicable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券适用范围';

-- 会员领券记录
CREATE TABLE IF NOT EXISTS coupon_user (
  id INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('unused', 'used', 'expired') DEFAULT 'unused' COMMENT '状态',
  received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME DEFAULT NULL,
  used_order_id INT DEFAULT NULL COMMENT '使用的订单ID',
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_status (user_id, status),
  UNIQUE KEY uk_user_coupon (user_id, coupon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员领券记录';

-- 使用统计（方便查询）
CREATE TABLE IF NOT EXISTS coupon_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL UNIQUE,
  total_received INT DEFAULT 0 COMMENT '发放总数',
  total_used INT DEFAULT 0 COMMENT '使用总数',
  total_discount DECIMAL(12,2) DEFAULT 0 COMMENT '优惠总金额',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券统计';

-- ============================================
-- 初始化数据
-- ============================================

-- 示例优惠券：新人满100减20
INSERT INTO coupons (code, name, description, type, value, min_order_amount, max_discount_amount, start_time, end_time, total_quantity, per_user_limit, applicable_type, status)
VALUES 
('NEWBIE100', '新人专享满减', '新用户满100减20', 'manjian', 20.00, 100.00, 50.00, '2025-01-01 00:00:00', '2026-12-31 23:59:59', NULL, 1, 'all', 'active');

INSERT INTO coupon_rules (coupon_id, rule_type, rule_value, reward_type, reward_value, sort_order)
VALUES (1, 'min_amount', 100.00, 'discount', 20.00, 1);

-- 示例：满200打8折
INSERT INTO coupons (code, name, description, type, value, min_order_amount, max_discount_amount, start_time, end_time, total_quantity, per_user_limit, applicable_type, status)
VALUES 
('SUMMER20', '夏季狂欢8折', '满200元享8折优惠', 'percentage', 20.00, 200.00, 100.00, '2025-06-01 00:00:00', '2026-08-31 23:59:59', 1000, 1, 'all', 'active');

INSERT INTO coupon_rules (coupon_id, rule_type, rule_value, reward_type, reward_value, sort_order)
VALUES (2, 'min_amount', 200.00, 'discount', 20.00, 1);

-- 示例：指定品类优惠券
INSERT INTO coupons (code, name, description, type, value, min_order_amount, start_time, end_time, total_quantity, per_user_limit, applicable_type, status)
VALUES 
('CATEGORY50', '数码专场50元券', '手机/电脑品类专享', 'fixed', 50.00, 300.00, '2025-01-01 00:00:00', '2026-12-31 23:59:59', 500, 1, 'categories', 'active');

-- 初始化统计
INSERT INTO coupon_stats (coupon_id, total_received, total_used, total_discount)
SELECT id, COALESCE(total_quantity, 0), used_count, 0 FROM coupons;