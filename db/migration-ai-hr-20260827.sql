-- AI HR 招聘模块数据库迁移
-- 日期: 2026-08-27
-- 作者: 江小鱼
-- 范围: SGP 开发环境（profile 1）

-- 1. ai_hr_job_presets — 岗位预设招聘条件
CREATE TABLE IF NOT EXISTS ai_hr_job_presets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  preset_questions JSON NOT NULL COMMENT '硬性条件问题清单 [{key, question, requirement, weight, required}]',
  soft_traits JSON COMMENT '软素质维度 [{key, label, weight}]',
  max_questions INT DEFAULT 12 COMMENT 'AI 最多主动问的轮数',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI HR 岗位预设条件';

-- 2. ai_hr_conversations — 对话 session
CREATE TABLE IF NOT EXISTS ai_hr_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) NOT NULL UNIQUE COMMENT '前端 UUID',
  job_id INT NOT NULL,
  candidate_name VARCHAR(50) DEFAULT NULL,
  candidate_phone VARCHAR(20) DEFAULT NULL,
  messages JSON NOT NULL DEFAULT (JSON_ARRAY()) COMMENT '[{role, content, ts, preset_key?}]',
  preset_answers JSON DEFAULT NULL COMMENT '提取的预设条件答案 {key: value}',
  status ENUM('active','completed','abandoned') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_job_status (job_id, status),
  KEY idx_status_updated (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI HR 对话 session';

-- 3. ai_hr_evaluations — 最终评价
CREATE TABLE IF NOT EXISTS ai_hr_evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL UNIQUE,
  job_id INT NOT NULL,
  candidate_name VARCHAR(50) DEFAULT NULL,
  overall_score TINYINT DEFAULT NULL COMMENT '0-100 综合分',
  preset_results JSON DEFAULT NULL COMMENT '[{key, question, answer, passed, weight, requirement}]',
  soft_scores JSON DEFAULT NULL COMMENT '[{key, label, score, evidence, weight}]',
  red_flags JSON DEFAULT NULL COMMENT '红黄牌',
  highlights JSON DEFAULT NULL COMMENT '亮点',
  recommendation ENUM('strong','consider','reject') DEFAULT NULL,
  ai_summary TEXT DEFAULT NULL COMMENT 'AI 给 HR 的一句话总结',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_job (job_id),
  KEY idx_rec (recommendation),
  KEY idx_score (overall_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI HR 最终评价报告';

-- 4. RBAC 权限三件套
-- 表字段: name(权限key with colon) / label / category / description
INSERT INTO rbac_permissions (name, label, category, description) VALUES
  ('ai_hr:read', 'AI HR 查看', 'ai-hr', '查看 AI HR 报告和岗位配置'),
  ('ai_hr:write', 'AI HR 编辑', 'ai-hr', '创建/编辑 AI HR 岗位预设条件'),
  ('ai_hr:delete', 'AI HR 删除', 'ai-hr', '删除 AI HR 对话或评价报告')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 5. 注册模块到 profile 1（开发环境）
-- server_modules 表用 (server_profile_id, module_key) 复合主键，无 enabled 字段
INSERT IGNORE INTO server_modules (server_profile_id, module_key)
  VALUES (1, 'ai-hr');

-- 6. 权限授权：admin 用户在 rbac middleware 自动 bypass（见 middleware/rbac.js）
--    非 admin 角色的授权由 gdqadmin 后台的"角色权限"页配
--    不在这里硬编授权，避免给非预期角色赋权