-- 餐饮完整表结构（补全）

-- 桌台表补充列
ALTER TABLE restaurant_tables
  ADD COLUMN capacity INT UNSIGNED DEFAULT 4 AFTER table_name,
  ADD COLUMN area VARCHAR(20) DEFAULT 'main' AFTER capacity;

-- 菜品分类表
CREATE TABLE IF NOT EXISTS `restaurant_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 菜品表
CREATE TABLE IF NOT EXISTS `restaurant_dishes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `category_id` int unsigned NOT NULL DEFAULT 0,
  `unit` varchar(20) NOT NULL DEFAULT '份',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(500) DEFAULT '',
  `description` varchar(500) DEFAULT '',
  `is_available` enum('yes','no') DEFAULT 'yes',
  `status` tinyint DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 堂食/外卖订单表
CREATE TABLE IF NOT EXISTS `restaurant_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(30) NOT NULL DEFAULT '',
  `order_type` enum('dine','takeout') NOT NULL DEFAULT 'dine',
  `table_id` int unsigned DEFAULT NULL,
  `customer_count` int unsigned DEFAULT 1,
  `customer_name` varchar(50) DEFAULT '',
  `customer_phone` varchar(20) DEFAULT '',
  `delivery_address` varchar(300) DEFAULT '',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `freight_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'ordering',
  `remark` varchar(500) DEFAULT '',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_table` (`table_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 订单菜品明细表
CREATE TABLE IF NOT EXISTS `restaurant_order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL DEFAULT 0,
  `dish_id` int unsigned NOT NULL DEFAULT 0,
  `dish_name` varchar(100) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT '份',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int unsigned NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 预订表
CREATE TABLE IF NOT EXISTS `restaurant_reservations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reserve_no` varchar(30) NOT NULL DEFAULT '',
  `customer_name` varchar(50) NOT NULL DEFAULT '',
  `customer_phone` varchar(20) NOT NULL DEFAULT '',
  `table_id` int unsigned DEFAULT NULL,
  `people_count` int unsigned DEFAULT 1,
  `reserve_date` date DEFAULT NULL,
  `reserve_time` varchar(10) DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `remark` varchar(500) DEFAULT '',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reserve_no` (`reserve_no`),
  KEY `idx_date` (`reserve_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
