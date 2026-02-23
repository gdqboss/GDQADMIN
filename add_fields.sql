ALTER TABLE `ddwx_shop_product` ADD COLUMN `english_name` varchar(255) DEFAULT '' COMMENT '英文名称' AFTER `name`;
ALTER TABLE `ddwx_shop_product` ADD COLUMN `supplier_code` varchar(100) DEFAULT '' COMMENT '供货商ID' AFTER `supplier_number`;
ALTER TABLE `ddwx_shop_product` ADD COLUMN `production_date` date DEFAULT NULL COMMENT '生产日期' AFTER `pic`;
ALTER TABLE `ddwx_shop_product` ADD COLUMN `warehouse_date` date DEFAULT NULL COMMENT '入仓日期' AFTER `production_date`;
