/**
 * 彩美特系统角色与权限常量
 * 统一管理所有角色字符串，禁止硬编码
 *
 * 使用方式：
 *   import { ROLES, PERMS, hasRole, hasAnyRole } from '@/constants/roles'
 *   if (hasRole(userRole, ROLES.ADMIN)) { ... }
 */

// ─── 角色常量 ─────────────────────────────────────────────────────────────────
export const ROLES = Object.freeze({
  ADMIN:    'admin',      // 超级管理员
  MANAGER:  'manager',    // 经理/管理层
  OPERATOR: 'operator',   // 操作员
  MEMBER:   'member',     // 普通会员
})

// ─── 综合判断 ─────────────────────────────────────────────────────────────────
/** 是否为管理层（admin 或 manager） */
export const isManagement = (role) => role === ROLES.ADMIN || role === ROLES.MANAGER

/** 是否为执行层（operator 或 member） */
export const isOperator = (role) => role === ROLES.OPERATOR || role === ROLES.MEMBER

// ─── 权限常量（新版 RBAC）──────────────────────────────────────────────────────
export const PERMS = Object.freeze({
  // Dashboard
  DASHBOARD_VIEW:      'dashboard:view',
  DASHBOARD_STATS:     'dashboard:stats',

  // 产品/商品
  PRODUCT_VIEW:        'product:view',
  PRODUCT_MANAGE:     'product:manage',

  // 供应链
  SUPPLIER_VIEW:       'supplier:view',
  SUPPLIER_MANAGE:     'supplier:manage',

  // 工作日志
  WORK_LOG_READ:       'work_log:read',
  WORK_LOG_WRITE:      'work_log:write',
  WORK_LOG_DELETE:     'work_log:delete',
  WORK_LOG_TEMPLATE:   'work_log_template:manage',

  // OA / 考勤
  ATTENDANCE_VIEW:     'attendance:view',
  ATTENDANCE_MANAGE:   'attendance:manage',

  // 报表
  REPORT_VIEW:         'report:view',
  REPORT_EXPORT:       'report:export',

  // 财务
  FINANCE_VIEW:        'finance:view',
  FINANCE_MANAGE:      'finance:manage',

  // 系统设置
  SETTINGS_MANAGE:     'settings:manage',
  USER_MANAGE:         'user:manage',
})

// ─── 兼容旧代码的快捷方式 ─────────────────────────────────────────────────────
/** 兼容旧代码：直接导出角色字符串（不推荐） */
export const ROLE_ADMIN    = ROLES.ADMIN
export const ROLE_MANAGER  = ROLES.MANAGER
export const ROLE_OPERATOR = ROLES.OPERATOR
export const ROLE_MEMBER   = ROLES.MEMBER