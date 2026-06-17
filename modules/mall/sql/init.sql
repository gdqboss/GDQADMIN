-- 商城核心表结构
-- 使用 utf8mb4 字符集

-- 收货地址表
CREATE TABLE IF NOT EXISTS `mall_addresses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `receiver_name` varchar(50) NOT NULL DEFAULT '',
  `receiver_phone` varchar(20) NOT NULL DEFAULT '',
  `province` varchar(50) NOT NULL DEFAULT '',
  `city` varchar(50) NOT NULL DEFAULT '',
  `district` varchar(50) NOT NULL DEFAULT '',
  `detail` varchar(200) NOT NULL DEFAULT '',
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 购物车表
CREATE TABLE IF NOT EXISTS `mall_cart` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `product_id` int unsigned NOT NULL DEFAULT 0,
  `sku_id` int unsigned NOT NULL DEFAULT 0,
  `quantity` int unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_product_sku` (`user_id`, `product_id`, `sku_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 商城订单表
CREATE TABLE IF NOT EXISTS `mall_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL DEFAULT '',
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `user_name` varchar(50) NOT NULL DEFAULT '',
  `user_phone` varchar(20) NOT NULL DEFAULT '',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `freight_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pay_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `receiver_name` varchar(50) NOT NULL DEFAULT '',
  `receiver_phone` varchar(20) NOT NULL DEFAULT '',
  `receiver_address` varchar(300) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'pending_pay',
  `pay_time` datetime DEFAULT NULL,
  `ship_time` datetime DEFAULT NULL,
  `receive_time` datetime DEFAULT NULL,
  `remark` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 商城订单明细表
CREATE TABLE IF NOT EXISTS `mall_order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL DEFAULT 0,
  `product_id` int unsigned NOT NULL DEFAULT 0,
  `sku_id` int unsigned NOT NULL DEFAULT 0,
  `product_name` varchar(200) NOT NULL DEFAULT '',
  `sku_name` varchar(100) NOT NULL DEFAULT '',
  `image` varchar(300) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int unsigned NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 商品浏览记录
CREATE TABLE IF NOT EXISTS `mall_browsing_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `product_id` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
