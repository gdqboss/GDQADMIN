-- 用户多供应商关联迁移
-- 创建用户-供应商关联表（多对多）

CREATE TABLE IF NOT EXISTS user_suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '用户ID',
  supplier_id INT NOT NULL COMMENT '供应商ID',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_supplier (user_id, supplier_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_supplier_id (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户-供应商关联表';

-- 迁移现有的 supplier_id 数据到新表
INSERT INTO user_suppliers (user_id, supplier_id)
SELECT id, supplier_id
FROM users
WHERE supplier_id IS NOT NULL
ON DUPLICATE KEY UPDATE user_id = user_id;

-- 注意：保留 users.supplier_id 字段以保持向后兼容，但新功能将使用 user_suppliers 表
