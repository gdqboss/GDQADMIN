-- 添加审核相关字段
ALTER TABLE tasks
ADD COLUMN review_notes TEXT COMMENT '审核备注',
ADD COLUMN reviewed_by INT COMMENT '审核人ID',
ADD COLUMN reviewed_at DATETIME COMMENT '审核时间';

-- 添加索引
ALTER TABLE tasks ADD INDEX idx_reviewed_by (reviewed_by);
