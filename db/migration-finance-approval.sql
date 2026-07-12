-- ============================================
-- 财务系统与OA审批集成 - 扩展字段
-- ============================================

-- 扩展 expense_records 表，添加审批相关字段（如果不存在）
-- 字段已存在，跳过 ALTER TABLE

-- 更新现有记录的 approval_required 字段（金额>=5000的需要审批）
UPDATE expense_records SET approval_required = 1 WHERE amount >= 5000 AND approval_required = 0;

-- 添加财务审批类型到 approval_types 表（如果不存在）
INSERT INTO approval_types (code, name, icon, form_fields, status) VALUES
  ('expense', '费用支出审批', 'payments',
   '[{"name":"expense_id","label":"费用支出ID","type":"number","required":true},{"name":"amount","label":"金额","type":"number","required":true},{"name":"category","label":"费用类别","type":"text","required":true},{"name":"description","label":"费用说明","type":"textarea","required":true},{"name":"payee","label":"收款方","type":"text","required":false}]',
   'active'),
  ('payment', '大额付款审批', 'account_balance',
   '[{"name":"payment_amount","label":"付款金额","type":"number","required":true},{"name":"supplier_name","label":"供货商","type":"text","required":true},{"name":"payment_reason","label":"付款原因","type":"textarea","required":true}]',
   'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), form_fields = VALUES(form_fields);
