-- Layer 3.5: 异常申诉 (labor-appeals) — 2026-07-13 接力
-- 目的: 注册 module_key + RBAC 复用(已有 hr:read/hr:write/worker:read/worker:write)
-- 申诉 type='labor_appeal', type_code={attendance/workhours/payroll/evaluation/dispatch}

-- 1. server_modules: profile 1 (新加坡开发) 自动加
INSERT IGNORE INTO server_modules (server_profile_id, module_key)
  SELECT 1, 'labor-appeals'
  FROM DUAL
  WHERE NOT EXISTS (
    SELECT 1 FROM server_modules
    WHERE server_profile_id = 1 AND module_key = 'labor-appeals'
  );

-- 2. server_modules: profile 5 (SmartBiz) 加(labor 模块需要)
INSERT IGNORE INTO server_modules (server_profile_id, module_key)
  SELECT 5, 'labor-appeals'
  FROM DUAL
  WHERE NOT EXISTS (
    SELECT 1 FROM server_modules
    WHERE server_profile_id = 5 AND module_key = 'labor-appeals'
  );

-- 3. 标记:此处不需要新 rbac_permissions, 复用 worker:read/write + hr:read/write
SELECT 'employee-ai layer-3.5 labor-appeals module registered' AS status;
