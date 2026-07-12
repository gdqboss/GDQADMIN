CREATE DATABASE IF NOT EXISTS gdq DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gdq;

-- ===================== P0 Tables =====================

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','operator') DEFAULT 'operator',
  department VARCHAR(50),
  status ENUM('active','disabled') DEFAULT 'active',
  last_login DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(50) UNIQUE NOT NULL,
  name VARCHAR(200) NOT NULL,
  category VARCHAR(50),
  spec VARCHAR(200),
  unit VARCHAR(20),
  supplier VARCHAR(100),
  purchase_price DECIMAL(10,2),
  sale_price DECIMAL(10,2),
  stock INT DEFAULT 0,
  safe_stock INT DEFAULT 0,
  status ENUM('active','discontinued') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS warehouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address VARCHAR(255),
  type VARCHAR(50),
  manager VARCHAR(50),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS warehouse_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  warehouse_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT DEFAULT 0,
  location VARCHAR(50),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uk_wh_prod (warehouse_id, product_id)
);

-- ===================== P1 Tables =====================

CREATE TABLE IF NOT EXISTS inbound_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) UNIQUE NOT NULL,
  warehouse_id INT NOT NULL,
  supplier VARCHAR(100),
  total_qty INT,
  operator VARCHAR(50),
  status ENUM('pending','completed','cancelled') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS inbound_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  FOREIGN KEY (record_id) REFERENCES inbound_records(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS outbound_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) UNIQUE NOT NULL,
  warehouse_id INT NOT NULL,
  customer VARCHAR(100),
  total_qty INT,
  operator VARCHAR(50),
  status ENUM('pending','completed','cancelled') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS outbound_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  FOREIGN KEY (record_id) REFERENCES outbound_records(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS return_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) UNIQUE NOT NULL,
  warehouse_id INT NOT NULL,
  source VARCHAR(100),
  total_qty INT,
  operator VARCHAR(50),
  status ENUM('pending','completed','cancelled') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE IF NOT EXISTS return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  FOREIGN KEY (record_id) REFERENCES return_records(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS stock_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  warehouse_id INT,
  current_stock INT,
  safe_stock INT,
  suggest_qty INT,
  level ENUM('low','critical') DEFAULT 'low',
  handled BOOLEAN DEFAULT FALSE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  type VARCHAR(50),
  applicant VARCHAR(50),
  department VARCHAR(50),
  amount DECIMAL(10,2),
  urgency ENUM('normal','urgent','critical') DEFAULT 'normal',
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  current_step INT DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS approval_steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  approval_id INT NOT NULL,
  step_order INT NOT NULL,
  role VARCHAR(50),
  assignee VARCHAR(50),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  comment TEXT,
  acted_at DATETIME,
  FOREIGN KEY (approval_id) REFERENCES approvals(id)
);

-- ===================== P2 Tables =====================

CREATE TABLE IF NOT EXISTS qrcodes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  product_id INT,
  status ENUM('unused','bindProduct','shipped','sold','afterSale','disabled') DEFAULT 'unused',
  scan_count INT DEFAULT 0,
  warehouse VARCHAR(100),
  buyer VARCHAR(50),
  buy_date DATE,
  warranty_end DATE,
  sales_person VARCHAR(50),
  bound_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS scan_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qrcode_id INT NOT NULL,
  scanner VARCHAR(50),
  role VARCHAR(20),
  action VARCHAR(100),
  location VARCHAR(200),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (qrcode_id) REFERENCES qrcodes(id)
);

CREATE TABLE IF NOT EXISTS after_sale_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qrcode_id INT NOT NULL,
  buyer VARCHAR(50),
  issue TEXT,
  status ENUM('processing','resolved','rejected') DEFAULT 'processing',
  handler VARCHAR(50),
  handler_note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (qrcode_id) REFERENCES qrcodes(id)
);

CREATE TABLE IF NOT EXISTS user_hierarchy (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  role VARCHAR(20),
  level INT DEFAULT 0,
  parent_id INT,
  total_commission DECIMAL(10,2) DEFAULT 0,
  total_points INT DEFAULT 0,
  FOREIGN KEY (parent_id) REFERENCES user_hierarchy(id)
);

CREATE TABLE IF NOT EXISTS commission_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qrcode_id INT,
  buyer VARCHAR(50),
  beneficiary VARCHAR(50),
  type VARCHAR(20),
  amount DECIMAL(10,2),
  status ENUM('pending','settled') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (qrcode_id) REFERENCES qrcodes(id)
);

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  clock_in TIME,
  clock_out TIME,
  status ENUM('normal','late','early','absent','leave') DEFAULT 'normal',
  location VARCHAR(200),
  wecom_synced BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uk_user_date (user_id, date)
);

CREATE TABLE IF NOT EXISTS leave_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(20),
  start_date DATE,
  end_date DATE,
  days DECIMAL(3,1),
  reason TEXT,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  approver VARCHAR(50),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uk_user_leave (user_id, type, start_date, end_date)
);

CREATE TABLE IF NOT EXISTS work_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  report_to VARCHAR(50),
  today_work TEXT,
  tomorrow_plan TEXT,
  issues TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ===================== P3 Tables =====================

