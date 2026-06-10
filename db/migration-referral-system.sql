-- 餐饮推荐裂变模块：桌码 + 推荐奖励 + 消费券
-- Run: mysql -u gdq -pRe78g0A1XcNmr1T8 gdq < migration-referral-system.sql

-- ============================================================
-- 1. 桌码表 restaurant_tables
-- ============================================================
CREATE TABLE IF NOT EXISTS restaurant_tables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_no VARCHAR(20) NOT NULL UNIQUE COMMENT '桌号，如 A01',
  table_name VARCHAR(50) DEFAULT NULL COMMENT '桌名，如 靠窗A01',
  qr_token VARCHAR(64) NOT NULL UNIQUE COMMENT '二维码token',
  qr_url VARCHAR(500) DEFAULT NULL COMMENT '二维码图片URL',
  status ENUM('active','inactive','occupied') DEFAULT 'active' COMMENT '状态',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (qr_token),
  INDEX idx_status (status)
);

-- ============================================================
-- 2. 推荐奖励规则 referral_rewards
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_rewards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT DEFAULT NULL COMMENT '关联商品ID，NULL表示通用规则',
  product_name VARCHAR(200) DEFAULT NULL COMMENT '商品名称（冗余）',
  required_heads INT NOT NULL DEFAULT 3 COMMENT '需要人数',
  reward_type ENUM('coupon','score','cash') DEFAULT 'coupon' COMMENT '奖励类型',
  reward_coupon_id INT DEFAULT NULL COMMENT '奖励消费券ID',
  reward_amount DECIMAL(10,2) DEFAULT NULL COMMENT '奖励积分/现金额',
  reward_desc VARCHAR(255) DEFAULT NULL COMMENT '奖励说明',
  valid_days INT DEFAULT 30 COMMENT '奖励有效期（天）',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_product (product_id),
  INDEX idx_status (status)
);

-- ============================================================
-- 3. 消费券 coupons（参照上海ddwx_coupon精简版）
-- ============================================================
CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '券名称',
  type ENUM('cash','discount','product') DEFAULT 'cash' COMMENT 'cash=满减券 discount=折扣 product=实物',
  -- 满减券
  money DECIMAL(10,2) DEFAULT NULL COMMENT '满减金额',
  min_price DECIMAL(10,2) DEFAULT 0 COMMENT '使用门槛',
  -- 折扣券
  discount_rate DECIMAL(5,2) DEFAULT NULL COMMENT '折扣率，如 0.8=8折',
  -- 实物券
  product_id INT DEFAULT NULL COMMENT '指定商品ID',
  product_name VARCHAR(200) DEFAULT NULL COMMENT '商品名称（冗余）',
  -- 时间
  start_time DATETIME DEFAULT NULL,
  end_time DATETIME DEFAULT NULL,
  valid_days INT DEFAULT 30 COMMENT '发券后有效期（天）',
  -- 限制
  stock INT DEFAULT -1 COMMENT '库存，-1=不限',
  get_count INT DEFAULT 0 COMMENT '已发放数',
  per_limit INT DEFAULT 1 COMMENT '每人限领',
  -- 适用
  apply_all TINYINT(1) DEFAULT 1 COMMENT '1=全场通用 0=指定商品',
  product_ids JSON DEFAULT NULL COMMENT '适用商品ID列表',
  category_ids JSON DEFAULT NULL COMMENT '适用分类ID列表',
  -- 状态
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_type (type)
);

