-- Migration: OA System Enhanced
-- Date: 2026-03-02
-- Description: Organization structure, attendance GPS, work log templates, approval flow engine

-- 1. 部门表（组织架构）
CREATE TABLE IF NOT EXISTS departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  parent_id INT DEFAULT NULL COMMENT '上级部门ID',
  level INT DEFAULT 1 COMMENT '部门层级 1-5',
  manager_id INT DEFAULT NULL COMMENT '部门负责人user_id',
  sort_order INT DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. 职级表（行政等级）
CREATE TABLE IF NOT EXISTS job_levels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE COMMENT '职级名称：总监/经理/专员',
  level INT NOT NULL UNIQUE COMMENT '等级数字，越大权限越高',
  description VARCHAR(200),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO job_levels (name, level, description) VALUES
  ('总监', 5, '公司高层管理'),
  ('副总监', 4, '部门副职'),
  ('经理', 3, '部门负责人'),
  ('主管', 2, '团队负责人'),
  ('专员', 1, '普通员工');

-- 3. 增强考勤表（GPS定位 + 异常说明）
ALTER TABLE attendance
  ADD COLUMN gps_lat DECIMAL(10,7) DEFAULT NULL COMMENT 'GPS纬度',
  ADD COLUMN gps_lng DECIMAL(10,7) DEFAULT NULL COMMENT 'GPS经度',
  ADD COLUMN gps_accuracy DECIMAL(6,2) DEFAULT NULL COMMENT 'GPS精度(米)',
  ADD COLUMN device_info VARCHAR(200) DEFAULT NULL COMMENT '设备信息',
  ADD COLUMN ip_address VARCHAR(50) DEFAULT NULL COMMENT 'IP地址',
  ADD COLUMN abnormal_reason TEXT DEFAULT NULL COMMENT '异常打卡说明',
  ADD COLUMN approved_by INT DEFAULT NULL COMMENT '异常审批人',
  ADD COLUMN approved_at DATETIME DEFAULT NULL COMMENT '审批时间';

-- 4. 工作日志模板表
CREATE TABLE IF NOT EXISTS work_log_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '模板名称',
  creator_id INT NOT NULL COMMENT '创建人',
  fields JSON NOT NULL COMMENT '[{name:"今日进展",type:"textarea",required:true}]',
  is_default TINYINT(1) DEFAULT 0 COMMENT '是否默认模板',
  status ENUM('active','archived') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 插入默认模板
INSERT IGNORE INTO work_log_templates (id, name, creator_id, fields, is_default) VALUES
(1, '标准日报模板', 1, JSON_ARRAY(
  JSON_OBJECT('name', '今日完成', 'type', 'textarea', 'required', true),
  JSON_OBJECT('name', '明日计划', 'type', 'textarea', 'required', true),
  JSON_OBJECT('name', '问题反馈', 'type', 'textarea', 'required', false)
), 1);

-- 5. 增强工作日志表
ALTER TABLE work_logs
  ADD COLUMN template_id INT DEFAULT NULL COMMENT '使用的模板ID',
  ADD COLUMN content JSON DEFAULT NULL COMMENT '日志内容JSON',
  ADD COLUMN recipients JSON DEFAULT NULL COMMENT '发送对象[user_id]',
  ADD COLUMN attachments JSON DEFAULT NULL COMMENT '附件[{name,url}]',
  ADD COLUMN status ENUM('draft','submitted') DEFAULT 'submitted';

-- 6. 工作日志互动表（已阅/评论/点赞）
CREATE TABLE IF NOT EXISTS work_log_interactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  log_id INT NOT NULL COMMENT '日志ID',
  user_id INT NOT NULL COMMENT '操作人',
  type ENUM('read','comment','like') NOT NULL COMMENT '互动类型',
  content TEXT DEFAULT NULL COMMENT '评论内容',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (log_id) REFERENCES work_logs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_log_user_type (log_id, user_id, type)
);

-- 7. 审批类型配置表
CREATE TABLE IF NOT EXISTS approval_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE COMMENT 'vehicle/seal/advance/expense/leave/hire/resign/transfer',
  name VARCHAR(100) NOT NULL COMMENT '类型名称',
  icon VARCHAR(50) DEFAULT NULL COMMENT '图标',
  form_fields JSON NOT NULL COMMENT '表单字段定义',
  default_flow JSON DEFAULT NULL COMMENT '默认审批流[{level,role,approver}]',
  status ENUM('active','disabled') DEFAULT 'active',
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 插入8种默认审批类型
INSERT IGNORE INTO approval_types (code, name, icon, form_fields, default_flow, sort_order) VALUES
('vehicle', '用车申请', 'directions_car', JSON_ARRAY(
  JSON_OBJECT('name', 'destination', 'label', '目的地', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'start_time', 'label', '开始时间', 'type', 'datetime', 'required', true),
  JSON_OBJECT('name', 'end_time', 'label', '结束时间', 'type', 'datetime', 'required', true),
  JSON_OBJECT('name', 'reason', 'label', '用车事由', 'type', 'textarea', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'manager')), 1),

