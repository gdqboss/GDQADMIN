import { pool } from '../db/connection.js'

/**
 * 权限定义 - 基于业务场景
 * 每个接口按需要的最小权限来设置
 */

// 权限列表（与rbac_permissions表name字段一致）
const PERMISSIONS = {
  // 仓库相关
  WAREHOUSES_READ: 'warehouse:read',
  WAREHOUSES_WRITE: 'warehouse:write',
  WAREHOUSES_DELETE: 'warehouse:delete',

  // 库存相关
  INVENTORY_READ: 'inventory:read',
  INVENTORY_WRITE: 'inventory:write',
  INVENTORY_DELETE: 'inventory:delete',

  // 产品相关
  PRODUCTS_READ: 'product:read',
  PRODUCTS_WRITE: 'product:write',
  PRODUCTS_DELETE: 'product:delete',

  // 二维码相关
  QRCODE_READ: 'qrcode:read',
  QRCODE_WRITE: 'qrcode:write',
  QRCODE_DELETE: 'qrcode:delete',

  // 销售/零售相关
  SALES_READ: 'sales:read',
  SALES_WRITE: 'sales:write',

  // 报表
  REPORTS_READ: 'report:read',

  // 财务
  FINANCE_READ: 'finance:read',

  // OA办公
  OA_READ: 'oa:read',
  OA_WRITE: 'oa:write',

  // 用户管理
  USERS_READ: 'user:read',
  USERS_WRITE: 'user:write',

  // 审批
  APPROVALS_READ: 'approval:read',
  APPROVALS_WRITE: 'approval:write',

  // 供应商/经销商
  SUPPLIERS_READ: 'supplier:read',
  DEALERS_READ: 'dealer:read',

  // 门店
  STORES_READ: 'store:read',

  // 任务
  TASKS_READ: 'task:read',

  // 售后
  AFTERSALE_READ: 'aftersale:read',
  AFTERSALE_WRITE: 'aftersale:write',

  // 提醒/预警
  ALERTS_READ: 'alerts:read',
  ALERTS_WRITE: 'alerts:write',

  // 快捷操作相关（QUICK_ACTION）
  QUICK_ACTION_READ: 'quick_action:read',
  QUICK_ACTION_WRITE: 'quick_action:write',
  QUICK_ACTION_MANAGE: 'quick_action:manage',
  QUICK_ACTION_DELETE: 'quick_action:delete',

  // 文章管理
  ARTICLES_READ: 'articles:read',
  ARTICLES_WRITE: 'articles:write',
  ARTICLES_DELETE: 'articles:delete',
}

// 角色常量
const ROLES = {
  ADMIN: 'admin',
  MANAGER: 'manager',
  SUPERADMIN: 'superadmin',
  OPERATOR: 'operator',
  WAREHOUSE: 'warehouse',
  SALES: 'sales',
  FINANCE: 'finance',
  MEMBER: 'member',
}

// 角色默认权限映射（可在数据库动态配置）
const ROLE_PERMISSION_MAP = {
  [ROLES.ADMIN]: [...Object.values(PERMISSIONS), 'work_log:read', 'work_log:write', 'work_log_template:read', 'work_log_template:manage', 'quick_action:read', 'quick_action:write', 'quick_action:manage', 'quick_action:delete'],
  [ROLES.MANAGER]: [
    PERMISSIONS.WAREHOUSES_READ, PERMISSIONS.WAREHOUSES_WRITE,
    PERMISSIONS.INVENTORY_READ, PERMISSIONS.INVENTORY_WRITE,
    PERMISSIONS.PRODUCTS_READ, PERMISSIONS.PRODUCTS_WRITE, PERMISSIONS.PRODUCTS_DELETE, PERMISSIONS.PRODUCTS_WRITE,
    PERMISSIONS.QRCODE_READ, PERMISSIONS.QRCODE_WRITE, PERMISSIONS.QRCODE_DELETE,
    PERMISSIONS.SALES_READ, PERMISSIONS.SALES_WRITE,
    PERMISSIONS.REPORTS_READ,
    PERMISSIONS.OA_READ, PERMISSIONS.OA_WRITE,
    PERMISSIONS.USERS_READ,
    PERMISSIONS.APPROVALS_READ, PERMISSIONS.APPROVALS_WRITE,
    PERMISSIONS.SUPPLIERS_READ, PERMISSIONS.DEALERS_READ,
    PERMISSIONS.STORES_READ,
    PERMISSIONS.TASKS_READ,
    PERMISSIONS.AFTERSALE_READ, PERMISSIONS.AFTERSALE_WRITE,
    PERMISSIONS.ALERTS_READ, PERMISSIONS.ALERTS_WRITE,
  ],
  [ROLES.WAREHOUSE]: [
    PERMISSIONS.WAREHOUSES_READ, PERMISSIONS.WAREHOUSES_WRITE,
    PERMISSIONS.INVENTORY_READ, PERMISSIONS.INVENTORY_WRITE,
    PERMISSIONS.PRODUCTS_READ, PERMISSIONS.PRODUCTS_WRITE, PERMISSIONS.PRODUCTS_DELETE,
    PERMISSIONS.QRCODE_READ, PERMISSIONS.QRCODE_WRITE, PERMISSIONS.QRCODE_DELETE,
    PERMISSIONS.REPORTS_READ,
    PERMISSIONS.ALERTS_READ, PERMISSIONS.ALERTS_WRITE,
    PERMISSIONS.AFTERSALE_READ,
  ],
  [ROLES.OPERATOR]: [
    PERMISSIONS.PRODUCTS_READ, PERMISSIONS.PRODUCTS_WRITE, PERMISSIONS.PRODUCTS_DELETE, PERMISSIONS.PRODUCTS_WRITE,
    PERMISSIONS.QRCODE_READ, PERMISSIONS.QRCODE_WRITE, PERMISSIONS.QRCODE_DELETE,
    PERMISSIONS.INVENTORY_READ,
    PERMISSIONS.SALES_READ,
  ],
  [ROLES.SALES]: [
    PERMISSIONS.PRODUCTS_READ, PERMISSIONS.PRODUCTS_WRITE, PERMISSIONS.PRODUCTS_DELETE,
    PERMISSIONS.QRCODE_READ,
    PERMISSIONS.SALES_READ, PERMISSIONS.SALES_WRITE,
    PERMISSIONS.DEALERS_READ,
    PERMISSIONS.STORES_READ,
    PERMISSIONS.TASKS_READ,
  ],
  [ROLES.FINANCE]: [
    PERMISSIONS.FINANCE_READ,
    PERMISSIONS.REPORTS_READ,
    PERMISSIONS.APPROVALS_READ, PERMISSIONS.APPROVALS_WRITE,
    PERMISSIONS.SUPPLIERS_READ, PERMISSIONS.DEALERS_READ,
  ],
  [ROLES.MEMBER]: [
    PERMISSIONS.OA_READ, // 只读自己的OA信息
  ],
}

