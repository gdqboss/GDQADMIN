-- 创建经销商表
CREATE TABLE IF NOT EXISTS `ddwx_barcode_dealer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dealer_name` varchar(255) NOT NULL COMMENT '经销商名称',
  `contact_person` varchar(100) NOT NULL COMMENT '负责人',
  `phone` varchar(20) NOT NULL COMMENT '联系电话',
  `address` varchar(255) NOT NULL COMMENT '经营地址',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='经销商表';

-- 创建门店表
CREATE TABLE IF NOT EXISTS `ddwx_barcode_store` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dealer_id` int(11) NOT NULL COMMENT '经销商ID',
  `store_name` varchar(255) NOT NULL COMMENT '门店名称',
  `contact_person` varchar(100) NOT NULL COMMENT '负责人',
  `phone` varchar(20) NOT NULL COMMENT '联系电话',
  `address` varchar(255) NOT NULL COMMENT '经营地址',
  `is_main` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为主店（0：否，1：是）',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_dealer_id` (`dealer_id`),
  KEY `idx_is_main` (`is_main`),
  CONSTRAINT `fk_dealer_store` FOREIGN KEY (`dealer_id`) REFERENCES `ddwx_barcode_dealer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店表';

-- 创建存储过程：设置主店时，将其他门店设为非主店
DELIMITER //
CREATE PROCEDURE `set_main_store`(IN `p_store_id` INT, IN `p_dealer_id` INT)
BEGIN
    -- 开始事务
    START TRANSACTION;
    
    -- 将该经销商的所有门店设为非主店
    UPDATE `ddwx_barcode_store` SET `is_main` = 0 WHERE `dealer_id` = p_dealer_id;
    
    -- 将指定门店设为主店
    UPDATE `ddwx_barcode_store` SET `is_main` = 1 WHERE `id` = p_store_id AND `dealer_id` = p_dealer_id;
    
    -- 提交事务
    COMMIT;
END //
DELIMITER ;