-- ============================================================
-- 2026-08-25 rbac 全套修复 migration
-- 波哥派活: HK 横琴谢润东(hod)发不了任务, 深层审计发现 rbac 全套崩塌
-- 修复: 补任务权限三件套 + 给所有 active user 按 role 绑 rbac_user_roles
-- 影响: profile 1 (SGP) + profile 6 (HK) 都要跑
-- ============================================================

-- 1. 补任务类权限点 (tasks category 从 1 个扩展到 5 个)
INSERT IGNORE INTO rbac_permissions (name, label, category) VALUES
  ('task:create', '创建任务', 'tasks'),
  ('task:write', '编辑任务', 'tasks'),
  ('task:approve', '审核任务', 'tasks'),
  ('task:delete', '删除任务', 'tasks');

-- 2. 给所有有意义的 role 补 task 写入权限 (admin 已天然放行)
INSERT IGNORE INTO rbac_role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM rbac_roles r, rbac_permissions p
  WHERE p.category = 'tasks' AND p.name IN ('task:create','task:write','task:approve','task:delete')
    AND r.name IN ('hod','general_manager','team_leader','clerk','superuser');

-- 3. 给所有 active user 按 users.role 字段自动绑 rbac_user_roles
--    (修复 HK 0 覆盖率 + SGP 隐藏覆盖率问题)
--    COLLATE utf8mb4_unicode_ci 解决 utf8mb4_general_ci vs utf8mb4_unicode_ci 混用问题
INSERT IGNORE INTO rbac_user_roles (user_id, role_id)
  SELECT u.id, r.id FROM users u
  JOIN rbac_roles r ON r.name COLLATE utf8mb4_unicode_ci = u.role COLLATE utf8mb4_unicode_ci
  WHERE u.status = 'active' AND u.role IS NOT NULL AND u.role <> '';

-- 4. 给 admin (江清波 id=9) 强制绑 superuser role, 确保跨模块
INSERT IGNORE INTO rbac_user_roles (user_id, role_id)
  SELECT 9, id FROM rbac_roles WHERE name='superuser'
  ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- 5. 验证
SELECT 'rbac_permissions count' AS metric, COUNT(*) AS value FROM rbac_permissions
UNION ALL
SELECT 'rbac_role_permissions count', COUNT(*) FROM rbac_role_permissions
UNION ALL
SELECT 'rbac_user_roles count', COUNT(*) FROM rbac_user_roles
UNION ALL
SELECT 'active users', COUNT(*) FROM users WHERE status='active'
UNION ALL
SELECT 'users with rbac role', COUNT(DISTINCT user_id) FROM rbac_user_roles;