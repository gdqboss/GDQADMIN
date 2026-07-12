-- 一物一码绑定支持选择规格SKU
-- 给 qrcodes 表添加 sku_id 字段

ALTER TABLE qrcodes
  ADD COLUMN sku_id INT DEFAULT NULL COMMENT '绑定的SKU ID' AFTER product_id,
  ADD CONSTRAINT fk_qrcode_sku FOREIGN KEY (sku_id) REFERENCES product_skus(id) ON DELETE SET NULL;
