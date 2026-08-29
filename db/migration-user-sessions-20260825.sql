-- 2026-08-25 gbaw.cn/gdqadmin 单点登录踢出机制
-- user_sessions 表: 每条记录 = 一次成功登录 (token + device + invalidated)
-- invalidated=1 表示被新登录踢出或主动 logout
-- middleware/auth.js 用此表校验 token 有效性 (实现真正的单点登录)

CREATE TABLE IF NOT EXISTS user_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL COMMENT 'SHA256(token) 用于 revoke 校验, 不存明文',
  device_fingerprint VARCHAR(64) DEFAULT NULL COMMENT '浏览器指纹 ua+screen',
  device_label VARCHAR(100) DEFAULT NULL COMMENT '前端传入的设备名 (Chrome/Edge/iPhone...)',
  ip VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  invalidated TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=active 1=revoked',
  invalidated_at DATETIME DEFAULT NULL,
  invalidated_reason VARCHAR(50) DEFAULT NULL COMMENT 'logout / new_login / admin_revoke',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_active (user_id, invalidated),
  INDEX idx_token_hash (token_hash),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 兼容老 token: 中间件层若 user_sessions 查不到对应 token_hash, 视为已失效
-- 这样下次所有用户重新登录时自动建 session