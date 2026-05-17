-- 商品表增加售后群二维码平台类型（wechat_work/dingtalk/telegram/whatsapp/other）
ALTER TABLE products ADD COLUMN group_qr_type VARCHAR(50) DEFAULT NULL COMMENT '售后服务群类型：wechat_work/dingtalk/telegram/whatsapp/other';
fau
