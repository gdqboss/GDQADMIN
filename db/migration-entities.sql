-- Migration: Add suppliers, dealers, stores tables
-- Also enhance outbound_records and qrcodes

USE gdq;

-- 供货商表
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  contact VARCHAR(50),
  phone VARCHAR(30),
  email VARCHAR(100),
  address VARCHAR(255),
  status ENUM('active','inactive') DEFAULT 'active',
  remark TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 经销商表
CREATE TABLE IF NOT EXISTS dealers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  contact VARCHAR(50),
  phone VARCHAR(30),
  email VARCHAR(100),
  address VARCHAR(255),
  region VARCHAR(50),
  status ENUM('active','inactive') DEFAULT 'active',
  remark TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 门店表
CREATE TABLE IF NOT EXISTS stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  dealer_id INT,
  contact VARCHAR(50),
  phone VARCHAR(30),
  address VARCHAR(255),
  city VARCHAR(50),
  status ENUM('active','inactive') DEFAULT 'active',
  remark TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dealer_id) REFERENCES dealers(id) ON DELETE SET NULL
);

-- 维修记录表
CREATE TABLE IF NOT EXISTS repair_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qrcode_id INT NOT NULL,
  repair_person VARCHAR(50),
  issue TEXT,
  solution TEXT,
  cost DECIMAL(10,2) DEFAULT 0,
  status ENUM('pending','in_progress','completed') DEFAULT 'pending',
  repaired_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (qrcode_id) REFERENCES qrcodes(id)
);

-- Add columns via stored procedure (MySQL-safe)
DROP PROCEDURE IF EXISTS gdq_add_column;
DELIMITER //
CREATE PROCEDURE gdq_add_column(
  IN tbl VARCHAR(64), IN col VARCHAR(64), IN col_def TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', col_def);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END//
DELIMITER ;

CALL gdq_add_column('outbound_records', 'dealer_id', 'INT DEFAULT NULL');
CALL gdq_add_column('outbound_records', 'store_id', 'INT DEFAULT NULL');
CALL gdq_add_column('inbound_records', 'supplier_id', 'INT DEFAULT NULL');
CALL gdq_add_column('qrcodes', 'supplier_id', 'INT DEFAULT NULL');
CALL gdq_add_column('qrcodes', 'dealer_id', 'INT DEFAULT NULL');
CALL gdq_add_column('qrcodes', 'store_id', 'INT DEFAULT NULL');
CALL gdq_add_column('qrcodes', 'inbound_by', 'VARCHAR(50) DEFAULT NULL');
CALL gdq_add_column('qrcodes', 'outbound_by', 'VARCHAR(50) DEFAULT NULL');
CALL gdq_add_column('qrcodes', 'after_sale_contact', 'VARCHAR(100) DEFAULT NULL');

DROP PROCEDURE IF EXISTS gdq_add_column;

-- Sample suppliers
INSERT IGNORE INTO suppliers (name, contact, phone, address) VALUES
('深圳科技有限公司', '张先生', '0755-88881111', '深圳市南山区科技园'),
('浙江优品制造', '李女士', '0571-66662222', '杭州市余杭区'),
('东莞金属制品厂', '王先生', '0769-55553333', '东莞市长安镇');

-- Sample dealers
INSERT IGNORE INTO dealers (name, contact, phone, region) VALUES
('华东经销商', '陈总', '021-77774444', '华东'),
('华南经销商', '吴总', '020-88885555', '华南'),
('华北经销商', '刘总', '010-99996666', '华北');

-- Sample stores
INSERT IGNORE INTO stores (name, dealer_id, contact, phone, city) VALUES
('上海旗舰店', 1, '小陈', '021-11112222', '上海'),
('深圳体验店', 2, '小吴', '0755-33334444', '深圳'),
('北京专卖店', 3, '小刘', '010-55556666', '北京');
