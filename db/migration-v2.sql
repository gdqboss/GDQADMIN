-- GDQ Migration v2: 商品变体 + 图片 + 角色 + H5用户
-- Run: mysql -u gdq -pRe78g0A1XcNmr1T8 gdq < migration-v2.sql

-- 商品扩展字段（用存储过程避免重复添加）
DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER //
CREATE PROCEDURE add_col_if_missing()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='image_main') THEN
    ALTER TABLE products ADD COLUMN image_main VARCHAR(255) DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='images') THEN
    ALTER TABLE products ADD COLUMN images JSON DEFAULT NULL COMMENT '图片数组';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='external_links') THEN
    ALTER TABLE products ADD COLUMN external_links JSON DEFAULT NULL COMMENT '[{platform,url}]';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='require_qrcode') THEN
    ALTER TABLE products ADD COLUMN require_qrcode TINYINT(1) DEFAULT 0 COMMENT '出库必须关联一物一码';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='outbound_items' AND COLUMN_NAME='qrcode_id') THEN
    ALTER TABLE outbound_items ADD COLUMN qrcode_id INT DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone') THEN
    ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL;
  END IF;
END //
DELIMITER ;
CALL add_col_if_missing();
DROP PROCEDURE IF EXISTS add_col_if_missing;

-- 修改 users.role 为 VARCHAR（兼容旧数据）
ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'operator';

-- 规格组（如"颜色"、"尺寸"）
CREATE TABLE IF NOT EXISTS product_specs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  name VARCHAR(50) NOT NULL,
  sort_order INT DEFAULT 0,
  INDEX idx_pid (product_id)
);

-- 规格值（如"黑色"、"S码"）
CREATE TABLE IF NOT EXISTS product_spec_values (
  id INT AUTO_INCREMENT PRIMARY KEY,
  spec_id INT NOT NULL,
  value VARCHAR(100) NOT NULL,
  sort_order INT DEFAULT 0
);

-- SKU 变体（规格组合，独立价格/库存）
CREATE TABLE IF NOT EXISTS product_skus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  sku VARCHAR(100) NOT NULL UNIQUE,
  specs JSON NOT NULL COMMENT '{"颜色":"黑色"}',
  purchase_price DECIMAL(10,2),
  sale_price DECIMAL(10,2),
  stock INT DEFAULT 0,
  status ENUM('active','discontinued') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pid (product_id)
);

-- 角色表（支持动态角色）
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(100) NOT NULL,
  permissions JSON DEFAULT NULL COMMENT '可访问的页面key数组',
  is_system TINYINT(1) DEFAULT 0 COMMENT '1=内置不可删',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO roles (name, label, permissions, is_system) VALUES
  ('admin',     '超级管理员', NULL, 1),
  ('manager',   '经理',       JSON_ARRAY('dashboard','wecom','ai-automation','oa','qrcode','products','in-out','warehouses','alerts','approvals','reports','suppliers','dealers','stores'), 1),
  ('operator',  '操作员',     JSON_ARRAY('dashboard','qrcode','products','in-out'), 1),
  ('custom',    '自定义',     JSON_ARRAY(), 1),
  ('supplier',  '供货商',     JSON_ARRAY('dashboard','products'), 0),
  ('dealer',    '经销商',     JSON_ARRAY('dashboard','products'), 0),
  ('store',     '门店',       JSON_ARRAY('dashboard','products','in-out'), 0),
  ('service',   '客服',       JSON_ARRAY('dashboard','qrcode'), 0),
  ('warehouse', '仓库员',     JSON_ARRAY('dashboard','in-out','warehouses'), 0);

-- H5 独立用户表（手机号登录）
CREATE TABLE IF NOT EXISTS h5_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50),
  phone VARCHAR(20) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  parent_id INT DEFAULT NULL COMMENT '推荐人',
  status ENUM('active','disabled') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 维修记录表（如未存在）
CREATE TABLE IF NOT EXISTS repair_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qrcode_id INT NOT NULL,
  repair_person VARCHAR(100),
  issue TEXT,
  solution TEXT,
  status ENUM('pending','completed') DEFAULT 'pending',
  repaired_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_qrcode (qrcode_id)
);
