-- 秒杀模块数据库
-- 6张表

-- 秒杀活动表
CREATE TABLE IF NOT EXISTS seckill_activities (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL COMMENT '活动名称',
  description TEXT COMMENT '活动描述',
  start_time DATETIME NOT NULL COMMENT '开始时间',
  end_time DATETIME NOT NULL COMMENT '结束时间',
  status ENUM('pending','active','paused','ended') DEFAULT 'pending' COMMENT '状态',
  rules JSON COMMENT '规则：每人限购/最低购买/叠加优惠',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 秒杀商品表（活动内的商品）
CREATE TABLE IF NOT EXISTS seckill_products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  activity_id INT NOT NULL COMMENT '所属活动',
  product_id INT COMMENT '关联商品',
  sku_id INT COMMENT '关联SKU',
  seckill_price DECIMAL(10,2) NOT NULL COMMENT '秒杀价',
  original_price DECIMAL(10,2) NOT NULL COMMENT '原价',
  stock INT NOT NULL DEFAULT 0 COMMENT '秒杀库存',
  sold INT NOT NULL DEFAULT 0 COMMENT '已售数量',
  max_per_user INT NOT NULL DEFAULT 1 COMMENT '每人限购',
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES seckill_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 秒杀订单表
CREATE TABLE IF NOT EXISTS seckill_orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_no VARCHAR(64) UNIQUE NOT NULL COMMENT '订单号',
  activity_id INT NOT NULL,
  product_id INT NOT NULL,
  sku_id INT,
  user_id INT NOT NULL COMMENT '下单用户',
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL COMMENT '秒杀价',
  total_amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','cancelled','refunded') DEFAULT 'pending',
  pay_time DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES seckill_activities(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 秒杀收藏/提醒表
CREATE TABLE IF NOT EXISTS seckill_follows (
  id INT PRIMARY KEY AUTO_INCREMENT,
  activity_id INT NOT NULL,
  user_id INT NOT NULL,
  notify_sent TINYINT DEFAULT 0 COMMENT '是否已发提醒',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES seckill_activities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_activity_user (activity_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 秒杀统计表
CREATE TABLE IF NOT EXISTS seckill_stats (
  id INT PRIMARY KEY AUTO_INCREMENT,
  activity_id INT NOT NULL UNIQUE,
  total_views INT DEFAULT 0 COMMENT '浏览量',
  total_orders INT DEFAULT 0 COMMENT '下单数',
  total_amount DECIMAL(12,2) DEFAULT 0 COMMENT '成交金额',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES seckill_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 秒杀配置表
CREATE TABLE IF NOT EXISTS seckill_config (
  id INT PRIMARY KEY AUTO_INCREMENT,
  `key` VARCHAR(64) UNIQUE NOT NULL,
  value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO seckill_config (`key`, value) VALUES
  ('auto_start', 'true'),
  ('seckill_limit_per_user', '5');
