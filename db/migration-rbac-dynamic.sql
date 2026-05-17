-- Migration: 动态权限系统 (RBAC) v2
-- Date: 2026-05-11
-- Purpose: 建立完整的动态权限数据库结构（使用 rbac_ 前缀避免与现有表冲突）
-- Note: 兼容现有 users.role 字段，新建 rbac_user_roles 做多对多关联

-- ============================================================
-- 1. rbac_permissions 表 — 权限定义
-- ============================================================
CREATE TABLE IF NOT EXISTS rbac_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE COMMENT '权限标识 (如: product:read)',
  label VARCHAR(100) NOT NULL COMMENT '权限显示名',
  category VARCHAR(50) DEFAULT 'other' COMMENT '权限分类',
  description VARCHAR(255) DEFAULT '' COMMENT '描述',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_name (name),
  KEY idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限定义表';

-- ============================================================
-- 2. rbac_menus 表 — 菜单定义（支持层级）
-- ============================================================
CREATE TABLE IF NOT EXISTS rbac_menus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '菜单标识',
  label VARCHAR(100) NOT NULL COMMENT '菜单显示名',
  path VARCHAR(255) NOT NULL COMMENT '路由路径',
  icon VARCHAR(100) DEFAULT '' COMMENT '图标名称',
  parent_id INT DEFAULT NULL COMMENT '父菜单ID',
  sort_order INT DEFAULT 0 COMMENT '排序',
  component_path VARCHAR(255) DEFAULT NULL COMMENT 'Vue组件路径',
  type ENUM('menu','button','divider') DEFAULT 'menu' COMMENT '类型',
  visible ENUM('show','hide') DEFAULT 'show' COMMENT '是否显示',
  status ENUM('enabled','disabled') DEFAULT 'enabled' COMMENT '状态',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_parent (parent_id),
  KEY idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='菜单定义表';

-- ============================================================
-- 3. rbac_roles 表 — 角色定义
-- ============================================================
CREATE TABLE IF NOT EXISTS rbac_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE COMMENT '角色标识',
  label VARCHAR(100) NOT NULL COMMENT '角色显示名',
  description VARCHAR(255) DEFAULT '' COMMENT '描述',
  is_system TINYINT(1) DEFAULT 0 COMMENT '系统角色（不可删除）',
  sort_order INT DEFAULT 0 COMMENT '排序',
  status ENUM('enabled','disabled') DEFAULT 'enabled' COMMENT '状态',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_name (name),
  KEY idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色定义表';

-- ============================================================
-- 4. rbac_role_permissions 表 — 角色-权限关联
-- ============================================================
CREATE TABLE IF NOT EXISTS rbac_role_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES rbac_permissions(id) ON DELETE CASCADE,
  UNIQUE KEY uk_role_perm (role_id, permission_id),
  KEY idx_role (role_id),
  KEY idx_perm (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色-权限关联表';

-- ============================================================
-- 5. rbac_user_roles 表 — 用户-角色关联
-- ============================================================
CREATE TABLE IF NOT EXISTS rbac_user_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE CASCADE,
  UNIQUE KEY uk_user_role (user_id, role_id),
  KEY idx_user (user_id),
  KEY idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户-角色关联表';

-- ============================================================
-- 插入默认权限
-- ============================================================
INSERT INTO rbac_permissions (name, label, category, description) VALUES
-- 产品模块
('product:read', '查看商品', 'product', '查看商品列表和详情'),
('product:write', '管理商品', 'product', '新增/编辑/删除商品'),
-- 仓库模块
('warehouse:read', '查看仓库', 'warehouse', '查看仓库和库存'),
('warehouse:write', '管理仓库', 'warehouse', '新增/编辑/删除仓库'),
('stock:read', '查看库存', 'stock', '查看库存数据'),
('stock:write', '管理库存', 'stock', '出入库/盘点操作'),
-- 财务模块
('finance:read', '查看财务', 'finance', '查看财务报表'),
('finance:write', '管理财务', 'finance', '财务单据操作'),
-- 零售模块
('retail:read', '查看零售', 'retail', '查看零售记录'),
('retail:write', '管理零售', 'retail', '零售开单/退货'),
-- 售后模块
('aftersale:read', '查看售后', 'aftersale', '查看售后记录'),
('aftersale:write', '管理售后', 'aftersale', '处理售后'),
-- OA模块
('oa:read', '查看OA', 'oa', '查看考勤/审批'),
('oa:write', '管理OA', 'oa', '审批/排班/请假'),
-- 供应链模块
('supplier:read', '查看供货商', 'supply', '查看供货商'),
('supplier:write', '管理供货商', 'supply', '新增/编辑供货商'),
('dealer:read', '查看经销商', 'supply', '查看经销商'),
('dealer:write', '管理经销商', 'supply', '新增/编辑经销商'),
('store:read', '查看门店', 'supply', '查看门店'),
('store:write', '管理门店', 'supply', '新增/编辑门店'),
-- 系统模块
('user:read', '查看用户', 'system', '查看用户列表'),
('user:write', '管理用户', 'system', '新增/编辑/禁用用户'),
('role:read', '查看角色', 'system', '查看角色'),
('role:write', '管理角色', 'system', '新增/编辑/删除角色'),
('permission:read', '查看权限', 'system', '查看权限'),
('permission:write', '管理权限', 'system', '新增/编辑/删除权限'),
('menu:read', '查看菜单', 'system', '查看菜单'),
('menu:write', '管理菜单', 'system', '新增/编辑/删除菜单'),
('system:config', '系统配置', 'system', '修改系统配置'),
-- BI/报表
('bi:read', '查看BI分析', 'bi', '查看BI图表'),
('report:read', '查看报表', 'report', '查看各类报表')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- ============================================================
-- 插入默认角色
-- ============================================================
INSERT INTO rbac_roles (name, label, description, is_system, sort_order) VALUES
('admin', '超级管理员', '拥有所有权限', 1, 1),
('manager', '经理', '部门管理权限', 0, 2),
('warehouse', '仓库管理员', '仓库和库存管理', 0, 3),
('sales', '销售员', '零售和客户管理', 0, 4),
('finance', '财务', '财务和报表查看', 0, 5),
('operator', '操作员', '基础操作权限', 0, 99);

-- ============================================================
-- admin 角色默认拥有所有权限
-- ============================================================
INSERT IGNORE INTO rbac_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rbac_roles r, rbac_permissions p WHERE r.name = 'admin';
