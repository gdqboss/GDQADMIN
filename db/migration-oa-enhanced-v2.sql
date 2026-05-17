-- ═══════════════════════════════════════════════════════════════════════════════
-- Migration: OA System Enhanced V2 - Production Ready
-- Date: 2026-03-03
-- Description: Enterprise-grade OA with shift management, overtime, leave balance,
--              workflow engine, announcements, notifications, and monthly reports
-- ═══════════════════════════════════════════════════════════════════════════════

-- 1. 班次管理表 (Shift Management)
CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL COMMENT '班次名称：早班/中班/晚班/弹性班',
  code VARCHAR(20) NOT NULL UNIQUE COMMENT '班次代码：MORNING/AFTERNOON/NIGHT/FLEX',
  start_time TIME NOT NULL COMMENT '上班时间',
  end_time TIME NOT NULL COMMENT '下班时间',
  late_threshold INT DEFAULT 15 COMMENT '迟到阈值(分钟)',
  early_threshold INT DEFAULT 30 COMMENT '早退阈值(分钟)',
  work_hours DECIMAL(4,2) DEFAULT 8.0 COMMENT '标准工时',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 插入默认班次
INSERT IGNORE INTO shifts (name, code, start_time, end_time, late_threshold, early_threshold, work_hours) VALUES
  ('早班', 'MORNING', '08:00:00', '17:00:00', 15, 30, 8.0),
  ('中班', 'AFTERNOON', '13:00:00', '22:00:00', 15, 30, 8.0),
  ('晚班', 'NIGHT', '22:00:00', '07:00:00', 15, 30, 8.0),
  ('弹性班', 'FLEX', '09:00:00', '18:00:00', 30, 60, 8.0);

-- 2. 排班表 (Shift Schedules)
CREATE TABLE IF NOT EXISTS shift_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '员工ID',
  shift_id INT NOT NULL COMMENT '班次ID',
  schedule_date DATE NOT NULL COMMENT '排班日期',
  is_rest_day TINYINT(1) DEFAULT 0 COMMENT '是否休息日',
  notes VARCHAR(200) DEFAULT NULL COMMENT '备注',
  created_by INT DEFAULT NULL COMMENT '创建人',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uk_user_date (user_id, schedule_date)
);

-- 3. 加班记录表 (Overtime Records)
CREATE TABLE IF NOT EXISTS overtime_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '员工ID',
  overtime_date DATE NOT NULL COMMENT '加班日期',
  start_time TIME NOT NULL COMMENT '开始时间',
  end_time TIME NOT NULL COMMENT '结束时间',
  hours DECIMAL(4,2) NOT NULL COMMENT '加班时长(小时)',
  reason TEXT NOT NULL COMMENT '加班原因',
  type ENUM('weekday','weekend','holiday') DEFAULT 'weekday' COMMENT '加班类型',
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  approver_id INT DEFAULT NULL COMMENT '审批人',
  approved_at DATETIME DEFAULT NULL,
  approval_comment VARCHAR(500) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. 假期余额表 (Leave Balances)
CREATE TABLE IF NOT EXISTS leave_balances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '员工ID',
  year INT NOT NULL COMMENT '年度',
  leave_type ENUM('annual','sick','personal','compensatory','marriage','maternity','paternity','bereavement') NOT NULL COMMENT '假期类型',
  total_days DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '总天数',
  used_days DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '已用天数',
  remaining_days DECIMAL(5,2) GENERATED ALWAYS AS (total_days - used_days) STORED COMMENT '剩余天数',
  expires_at DATE DEFAULT NULL COMMENT '过期日期',
  notes VARCHAR(200) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_user_year_type (user_id, year, leave_type)
);

-- 5. 流程定义表 (Workflow Definitions)
CREATE TABLE IF NOT EXISTS workflow_definitions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '流程名称',
  code VARCHAR(50) NOT NULL UNIQUE COMMENT '流程代码',
  category VARCHAR(50) DEFAULT NULL COMMENT '流程分类',
  description TEXT DEFAULT NULL COMMENT '流程描述',
  form_schema JSON NOT NULL COMMENT '表单定义',
  flow_config JSON NOT NULL COMMENT '流程配置：节点/条件/路由',
  icon VARCHAR(50) DEFAULT NULL,
  version INT DEFAULT 1 COMMENT '版本号',
  status ENUM('draft','active','archived') DEFAULT 'draft',
  creator_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 6. 流程实例表 (Workflow Instances)
