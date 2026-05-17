-- ============================================
-- 简化版财务系统 - 适配GDQ贸易业务
-- 核心：采购成本 + 销售收入 + 费用支出 + 应收应付
-- ============================================

-- 1. 采购成本记录表（自动从入库单生成）
CREATE TABLE IF NOT EXISTS purchase_costs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) NOT NULL UNIQUE COMMENT '采购单号',
  inbound_id INT DEFAULT NULL COMMENT '关联入库单ID',
  supplier_id INT NOT NULL COMMENT '供货商ID',
  purchase_date DATE NOT NULL COMMENT '采购日期',
  product_id INT NOT NULL COMMENT '商品ID',
  quantity INT NOT NULL COMMENT '数量',
  unit_price DECIMAL(10,2) NOT NULL COMMENT '采购单价',
  total_amount DECIMAL(15,2) NOT NULL COMMENT '采购总额',
  payment_method ENUM('cash','bank','alipay','wechat','credit') DEFAULT 'credit' COMMENT '付款方式',
  paid_amount DECIMAL(15,2) DEFAULT 0 COMMENT '已付金额',
  payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid' COMMENT '付款状态',
  note TEXT COMMENT '备注',
  creator_id INT NOT NULL COMMENT '录入人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (inbound_id) REFERENCES inbound_records(id) ON DELETE SET NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (creator_id) REFERENCES users(id),
  INDEX idx_purchase_date (purchase_date),
  INDEX idx_supplier (supplier_id),
  INDEX idx_product (product_id),
  INDEX idx_payment_status (payment_status)
) COMMENT='采购成本记录表';

-- 2. 销售收入记录表（自动从零售单生成）
CREATE TABLE IF NOT EXISTS sales_revenues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) NOT NULL UNIQUE COMMENT '销售单号',
  retail_id INT DEFAULT NULL COMMENT '关联零售记录ID',
  sale_date DATE NOT NULL COMMENT '销售日期',
  product_id INT NOT NULL COMMENT '商品ID',
  quantity INT NOT NULL COMMENT '数量',
  cost_price DECIMAL(10,2) NOT NULL COMMENT '成本价',
  sale_price DECIMAL(10,2) NOT NULL COMMENT '销售价',
  total_revenue DECIMAL(15,2) NOT NULL COMMENT '销售收入',
  total_cost DECIMAL(15,2) NOT NULL COMMENT '销售成本',
  gross_profit DECIMAL(15,2) NOT NULL COMMENT '毛利润',
  store_id INT DEFAULT NULL COMMENT '门店ID',
  salesperson_id INT DEFAULT NULL COMMENT '销售员ID（H5用户）',
  customer_phone VARCHAR(20) DEFAULT NULL COMMENT '客户手机号',
  payment_method ENUM('cash','bank','alipay','wechat','other') NOT NULL COMMENT '收款方式',
  received_amount DECIMAL(15,2) DEFAULT 0 COMMENT '已收金额',
  payment_status ENUM('unpaid','partial','paid') DEFAULT 'paid' COMMENT '收款状态',
  note TEXT COMMENT '备注',
  creator_id INT NOT NULL COMMENT '录入人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (retail_id) REFERENCES retail_records(id) ON DELETE SET NULL,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id),
  INDEX idx_sale_date (sale_date),
  INDEX idx_product (product_id),
  INDEX idx_store (store_id),
  INDEX idx_salesperson (salesperson_id),
  INDEX idx_payment_status (payment_status)
) COMMENT='销售收入记录表';

