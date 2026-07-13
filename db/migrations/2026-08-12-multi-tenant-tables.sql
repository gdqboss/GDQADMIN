-- ===========================================================================
-- 2026-08-12 多租户 + 客户自管配置表 (banner / theme / translation)
-- ===========================================================================

-- 1. banners 表 (轮播图 / 主图)
CREATE TABLE IF NOT EXISTS banners (
  id INT PRIMARY KEY AUTO_INCREMENT,
  server_profile_id INT NOT NULL,
  position VARCHAR(50) NOT NULL COMMENT 'home_top / home_mid / popup / category_top / order_success',
  title VARCHAR(200) COMMENT '可选标题',
  subtitle VARCHAR(200) COMMENT '可选副标题',
  image_url VARCHAR(500) NOT NULL COMMENT '主图 URL',
  image_mobile_url VARCHAR(500) COMMENT '移动端适配图(可选)',
  link_type ENUM('none','internal','external','product','category','article','activity','minip','wechat','phone','custom') DEFAULT 'none',
  link_target VARCHAR(500) COMMENT '跳转目标(路径/URL/ID)',
  link_params JSON COMMENT '跳转额外参数',
  sort_order INT DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active',
  start_at DATETIME COMMENT '生效开始',
  end_at DATETIME COMMENT '生效结束',
  click_count INT DEFAULT 0,
  view_count INT DEFAULT 0,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_position (server_profile_id, position, status, sort_order),
  INDEX idx_time (start_at, end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轮播图配置 (多租户)';

-- 2. theme_config 表 (主题 / UI 风格)
CREATE TABLE IF NOT EXISTS theme_config (
  id INT PRIMARY KEY AUTO_INCREMENT,
  server_profile_id INT NOT NULL UNIQUE,
  ui_kit VARCHAR(50) DEFAULT 'element-plus' COMMENT 'element-plus / naive-ui / ant-design / custom',
  theme_mode ENUM('light','dark','auto') DEFAULT 'light',
  primary_color VARCHAR(20) DEFAULT '#d97706',
  secondary_color VARCHAR(20) DEFAULT '#0f172a',
  success_color VARCHAR(20) DEFAULT '#10b981',
  warning_color VARCHAR(20) DEFAULT '#f59e0b',
  danger_color VARCHAR(20) DEFAULT '#ef4444',
  bg_color VARCHAR(20) DEFAULT '#ffffff',
  text_color VARCHAR(20) DEFAULT '#1f2937',
  font_family VARCHAR(200) DEFAULT '"PingFang SC", sans-serif',
  border_radius VARCHAR(10) DEFAULT '8px',
  layout_type VARCHAR(50) DEFAULT 'admin' COMMENT 'admin / portal / minimal / labor',
  custom_css LONGTEXT COMMENT '客户自定义 CSS',
  logo_url VARCHAR(500) COMMENT '公司 logo',
  favicon_url VARCHAR(500) COMMENT '浏览器图标',
  updated_by INT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='主题配置 (多租户)';

-- 3. translations 表 (多语言,客户可自改)
CREATE TABLE IF NOT EXISTS translations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  server_profile_id INT NOT NULL,
  lang VARCHAR(10) NOT NULL COMMENT 'zh / en / ms',
  msg_key VARCHAR(200) NOT NULL COMMENT 'i18n key',
  msg_value TEXT NOT NULL,
  module VARCHAR(50) DEFAULT 'common' COMMENT '模块名',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_lang_key (server_profile_id, lang, msg_key),
  INDEX idx_module (server_profile_id, lang, module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='多语言翻译 (多租户,客户可改)';

-- 4. audit_logs 表 (审计日志 - 谁改了什么)
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  server_profile_id INT NOT NULL,
  user_id INT,
  username VARCHAR(100),
  action VARCHAR(50) NOT NULL COMMENT 'CREATE / UPDATE / DELETE / LOGIN',
  table_name VARCHAR(100) NOT NULL,
  record_id INT,
  old_value JSON,
  new_value JSON,
  ip_address VARCHAR(50),
  user_agent VARCHAR(500),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (server_profile_id, user_id, created_at),
  INDEX idx_table (table_name, record_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作审计日志';

-- 5. users 表加 server_profile_id (区分 customer_admin vs super_admin)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS server_profile_id INT DEFAULT 1 COMMENT '所属客户 (NULL=super_admin可跨客户)',
  ADD INDEX IF NOT EXISTS idx_server_profile (server_profile_id);

-- 6. 插入默认数据 (新加坡 profile 1)
INSERT IGNORE INTO theme_config (server_profile_id, ui_kit, theme_mode, primary_color, secondary_color)
VALUES (1, 'element-plus', 'light', '#d97706', '#0f172a');

-- 给每个已有 profile 加默认 theme
INSERT IGNORE INTO theme_config (server_profile_id, ui_kit, theme_mode)
SELECT id, 'element-plus', 'light' FROM server_profiles WHERE id NOT IN (SELECT server_profile_id FROM theme_config);