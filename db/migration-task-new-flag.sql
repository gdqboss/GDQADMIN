-- 添加 is_new 字段支持任务红点未读
ALTER TABLE tasks ADD COLUMN is_new TINYINT(1) DEFAULT 1 AFTER status;