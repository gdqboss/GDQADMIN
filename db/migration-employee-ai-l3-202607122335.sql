-- Layer 3: HR 录入 + 申诉 type 文档化
-- 2026-07-12 22:35 CST relay 完成
-- 目的: 补 rbac_permissions(hr:read / hr:write) + server_modules(labor-hr)

-- 1. rbac_permissions: HR 三件套(historically 11 labor rows 已有 jobsite/worker/dispatch/eval)
--    现在加 hr:read / hr:write (无 hr:delete — 删除走 worker:delete)
INSERT IGNORE INTO rbac_permissions (name, label, category, description) VALUES
  ('hr:read',       'HR-查看',       'labor', 'HR 查看员工档案/合同/证件/入职离职'),
  ('hr:write',      'HR-编',         'labor', 'HR 录入员工资料/入职/离职/续约/证件');

-- 2. server_modules: profile 5(SmartBiz)添加 labor-hr 模块
INSERT IGNORE INTO server_modules (server_profile_id, module_key)
  SELECT 5, 'labor-hr'
  FROM DUAL
  WHERE NOT EXISTS (
    SELECT 1 FROM server_modules
    WHERE server_profile_id = 5 AND module_key = 'labor-hr'
  );

-- 3. server_modules: profile 1(新加坡开发)自动加(永远全选)
INSERT IGNORE INTO server_modules (server_profile_id, module_key)
  SELECT 1, 'labor-hr'
  FROM DUAL
  WHERE NOT EXISTS (
    SELECT 1 FROM server_modules
    WHERE server_profile_id = 1 AND module_key = 'labor-hr'
  );
