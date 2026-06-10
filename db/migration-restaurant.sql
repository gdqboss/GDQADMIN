-- =====================================================
-- 餐饮管理模块数据库结构
-- =====================================================

USE gdq;

-- 桌台表
CREATE TABLE IF NOT EXISTS restaurant_tables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_no VARCHAR(20) NOT NULL COMMENT '桌台编号',
  table_name VARCHAR(50) NOT NULL COMMENT '桌台名称',
  capacity INT DEFAULT 4 COMMENT '容纳人数',
  area VARCHAR(30) DEFAULT 'main' COMMENT '区域：main/dining/vip',
  status ENUM('idle','occupied','reserved','locked') DEFAULT 'idle' COMMENT '状态：空闲/占用/预订/锁定',
  current_order_id INT DEFAULT NULL COMMENT '当前进行中的订单ID',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 菜品分类表
CREATE TABLE IF NOT EXISTS dish_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL COMMENT '分类名称',
  sort_order INT DEFAULT 0 COMMENT '排序',
  status ENUM('active','disabled') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 菜品表
CREATE TABLE IF NOT EXISTS dishes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '菜品名称',
  category_id INT COMMENT FOREIGN KEY REFERENCES dish_categories(id),
  unit VARCHAR(20) DEFAULT '份' COMMENT '单位',
  price DECIMAL(10,2) NOT NULL COMMENT '单价',
  image VARCHAR(255) COMMENT '图片',
  description TEXT,
  is_available ENUM('yes','no') DEFAULT 'yes' COMMENT '是否可售',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 堂食订单表（点餐）
CREATE TABLE IF NOT EXISTS dine_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) UNIQUE NOT NULL COMMENT '订单号',
  table_id INT NOT NULL FOREIGN KEY REFERENCES restaurant_tables(id),
  customer_count INT DEFAULT 1 COMMENT '用餐人数',
  status ENUM('ordering','confirmed','preparing','served','completed','cancelled') DEFAULT 'ordering' COMMENT '状态',
  total_amount DECIMAL(10,2) DEFAULT 0 COMMENT '总金额',
  pay_amount DECIMAL(10,2) DEFAULT 0 COMMENT '实付金额',
  discount_amount DECIMAL(10,2) DEFAULT 0 COMMENT '优惠金额',
  pay_type VARCHAR(20) COMMENT '支付方式：cash/card/wechat/alipay',
  remark TEXT,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  confirmed_at DATETIME,
  served_at DATETIME,
  completed_at DATETIME
);

-- 订单菜品明细
CREATE TABLE IF NOT EXISTS dine_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL FOREIGN KEY REFERENCES dine_orders(id),
  dish_id INT NOT NULL FOREIGN KEY REFERENCES dishes(id),
  dish_name VARCHAR(100) NOT NULL,
  unit VARCHAR(20),
  price DECIMAL(10,2) NOT NULL,
  number INT DEFAULT 1,
  subtotal DECIMAL(10,2) NOT NULL,
  remark VARCHAR(200),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 外卖订单表
CREATE TABLE IF NOT EXISTS takeout_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) UNIQUE NOT NULL COMMENT '订单号',
  customer_name VARCHAR(50) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  delivery_address TEXT,
  status ENUM('pending','confirmed','preparing','delivering','completed','cancelled') DEFAULT 'pending',
  total_amount DECIMAL(10,2) DEFAULT 0,
  pay_amount DECIMAL(10,2) DEFAULT 0,
  freight_amount DECIMAL(10,2) DEFAULT 0,
  discount_amount DECIMAL(10,2) DEFAULT 0,
  pay_type VARCHAR(20),
  remark TEXT,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  confirmed_at DATETIME,
  completed_at DATETIME
);

-- 外卖订单明细
CREATE TABLE IF NOT EXISTS takeout_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL FOREIGN KEY REFERENCES takeout_orders(id),
  dish_id INT NOT NULL FOREIGN KEY REFERENCES dishes(id),
  dish_name VARCHAR(100) NOT NULL,
  unit VARCHAR(20),
  price DECIMAL(10,2) NOT NULL,
  number INT DEFAULT 1,
  subtotal DECIMAL(10,2) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 预订表
CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserve_no VARCHAR(30) UNIQUE NOT NULL COMMENT '预订号',
  customer_name VARCHAR(50) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  table_id INT FOREIGN KEY REFERENCES restaurant_tables(id),
  people_count INT DEFAULT 1,
  reserve_date DATE NOT NULL,
  reserve_time TIME NOT NULL,
  status ENUM('pending','confirmed','arrived','cancelled','no_show') DEFAULT 'pending',
  remark TEXT,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  confirmed_at DATETIME
);

-- 排队叫号表
CREATE TABLE IF NOT EXISTS queue_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(20) UNIQUE NOT NULL COMMENT '票号',
  customer_name VARCHAR(50),
  customer_phone VARCHAR(20),
  people_count INT DEFAULT 1,
  status ENUM('waiting','called','served','cancelled') DEFAULT 'waiting',
  table_id INT FOREIGN KEY REFERENCES restaurant_tables(id),
  position INT NOT NULL COMMENT '排队位置',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  called_at DATETIME,
  served_at DATETIME
);

-- 收银记录表
CREATE TABLE IF NOT EXISTS cashier_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(30) UNIQUE NOT NULL COMMENT '收银单号',
  order_type ENUM('dine','takeout') NOT NULL COMMENT '订单类型',
  order_id INT NOT NULL COMMENT '关联订单ID',
  total_amount DECIMAL(10,2) NOT NULL,
  discount_amount DECIMAL(10,2) DEFAULT 0,
  receivable_amount DECIMAL(10,2) NOT NULL COMMENT '应收金额',
  received_amount DECIMAL(10,2) NOT NULL COMMENT '实收金额',
  change_amount DECIMAL(10,2) DEFAULT 0 COMMENT '找零',
  pay_type VARCHAR(20) NOT NULL COMMENT '支付方式',
  operator_id INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);