/**
 * 获取用户的所有权限（动态优先，回退到旧硬编码）
 * 1. 先查 users.permissions 字段（个性化扩展）
 * 2. 再查 rbac_role_permissions → rbac_permissions（按 users.role 找 rbac_roles.id）
 * 3. 最后回退到 ROLE_PERMISSION_MAP（兼容旧逻辑）
 */
async function getUserPermissions(userId, role) {
  let dynamicPerms = []

  // 1. 用户个性化权限字段（users.permissions JSON数组）
  try {
    const [users] = await pool.query(
      'SELECT permissions FROM users WHERE id = ?',
      [userId]
    )
    if (users.length && users[0].permissions) {
      const customPerms = JSON.parse(users[0].permissions)
      if (Array.isArray(customPerms) && customPerms.length > 0) {
        dynamicPerms = customPerms
      }
    }
  } catch (e) { /* ignore */ }

  // 2. 动态 RBAC 查库（通过 users.role 找 rbac_roles）
  if (dynamicPerms.length === 0 && role) {
    try {
      const [roleRows] = await pool.query(
        'SELECT id FROM rbac_roles WHERE name = ?',
        [role]
      )
      if (roleRows.length > 0) {
        const [permRows] = await pool.query(
          `SELECT p.name FROM rbac_permissions p
           JOIN rbac_role_permissions rp ON p.id = rp.permission_id
           WHERE rp.role_id = ?`,
          [roleRows[0].id]
        )
        if (permRows.length > 0) {
          dynamicPerms = permRows.map(r => r.name)
        }
      }
    } catch (e) { /* 表可能不存在，忽略 */ }
  }

  // 3. 如果有动态权限就返回，否则用旧硬编码回退
  if (dynamicPerms.length > 0) {
    return dynamicPerms
  }
  return ROLE_PERMISSION_MAP[role] || []
}

/**
 * 检查用户是否有指定权限
 */
export async function hasPermission(userId, role, permission) {
  const perms = await getUserPermissions(userId, role)
  return perms.includes(permission)
}

// ============================================
// 中间件
// ============================================

/**
 * 角色检查（原有功能保留）
 */
export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ code: 401, message: '未登录' })
    }
    if (!roles.includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '无权限访问' })
    }
    next()
  }
}

/**
 * 权限检查 - 必须拥有所有指定权限（AND）
 */
export function requirePermission(...requiredPerms) {
  return async (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ code: 401, message: '未登录' })
    }
    
    try {
      const userPerms = await getUserPermissions(req.user.id, req.user.role)
      const hasAll = requiredPerms.every(p => userPerms.includes(p))
      
      if (!hasAll) {
        return res.status(403).json({ code: 403, message: '无权限访问' })
      }
      next()
    } catch (err) {
      next(err)
    }
  }
}

/**
 * 权限检查 - 拥有任意指定权限即可（OR）
 */
export function requireAnyPermission(...requiredPerms) {
  return async (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ code: 401, message: '未登录' })
    }
    
    try {
      const userPerms = await getUserPermissions(req.user.id, req.user.role)
      const hasAny = requiredPerms.some(p => userPerms.includes(p))
      
      if (!hasAny) {
        return res.status(403).json({ code: 403, message: '无权限访问' })
      }
      next()
    } catch (err) {
      next(err)
    }
  }
}

// 导出权限常量供路由使用
export { PERMISSIONS, ROLES }