('seal', '用印申请', 'verified', JSON_ARRAY(
  JSON_OBJECT('name', 'document_type', 'label', '文件类型', 'type', 'select', 'options', JSON_ARRAY('合同', '证明', '其他'), 'required', true),
  JSON_OBJECT('name', 'copies', 'label', '份数', 'type', 'number', 'required', true),
  JSON_OBJECT('name', 'reason', 'label', '用印事由', 'type', 'textarea', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'manager'), JSON_OBJECT('level', 2, 'role', 'director')), 2),

('advance', '预支申请', 'account_balance_wallet', JSON_ARRAY(
  JSON_OBJECT('name', 'amount', 'label', '预支金额', 'type', 'number', 'required', true),
  JSON_OBJECT('name', 'reason', 'label', '预支事由', 'type', 'textarea', 'required', true),
  JSON_OBJECT('name', 'repay_date', 'label', '预计归还日期', 'type', 'date', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'manager'), JSON_OBJECT('level', 2, 'role', 'finance')), 3),

('expense', '报销申请', 'receipt_long', JSON_ARRAY(
  JSON_OBJECT('name', 'amount', 'label', '报销金额', 'type', 'number', 'required', true),
  JSON_OBJECT('name', 'category', 'label', '费用类别', 'type', 'select', 'options', JSON_ARRAY('差旅', '餐饮', '办公', '其他'), 'required', true),
  JSON_OBJECT('name', 'description', 'label', '费用说明', 'type', 'textarea', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'manager'), JSON_OBJECT('level', 2, 'role', 'finance')), 4),

('leave', '请假申请', 'event_busy', JSON_ARRAY(
  JSON_OBJECT('name', 'leave_type', 'label', '请假类型', 'type', 'select', 'options', JSON_ARRAY('事假', '病假', '年假', '调休'), 'required', true),
  JSON_OBJECT('name', 'start_date', 'label', '开始日期', 'type', 'date', 'required', true),
  JSON_OBJECT('name', 'end_date', 'label', '结束日期', 'type', 'date', 'required', true),
  JSON_OBJECT('name', 'reason', 'label', '请假事由', 'type', 'textarea', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'manager')), 5),

('hire', '入职申请', 'person_add', JSON_ARRAY(
  JSON_OBJECT('name', 'candidate_name', 'label', '候选人姓名', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'position', 'label', '应聘职位', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'department', 'label', '入职部门', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'salary', 'label', '薪资', 'type', 'number', 'required', true),
  JSON_OBJECT('name', 'start_date', 'label', '入职日期', 'type', 'date', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'hr'), JSON_OBJECT('level', 2, 'role', 'director')), 6),

('resign', '离职申请', 'person_remove', JSON_ARRAY(
  JSON_OBJECT('name', 'resign_date', 'label', '离职日期', 'type', 'date', 'required', true),
  JSON_OBJECT('name', 'reason', 'label', '离职原因', 'type', 'textarea', 'required', true),
  JSON_OBJECT('name', 'handover', 'label', '工作交接情况', 'type', 'textarea', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'manager'), JSON_OBJECT('level', 2, 'role', 'hr')), 7),

('transfer', '调岗申请', 'swap_horiz', JSON_ARRAY(
  JSON_OBJECT('name', 'from_department', 'label', '原部门', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'to_department', 'label', '目标部门', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'from_position', 'label', '原职位', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'to_position', 'label', '目标职位', 'type', 'text', 'required', true),
  JSON_OBJECT('name', 'reason', 'label', '调岗原因', 'type', 'textarea', 'required', true),
  JSON_OBJECT('name', 'effective_date', 'label', '生效日期', 'type', 'date', 'required', true)
), JSON_ARRAY(JSON_OBJECT('level', 1, 'role', 'manager'), JSON_OBJECT('level', 2, 'role', 'hr')), 8);

-- 8. 增强审批表（关联员工和二维码）
ALTER TABLE approvals
  ADD COLUMN type_code VARCHAR(50) DEFAULT NULL COMMENT '审批类型代码',
  ADD COLUMN applicant_id INT DEFAULT NULL COMMENT '申请人user_id',
  ADD COLUMN form_data JSON DEFAULT NULL COMMENT '表单数据',
  ADD COLUMN qrcode_id INT DEFAULT NULL COMMENT '关联二维码ID',
  ADD COLUMN attachments JSON DEFAULT NULL COMMENT '附件列表';

-- 9. 增强审批步骤表
ALTER TABLE approval_steps
  ADD COLUMN approver_id INT DEFAULT NULL COMMENT '审批人user_id',
  ADD COLUMN level INT DEFAULT 1 COMMENT '审批层级';
