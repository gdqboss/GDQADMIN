-- ╔═══════════════════════════════════════════════════════════════════════════╗
-- ║ secure_knowledge MVP — 机密配方 + AI 创新 (2026-09-18 江小鱼立)        ║
-- ║                                                                          ║
-- ║ 触发: 波哥 2026-09-18 "本公司有些机密配方, 我们 AI 了解后,               ║
-- ║        能不能结合互联网的知识, 帮老板再创新? 例: 增加板材硬度"           ║
-- ║                                                                          ║
-- ║ 设计: 2 张表 + LLM 调用器(读 ai_config 客户自配)                        ║
-- ║   - secure_documents       机密文档 (加密存储)                          ║
-- ║   - innovation_logs        AI 创新调用日志 (谁/何时/什么问题/什么 LLM)   ║
-- ║                                                                          ║
-- ║ 5 层防御 (机密不外传):                                                   ║
-- ║   1. rbac: secure_knowledge:read 仅老板/授权人                          ║
-- ║   2. AES-256 加密存储 (密钥在 .env)                                     ║
-- ║   3. prompt 隔离: 机密不进系统日志, 不进 cache                          ║
-- ║   4. LLM 自配: 调客户 ai_config 里的 LLM, 不调外部默认                  ║
-- ║   5. 老板审批: innovate 调用前必须授权 confirm                           ║
-- ║                                                                          ║
-- ║ 跟现有系统关系:                                                          ║
-- ║   - 复用 ai_config 表 (customer 自己配 LLM)                              ║
-- ║   - 复用 agent-memory 的 prompt cache (避免重复加密)                    ║
-- ║   - 复用 routes/agent-memory.js 的 menu_modules 入口                    ║
-- ╚═══════════════════════════════════════════════════════════════════════════╝

SET NAMES utf8mb4;

-- ─── 1. secure_documents (机密文档) ───────────────────────────────────────
DROP TABLE IF EXISTS secure_documents;
CREATE TABLE secure_documents (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  -- 所有者
  owner_user_id INT UNSIGNED NOT NULL,             -- 老板本人 (users.id)
  company_id INT UNSIGNED,                         -- 哪家公司 (NULL = 公司级共享)
  -- 文档信息
  title VARCHAR(255) NOT NULL,                     -- 'XX 板材硬度配方 v3'
  category VARCHAR(64),                            -- 'formula' / 'process' / 'spec' / 'recipe'
  tags JSON,                                       -- ['机密','配方','板材']
  -- 内容 (加密)
  encrypted_content LONGTEXT NOT NULL,             -- AES-256 加密的原文
  content_hash CHAR(64) NOT NULL,                  -- SHA-256 hash (用于校验完整性)
  encryption_iv CHAR(32) NOT NULL,                 -- AES IV (每文档独立)
  content_size INT,                                -- 原文字节数
  -- 元信息
  uploaded_by INT UNSIGNED NOT NULL,               -- 谁上传的 (老板自己或授权人)
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_accessed_at DATETIME,                       -- 最后被 AI 调用访问
  access_count INT DEFAULT 0,                      -- 被 AI 调用次数
  -- 授权名单
  authorized_user_ids JSON,                        -- [3, 7] = 这几个 user_id 也可访问
  -- 状态
  is_active TINYINT(1) DEFAULT 1,
  archived_at DATETIME,
  INDEX idx_owner (owner_user_id, is_active),
  INDEX idx_category (category, is_active),
  INDEX idx_hash (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. innovation_logs (AI 创新调用日志) ────────────────────────────────
DROP TABLE IF EXISTS innovation_logs;
CREATE TABLE innovation_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  -- 调用方
  called_by_user_id INT UNSIGNED NOT NULL,         -- 谁触发的
  -- 关联文档
  document_id BIGINT UNSIGNED,                     -- 关联 secure_documents.id
  -- 问题
  question TEXT NOT NULL,                          -- 例: '如何增加板材硬度?'
  question_hash CHAR(64),                          -- 问题 hash (类似问题 dedupe)
  -- 约束规则 (老板设的, 注入 prompt)
  constraint_rules TEXT,                           -- 例: '不准外传 / 不准透露数字'
  -- LLM 调用
  llm_provider VARCHAR(64),
  llm_model VARCHAR(128),
  llm_endpoint VARCHAR(255),                       -- 客户的 base_url (记日志用)
  -- 结果
  response_text LONGTEXT,                          -- AI 回复
  response_tokens INT,                             -- 用了多少 token
  response_latency_ms INT,                         -- 多少毫秒
  -- 老板 feedback
  feedback_rating TINYINT,                         -- 1-5
  feedback_text TEXT,
  -- 时间
  called_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (called_by_user_id, called_at),
  INDEX idx_doc (document_id),
  INDEX idx_question (question_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── rbac: 加 5 个 perm ───────────────────────────────────────────────────
INSERT IGNORE INTO rbac_permissions (name, label, category, description, hidden) VALUES
  ('secure-knowledge:upload',  '上传机密文档',   'secure-knowledge', '老板 / 授权人上传机密文档', 0),
  ('secure-knowledge:read',    '查看机密文档',   'secure-knowledge', '查看机密文档原文', 0),
  ('secure-knowledge:innovate','触发 AI 创新',   'secure-knowledge', '调 LLM 用机密 + 互联网创新', 0),
  ('secure-knowledge:authorize','授权他人访问',   'secure-knowledge', '老板专属: 授权他人查看', 0),
  ('secure-knowledge:archive', '归档机密文档',   'secure-knowledge', '老板专属: 归档/删除', 0);

-- ─── menu_modules: 加 1 个菜单 ────────────────────────────────────────────
INSERT IGNORE INTO menu_modules (`key`, label_zh, label_en, icon, route, category, sort_order, required, children_json) VALUES
  ('secure-knowledge', '机密配方 AI', 'Secure Knowledge AI', 'lock', '/secure-knowledge', 'general', 91, 1,
   JSON_ARRAY(
      JSON_OBJECT('key','secure-knowledge:list',     'label','机密库',     'to','/secure-knowledge'),
      JSON_OBJECT('key','secure-knowledge:innovate', 'label','AI 创新',    'to','/secure-knowledge/innovate'),
      JSON_OBJECT('key','secure-knowledge:logs',     'label','调用日志',   'to','/secure-knowledge/logs')
   ));

-- ─── server_modules: SGP 默认启用 ─────────────────────────────────────────
INSERT IGNORE INTO server_modules (server_profile_id, module_key) VALUES (1, 'secure-knowledge');

-- 立 (2026-09-18 江小鱼)
-- 后续 Phase 2: 通用 LLM caller + AES-256 加密 + innovate endpoint