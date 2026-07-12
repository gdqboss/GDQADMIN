-- 为 product_skus 表添加图片字段
ALTER TABLE product_skus ADD COLUMN image VARCHAR(255) DEFAULT NULL COMMENT 'SKU图片' AFTER specs;
