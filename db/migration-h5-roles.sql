-- Migration: H5 User Multi-Role System
-- Date: 2026-03-01
-- Purpose: Add role-based permissions for H5 users

-- Create h5_roles table
CREATE TABLE IF NOT EXISTS h5_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE COMMENT '角色标识',
  label VARCHAR(100) NOT NULL COMMENT '角色显示名',
  can_sell TINYINT(1) DEFAULT 0 COMMENT '可以销售',
  can_adjust_price TINYINT(1) DEFAULT 0 COMMENT '可以改价',
  can_view_cost TINYINT(1) DEFAULT 0 COMMENT '可以查看成本',
  is_system TINYINT(1) DEFAULT 0 COMMENT '系统角色（不可删除）',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='H5用户角色表';

-- Insert default roles
INSERT INTO h5_roles (name, label, can_sell, can_adjust_price, can_view_cost, is_system) VALUES
('customer', '客户', 0, 0, 0, 1),
('salesperson', '销售员', 1, 1, 0, 1),
('cashier', '收银员', 1, 1, 0, 1),
('service', '客服', 0, 0, 0, 1),
('vip', 'VIP客户', 0, 0, 0, 1);

-- Add role, store_id, level columns to h5_users
ALTER TABLE h5_users
ADD COLUMN role VARCHAR(50) DEFAULT 'customer' COMMENT '角色标识',
ADD COLUMN store_id INT DEFAULT NULL COMMENT '关联门店',
ADD COLUMN level INT DEFAULT 1 COMMENT '用户等级';

-- Create indexes
CREATE INDEX idx_h5_users_role ON h5_users(role);
CREATE INDEX idx_h5_users_store ON h5_users(store_id);

-- Add foreign key constraint (optional, depends on whether stores table exists)
-- ALTER TABLE h5_users ADD FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL;