-- 3. 费用支出记录表（手工录入）
CREATE TABLE IF NOT EXISTS expense_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) NOT NULL UNIQUE COMMENT '支出单号',
  expense_date DATE NOT NULL COMMENT '支出日期',
  category ENUM('rent','salary','utility','marketing','logistics','maintenance','other') NOT NULL COMMENT '费用类别',
  category_name VARCHAR(50) DEFAULT NULL COMMENT '自定义类别名称',
  amount DECIMAL(15,2) NOT NULL COMMENT '支出金额',
  payment_method ENUM('cash','bank','alipay','wechat','other') NOT NULL COMMENT '支付方式',
  store_id INT DEFAULT NULL COMMENT '关联门店',
  department_id INT DEFAULT NULL COMMENT '关联部门',
  payee VARCHAR(100) DEFAULT NULL COMMENT '收款方',
  description VARCHAR(200) NOT NULL COMMENT '费用说明',
  attachments JSON DEFAULT NULL COMMENT '附件（发票、收据等）',
  approval_status ENUM('pending','approved','rejected') DEFAULT 'approved' COMMENT '审批状态',
  approver_id INT DEFAULT NULL COMMENT '审批人',
  approved_at DATETIME DEFAULT NULL COMMENT '审批时间',
  note TEXT COMMENT '备注',
  creator_id INT NOT NULL COMMENT '录入人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id),
  INDEX idx_expense_date (expense_date),
  INDEX idx_category (category),
  INDEX idx_store (store_id),
  INDEX idx_approval_status (approval_status)
) COMMENT='费用支出记录表';

-- 4. 应付款记录表（供货商欠款）
CREATE TABLE IF NOT EXISTS accounts_payable (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL COMMENT '供货商ID',
  purchase_cost_id INT DEFAULT NULL COMMENT '关联采购记录',
  transaction_type ENUM('purchase','payment','refund','adjustment') NOT NULL COMMENT '交易类型',
  transaction_date DATE NOT NULL COMMENT '交易日期',
  amount DECIMAL(15,2) NOT NULL COMMENT '金额（正数=欠款增加，负数=还款）',
  balance DECIMAL(15,2) NOT NULL COMMENT '余额（欠款总额）',
  payment_method ENUM('cash','bank','alipay','wechat','other') DEFAULT NULL COMMENT '付款方式',
  note TEXT COMMENT '备注',
  creator_id INT NOT NULL COMMENT '操作人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
  FOREIGN KEY (purchase_cost_id) REFERENCES purchase_costs(id) ON DELETE SET NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id),
  INDEX idx_supplier (supplier_id),
  INDEX idx_transaction_date (transaction_date),
  INDEX idx_transaction_type (transaction_type)
) COMMENT='应付款记录表';

-- 5. 应收款记录表（客户欠款）
CREATE TABLE IF NOT EXISTS accounts_receivable (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_phone VARCHAR(20) NOT NULL COMMENT '客户手机号',
  customer_name VARCHAR(100) DEFAULT NULL COMMENT '客户姓名',
  sales_revenue_id INT DEFAULT NULL COMMENT '关联销售记录',
  transaction_type ENUM('sale','payment','refund','adjustment') NOT NULL COMMENT '交易类型',
  transaction_date DATE NOT NULL COMMENT '交易日期',
  amount DECIMAL(15,2) NOT NULL COMMENT '金额（正数=欠款增加，负数=收款）',
  balance DECIMAL(15,2) NOT NULL COMMENT '余额（欠款总额）',
  payment_method ENUM('cash','bank','alipay','wechat','other') DEFAULT NULL COMMENT '收款方式',
  note TEXT COMMENT '备注',
  creator_id INT NOT NULL COMMENT '操作人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sales_revenue_id) REFERENCES sales_revenues(id) ON DELETE SET NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id),
  INDEX idx_customer_phone (customer_phone),
  INDEX idx_transaction_date (transaction_date),
  INDEX idx_transaction_type (transaction_type)
) COMMENT='应收款记录表';

