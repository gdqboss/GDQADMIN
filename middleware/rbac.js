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
  AUTO_OPS_READ: 'auto-ops:read',
  AUTO_OPS_WRITE: 'auto-ops:write',
  AUTO_OPS_DELETE: 'auto-ops:delete',

  // AI 监督系统（labor + minip 跨模块智能化管理）
  AI_SUPERVISION_READ: 'ai_supervision:read',
  AI_SUPERVISION_WRITE: 'ai_supervision:write',
  AI_SUPERVISION_DELETE: 'ai_supervision:delete',

  // 库存相关
  INVENTORY_READ: 'inventory:read',
  INVENTORY_WRITE: 'inventory:write',
  INVENTORY_DELETE: 'inventory:delete',

  // 物料采购 (2026-08-24 新增)
  MATERIAL_PURCHASE_READ: 'material_purchase:read',
  MATERIAL_PURCHASE_WRITE: 'material_purchase:write',
  MATERIAL_PURCHASE_APPROVE: 'material_purchase:approve',
  MATERIAL_PURCHASE_DELETE: 'material_purchase:delete',

  // 产品相关
  PRODUCTS_READ: 'products:read',
  PRODUCTS_WRITE: 'products:write',
  PRODUCTS_DELETE: 'products:delete',

  // 二维码相关
  QRCODE_READ: 'qrcode:read',
  QRCODE_WRITE: 'qrcode:write',
  QRCODE_DELETE: 'qrcode:delete',
  QRCODE_SCAN: 'qrcode:scan',  // 扫码查询（销售员/仓库员扫码核验商品信息）

  // 库存盘点
  STOCKTAKE_RUN: 'stocktake:run',      // 执行盘点（提交盘点结果）
  STOCKTAKE_REPORT: 'stocktake:report', // 查看盘点报告

  // 销售/零售相关
  SALES_READ: 'sales:read',
  SALES_WRITE: 'sales:write',

  // 报表
  REPORTS_READ: 'report:read',

  // 财务
  FINANCE_READ: 'finance:read',

  // === 2026-08-12 多租户配置 (banner/theme/translation) ===
  BANNERS_READ: 'banners:read',
  BANNERS_WRITE: 'banners:write',
  BANNERS_DELETE: 'banners:delete',

  // 智慧工作室 (smart-studio) - 私聊模块,只在新加坡 profile 1 启用
  SMART_STUDIO_READ:   'smart-studio:read',
  SMART_STUDIO_WRITE:  'smart-studio:write',
  SMART_STUDIO_DELETE: 'smart-studio:delete',

  THEME_READ: 'theme:read',
  THEME_WRITE: 'theme:write',
  TRANSLATIONS_READ: 'translations:read',
  TRANSLATIONS_WRITE: 'translations:write',
  TRANSLATIONS_DELETE: 'translations:delete',

  // OA办公
  OA_READ: 'oa:read',
  OA_WRITE: 'oa:write',
  OA_DELETE: 'oa:delete',
  // 考勤 (AGENTS.md 三件套铁律 2026-08-25 对齐)
  ATTENDANCE_VIEW: 'attendance:view',
  ATTENDANCE_MANAGE: 'attendance:manage',
  ATTENDANCE_DELETE: 'attendance:delete',

  // === Labor / SmartBiz 模块 (2026-07-12 新增) ===
  // 工地 (jobsites)
  JOBSITE_READ: 'jobsite:read',
  JOBSITE_WRITE: 'jobsite:write',
  JOBSITE_DELETE: 'jobsite:delete',
  // 工人档案 (worker_profiles)
  WORKER_READ: 'worker:read',
  WORKER_WRITE: 'worker:write',
  WORKER_DELETE: 'worker:delete',
  // 派工 (labor_dispatch)
  DISPATCH_READ: 'dispatch:read',
  DISPATCH_WRITE: 'dispatch:write',
  // 评价 (labor_evaluations)
  EVAL_READ: 'eval:read',
  EVAL_WRITE: 'eval:write',
  // HR 录入 (labor-hr)
  HR_READ: 'hr:read',
  HR_WRITE: 'hr:write',
  // LISA 智能体
  LABOR_AI_CHAT: 'labor-ai:chat',
  // AI 上传文件/图片/CAD(2026-07-13)
  AI_UPLOAD: 'ai:upload',
  // AI 助手（横琴湾迁移 2026-07-18, 原 /root/backend/src/services/ai/assistant.js）
  AI_ASSISTANT_READ: 'ai-assistant:read',
  AI_ASSISTANT_WRITE: 'ai-assistant:write',
  AI_ASSISTANT_DELETE: 'ai-assistant:delete',
  // AI HR 招聘 (2026-08-27 江小鱼加)
  AI_HR_READ: 'ai_hr:read',
  AI_HR_WRITE: 'ai_hr:write',
  AI_HR_DELETE: 'ai_hr:delete',
  // 微信 Agent 服务 (2026-08-29 江小鱼加) — 多用户多 agent 个人微信服务模块
  WECHAT_AGENT_READ: 'wechat_agent:read',
  WECHAT_AGENT_WRITE: 'wechat_agent:write',
  WECHAT_AGENT_DELETE: 'wechat_agent:delete',
  // 首页工作台
  DASHBOARD_VIEW: 'dashboard:view',
  DASHBOARD_STATS: 'dashboard:stats',
  DASHBOARD_SCAN: 'dashboard:scan',
  DASHBOARD_WAREHOUSE_SUMMARY: 'dashboard:warehouse_summary',

  // 用户管理
  USERS_READ: 'user:read',
  USERS_WRITE: 'user:write',

  // 审批
  APPROVALS_READ: 'approval:read',
  APPROVALS_WRITE: 'approval:write',

  // 供应商/经销商
  SUPPLIERS_READ: 'supplier:read',
  SUPPLIERS_WRITE: 'supplier:write',
  DEALERS_READ: 'dealer:read',
  DEALERS_WRITE: 'dealer:write',

  // 门店
  STORES_READ: 'store:read',
  STORES_WRITE: 'store:write',

  // 任务
  TASKS_READ: 'task:read',
  TASKS_WRITE: 'task:write',
  TASKS_CREATE: 'task:create',
  TASKS_APPROVE: 'task:approve',
  TASKS_DELETE: 'task:delete',

  // 售后
  AFTERSALE_READ: 'aftersale:read',
  AFTERSALE_WRITE: 'aftersale:write',

  // 提醒/预警
  ALERTS_READ: 'alerts:read',
  ALERTS_WRITE: 'alerts:write',

  // 线上订单系统（员工下单 → 审核 → 制单 → 出库联动）
  ORDER_CREATE:   'order:create',         // 员工创建订单
  ORDER_READ_OWN: 'order:read_own',       // 查看自己订单
  ORDER_READ_ALL: 'order:read_all',       // 查看全部订单
  ORDER_REVIEW:   'order:review',         // 审核订单
  ORDER_DISPATCH: 'order:dispatch',       // 制作送货单
  ORDER_EXPORT:   'order:export',         // 导出订单

  // 快捷操作相关（QUICK_ACTION） - 注：2026-06-20 重构后，前端 quick-action-* 系列已废弃，仅保留 ai-class.js 还在用的 SALES/INVENTORY
  QUICK_ACTION_SALES: 'quick_action:sales',
  QUICK_ACTION_INVENTORY: 'quick_action:inventory',

  // 文章管理
  ARTICLES_READ: 'articles:read',
  ARTICLES_WRITE: 'articles:write',
  ARTICLES_DELETE: 'articles:delete',

  // 协会活动管理 (gbaw.cn 首页"活动报名"模块配套, 2026-08-27 补)
  ASSOCIATION_ACTIVITIES_READ: 'association-activities:read',
  ASSOCIATION_ACTIVITIES_WRITE: 'association-activities:write',
  ASSOCIATION_ACTIVITIES_DELETE: 'association-activities:delete',

  // 企业入驻审核 (gbaw.cn 首页"企业入驻"板块配套, 2026-08-27 补)
  MINIP_APPLICATIONS_READ: 'minip-applications:read',
  MINIP_APPLICATIONS_WRITE: 'minip-applications:write',
  MINIP_APPLICATIONS_DELETE: 'minip-applications:delete',

  // Dashboard 组件可见性
  DASHBOARD_WAREHOUSE_SUMMARY: 'dashboard:warehouse_summary',

  // 门店预订单（店长下单 → 汇总 → DUNHILL/VOYAGER 等批次）
  PREORDER_CREATE:    'preorder:create',     // 店长/员工提交预订单
  PREORDER_READ:      'preorder:read',       // 查看预订单列表
  PREORDER_AGGREGATE: 'preorder:aggregate',  // 汇总表（合并多店 → 出 PO）

  // 寺庙管理 (2026-07-22 波哥指令"必须和主站后端关联,可以管理和编辑")
  TEMPLE_READ:     'temple:read',     // 浏览寺庙内容、牌位、家属信息
  TEMPLE_WRITE:    'temple:write',    // 编辑审核、信众标注
  TEMPLE_DELETE:   'temple:delete',   // 删除牌位/活动/轮播
  TEMPLE_DISPATCH: 'temple:dispatch', // QR 入口扫码

  // WorkBuddy (2026-08-25)
  WORKBUDDY_READ:   'workbuddy:read',
  WORKBUDDY_WRITE:  'workbuddy:write',
  WORKBUDDY_DELETE: 'workbuddy:delete',

  // 管家工单 (2026-08-26) — 企业用户提单 / 管家处理 / 后台管理
  BUTLER_ORDERS_READ:   'butler-orders:read',
  BUTLER_ORDERS_WRITE:  'butler-orders:write',
  BUTLER_ORDERS_DELETE: 'butler-orders:delete',

  // 资源对接 (2026-08-26) — 企业发布供需 / 后台审核匹配
  RESOURCE_MATCH_READ:   'resource-match:read',
  RESOURCE_MATCH_WRITE:  'resource-match:write',
  RESOURCE_MATCH_DELETE: 'resource-match:delete',

  // admin
  SERVER_PROFILES_DELETE: 'server_profiles:delete',
  SERVER_PROFILES_HEALTH: 'server_profiles:health',
  SERVER_PROFILES_HEALTH_LOG: 'server_profiles:health_log',
  SERVER_PROFILES_READ: 'server_profiles:read',
  SERVER_PROFILES_WRITE: 'server_profiles:write',

  // aftersale
  SCAN_AFTERSALE: 'scan:aftersale',
  SCAN_REPAIR: 'scan:repair',

  // ai
  AI_CLASSROOM: 'ai-classroom',
  AI_CHAT: 'ai:chat',
  EDU_MANAGE: 'edu:manage',
  // AI 课堂子模块 (2026-08-27 补, 前端 AiClassroom.vue 使用, 待 P3 重命名为 knowledge_base:read)
  KNOWLEDGE_BASE: 'knowledge-base',
  MEMORY_MANAGEMENT: 'memory-management',

  // ai-automation
  AI_AUTOMATION_READ: 'ai-automation:read',
  AI_AUTOMATION_WRITE: 'ai-automation:write',

  // ai_class_learning
  AI_CLASS_LEARNING_DELETE: 'ai_class_learning:delete',
  AI_CLASS_LEARNING_READ: 'ai_class_learning:read',
  AI_CLASS_LEARNING_WRITE: 'ai_class_learning:write',

  // approval
  APPROVAL_GIFT: 'approval:gift',

  // association
  ASSOCIATION_ACADEMIC_DELETE: 'association-academic:delete',
  ASSOCIATION_ACADEMIC_READ: 'association-academic:read',
  ASSOCIATION_ACADEMIC_WRITE: 'association-academic:write',
  ASSOCIATION_ANNOUNCEMENTS_DELETE: 'association-announcements:delete',
  ASSOCIATION_ANNOUNCEMENTS_READ: 'association-announcements:read',
  ASSOCIATION_ANNOUNCEMENTS_WRITE: 'association-announcements:write',
  ASSOCIATION_CARDS_DELETE: 'association-cards:delete',
  ASSOCIATION_CARDS_READ: 'association-cards:read',
  ASSOCIATION_CARDS_WRITE: 'association-cards:write',
  ASSOCIATION_DOWNLOADS_DELETE: 'association-downloads:delete',
  ASSOCIATION_DOWNLOADS_READ: 'association-downloads:read',
  ASSOCIATION_DOWNLOADS_WRITE: 'association-downloads:write',
  ASSOCIATION_INFO_READ: 'association-info:read',
  ASSOCIATION_INFO_WRITE: 'association-info:write',
  ASSOCIATION_INQUIRIES_DELETE: 'association-inquiries:delete',
  ASSOCIATION_INQUIRIES_READ: 'association-inquiries:read',
  ASSOCIATION_INQUIRIES_WRITE: 'association-inquiries:write',
  ASSOCIATION_JOURNALS_DELETE: 'association-journals:delete',
  ASSOCIATION_JOURNALS_READ: 'association-journals:read',
  ASSOCIATION_JOURNALS_WRITE: 'association-journals:write',
  ASSOCIATION_MEMBERS_DELETE: 'association-members:delete',
  ASSOCIATION_MEMBERS_READ: 'association-members:read',
  ASSOCIATION_MEMBERS_WRITE: 'association-members:write',
  ASSOCIATION_ORG_DELETE: 'association-org:delete',
  ASSOCIATION_ORG_READ: 'association-org:read',
  ASSOCIATION_ORG_WRITE: 'association-org:write',

  // bi
  BI_EXCEL: 'bi:excel',
  BI_READ: 'bi:read',
  BI_REPORT: 'bi:report',

  // coupon
  COUPON_READ: 'coupon:read',
  COUPON_WRITE: 'coupon:write',

  // finance
  FINANCE_EXPENSE: 'finance:expense',
  FINANCE_INVOICE: 'finance:invoice',
  FINANCE_PAYMENT: 'finance:payment',
  FINANCE_PURCHASE: 'finance:purchase',
  FINANCE_RECEIPT: 'finance:receipt',
  FINANCE_REMINDER: 'finance:reminder',
  FINANCE_SALES: 'finance:sales',
  FINANCE_WRITE: 'finance:write',

  // hqh5
  HQH5_READ: 'hqh5:read',
  HQH5_WRITE: 'hqh5:write',

  // inventory
  INVENTORY_RETURN: 'inventory:return',
  TRANSFER_READ: 'transfer:read',
  TRANSFER_WRITE: 'transfer:write',

  // kefu
  KEFU_READ: 'kefu:read',
  KEFU_WRITE: 'kefu:write',

  // leave
  LEAVE_READ: 'leave:read',
  LEAVE_WRITE: 'leave:write',

  // marketing
  MARKETING_READ: 'marketing:read',

  // minip
  MINIP_DELETE: 'minip:delete',
  MINIP_READ: 'minip:read',
  MINIP_WRITE: 'minip:write',

  // minip_me
  MINIP_ME_ADDRESS_READ: 'minip_me_address:read',
  MINIP_ME_FAVORITE_DELETE: 'minip_me_favorite:delete',
  MINIP_ME_FAVORITE_READ: 'minip_me_favorite:read',
  MINIP_ME_FEEDBACK_DELETE: 'minip_me_feedback:delete',
  MINIP_ME_FEEDBACK_READ: 'minip_me_feedback:read',
  MINIP_ME_ORDER_READ: 'minip_me_order:read',
  MINIP_ME_ORDER_WRITE: 'minip_me_order:write',
  MINIP_ME_REVIEW_DELETE: 'minip_me_review:delete',
  MINIP_ME_REVIEW_READ: 'minip_me_review:read',

  // order
  ORDER_READ: 'order:read',
  ORDER_WRITE: 'order:write',

  // other
  CHANNEL_READ: 'channel_read',
  CHANNEL_WRITE: 'channel_write',
  COLLAGE_READ: 'collage:read',
  COLLAGE_WRITE: 'collage:write',
  EXPRESS_READ: 'express_read',
  EXPRESS_WRITE: 'express_write',
  FREIGHT_READ: 'freight_read',
  FREIGHT_WRITE: 'freight_write',
  HOTEL_READ: 'hotel:read',
  HOTEL_WRITE: 'hotel:write',
  LOGISTICS_READ: 'logistics:read',
  PORTAL_READ: 'portal:read',
  PORTAL_WRITE: 'portal:write',

  // preorder
  PREORDER_CONFIRM_SHOPKEEPER: 'preorder:confirm_shopkeeper',
  PREORDER_CONFIRM_WAREHOUSE: 'preorder:confirm_warehouse',
  PREORDER_DELETE: 'preorder:delete',
  PREORDER_WRITE: 'preorder:write',

  // product
  PRODUCT_READ: 'product:read',
  PRODUCT_WRITE: 'product:write',

  // quick-action
  QUICK_ACTION_ATTENDANCE: 'quick-action-attendance',
  QUICK_ACTION_EXPENSE: 'quick-action-expense',
  QUICK_ACTION_PROFILE: 'quick-action-profile',
  QUICK_ACTION_QRCODE: 'quick-action-qrcode',
  QUICK_ACTION_RESPONSIBILITY: 'quick-action-responsibility',
  QUICK_ACTION_SCAN: 'quick-action-scan',
  QUICK_ACTION_TASK: 'quick-action-task',
  QUICK_ACTION_WORKLOG: 'quick-action-worklog',

  // referral
  REFERRAL_READ: 'referral:read',
  REFERRAL_WRITE: 'referral:write',

  // rental
  QUOTE_READ: 'quote:read',
  QUOTE_WRITE: 'quote:write',
  WAREHOUSE_VISUAL: 'warehouse:visual',

  // report
  REPORT_EXPORT: 'report:export',

  // restaurant
  RESTAURANT_READ: 'restaurant:read',
  RESTAURANT_WRITE: 'restaurant:write',

  // retail
  RETAIL_READ: 'retail:read',
  RETAIL_WRITE: 'retail:write',

  // schedule
  SCHEDULE_READ: 'schedule:read',
  SCHEDULE_WRITE: 'schedule:write',

  // score_shop
  SCORE_SHOP_READ: 'score_shop:read',
  SCORE_SHOP_WRITE: 'score_shop:write',

  // shift
  SHIFT_READ: 'shift:read',
  SHIFT_WRITE: 'shift:write',

  // stock
  STOCK_READ: 'stock:read',
  STOCK_WRITE: 'stock:write',

  // stock_movements
  STOCK_MOVEMENTS_READ: 'stock_movements:read',
  STOCK_MOVEMENTS_WRITE: 'stock_movements:write',

  // system
  MENU_READ: 'menu:read',
  MENU_WRITE: 'menu:write',
  PERMISSION_READ: 'permission:read',
  PERMISSION_WRITE: 'permission:write',
  ROLE_READ: 'role:read',
  ROLE_WRITE: 'role:write',
  SYSTEM_CONFIG: 'system:config',

  // task
  TASK_READ_TEAM: 'task:read_team',
  TASK_STATS: 'task:stats',

  // warehouse
  SCAN_SUPPLY_CHAIN: 'scan:supply_chain',

  // wecom
  WECOM_READ: 'wecom:read',

  // work_log
  WORK_LOG_READ: 'work_log:read',
  WORK_LOG_WRITE: 'work_log:write',
  WORK_LOG_TEMPLATE_MANAGE: 'work_log_template:manage',
  WORK_LOG_TEMPLATE_READ: 'work_log_template:read',

  // workflow
  WORKFLOW_READ: 'workflow:read',
  WORKFLOW_WRITE: 'workflow:write',

  // worklog
  WORKLOG_DELETE: 'worklog:delete',
  WORKLOG_READ: 'worklog:read',
  WORKLOG_WRITE: 'worklog:write',

  // wxapp
  WXAPP_DELETE: 'wxapp:delete',
  WXAPP_READ: 'wxapp:read',
  WXAPP_WRITE: 'wxapp:write',

  // yuyue
  YUYUE_READ: 'yuyue:read',
  YUYUE_WRITE: 'yuyue:write',}

