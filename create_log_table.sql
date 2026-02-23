CREATE TABLE IF NOT EXISTS `ddwx_barcode_inventory_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `aid` int(11) NOT NULL,
    `bid` int(11) NOT NULL DEFAULT '0',
    `type` varchar(50) NOT NULL COMMENT '日志类型',
    `request_data` text COMMENT '请求数据',
    `product_id` int(11) DEFAULT NULL COMMENT '商品ID',
    `error_msg` text COMMENT '错误信息',
    `error_trace` text COMMENT '错误堆栈',
    `create_time` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `aid` (`aid`),
    KEY `bid` (`bid`),
    KEY `type` (`type`),
    KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='条码库存操作日志';