-- ============================================================
-- 4. 推荐记录 referral_records
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_id INT NOT NULL COMMENT '桌码ID',
  referrer_h5_user_id INT DEFAULT NULL COMMENT '推荐人H5用户ID',
  invited_h5_user_id INT DEFAULT NULL COMMENT '被邀请人H5用户ID',
  invited_phone VARCHAR(20) DEFAULT NULL COMMENT '被邀请人手机号（未注册时）',
  invited_name VARCHAR(50) DEFAULT NULL COMMENT '被邀请人姓名',
  qr_token VARCHAR(64) NOT NULL COMMENT '扫码桌码token',
  status ENUM('scanned','paid','rewarded','expired') DEFAULT 'scanned' COMMENT 'scanned=扫码 paid=消费 rewarded=已奖励',
  order_id INT DEFAULT NULL COMMENT '关联订单ID',
  order_no VARCHAR(64) DEFAULT NULL COMMENT '订单号',
  order_amount DECIMAL(10,2) DEFAULT NULL COMMENT '订单金额',
  paid_at DATETIME DEFAULT NULL COMMENT '消费时间',
  reward_given TINYINT(1) DEFAULT 0 COMMENT '是否已发奖励',
  reward_id INT DEFAULT NULL COMMENT '发出的优惠券ID',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_table (table_id),
  INDEX idx_referrer (referrer_h5_user_id),
  INDEX idx_invited (invited_h5_user_id),
  INDEX idx_status (status),
  INDEX idx_token (qr_token)
);

-- ============================================================
-- 5. 会员消费券 user_coupons
-- ============================================================
CREATE TABLE IF NOT EXISTS user_coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '用户ID（h5_users.id）',
  coupon_id INT NOT NULL COMMENT '优惠券ID',
  coupon_name VARCHAR(100) DEFAULT NULL COMMENT '券名称（冗余）',
  type ENUM('cash','discount','product') DEFAULT 'cash',
  -- 券内容
  money DECIMAL(10,2) DEFAULT NULL,
  min_price DECIMAL(10,2) DEFAULT 0,
  discount_rate DECIMAL(5,2) DEFAULT NULL,
  product_id INT DEFAULT NULL,
  product_name VARCHAR(200) DEFAULT NULL,
  -- 时间
  valid_start DATETIME DEFAULT NULL,
  valid_end DATETIME DEFAULT NULL,
  -- 状态
  status ENUM('unused','used','expired') DEFAULT 'unused' COMMENT 'unused=未用 used=已用 expired=过期',
  used_order_id INT DEFAULT NULL COMMENT '使用的订单ID',
  used_at DATETIME DEFAULT NULL,
  source ENUM('reward','self_get','system') DEFAULT 'reward' COMMENT '来源：reward=推荐奖励 self_get=自领 system=系统发放',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_valid (valid_end)
);

-- ============================================================
-- 6. 推荐人汇总视图（方便查询每个推荐人的业绩）
-- ============================================================
CREATE OR REPLACE VIEW v_referrer_summary AS
SELECT
  rr.referrer_h5_user_id,
  hu.name AS referrer_name,
  hu.phone AS referrer_phone,
  COUNT(*) AS total_invited,
  SUM(CASE WHEN rr.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
  SUM(CASE WHEN rr.status = 'rewarded' THEN 1 ELSE 0 END) AS rewarded_count,
  COUNT(DISTINCT CASE WHEN rr.status = 'paid' THEN rr.invited_h5_user_id END) AS unique_paid
FROM referral_records rr
LEFT JOIN h5_users hu ON rr.referrer_h5_user_id = hu.id
GROUP BY rr.referrer_h5_user_id;

-- ============================================================
-- 默认插入示例数据
-- ============================================================
INSERT IGNORE INTO restaurant_tables (table_no, table_name, qr_token, status) VALUES
  ('A01', '靠窗A01', CONCAT('TBL', UNIX_TIMESTAMP(), FLOOR(RAND()*10000)), 'active'),
  ('A02', '靠窗A02', CONCAT('TBL', UNIX_TIMESTAMP()+1, FLOOR(RAND()*10000)), 'active'),
  ('B01', '大桌B01', CONCAT('TBL', UNIX_TIMESTAMP()+2, FLOOR(RAND()*10000)), 'active')
ON DUPLICATE KEY UPDATE table_name=VALUES(table_name);