-- ============================================================
-- 一物一码系统 v3 迁移：状态流转扩展 + 售后流程增强
-- ============================================================

-- 1. 扩展二维码状态枚举
ALTER TABLE qrcodes MODIFY COLUMN status
  ENUM('unused','bindProduct','inStock','outStock','shipped','sold','activated','afterSale','returned','disabled')
  DEFAULT 'unused';

-- 2. 售后记录表增强（逐条添加，忽略已存在的列错误）
-- 如果某列已存在会报错，可逐条执行，跳过报错的即可

ALTER TABLE after_sale_records ADD COLUMN ticket_no VARCHAR(30) COMMENT '工单编号';
ALTER TABLE after_sale_records ADD COLUMN type ENUM('repair','return','exchange','consult') DEFAULT 'repair' COMMENT '售后类型';
ALTER TABLE after_sale_records ADD COLUMN priority ENUM('low','normal','high','urgent') DEFAULT 'normal' COMMENT '优先级';
ALTER TABLE after_sale_records ADD COLUMN images TEXT COMMENT '图片凭证JSON数组';
ALTER TABLE after_sale_records ADD COLUMN contact_phone VARCHAR(30) COMMENT '联系电话';
ALTER TABLE after_sale_records ADD COLUMN responded_at DATETIME COMMENT '首次响应时间';
ALTER TABLE after_sale_records ADD COLUMN resolved_at DATETIME COMMENT '解决时间';
ALTER TABLE after_sale_records ADD COLUMN previous_status VARCHAR(20) COMMENT '售后前二维码状态（用于回退）';

-- 以下字段可能已存在，已存在则跳过
-- ALTER TABLE after_sale_records ADD COLUMN assigned_to INT COMMENT '指派处理人';
-- ALTER TABLE after_sale_records ADD COLUMN channel_qrcodes JSON COMMENT '渠道二维码';

-- 3. 为 ticket_no 添加唯一索引
ALTER TABLE after_sale_records ADD UNIQUE INDEX uk_ticket_no (ticket_no);
