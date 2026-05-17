-- ═══════════════════════════════════════════════════════════════════════════════
-- OA SHIFT MANAGEMENT & WORKFLOW ENGINE MIGRATION
-- Created: 2026-03-03
-- ═══════════════════════════════════════════════════════════════════════════════

-- 班次定义表 (Shift Definitions)
CREATE TABLE IF NOT EXISTS shifts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL COMMENT '班次名称: 早班/中班/晚班',
  code VARCHAR(20) NOT NULL UNIQUE COMMENT '班次代码',
  start_time TIME NOT NULL COMMENT '开始时间',
  end_time TIME NOT NULL COMMENT '结束时间',
  duration DECIMAL(4,2) NOT NULL COMMENT '工作时长(小时)',
  break_duration DECIMAL(4,2) DEFAULT 0 COMMENT '休息时长(小时)',
  color VARCHAR(20) DEFAULT '#3B82F6' COMMENT '日历显示颜色',
  description TEXT COMMENT '班次说明',
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='班次定义表';

-- 排班表 (Shift Schedules)
CREATE TABLE IF NOT EXISTS shift_schedules (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL COMMENT '员工ID',
  shift_id INT NOT NULL COMMENT '班次ID',
  schedule_date DATE NOT NULL COMMENT '排班日期',
  department VARCHAR(100) COMMENT '部门',
  status ENUM('scheduled', 'confirmed', 'swapped', 'cancelled') DEFAULT 'scheduled',
  notes TEXT COMMENT '备注',
  created_by INT COMMENT '创建人',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uk_user_date (user_id, schedule_date),
  INDEX idx_date (schedule_date),
  INDEX idx_department (department),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排班表';

-- 班次调换记录 (Shift Swap Records)
CREATE TABLE IF NOT EXISTS shift_swaps (
  id INT PRIMARY KEY AUTO_INCREMENT,
  schedule_id_a INT NOT NULL COMMENT '排班A',
  schedule_id_b INT NOT NULL COMMENT '排班B',
  user_id_a INT NOT NULL COMMENT '员工A',
  user_id_b INT NOT NULL COMMENT '员工B',
  reason TEXT COMMENT '调换原因',
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  approved_by INT COMMENT '批准人',
  approved_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (schedule_id_a) REFERENCES shift_schedules(id) ON DELETE CASCADE,
  FOREIGN KEY (schedule_id_b) REFERENCES shift_schedules(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id_a) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id_b) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='班次调换记录';

-- 考勤班次关联 (扩展现有attendance表)
ALTER TABLE attendance
  ADD COLUMN shift_id INT COMMENT '班次ID' AFTER date,
  ADD COLUMN scheduled_in TIME COMMENT '计划上班时间' AFTER shift_id,
  ADD COLUMN scheduled_out TIME COMMENT '计划下班时间' AFTER scheduled_in,
  ADD COLUMN overtime_hours DECIMAL(4,2) DEFAULT 0 COMMENT '加班时长(小时)' AFTER clock_out,
  ADD COLUMN late_minutes INT DEFAULT 0 COMMENT '迟到分钟数' AFTER overtime_hours,
  ADD COLUMN early_minutes INT DEFAULT 0 COMMENT '早退分钟数' AFTER late_minutes,
  ADD FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL;

-- ═══════════════════════════════════════════════════════════════════════════════
-- WORKFLOW ENGINE TABLES
-- ═══════════════════════════════════════════════════════════════════════════════

-- 工作流定义表 (Workflow Definitions)
CREATE TABLE IF NOT EXISTS workflow_definitions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COMMENT '工作流名称',
  code VARCHAR(50) NOT NULL UNIQUE COMMENT '工作流代码',
  category VARCHAR(50) COMMENT '分类: approval/notification/automation',
  description TEXT COMMENT '描述',
  flow_config JSON NOT NULL COMMENT '流程配置(节点/连线/条件)',
  version INT DEFAULT 1 COMMENT '版本号',
  is_active BOOLEAN DEFAULT TRUE COMMENT '是否启用',
  is_template BOOLEAN DEFAULT FALSE COMMENT '是否为模板',
  created_by INT COMMENT '创建人',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_code (code),
  INDEX idx_category (category),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工作流定义表';

-- 工作流实例表 (Workflow Instances)
CREATE TABLE IF NOT EXISTS workflow_instances (
  id INT PRIMARY KEY AUTO_INCREMENT,
  workflow_id INT NOT NULL COMMENT '工作流定义ID',
  workflow_code VARCHAR(50) NOT NULL COMMENT '工作流代码',
  workflow_version INT DEFAULT 1 COMMENT '工作流版本',
  title VARCHAR(200) NOT NULL COMMENT '实例标题',
  initiator_id INT NOT NULL COMMENT '发起人',
  business_key VARCHAR(100) COMMENT '业务关联键',
  business_type VARCHAR(50) COMMENT '业务类型',
  form_data JSON COMMENT '表单数据',
  current_node VARCHAR(50) COMMENT '当前节点',
  status ENUM('running', 'completed', 'terminated', 'suspended') DEFAULT 'running',
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (workflow_id) REFERENCES workflow_definitions(id) ON DELETE RESTRICT,
  FOREIGN KEY (initiator_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_workflow (workflow_id),
  INDEX idx_initiator (initiator_id),
  INDEX idx_status (status),
  INDEX idx_business (business_type, business_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工作流实例表';

-- 工作流任务表 (Workflow Tasks)
CREATE TABLE IF NOT EXISTS workflow_tasks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  instance_id INT NOT NULL COMMENT '工作流实例ID',
  node_id VARCHAR(50) NOT NULL COMMENT '节点ID',
  node_name VARCHAR(100) NOT NULL COMMENT '节点名称',
  node_type ENUM('start', 'approval', 'notification', 'auto_action', 'condition', 'end') NOT NULL,
  assignee_id INT COMMENT '处理人',
  assignee_type VARCHAR(50) COMMENT '处理人类型: user/role/department',
  status ENUM('pending', 'in_progress', 'completed', 'skipped', 'timeout') DEFAULT 'pending',
  action VARCHAR(50) COMMENT '执行动作: approve/reject/notify/execute',
  comment TEXT COMMENT '处理意见',
  form_data JSON COMMENT '任务表单数据',
  timeout_at TIMESTAMP NULL COMMENT '超时时间',
  escalation_to INT COMMENT '超时升级至',
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (instance_id) REFERENCES workflow_instances(id) ON DELETE CASCADE,
  FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (escalation_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_instance (instance_id),
  INDEX idx_assignee (assignee_id),
  INDEX idx_status (status),
  INDEX idx_timeout (timeout_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工作流任务表';

-- 工作流日志表 (Workflow Logs)
CREATE TABLE IF NOT EXISTS workflow_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  instance_id INT NOT NULL COMMENT '工作流实例ID',
  task_id INT COMMENT '任务ID',
  node_id VARCHAR(50) COMMENT '节点ID',
  action VARCHAR(50) NOT NULL COMMENT '动作',
  operator_id INT COMMENT '操作人',
  message TEXT COMMENT '日志消息',
  details JSON COMMENT '详细信息',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (instance_id) REFERENCES workflow_instances(id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES workflow_tasks(id) ON DELETE SET NULL,
  FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_instance (instance_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工作流日志表';

-- ═══════════════════════════════════════════════════════════════════════════════
-- INITIAL DATA
-- ═══════════════════════════════════════════════════════════════════════════════

-- 插入默认班次
INSERT INTO shifts (name, code, start_time, end_time, duration, break_duration, color, description) VALUES
('早班', 'MORNING', '08:00:00', '16:00:00', 8.0, 1.0, '#3B82F6', '早班 8:00-16:00'),
('中班', 'AFTERNOON', '12:00:00', '20:00:00', 8.0, 1.0, '#10B981', '中班 12:00-20:00'),
('晚班', 'NIGHT', '16:00:00', '00:00:00', 8.0, 1.0, '#8B5CF6', '晚班 16:00-24:00'),
('全天班', 'FULL_DAY', '09:00:00', '18:00:00', 9.0, 1.0, '#F59E0B', '全天班 9:00-18:00');

-- 插入工作流模板
INSERT INTO workflow_definitions (name, code, category, description, flow_config, is_template, created_by) VALUES
('请假审批流程', 'LEAVE_APPROVAL', 'approval', '员工请假审批流程',
'{"nodes":[{"id":"start","type":"start","name":"开始"},{"id":"manager","type":"approval","name":"部门经理审批","assignee_type":"role","assignee":"manager"},{"id":"hr","type":"approval","name":"HR审批","assignee_type":"role","assignee":"hr"},{"id":"end","type":"end","name":"结束"}],"edges":[{"from":"start","to":"manager"},{"from":"manager","to":"hr","condition":"approved"},{"from":"hr","to":"end","condition":"approved"}]}',
TRUE, 1),
('加班申请流程', 'OVERTIME_APPROVAL', 'approval', '员工加班申请审批流程',
'{"nodes":[{"id":"start","type":"start","name":"开始"},{"id":"manager","type":"approval","name":"部门经理审批","assignee_type":"role","assignee":"manager"},{"id":"end","type":"end","name":"结束"}],"edges":[{"from":"start","to":"manager"},{"from":"manager","to":"end","condition":"approved"}]}',
TRUE, 1);