-- 6. 付款记录表（采购付款明细）
CREATE TABLE IF NOT EXISTS payment_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) NOT NULL UNIQUE COMMENT '付款单号',
  payment_date DATE NOT NULL COMMENT '付款日期',
  supplier_id INT NOT NULL COMMENT '供货商ID',
  amount DECIMAL(15,2) NOT NULL COMMENT '付款金额',
  payment_method ENUM('cash','bank','alipay','wechat','other') NOT NULL COMMENT '付款方式',
  related_purchases JSON DEFAULT NULL COMMENT '关联的采购单ID数组',
  note TEXT COMMENT '备注',
  creator_id INT NOT NULL COMMENT '操作人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (creator_id) REFERENCES users(id),
  INDEX idx_payment_date (payment_date),
  INDEX idx_supplier (supplier_id)
) COMMENT='付款记录表';

-- 7. 收款记录表（销售收款明细）
CREATE TABLE IF NOT EXISTS receipt_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) NOT NULL UNIQUE COMMENT '收款单号',
  receipt_date DATE NOT NULL COMMENT '收款日期',
  customer_phone VARCHAR(20) NOT NULL COMMENT '客户手机号',
  customer_name VARCHAR(100) DEFAULT NULL COMMENT '客户姓名',
  amount DECIMAL(15,2) NOT NULL COMMENT '收款金额',
  payment_method ENUM('cash','bank','alipay','wechat','other') NOT NULL COMMENT '收款方式',
  related_sales JSON DEFAULT NULL COMMENT '关联的销售单ID数组',
  note TEXT COMMENT '备注',
  creator_id INT NOT NULL COMMENT '操作人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id) REFERENCES users(id),
  INDEX idx_receipt_date (receipt_date),
  INDEX idx_customer_phone (customer_phone)
) COMMENT='收款记录表';

-- 8. 财务汇总表（按日/月/年汇总）
CREATE TABLE IF NOT EXISTS financial_summary (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_type ENUM('day','month','year') NOT NULL COMMENT '汇总周期',
  period_value VARCHAR(20) NOT NULL COMMENT '周期值 如2026-03-01, 2026-03, 2026',
  total_revenue DECIMAL(15,2) DEFAULT 0 COMMENT '销售收入',
  total_cost DECIMAL(15,2) DEFAULT 0 COMMENT '销售成本',
  gross_profit DECIMAL(15,2) DEFAULT 0 COMMENT '毛利润',
  total_expense DECIMAL(15,2) DEFAULT 0 COMMENT '费用支出',
  net_profit DECIMAL(15,2) DEFAULT 0 COMMENT '净利润',
  purchase_amount DECIMAL(15,2) DEFAULT 0 COMMENT '采购金额',
  sales_count INT DEFAULT 0 COMMENT '销售笔数',
  purchase_count INT DEFAULT 0 COMMENT '采购笔数',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_period (period_type, period_value),
  INDEX idx_period_value (period_value)
) COMMENT='财务汇总表';

-- 9. 扩展现有表字段

-- 入库单添加财务关联
ALTER TABLE inbound_records
  ADD COLUMN finance_synced TINYINT(1) DEFAULT 0 COMMENT '是否已同步到财务',
  ADD COLUMN purchase_cost_id INT DEFAULT NULL COMMENT '关联采购成本记录ID',
  ADD INDEX idx_finance_synced (finance_synced);

-- 零售单添加财务关联
ALTER TABLE retail_records
  ADD COLUMN finance_synced TINYINT(1) DEFAULT 0 COMMENT '是否已同步到财务',
  ADD COLUMN sales_revenue_id INT DEFAULT NULL COMMENT '关联销售收入记录ID',
  ADD INDEX idx_finance_synced (finance_synced);

-- 供货商添加应付款余额
ALTER TABLE suppliers
  ADD COLUMN payable_balance DECIMAL(15,2) DEFAULT 0 COMMENT '应付款余额（欠供货商的钱）';

-- 插入费用类别翻译数据到i18n（后续前端使用）
-- rent=房租, salary=工资, utility=水电, marketing=营销, logistics=物流, maintenance=维护, other=其他
