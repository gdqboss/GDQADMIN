-- 创建独立的进销存商品表
CREATE TABLE IF NOT EXISTS `ddwx_barcode_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `bid` int(11) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL COMMENT '商品名称',
  `english_name` varchar(255) DEFAULT '' COMMENT '英文名称',
  `supplier_id` int(11) DEFAULT '0' COMMENT '供应商ID',
  `supplier_code` varchar(100) DEFAULT '' COMMENT '供货商ID',
  `pic` varchar(255) DEFAULT '' COMMENT '商品主图',
  `production_date` date DEFAULT NULL COMMENT '生产日期',
  `warehouse_date` date DEFAULT NULL COMMENT '入仓日期',
  `stock` int(11) unsigned DEFAULT '0' COMMENT '总库存量',
  `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
  `update_time` int(11) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `aid` (`aid`),
  KEY `bid` (`bid`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='条码库存-商品表';

-- 创建独立的进销存规格表
CREATE TABLE IF NOT EXISTS `ddwx_barcode_inventory_guige` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `proid` int(11) NOT NULL COMMENT '商品ID',
  `name` varchar(100) NOT NULL COMMENT '规格名称',
  `stock` int(11) unsigned DEFAULT '0' COMMENT '库存量',
  `cost_price` decimal(11,2) DEFAULT '0.00' COMMENT '成本价',
  `market_price` decimal(11,2) DEFAULT '0.00' COMMENT '出厂价',
  `sell_price` decimal(11,2) DEFAULT '0.00' COMMENT '销售价',
  `barcode` varchar(100) DEFAULT '' COMMENT '条码/二维码',
  `code_type` tinyint(1) DEFAULT '1' COMMENT '码类型：1=条码，2=二维码',
  `pic` varchar(255) DEFAULT '' COMMENT '规格图片',
  `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
  `update_time` int(11) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `aid` (`aid`),
  KEY `proid` (`proid`),
  KEY `barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='条码库存-规格表';

-- 创建库存变动日志表
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='条码库存-操作日志';
