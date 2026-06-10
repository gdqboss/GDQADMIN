-- =====================================================
-- 积分商城 & 优惠券 模块数据库结构
-- =====================================================

USE gdq;

-- ── 积分商品表 ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS score_products (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(200) NOT NULL COMMENT '商品名称',
  description   TEXT COMMENT '商品描述',
  image_main    VARCHAR(500) COMMENT '主图',
  images        JSON COMMENT '图片列表',
  score_price   INT NOT NULL DEFAULT 0 COMMENT '积分价格',
  stock         INT NOT NULL DEFAULT 0 COMMENT '库存',
  safe_stock    INT NOT NULL DEFAULT 0 COMMENT '安全库存',
  status        ENUM('active','inactive','sold_out') DEFAULT 'active',
  is_recommend  TINYINT(1) DEFAULT 0 COMMENT '是否推荐',
  sort_order    INT DEFAULT 0 COMMENT '排序',
  exchange_count INT DEFAULT 0 COMMENT '已兑换次数',
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_is_recommend (is_recommend),
  INDEX idx_sort_order (sort_order)
);

-- ── 积分订单/兑换记录表 ─────────────────────────────────
CREATE TABLE IF NOT EXISTS score_orders (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  order_no          VARCHAR(32) NOT NULL UNIQUE COMMENT '兑换单号',
  user_id           INT NOT NULL COMMENT '用户ID',
  score_product_id  INT NOT NULL COMMENT '积分商品ID',
  product_name      VARCHAR(200) COMMENT '商品名称（快照）',
  product_image     VARCHAR(500) COMMENT '商品图片（快照）',
  score_price       INT NOT NULL COMMENT '消耗积分（快照）',
  quantity          INT NOT NULL DEFAULT 1 COMMENT '兑换数量',
  total_score       INT NOT NULL COMMENT '总消耗积分',
  address_id        INT COMMENT '收货地址ID',
  receiver_name     VARCHAR(100) COMMENT '收货人（快照）',
  receiver_phone    VARCHAR(20) COMMENT '电话（快照）',
  receiver_address  VARCHAR(500) COMMENT '地址（快照）',
  remark            VARCHAR(500) COMMENT '用户备注',
  status            ENUM('pending','processing','shipped','completed','cancelled') DEFAULT 'pending',
  shipped_at        DATETIME COMMENT '发货时间',
  completed_at      DATETIME COMMENT '完成时间',
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
);

-- ── 用户积分变动记录表 ──────────────────────────────────
CREATE TABLE IF NOT EXISTS score_records (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL COMMENT '用户ID',
  type        ENUM('earn','spend','expire','refund') NOT NULL COMMENT '类型',
  score       INT NOT NULL COMMENT '积分变动数量（正数）',
  balance     INT NOT NULL COMMENT '变动后余额',
  source      VARCHAR(50) COMMENT '来源: order/reward/sign/admin/refund',
  source_id   INT COMMENT '关联ID（如订单ID）',
  remark      VARCHAR(200) COMMENT '说明',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_type (type),
  INDEX idx_created_at (created_at)
);

-- ── 积分规则配置表 ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS score_config (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  `key`         VARCHAR(50) NOT NULL UNIQUE COMMENT '配置项',
  value         VARCHAR(200) COMMENT '配置值',
  description   VARCHAR(200) COMMENT '说明',
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 初始化默认积分规则
INSERT IGNORE INTO score_config (`key`, value, description) VALUES
  ('sign_score', '10', '每日签到积分'),
  ('order_ratio', '1', '订单积分比例（每消费1元得1积分）'),
  ('sign_limit', '1', '每日签到次数上限'),
  ('first_order_score', '100', '首次下单奖励积分');

-- ── 管理员优惠券表（后台维护）────────────────────────────
CREATE TABLE IF NOT EXISTS admin_coupons (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL COMMENT '优惠券名称',
  type            ENUM('cash','discount','shipping') DEFAULT 'cash' COMMENT '类型：现金券/折扣券/运费券',
  money           DECIMAL(10,2) COMMENT '优惠金额/折扣值',
  min_amount      DECIMAL(10,2) DEFAULT 0.00 COMMENT '满减门槛',
  discount_rate   DECIMAL(5,2) COMMENT '折扣率（如0.90表示9折）',
  shipping_fee    DECIMAL(10,2) DEFAULT 0.00 COMMENT '运费券免除金额',
  total_count     INT NOT NULL DEFAULT 0 COMMENT '发放总数量',
  remain_count    INT NOT NULL DEFAULT 0 COMMENT '剩余数量',
  per_limit      INT DEFAULT 1 COMMENT '每人限领数量',
  start_time      DATETIME COMMENT '开始时间',
  end_time        DATETIME COMMENT '结束时间',
  valid_days      INT DEFAULT 30 COMMENT '领取后有效天数',
  apply_all       TINYINT(1) DEFAULT 1 COMMENT '适用于全部商品',
  product_ids     JSON COMMENT '限商品ID列表',
  category_ids    JSON COMMENT '限分类ID列表',
  status          ENUM('active','inactive','expired') DEFAULT 'active',
  created_by      INT COMMENT '创建人ID',
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_end_time (end_time)
);

-- ── 用户领取的管理员优惠券 ───────────────────────────────
CREATE TABLE IF NOT EXISTS user_admin_coupons (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL COMMENT '用户ID',
  coupon_id       INT NOT NULL COMMENT '优惠券ID（admin_coupons）',
  coupon_name     VARCHAR(100) COMMENT '优惠券名称（快照）',
  type            ENUM('cash','discount','shipping') COMMENT '类型（快照）',
  money           DECIMAL(10,2) COMMENT '优惠金额（快照）',
  min_amount      DECIMAL(10,2) COMMENT '满减门槛（快照）',
  discount_rate   DECIMAL(5,2) COMMENT '折扣率（快照）',
  shipping_fee    DECIMAL(10,2) COMMENT '运费券免除（快照）',
  valid_start     DATETIME COMMENT '有效开始时间',
  valid_end       DATETIME COMMENT '有效结束时间',
  status          ENUM('unused','used','expired') DEFAULT 'unused' COMMENT '状态',
  used_order_id   INT COMMENT '使用的订单ID',
  used_at         DATETIME COMMENT '使用时间',
  received_at     DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '领取时间',
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_valid_end (valid_end)
);

-- ── 用户每日签到记录 ───────────────────────────────────
CREATE TABLE IF NOT EXISTS score_sign_records (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL COMMENT '用户ID',
  sign_date   DATE NOT NULL COMMENT '签到日期',
  score       INT NOT NULL DEFAULT 0 COMMENT '获得积分',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_date (user_id, sign_date)
);

-- ── 积分商品分类表 ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS score_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50) NOT NULL COMMENT '分类名称',
  icon        VARCHAR(100) COMMENT '图标',
  sort_order  INT DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 初始化默认分类
INSERT IGNORE INTO score_categories (name, icon, sort_order) VALUES
  ('全部', 'apps', 0),
  ('实物礼品', 'card_giftcard', 1),
  ('虚拟卡券', 'confirmation_number', 2),
  ('生活服务', 'home_repair_service', 3);
