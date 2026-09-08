-- ============================================================
-- OA 流程引擎升级 · DB 迁移 (2026-09-08 · AutoClaw)
-- 依据: OA引擎升级_交接_AutoClaw_引擎后端_20260908.md §3
-- 前提: SGP gdq / HK gdq_hk 四表均 0 行(实查 2026-09-08), 直接改造
-- 备份: /root/backups/backup-workflow4tables-pre-oaflow-20260908-20xx.sql
-- 兼容: ALTER 走 ALGORITHM=INPLACE 规避 mysql /tmp 无写权限问题(Errcode 13)
--       枚举扩展 status/node_type 属 metadata-only 变更, INPLACE 安全
-- ============================================================

-- ---------- workflow_definitions ----------
-- category 列已存在(varchar50, 旧注释: approval/notification/automation — 语义扩展为业务分类, 不改类型)
ALTER TABLE workflow_definitions
  ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
  ADD COLUMN form_config LONGTEXT NULL DEFAULT NULL COMMENT '字段配置(前端 oa-flows f() 同构 JSON 数组)' AFTER description,
  ADD INDEX idx_tenant (tenant_id),
  ALGORITHM=INPLACE, LOCK=NONE;

-- ---------- workflow_instances ----------
-- status 扩展: + returned(打回待改) / rejected(整单驳回终态)
ALTER TABLE workflow_instances
  MODIFY COLUMN status ENUM('running','completed','terminated','suspended','returned','rejected') DEFAULT 'running',
  ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
  ADD COLUMN return_info LONGTEXT NULL DEFAULT NULL COMMENT '打回信息{fromNodeId,comment,returnedAt}' AFTER current_node,
  ADD COLUMN flow_snapshot LONGTEXT NULL DEFAULT NULL COMMENT '发起时整份flow_config快照,定义后续修改不影响在途实例' AFTER return_info,
  ADD INDEX idx_tenant (tenant_id),
  ADD INDEX idx_tenant_status (tenant_id, status),
  ALGORITHM=INPLACE, LOCK=NONE;

-- ---------- workflow_tasks ----------
-- node_type 扩展: + approve(审批) / vote(投票: act approve=赞成 reject=反对)
-- status 扩展: + cancelled(超时路由/打回时取消)
ALTER TABLE workflow_tasks
  MODIFY COLUMN node_type ENUM('start','approval','notification','auto_action','condition','end','approve','vote') NOT NULL,
  MODIFY COLUMN status ENUM('pending','in_progress','completed','skipped','timeout','cancelled') DEFAULT 'pending',
  ADD COLUMN cancelled_at DATETIME NULL DEFAULT NULL COMMENT '任务取消时间(超时路由/打回)' AFTER completed_at,
  ADD COLUMN enter_at DATETIME NULL DEFAULT NULL COMMENT '节点进入时间(timeout网关计时基准)' AFTER created_at,
  ALGORITHM=INPLACE, LOCK=NONE;

-- ---------- workflow_logs ----------
-- 沿用原结构, action 为 varchar 无需改
-- action 取值: start/gate_pass/gate_route/gate_reject/gate_block/approve/reject/
--              return/resubmit/cc_pass/end/timeout_route/warn
