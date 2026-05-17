-- 任务管理系统数据库迁移
-- 创建时间: 2026-03-04

USE gdq;

-- 1. 职位权责表
CREATE TABLE IF NOT EXISTS role_responsibilities (
  id INT PRIMARY KEY AUTO_INCREMENT,
  role VARCHAR(50) NOT NULL COMMENT '角色名称',
  title VARCHAR(200) NOT NULL COMMENT '权责标题',
  description TEXT COMMENT '权责描述',
  responsibilities JSON COMMENT '具体职责列表',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='职位权责表';

-- 2. 任务表
CREATE TABLE IF NOT EXISTS tasks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL COMMENT '任务标题',
  content TEXT COMMENT '任务内容',
  assigned_to INT NOT NULL COMMENT '被指派人ID',
  assigned_by INT NOT NULL COMMENT '指派人ID',
  scheduled_date DATE COMMENT '安排时间',
  due_date DATE COMMENT '截止时间',
  priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' COMMENT '优先级',
  status ENUM('pending', 'in_progress', 'submitted', 'completed', 'rejected') DEFAULT 'pending' COMMENT '状态',
  submitted_at TIMESTAMP NULL COMMENT '提交时间',
  completed_at TIMESTAMP NULL COMMENT '完成时间',
  completion_note TEXT COMMENT '完成说明',
  review_note TEXT COMMENT '审核意见',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_assigned_to (assigned_to),
  INDEX idx_assigned_by (assigned_by),
  INDEX idx_status (status),
  INDEX idx_due_date (due_date),
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务表';

-- 3. 任务附件表
CREATE TABLE IF NOT EXISTS task_attachments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  task_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size INT COMMENT '文件大小(字节)',
  uploaded_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_task_id (task_id),
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务附件表';

-- 插入默认角色权责
INSERT INTO role_responsibilities (role, title, description, responsibilities) VALUES
('admin', '系统管理员', '负责系统整体管理和维护', '["管理所有用户和权限", "配置系统设置", "查看所有数据和报表", "管理角色权责", "审批员工注册"]'),
('manager', '经理', '负责团队管理和业务运营', '["管理团队成员", "分配和审核任务", "查看团队报表", "审批业务流程", "指导下属工作"]'),
('operator', '操作员', '负责日常业务操作', '["完成上级分配的任务", "记录工作日志", "提交工作报告", "参与团队协作"]')
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  description = VALUES(description),
  responsibilities = VALUES(responsibilities);
