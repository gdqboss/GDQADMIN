-- Migration: User Identity System
-- Date: 2026-03-02
-- Description: Add identity codes, OA fields, and user relationship tracking

-- 1. users表添加身份码字段
ALTER TABLE users
  ADD COLUMN identity_code VARCHAR(32) UNIQUE DEFAULT NULL COMMENT '专属身份码',
  ADD COLUMN identity_qr_path VARCHAR(255) DEFAULT NULL COMMENT '身份码二维码图片路径',
  ADD COLUMN can_oa_checkin TINYINT(1) DEFAULT 0 COMMENT '可使用OA签到',
  ADD COLUMN last_checkin_at DATETIME DEFAULT NULL COMMENT '最后签到时间',
  ADD COLUMN department_id INT DEFAULT NULL COMMENT '部门ID',
  ADD COLUMN job_level_id INT DEFAULT NULL COMMENT '职级ID',
  ADD COLUMN employee_code VARCHAR(50) DEFAULT NULL COMMENT '员工工号',
  ADD COLUMN hire_date DATE DEFAULT NULL COMMENT '入职日期',
  ADD COLUMN avatar VARCHAR(255) DEFAULT NULL COMMENT '头像URL';

CREATE INDEX idx_users_identity_code ON users(identity_code);
CREATE INDEX idx_user_dept ON users(department_id);

-- 2. h5_users表添加密码修改和身份码字段
ALTER TABLE h5_users
  ADD COLUMN password_updated_at DATETIME DEFAULT NULL COMMENT '密码最后修改时间',
  ADD COLUMN is_internal TINYINT(1) DEFAULT 0 COMMENT '是否内部人员',
  ADD COLUMN identity_code VARCHAR(32) UNIQUE DEFAULT NULL COMMENT '内部人员专属身份码';

CREATE INDEX idx_h5_users_identity_code ON h5_users(identity_code);
CREATE INDEX idx_h5_users_is_internal ON h5_users(is_internal);

-- 3. 创建身份码扫码记录表
CREATE TABLE IF NOT EXISTS identity_scan_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identity_code VARCHAR(32) NOT NULL,
  user_type ENUM('system', 'h5') NOT NULL,
  user_id INT NOT NULL,
  action VARCHAR(50) NOT NULL COMMENT 'checkin/verify/access',
  location VARCHAR(200) DEFAULT NULL,
  ip VARCHAR(50) DEFAULT NULL,
  device_info VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_identity_code (identity_code),
  INDEX idx_user (user_type, user_id)
);

-- 4. 扩展qrcodes表支持推荐人绑定
ALTER TABLE qrcodes
  ADD COLUMN referrer_h5_user_id INT DEFAULT NULL COMMENT '推荐人H5用户ID';

CREATE INDEX idx_qrcode_referrer ON qrcodes(referrer_h5_user_id);

-- 5. 创建用户关系链变更日志表
CREATE TABLE IF NOT EXISTS h5_user_parent_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  h5_user_id INT NOT NULL,
  old_parent_id INT DEFAULT NULL,
  new_parent_id INT DEFAULT NULL,
  operator_id INT NOT NULL COMMENT '操作人（系统用户ID）',
  reason VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_h5_user (h5_user_id)
);

-- 6. 创建密码修改记录表
CREATE TABLE IF NOT EXISTS password_change_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('system', 'h5') NOT NULL,
  user_id INT NOT NULL,
  changed_by ENUM('self', 'admin') NOT NULL,
  ip VARCHAR(50) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_type, user_id)
);
