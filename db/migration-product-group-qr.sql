-- 商品表增加售后群二维码URL，用于一物一码出售后扫码入群
ALTER TABLE products ADD COLUMN group_qr_url VARCHAR(500) DEFAULT NULL COMMENT '售后服务群二维码图片URL';