CREATE TABLE IF NOT EXISTS wecom_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  external_id VARCHAR(100),
  name VARCHAR(100),
  type ENUM('single','group') DEFAULT 'single',
  last_message TEXT,
  last_time DATETIME,
  unread INT DEFAULT 0,
  wecom_chat_id VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS wecom_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender VARCHAR(100),
  is_self BOOLEAN DEFAULT FALSE,
  content TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES wecom_conversations(id)
);

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) UNIQUE NOT NULL,
  value TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ===================== P4 WeCom Integration Tables =====================

CREATE TABLE IF NOT EXISTS wecom_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wecom_userid VARCHAR(100) UNIQUE NOT NULL,
  user_id INT,
  name VARCHAR(100),
  department_ids JSON,
  position VARCHAR(100),
  mobile VARCHAR(30),
  email VARCHAR(100),
  status INT DEFAULT 1,
  synced_at DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wecom_departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wecom_dept_id INT UNIQUE NOT NULL,
  name VARCHAR(200),
  parentid INT DEFAULT 0,
  dept_order INT DEFAULT 0,
  synced_at DATETIME
);

CREATE TABLE IF NOT EXISTS wecom_approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sp_no VARCHAR(50) UNIQUE NOT NULL,
  sp_name VARCHAR(200),
  sp_status VARCHAR(20),
  apply_time DATETIME,
  applicant_userid VARCHAR(100),
  data_json JSON,
  synced_at DATETIME
);

CREATE TABLE IF NOT EXISTS wecom_sync_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('attendance','approvals','contacts') NOT NULL,
  status ENUM('success','failed') NOT NULL,
  records_synced INT DEFAULT 0,
  error_message TEXT,
  started_at DATETIME,
  finished_at DATETIME
);

-- ===================== Performance Indexes =====================

CREATE INDEX idx_inbound_created ON inbound_records(created_at DESC);
CREATE INDEX idx_outbound_created ON outbound_records(created_at DESC);
CREATE INDEX idx_return_created ON return_records(created_at DESC);
CREATE INDEX idx_qrcode_status ON qrcodes(status);
CREATE INDEX idx_qrcode_created ON qrcodes(created_at DESC);
CREATE INDEX idx_approval_status ON approvals(status);
CREATE INDEX idx_approval_created ON approvals(created_at DESC);
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_leave_status ON leave_records(status);
CREATE INDEX idx_wecom_msg_conv ON wecom_messages(conversation_id, created_at);
CREATE INDEX idx_sync_log_type ON wecom_sync_log(type, started_at DESC);

-- ===================== Seed Data =====================

-- Default admin user (password: admin123)
INSERT INTO users (name, email, password, role, department) VALUES
('管理员', 'admin@gdq.com', '$2a$10$KOK80OJ7SU17n/jt35pTI.UkQOPumo4fVH2jF7eyG3hPTCjUqQj1W', 'admin', '管理部'),
('张经理', 'zhang@gdq.com', '$2a$10$KOK80OJ7SU17n/jt35pTI.UkQOPumo4fVH2jF7eyG3hPTCjUqQj1W', 'manager', '仓储部'),
('李操作', 'li@gdq.com', '$2a$10$KOK80OJ7SU17n/jt35pTI.UkQOPumo4fVH2jF7eyG3hPTCjUqQj1W', 'operator', '仓储部')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Sample products
INSERT INTO products (sku, name, category, spec, unit, supplier, purchase_price, sale_price, stock, safe_stock) VALUES
('SKU-001', '蓝牙耳机 Pro', '电子产品', '蓝牙5.3 主动降噪', '个', '深圳科技有限公司', 89.00, 199.00, 150, 30),
('SKU-002', '无线充电器', '电子产品', '15W快充 Qi协议', '个', '深圳科技有限公司', 35.00, 79.00, 80, 20),
('SKU-003', '保温杯 500ml', '日用品', '316不锈钢 真空保温', '个', '浙江优品制造', 25.00, 59.00, 200, 50),
('SKU-004', '笔记本电脑支架', '办公用品', '铝合金 可调节高度', '个', '东莞金属制品厂', 45.00, 129.00, 60, 15),
('SKU-005', '机械键盘', '电子产品', '87键 红轴 RGB背光', '个', '深圳科技有限公司', 120.00, 299.00, 45, 10)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Sample warehouses
INSERT INTO warehouses (name, address, type, manager) VALUES
('主仓库', '上海市浦东新区张江高科技园区', '中心仓', '张经理'),
('华南分仓', '广东省深圳市南山区科技园', '区域仓', '王主管'),
('华北分仓', '北京市朝阳区望京SOHO', '区域仓', '赵主管')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Sample warehouse stock
INSERT INTO warehouse_stock (warehouse_id, product_id, quantity, location) VALUES
(1, 1, 80, 'A-01-01'), (1, 2, 50, 'A-01-02'), (1, 3, 120, 'B-02-01'),
(1, 4, 40, 'B-02-02'), (1, 5, 30, 'C-03-01'),
(2, 1, 40, 'A-01-01'), (2, 2, 20, 'A-01-02'), (2, 3, 50, 'B-01-01'),
(3, 1, 30, 'A-01-01'), (3, 3, 30, 'A-02-01'), (3, 4, 20, 'B-01-01'), (3, 5, 15, 'B-01-02')
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);