CREATE TABLE IF NOT EXISTS workflow_instances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  workflow_id INT NOT NULL COMMENT '流程定义ID',
  instance_code VARCHAR(50) NOT NULL UNIQUE COMMENT '实例编号',
  title VARCHAR(200) NOT NULL COMMENT '流程标题',
  applicant_id INT NOT NULL COMMENT '申请人',
  form_data JSON NOT NULL COMMENT '表单数据',
  current_node VARCHAR(50) DEFAULT NULL COMMENT '当前节点',
  status ENUM('pending','approved','rejected','withdrawn','cancelled') DEFAULT 'pending',
  priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  attachments JSON DEFAULT NULL COMMENT '附件列表',
  FOREIGN KEY (workflow_id) REFERENCES workflow_definitions(id) ON DELETE RESTRICT,
  FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_applicant (applicant_id),
  INDEX idx_status (status),
  INDEX idx_started (started_at)
);

-- 7. 流程任务表 (Workflow Tasks)
CREATE TABLE IF NOT EXISTS workflow_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  instance_id INT NOT NULL COMMENT '流程实例ID',
  node_id VARCHAR(50) NOT NULL COMMENT '节点ID',
  node_name VARCHAR(100) NOT NULL COMMENT '节点名称',
  task_type ENUM('approve','review','notify','countersign') DEFAULT 'approve' COMMENT '任务类型',
  assignee_id INT NOT NULL COMMENT '处理人',
  status ENUM('pending','approved','rejected','transferred','cancelled') DEFAULT 'pending',
  action VARCHAR(50) DEFAULT NULL COMMENT '操作：approve/reject/transfer',
  comment TEXT DEFAULT NULL COMMENT '处理意见',
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  due_date DATETIME DEFAULT NULL COMMENT '截止时间',
  is_overdue TINYINT(1) GENERATED ALWAYS AS (due_date IS NOT NULL AND completed_at IS NULL AND NOW() > due_date) STORED,
  FOREIGN KEY (instance_id) REFERENCES workflow_instances(id) ON DELETE CASCADE,
  FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_assignee_status (assignee_id, status),
  INDEX idx_instance (instance_id)
);

-- 8. 公告表 (Announcements)
CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL COMMENT '公告标题',
  content TEXT NOT NULL COMMENT '公告内容',
  category ENUM('company','department','urgent','notice','policy') DEFAULT 'notice',
  priority ENUM('low','normal','high') DEFAULT 'normal',
  is_pinned TINYINT(1) DEFAULT 0 COMMENT '是否置顶',
  publisher_id INT NOT NULL COMMENT '发布人',
  target_audience JSON DEFAULT NULL COMMENT '目标受众：部门/角色/用户ID列表',
  attachments JSON DEFAULT NULL,
  published_at DATETIME DEFAULT NULL COMMENT '发布时间',
  expires_at DATETIME DEFAULT NULL COMMENT '过期时间',
  status ENUM('draft','published','expired','withdrawn') DEFAULT 'draft',
  view_count INT DEFAULT 0 COMMENT '浏览次数',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (publisher_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status_published (status, published_at),
  INDEX idx_pinned (is_pinned)
);

-- 9. 公告阅读记录表 (Announcement Reads)
CREATE TABLE IF NOT EXISTS announcement_reads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  user_id INT NOT NULL,
  read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_announcement_user (announcement_id, user_id)
);

-- 10. 通知表 (Notifications)
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '接收人',
  type VARCHAR(50) NOT NULL COMMENT '通知类型：approval/announcement/attendance/system',
  title VARCHAR(200) NOT NULL COMMENT '通知标题',
  content TEXT DEFAULT NULL COMMENT '通知内容',
  link VARCHAR(500) DEFAULT NULL COMMENT '跳转链接',
  related_id INT DEFAULT NULL COMMENT '关联ID',
  related_type VARCHAR(50) DEFAULT NULL COMMENT '关联类型',
  is_read TINYINT(1) DEFAULT 0 COMMENT '是否已读',
  read_at DATETIME DEFAULT NULL,
  priority ENUM('low','normal','high') DEFAULT 'normal',
  channels JSON DEFAULT NULL COMMENT '推送渠道：["web","email","sms","wecom"]',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read),
  INDEX idx_created (created_at)
);

