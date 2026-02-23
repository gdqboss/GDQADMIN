-- 创建库存盘点记录表
CREATE TABLE IF NOT EXISTS `ddwx_barcode_inventory_check` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int(11) NOT NULL DEFAULT '0' COMMENT '商户ID',
  `bid` int(11) NOT NULL DEFAULT '0' COMMENT '品牌ID',
  `check_sn` varchar(50) NOT NULL COMMENT '盘点单号',
  `check_name` varchar(100) NOT NULL COMMENT '盘点名称',
  `check_time` int(11) NOT NULL COMMENT '盘点时间',
  `check_user` varchar(50) NOT NULL COMMENT '盘点人',
  `total_products` int(11) NOT NULL DEFAULT '0' COMMENT '盘点商品总数',
  `total_diff` int(11) NOT NULL DEFAULT '0' COMMENT '差异总数',
  `total_diff_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '差异金额',
  `status` int(11) NOT NULL DEFAULT '0' COMMENT '状态：0-未完成，1-已完成',
  `remark` text COMMENT '备注',
  `createtime` int(11) NOT NULL COMMENT '创建时间',
  `updatetime` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_check_sn` (`check_sn`),
  KEY `idx_aid_status` (`aid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='库存盘点记录表';

-- 创建库存盘点详情表
CREATE TABLE IF NOT EXISTS `ddwx_barcode_inventory_check_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `aid` int(11) NOT NULL DEFAULT '0' COMMENT '商户ID',
  `check_id` int(11) NOT NULL COMMENT '盘点记录ID',
  `proid` int(11) NOT NULL COMMENT '商品ID',
  `ggid` int(11) NOT NULL COMMENT '规格ID',
  `pro_name` varchar(200) NOT NULL COMMENT '商品名称',
  `gg_name` varchar(100) NOT NULL COMMENT '规格名称',
  `barcode` varchar(100) DEFAULT NULL COMMENT '条码/二维码',
  `system_stock` int(11) NOT NULL COMMENT '系统库存',
  `actual_stock` int(11) NOT NULL COMMENT '实际盘点库存',
  `diff_stock` int(11) NOT NULL COMMENT '差异数量',
  `price` decimal(10,2) NOT NULL COMMENT '商品单价',
  `diff_amount` decimal(10,2) NOT NULL COMMENT '差异金额',
  `remark` text COMMENT '备注',
  `createtime` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_check_id` (`check_id`),
  KEY `idx_proid_ggid` (`proid`,`ggid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='库存盘点详情表';
