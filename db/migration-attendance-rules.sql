-- 出勤规则表
CREATE TABLE IF NOT EXISTS attendance_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '规则名称',
  weekdays JSON COMMENT '工作日 [1,2,3,4,5] (1=周一)',
  start_time VARCHAR(5) DEFAULT '09:00' COMMENT '上班时间',
  end_time VARCHAR(5) DEFAULT '18:00' COMMENT '下班时间',
  status ENUM('active','inactive') DEFAULT 'active',
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出勤规则成员表
CREATE TABLE IF NOT EXISTS attendance_rule_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rule_id INT NOT NULL,
  user_id INT NOT NULL,
  user_name VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rule_id) REFERENCES attendance_rules(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_rule_user (rule_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
