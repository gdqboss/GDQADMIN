-- 添加质保期长度和单位字段
ALTER TABLE qrcodes 
  ADD COLUMN warranty_period INT DEFAULT NULL COMMENT '质保期长度',
  ADD COLUMN warranty_unit ENUM('day','month','year') DEFAULT NULL COMMENT '质保期单位：天/月/年';

-- 添加索引
CREATE INDEX idx_warranty_end ON qrcodes(warranty_end);
