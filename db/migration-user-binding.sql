-- 用户身份绑定机制数据库迁移

-- 1. qrcodes 表添加 buyer_phone 字段
ALTER TABLE qrcodes
  ADD COLUMN buyer_phone VARCHAR(20) DEFAULT NULL COMMENT '买家手机号' AFTER buyer;

-- 2. 创建短信验证码表
CREATE TABLE IF NOT EXISTS sms_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL COMMENT '手机号',
  code VARCHAR(6) NOT NULL COMMENT '验证码',
  expires_at DATETIME NOT NULL COMMENT '过期时间',
  used TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
  ip VARCHAR(50) DEFAULT NULL COMMENT '请求IP',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  INDEX idx_phone_expires (phone, expires_at),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信验证码记录';
