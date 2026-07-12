-- 员工注册功能数据库迁移

-- 1. 修改status字段，添加pending和rejected状态
ALTER TABLE users 
  MODIFY COLUMN status ENUM('pending', 'active', 'rejected', 'disabled') DEFAULT 'active' COMMENT '账号状态';

-- 2. 添加新字段
ALTER TABLE users 
  ADD COLUMN id_card VARCHAR(18) DEFAULT NULL COMMENT '身份证号',
  ADD COLUMN applied_at DATETIME DEFAULT NULL COMMENT '申请时间',
  ADD COLUMN approved_at DATETIME DEFAULT NULL COMMENT '审核时间',
  ADD COLUMN approved_by INT DEFAULT NULL COMMENT '审核人ID',
  ADD COLUMN reject_reason VARCHAR(255) DEFAULT NULL COMMENT '拒绝原因';

-- 3. 创建索引
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_id_card ON users(id_card);
