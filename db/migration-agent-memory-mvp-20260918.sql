-- ╔═══════════════════════════════════════════════════════════════════════════╗
-- ║ agent-memory MVP — 全员 AI 数据中心 (2026-09-18 江小鱼立)               ║
-- ║                                                                          ║
-- ║ 触发: 波哥 2026-09-18 "通过出勤/任务/日志/员工个人 agent 交流,           ║
-- ║        把员工的优秀思维和经验累积到 AI 数据中心"                          ║
-- ║                                                                          ║
-- ║ 设计: 4 张表 + rbac + menu + server_module 注册                          ║
-- ║   - memory_events   事件流 (4 类数据源自动写入)                          ║
-- ║   - memory_profiles 员工画像 (AI 累计提炼)                                ║
-- ║   - memory_insights AI 提炼报告 (周/月报)                                 ║
-- ║   - memory_wisdom   知识财富 (可复用优秀思维)                             ║
-- ║                                                                          ║
-- ║ 数据来源 (4 个 hook):                                                     ║
-- ║   1. 出勤: routes/attendance.js → 优秀出勤模式                            ║
-- ║   2. 任务: routes/tasks.js + routes/oa-flow.js → 决策模式                  ║
-- ║   3. 日志: routes/log-interactions.js + work_log_* → 工作日志              ║
-- ║   4. agent 交流: routes/ai-hr.js + workbuddy.js → 员工问答经验             ║
-- ║                                                                          ║
-- ║ 跟现有系统的关系:                                                         ║
-- ║   - 不动 attendance / tasks / work_logs / ai_hr_conversations 表         ║
-- ║   - 只在它们有 INSERT/UPDATE 时, 同时写 1 条到 memory_events              ║
-- ║   - 周期性 LLM job 读 memory_events → 更新 memory_profiles + insights     ║
-- ║   - /api/agent-memory/* 提供查询/分析接口                                 ║
-- ╚═══════════════════════════════════════════════════════════════════════════╝

SET NAMES utf8mb4;

-- ─── 1. memory_events (事件流) ────────────────────────────────────────────
DROP TABLE IF EXISTS memory_events;
CREATE TABLE memory_events (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  source ENUM('attendance','task','work_log','agent_chat','oa_flow','manual') NOT NULL,
  source_ref_id BIGINT UNSIGNED,
  event_type VARCHAR(64),
  event_data JSON,
  ai_tags JSON,
  ai_score DECIMAL(4,2),
  is_wisdom TINYINT(1) DEFAULT 0,
  occurred_at DATETIME NOT NULL,
  ingested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  analyzed_at DATETIME,
  INDEX idx_user_time (user_id, occurred_at),
  INDEX idx_source (source, source_ref_id),
  INDEX idx_wisdom (is_wisdom, ai_score),
  INDEX idx_analyzed (analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. memory_profiles (员工画像) ────────────────────────────────────────
DROP TABLE IF EXISTS memory_profiles;
CREATE TABLE memory_profiles (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  thinking_style VARCHAR(64),
  reliability_score DECIMAL(4,2),
  speed_score DECIMAL(4,2),
  creativity_score DECIMAL(4,2),
  collaboration_score DECIMAL(4,2),
  trait_summary TEXT,
  best_at TEXT,
  watch_out TEXT,
  total_events INT DEFAULT 0,
  wisdom_count INT DEFAULT 0,
  last_analyzed_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_thinking (thinking_style),
  INDEX idx_reliability (reliability_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. memory_insights (AI 提炼报告) ─────────────────────────────────────
DROP TABLE IF EXISTS memory_insights;
CREATE TABLE memory_insights (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED,
  scope ENUM('user','team','company') NOT NULL,
  scope_ref_id INT UNSIGNED,
  period ENUM('daily','weekly','monthly','quarterly') NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  title VARCHAR(255),
  summary TEXT,
  key_patterns JSON,
  recommendations JSON,
  risks JSON,
  ai_model VARCHAR(64),
  ai_tokens_used INT,
  generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  user_rating TINYINT,
  user_feedback TEXT,
  INDEX idx_user_period (user_id, period, period_end),
  INDEX idx_scope_period (scope, scope_ref_id, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. memory_wisdom (知识财富 — 可复用优秀思维) ─────────────────────────
DROP TABLE IF EXISTS memory_wisdom;
CREATE TABLE memory_wisdom (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source_event_id BIGINT UNSIGNED,
  source_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  situation TEXT,
  action TEXT,
  reasoning TEXT,
  category VARCHAR(64),
  tags JSON,
  applied_count INT DEFAULT 0,
  effectiveness_score DECIMAL(4,2),
  is_inheritance TINYINT(1) DEFAULT 0,
  inheritance_priority TINYINT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (source_user_id),
  INDEX idx_category (category),
  INDEX idx_inheritance (is_inheritance, inheritance_priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── rbac_permissions: 加 4 个 perm ────────────────────────────────────────
INSERT IGNORE INTO rbac_permissions (name, label, category, description, hidden) VALUES
  ('agent-memory:read',         '查看全员 AI 数据中心',     'ai', '老板 / 员工查看 AI 数据中心', 0),
  ('agent-memory:write',        '录入数据 / 修改 AI 提炼', 'ai', '手动录入事件或编辑 AI 提炼', 0),
  ('agent-memory:analyze',      '触发 AI 分析 / 生成洞察', 'ai', '触发 GLM 提炼员工画像', 0),
  ('agent-memory:inheritance',  '管理传承知识 (老板专属)', 'ai', '老板专属: 标记 wisdom 为传承用', 0);

-- ─── menu_modules: 加 1 个菜单 ─────────────────────────────────────────────
INSERT IGNORE INTO menu_modules (`key`, label_zh, label_en, icon, route, category, sort_order, required, children_json) VALUES
  ('agent-memory', 'AI 数据中心', 'AI Memory Hub', 'brain', '/agent-memory', 'general', 90, 1,
   JSON_ARRAY(
      JSON_OBJECT('key','agent-memory:overview',  'label','总览',         'to','/agent-memory'),
      JSON_OBJECT('key','agent-memory:profiles',  'label','员工画像',     'to','/agent-memory/profiles'),
      JSON_OBJECT('key','agent-memory:wisdom',    'label','知识财富',     'to','/agent-memory/wisdom'),
      JSON_OBJECT('key','agent-memory:insights',  'label','AI 洞察',      'to','/agent-memory/insights'),
      JSON_OBJECT('key','agent-memory:inheritance','label','传承 (老板)', 'to','/agent-memory/inheritance')
   ));

-- ─── server_modules: SGP 默认启用 (profile_id=1) ────────────────────────────
INSERT IGNORE INTO server_modules (server_profile_id, module_key) VALUES (1, 'agent-memory');

-- 立 (2026-09-18)
-- 江小鱼 agent-memory MVP, 全员 AI 数据中心最小可用版本
-- 接入 4 类现有数据源: 出勤/任务/日志/agent 交流
-- 下一步: 写 routes/agent-memory.js (查询/分析接口) + 4 个 hook