-- 11. 考勤月报表 (Attendance Monthly Reports)
CREATE TABLE IF NOT EXISTS attendance_monthly_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  year INT NOT NULL,
  month INT NOT NULL,
  work_days INT DEFAULT 0 COMMENT '应出勤天数',
  actual_days INT DEFAULT 0 COMMENT '实际出勤天数',
  late_count INT DEFAULT 0 COMMENT '迟到次数',
  early_count INT DEFAULT 0 COMMENT '早退次数',
  absent_count INT DEFAULT 0 COMMENT '缺勤次数',
  overtime_hours DECIMAL(6,2) DEFAULT 0 COMMENT '加班时长',
  leave_days DECIMAL(5,2) DEFAULT 0 COMMENT '请假天数',
  report_data JSON DEFAULT NULL COMMENT '详细数据',
  generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_user_year_month (user_id, year, month)
);

-- ═══════════════════════════════════════════════════════════════════════════════
-- INDEXES FOR PERFORMANCE
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE INDEX idx_shift_schedules_date ON shift_schedules(schedule_date);
CREATE INDEX idx_overtime_date ON overtime_records(overtime_date);
CREATE INDEX idx_overtime_status ON overtime_records(status);
CREATE INDEX idx_leave_balances_user_year ON leave_balances(user_id, year);

-- ═══════════════════════════════════════════════════════════════════════════════
-- SAMPLE DATA FOR TESTING
-- ═══════════════════════════════════════════════════════════════════════════════

-- Insert sample workflow definition
INSERT IGNORE INTO workflow_definitions (id, name, code, category, description, form_schema, flow_config, icon, status, creator_id) VALUES
(1, '请假审批流程', 'LEAVE_APPROVAL', 'HR', '员工请假申请审批流程',
JSON_OBJECT(
  'fields', JSON_ARRAY(
    JSON_OBJECT('name', 'leave_type', 'label', '请假类型', 'type', 'select', 'options', JSON_ARRAY('年假', '病假', '事假', '调休'), 'required', true),
    JSON_OBJECT('name', 'start_date', 'label', '开始日期', 'type', 'date', 'required', true),
    JSON_OBJECT('name', 'end_date', 'label', '结束日期', 'type', 'date', 'required', true),
    JSON_OBJECT('name', 'days', 'label', '请假天数', 'type', 'number', 'required', true),
    JSON_OBJECT('name', 'reason', 'label', '请假事由', 'type', 'textarea', 'required', true)
  )
),
JSON_OBJECT(
  'nodes', JSON_ARRAY(
    JSON_OBJECT('id', 'start', 'name', '开始', 'type', 'start'),
    JSON_OBJECT('id', 'manager_approve', 'name', '部门经理审批', 'type', 'approve', 'assignee_rule', 'direct_manager'),
    JSON_OBJECT('id', 'hr_approve', 'name', 'HR审批', 'type', 'approve', 'assignee_rule', 'role:hr', 'condition', 'days > 3'),
    JSON_OBJECT('id', 'end', 'name', '结束', 'type', 'end')
  ),
  'edges', JSON_ARRAY(
    JSON_OBJECT('from', 'start', 'to', 'manager_approve'),
    JSON_OBJECT('from', 'manager_approve', 'to', 'hr_approve', 'condition', 'days > 3'),
    JSON_OBJECT('from', 'manager_approve', 'to', 'end', 'condition', 'days <= 3'),
    JSON_OBJECT('from', 'hr_approve', 'to', 'end')
  )
),
'event_busy', 'active', 1);

-- Insert sample leave balances for user 1
INSERT IGNORE INTO leave_balances (user_id, year, leave_type, total_days, used_days) VALUES
  (1, 2026, 'annual', 10.0, 2.0),
  (1, 2026, 'sick', 5.0, 0.0),
  (1, 2026, 'personal', 3.0, 1.0);

-- Insert sample announcement
INSERT IGNORE INTO announcements (id, title, content, category, priority, is_pinned, publisher_id, status, published_at) VALUES
(1, '系统升级通知', '本周六晚上22:00-24:00进行系统升级维护，期间系统将暂停服务，请各位同事提前做好工作安排。', 'urgent', 'high', 1, 1, 'published', NOW());

-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRATION COMPLETE
-- ═══════════════════════════════════════════════════════════════════════════════