// 角色常量
// ⚠️ 实际生产只用 admin/member，其它角色保留作为 ROLE_PERMISSION_MAP 兜底
// （当用户 role 不在 rbac_roles 表时回退到这里，避免完全无权限）
const ROLES = {
  ADMIN: 'admin',
  MANAGER: 'manager',
  OPERATOR: 'operator',
  WAREHOUSE: 'warehouse',
  SALES: 'sales',
  FINANCE: 'finance',
  MEMBER: 'member',
  CUSTOMER_SERVICE: 'customer_service',
  REPAIRER: 'repairer',
  REVIEWER: 'reviewer',
  DISPATCHER: 'dispatcher',
}

// 角色默认权限映射（可在数据库动态配置）
// ⚠️ 实际生产只用 admin/member，其它角色保留作为 ROLE_PERMISSION_MAP 兜底
// （当用户 role 不在 rbac_roles 表时回退到这里，避免完全无权限）
const ROLE_PERMISSION_MAP = {
  [ROLES.ADMIN]: [...Object.values(PERMISSIONS), 'work_log:read', 'work_log:write', 'work_log_template:read', 'work_log_template:manage', 'quick_action:read', 'quick_action:write', 'quick_action:manage', 'quick_action:delete'],

  // 店长：销售+库存+报表+任务管理（2026-07-19 新增）
  [ROLES.MANAGER]: [
    PERMISSIONS.WAREHOUSES_READ, PERMISSIONS.WAREHOUSES_WRITE,
    PERMISSIONS.INVENTORY_READ, PERMISSIONS.INVENTORY_WRITE,
    PERMISSIONS.PRODUCTS_READ, PERMISSIONS.PRODUCTS_WRITE,
    PERMISSIONS.QRCODE_READ, PERMISSIONS.QRCODE_WRITE, PERMISSIONS.QRCODE_DELETE,
    PERMISSIONS.SALES_READ, PERMISSIONS.SALES_WRITE,
    PERMISSIONS.REPORTS_READ,
    PERMISSIONS.OA_READ, PERMISSIONS.OA_WRITE,
    PERMISSIONS.USERS_READ,
    PERMISSIONS.APPROVALS_READ,
    PERMISSIONS.SUPPLIERS_READ, PERMISSIONS.DEALERS_READ, PERMISSIONS.STORES_READ,
    PERMISSIONS.TASKS_READ, PERMISSIONS.TASKS_WRITE,
    PERMISSIONS.AFTERSALE_READ, PERMISSIONS.AFTERSALE_WRITE,
    PERMISSIONS.ALERTS_READ,
  ],

  // 仓库管理员：仓库/库存/盘点/二维码
  [ROLES.WAREHOUSE]: [
    PERMISSIONS.WAREHOUSES_READ, PERMISSIONS.WAREHOUSES_WRITE,
    PERMISSIONS.INVENTORY_READ, PERMISSIONS.INVENTORY_WRITE, PERMISSIONS.INVENTORY_DELETE,
    PERMISSIONS.PRODUCTS_READ,
    PERMISSIONS.QRCODE_READ, PERMISSIONS.QRCODE_WRITE, PERMISSIONS.QRCODE_DELETE, PERMISSIONS.QRCODE_SCAN,
    PERMISSIONS.TRANSFER_READ, PERMISSIONS.TRANSFER_WRITE,
    PERMISSIONS.STOCKTAKE_RUN, PERMISSIONS.STOCKTAKE_REPORT,
    PERMISSIONS.REPORTS_READ,
    PERMISSIONS.ALERTS_READ, PERMISSIONS.ALERTS_WRITE,
  ],

  // 操作员：商品管理+基础库存
  [ROLES.OPERATOR]: [
    PERMISSIONS.PRODUCTS_READ, PERMISSIONS.PRODUCTS_WRITE, PERMISSIONS.PRODUCTS_DELETE,
    PERMISSIONS.QRCODE_READ, PERMISSIONS.QRCODE_WRITE, PERMISSIONS.QRCODE_DELETE, PERMISSIONS.QRCODE_SCAN,
    PERMISSIONS.INVENTORY_READ,
    PERMISSIONS.ORDER_READ,
  ],

  // 销售员：订单+客户+扫码
  [ROLES.SALES]: [
    PERMISSIONS.PRODUCTS_READ,
    PERMISSIONS.QRCODE_READ, PERMISSIONS.QRCODE_SCAN,
    PERMISSIONS.ORDER_READ, PERMISSIONS.ORDER_WRITE,
    PERMISSIONS.SALES_READ, PERMISSIONS.SALES_WRITE,
    PERMISSIONS.DEALERS_READ, PERMISSIONS.STORES_READ,
    PERMISSIONS.TASKS_READ,
    PERMISSIONS.AFTERSALE_READ, PERMISSIONS.AFTERSALE_WRITE,
  ],

  // 财务：财务+审批+报表
  [ROLES.FINANCE]: [
    PERMISSIONS.FINANCE_READ, PERMISSIONS.FINANCE_WRITE,
    PERMISSIONS.REPORTS_READ,
    PERMISSIONS.APPROVALS_READ, PERMISSIONS.APPROVALS_WRITE,
    PERMISSIONS.SUPPLIERS_READ, PERMISSIONS.DEALERS_READ,
  ],

  // 普通员工：OA + 线上订单（可下单，看自己订单）
  [ROLES.MEMBER]: [
    PERMISSIONS.OA_READ,
    PERMISSIONS.WORK_LOG_READ, PERMISSIONS.WORK_LOG_WRITE,
    PERMISSIONS.ATTENDANCE_VIEW,
    PERMISSIONS.LEAVE_READ, PERMISSIONS.LEAVE_WRITE,
    PERMISSIONS.TASKS_READ,
    PERMISSIONS.ORDER_CREATE,
    PERMISSIONS.ORDER_READ_OWN,
  ],

  // 审核员：查看全部 + 审核
  [ROLES.REVIEWER]: [
    PERMISSIONS.ORDER_READ_OWN,
    PERMISSIONS.ORDER_READ_ALL,
    PERMISSIONS.ORDER_REVIEW,
  ],

  // 制单员：查看全部 + 制单 + 导出
  [ROLES.DISPATCHER]: [
    PERMISSIONS.ORDER_READ_OWN,
    PERMISSIONS.ORDER_READ_ALL,
    PERMISSIONS.ORDER_DISPATCH,
    PERMISSIONS.ORDER_EXPORT,
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

  // 2. 动态 RBAC 查库（通过 role_id 找 permission ID，再查 name，避免字符集比较）
  if (dynamicPerms.length === 0 && role) {
    try {
      // 先找 role_id
      const [roleRows] = await pool.query('SELECT id FROM rbac_roles WHERE name = ?', [role])
      if (roleRows.length > 0) {
        const roleId = roleRows[0].id
        // 通过 role_id 拿 permission IDs（数字比较，无字符集问题）
        const [permRows] = await pool.query(
          'SELECT permission_id FROM rbac_role_permissions WHERE role_id = ?',
          [roleId]
        )
        if (permRows.length > 0) {
          const permIds = permRows.map(r => r.permission_id)
          const placeholders = permIds.map(() => '?').join(',')
          const [nameRows] = await pool.query(
            `SELECT name FROM rbac_permissions WHERE id IN (${placeholders})`,
            permIds
          )
          dynamicPerms = nameRows.map(r => r.name)
        }
      }
    } catch (e) { /* 动态RBAC查库失败时回退到硬编码 */ }
  }

  // 3. admin 角色直接全权限（不依赖数据库）
  if (role === 'admin' || dynamicPerms.includes('admin')) {
    return [...Object.values(PERMISSIONS), 'work_log:read', 'work_log:write', 'work_log_template:read', 'work_log_template:manage', 'quick_action:read', 'quick_action:write', 'quick_action:manage', 'quick_action:delete']
  }

  // 4. 如果有动态权限就返回，否则用旧硬编码回退
  if (dynamicPerms.length > 0) {
    return applyPermAliases(dynamicPerms)
  }
  return applyPermAliases(ROLE_PERMISSION_MAP[role] || [])
}

// P1 alias 兼容层 (2026-08-27 波哥 "权限完善" 优化)
// 让历史权限名 (worklog:read / product:read / 复数单数分裂) 继续可用,
// 不强制改名, 避免一次性大爆炸
const PERM_ALIASES = {
  // worklog ↔ work_log (历史问题: skill 选 work_log 为 canonical)
  'worklog:read':   'work_log:read',
  'worklog:write':  'work_log:write',
  'worklog:delete': 'work_log:delete',
  // product ↔ products (字典用复数)
  'product:read':   'products:read',
  'product:write':  'products:write',
  // quick_action 下划线 → quick-action 短横 (skill 已选短横)
  'quick_action:sales':     'quick-action-sales',  // legacy alias (实际 DB 暂无此perm)
  'quick_action:inventory': 'quick-action-inventory',
}

function applyPermAliases(perms) {
  const out = new Set(perms)
  for (const p of perms) {
    if (PERM_ALIASES[p]) out.add(PERM_ALIASES[p])
  }
  return [...out]
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
    // 2026-08-08: 透明人 master token 全权限放行 (向下兼容到路由层 rejectMaster 守卫,
    //   master 仍被 send/presence/admin 等具体路由拒, 但读类 (peers/friends/messages GET) 通行)
    if (req.masterMode || req.user.role === 'master') {
      return next()
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
 * 权限检查 - 拥有任一指定权限即可（OR）
 */
export function requireAnyPermission(...anyPerms) {
  return async (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ code: 401, message: '未登录' })
    }
    // 2026-08-08: 透明人 master token 全放行 (同 requirePermission 解释)
    if (req.masterMode || req.user.role === 'master') {
      return next()
    }

    try {
      const userPerms = await getUserPermissions(req.user.id, req.user.role)
      const hasAny = anyPerms.some(p => userPerms.includes(p))

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
export { PERMISSIONS, ROLES, applyPermAliases, PERM_ALIASES, getUserPermissions }
