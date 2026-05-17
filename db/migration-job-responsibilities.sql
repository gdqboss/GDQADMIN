-- 职位权责表
CREATE TABLE IF NOT EXISTS job_responsibilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_level_id INT NOT NULL COMMENT '职级ID',
  department_id INT DEFAULT NULL COMMENT '部门ID（可选，特定部门的职责）',
  title VARCHAR(100) NOT NULL COMMENT '权责标题',
  description TEXT COMMENT '权责描述',
  category ENUM('duty', 'authority', 'kpi') DEFAULT 'duty' COMMENT '类别：职责/权限/考核指标',
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (job_level_id) REFERENCES job_levels(id) ON DELETE CASCADE,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  INDEX idx_job_level (job_level_id),
  INDEX idx_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='职位权责表';
