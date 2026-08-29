-- material-purchase 模块表结构
-- 创建时间: 2026-08-24
-- 作者: jiangxiaoyu (波哥派活)
-- 用途: 物料采购工作流 — 请购→审批→采购→到货入库

CREATE TABLE IF NOT EXISTS material_purchase_orders (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  order_no VARCHAR(50) NOT NULL UNIQUE COMMENT '采购单号, 格式 CG-YYYYMMDD-NNNN',
  title VARCHAR(200) NOT NULL COMMENT '采购主题',
  applicant_id INT(11) NOT NULL COMMENT '申请人ID (users.id)',
  applicant_name VARCHAR(50) NOT NULL COMMENT '申请人姓名 (冗余)',
  department VARCHAR(100) DEFAULT NULL COMMENT '申请部门',
  supplier VARCHAR(200) DEFAULT NULL COMMENT '供应商名',
  supplier_id INT(11) DEFAULT NULL COMMENT '供应商ID (suppliers.id)',
  warehouse_id INT(11) DEFAULT NULL COMMENT '目标仓库ID (warehouses.id)',
  approval_id INT(11) DEFAULT NULL COMMENT '关联审批单ID (approvals.id)',
  total_amount DECIMAL(12,2) DEFAULT 0 COMMENT '采购总金额',
  urgency ENUM('normal','urgent','critical') DEFAULT 'normal',
  status ENUM('draft','pending_approval','approved','purchasing','arrived','completed','rejected','cancelled')
    DEFAULT 'draft' COMMENT '状态机',
  expected_date DATE DEFAULT NULL COMMENT '期望到货日期',
  arrived_at DATETIME DEFAULT NULL COMMENT '实际到货时间',
  inbound_record_id INT(11) DEFAULT NULL COMMENT '自动入库的入库单ID (inbound_records.id)',
  remark TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_applicant (applicant_id),
  INDEX idx_warehouse (warehouse_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='物料采购单';

CREATE TABLE IF NOT EXISTS material_purchase_items (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  order_id INT(11) NOT NULL COMMENT '采购单ID',
  product_id INT(11) NOT NULL COMMENT '商品ID (products.id)',
  product_name VARCHAR(200) NOT NULL COMMENT '商品名 (冗余)',
  sku_id INT(11) DEFAULT NULL COMMENT 'SKU ID',
  quantity INT(11) NOT NULL DEFAULT 1 COMMENT '采购数量',
  unit_price DECIMAL(10,2) DEFAULT 0 COMMENT '单价',
  subtotal DECIMAL(12,2) DEFAULT 0 COMMENT '小计',
  remark VARCHAR(500) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order (order_id),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购单明细';

CREATE TABLE IF NOT EXISTS material_purchase_logs (
  id INT(11) PRIMARY KEY AUTO_INCREMENT,
  order_id INT(11) NOT NULL,
  action VARCHAR(50) NOT NULL COMMENT 'submit/approve/reject/purchase/arrive/complete/cancel',
  from_status VARCHAR(50) DEFAULT NULL,
  to_status VARCHAR(50) DEFAULT NULL,
  operator_id INT(11) DEFAULT NULL,
  operator_name VARCHAR(50) DEFAULT NULL,
  comment TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购单操作日志';
