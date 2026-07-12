-- 调货流程数据库迁移
-- 创建时间: 2026-03-03

-- 1. 调货单主表
CREATE TABLE IF NOT EXISTS transfer_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) UNIQUE NOT NULL COMMENT '调货单号 TF-YYYYMMDD-XXXX',
  from_store_id INT NOT NULL COMMENT '调出门店',
  to_store_id INT NOT NULL COMMENT '调入门店',
  total_qty INT NOT NULL DEFAULT 0 COMMENT '总数量',
  status ENUM('pending','shipped','received','cancelled') DEFAULT 'pending' COMMENT '状态',
  initiated_by INT NOT NULL COMMENT '发起人（系统用户ID）',
  received_by INT DEFAULT NULL COMMENT '接收人（系统用户ID）',
  note TEXT COMMENT '备注',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  shipped_at DATETIME DEFAULT NULL COMMENT '发货时间',
  received_at DATETIME DEFAULT NULL COMMENT '收货时间',
  FOREIGN KEY (from_store_id) REFERENCES stores(id),
  FOREIGN KEY (to_store_id) REFERENCES stores(id),
  FOREIGN KEY (initiated_by) REFERENCES users(id),
  FOREIGN KEY (received_by) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_from_store (from_store_id),
  INDEX idx_to_store (to_store_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='调货单主表';

-- 2. 调货单明细表
CREATE TABLE IF NOT EXISTS transfer_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_id INT NOT NULL COMMENT '调货单ID',
  product_id INT NOT NULL COMMENT '商品ID',
  qrcode_id INT DEFAULT NULL COMMENT '二维码ID（如果扫码）',
  quantity INT NOT NULL DEFAULT 1 COMMENT '数量',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (record_id) REFERENCES transfer_records(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (qrcode_id) REFERENCES qrcodes(id),
  INDEX idx_record (record_id),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='调货单明细表';

-- 3. 扩展退货记录表
CREATE TABLE IF NOT EXISTS return_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(50) UNIQUE NOT NULL COMMENT '退货单号 RT-YYYYMMDD-XXXX',
  product_id INT NOT NULL COMMENT '商品ID',
  qrcode_id INT DEFAULT NULL COMMENT '二维码ID',
  warehouse_id INT DEFAULT NULL COMMENT '退回仓库',
  quantity INT NOT NULL DEFAULT 1 COMMENT '退货数量',
  reason TEXT COMMENT '退货原因',
  return_type ENUM('quality','wrong_item','customer_return','other') DEFAULT 'other' COMMENT '退货类型',
  operator_id INT DEFAULT NULL COMMENT '操作人ID',
  operator_name VARCHAR(100) DEFAULT NULL COMMENT '操作人姓名',
  retail_record_id INT DEFAULT NULL COMMENT '关联零售记录ID',
  buyer_name VARCHAR(100) DEFAULT NULL COMMENT '原购买人',
  buyer_phone VARCHAR(20) DEFAULT NULL COMMENT '原购买人电话',
  salesperson_name VARCHAR(100) DEFAULT NULL COMMENT '原销售员',
  service_user_id INT DEFAULT NULL COMMENT '客服ID',
  status ENUM('pending','approved','rejected','completed') DEFAULT 'pending' COMMENT '状态',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME DEFAULT NULL COMMENT '审批时间',
  completed_atDEFAULT NULL COMMENT '完成时间',
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (qrcode_id) REFERENCES qrcodes(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (operator_id) REFERENCES users(id),
  FOREIGN KEY (retail_record_id) REFERENCES retail_records(id),
  FOREIGN KEY (service_user_id) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  INDEX idx_qrcode (qrcode_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退货记录表';
