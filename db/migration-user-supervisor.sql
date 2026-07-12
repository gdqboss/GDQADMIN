-- 添加上级字段到users表
ALTER TABLE users 
  ADD COLUMN supervisor_id INT DEFAULT NULL COMMENT '直属上级user_id',
  ADD CONSTRAINT fk_users_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX idx_users_supervisor ON users(supervisor_id);
