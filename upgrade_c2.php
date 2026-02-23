<?php
if(getcustom('ganer_fenxiao')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_prize_pool_log` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NOT NULL ,
    `mid`  int(11) NOT NULL DEFAULT 0 COMMENT '订单会员id' ,
    `orderid`  int(11) NOT NULL DEFAULT 0 COMMENT '订单id' ,
    `type`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '订单类型' ,
    `num`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '奖金池金额' ,
    `createtime`  int(10) NOT NULL DEFAULT 0 COMMENT '创建时间' ,
    `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' ,
    `levelid` int(11) DEFAULT '0' COMMENT '等级id',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_prize_pool_set` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NOT NULL ,
    `pool_num`  decimal(12,2) NOT NULL ,
    `pool_time`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '结算时间 0支付后 1收货后' ,
    `send_bili` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '奖金发放总比例',
    `levelids` varchar(100) NOT NULL DEFAULT '' COMMENT '级别比例',
    `send_time` int(10) NOT NULL DEFAULT '0' COMMENT '发放时间',
    `show_pool` tinyint(1) NOT NULL DEFAULT '0' COMMENT '前台展示 0全部金额 1比例金额',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_tuiguang_manage` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NOT NULL DEFAULT 1 ,
    `key`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '唯一标识' ,
    `levelid`  int(11) NOT NULL DEFAULT 0 COMMENT '会员级别id' ,
    `down_levelid`  int(11) NOT NULL DEFAULT 0 COMMENT '下级会员级别id' ,
    `commission1`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '一级佣金比例' ,
    `commission2`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '二级佣金比例' ,
    `commission3`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '三级佣金比例' ,
    `createtime`  int(10) NOT NULL ,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_prize_pool_send_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `pool_num` decimal(12,2) NOT NULL,
      `send_bili` decimal(12,2) NOT NULL,
      `prize_total` decimal(12,2) NOT NULL,
      `levelid` int(11) NOT NULL DEFAULT '0' COMMENT '级别id',
      `level_bili` decimal(12,2) NOT NULL,
      `level_prize_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '发放总奖金',
      `member_count` int(11) NOT NULL DEFAULT '0' COMMENT '会员人数',
      `send_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '发放类型 0手动发放 1自动发放',
      `createtime` int(10) NOT NULL DEFAULT '0' COMMENT '发放时间',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='奖金池——发放记录';");
}

if(getcustom('product_sync_business')){
    if (!pdo_fieldexists2("ddwx_business", "sync_plate_product")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `sync_plate_product`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '允许同步平台商品 1是 0否';");
    }
    if (!pdo_fieldexists2("ddwx_business", "status_plate_product")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `status_plate_product`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '允许下架平台商品 1是 0否';");
    }
    if (!pdo_fieldexists2("ddwx_business", "stock_plate_product")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `stock_plate_product`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '允许修改平台商品库存 1是 0否';");
    }
    if (!pdo_fieldexists2("ddwx_business", "add_product")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `add_product`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '允许添加自营商品 1是 0否'");
    }
    if (!pdo_fieldexists2("ddwx_shop_guige", "plate_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `plate_id`  int(11) NOT NULL DEFAULT 0 COMMENT '关联平台规格商品id';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "plate_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `plate_id`  int(11) NOT NULL DEFAULT 0 COMMENT '关联平台产品id';");
    }
}
if(getcustom('restaurant_cuxiao_activity_day')){
    if (!pdo_fieldexists2("ddwx_restaurant_cuxiao", "activity_day_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cuxiao` ADD COLUMN `activity_day_type` tinyint(1) DEFAULT '0' COMMENT '活动日模式';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cuxiao` ADD COLUMN `activity_day_week` varchar(20) DEFAULT '0,1,2,3,4,5,6' COMMENT '星期模式';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cuxiao` ADD COLUMN `activity_day_date` varchar(20) DEFAULT NULL COMMENT '日期模式';");
    }
}

if(getcustom('levelup_perpaymoney')){
    if (!pdo_fieldexists2("ddwx_member_level", "up_perpaymoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `up_perpaymoney` decimal(11,2) DEFAULT NULL AFTER `up_rechargemoney`;");
    }
}
if(getcustom('network_slide')){
    if (!pdo_fieldexists2("ddwx_member_level", "net_down_levelid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `net_down_levelid` varchar (50) DEFAULT '0' COMMENT '直推下级等级ID';");
    }
    if (!pdo_fieldexists2("ddwx_member_level", "net_down_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `net_down_num` int(11) DEFAULT '0' COMMENT '直推下级人数';");
    }
    if (!pdo_fieldexists2("ddwx_member_level", "net_down_next_levelid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `net_down_next_levelid` int(11) DEFAULT '0' COMMENT '新推下级会员级别ID';");
    }
    if (!pdo_fieldexists2("ddwx_member_level", "slide_down_levelid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `slide_down_levelid` int(11) DEFAULT '0' COMMENT '滑落给予下级会员级别ID';");
    }
    if (!pdo_fieldexists2("ddwx_member_level", "slide_down_team")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `slide_down_team` tinyint(1) DEFAULT '0' COMMENT '0不限制 1仅滑落链动脱离的人';");
    }
    if (!pdo_fieldexists2("ddwx_member", "is_slide")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `is_slide` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是公排滑落会员 0否 1是';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_pid_changelog` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `mid` int(11) NOT NULL DEFAULT '0',
      `pid` int(11) NOT NULL DEFAULT '0' COMMENT '',
      `path` text,
      `pid_origin` int(11) DEFAULT NULL,
      `path_origin` text,
      `createtime` int(11) NOT NULL DEFAULT '0',
      `updatetime` int(11) NOT NULL DEFAULT '0',
      `isback` tinyint(1) DEFAULT '0' COMMENT '是否回归',
      `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `pid` (`pid`) USING BTREE,
      KEY `pid_origin` (`pid_origin`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    if (pdo_fieldexists2("ddwx_member_level", "slide_down_levelid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` MODIFY COLUMN `slide_down_levelid`  varchar(100) NULL DEFAULT '' COMMENT '滑落给予下级会员级别ID' AFTER `net_down_next_levelid`;");
    }
}

if(getcustom('fenhong_jiaquan_copies')){
    if (!pdo_fieldexists2("ddwx_member_level", "fenhong_limit_stime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `fenhong_limit_stime` int(11) DEFAULT NULL COMMENT '有效开始时间',ADD COLUMN `fenhong_limit_etime` int(11) DEFAULT NULL COMMENT '有效结束时间',ADD COLUMN `fenhong_jiaquan_maxmoney` decimal(10,2) DEFAULT '0' COMMENT '该等级会员加权分红金额上限';");
    }
}

if(getcustom('douyin_groupbuy')){
    if (!pdo_fieldexists2("ddwx_shop_product", "dyg_product_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `dyg_product_id` bigint UNSIGNED NOT NULL DEFAULT 0 COMMENT '抖音团购商品id',
            ADD INDEX `dyg_product_id`(`dyg_product_id`);");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `isdygroupbuy` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否是抖音团购核销 0：是 1：否',
            ADD COLUMN `dypoi_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '抖音核销门店',
            ADD COLUMN `dyaccount_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '抖音核销账户',
            ADD COLUMN `dycodes` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '抖音核销码数据集合',
            ADD COLUMN `dyencrypted_datas` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '抖音二维码数据集合',
            ADD COLUMN `dycertificate_ids` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '抖音核销码id数据集合',
            ADD COLUMN `dyorderids`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '抖音核销码订单id数据集合',
            ADD COLUMN `dydatas` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '抖音核销数据集合',
            MODIFY COLUMN `paytime` int(11)  NULL DEFAULT 0;");
    }

    if (!pdo_fieldexists2("ddwx_mendian", "poi_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` ADD COLUMN `poi_id` varchar(50) NOT NULL DEFAULT ''  COMMENT '抖音门店ID';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_douyin_groupbuy_category`  (
      `id` bigint(20) UNSIGNED NOT NULL,
      `aid` int(11) NOT NULL DEFAULT 0,
      `bid` int(11) NOT NULL,
      `pid` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
      `account_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '分类id 0：全部 ',
      `category_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
      `name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
      `parent_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父类目ID',
      `level` int(11) NULL DEFAULT 0 COMMENT '类目层级',
      `is_leaf` tinyint(1) NULL DEFAULT 0 COMMENT '是否是叶子结点',
      `enable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类目是否开放',
      `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
      `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`) USING BTREE,
      INDEX `aid`(`aid`) USING BTREE,
      INDEX `id`(`id`) USING BTREE,
      INDEX `pid`(`pid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_douyin_groupbuy_set`  (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT 0,
      `bid` int(11) NOT NULL DEFAULT 0,
      `client_key` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `client_secret` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `account_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商户账户',
      `account_name` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商家名称',
      `poi_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `status` tinyint(1) NOT NULL DEFAULT 0,
      `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
      `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
      `canrefund` tinyint(1) NOT NULL DEFAULT 0,
      `return_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `return_tel` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `return_province` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `return_city` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `return_area` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `return_address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
      `return_component_open` tinyint(1) NULL DEFAULT 0,
      `refundpic` tinyint(1) NOT NULL DEFAULT 0 COMMENT '退款图片 0：关闭 1：开启 开启后无需退货必上传',
      `autoclose` int(11) NOT NULL DEFAULT 10,
      PRIMARY KEY (`id`) USING BTREE,
      INDEX `aid`(`aid`) USING BTREE,
      INDEX `bid`(`bid`) USING BTREE,
      INDEX `account_id`(`account_id`) USING BTREE,
      INDEX `poi_id`(`poi_id`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_product_douyin_groupbuy`  (
      `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT 0,
      `bid` int(11) NULL DEFAULT 0,
      `proid` int(11) NOT NULL DEFAULT 0 COMMENT '商城商品id',
      `dy_product_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '抖音团购商品id',
      `dy_category_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '抖音团购分类id',
      `dy_category_full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
      `dy_product_type` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '抖音团购类型1：团购套餐4：日历房5：门票7：旅行跟拍8：一日游11：代金券12：新预售13：预定商品 15：次卡',
      `dy_biz_line` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '抖音团购业务线。（更新商品时不可修改）1：闭环自研开发者（如酒旅预售券） 5：小程序',
      `dy_sold_start_time` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '售卖开始时间(团购商品必填）',
      `dy_sold_end_time` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '售卖结束时间(团购商品必填）(到期自动下架)',
      `dy_origin_amount` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0' COMMENT '原价，团购创建时如有 commodity 属性可不填',
      `dy_actual_amount` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0' COMMENT '实际支付，单位分（注：创建预售商品为必传，创建预定商品时非必传）',
      `dy_sku_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '售卖单元名称',
      `dy_limit_type` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '库存上限类型。1：有限库存2：无限库存，2 时 stock_qty和avail_qty 字段无意义',
      `dy_stock_qty` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '库存信息（注：创建预售商品为必传，创建预定商品时非必传）',
      `dy_account_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '商家 ID，传入时服务商须与该商家满足授权关系',
      `dy_account_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商家名',
      `pois` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '店铺列表',
      `dy_pois` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '抖音店铺列表',
      `dy_poi_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
      `dy_bring_out_meal` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否可外带餐食 0：否 1：是',
      `dy_free_pack` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否可以打包 0：否 1：是',
      `dy_rec_person_num` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '建议使用人数',
      `dy_rec_person_num_max` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '最多使用人数',
      `dy_RefundPolicy` tinyint(1) NOT NULL DEFAULT 0 COMMENT '退款政策 \"1-允许退款 2-不可退款 3-有条件退\",',
      `dy_refund_need_merchant_confirm` tinyint(1) NOT NULL DEFAULT 0 COMMENT '退款是否需商家审核 0：否 1：是',
      `dy_superimposed_discounts` tinyint(1) UNSIGNED ZEROFILL NOT NULL DEFAULT 0 COMMENT '可以享受店内其他优惠 0：不可以 1：可以',
      `dy_use_date_type` int(11) NOT NULL DEFAULT 0 COMMENT '1指定日期 2指定天数；',
      `dy_use_day_duration` varchar(11) NOT NULL DEFAULT 0 COMMENT '购买日多少天',
      `dy_use_start_date` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用时间 券码的可以核销日期',
      `dy_use_end_date` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用时间 券码的可以核销日期',
      `dy_Notification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '使用规则',
      `dy_show_channel` int(11) NOT NULL DEFAULT 1 COMMENT '投放渠道 1-不限制 2-仅直播间可见 5-仅线下 8-仅线上',
      `dy_commodity` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
      `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
      `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`) USING BTREE,
      INDEX `aid`(`aid`) USING BTREE,
      INDEX `bid`(`bid`) USING BTREE,
      INDEX `proid`(`proid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        MODIFY COLUMN `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '';");

    \think\facade\Db::execute("ALTER TABLE `ddwx_douyin_groupbuy_set` DROP INDEX `aid`,ADD INDEX `aid`(`aid`) USING BTREE;");

    if (!pdo_fieldexists2("ddwx_douyin_groupbuy_set", "pstype")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_douyin_groupbuy_set` ADD COLUMN `pstype` tinyint(1) NULL DEFAULT 0 COMMENT '配方方式 0：默认 1：核销完成';");
    }
}
if(getcustom('taocan_product')){
    if (!pdo_fieldexists2("ddwx_member_level", "apply_taocan_proid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `apply_taocan_proid` int(11) DEFAULT 0 COMMENT '申请升级套餐商品id';");
    }
}

if(getcustom('mendian_upgrade')){
    if (!pdo_fieldexists2("ddwx_admin", "mendian_upgrade_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin` ADD COLUMN `mendian_upgrade_status` tinyint(1) NULL DEFAULT 0 COMMENT '1为开启门店升级功能';");
    }
    if (!pdo_fieldexists2("ddwx_member", "mdid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `mdid` int(11) NOT NULL DEFAULT 0 COMMENT '门店id';");
    }
    if (!pdo_fieldexists2("ddwx_mendian", "mid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` ADD COLUMN `mid` int(11) NOT NULL DEFAULT 0;");
    }
    if (!pdo_fieldexists2("ddwx_mendian", "check_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` ADD COLUMN `check_status` tinyint(1) DEFAULT '0' COMMENT '审核状态',
ADD COLUMN `reason` varchar(255) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_mendian", "xqname")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` 
		ADD COLUMN  `xqname` varchar(255) DEFAULT '',
		ADD COLUMN  `street` varchar(255) DEFAULT NULL,
		ADD COLUMN  `totalmoney` decimal(11,2) DEFAULT '0.00' COMMENT '总佣金',
		ADD COLUMN  `levelid` int(11) DEFAULT '0' COMMENT '等级id',
		ADD COLUMN  `groupid` int(5) DEFAULT '0' COMMENT '分组id',
		ADD COLUMN  `wxqrcode` varchar(255) DEFAULT NULL,
		ADD COLUMN  `pid` int(11) DEFAULT '0' COMMENT '推荐人id',
		ADD COLUMN  `mpqrcode` varchar(255) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "mendian_hexiao_set")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_product 
		ADD COLUMN  `mendian_hexiao_set` tinyint(1) DEFAULT '0' COMMENT '门店核销提成方式',
		ADD COLUMN  `mendianhexiaodata1` varchar(100) DEFAULT '' COMMENT '门店核销提成比例',
		ADD COLUMN  `mendianhexiaodata2` varchar(100) DEFAULT '' COMMENT '门店核销提成金额'");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_commission_record`  (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `mid` int(11) DEFAULT NULL,
		  `frommid` int(11) DEFAULT NULL,
		  `orderid` int(11) DEFAULT NULL,
		  `ogid` int(11) DEFAULT NULL,
		  `type` varchar(100) DEFAULT 'shop' COMMENT 'shop 商城',
		  `commission` decimal(11,2) DEFAULT NULL,
		  `score` decimal(12,3) DEFAULT '0.000',
		  `remark` varchar(255) DEFAULT NULL,
		  `createtime` int(11) DEFAULT NULL,
		  `endtime` int(11) DEFAULT NULL,
		  `status` tinyint(1) DEFAULT '0' COMMENT '-1 取消发放  1已发放 0 待发放',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `mid` (`mid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_group`  (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT '0',
		  `pid` int(11) DEFAULT '0',
		  `name` varchar(255) DEFAULT NULL,
		  `pic` varchar(255) DEFAULT NULL,
		  `status` int(1) DEFAULT '1',
		  `sort` int(11) DEFAULT '1',
		  `createtime` int(11) DEFAULT NULL,
		  `isdefault` tinyint(1) DEFAULT '0',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `bid` (`bid`) USING BTREE,
		  KEY `pid` (`pid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");


    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_hexiaouser`  (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `mid` int(11) NOT NULL DEFAULT '0',
		  `nickname` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `headimg` varchar(255) DEFAULT '0',
		  `mdid` int(10) DEFAULT NULL,
		  `createtime` int(11) DEFAULT NULL,
		  `hxnum` int(11) DEFAULT '0' COMMENT '核销数量',
		  `name` varchar(255) DEFAULT NULL,
		  `tel` varchar(255) DEFAULT NULL,
		  `status` int(11) DEFAULT '1' COMMENT '状态',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `can_up` (`headimg`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_level`  (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `aid` int(11) DEFAULT NULL,
	  `cid` int(11) NOT NULL DEFAULT '0',
	  `sort` int(11) DEFAULT '1',
	  `name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
	  `can_up` tinyint(1) DEFAULT '0',
	  `up_ordermoney` decimal(10,2) DEFAULT NULL,
	  `commissiontype` tinyint(1) DEFAULT '1',
	  `commission` decimal(10,2) DEFAULT '0.00',
	  `isdefault` tinyint(1) DEFAULT '0',
	  `createtime` int(11) DEFAULT NULL,
	  PRIMARY KEY (`id`) USING BTREE,
	  KEY `aid` (`aid`) USING BTREE,
	  KEY `sort` (`sort`) USING BTREE,
	  KEY `can_up` (`can_up`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_levelup_order`  (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `mid` int(11) DEFAULT NULL,
		  `levelid` int(11) DEFAULT NULL,
		  `ordernum` varchar(100) DEFAULT NULL,
		  `totalprice` decimal(11,2) DEFAULT NULL,
		  `title` varchar(255) DEFAULT NULL,
		  `status` int(1) DEFAULT '0' COMMENT '0未支付，1待审核，2已通过，3已驳回',
		  `createtime` int(11) DEFAULT NULL,
		  `levelup_time` int(11) DEFAULT NULL,
		  `platform` varchar(100) DEFAULT NULL,
		  `mdid` int(11) DEFAULT '0',
		  `beforelevelid` int(11) DEFAULT NULL,
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `mid` (`mid`) USING BTREE,
		  KEY `levelid` (`levelid`) USING BTREE,
		  KEY `levelup_time` (`levelup_time`),
		  KEY `status` (`status`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_memberqrcode`  (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `mid` int(11) DEFAULT NULL,
		  `wxqrcode` varchar(255) DEFAULT NULL,
		  `mpqrcode` varchar(255) DEFAULT NULL,
		  `h5qrcode` varchar(255) DEFAULT NULL,
		  `createtime` int(11) DEFAULT NULL,
		  PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_sysset`  (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `apply_status` int(11) DEFAULT '0',
		  `fwdistance` decimal(11,2) DEFAULT '0.00' COMMENT '服务距离',
		  `addhxuser_status` tinyint(1) DEFAULT '1',
		  `showaddress_status` tinyint(1) DEFAULT '1',
		  `notice_status` tinyint(1) DEFAULT '1',
		  `showLevel_status` decimal(11,2) DEFAULT '1.00',
		  `can_agent` tinyint(1) DEFAULT '0',
		  `status` tinyint(1) DEFAULT '0',
		  `commissiontype` tinyint(1) DEFAULT '0' COMMENT '提成方式',
		  `commission1` decimal(11,2) DEFAULT NULL,
		  `commission2` decimal(11,2) DEFAULT NULL,
		  `commission3` decimal(11,2) DEFAULT NULL,
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `status` (`status`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

    if (!pdo_fieldexists2("ddwx_mendian_sysset", "member_levelid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian_sysset` ADD COLUMN `member_levelid` int(11) DEFAULT '0';");
    }
    if (!pdo_fieldexists2("ddwx_mendian_commission_record", "mdid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian_commission_record` ADD COLUMN `mdid` int(11) DEFAULT '0';");
    }
    if (!pdo_fieldexists2("ddwx_hexiao_order", "hxmid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hexiao_order` ADD COLUMN  `hxmid` int(11) DEFAULT '0' COMMENT '核销员mid';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_orders`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `bid` int(11) NULL DEFAULT NULL,
      `mdid` int(11) NULL DEFAULT NULL,
      `number` int(11) NULL DEFAULT NULL,
      `info` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '总记录',
      `detail` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '明细',
      `createtime` int(11) NULL DEFAULT NULL,
      `status` tinyint(1) NULL DEFAULT 0 COMMENT '0未收货1已收货',
      `orderids` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '订单ids',
      PRIMARY KEY (`id`) USING BTREE,
      INDEX `mdid`(`mdid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");

    if (!pdo_fieldexists2("ddwx_freight", "select_address_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight` ADD COLUMN  `select_address_status` tinyint(1) DEFAULT '0' COMMENT '选择收货地址1开启0关闭';");
    }
    if (!pdo_fieldexists2("ddwx_mendian_sysset", "hexiao_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian_sysset` ADD COLUMN  `hexiao_status` tinyint(1) DEFAULT 0 COMMENT '未发货订单核销 0关闭1开启';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "mendianhexiaodata2_cal_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `mendianhexiaodata2_cal_type` varchar(20) DEFAULT 'order_num'  COMMENT '固定金额提成计算方式：order_num按单量，goods_num按购买数量' AFTER `mendianhexiaodata2`;");
    }
    if (!pdo_fieldexists2("ddwx_mendian_level", "commissiontype1_cal_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian_level` ADD COLUMN `commissiontype1_cal_type` varchar(20) DEFAULT 'order_num'  COMMENT '固定金额提成计算方式：order_num按单量，goods_num按购买数量' AFTER `commission`;");
    }
}

if(getcustom('yx_collage_teambuy_type')){
    if (!pdo_fieldexists2("ddwx_collage_sysset", "teambuy_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_sysset` ADD COLUMN `teambuy_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '团长拼团模式 0：普通 1：模式一';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `teambuy_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '团长拼团模式 0 ：普通 1：模式一' ;");
    }
}

if(getcustom('restaurant_cashdesk_auth_enter')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_restaurant_verify_auth_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `code` varchar(255) DEFAULT NULL,
      `qrcode` varchar(255) DEFAULT NULL COMMENT '二维码链接',
      `type` varchar(255) DEFAULT NULL COMMENT 'refund，cancel，discount',
      `uid` int(11) DEFAULT NULL COMMENT '收银台用户',
      `auth_uid` int(11) DEFAULT '0' COMMENT '扫码uid',
      `status` tinyint(1) DEFAULT '0' COMMENT '0:待验证 1：成功 2：身份不符合',
      `createtime` int(11) DEFAULT '0',
 
      `expiretime` int(11) DEFAULT '0' COMMENT '过期时间',
      PRIMARY KEY (`id`),
      UNIQUE KEY `code` (`code`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}

if(getcustom('product_supply_chain')){
    if (!pdo_fieldexists2("ddwx_admin", "supplier_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin` 
    ADD COLUMN `supplier_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否开启供应链',
    ADD COLUMN `supplier_name` varchar (64) DEFAULT '' COMMENT '供应链名称',
    ADD COLUMN `supplier_id` int (11) DEFAULT 0 COMMENT '供应商';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","product_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `product_type` tinyint(1) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","supplier_status")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
    ADD COLUMN `supplier_status` int(4) DEFAULT '100',
    ADD COLUMN `cardno`  varchar(64) NULL DEFAULT '' COMMENT '身份证号码',
    ADD COLUMN `card`  varchar(255) NULL DEFAULT '' COMMENT '身份证正面',
    ADD COLUMN `card_back`  varchar(255) NULL DEFAULT '' COMMENT '身份证反面',
    ADD COLUMN `area_id`  int (11) NULL DEFAULT 0 COMMENT '区域id',
    ADD COLUMN `area_regionid`  int (11) NULL DEFAULT 0 COMMENT '区域编码',
    ADD COLUMN `trade_type`  int (4) NULL DEFAULT '0' COMMENT '贸易类型,0普通,1101保税, 1404海外自提,2202一般贸易,1303海外直邮';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "out_proid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
    ADD COLUMN `out_proid`  varchar(64) NULL DEFAULT '' COMMENT '关联平台商品id(字符)',
    ADD COLUMN `trade_type`  int (4) NULL DEFAULT '0' COMMENT '贸易类型,0普通,1101保税, 1404海外自提,2202一般贸易,1303海外直邮';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "out_proid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `out_proid`  varchar(64) NULL DEFAULT '' COMMENT '关联平台商品id(字符)';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "gg_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `gg_num`  int(11) NULL DEFAULT 0 COMMENT '规格里面包含的单品数量';");
    }
    //supplier_shop_product, supplier_sysset,supplier_shop_guige,haidai_area todo
}

if(getcustom('more_productunit_guige')){
    if (!pdo_fieldexists2("ddwx_shop_product", "prounit")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `prounit` text COMMENT '多单位数据';");
    }
    if (!pdo_fieldexists2("ddwx_shop_guige", "prounit_0")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `prounit_0` int(11) DEFAULT '0' COMMENT '单位转换值';ALTER TABLE `ddwx_shop_guige` ADD COLUMN `prounit_1` int(11) DEFAULT '0' COMMENT '单位转换值';ALTER TABLE `ddwx_shop_guige` ADD COLUMN `prounit_2` int(11) DEFAULT '0' COMMENT '单位转换值';ALTER TABLE `ddwx_shop_guige` ADD COLUMN `prounit_3` int(11) DEFAULT '0' COMMENT '单位转换值';ALTER TABLE `ddwx_shop_guige` ADD COLUMN `prounit_4` int(11) DEFAULT '0' COMMENT '单位转换值';ALTER TABLE `ddwx_shop_guige` ADD COLUMN `prounit_5` int(11) DEFAULT '0' COMMENT '单位转换值';");
    }
}
if(getcustom('business_sales_quota')){
    if (!pdo_fieldexists2("ddwx_business", "total_sales_quota")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN  `total_sales_quota` decimal(11,2) DEFAULT '0.00' COMMENT '总销售额度',ADD COLUMN `sales_quota` decimal(11,2) DEFAULT '0.00' COMMENT '销售额度',ADD COLUMN  `kctime` tinyint(1) DEFAULT '0' COMMENT '扣除额度时间 0支付后 1是提交订单后',ADD COLUMN `kctype` tinyint(1) DEFAULT '0' COMMENT '扣除额度方式 0 按销售价 1成交价';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_salesquota_log` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `aid` int(11) NOT NULL DEFAULT '0',
	  `bid` int(11) NOT NULL DEFAULT '0',
	  `money` decimal(10,2) DEFAULT NULL,
	  `createtime` int(11) DEFAULT NULL,
	  `status` tinyint(1) DEFAULT '1' COMMENT '2 为售后退回',
	  `orderid` int(11) DEFAULT '0' COMMENT '订单号id',
	  `remark` varchar(100) DEFAULT NULL,
	  `after` decimal(10,0) DEFAULT '0',
	  PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}

if(getcustom('shop_zthx_backmoney')){
    if (!pdo_fieldexists2("ddwx_member_level", "zthx_backmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `zthx_backmoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '订单自提核销百分比返现';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "market_price")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `market_price` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '市场价格';");
    }
}
if(getcustom('restaurant_category_icon')) {
    if(!pdo_fieldexists2("ddwx_restaurant_product_category","tag")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_product_category` ADD COLUMN `tag` varchar(255) DEFAULT NULL COMMENT '标签';");
    }
}
if(getcustom('restaurant_cashdesk_mix_pay')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order","mix_paytypeid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `mix_paytypeid` tinyint(2) DEFAULT NULL COMMENT '混合支付方式id';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `mix_money` decimal(10,2) DEFAULT '0.00' COMMENT '混合支付金额';");
        think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `mix_paynum` varchar(50) DEFAULT NULL COMMENT '混合支付的流水号';");
        think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `mix_paytype` varchar(20) DEFAULT NULL COMMENT '支付方式';");
    }
}
if(getcustom('restaurant_cashdesk_member_paypwd')){
    if(!pdo_fieldexists2("ddwx_restaurant_cashdesk","default_paypwd")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cashdesk` ADD COLUMN `default_paypwd` varchar(50) DEFAULT NULL;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cashdesk` ADD COLUMN `paypwd_use_status` tinyint(1) DEFAULT '0' COMMENT '余额支付输入支付密码';");
    }
}
if(getcustom('restaurant_take_food')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_sysset","start_pickup_number")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` ADD COLUMN `start_pickup_number` int(11) DEFAULT '0' COMMENT '取餐号开始号';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_shop_sysset","take_food_number_prefix")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` ADD COLUMN `take_food_number_prefix` varchar(10) DEFAULT 'D' COMMENT '取餐码前缀';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_restaurant_take_food` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT NULL,
      `orderid` int(11) DEFAULT '0',
      `pickup_number` varchar(60) NOT NULL,
      `create_time` int(11) DEFAULT NULL,
      `call_time` int(11) DEFAULT NULL,
      `status` int(11) DEFAULT '0' COMMENT '0制作中;1待取餐',
      `call_text` varchar(255) DEFAULT NULL COMMENT '叫号语音文字',
      `platform` varchar(60) DEFAULT 'wx',
      `call_voice_url` varchar(255) DEFAULT NULL,
      `need_play` tinyint(1) DEFAULT '0' COMMENT '需要播放音频',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `queue_no` (`pickup_number`) USING BTREE,
      KEY `status` (`status`) USING BTREE,
      KEY `create_time` (`create_time`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='取餐列表';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_restaurant_take_food_sysset` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `banner` text,
      `screen_pic` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    if(!pdo_fieldexists2("ddwx_restaurant_take_food_sysset","banner_interval")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_take_food_sysset` ADD COLUMN `banner_interval` int(11) DEFAULT '3' COMMENT '轮播间隔';");
    }
    if(!pdo_fieldexists2("ddwx_admin_setapp_alipay","tmpl_take_food")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_alipay` ADD COLUMN `tmpl_take_food` varchar(50) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_wx_tmplset","tmpl_take_food")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wx_tmplset` ADD COLUMN `tmpl_take_food` varchar(60) DEFAULT NULL COMMENT '取餐通知';");
    }
}
if(getcustom('up_giveparent_prize')){
    if(!pdo_fieldexists2("ddwx_member_level","up_giveparent_prize")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `up_giveparent_prize` decimal (12,2) DEFAULT 0 COMMENT '升级脱离见点奖';");
    }
}
if(getcustom('shoporder_ranking')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shoporder_ranking_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `poolmoney` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '总奖池金额',
        `givedata` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '子奖池数据',
        `month` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '余额',
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `month`(`month`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shoporder_ranking_log_detail` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `logid` int(11) NOT NULL DEFAULT 0,
        `key` int(11) NOT NULL DEFAULT 0,
        `name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
        `ratio` float NOT NULL DEFAULT 0,
        `money` float NOT NULL DEFAULT 0,
        `num` int(11) NOT NULL DEFAULT 0,
        `display` tinyint(1) NOT NULL DEFAULT 0,
        `childmoney` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '子奖池总额',
        `mlist` longtext CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '子奖池用户数据',
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `logid`(`logid`) USING BTREE,
        INDEX `key`(`key`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shoporder_ranking_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `pool` float NOT NULL DEFAULT 0,
        `givedata` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
        `status` tinyint(1) NOT NULL DEFAULT 0,
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if(!pdo_fieldexists2("ddwx_shoporder_ranking_set","sendtype")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `sendtype` tinyint(1) NOT NULL DEFAULT 2 COMMENT '发放账户  1：余额 2：佣金（默认）';");
    }
}

if(getcustom('kecheng_free_memberlevel')){
    if(!pdo_fieldexists2("ddwx_kecheng_list","mianfei_memberlevel_open")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_list` ADD COLUMN `mianfei_memberlevel_open` tinyint(1) NOT NULL DEFAULT 0 COMMENT '免费学习等级0：关闭1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_list` ADD COLUMN `mianfei_gettj` varchar(150) DEFAULT NULL COMMENT '免费学习等级';");
    }
}
if(getcustom('lipinka_memberlevel')){
    if(!pdo_fieldexists2("ddwx_lipin","memberlevel_id")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lipin` ADD COLUMN `memberlevel_id` int(11) NOT NULL DEFAULT 0 COMMENT '礼品兑换等级';");
    }
}
if(getcustom('lipinka_kecheng')){
    if(!pdo_fieldexists2("ddwx_lipin","kecheng_ids")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lipin` ADD COLUMN `kecheng_ids` varchar(100) DEFAULT NULL COMMENT '礼品兑换知识付费课程';");
    }
}
if(getcustom('lipinka_huodong_baoming')){
    if(!pdo_fieldexists2("ddwx_lipin","prodata7")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lipin` ADD COLUMN `prodata7` varchar(200) DEFAULT NULL COMMENT '礼品兑换活动报名';");
    }
}
if(getcustom('daily_jinju')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_dailyjinju_product` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT '0',
        `bid` int(11) NOT NULL DEFAULT '0',
        `name` varchar(100) NOT NULL DEFAULT '' COMMENT '名称',
        `shortdesc` varchar(255) NOT NULL DEFAULT '' COMMENT '简介',
        `pic` varchar(255) NOT NULL DEFAULT '' COMMENT '主图片',
        `content` text COMMENT '内容',
        `voice_url` varchar(100) DEFAULT NULL COMMENT '音频链接',
        `voice_duration` varchar(10) DEFAULT NULL COMMENT '音频时长',
        `viewnum` int(11) NOT NULL DEFAULT '0' COMMENT '访问量',
        `dakanum` int(11) NOT NULL DEFAULT '0' COMMENT '打卡人数',
        `ganwunum` int(11) DEFAULT '0' COMMENT '感悟人数',
        `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0：关闭 1：开启',
        `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除 0：否 1：是',
        `ganwu_checked` tinyint(1) DEFAULT '0' COMMENT '感悟审核1需要审核0不需要',
        `daka_limit_day` tinyint(3) unsigned DEFAULT '0' COMMENT '发布后多少天可打卡0不限制',
        `createtime` int(11) unsigned NOT NULL DEFAULT '0',
        `updatetime` int(11) unsigned DEFAULT NULL,
        `tourldata` text COMMENT '扩展选择',
        `fabutime` int(11) DEFAULT NULL COMMENT '发布时间',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='每日金句列表';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_dailyjinju_ganwu_star` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `mid` int(11) DEFAULT NULL,
        `ganwu_orderid` varchar(60) DEFAULT NULL,
        `createtime` int(11) DEFAULT NULL,
        `status` tinyint(1) DEFAULT '0' COMMENT '1已标星0未标星',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `bid` (`bid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='每日金句点赞标星';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_dailyjinju_ganwu_order` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `mid` int(11) DEFAULT NULL,
        `ordernum` varchar(60) DEFAULT NULL,
        `title` text,
        `proid` int(11) DEFAULT NULL,
        `proname` varchar(100) NOT NULL DEFAULT '',
        `propic` varchar(100) DEFAULT NULL,
        `createtime` int(11) DEFAULT NULL,
        `platform` varchar(20) DEFAULT 'wx',
        `ganwu` varchar(600) DEFAULT NULL COMMENT '感悟',
        `share_num` int(11) DEFAULT '0' COMMENT '分享数量',
        `star_num` int(11) DEFAULT '0' COMMENT '标星点赞数量',
        `is_checked` tinyint(1) DEFAULT '0' COMMENT '1已审核0未什么',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `bid` (`bid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='每日金句感悟订单';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_dailyjinju_daka_order` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `mid` int(11) DEFAULT NULL,
        `ordernum` varchar(30) DEFAULT NULL,
        `title` text,
        `proid` int(11) DEFAULT NULL,
        `proname` varchar(100) NOT NULL DEFAULT '',
        `propic` varchar(100) DEFAULT NULL,
        `platform` varchar(20) DEFAULT NULL,
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `bid` (`bid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='每日金句打卡记录';");
}
if(getcustom('huodong_baoming')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_huodong_baoming_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `status` tinyint(1) DEFAULT '0',
        `autoshdays` int(11) DEFAULT '7',
        `autoclose` int(255) DEFAULT '600',
        `minminute` int(11) DEFAULT '3',
        `discount` tinyint(1) DEFAULT '0',
        `iscoupon` tinyint(1) DEFAULT '0',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_huodong_baoming_product` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
        `sellpoint` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
        `pic` varchar(255) CHARACTER SET utf8 DEFAULT '',
        `pics` varchar(1000) CHARACTER SET utf8 DEFAULT NULL,
        `sales` int(11) DEFAULT '0' COMMENT '真实销售数量',
        `start_viewnum` int(11) DEFAULT '0' COMMENT '默认访问量',
        `viewnum` int(11) DEFAULT '0' COMMENT '实际访问量',
        `start_sales` int(11) DEFAULT NULL COMMENT '默认报名数',
        `detail` longtext,
        `sell_price` float(11,2) DEFAULT '0.00',
        `givescore` int(11) DEFAULT '0' COMMENT '赠送积分',
        `is_fufei` tinyint(1) DEFAULT '0' COMMENT '是否免费0免费1付费',
        `sort` int(11) DEFAULT '0',
        `status` int(1) DEFAULT '1',
        `stock` int(11) unsigned DEFAULT '0' COMMENT '总报名人数0不限制',
        `perlimit` int(11) DEFAULT NULL COMMENT '每人单独报名限制 0不限制',
        `createtime` int(11) DEFAULT NULL,
        `guigedata` text CHARACTER SET utf8,
        `detail_text` text CHARACTER SET utf8,
        `detail_pics` text CHARACTER SET utf8,
        `gettj` varchar(255) CHARACTER SET utf8 DEFAULT '-1',
        `gettjurl` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
        `gettjtip` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
        `cid` varchar(11) CHARACTER SET utf8 DEFAULT '0' COMMENT '分类id',
        `formdata` text CHARACTER SET utf8,
        `start_time` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
        `end_time` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
        `huodong_start_time` varchar(100) CHARACTER SET utf8 DEFAULT NULL COMMENT '预约开始时间',
        `huodong_end_time` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
        `type` tinyint(1) DEFAULT '0' COMMENT '类型 0：线下活动 1：洗上活动',
        `zhubanfang_name` varchar(255) DEFAULT NULL COMMENT '主办方名称',
        `zhubanfang_tel` varchar(255) DEFAULT NULL COMMENT '主办方手机号',
        `huodong_address` varchar(255) DEFAULT NULL COMMENT '活动地址',
        `longitude` varchar(255) DEFAULT NULL COMMENT '经纬度',
        `latitude` varchar(255) DEFAULT NULL COMMENT '经纬度',
        `show_member_order` tinyint(1) DEFAULT '0' COMMENT '商品详情，显示报名会员',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `bid` (`bid`) USING BTREE,
        KEY `status` (`status`) USING BTREE,
        KEY `stock` (`stock`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='活动报名列表';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_huodong_baoming_order` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `mid` int(11) DEFAULT NULL,
        `ordernum` varchar(255) DEFAULT NULL,
        `title` text,
        `totalprice` float(11,2) DEFAULT NULL,
        `product_price` float(11,2) DEFAULT '0.00',
        `givescore` int(11) DEFAULT '0',
        `coupon_rid` int(11) DEFAULT NULL COMMENT '优惠券coupon_record的id',
        `createtime` int(11) DEFAULT NULL,
        `status` int(11) DEFAULT '0' COMMENT '0未支付;1已支付;2已发货;3已收货;4关闭;',
        `linkman` varchar(255) DEFAULT NULL,
        `tel` varchar(50) DEFAULT NULL,
        `area` varchar(255) DEFAULT NULL,
        `area2` varchar(255) DEFAULT NULL,
        `address` varchar(255) DEFAULT NULL,
        `longitude` varchar(100) DEFAULT NULL,
        `latitude` varchar(100) DEFAULT NULL,
        `message` varchar(255) DEFAULT NULL,
        `remark` varchar(255) DEFAULT NULL,
        `payorderid` int(11) DEFAULT NULL,
        `paytypeid` int(11) DEFAULT NULL COMMENT '支付',
        `paytype` varchar(50) DEFAULT NULL,
        `paynum` varchar(255) DEFAULT NULL,
        `paytime` int(11) DEFAULT NULL,
        `send_time` bigint(20) DEFAULT NULL COMMENT '发货时间',
        `collect_time` int(11) DEFAULT NULL COMMENT '收货时间',
        `hexiao_code` varchar(100) DEFAULT NULL COMMENT '唯一码 核销码',
        `hexiao_qr` varchar(255) DEFAULT NULL,
        `platform` varchar(255) DEFAULT 'wx',
        `iscomment` tinyint(1) DEFAULT '0',
        `field1` varchar(255) DEFAULT NULL,
        `field2` varchar(255) DEFAULT NULL,
        `field3` varchar(255) DEFAULT NULL,
        `field4` varchar(255) DEFAULT NULL,
        `field5` varchar(255) DEFAULT NULL,
        `delete` tinyint(1) DEFAULT '0',
        `checkmemid` int(11) DEFAULT NULL COMMENT '指定返佣用户ID',
        `fromwxvideo` tinyint(1) DEFAULT '0' COMMENT '是否是视频号过来的订单',
        `scene` int(11) DEFAULT '0' COMMENT '小程序场景',
        `coupon_money` float(11,2) DEFAULT '0.00',
        `propic` varchar(255) DEFAULT NULL,
        `proname` varchar(255) DEFAULT NULL,
        `num` int(11) DEFAULT NULL,
        `ggname` varchar(255) DEFAULT NULL,
        `proid` int(11) DEFAULT '0',
        `ggid` int(11) DEFAULT '0',
        `refund_status` tinyint(11) DEFAULT '0',
        `refund_time` int(11) DEFAULT NULL,
        `refund_reason` varchar(255) DEFAULT NULL,
        `refund_money` decimal(11,2) DEFAULT NULL,
        `refund_checkremark` varchar(255) DEFAULT NULL,
        `sysOrderNo` varchar(255) DEFAULT '' COMMENT '定制返回的订单号',
        `commission` float(11,0) DEFAULT '0' COMMENT '佣金',
        `masterName` varchar(255) DEFAULT NULL COMMENT '师傅姓名',
        `errandDistance` float(11,2) DEFAULT '0.00' COMMENT '距离',
        `platformIncome` float(11,2) DEFAULT '0.00' COMMENT '平台收入',
        `firstCategory` int(11) DEFAULT '0',
        `secondCategory` int(11) DEFAULT '0',
        `unit` varchar(255) DEFAULT NULL COMMENT '单位',
        `material_ids` varchar(255) DEFAULT '',
        `fee` varchar(255) DEFAULT '',
        `other_fee` varchar(255) DEFAULT '',
        `leveldk_money` float(11,2) DEFAULT '0.00',
        `mdid` int(5) DEFAULT '0',
        `addmoney` decimal(10,2) DEFAULT '0.00',
        `addmoneyPaycode` varchar(255) DEFAULT NULL,
        `addmoneyStatus` int(11) DEFAULT '0',
        `addmoneyPayorderid` int(11) DEFAULT '0',
        `parent1` int(11) DEFAULT NULL,
        `parent2` int(11) DEFAULT NULL,
        `parent3` int(11) DEFAULT NULL,
        `parent1commission` decimal(11,2) DEFAULT '0.00',
        `parent2commission` decimal(11,2) DEFAULT '0.00',
        `parent3commission` decimal(11,2) DEFAULT '0.00',
        `parent1score` int(11) DEFAULT '0',
        `parent2score` int(11) DEFAULT '0',
        `parent3score` int(11) DEFAULT '0',
        `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放',
        `isfenhong` int(2) DEFAULT '0',
        `cost_price` decimal(11,2) DEFAULT '0.00',
        `protype` tinyint(1) DEFAULT NULL COMMENT '1线上0线下',
        PRIMARY KEY (`id`) USING BTREE,
        UNIQUE KEY `code` (`hexiao_code`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `bid` (`bid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE,
        KEY `status` (`status`) USING BTREE,
        KEY `createtime` (`createtime`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_huodong_baoming_guige` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `proid` int(11) DEFAULT NULL,
        `name` varchar(255) DEFAULT NULL,
        `pic` varchar(255) DEFAULT NULL,
        `sell_price` decimal(11,2) DEFAULT '0.00',
        `givescore` int(11) DEFAULT '0',
        `danwei` varchar(11) DEFAULT NULL,
        `stock` int(11) unsigned DEFAULT '0',
        `procode` varchar(255) DEFAULT NULL,
        `sales` int(11) DEFAULT '0',
        `ks` varchar(255) DEFAULT NULL,
        `lvprice_data` text,
        `bid` int(11) DEFAULT '0',
        `cost_price` decimal(11,2) DEFAULT '0.00',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `proid` (`proid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_huodong_baoming_category` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `pid` int(11) DEFAULT '0',
        `name` varchar(255) DEFAULT NULL,
        `pic` varchar(255) DEFAULT NULL,
        `status` int(1) DEFAULT '1',
        `sort` int(11) DEFAULT '1',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `pid` (`pid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");    
    if(!pdo_fieldexists2("ddwx_huodong_baoming_product","mianfei_memberlevel_open")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN `mianfei_memberlevel_open` tinyint(1) NOT NULL DEFAULT 0 COMMENT '免费等级0：关闭1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN `mianfei_gettj` varchar(150) DEFAULT NULL COMMENT '免费等级';");
    }
    if(!pdo_fieldexists2("ddwx_huodong_baoming_product","fanwei")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN `fanwei` tinyint(1) NOT NULL DEFAULT 0 COMMENT '参与范围0：关闭1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN `fanwei_lng` varchar(60) DEFAULT NULL COMMENT '经纬度';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN `fanwei_lat` varchar(60) DEFAULT NULL COMMENT '经纬度';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN `fanwei_range` varchar(60) DEFAULT NULL COMMENT '范围距离';");
    }
}
if(getcustom('yx_business_miandan')){
    if(!pdo_fieldexists2("ddwx_business_sysset","miandan_tourl")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `miandan_tourl` varchar(200) DEFAULT NULL COMMENT '免单升级链接';");
    }    
    if(!pdo_fieldexists2("ddwx_business_sysset","miandan_mianfei_gettj")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `miandan_mianfei_gettj` varchar(100) DEFAULT NULL COMMENT '免单免费会员等级';");
    }    
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_miandan_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `status` tinyint(1) DEFAULT '1' COMMENT '1开启0关闭',
        `tourl` varchar(150) DEFAULT NULL COMMENT '非指定会员跳转链接',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='多商户免单设置';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_miandan_order` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `mid` int(11) DEFAULT NULL,
        `ordernum` varchar(255) DEFAULT NULL,
        `title` text,
        `proid` int(11) DEFAULT NULL,
        `proname` varchar(255) NOT NULL DEFAULT '',
        `propic` varchar(255) DEFAULT NULL,
        `num` int(11) DEFAULT '1',
        `sell_price` decimal(10,2) DEFAULT NULL,
        `totalprice` float(11,2) DEFAULT NULL,
        `product_price` float(11,2) DEFAULT '0.00',
        `createtime` int(11) DEFAULT NULL,
        `status` int(11) DEFAULT '0' COMMENT '0未核销;1已核销;',
        `remark` varchar(255) DEFAULT NULL,
        `delete` int(1) DEFAULT '0',
        `mdid` int(11) DEFAULT NULL,
        `collect_time` int(11) DEFAULT NULL COMMENT '完成时间',
        `hexiao_code` varchar(100) DEFAULT NULL,
        `hexiao_qr` varchar(255) DEFAULT NULL,
        `platform` varchar(255) DEFAULT 'wx',
        `hexiao_mdid` int(11) NOT NULL DEFAULT '0' COMMENT '核销门店id',
        `hexiao_mid` int(11) NOT NULL DEFAULT '0' COMMENT '核销用户id',
        `hexiao_uid` int(11) NOT NULL DEFAULT '0' COMMENT '核销账号id',
        PRIMARY KEY (`id`) USING BTREE,
        UNIQUE KEY `hexiao_code` (`hexiao_code`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `bid` (`bid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE,
        KEY `status` (`status`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='多商户免单记录';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_miandan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT '0',
        `bid` int(11) NOT NULL DEFAULT '0',
        `name` varchar(100) NOT NULL DEFAULT '' COMMENT '名称',
        `shortdesc` varchar(255) NOT NULL DEFAULT '' COMMENT '简介',
        `pic` varchar(255) NOT NULL DEFAULT '' COMMENT '主图片',
        `pics` varchar(1000) DEFAULT NULL,
        `content` text,
        `detail` text COMMENT '详情',
        `sell_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '价格',
        `sales` int(11) NOT NULL DEFAULT '0' COMMENT '销量',
        `limit_num` int(11) NOT NULL DEFAULT '0' COMMENT '限领数量',
        `mianfei_gettj` varchar(100) DEFAULT NULL,
        `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0：关闭 1：开启',
        `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除 0：否 1：是',
        `createtime` int(11) unsigned NOT NULL DEFAULT '0',
        `updatetime` int(11) unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='多商户免单';");
      if(!pdo_fieldexists2("ddwx_business_miandan","ischecked")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_miandan` ADD COLUMN `ischecked` tinyint(1) DEFAULT '0' COMMENT '免单审核状态0未审核1已审核2已驳回';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_miandan` ADD COLUMN `check_reason` varchar(100) DEFAULT NULL COMMENT '免单审核备注';");
    }  
}
if(getcustom('member_huoyuedu_notice')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_huoyuedu_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `no_buy_day` int(10) DEFAULT NULL COMMENT '未消费天数',
        `money_qiankuan` decimal(10,2) DEFAULT '0.00' COMMENT '余额欠款低于金额展示',
        `money_min` decimal(10,2) DEFAULT NULL COMMENT '余额低于金额展示',
        `home_show` tinyint(1) DEFAULT '0' COMMENT '首页是否展示1展示0不展示',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('shop_paiming_fenhong')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_paiming_fenhong_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `is_open` tinyint(1) DEFAULT '0' COMMENT '开关1开',
        `sale_ratio` decimal(5,2) DEFAULT NULL COMMENT '可发放的销售额百分比',
        `three_point_ratio` decimal(5,2) DEFAULT NULL COMMENT '3的整数名额占比',
        `point_amount` decimal(10,2) DEFAULT NULL COMMENT '营业额每份的金额',
        `over_point_amount` decimal(10,2) DEFAULT NULL COMMENT '消费达到分红名额金额',
        `max_point_amount` decimal(10,2) DEFAULT NULL COMMENT '分红名额累计最大金额',
        `last_amount` decimal(11,2) DEFAULT NULL COMMENT '上期结余',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='商城排名分红设置';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_paiming_fenhong_sale` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `totalprice` decimal(11,2) DEFAULT NULL COMMENT '当日营业额',
        `fenhong_amount` decimal(11,2) DEFAULT '0.00' COMMENT '分红金额',
        `three_fenhong_amount` decimal(11,2) DEFAULT NULL COMMENT '分给3的倍数位置靠前的金额',
        `other_fenhong_amount` decimal(11,2) DEFAULT NULL COMMENT '其他剩余分红',
        `day` varchar(20) DEFAULT NULL COMMENT '统计日期年月日',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='排名分红每日销售额分配';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_paiming_fenhong_record_diff` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `amount` decimal(8,2) DEFAULT NULL COMMENT '差值金额',
        `record_ids` varchar(50) DEFAULT NULL COMMENT '对应点位id',
        `date` varchar(10) DEFAULT NULL COMMENT '日期',
        `status` tinyint(1) DEFAULT '0' COMMENT '1已完成0未完成',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='排名分红补差额';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_paiming_fenhong_record` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `max_amount` decimal(8,2) DEFAULT NULL COMMENT '最大金额',
        `already_amount` decimal(8,2) DEFAULT '0.00' COMMENT '已返金额',
        `status` tinyint(1) DEFAULT '0' COMMENT '1已完成0未完成',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`),
        KEY `mid` (`mid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='排名分红点位记录';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_paiming_fenhong_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `money` decimal(11,2) DEFAULT '0.00',
        `after` decimal(11,2) DEFAULT '0.00',
        `createtime` int(11) DEFAULT NULL,
        `remark` varchar(60) CHARACTER SET utf8mb4 DEFAULT NULL,
        `type` tinyint(1) DEFAULT '0' COMMENT '类型0分红1转出',
        `orderid` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
      if(!pdo_fieldexists2("ddwx_member","paiming_fenhong_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `paiming_fenhong_money` decimal(10,2) DEFAULT '0' COMMENT '排名分红金额';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `paiming_fenhong_buy_money` decimal(10,2) DEFAULT '0' COMMENT '当前剩余购买金额';");
    }
}
if(getcustom('pos_score_tongji')){

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_posscore_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `score_ratio` decimal(5,2) DEFAULT NULL COMMENT '刷卡金额的百分比到积分',
        `desc` varchar(800) DEFAULT NULL COMMENT '操作指引',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='pos积分奖励设置';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_posscore_possn` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `possn` varchar(100) DEFAULT NULL COMMENT '设备码',
        `is_bind` tinyint(3) DEFAULT '1' COMMENT '1绑定0解绑',
        `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
        `updatetime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`),
        KEY `mid` (`mid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='积分奖励设备列表';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_posscore_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `score` int(11) DEFAULT NULL,
        `request_type` varchar(100) DEFAULT NULL COMMENT '接口类型名称',
        `channel_no` varchar(100) DEFAULT NULL COMMENT '渠道编号',
        `back_code` varchar(100) DEFAULT NULL COMMENT '返回应答码',
        `request_no` varchar(100) DEFAULT NULL COMMENT '流水号',
        `merchant_no` varchar(100) DEFAULT NULL COMMENT '商户编号',
        `mer_name` varchar(100) DEFAULT NULL COMMENT '商户名称',
        `trade_order` varchar(100) DEFAULT NULL COMMENT '订单号',
        `serid` varchar(100) DEFAULT NULL COMMENT '终端号',
        `pos_sn` varchar(100) DEFAULT NULL COMMENT '设备序列号',
        `trade_state` tinyint(3) DEFAULT NULL COMMENT '交易状态1成功',
        `trade_type` varchar(60) DEFAULT NULL COMMENT '交易类型',
        `trade_total` varchar(100) DEFAULT NULL COMMENT '交易金额',
        `trade_time` datetime DEFAULT NULL COMMENT '交易时间',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`),
        KEY `mid` (`mid`),
        KEY `request_no` (`request_no`),
        KEY `trade_order` (`trade_order`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    if(!pdo_fieldexists2("ddwx_posscore_set","secret")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_posscore_set` ADD COLUMN `secret` varchar(60) DEFAULT NULL COMMENT '秘钥';");
    }
    if(!pdo_fieldexists2("ddwx_posscore_set","is_open")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_posscore_set` ADD COLUMN `is_open` tinyint(1) DEFAULT '1' COMMENT '积分奖励状态0关闭1开启';");
    }
    if(!pdo_fieldexists2("ddwx_member","pos_score_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `pos_score_ratio` decimal(5,2) DEFAULT '0.3' COMMENT 'pos积分奖励比率';");
    }
    if(!pdo_fieldexists2("ddwx_posscore_possn","realname")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_posscore_possn` ADD COLUMN `realname` varchar(30) DEFAULT NULL COMMENT '姓名';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_posscore_possn` ADD COLUMN `phone` varchar(30) DEFAULT NULL COMMENT '手机号';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_posscore_possn` ADD COLUMN `status` tinyint(1) DEFAULT '0' COMMENT '审核状态0审核中1已审核2已驳回';");
    }
}

if(getcustom('choujiang_qrcode')){
    if(!pdo_fieldexists2("ddwx_choujiang_sharelog","choujiang_qr_times")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_choujiang_sharelog` ADD COLUMN `choujiang_qr_times` int(11) DEFAULT 0 COMMENT '通过扫抽奖码获取抽奖机会';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_choujiang_qrcode` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `headimg` varchar(255) DEFAULT NULL,
        `nickname` varchar(255) DEFAULT NULL,
        `choujiang_id` int(11) DEFAULT NULL COMMENT '抽奖活动id',
        `code` varchar(255) DEFAULT NULL COMMENT '识别code',
        `choujiang_qr` varchar(255) DEFAULT NULL COMMENT '抽奖二维码',
        `status` tinyint(1) DEFAULT '0' COMMENT '状态1已使用0未使用',
        `usetime` int(11) DEFAULT NULL COMMENT '使用时间',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `aid` (`aid`,`code`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='抽奖二维码扫码增加次数';");
}
if(getcustom('commission_duipeng_score_withdraw')){
    if(!pdo_fieldexists2("ddwx_shop_guige","give_withdraw_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `give_withdraw_score` int(11) DEFAULT 0 COMMENT '自购赠送提现积分';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `give_parent1_withdraw_score` int(11) DEFAULT 0 COMMENT '赠送直推提现积分';");
    }
    if(!pdo_fieldexists2("ddwx_member","commission_withdraw_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `commission_withdraw_score` int(11) DEFAULT 0 COMMENT '佣金对碰提现积分';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set", "comwithdraw_duipeng_score_bili")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`
        ADD COLUMN `comwithdraw_duipeng_score_bili`  tinyint(1)  DEFAULT 0 COMMENT '提现积分与佣金对碰比例',
        ADD COLUMN `comwithdraw_integer_type`  tinyint(1) DEFAULT 0  COMMENT '提现佣金1个，2十，3百，4千 整数提现选择';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","give_withdraw_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_withdraw_score` int(11) DEFAULT 0 COMMENT '自购赠送提现积分';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_parent1_withdraw_score` int(11) DEFAULT 0 COMMENT '赠送直推提现积分';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_parent1` int(11) DEFAULT 0 COMMENT '直推人id';");
    }
    if(!pdo_fieldexists2("ddwx_member_commission_withdrawlog", "need_commission_withdraw_score")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` ADD COLUMN `need_commission_withdraw_score`  int(11) NULL DEFAULT 0 COMMENT '佣金对碰提现积分';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_commission_withdraw_scorelog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `mid` varchar(100) DEFAULT NULL,
        `score` int(11) DEFAULT '0',
        `after` int(11) DEFAULT '0',
        `createtime` int(11) DEFAULT NULL,
        `remark` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
        `from_mid` int(11) DEFAULT '0',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='佣金对碰积分提现记录';");
}
if(getcustom('shop_add_stock')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_stock_order_goods` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT NULL,
        `ordernum` varchar(30) DEFAULT NULL,
        `proid` int(11) DEFAULT NULL,
        `ggid` int(11) DEFAULT NULL,
        `stock` int(11) DEFAULT '0' COMMENT '库存',
        `afterstock` int(11) DEFAULT '0' COMMENT '改变后的库存',
        `uid` int(11) DEFAULT NULL,
        `uname` varchar(60) DEFAULT NULL,
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `aid` (`aid`),
        KEY `bid` (`bid`),
        KEY `ggid` (`ggid`),
        KEY `proid` (`proid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('shop_stock_warning_notice')){
    if(!pdo_fieldexists2("ddwx_wx_tmplset","tmpl_stockwarning")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wx_tmplset` ADD COLUMN `tmpl_stockwarning` varchar(255) DEFAULT NULL COMMENT '库存不足提醒';");
   }
    if(!pdo_fieldexists2("ddwx_mp_tmplset_new","tmpl_stockwarning")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_stockwarning` varchar(255) DEFAULT NULL COMMENT '库存不足提醒';");
   }
    if(!pdo_fieldexists2("ddwx_mp_tmplset","tmpl_stockwarning")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset` ADD COLUMN `tmpl_stockwarning` varchar(255) DEFAULT NULL COMMENT '库存不足提醒';");
   }
    if(!pdo_fieldexists2("ddwx_admin_user","tmpl_stockwarning")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_user` ADD COLUMN `tmpl_stockwarning` tinyint(1) DEFAULT '1' COMMENT '库存不足提醒';");
   }
    if(!pdo_fieldexists2("ddwx_shop_sysset","shop_stock_warning_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `shop_stock_warning_num` int(11) DEFAULT 1 COMMENT '库存不足预警值';");
   }
}

if(getcustom('yx_collage_jieti')){
    if(!pdo_fieldexists2("ddwx_collage_product","jieti_data")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `jieti_data` text CHARACTER SET utf8 COMMENT '阶梯拼团数据';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `collage_type` tinyint(1) DEFAULT '0' COMMENT '0普通  1数量';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `starttime` int(11) DEFAULT NULL;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `endtime` int(11) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_collage_order_team","collage_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order_team` ADD COLUMN `collage_type` tinyint(1) DEFAULT '0' COMMENT '0普通团 1时间无限团';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order_team` ADD COLUMN `endtime` int(11) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_collage_order_team","endtime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order_team` ADD COLUMN `endtime` int(11) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_collage_product","view_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `view_num` int(11) DEFAULT '0';");
    }

    if(!pdo_fieldexists2("ddwx_collage_product","share_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` 
            ADD COLUMN `share_num` int(11) DEFAULT '0',
            ADD COLUMN `xn_share_num` int(11) DEFAULT '0' COMMENT '虚拟分享数',
            ADD COLUMN `xn_view_num` int(11) DEFAULT '0' COMMENT '虚拟浏览数';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_collage_view_history` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) DEFAULT '0',
          `bid` int(11) DEFAULT '0',
          `proid` int(11) DEFAULT '0',
          `mid` int(11) DEFAULT '0',
          `createtime` int(11) DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");                                 
}
if(getcustom('restaurant_shop_pindan')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_sysset","table_payafter_autoclosetime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` ADD COLUMN `table_payafter_autoclosetime` int(11) DEFAULT '10';");
    }
}
if(getcustom('product_bonus_pool')){
    if(!pdo_fieldexists2("ddwx_admin","bonus_pool_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin` ADD COLUMN `bonus_pool_status` tinyint(1) DEFAULT '0' COMMENT '奖金池状态';");
    }
}
if(getcustom('member_overdraft_money') && getcustom('restaurant')){
    if(!pdo_fieldexists2("ddwx_restaurant_admin_set","business_cashdesk_guazhang")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_admin_set` ADD COLUMN `business_cashdesk_guazhang` tinyint(1) DEFAULT '1' COMMENT '多商户收银台 挂账   0:关闭 1：开启';");
    }
}
if(getcustom('member_overdraft_money') && getcustom('restaurant_shop_cashdesk')){
    if(!pdo_fieldexists2("ddwx_restaurant_cashdesk","guazhangpay")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cashdesk` ADD COLUMN `guazhangpay` tinyint(1) DEFAULT '1' COMMENT '挂账收款';");
    }
}
if(getcustom('shd_print') ||  getcustom('shop_shd_print2')){
    if(!pdo_fieldexists2("ddwx_shop_sysset","printmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
            ADD COLUMN `printmoney` tinyint(1) NOT NULL DEFAULT 1 COMMENT '批量打印金额：0 隐藏 1 显示',
            ADD COLUMN `printlian` tinyint(1) NOT NULL DEFAULT 1 COMMENT '批量打印第几联：0 隐藏 1 显示';");
    }
    if(!pdo_fieldexists2("ddwx_business","printmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `printmoney` tinyint(1) NOT NULL DEFAULT 1 COMMENT '批量打印金额：0 隐藏 1 显示',
            ADD COLUMN `printlian` tinyint(1) NOT NULL DEFAULT 1 COMMENT '批量打印第几联：0 隐藏 1 显示';");
    }
}
if(getcustom('restaurant_cashdesk_link_table')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order","link_tableid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `link_tableid` varchar(50) DEFAULT NULL COMMENT '关联桌台，存在于买单桌台';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order_goods","tableid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` ADD COLUMN `tableid` int(11) DEFAULT '0' COMMENT '区别关联桌台中，所属哪个桌台';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_table","link_tableid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_table` ADD COLUMN `link_tableid` int(11) DEFAULT '0' COMMENT '关联的桌台ID';");
    }
}
if(getcustom('membercard_custom')){
    if(!pdo_fieldexists2("ddwx_membercard","isdiycode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_membercard` 
			ADD COLUMN `givemoney` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `givescore` decimal(11,2) DEFAULT '0.00',
			ADD COLUMN `give_coupon` tinyint(1) DEFAULT '0',
			ADD COLUMN `coupon_ids` varchar(255) DEFAULT NULL,
			ADD COLUMN `parent_givemoney` decimal(11,2) DEFAULT '0.00',
			ADD COLUMN `parent_givescore` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `parent_give_coupon` tinyint(1) DEFAULT '0',
			ADD COLUMN `parent_coupon_ids` varchar(255) DEFAULT NULL,
            ADD COLUMN `isdiycode` tinyint(1) DEFAULT '0' COMMENT '是否开启自定义卡号',
            ADD COLUMN `rule` varchar(1000) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_membercard_record","sharecode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_membercard_record` 
            ADD COLUMN `sharecode` varchar(255) DEFAULT NULL COMMENT '分享码',
            ADD COLUMN `rule` varchar(1000) DEFAULT NULL,
			ADD COLUMN `totalscore` decimal(11,2) DEFAULT '0.00',
			ADD COLUMN `totalmoney` decimal(11,2) DEFAULT '0.00',
			ADD COLUMN `totalcoupon` int(11) DEFAULT '0',
			ADD COLUMN `membership_number` varchar(255) DEFAULT '';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_membercard_sharelog` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `aid` int(11) DEFAULT NULL,
	  `bid` int(11) unsigned DEFAULT '0',
	  `mid` int(11) DEFAULT NULL,
	  `record_id` int(11) DEFAULT NULL COMMENT '申请开卡id',
	  `createtime` int(11) DEFAULT NULL,
	  `status` tinyint(1) DEFAULT '0' COMMENT '0',
	  `title` varchar(255) DEFAULT NULL,
	  `platform` varchar(255) DEFAULT NULL,
	  `givemoney` decimal(10,2) DEFAULT '0.00',
	  `givescore` decimal(10,2) DEFAULT '0.00',
	  `give_couponids` varchar(100) DEFAULT '0',
	  `pid` int(11) DEFAULT '0',
	  `parent_givemoney` decimal(10,2) DEFAULT '0.00',
	  `parent_givescore` decimal(10,2) DEFAULT '0.00',
	  `parent_couponids` varchar(255) DEFAULT NULL,
	  `card_id` varchar(255) DEFAULT NULL,
	  `headimg` varchar(255) DEFAULT NULL,
	  `nickname` varchar(255) DEFAULT NULL,
	  PRIMARY KEY (`id`) USING BTREE,
	  KEY `aid` (`aid`) USING BTREE,
	  KEY `record_id` (`record_id`) USING BTREE,
	  KEY `status` (`status`) USING BTREE,
	  KEY `pid` (`pid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

}

if(getcustom('mendian_no_select')) {
    if (!pdo_fieldexists2("ddwx_hexiao_order", "proids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hexiao_order` ADD COLUMN `proids`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '产品id';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order", "mdids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `mdids`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '可核销的门店id集合';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order", "hx_mdids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `hx_mdids`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '核销门店id集合';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "is_hx")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `is_hx`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已核销 0否 1是';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "hx_mdid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `hx_mdid`  int(11) NOT NULL DEFAULT 0 COMMENT '核销门店id';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "mdids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `mdids`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '可核销的门店id集合';");
    }
}
if(getcustom('coupon_view_range')){
    if(!pdo_fieldexists2("ddwx_coupon","view_range")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` ADD COLUMN `view_range` tinyint(1) DEFAULT '0' COMMENT '查看范围 0：默认公开 1：仅自己可见';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` ADD COLUMN `view_uid` int(11) DEFAULT '0' COMMENT '仅自己可见的uid';");
    }
}
if(getcustom('level_teamfenhong')){
    if(!pdo_fieldexists2("ddwx_shop_product","level_teamfenhongset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `level_teamfenhongset` tinyint(1) NOT NULL DEFAULT 0 COMMENT '等级团队分红设置 -1:不参与 0：按照会员等级 单独设置',
            ADD COLUMN `levelteamfenhongs` text NULL COMMENT '等级团队分红设数据';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `level_teamfenhongbl_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '等级团队分红类型' ;");
    }
}
if(getcustom('shop_category_page')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_category_page` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) DEFAULT NULL,
      `aid` int(11) DEFAULT NULL,
      `cids` varchar(255) DEFAULT NULL COMMENT '分类id逗号隔开',
      `sort` int(11) DEFAULT '0',
      `createtime` int(1) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='商品分类页';");
}
if(getcustom('level_teamfenhong')){
    if(!pdo_fieldexists2("ddwx_member_level","level_surpass")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `level_surpass` tinyint(1) NOT NULL DEFAULT 0 COMMENT '直属下级等级超越此等级 0:否 1：是';");
    }
    if(!pdo_fieldexists2("ddwx_member_level","level_jicha")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `level_jicha` tinyint(1) NOT NULL DEFAULT 0 COMMENT '等级团队分红级差 -1：关闭 0：跟随系统 1：开启';");
    }
}

if(getcustom('sign_pay_bonus')){
    if(!pdo_fieldexists2("ddwx_signset","ispay")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` 
			ADD COLUMN `ispay` tinyint(1) DEFAULT '0' COMMENT '签到是否开启支付',
            ADD COLUMN `payprice` decimal(10,2) DEFAULT '0.00' COMMENT '支付金额',
			ADD COLUMN `commission_rate` decimal(10,2) DEFAULT '0.00' COMMENT '抽佣比例',
			ADD COLUMN `min_money` decimal(10,2) DEFAULT '0.00' COMMENT '开奖最低金额',
			ADD COLUMN `moneytype` tinyint(1) DEFAULT '1' COMMENT '开奖金额到哪里';");
    }
   if(!pdo_fieldexists2("ddwx_signset","isshowbonus")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` 
            ADD COLUMN `isshowbonus` tinyint(1) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_sign_record","orderid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_sign_record` 
            ADD COLUMN `orderid` int(11) DEFAULT '0',
            ADD COLUMN `payprice` decimal(10,2) DEFAULT NULL,
			ADD COLUMN `bonus_price` decimal(10,2) DEFAULT '0.00';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_sign_order` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `mid` int(11) DEFAULT NULL,
		  `ordernum` varchar(100) DEFAULT NULL,
		  `createtime` int(11) DEFAULT NULL,
		  `status` tinyint(1) DEFAULT '0' COMMENT '0待接单 1已支付',
		  `payorderid` int(11) DEFAULT NULL,
		  `paytypeid` int(11) DEFAULT NULL,
		  `paytype` varchar(50) DEFAULT NULL,
		  `paynum` varchar(100) DEFAULT NULL,
		  `paytime` int(11) DEFAULT NULL,
		  `title` varchar(255) DEFAULT NULL,
		  `platform` varchar(255) DEFAULT NULL,
		  `price` decimal(10,2) DEFAULT NULL,
		  `refund_status` int(2) DEFAULT '0',
		  `refund_money` float(11,2) DEFAULT '0.00',
		  `refund_time` int(11) DEFAULT '0',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `ordernum` (`ordernum`) USING BTREE,
		  KEY `status` (`status`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

	
	\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_sign_bonus` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `totalprice` decimal(10,2) DEFAULT '0.00' COMMENT '奖金池金额',
		  `status` tinyint(1) DEFAULT '0' COMMENT '0待瓜分 1已瓜分',
		  `date` date DEFAULT NULL,
		  `givetime` int(11) DEFAULT NULL,
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `status` (`status`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

	\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_sign_bonus_moneylog` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `mid` int(11) DEFAULT NULL,
		  `money` decimal(11,2) DEFAULT '0.00',
		  `after` decimal(11,2) DEFAULT '0.00',
		  `createtime` int(11) DEFAULT '0',
		  `remark` varchar(255) DEFAULT NULL,
		  `from_mid` int(11) DEFAULT '0',
		  `paytype` varchar(50) DEFAULT NULL,
		  `bonusid` int(11) DEFAULT '0',
		  `recordid` int(11) DEFAULT '0',
	   PRIMARY KEY (`id`) USING BTREE,
	   KEY `aid` (`aid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

	\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_sign_kaijianglog` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `mid` int(11) DEFAULT NULL,
		  `money` decimal(11,2) DEFAULT '0.00',
		  `createtime` int(11) DEFAULT '0',
		  `remark` varchar(255) DEFAULT NULL,
		  `bonusid` int(11) DEFAULT '0',
		  `date` date DEFAULT '0000-00-00',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

}

if(getcustom('shoporder_ranking')){
    if(!pdo_fieldexists2("ddwx_shoporder_ranking_set","name")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` 
            ADD COLUMN `name` varchar(255) NOT NULL  DEFAULT '消费排行榜' COMMENT '活动名称' AFTER `id`,
            ADD COLUMN `pic` varchar(255) NOT NULL DEFAULT '' COMMENT '活动名称' AFTER `name`;");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `rankingset` tinyint(1) NOT NULL DEFAULT 0 COMMENT '消费排行榜 -1：不参与 0:按照系统设置 1：单独设置',
            ADD COLUMN `ranking_radio` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '消费排行榜分红比例' AFTER `rankingset`,
            ADD COLUMN `ranking_money` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '消费排行榜分红金额' AFTER `ranking_radio`;");
    }
}
if(getcustom('team_yeji_ranking')){
    if(!pdo_fieldexists2("ddwx_admin_set","teamyj_rank_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `teamyj_rank_status` tinyint(1) DEFAULT '0',
            ADD COLUMN `teamyj_rank_people` int(11) DEFAULT '0';");
    }
}
if(getcustom('member_create_child_order')){
    if(!pdo_fieldexists2("ddwx_payorder","pmid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` ADD COLUMN `pmid` int(11) DEFAULT '0' COMMENT '下单人mid';");
    }
    if(!pdo_fieldexists2("ddwx_member_level","create_child_order_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `create_child_order_status` tinyint(1) DEFAULT '0' COMMENT '待下级下单';");
    }
}

if(getcustom('erp_wangdiantong')){
    if(!pdo_fieldexists2("ddwx_admin","wdt_status")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin` ADD COLUMN `wdt_status` tinyint(1) NULL DEFAULT 0;");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","wdt_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `wdt_status` tinyint(1) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_shop_guige","wdt_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `wdt_status` tinyint(1) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","wdt_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
        ADD COLUMN `wdt_status` tinyint(1) DEFAULT '0',
        ADD COLUMN `wdt_paidmoney` decimal (11,2) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods","wdt_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `wdt_status` tinyint(1) DEFAULT '0';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_wdt_sysset` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `name` varchar(32) DEFAULT '',
      `createtime` int(11) DEFAULT NULL,
      `sid` varchar(64) DEFAULT '',
      `appkey` varchar(128) DEFAULT NULL,
      `appsecret` varchar(255) DEFAULT NULL,
      `istest` tinyint(1) DEFAULT '0',
      `shop_no` varchar(32) DEFAULT '',
      `status` tinyint(1) DEFAULT '0',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_order_wdt_push` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tid` varchar(128) DEFAULT '',
      `trade_status` int(4) DEFAULT '30',
      `delivery_term` tinyint(1) DEFAULT '1' COMMENT '款到发货',
      `pay_status` int(4) DEFAULT '0',
      `trade_time` datetime DEFAULT NULL,
      `pay_time` datetime DEFAULT NULL,
      `buyer_nick` varchar(64) DEFAULT '',
      `receiver_province` varchar(32) DEFAULT '',
      `receiver_city` varchar(32) DEFAULT '',
      `receiver_district` varchar(32) DEFAULT '',
      `receiver_mobile` varchar(32) DEFAULT '',
      `receiver_name` varchar(32) DEFAULT '',
      `receiver_address` varchar(128) DEFAULT '',
      `post_amount` decimal(10,2) DEFAULT NULL,
      `paid` decimal(10,2) DEFAULT NULL,
      `cod_amount` decimal(10,2) DEFAULT NULL,
      `ext_cod_fee` varchar(255) DEFAULT NULL,
      `other_amount` varchar(255) DEFAULT NULL,
      `order_list` text,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      `orderid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if(!pdo_fieldexists2("ddwx_shop_refund_order","wdt_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order` 
        ADD COLUMN `wdt_status` tinyint(1) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_shop_refund_order_goods","wdt_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order_goods` ADD COLUMN `wdt_status` tinyint(1) DEFAULT '0';");
    }
}
if(getcustom('business_agent_jicha_pj')){
    if(!pdo_fieldexists2("ddwx_member_level","business_pj_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `business_pj_ratio` decimal(12,2) DEFAULT '0.00' COMMENT '平级商家分成(%)';");
    }
    if(!pdo_fieldexists2("ddwx_member_level","business_jicha_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `business_jicha_status` tinyint(1) DEFAULT '0' COMMENT '商家分成 开启级差';");
    }
}
if(getcustom('pay_money_combine')){
    if(!pdo_fieldexists2("ddwx_admin_set","iscombine")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `iscombine` tinyint(1) NOT NULL DEFAULT 0 COMMENT '余额组合支付 0：关闭 1：开启' ,
            ADD COLUMN `combine_money` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '组合余额支付部分',
            ADD COLUMN `combine_alipay` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合支付宝支付部分';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order` 
            ADD COLUMN `refundcombine` int(11) NOT NULL DEFAULT 0 COMMENT '组合支付是否退回0：未退回 1：退回全部 其他数字只退回此部分',
            ADD COLUMN `refund_combine_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合余额支付部分',
            ADD COLUMN `refund_combine_wxpay` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合微信支付部分',
            ADD COLUMN `refund_combine_alipay` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合支付宝支付部分';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `combine_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合余额支付部分' ,
            ADD COLUMN `combine_wxpay` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合微信支付部分' ,
            ADD COLUMN `combine_alipay` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合支付宝支付部分' ,
            ADD COLUMN `refund_combine_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合余额支付部分',
            ADD COLUMN `refund_combine_wxpay` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合微信支付部分',
            ADD COLUMN `refund_combine_alipay` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '组合支付宝支付部分';");
    }
}
if(getcustom('article_bind_area')){
    if(!pdo_fieldexists2("ddwx_article","province")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_article` 
            ADD COLUMN `province` varchar(64) NULL DEFAULT '',
            ADD COLUMN `city` varchar(64) NULL DEFAULT '',
            ADD COLUMN `district` varchar(128) NULL DEFAULT '';");
    }
}
if(getcustom('choujiang_time')) {
    if (!pdo_fieldexists2("ddwx_dscj_record", "updatetime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_dscj_record`
    ADD COLUMN `updatetime` int(11) DEFAULT NULL,
    MODIFY COLUMN `jx`  int(4) NULL DEFAULT NULL COMMENT '获得的奖项';");
    }
}
if(!pdo_fieldexists2("ddwx_choujiang_record","updatetime")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_choujiang_record`
    ADD COLUMN `updatetime` int(11) DEFAULT NULL,
    MODIFY COLUMN `jx`  int(4) NULL DEFAULT NULL COMMENT '获得的奖项';");
}

if(getcustom('teamfenhong_pingji_yueji_bonus')){
    if(!pdo_fieldexists2("ddwx_admin_set","teamfenhong_pingji_yueji_bonus")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `teamfenhong_pingji_yueji_bonus`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '越级平级奖，1开启后越级拿平级奖';");
    }
}
if(getcustom('teamfenhong_money_product')){
    if(!pdo_fieldexists2("ddwx_member_level","teamfenhong_money_product")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `teamfenhong_money_product`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '团队分红每单分红金额参与产品单独设置 0不参与 1参与';");
    }
}
if(getcustom('member_commission_max')){
    if(!pdo_fieldexists2("ddwx_admin_set","member_commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `member_commission_max`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否开启佣金上限 0不开启 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_member","commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `commission_max`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '佣金上限';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_commissionmax_log` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NULL DEFAULT NULL ,
    `mid`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
    `value`  int(11) NULL DEFAULT 0 ,
    `after`  int(11) NULL DEFAULT NULL ,
    `createtime`  int(11) NULL DEFAULT NULL ,
    `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
    `channel`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '变动渠道' ,
    `orderid`  int(11) NULL DEFAULT NULL ,
    PRIMARY KEY (`id`),
    INDEX `aid` (`aid`) USING BTREE ,
    INDEX `mid` (`mid`) USING BTREE 
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic;");

    if(!pdo_fieldexists2("ddwx_shop_guige","give_commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `give_commission_max`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '购买产品赠送佣金上限';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","give_commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_commission_max`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送佣金上限数量';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","give_commission_max2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_commission_max2`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送佣金上限数量';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","give_commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `give_commission_max`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '购买产品赠送佣金上限';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","givecommax_time")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `givecommax_time`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '赠送佣金上限时间 0收货后赠送 1支付后赠送';");
    }
    if(!pdo_fieldexists2("ddwx_member","commission_max_self")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `commission_max_self`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '佣金上限个人部分';");
    }
    if(!pdo_fieldexists2("ddwx_member","commission_max_edit")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `commission_max_edit`  tinyint(1) NULL DEFAULT 0;");
        \think\facade\Db::execute("update ddwx_member set commission_max= CASE WHEN (commission_max-totalcommission)<=0 THEN 0 ELSE (commission_max-totalcommission) END ,commission_max_self=commission_max");
    }
}
if(getcustom('member_commission_max') || getcustom('add_commission_max')){
    if(!pdo_fieldexists2("ddwx_member","commission_max_plate")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `commission_max_plate`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '佣金上限平台部分';");
    }
    if(!pdo_fieldexists2("ddwx_member","commission_max_self")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `commission_max_self`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '佣金上限个人部分';");
    }
    if(!pdo_fieldexists2("ddwx_member_commissionmax_log","in_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commissionmax_log` ADD COLUMN `in_type`  tinyint(1) NULL DEFAULT 0 COMMENT '来源 0系统发放 1后台发放';");
    }


    if(!pdo_fieldexists2("ddwx_admin_set","commission_max_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `commission_max_type`  tinyint(1) NULL DEFAULT 0 COMMENT '让利到佣金上限计算方式 0按利润 1按订单金额';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","commission_max_time")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `commission_max_time`  tinyint(1) NULL DEFAULT 0 COMMENT '让利到佣金上限发放时间 0收货完成 1支付完成';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","commission_max_xf")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `commission_max_xf`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '消费赠送佣金限额';");
    }
    if(!pdo_fieldexists2("ddwx_business","commission_max_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `commission_max_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '让利到佣金上限总比例';");
    }
    if(!pdo_fieldexists2("ddwx_business","member_commission_max_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `member_commission_max_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '让利佣金上限到会员比例';");
    }
    if(!pdo_fieldexists2("ddwx_business","business_commission_max_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `business_commission_max_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '让利佣金上限到商家比例';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","is_commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `is_commission_max`  tinyint(1) NULL DEFAULT 0 COMMENT '是否已发放佣金上限 0否 1是';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods","commission_max_total")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `commission_max_total`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '发放佣金上限总数量';");
    }
    if(!pdo_fieldexists2("ddwx_maidan_order","is_commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `is_commission_max`  tinyint(1) NULL DEFAULT 0 COMMENT '是否已发放佣金上限 0否 1是';");
    }
    if(!pdo_fieldexists2("ddwx_maidan_order","commission_max_total")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `commission_max_total`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '发放佣金上限总数量';");
    }
    if(!pdo_fieldexists2("ddwx_business","product_commission_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `product_commission_max`   tinyint(1) NULL DEFAULT 0 COMMENT '商品独立设置佣金上限 0关闭 1开启';");
    }
}

if(getcustom('yx_queue_free_other_mode')){
    if(!pdo_fieldexists2("ddwx_queue_free_set","mode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN `mode` tinyint(1) DEFAULT '0',
            ADD COLUMN `pj_min_money` decimal(10,2) DEFAULT '0.00' COMMENT '平均最低金额',
            ADD COLUMN `new_order_ratio` decimal(5,2) DEFAULT '0.00' COMMENT '金额分配新单占比',
            ADD COLUMN `old_order_ratio` decimal(5,2) DEFAULT '0.00' COMMENT '金额分配最早单占比',
            ADD COLUMN `pjmoney_pj_ratio` decimal(5,2) DEFAULT NULL COMMENT '平均+金额分配的 平均占比',
            ADD COLUMN `pjmoney_pj_min_money` decimal(10,2) DEFAULT '0.00' COMMENT '平均+金额分配的 平均最低金额',
            ADD COLUMN `pjmoney_money_ratio` decimal(5,2) DEFAULT '0.00' COMMENT '平均+金额分配的 金额占比',
            ADD COLUMN `pjmoney_new_order_ratio` decimal(5,2) DEFAULT '0.00' COMMENT '平均+金额分配的 金额的最新单',
            ADD COLUMN `pjmoney_old_order_ratio` decimal(5,2) DEFAULT '0.00' COMMENT '平均+金额分配的 金额的最早单',
            ADD COLUMN `pjfixed_fixed_ratio` decimal(5,2) DEFAULT '0.00' COMMENT '平均+固定分配的 固定比例',
            ADD COLUMN `pjfixed_pj_min_money` decimal(10,2) DEFAULT '0.00' COMMENT '平均+固定分配的  平均最小金额';
        ");
    }
    if(!pdo_fieldexists2("ddwx_queue_free_log","remark")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_log`
            ADD COLUMN `remark` varchar(255) DEFAULT NULL,
            ADD COLUMN `mode` tinyint(1) DEFAULT '0';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS  `ddwx_queue_free_average` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) DEFAULT NULL,
          `bid` int(11) DEFAULT '0',
          `mid` int(11) DEFAULT '0',
          `order` text,
          `pj_money` decimal(11,2) DEFAULT NULL,
          `newlog` text,
          `where` text,
          `set` text,
          `rate_back` decimal(11,2) DEFAULT NULL,
          `mode` tinyint(1) DEFAULT NULL,
          `remark` varchar(255) DEFAULT NULL,
          `status` tinyint(1) DEFAULT '0' COMMENT '0:未发放 1：已发放',
          `createtime` int(11) DEFAULT NULL,
          `endtime` int(11) DEFAULT '0',
          PRIMARY KEY (`id`),
          KEY `aid` (`aid`) USING BTREE,
          KEY `bid` (`bid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='排队免单 平均分配数据表';");
}



if(getcustom('admin_user_authorize')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_admin_authorize` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
	  `name` varchar(255) DEFAULT NULL,
	  `code` varchar(100) DEFAULT '' COMMENT '验证码',
	  `createtime` int(11) DEFAULT NULL,
	  `status` tinyint(1) DEFAULT '0' COMMENT '是否使用',
	  `usetime` int(11) DEFAULT '0' COMMENT '使用时间',
	  `aid` int(11) DEFAULT '0' COMMENT '使用aid',
	  `username` varchar(255) DEFAULT NULL,
	  PRIMARY KEY (`id`),
	  KEY `name` (`name`) USING HASH,
      KEY `code` (`code`) USING HASH
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if(!pdo_fieldexists2("ddwx_admin_authorize","updatetime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_authorize`
            ADD COLUMN `updatetime`  int(11) NULL ,
            ADD INDEX `name` (`name`) USING HASH ,
            ADD INDEX `code` (`code`) USING HASH ;");
    }
}
if(getcustom('coupon_get_assert_time')){
    if(!pdo_fieldexists2("ddwx_coupon","yxqdate4_assert")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` 
            ADD COLUMN `yxqdate4_assert` int(11) DEFAULT '0',
            ADD COLUMN `yxqdate4_expire` int(11) DEFAULT '0';");
    }
}


if(getcustom('form_listpage')){
	if(!pdo_fieldexists2("ddwx_form","listpage_title")){
		\think\facade\Db::execute("ALTER TABLE `ddwx_form` ADD COLUMN `listpage_title` varchar(255) DEFAULT NULL;");
	}
	if(!pdo_fieldexists2("ddwx_form","listpage_description")){
		\think\facade\Db::execute("ALTER TABLE `ddwx_form` ADD COLUMN `listpage_description` varchar(255) DEFAULT NULL;");
	}
	if(!pdo_fieldexists2("ddwx_form","listpage_pic")){
		\think\facade\Db::execute("ALTER TABLE `ddwx_form` ADD COLUMN `listpage_pic` varchar(255) DEFAULT NULL;");
	}
	if(!pdo_fieldexists2("ddwx_form","listpage_tourl")){
		\think\facade\Db::execute("ALTER TABLE `ddwx_form` ADD COLUMN `listpage_tourl` varchar(255) DEFAULT NULL;");
	}
}

if(getcustom('yx_queue_free_fanli_commission')){
   if(!pdo_fieldexists2("ddwx_queue_free_set","queue_free_commission")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN  `queue_free_commission` text COMMENT '排队返利分成比例',
            ADD COLUMN  `queue_free_commission_jicha_status` tinyint(1) DEFAULT '0' COMMENT '排队返利分成级差',
            ADD COLUMN  `queue_free_commission_pj` decimal(10,2) DEFAULT '0.00' COMMENT '所有收入的',
            ADD COLUMN `queue_free_commission_ratio` decimal(10,2) DEFAULT '0.00' COMMENT '排队返利佣金比例';");
    }
}

if(getcustom('kecheng_order_learn')){
   if(!pdo_fieldexists2("ddwx_kecheng_list","orderlearn")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_list` ADD COLUMN `orderlearn` tinyint(1) NOT NULL DEFAULT 0 COMMENT '按顺序学习0：关闭1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_tiku` ADD COLUMN `mlid` int NOT NULL DEFAULT 0 COMMENT '题库章节id';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_record` ADD COLUMN `mlid` int NOT NULL DEFAULT 0 COMMENT '题库章节id';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_recordlog` ADD COLUMN `mlid` int(11) NOT NULL DEFAULT 0 COMMENT '题库章节id';");
    }
    if(!pdo_fieldexists2("ddwx_kecheng_list","learnhg")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_list` ADD COLUMN `learnhg` tinyint(1) NOT NULL DEFAULT 0 COMMENT '答题合格后学习下一章0：关闭1：开启';");
    }
}

if(getcustom('commissionranking')){
    if(!pdo_fieldexists2("ddwx_admin_set","rank_title")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `rank_title` varchar(50) DEFAULT NULL COMMENT '排行榜标题',
            ADD COLUMN `rank_desc` varchar(50) DEFAULT NULL COMMENT '排行榜标题下描述';");
    }
}
if(getcustom('restaurant_cashdesk_custom_pay')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_restaurant_cashdesk_custom_pay` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      `title` varchar(255) DEFAULT NULL,
      `sort` int(11) DEFAULT '0',
      `status` tinyint(1) DEFAULT '1',
      `createtime` int(11) DEFAULT '0',
       PRIMARY KEY (`id`),
      KEY `aid` (`aid`),
      KEY `bid` (`bid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    if(pdo_fieldexists2("ddwx_payorder","paytypeid")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` MODIFY COLUMN `paytypeid` int(11) DEFAULT '0' COMMENT '1余额支付 2微信支付 3支付宝支付 4货到付款 5转账汇款 11百度小程序 12头条小程序';");
    }
}

if(getcustom('restaurant_shop_cashdesk')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_restaurant_remark` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) DEFAULT '',
      `createtime` int(11) DEFAULT NULL,
      `sort` int(4) DEFAULT '0',
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('yuyue_selecttime_with_stock')){
    if(!pdo_fieldexists2("ddwx_yuyue_product","showdatetype")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` 
            ADD COLUMN `showdatetype` tinyint(1) DEFAULT 0 COMMENT '时间展示样式';");
    }
}

if(getcustom('restaurant_cashdek_ordergoods_remark')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order_goods","remark")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` 
            ADD COLUMN `remark` varchar(100) DEFAULT NULL COMMENT '备注';");
    }
}
if(getcustom('restaurant_table_default_product')){
    if(!pdo_fieldexists2("ddwx_restaurant_table","default_product_status")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_table` 
            ADD COLUMN `default_product_status` tinyint(1) DEFAULT '0' COMMENT '默认餐品',
            ADD COLUMN `default_product_bxdata` text COMMENT '默认餐品 必选',
            ADD COLUMN `default_product_kxdata` text COMMENT '默认餐品 可选';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order_goods","is_must_select")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` 
            ADD COLUMN `is_must_select` tinyint(1) DEFAULT '0' COMMENT '是否是必选';");
    }
}
if(getcustom('agent_to_origin')){
    if(!pdo_fieldexists2("ddwx_member_level","agent_to_origin")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `agent_to_origin` tinyint(1) DEFAULT '0' COMMENT '一级分销发放给发给原推荐人 0关闭 1开启';");
    }
}

if(getcustom('video_free_time')){
  if(!pdo_fieldexists2("ddwx_kecheng_chapter", "mianfei_time")){
    \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_chapter` 
      ADD COLUMN `mianfei_time` int NULL COMMENT '试看时长';");
  }
  if(!pdo_fieldexists2("ddwx_kecheng_chapter", "mianfei_unit")){
    \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_chapter` 
      ADD COLUMN `mianfei_unit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '时长单位  1 秒 2 分钟';");
  }
}
if(getcustom('plug_siming')){
  if(!pdo_fieldexists2("ddwx_admin","pc_index_data")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin` 
    	ADD COLUMN `pc_index_data` varchar(255) NULL DEFAULT 'all' COMMENT 'pc后台首页展示数据';");
  }
  if(!pdo_fieldexists2("ddwx_admin","mobile_index_data")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin` 
    	ADD COLUMN `mobile_index_data` varchar(255) NULL DEFAULT 'all' COMMENT '手机端后台财务展示数据';");
  }
}

if(getcustom('member_levelup_orderprice')){

  if(!pdo_fieldexists2("ddwx_member_level","up_orderprice")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
    	ADD COLUMN `up_orderprice` decimal(11,2) DEFAULT '0.00' COMMENT '单笔订单金额';");
  }

  if(!pdo_fieldexists2("ddwx_member_level","up_orderprice_condition")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
    	ADD COLUMN `up_orderprice_condition` varchar(20) NOT NULL DEFAULT 'or' COMMENT '单笔订单金额条件 or或，and且';");
  }
}
if(getcustom('restaurant_finance_notice_switch')){
    if(!pdo_fieldexists2("ddwx_member","is_receive_finance_tmpl")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
    	ADD COLUMN `is_receive_finance_tmpl` tinyint(1) DEFAULT '0' COMMENT '是否接收门店消费通知',
    	ADD COLUMN `is_receive_finance_sms` tinyint(1) DEFAULT '0' COMMENT '是否接收门店消费短信通知';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_money_change_st")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` 
    	ADD COLUMN `tmpl_money_change_st` tinyint(1) DEFAULT '1' COMMENT '消费通知开关',
    	ADD COLUMN `tmpl_money_change` varchar(50) DEFAULT NULL COMMENT '消费通知';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_money_change_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_money_change_txy` varchar(64) DEFAULT NULL;");
    }
}
if(getcustom('restaurant_shop_designer_page')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_restaurant_shop_designer_page` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      `content` longtext,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('shortvideo_play_give')){
  if(!pdo_fieldexists2("ddwx_shortvideo_sysset","video_gesture")){
      \think\facade\Db::execute("ALTER TABLE `ddwx_shortvideo_sysset`
          ADD COLUMN `video_gesture`  tinyint(1) NULL DEFAULT '1';");
    }
  if(!pdo_fieldexists2("ddwx_shortvideo_sysset","add_give_score")){
    \think\facade\Db::execute("ALTER TABLE `ddwx_shortvideo_sysset`
        ADD COLUMN `read_give_score`  int(11) NULL DEFAULT 0,
        ADD COLUMN `mid_give_score`  int(11) NULL DEFAULT 0 AFTER `read_give_score`,
        ADD COLUMN `day_give_score`  int(11) NULL DEFAULT 0 AFTER `mid_give_score`,
        ADD COLUMN `add_give_score`  int(11) NULL DEFAULT 0 AFTER `day_give_score`;");
  }
  if(!pdo_fieldexists2("ddwx_shortvideo","read_give_score")){
    \think\facade\Db::execute("ALTER TABLE `ddwx_shortvideo`
        ADD COLUMN `read_give_score`  varchar(32) NULL DEFAULT '';");
  }
  if(!pdo_fieldexists2("ddwx_shortvideo_sysset","read_give_money")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shortvideo_sysset` 
      ADD COLUMN `read_give_money` decimal(11,2) DEFAULT '0.00' COMMENT '短视频看完奖励',
      ADD COLUMN `coupon_list` text NULL COMMENT '短视频看完奖励优惠券',
      ADD COLUMN `mid_give_money` decimal(11,2) DEFAULT '0.00' ,
      ADD COLUMN `day_give_money` decimal(11,2) DEFAULT '0.00' ,
      ADD COLUMN `mid_give_coupon` int(11) DEFAULT '0',
      ADD COLUMN `day_give_coupon` int(11) DEFAULT '0';");
  }
  if(!pdo_fieldexists2("ddwx_shortvideo","read_give_money")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shortvideo` 
      ADD COLUMN `read_give_money` varchar(10) DEFAULT '' COMMENT '空继承主设置 0不赠送',
      ADD COLUMN `coupon_list` text COMMENT '短视频看完奖励优惠券',
      ADD COLUMN `coupon_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否使用系统设置 0 否 1 是';");
  }

  \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ext_givescore_record`  (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `from_table` varchar(32) DEFAULT '',
        `from_id` int(11) DEFAULT NULL,
        `score` int(11) DEFAULT NULL,
        `createtime` int(11) DEFAULT NULL,
        `type` varchar(32) DEFAULT '',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid_mid` (`aid`,`mid`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ext_givemoney_record` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `mid` int(11) DEFAULT NULL,
      `from_table` varchar(32) DEFAULT '',
      `from_id` int(11) DEFAULT NULL,
      `score` int(11) DEFAULT NULL,
      `createtime` int(11) DEFAULT NULL,
      `type` varchar(32) DEFAULT '',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid_mid` (`aid`,`mid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ext_givecoupon_record` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `aid` int(11) DEFAULT NULL,
    `mid` int(11) DEFAULT NULL,
    `from_table` varchar(32) DEFAULT '',
    `from_id` int(11) DEFAULT NULL,
    `score` int(11) DEFAULT NULL,
    `createtime` int(11) DEFAULT NULL,
    `type` varchar(32) DEFAULT '',
    `extra` text COMMENT '附加优惠券信息',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `aid_mid` (`aid`,`mid`)
  ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

}

if(getcustom('maidan_auto_reg')){
    if(!pdo_fieldexists2("ddwx_admin_set","maidan_auto_reg")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
    	ADD COLUMN `maidan_auto_reg` tinyint(1) DEFAULT '0' COMMENT '买单是否自动注册会员 0否 1是',
    	ADD COLUMN `maidan_login_tip` varchar(100) DEFAULT '' COMMENT '买单付款登录提示文字';");
    }
    if(!pdo_fieldexists2("ddwx_maidan_order","openid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` 
    	ADD COLUMN `openid` varchar(50) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT '会员未登录时的openid',
    	ADD COLUMN `unionid` varchar(50) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT '会员未登录时的unionid';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","maidan_bind_tel")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
    	ADD COLUMN `maidan_bind_tel` tinyint(1) DEFAULT '0' COMMENT '买单静默登录绑定手机号';");
    }
}
if(getcustom('levelup_teamnum_peoplenum')){
    if(!pdo_fieldexists2("ddwx_member_level","up_team_path_condition")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
    	ADD COLUMN `up_team_path_condition` varchar(10) DEFAULT NULL COMMENT '升级，每条线升级条件的条件逻辑关系',
    	ADD COLUMN `up_team_path_num` int(11) DEFAULT NULL COMMENT '满足x条线',
    	ADD COLUMN `up_team_people_num` int(11) DEFAULT NULL COMMENT '每条线超Y人',
    	ADD COLUMN `up_team_path_level` varchar(50) DEFAULT NULL COMMENT '满足的等级';");
    } 
}

if(getcustom('ciruikang_fenxiao')){
    if(!pdo_fieldexists2("ddwx_admin_set","open_product_parentbuy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `open_product_parentbuy` tinyint(1) NOT NULL DEFAULT 0 COMMENT '商品需上级购买足量 0 :关闭 1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
            ADD COLUMN `crk_up_pronum` int NOT NULL DEFAULT 0 COMMENT '一次性升级商品数量',
            ADD COLUMN `crk_up_levelid` int NOT NULL DEFAULT 0 COMMENT '一次性升级等级id',
            ADD COLUMN `crk_up_send_pronum` int NOT NULL DEFAULT 0 COMMENT '升级商品数量已发放数量',
            ADD COLUMN `crk_up_onetime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '一次升级的时间';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `crk_givenum` int NOT NULL DEFAULT 0 COMMENT '赠送数量' ,
            ADD COLUMN `crk_isuse` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已使用',
            ADD COLUMN `crk_residue` int(11) NOT NULL DEFAULT 0 COMMENT '剩余未使用数量',
            ADD COLUMN `crk_sumnum` int(11) NOT NULL DEFAULT 0 COMMENT '每满多少',
            ADD COLUMN `crk_sendnum` int(11) NOT NULL DEFAULT 0 COMMENT '赠多少',
            ADD COLUMN `crk_bnum` int(11) NOT NULL DEFAULT 0 COMMENT '满赠的倍数';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD COLUMN `crk_decnum` int(11) NOT NULL DEFAULT 0 COMMENT '减少上级库存数量',
            ADD COLUMN `crk_psid` int(11) NOT NULL DEFAULT 0 COMMENT '库存id';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
            ADD COLUMN `crk_sumnum` int NOT NULL DEFAULT 0 COMMENT '每购买多少商品',
            ADD COLUMN `crk_sendnum` int NOT NULL DEFAULT 0 COMMENT '随机赠送多少商品',
            ADD COLUMN `up_regtime_and` int NOT NULL DEFAULT 0 COMMENT '注册多少天内（包含）',
            ADD COLUMN `up_pro_orderstatus` tinyint(1) NOT NULL DEFAULT 0 COMMENT '统计订单状态 0:付款后所有订单 1:仅确认收货订单',
            ADD COLUMN `up_pro_orderrange` tinyint(1) NOT NULL DEFAULT 0 COMMENT '统计订单范围 0:仅自己订单 1:自己及下级订单',
            ADD COLUMN `up_small_market_num` int NOT NULL DEFAULT 0 COMMENT '小市场业绩',
            ADD COLUMN `up_pro_minprelevelid` int NOT NULL DEFAULT 0 COMMENT '累计商品升级前的当前等级，仅能填写一个等级ID',
            ADD COLUMN `up_proid2` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '一次性购买商品(商品ID)',
            ADD COLUMN `up_pronum2` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '1' COMMENT '一次性购买数量',
            ADD COLUMN `up_pro_orderstatus2` tinyint(1) NOT NULL DEFAULT 0 COMMENT '统计订单状态 0:付款后所有订单 1:仅确认收货订单',
            ADD COLUMN `up_pro_prelevelid2` int(11) NOT NULL DEFAULT 0 COMMENT '一次性购买升级 购买者等级(ID)',
            ADD COLUMN `fenhong_weight_ztnum` int NOT NULL DEFAULT 0 COMMENT '加权合作分红直推人数满X人',
            ADD COLUMN `fenhong_weight_levelid` int NOT NULL DEFAULT 0 COMMENT '加权合作分红等级ID为Y的下级',
            ADD COLUMN `fenhong_weight_ratio` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '加权合作分红拿当月业绩%的合作分红',
            ADD COLUMN `fenhong_weight_yeji` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '加权合作分红当月业绩满X万',
            ADD COLUMN `crk_send_orderstatus` tinyint(1) NOT NULL DEFAULT 0 COMMENT '赠送商品统计订单状态 0: 付款后所有订单 1:仅确认收货订单',
            ADD COLUMN `onebuy_commission1` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '一次性购买升级奖励 一级(%)',
            ADD COLUMN `onebuy_commission2` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '一次性购买升级奖励 二级(%)';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `areasafe` tinyint(1) NOT NULL DEFAULT 0 COMMENT '区域保护 0：无区域保护 2：市级区域 3：区县区域',
            ADD COLUMN `areasafe_province` varchar(20) NOT NULL DEFAULT '' COMMENT '区域保护 省',
            ADD COLUMN `areasafe_city` varchar(20) NOT NULL DEFAULT '' COMMENT '区域保护 市',
            ADD COLUMN `areasafe_area` varchar(20) NOT NULL DEFAULT '' COMMENT '区域保护 区县',
            ADD COLUMN `recomid` int(11) NOT NULL DEFAULT 0 COMMENT '推荐商家id';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
            ADD COLUMN `areasafe_city_btmoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '区域保护 市级每双补贴',
            ADD COLUMN `areasafe_area_btmoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '区域保护 区县级每双补贴',
            ADD COLUMN `areasafe_recom_btmoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '区域保护 推荐商家每双补贴';");

        \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_business_btmoneylog` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `aid` int(11) NOT NULL DEFAULT 0,
            `bid` int(11) NOT NULL DEFAULT 0 COMMENT '商家id',
            `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型 2：市补贴 3：区县补贴 4：推荐补贴',
            `orderid` int(11) NOT NULL DEFAULT 0 COMMENT '订单id（推荐补贴用）',
            `orderids` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '订单id集合',
            `totalnum` int(11) NOT NULL DEFAULT 0 COMMENT '所有商品数量',
            `money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '补贴金额',
            `areasafe_city_btmoney` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '市级补贴',
            `areasafe_area_btmoney` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '区县补贴',
            `areasafe_recom_btmoney` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '推荐商家补贴',
            `monthtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放的月份',
            `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`) USING BTREE,
            INDEX `aid`(`aid`) USING BTREE,
            INDEX `monthtime`(`monthtime`) USING BTREE,
            INDEX `bid`(`bid`) USING BTREE,
            INDEX `orderid`(`orderid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

        \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_fenhong_weightlog` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `aid` int(11) NOT NULL DEFAULT 0,
            `mid` int(11) NOT NULL DEFAULT 0,
            `levelid` int(11) NOT NULL DEFAULT 0 COMMENT '等级',
            `ztnum` int(11) NOT NULL DEFAULT 0 COMMENT '直推人数',
            `yeji` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '业绩',
            `allyeji` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '总业绩',
            `totalprice` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '当月总金额',
            `fenhongprice` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '分红金额',
            `money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '分红金额',
            `fenhong_weight_ztnum` int(11) NOT NULL DEFAULT 0 COMMENT '等级直推人数',
            `fenhong_weight_yeji` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '等级当月业绩',
            `fenhong_weight_ratio` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '等级合作分红比例',
            `monthtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放的月份',
            `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`) USING BTREE,
            INDEX `aid`(`aid`) USING BTREE,
            INDEX `mid`(`mid`) USING BTREE,
            INDEX `levelid`(`levelid`) USING BTREE,
            INDEX `monthtime`(`monthtime`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

        \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_product_stock` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `aid` int(11) NOT NULL DEFAULT 0,
            `mid` int(11) NOT NULL DEFAULT 0,
            `proid` int(11) NOT NULL DEFAULT 0,
            `ggid` int(11) NOT NULL DEFAULT 0,
            `stock` int(11) NOT NULL DEFAULT 0 COMMENT '库存（只统计因自己变化而变动）',
            `num` int(11) NOT NULL DEFAULT 0 COMMENT '库存数量（统计自己及下级变动）',
            `createtime` int(11) UNSIGNED NOT NULL DEFAULT 11,
            `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
            `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
            `ggname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
            PRIMARY KEY (`id`) USING BTREE,
            INDEX `aid`(`aid`) USING BTREE,
            INDEX `mid`(`mid`) USING BTREE,
            INDEX `proid`(`proid`) USING BTREE,
            INDEX `ggid`(`ggid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

        \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_product_stock_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `aid` int(11) NOT NULL DEFAULT 0,
            `mid` int(11) NOT NULL DEFAULT 0,
            `psid` int(11) NOT NULL DEFAULT 0 COMMENT '商品库存库变化id',
            `num` int(11) NOT NULL DEFAULT 0 COMMENT '变化数量',
            `stock` int(11) NOT NULL DEFAULT 0,
            `remark` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '备注',
            `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
            `from_mid` int(11) NOT NULL DEFAULT 0,
            `from_orderid` int(11) NOT NULL DEFAULT 0,
            `from_ogid` int(11) NOT NULL DEFAULT 0,
            `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
            `prenum` int(11) NOT NULL DEFAULT 0,
            `afternum` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`) USING BTREE,
            INDEX `aid`(`aid`) USING BTREE,
            INDEX `mid`(`mid`) USING BTREE,
            INDEX `psid`(`psid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
    }
    if(!pdo_fieldexists2("ddwx_member_level","onebuy_commissionjs")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `onebuy_commissionjs`  tinyint(1) NULL DEFAULT 0 COMMENT '直推和间推结算方式' AFTER `onebuy_commission2`;");
    }
}
if(getcustom('member_shougou_parentreward')){
    if(!pdo_fieldexists2("ddwx_member_level","commissionsg1")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `commissionsg1` float(11,2) DEFAULT '0',
        ADD COLUMN `commissionsg2` float(11,2) DEFAULT '0',
        ADD COLUMN `commissionsg3` float(11,2) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_member_commission_record","islock")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_record` ADD COLUMN `islock` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否锁住 0：否 1：是';");
    }
}
if(getcustom('member_shougou_parentreward_wait')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_commission_record_wait` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) DEFAULT NULL,
          `mid` int(11) DEFAULT NULL,
          `frommid` int(11) DEFAULT NULL,
          `orderid` int(11) DEFAULT NULL,
          `ogid` int(11) DEFAULT NULL,
          `type` varchar(100) DEFAULT 'shop' COMMENT 'shop 商城',
          `commission` decimal(11,2) DEFAULT NULL,
          `score` decimal(12,3) DEFAULT '0.000',
          `remark` varchar(255) DEFAULT NULL,
          `createtime` int(11) DEFAULT NULL,
          `endtime` int(11) DEFAULT NULL,
          `status` tinyint(1) DEFAULT '0',
          PRIMARY KEY (`id`) USING BTREE,
          KEY `aid` (`aid`) USING BTREE,
          KEY `mid` (`mid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
}
if(getcustom('member_level_close_jicha')){
    if(!pdo_fieldexists2("ddwx_member_level","isclose_jicha")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `isclose_jicha` tinyint(1) DEFAULT 0 COMMENT '是否关闭该等级级差';");
    }
}

if(getcustom('product_fenzhangmoney')){
  if(!pdo_fieldexists2("ddwx_shop_product","product_fenzhangmoney")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `product_fenzhangmoney` float(11,2) DEFAULT '-1';");
  }
}
if(getcustom('yx_queue_duli_queue')){
    if(!pdo_fieldexists2("ddwx_queue_free","teamid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free` ADD COLUMN `teamid` int(11) DEFAULT '0' COMMENT '队伍id 0:平台 >0:推荐人的mid';");
    }
    if(!pdo_fieldexists2("ddwx_queue_free_set","duli_queue_levelid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `duli_queue_levelid` varchar(255) DEFAULT NULL COMMENT '开启独立排队的等级';");
    }
}
if(getcustom('coupon_multiselect')){
    if(!pdo_fieldexists2("ddwx_coupon","is_multiselect")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` ADD COLUMN `is_multiselect` tinyint(1) DEFAULT '0' COMMENT '是否可叠加使用';");
    }
}
if(getcustom('member_up_binding_tel')){
    if(!pdo_fieldexists2("ddwx_member_level","up_binding_tel")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `up_binding_tel` tinyint(1) DEFAULT '0' COMMENT '升级绑定手机号';");
    }
    if(!pdo_fieldexists2("ddwx_member_level","up_binding_tel_condition")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `up_binding_tel_condition` varchar(10) DEFAULT NULL COMMENT '升级绑定手机号 ';");
    }
}

if(getcustom('product_xieyi')){
    if(!pdo_fieldexists2("ddwx_business_sysset","product_xieyi_check")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `product_xieyi_check`  tinyint(1) NULL DEFAULT 0 COMMENT '商家商品协议 0不需要审核 1需要审核';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","xieyi_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `xieyi_id`  int(11) NULL DEFAULT 0 COMMENT '商品协议id';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_xieyi` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NOT NULL DEFAULT 0 ,
    `bid`  int(11) NOT NULL DEFAULT 0 ,
    `name`  varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '标题' ,
    `content`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL ,
    `status`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '0未审核 1审核通过 2审核拒绝' ,
    `createtime`  int(10) NOT NULL DEFAULT 0 COMMENT '创建时间' ,
    `reason`  varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' ,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
}

if(getcustom('product_service_fee')){
    if(!pdo_fieldexists2("ddwx_shop_product", "service_fee")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
      ADD COLUMN `service_fee` decimal(11,2) DEFAULT '0.00' COMMENT '服务费';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product", "service_fee_switch")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
      ADD COLUMN `service_fee_switch` tinyint(1) DEFAULT '0' COMMENT '服务费开关 1:开启 0:关闭';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product", "service_fee_data")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
      ADD COLUMN `service_fee_data` text COMMENT '开启服务费时的各个等级价格数据';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product", "shd_remark")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
      ADD COLUMN `shd_remark` varchar(60) DEFAULT NULL COMMENT '送货单备注';");
    }
    if(!pdo_fieldexists2("ddwx_shop_sysset", "show_shd_remark")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
      ADD COLUMN `show_shd_remark` tinyint(1) DEFAULT 0 COMMENT '是否显示送货单备注1:显示 0:不显示'");
    }

    if(!pdo_fieldexists2("ddwx_shop_guige", "service_fee")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` 
      ADD COLUMN `service_fee` decimal(11,2) DEFAULT '0.00' COMMENT '服务费';");
    }
    if(!pdo_fieldexists2("ddwx_shop_guige", "service_fee_data")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` 
      ADD COLUMN `service_fee_data` text COMMENT '开启服务费时的各个等级价格数据';");
    }

    if(!pdo_fieldexists2("ddwx_member", "service_fee")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
      ADD COLUMN `service_fee` decimal(11,2) DEFAULT '0.00' COMMENT '服务费余额';");
    }

    if(!pdo_fieldexists2("ddwx_payorder", "service_fee_money")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` 
      ADD COLUMN `service_fee_money` decimal(11,2) DEFAULT '0.00' COMMENT '服务费';");
    }

    if(!pdo_fieldexists2("ddwx_shop_order", "service_fee_money")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
      ADD COLUMN `service_fee_money` decimal(11,2) DEFAULT '0.00' COMMENT '扣除总服务费';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order", "service_fee")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
      ADD COLUMN `service_fee` decimal(11,2) DEFAULT '0.00' COMMENT '服务费';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods", "service_fee")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
      ADD COLUMN `service_fee` decimal(11,2) DEFAULT '0.00' COMMENT '服务费';");
    }
    if(!pdo_fieldexists2("ddwx_shop_refund_order", "refund_service_fee")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order` 
      ADD COLUMN `refund_service_fee` decimal(11,2) DEFAULT '0.00' COMMENT '服务费的退款金额';");
    }
    if(!pdo_fieldexists2("ddwx_shop_refund_order_goods", "service_fee")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order_goods` 
      ADD COLUMN `service_fee` decimal(11,2) DEFAULT '0.00' COMMENT '服务费';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_servicefee_log`  (
		    `id` int NOT NULL AUTO_INCREMENT,
		    `aid` int(11) NOT NULL DEFAULT 0,
		    `mid` int(11) NOT NULL DEFAULT 0,
		    `service_fee` decimal(11, 2) NOT NULL DEFAULT 0 COMMENT '服务费',
		    `after` decimal(11, 2) NOT NULL DEFAULT 0,
		    `remark` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
		    `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1普通',
		    `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
		    PRIMARY KEY (`id`),
            INDEX `aid`(`aid`) USING BTREE,
            INDEX `mid`(`mid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_servicefee_recharge_order`  (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) NULL DEFAULT NULL,
          `mid` int(11) NULL DEFAULT NULL,
          `money` decimal(11, 2) NULL DEFAULT 0.00,
          `ordernum` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
          `createtime` int(11) NULL DEFAULT NULL,
          `status` tinyint(1) NULL DEFAULT 0,
          `payorderid` int(11) NULL DEFAULT NULL,
          `paytypeid` int(11) NULL DEFAULT NULL,
          `paytype` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
          `paynum` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
          `paytime` int(11) NULL DEFAULT NULL,
          `platform` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
          PRIMARY KEY (`id`) USING BTREE,
          INDEX `aid`(`aid`) USING BTREE,
          INDEX `mid`(`mid`) USING BTREE
        ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8");
}
if(getcustom('member_overdraft_money')){
    if(!pdo_fieldexists2("ddwx_business_sysset","business_cashdesk_guazhang")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `business_cashdesk_guazhang` tinyint(1) DEFAULT '0' COMMENT '多商户收银台 信用付   0:关闭 1：开启';");
    }
    if(!pdo_fieldexists2("ddwx_cashier","guazhangpay")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier` ADD COLUMN `guazhangpay` tinyint(1) DEFAULT '1' COMMENT '挂账收款';");
    }
}
if(getcustom('cashier_member_paypwd')){
    if(!pdo_fieldexists2("ddwx_cashier","paypwd_use_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier`
            ADD COLUMN `paypwd_use_status` tinyint(1) DEFAULT '0' COMMENT '余额支付开启支付密码',
            ADD COLUMN `default_paypwd` varchar(50) DEFAULT NULL;");
    }
}
if(getcustom('sxpay_apptowx')){
    if(!pdo_fieldexists2("ddwx_admin_setapp_app","sxpay_mno")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_app` ADD COLUMN `sxpay_mno` varchar(100) DEFAULT NULL COMMENT '随行付商户号';");
    }
    if(!pdo_fieldexists2("ddwx_admin_setapp_app","sxpay_mchkey")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_app` ADD COLUMN `sxpay_mchkey` varchar(100) DEFAULT NULL COMMENT '随行付支付密钥'");
    }
    if(!pdo_fieldexists2("ddwx_admin_setapp_app","sxpay_embedded")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_app` ADD COLUMN `sxpay_embedded` tinyint(4) NOT NULL DEFAULT '0' COMMENT '随行付小程序 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_admin_setapp_app","alipay_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_app` ADD COLUMN `alipay_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付模式 0普通模式 3随行付模式';");
    }
}
if(getcustom('member_set')) {
    if (!pdo_fieldexists2("ddwx_member_set", "member_edit_switch")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_set` ADD COLUMN `member_edit_switch` tinyint(1) DEFAULT '1' COMMENT '会员编辑开关 0关闭 1开启';");
    }
}
if(getcustom('zhongkang_sync')){
    if (!pdo_fieldexists2("ddwx_shop_product", "zhongkang_appid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `zhongkang_appid` varchar(60) DEFAULT NULL,
            ADD COLUMN `zhongkang_levelid` int(11) DEFAULT '0';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set", "zhongkang_secret")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `zhongkang_secret` varchar(50) DEFAULT NULL;");
    }
}
if(getcustom('yx_queue_free')){
    if (!pdo_fieldexists2("ddwx_admin_set", "rank_queue_free_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `rank_queue_free_status` tinyint(1) DEFAULT '0' COMMENT '排行榜佣金统计状态';");
    }
    if (!pdo_fieldexists2("ddwx_member", "totalqueuecommission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `totalqueuecommission` decimal(10,2) DEFAULT '0.00' COMMENT '累计排队返利的返利';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free_set", "feepercent_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `feepercent_type` tinyint(1) DEFAULT '0' COMMENT '多商户抽成方式，0人工增加抽成比例1自动增加抽成比例';");
    }
}

if(getcustom('withdraw_mul')){
  if (!pdo_fieldexists2("ddwx_admin_set", "withdrawmul")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `withdrawmul` decimal(11,2) DEFAULT '0' COMMENT '余额提现整数倍';");
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `comwithdrawmul` decimal(11,2) DEFAULT '0' COMMENT '佣金提现整数倍';");
  }
}
if(getcustom('cashdesk_sxpay')){
    if (!pdo_fieldexists2("ddwx_restaurant_shop_order", "child_paytypeid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `child_paytypeid` int(11) DEFAULT '0' COMMENT '支付类型子类型  随行付微信2 随行付支付宝3，自定义支付ID';");
    }
}
if(getcustom('restaurant_cashdesk_custom_pay')){
    if (!pdo_fieldexists2("ddwx_restaurant_shop_order", "child_paytypeid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `child_paytypeid` int(11) DEFAULT '0' COMMENT '支付类型子类型  随行付微信2 随行付支付宝3，自定义支付ID';");
    }
}
if(getcustom('article_multi_category')){
    if (pdo_fieldexists2("ddwx_article", "cid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_article` 
        MODIFY COLUMN `cid` varchar(50) DEFAULT NULL;");
    }
}


if(getcustom('luckycollage_zjmax')){
  if (!pdo_fieldexists2("ddwx_lucky_collage_product", "zjmax")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product`
ADD COLUMN `zjmax` int NULL DEFAULT 0 COMMENT '每个会员参团最高可中次数';");
  }
}
if(getcustom('luckycollage_fail_commission')){
  if (!pdo_fieldexists2("ddwx_lucky_collage_product", "fail_commissionset")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product`
ADD COLUMN `fail_commissionset` tinyint(1) DEFAULT '-1',
ADD COLUMN `fail_commissiondata1` text CHARACTER SET utf8,
ADD COLUMN `fail_commissiondata2` text CHARACTER SET utf8,
ADD COLUMN `fail_commissiondata3` text CHARACTER SET utf8,
ADD COLUMN `fail_fenhongset` int(11) DEFAULT '0' COMMENT '失败分红设置',
ADD COLUMN `fail_gdfenhongset` int(2) DEFAULT '0' COMMENT '0按会员等级 1价格比例  2固定金额 -1不参与分红',
ADD COLUMN `fail_gdfenhongdata1` text CHARACTER SET utf8,
ADD COLUMN `fail_gdfenhongdata2` text CHARACTER SET utf8,
ADD COLUMN `fail_teamfenhongset` int(2) DEFAULT '0',
ADD COLUMN `fail_teamfenhongdata1` text CHARACTER SET utf8,
ADD COLUMN `fail_teamfenhongdata2` text CHARACTER SET utf8,
ADD COLUMN `fail_areafenhongset` int(2) DEFAULT '0',
ADD COLUMN `fail_areafenhongdata1` text CHARACTER SET utf8,
ADD COLUMN `fail_areafenhongdata2` text CHARACTER SET utf8;");
  }
}


if(getcustom('luckycollage_robot_get')){
    if (!pdo_fieldexists2("ddwx_lucky_collage_sysset", "robot_get")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_sysset` 
    ADD COLUMN `robot_get` tinyint(1) NULL DEFAULT 1 COMMENT '机器人是否可以中奖 0 否 1 是';");
    }
}

if (getcustom('yx_mangfan')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mangfan_set` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) DEFAULT NULL,
          `bid` int(11) DEFAULT NULL,
          `status` tinyint(1) DEFAULT '0',
          `order_max_num` int(11) DEFAULT NULL,
          `good_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商品类型 0 单独设置商品 1 所有商品 2 指定类目',
          `good_commission_type1` tinyint(1) NOT NULL DEFAULT '0',
          `good_commission_rate1` decimal(10,2) DEFAULT '0.00',
          `good_commission_type2` tinyint(1) NOT NULL DEFAULT '0',
          `good_commission_rate2` decimal(10,2) DEFAULT '0.00',
          `cate_list` text,
          `good_commission_type0` tinyint(1) NOT NULL DEFAULT '0',
          `good_list` text COMMENT '单独设置活动商品',
          `receive_type` tinyint(1) DEFAULT '1' COMMENT '到账钱包 1 佣金 2 余额',
          `create_time` int(11) DEFAULT NULL,
          `update_time` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `aid` (`aid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消费盲返';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mangfan_order_list` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) NOT NULL,
          `bid` int(11) DEFAULT '0',
          `mid` int(11) DEFAULT NULL,
          `order_id` int(11) DEFAULT NULL,
          `ordernum` varchar(100) DEFAULT NULL,
          `commission_status` tinyint(1) DEFAULT '0' COMMENT '佣金是否发放',
          `pay_time` int(11) DEFAULT NULL COMMENT '同步订单列表的付款时间',
          `mangfan_index` int(11) DEFAULT '0' COMMENT '排序值 返佣已此值为基准',
          `create_time` int(11) DEFAULT NULL,
          `update_time` int(11) DEFAULT NULL,
          `change_times` int(11) DEFAULT '0' COMMENT '更改次数',
          `origin_order_id` text COMMENT '更改前相关订单id',
          `type` varchar(50) NOT NULL DEFAULT 'shop' COMMENT '订单类型',
          PRIMARY KEY (`id`),
          KEY `aid_mid` (`aid`,`mid`),
          KEY `mangfan_index` (`aid`,`mangfan_index`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mangfan_commission_record` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) NOT NULL,
          `mid` int(11) NOT NULL,
          `order_id` int(11) NOT NULL,
          `ordernum` varchar(100) DEFAULT NULL,
          `from_mid` int(11) NOT NULL,
          `from_order_id` int(11) DEFAULT NULL,
          `from_ordernum` varchar(100) DEFAULT NULL,
          `commission` decimal(10,2) NOT NULL COMMENT '佣金数量',
          `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 未发放  1 已发放 -1 作废',
          `create_time` int(11) DEFAULT NULL,
          `update_time` int(11) DEFAULT NULL,
          `type` varchar(50) NOT NULL DEFAULT 'shop' COMMENT '订单类型',
          `from_type` varchar(50) NOT NULL DEFAULT 'shop',
          PRIMARY KEY (`id`),
          KEY `aid_mid` (`aid`,`mid`),
          KEY `aid_from_mid` (`aid`,`from_mid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    if (!pdo_fieldexists2("ddwx_shop_order_goods", "is_mangfan")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods`
        ADD COLUMN `mangfan_commission_type` tinyint(1) NULL DEFAULT 0 COMMENT '盲返佣金类型',
        ADD COLUMN `mangfan_rate` decimal(10, 2) NULL DEFAULT 0 COMMENT '盲返佣金比例',
        ADD COLUMN `is_mangfan` tinyint(1) NULL DEFAULT 0 COMMENT '是否是盲返商品 0 否 1 是';");
    }

    if(!pdo_fieldexists2("ddwx_mangfan_order_list", "type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mangfan_order_list` ADD COLUMN `type` varchar(50) NOT NULL DEFAULT 'shop' COMMENT '订单类型';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_mangfan_commission_record` 
            ADD COLUMN `type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'shop' COMMENT '订单类型',
            ADD COLUMN `from_type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'shop';");
    }
}

if(getcustom('yx_queue_free_freeze_account')){
    if (!pdo_fieldexists2("ddwx_member", "freeze_credit")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member`
        ADD COLUMN `freeze_credit` decimal(10, 2) NULL DEFAULT 0 COMMENT '冻结账户数量';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free_set", "freeze_exchange_wallet")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set`
        ADD COLUMN `freeze_exchange_wallet` varchar(10) DEFAULT '' COMMENT '冻结账户转出钱包';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_freeze_credit_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `mid` int(11) DEFAULT NULL,
      `money` decimal(11,2) DEFAULT '0.00',
      `after` decimal(11,2) DEFAULT '0.00',
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
      `from_mid` int(11) DEFAULT '0',
      `paytype` varchar(60) DEFAULT NULL,
      `exchange_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_freeze_credit_exchange_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `mid` int(11) NOT NULL DEFAULT '0',
      `num` decimal(12,2) DEFAULT '0.00' COMMENT '兑换数量',
      `score` decimal(10,2) DEFAULT '0.00' COMMENT '消耗积分',
      `type` tinyint(1) DEFAULT '0' COMMENT '兑换钱包',
      `create_time` int(11) DEFAULT NULL,
      `update_time` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid_mid` (`aid`,`mid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");


}

if(getcustom('business_show_turnover')){
    if (!pdo_fieldexists2("ddwx_business", "turnover_show")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
    ADD COLUMN `turnover_show` tinyint(1) NULL DEFAULT 0 COMMENT '是否显示商家营业额 0 关闭 1 开启';");
    }
}


if(getcustom('partner_gongxian')){
  if (!pdo_fieldexists2("ddwx_member_level", "fenhong_gongxian_peraddnum")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `fenhong_gongxian_peraddnum` float(11,2) DEFAULT '0.00' AFTER `fenhong_gongxian_percent`;");
  }
}

if(getcustom('commission_to_score')){
    if (!pdo_fieldexists2("ddwx_commission_toscore_log", "set_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_commission_toscore_log` ADD COLUMN `set_id`  int(11) NOT NULL DEFAULT 0 COMMENT '释放设置数据id';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set", "commission_to_score_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `commission_to_score_status`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '积分释放开关 0关闭 1开启';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_commission_toscore_log2` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `mid` int(11) NOT NULL DEFAULT '0',
      `commission_to_score_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '补贴金额计算方式 0利润百分比 1固定金额',
      `butie_num` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '补贴金额',
      `commission` decimal(12,3) DEFAULT '0.000',
      `commission_total` decimal(12,3) DEFAULT '0.000',
      `num` decimal(12,3) DEFAULT '0.000',
      `w_day` int(11) NOT NULL DEFAULT '0' COMMENT '执行日期',
      `w_time` int(11) NOT NULL DEFAULT '0' COMMENT '执行时间',
      `set_id` int(11) NOT NULL DEFAULT '0' COMMENT '释放设置数据id',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_score_to_commission_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `title` varchar(255) DEFAULT '' COMMENT '标题描述',
      `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '补贴方式 0按百分比 1固定金额',
      `min` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '最小积分',
      `max` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '最高积分',
      `bili` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '转换比例',
      `butie` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '补贴金额',
      `createtime` int(10) NOT NULL DEFAULT '0',
      `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0关闭 1开启',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;");
}
if(getcustom('business_reward_member')){
    if (!pdo_fieldexists2("ddwx_business", "reward_member")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `reward_member` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商家打赏功能 0关闭 1开启';");
    }
    if (!pdo_fieldexists2("ddwx_business", "reward_member_bili")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `reward_member_bili` varchar (60) NOT NULL DEFAULT '0.00' COMMENT '打赏比例';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_reward_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `business_reward_bili_b` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '打赏奖励商家比例',
      `business_reward_bili` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '打赏奖励会有比例',
      `commissionset` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分销设置 0按会员等级设置 1单独设置比例',
      `commissiondata` text NOT NULL COMMENT '一级分销比例',
      `teamfenhongset` tinyint(1) NOT NULL DEFAULT '0' COMMENT '团队分红设置 0根据等级 1单独设置比例',
      `teamfenhongdata` text NOT NULL COMMENT '团队分红比例',
      `gdfenhongset` tinyint(1) NOT NULL DEFAULT '0' COMMENT '股东分红设置 0根据等级 1单独设置比例',
      `gdfenhongdata` text NOT NULL COMMENT '股东分红比例',
      `business_reward_desc` text NOT NULL COMMENT '打赏说明',
      `createtime` int(10) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='商家打赏设置';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_score_to_commission_set` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NULL DEFAULT 0 ,
    `title`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '标题描述' ,
    `type`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '补贴方式 0按百分比 1固定金额' ,
    `min`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '最小积分' ,
    `max`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '最高积分' ,
    `bili`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '转换比例' ,
    `butie`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '补贴金额' ,
    `createtime`  int(10) NOT NULL DEFAULT 0 ,
    `status`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '0关闭 1开启' ,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_reward_order` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `bid` int(11) DEFAULT NULL,
      `mid` int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
      `ordernum` varchar(255) NOT NULL DEFAULT '' COMMENT '订单编号',
      `payorderid` int(11) NOT NULL DEFAULT '0' COMMENT '支付订单id',
      `name` varchar(255) NOT NULL DEFAULT '' COMMENT '标注名称',
      `to_mid` int(11) NOT NULL DEFAULT '0' COMMENT '打赏会员id',
      `money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '打赏金额',
      `to_money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '到账金额',
      `to_business_money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '奖励商家积分',
      `createtime` int(10) NOT NULL DEFAULT '0' COMMENT '请求时间',
      `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未支付 1已支付',
      `paytime` int(10) NOT NULL DEFAULT '0' COMMENT '支付时间',
      `paytype` varchar(255) NOT NULL DEFAULT '' COMMENT '支付类型',
      `paytypeid` int(11) NOT NULL DEFAULT '0' COMMENT '支付订单id',
      `paynum` varchar(60) DEFAULT '',
      `platform` varchar(255) DEFAULT NULL,
      `pay_money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '需支付余额数量',
      `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
      `isfenhong` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1是否已分红',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`),
      KEY `bid` (`bid`),
      KEY `mid` (`mid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='商家打赏订单';");

    if (!pdo_fieldexists2("ddwx_business_reward_set", "business_reward_commission_bili")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_reward_set` ADD COLUMN `business_reward_commission_bili` decimal (12,2) NOT NULL DEFAULT '0.00' COMMENT '奖励会员佣金比例';");
    }
    if (!pdo_fieldexists2("ddwx_business_reward_order", "to_commission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_reward_order` ADD COLUMN `to_commission` decimal (12,2) NOT NULL DEFAULT '0.00' COMMENT '到账佣金数量';");
    }
}
if(getcustom('system_webuser_express_appcode')){
    if (!pdo_fieldexists2("ddwx_admin", "ali_appcode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin` ADD COLUMN `ali_appcode` varchar(50) DEFAULT NULL COMMENT '控制台设置的快递apode';");
    }
}

if(getcustom('sign_pay_bonus')){
    if (!pdo_fieldexists2("ddwx_signset", "default_totalprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` ADD COLUMN `default_totalprice` decimal(10,2) DEFAULT '0.00' COMMENT '奖金池初始金额';");
    }
}
if(getcustom('yuyue_self_selecttime')){
    if (!pdo_fieldexists2("ddwx_yuyue_product", "selftimedata")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` ADD COLUMN `selftimedata` text COMMENT '自选日期时间';");
    }
}
if(getcustom('yx_queue_free_money_range')){
    if (!pdo_fieldexists2("ddwx_queue_free_set", "queue_money_range")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `queue_money_range` text COMMENT '金额范围';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free", "range_no")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free` ADD COLUMN `range_no` varchar(20) DEFAULT NULL COMMENT '范围编号';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free_set", "queue_money_range_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `queue_money_range_status` tinyint(1) DEFAULT '0' COMMENT '金额范围状态';");
    }
}
if(getcustom('yx_queue_free')){
    if (!pdo_fieldexists2("ddwx_queue_free", "quit_queue")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free` ADD COLUMN `quit_queue` tinyint(1) DEFAULT '0' COMMENT '退出排队 1退出';");
    }
}

if(getcustom('yx_cashback_time_tjspeed')){
    if(!pdo_fieldexists2("ddwx_cashback","parent_speed")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` 
            ADD COLUMN `parent_speed` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '直推上级加速（%）',
            ADD COLUMN `parent2_speed` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '间推上级加速';");
    }
    if(!pdo_fieldexists2("ddwx_member","cashback_speed_num")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `cashback_speed_num` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '返现加速数额';");
    }
}
if(getcustom('yx_cashback_time_teamspeed')){
    if(!pdo_fieldexists2("ddwx_cashback","teamspeeddata")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `teamspeeddata` text NULL COMMENT '团队业绩达标加速';");
    }
    if(!pdo_fieldexists2("ddwx_member","cashback_speed_num")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `cashback_speed_num` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '返现加速数额';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods_cashback","teamspeed_yeji")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` 
            ADD COLUMN `teamspeed_yeji` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '团队加速业绩',
            ADD COLUMN `teamspeed` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '团队加速',
            ADD COLUMN `teamspeed_money` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '团队加速达标业绩';");
    }
}

if(getcustom('sign_pay_bonus')){
    if (!pdo_fieldexists2("ddwx_mp_tmplset_new", "tmpl_sign_bonus_open")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_sign_bonus_open` varchar(64) DEFAULT NULL COMMENT '签到开奖通知';");
    }
}

if(getcustom('yx_cashback_time_delaysend')){
    if (!pdo_fieldexists2("ddwx_cashback", "delaysend_day")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `delaysend_day` int(11) NOT NULL DEFAULT 0 COMMENT '下单后第几天开始返还';");
        \think\facade\Db::execute(" ALTER TABLE `ddwx_shop_order_goods_cashback` 
            ADD COLUMN `delaysend_day` int(11) NOT NULL DEFAULT 0 COMMENT '延迟发放天数',
            ADD COLUMN `delaysend_starttime` int(11) NOT NULL DEFAULT 0 COMMENT '延迟发放开始时间';");
    }
}

if(getcustom('business_score_withdraw')){
    if (!pdo_fieldexists2("ddwx_business_sysset", "business_score_withdraw")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
    ADD COLUMN `business_score_withdraw` tinyint(1) NOT NULL DEFAULT 0 COMMENT '商家积分是否可提现',
    ADD COLUMN `business_score_withdrawfee` decimal(6,2) DEFAULT '0.00' COMMENT '商家积分提现手续费';");
    }
}

if(getcustom('consumer_value_add')){
    //消费增值功能
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_admin_bonuspool_log` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NULL DEFAULT NULL ,
    `mid`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
    `value`  int(11) NULL DEFAULT 0 ,
    `after`  int(11) NULL DEFAULT NULL ,
    `createtime`  int(11) NULL DEFAULT NULL ,
    `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
    `channel`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '变动渠道' ,
    `orderid`  int(11) NULL DEFAULT NULL ,
    `green_score_price`  decimal(12,4) NOT NULL DEFAULT 0.00 ,
    `dif_price` decimal(12,4) NOT NULL DEFAULT '0.0000',
    PRIMARY KEY (`id`),
    INDEX `aid` (`aid`) USING BTREE ,
    INDEX `mid` (`mid`) USING BTREE 
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_consumer_set` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NOT NULL ,
    `green_score_bili`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额赠送绿色积分比例' ,
    `bonus_pool_bili`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额放入奖金池比例' ,
    `cash_fee`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '提现手续费' ,
    `to_commission`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '提现到佣金比例' ,
    `to_score`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '提现到积分比例' ,
    `to_money`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '提现到余额比例' ,
    `min_price`  decimal(12,4) NOT NULL DEFAULT 0.00 ,
    `max_price`  decimal(12,4) NOT NULL DEFAULT 0.00 COMMENT '绿色积分最高价值' ,
    `reward_time`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '0支付完成赠送 1收货后赠送' ,
    `createtime`  int(10) NOT NULL DEFAULT 0 COMMENT '最后修改时间' ,
    `green_score_total`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '平台绿色积分总数量' ,
    `bonus_pool_total`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '平台奖金池总数量' ,
    `green_score_price`  decimal(12,4) NOT NULL DEFAULT 0.00 COMMENT '绿色积分价值' ,
    `withdraw_desc`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '提现说明' ,
    `withdraw_to_pool`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_greenscore_log` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NULL DEFAULT NULL ,
    `mid`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
    `value`  decimal(12,2) NULL DEFAULT 0.00 ,
    `after`  decimal(12,2) NULL DEFAULT NULL ,
    `createtime`  int(11) NULL DEFAULT NULL ,
    `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
    `channel`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '变动渠道' ,
    `orderid`  int(11) NULL DEFAULT NULL ,
    `green_score_price`  decimal(12,4) NOT NULL DEFAULT 0.00 ,
    PRIMARY KEY (`id`),
    INDEX `aid` (`aid`) USING BTREE ,
    INDEX `mid` (`mid`) USING BTREE 
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_greenscore_withdraw_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `mid` int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
      `money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '提现金额',
      `fee` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '手续费',
      `green_score` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '扣除绿色积分',
      `to_commission` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '转入佣金数量',
      `to_money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '转入余额数量',
      `to_score` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '转入积分数量',
      `to_pool` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '回放奖金池',
      `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
      `createtime` int(10) NOT NULL DEFAULT '0' COMMENT '操作时间',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;");
    if (!pdo_fieldexists2("ddwx_member", "green_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `green_score`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '绿色积分';");
    }
    if (!pdo_fieldexists2("ddwx_shop_guige", "give_green_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `give_green_score`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送绿色积分数量';");
    }
    if (!pdo_fieldexists2("ddwx_shop_guige", "give_bonus_pool")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` ADD COLUMN `give_bonus_pool`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送奖金池数量';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order", "give_green_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_green_score`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '收货后赠送绿色积分';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order", "give_bonus_pool")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_bonus_pool`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '收货后赠送奖金池';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order", "give_green_score2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_green_score2`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '支付后赠送绿色积分';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order", "give_bonus_pool2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_bonus_pool2`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '支付后赠送奖金池';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "give_green_score2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `give_green_score2`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '支付后赠送绿色积分';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "give_bonus_pool2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `give_bonus_pool2`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '支付后赠送奖金池';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "give_green_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `give_green_score`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '收货后赠送绿色积分';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "give_bonus_pool")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `give_bonus_pool`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '收货后赠送奖金池';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "give_green_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `give_green_score`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送绿色积分数量';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "give_bonus_pool")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `give_bonus_pool`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送奖金池数量';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "give_green_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `give_green_score`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送绿色积分数量';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "give_bonus_pool")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `give_bonus_pool`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '赠送奖金池数量';");
    }
    if (!pdo_fieldexists2("ddwx_greenscore_withdraw_log", "remain")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_greenscore_withdraw_log` ADD COLUMN `remain`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '剩余数量';");
    }
    if (!pdo_fieldexists2("ddwx_consumer_set", "open_withdraw")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `open_withdraw`  tinyint(1) NULL DEFAULT 1 COMMENT '绿色积分提取开关 0关闭 1开启';");
    }
}
if(getcustom('business_maidan_scoredk')){
    if (!pdo_fieldexists2("ddwx_business", "scoredkmaxset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
    ADD COLUMN `scoredkmaxset` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `scoredkmaxval` decimal(6,2) DEFAULT '0.00' COMMENT '商家积分抵扣比例';");
    }
}

if(getcustom('business_score_withdraw')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_score_withdrawlog` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT NULL,
      `score` int(11) DEFAULT '0',
      `money` decimal(11,2) DEFAULT NULL,
      `txmoney` decimal(11,2) DEFAULT NULL,
      `weixin` varchar(255) DEFAULT NULL,
      `aliaccount` varchar(255) DEFAULT NULL,
      `ordernum` varchar(255) DEFAULT NULL,
      `paytype` varchar(255) DEFAULT NULL,
      `status` tinyint(1) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      `bankname` varchar(255) DEFAULT NULL,
      `bankcarduser` varchar(255) DEFAULT NULL,
      `bankcardnum` varchar(255) DEFAULT NULL,
      `paytime` int(11) DEFAULT NULL,
      `paynum` varchar(255) DEFAULT NULL,
      `reason` varchar(255) DEFAULT '',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;");
}
if(getcustom('product_bonus_pool')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_bonus_pool_category` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      `name` varchar(255) DEFAULT NULL,
      `status` tinyint(1) DEFAULT NULL,
      `gettj` varchar(255) DEFAULT NULL,
      `sort` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    if (!pdo_fieldexists2("ddwx_bonus_pool", "cid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_bonus_pool` ADD COLUMN `cid` int(11) DEFAULT '0';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "bonus_pool_cid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `bonus_pool_cid` int(11) DEFAULT '0' COMMENT '奖金池分类';");
    }
    if (!pdo_fieldexists2("ddwx_shop_sysset", "bonus_pool_member_count")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `bonus_pool_member_count` text COMMENT '奖金池等级对应分红会员数量';");
    }
    if (!pdo_fieldexists2("ddwx_shop_sysset", "bonus_pool_total_mcount")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `bonus_pool_total_mcount` int(11) DEFAULT '0' COMMENT '分红会员总数量';");
    }
}

if(getcustom('business_showshortvideo')){
    if (!pdo_fieldexists2("ddwx_business", "show_shortvideo")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `show_shortvideo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '主页显示短视频 0 : 不显示 1：显示',
            ADD COLUMN `show_shortvideo_num` int NOT NULL DEFAULT 10 COMMENT '主页显示短视频显示数量';");
    }
}

if(getcustom('design_cat_showtj')){
    if (!pdo_fieldexists2("ddwx_designerpage_category", "showtj")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_designerpage_category` 
            ADD COLUMN `showtj` varchar(255) NULL DEFAULT '-1' COMMENT '展示条件 ',
            ADD COLUMN `showtj2` varchar(255) NULL DEFAULT '-1' COMMENT '展示条件2';");
    }
}

if(getcustom('pay_share')){
    if (!pdo_fieldexists2("ddwx_shop_sysset", "share_payment")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `share_payment` tinyint(1) DEFAULT '0' COMMENT '分享后付款 1：开启 0：关闭';");
    }
    if (!pdo_fieldexists2("ddwx_payorder", "share_payment_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` ADD COLUMN `share_payment_status` tinyint(1) DEFAULT '0' COMMENT '分享后付款-分享状态 1：分享成功 0：未分享';");
    }
}

if(getcustom('yuyue_apply')){
	if (!pdo_fieldexists2("ddwx_yuyue_worker","fwcids")) {
		\think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_worker` ADD COLUMN `fwcids` varchar(255) DEFAULT NULL;");
	}
}

if(getcustom('teamfenhong_yejitj')){
    if (!pdo_fieldexists2("ddwx_member_level","teamfenhong_yeji_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
            ADD COLUMN `teamfenhong_yeji_money` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '上月业绩达标',
            ADD COLUMN `teamfenhong_yeji_yunfee` tinyint(1) NOT NULL DEFAULT 1 COMMENT '业绩含运费 0：不含 1：含',
            ADD COLUMN `teamfenhong_yeji_total` tinyint(1) NOT NULL DEFAULT 0 COMMENT '业绩累计 0：不累计 1：累计',
            ADD COLUMN `teamfenhong_yeji_month` int(0) NOT NULL DEFAULT 0 COMMENT '业绩累计几个月',
            ADD COLUMN `teamfenhong_yeji_self` tinyint(1) NOT NULL DEFAULT 0 COMMENT '业绩包含自己 0：不含 1：含';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_teamfenhong_yejizerolog`  (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0,
        `levelid` int(11) NOT NULL DEFAULT 0 COMMENT '等级',
        `total_month` int(11) NOT NULL DEFAULT 0 COMMENT '累计月份',
        `zero_month` int(11) NOT NULL DEFAULT 0 COMMENT '清零月份',
        `createtime` int(11) NOT NULL DEFAULT 0 COMMENT '清零时间',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `levelid`(`levelid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}


if(getcustom('toupiao_add_type')){
    if (!pdo_fieldexists2("ddwx_toupiao","toupiao_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_toupiao` ADD COLUMN `toupiao_type` tinyint(1)  DEFAULT 0 COMMENT '投票类型 0：图片 1：视频';");
    }
    if (!pdo_fieldexists2("ddwx_toupiao_join","videos")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_toupiao_join` ADD COLUMN `videos` text  DEFAULT NULL COMMENT '详情视频';");
    }
}
if(getcustom('product_pickup_device')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_pickup_device` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `name` varchar(255) DEFAULT NULL,
      `device_no` varchar(100) DEFAULT NULL COMMENT '设备号',
      `main_goods_lane` varchar(255) DEFAULT NULL COMMENT '主柜格子数量',
      `deputy_goods_lane` text COMMENT '副柜数据',
      `goods_lane` varchar(100) DEFAULT NULL COMMENT '货道',
      `remark` varchar(255) DEFAULT NULL,
      `province` varchar(100) DEFAULT NULL,
      `city` varchar(100) DEFAULT NULL,
      `district` varchar(100) DEFAULT NULL,
      `address` varchar(255) DEFAULT NULL,
      `longitude` varchar(255) DEFAULT NULL COMMENT '经度',
      `latitude` varchar(255) DEFAULT NULL COMMENT '纬度',
      `createtime` int(11) DEFAULT NULL,
      `sort` int(11) DEFAULT NULL,
      `uid` int(11) DEFAULT '0' COMMENT '接收通知和补货的管理员',
      `device_status` tinyint(1) DEFAULT '0' COMMENT '设备在线状态，随时更新',
      `status` tinyint(1) DEFAULT '1',
      `lack_stock` int(11) DEFAULT '0' COMMENT '用于补货的筛选（不然筛选不了），列表前置更新',
      `buy_url` varchar(255) DEFAULT NULL,
      `guangeiot` varchar(50) DEFAULT NULL COMMENT '厂家',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_pickup_device_addstock` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      `device_name` varchar(255) DEFAULT NULL COMMENT '设备名称',
      `device_no` varchar(255) DEFAULT NULL,
      `device_id` int(11) DEFAULT '0',
      `goodsdata` text COMMENT '补货信息',
      `uid` int(11) DEFAULT '0',
      `add_stock` int(11) DEFAULT NULL COMMENT '补货量',
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE,
      KEY `device_id` (`device_id`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_pickup_device_goods` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `device_id` int(11) DEFAULT '0' COMMENT '设备ID',
      `device_no` varchar(255) DEFAULT NULL,
      `goods_lane` int(11) DEFAULT '0' COMMENT '货道',
      `proid` int(11) DEFAULT '0',
      `ggid` int(11) DEFAULT NULL,
      `sell_price` decimal(10,2) DEFAULT '0.00',
      `pic` varchar(255) DEFAULT NULL COMMENT '货品图片',
      `proname` varchar(255) DEFAULT NULL,
      `ggname` varchar(255) DEFAULT NULL,
      `stock` int(11) DEFAULT '1',
      `real_stock` int(11) DEFAULT '0' COMMENT '实时库存，补货',
      `sales` int(11) DEFAULT '0',
      `createtime` int(1) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `device_id` (`device_id`) USING BTREE,
      KEY `goods_lane` (`goods_lane`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_pickup_device_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(1) DEFAULT NULL,
      `bid` int(11) DEFAULT NULL,
      `title` varchar(255) DEFAULT NULL,
      `appid` varchar(20) DEFAULT NULL,
      `appsecret` varchar(255) DEFAULT NULL,
      `add_stock_remind` tinyint(1) DEFAULT '1' COMMENT '补货提醒',
      `remind_type` varchar(10) DEFAULT NULL COMMENT '补货提醒方式，tmpl,sms',
      `remind_pinlv` varchar(10) DEFAULT NULL COMMENT '补货提醒频率多选，1每件  2库存预警 3每天x点',
      `remind_limit_stock` varchar(255) DEFAULT NULL COMMENT '剩余x件',
      `remind_time` varchar(10) DEFAULT NULL COMMENT '每天x点',
      `status` tinyint(1) DEFAULT '1',
      `guangeiot` text COMMENT '厂家',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    if (!pdo_fieldexists2("ddwx_shop_order","dgid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `dgid` int(11) DEFAULT '0' COMMENT '商品柜的商品id';");
    }
    if (!pdo_fieldexists2("ddwx_mp_tmplset_new","tmpl_device_addstock_remind")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_device_addstock_remind` varchar(64) DEFAULT NULL COMMENT '补货通知';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_device_addstock_remind_st")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` 
            ADD COLUMN `tmpl_device_addstock_remind_st` tinyint(1) DEFAULT '1' COMMENT '设备缺货通知开关',
            ADD COLUMN `tmpl_device_addstock_remind` varchar(50) DEFAULT NULL COMMENT '设备缺货通知';");
    }
    if (!pdo_fieldexists2("ddwx_admin_user","tmpl_device_addstock_remind")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_user` ADD COLUMN `tmpl_device_addstock_remind` tinyint(1) DEFAULT '1' COMMENT '设备缺货通知';");
    }
    if (!pdo_fieldexists2("ddwx_product_pickup_device_set","type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_product_pickup_device_set` ADD COLUMN `type` tinyint(1) DEFAULT '0' COMMENT '0:根据商品有库存的柜门1：固定个柜门';");
    }
    if(pdo_fieldexists2("ddwx_product_pickup_device","uid")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_product_pickup_device` 
        MODIFY COLUMN `uid` varchar(50) DEFAULT NULL COMMENT '接收通知和补货的管理员';");
    }
}

if(getcustom('yx_riddle')) {
    if (!pdo_fieldexists2("ddwx_riddle","title")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_riddle` ADD COLUMN `title`  varchar(64) NULL AFTER `name`;");
    }
}
if(getcustom('yx_riddle_namealias')) {
    if (!pdo_fieldexists2("ddwx_riddle","alias")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_riddle` ADD COLUMN `alias`  varchar(32) NULL AFTER `name`;");
    }
}
if(getcustom('business_apply_form')){
    if(!pdo_fieldexists2("ddwx_business","form_record_id")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `form_record_id` int(11) DEFAULT '0' COMMENT '自定义资料id';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_apply_form` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT '0',
        `bid` int(11) DEFAULT '0',
        `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
        `content` longtext,
        `sort` int(11) DEFAULT '0',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_business_apply_form_record` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `bid` int(11) DEFAULT '0',
        `formid` bigint(20) DEFAULT NULL,
        `content` text COMMENT '自定义表单内容',
        `createtime` int(11) DEFAULT NULL,
        `form0` varchar(255) DEFAULT NULL,
        `form1` varchar(255) DEFAULT NULL,
        `form2` varchar(255) DEFAULT NULL,
        `form3` varchar(255) DEFAULT NULL,
        `form4` varchar(255) DEFAULT NULL,
        `form5` varchar(255) DEFAULT NULL,
        `form6` varchar(255) DEFAULT NULL,
        `form7` varchar(255) DEFAULT NULL,
        `form8` varchar(255) DEFAULT NULL,
        `form9` varchar(255) DEFAULT NULL,
        `form10` varchar(255) DEFAULT NULL,
        `form11` varchar(255) DEFAULT NULL,
        `form12` varchar(255) DEFAULT NULL,
        `form13` varchar(255) DEFAULT NULL,
        `form14` varchar(255) DEFAULT NULL,
        `form15` varchar(255) DEFAULT NULL,
        `form16` varchar(255) DEFAULT NULL,
        `form17` varchar(255) DEFAULT NULL,
        `form18` varchar(255) DEFAULT NULL,
        `form19` varchar(255) DEFAULT NULL,
        `form20` varchar(255) DEFAULT NULL,
        `form21` varchar(255) DEFAULT NULL,
        `form22` varchar(255) DEFAULT NULL,
        `form23` varchar(255) DEFAULT NULL,
        `form24` varchar(255) DEFAULT NULL,
        `form25` varchar(255) DEFAULT NULL,
        `form26` varchar(255) DEFAULT NULL,
        `form27` varchar(255) DEFAULT NULL,
        `form28` varchar(255) DEFAULT NULL,
        `form29` varchar(255) DEFAULT NULL,
        `form30` varchar(255) DEFAULT NULL,
        `form31` varchar(255) DEFAULT NULL,
        `form32` varchar(255) DEFAULT NULL,
        `form33` varchar(255) DEFAULT NULL,
        `form34` varchar(255) DEFAULT NULL,
        `form35` varchar(255) DEFAULT NULL,
        `form36` varchar(255) DEFAULT NULL,
        `form37` varchar(255) DEFAULT NULL,
        `form38` varchar(255) DEFAULT NULL,
        `form39` varchar(255) DEFAULT NULL,
        `form40` varchar(255) DEFAULT NULL,
        `form41` varchar(255) DEFAULT NULL,
        `form42` varchar(255) DEFAULT NULL,
        `form43` varchar(255) DEFAULT NULL,
        `form44` varchar(255) DEFAULT NULL,
        `form45` varchar(255) DEFAULT NULL,
        `form46` varchar(255) DEFAULT NULL,
        `form47` varchar(255) DEFAULT NULL,
        `form48` varchar(255) DEFAULT NULL,
        `form49` varchar(255) DEFAULT NULL,
        `form50` varchar(255) DEFAULT NULL,
        `form51` varchar(255) DEFAULT NULL,
        `form52` varchar(255) DEFAULT NULL,
        `form53` varchar(255) DEFAULT NULL,
        `form54` varchar(255) DEFAULT NULL,
        `form55` varchar(255) DEFAULT NULL,
        `form56` varchar(255) DEFAULT NULL,
        `form57` varchar(255) DEFAULT NULL,
        `form58` varchar(255) DEFAULT NULL,
        `form59` varchar(255) DEFAULT NULL,
        `form60` varchar(255) DEFAULT '',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `bid` (`bid`) USING BTREE,
        KEY `formid` (`formid`) USING BTREE
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
}
if(getcustom('member_levelup_givechild')){
    if (!pdo_fieldexists2("ddwx_member_level", "team_levelup_data")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `team_levelup_data` text COMMENT '可升级会员等级数量';");
    }
    if(!pdo_fieldexists2("ddwx_member_level","team_levelup")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `team_levelup` tinyint(1) DEFAULT '0' COMMENT '是否可以给下级升级';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "team_levelup_data")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        ADD COLUMN `give_team_levelup` tinyint(1) DEFAULT '0' COMMENT '是否购买增加数量',
        ADD COLUMN `team_levelup_data` text COMMENT '可升级会员等级数量';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_levelup_uesnum` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `levelid` int(11) DEFAULT NULL COMMENT '会员当前的级别',
        `levelupid` int(11) DEFAULT '0' COMMENT '可升级的会员等级',
        `num` int(11) DEFAULT '0' COMMENT '当前等级可升级数量',
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE,
        KEY `levelid` (`levelid`) USING BTREE,
        KEY `levelupid` (`levelupid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
}
if(getcustom('luckycollage_memberlevel_limit')){
    if(!pdo_fieldexists2("ddwx_lucky_collage_product","gettj")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `gettj` varchar(100) DEFAULT NULL COMMENT '参与会员等级限制';");
    } 
}
if(getcustom('luckycollage_zjjl')){
    if(!pdo_fieldexists2("ddwx_lucky_collage_product","zj_money_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `zj_money_rate` decimal(10,2) DEFAULT 0 COMMENT '中奖金额比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `zj_money_type` tinyint(3) DEFAULT 2 COMMENT '中奖金额类型1比例2金额';");
    } 
    if(!pdo_fieldexists2("ddwx_lucky_collage_product","zjjlscore_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `zjjlscore_rate` decimal(10,2) DEFAULT 0 COMMENT '中奖积分比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `zjjlscore_type` tinyint(1) DEFAULT 2 COMMENT '中奖积分比例1比例2数量';");
    } 
}
if(getcustom('luckycollage_bzjl')){
    if(!pdo_fieldexists2("ddwx_lucky_collage_product","bzjlscore_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `bzjlscore_rate` decimal(10,2) DEFAULT 0 COMMENT '不中奖积分比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `bzjlscore_type` tinyint(1) DEFAULT 2 COMMENT '不中奖积分比例1比例2数量';");
    } 
}
if(getcustom('luckycollage_score_pay')){
    if(!pdo_fieldexists2("ddwx_lucky_collage_product","pay_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` ADD COLUMN `pay_type` tinyint(1) DEFAULT 0 COMMENT '支付方式0金额1积分';");
    } 
    if(!pdo_fieldexists2("ddwx_lucky_collage_order","is_score_pay")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_order` ADD COLUMN `is_score_pay` tinyint(1) DEFAULT 0 COMMENT '支付方式0金额1积分';");
    } 
    if(!pdo_fieldexists2("ddwx_lucky_collage_order","totalscore")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_order` ADD COLUMN `totalscore` int(11) DEFAULT 0 COMMENT '积分支付数量';");
    } 
}

if(getcustom('member_code_paycode')){
    if(!pdo_fieldexists2("ddwx_member_code_set","code_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_code_set` ADD COLUMN `code_type` tinyint(1) DEFAULT 0 COMMENT '类型0二维码1条形码';");
    }
    if(!pdo_fieldexists2("ddwx_member_code_set", "iswxpay")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_code_set` ADD COLUMN `iswxpay` tinyint(1) DEFAULT 0 COMMENT '是否显示微信付款码';");
    }
    if(!pdo_fieldexists2("ddwx_member", "member_barcode_img")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `member_barcode_img` varchar(200) NULL COMMENT '会员码条形码';");
    }
}

if(getcustom('cashier_overdraft_money_dec')){
    if(!pdo_fieldexists2("ddwx_cashier_order", "dec_overdraft_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order` ADD COLUMN `dec_overdraft_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '信用额度抵扣金额' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order` ADD COLUMN `overdraft_money_dec_rate` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '信用额度抵扣比例' ;");
    }

    if(!pdo_fieldexists2("ddwx_admin_set","overdraft_money_dec")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `overdraft_money_dec` tinyint(1) NOT NULL DEFAULT 0 COMMENT '信用额度抵扣：0 关闭 1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `overdraft_money_dec_rate` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '信用额度最高抵扣比例%';");

    }
    if(!pdo_fieldexists2("ddwx_business","overdraft_money_dec")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `overdraft_money_dec` tinyint(1) NOT NULL DEFAULT 0 COMMENT '余额抵扣：0 关闭 1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_business`  ADD COLUMN `overdraft_money_dec_rate` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '信用额度最高抵扣比例%';");
    }
}

if(getcustom('product_pickup_device_guangeiot')) {
    if (!pdo_fieldexists2("ddwx_product_pickup_device","guangeiot")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_product_pickup_device` ADD COLUMN `guangeiot` varchar(50) DEFAULT NULL COMMENT '厂家';");
    }
    if (!pdo_fieldexists2("ddwx_product_pickup_device_set","guangeiot")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_product_pickup_device_set` ADD COLUMN `guangeiot` text COMMENT '厂家';");
    }
    if (!pdo_fieldexists2("ddwx_product_pickup_device_set","title")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_product_pickup_device_set` 
        ADD COLUMN `title` varchar(255) DEFAULT NULL,
        ADD COLUMN  `appid` varchar(20) DEFAULT NULL,
        ADD COLUMN  `appsecret` varchar(255) DEFAULT NULL;");
    }
}
if(getcustom('yx_queue_free_multi_team')) {
    if (!pdo_fieldexists2("ddwx_queue_free_set","queue_multi_team_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN `queue_multi_team_status` tinyint(1) DEFAULT '0' COMMENT '多队伍排队状态',
            ADD COLUMN `queue_multi_team_people_num` int(11) DEFAULT '0' COMMENT '队伍人数',
            ADD COLUMN `queue_multi_team_min_money` decimal(12,2) DEFAULT '0.00' COMMENT '参与排队最低金额',
            ADD COLUMN `queue_multi_team_repeat_money` decimal(12,2) DEFAULT '0.00' COMMENT '复购金额',
            ADD COLUMN `queue_multi_team_remind_days` int(11) DEFAULT '0' COMMENT '出局前n天提醒',
            ADD COLUMN `queue_multi_team_repeat_datetype` tinyint(1) DEFAULT '1' COMMENT '复购统计时间';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free","multi_team_no")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free` ADD COLUMN `multi_team_no` int(11) DEFAULT '0' COMMENT '多队伍编号';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_queue_free_before_quit_st")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` 
            ADD COLUMN `tmpl_queue_free_before_quit_st` tinyint(1) DEFAULT '1' COMMENT '排队返利，出局前通知开关',
            ADD COLUMN `tmpl_queue_free_before_quit` varchar(50) DEFAULT NULL COMMENT '排队返利，出局前通知';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_queue_free_before_quit_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_queue_free_before_quit_txy` varchar(64) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_mp_tmplset_new","tmpl_queue_free_before_quit")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_queue_free_before_quit` varchar(64) DEFAULT NULL;");
    }
}
if(getcustom('restaurant_cashdesk_auth_enter')) {
    if (!pdo_fieldexists2("ddwx_restaurant_cashdesk","order_cancel_pint")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cashdesk` 
            ADD COLUMN `order_cancel_pint` tinyint(1) DEFAULT '0' COMMENT '订单取消打印',
            ADD COLUMN `order_refund_pint` tinyint(1) DEFAULT '0' COMMENT '订单退款打印';");
    } 
}
if(getcustom('restaurant_shop_guadan_order')) {
    if (!pdo_fieldexists2("ddwx_restaurant_cashdesk","guadan_print")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cashdesk` 
            ADD COLUMN `guadan_print` tinyint(1) DEFAULT '0' COMMENT '挂单打印';");
    }
    if (!pdo_fieldexists2("ddwx_restaurant_shop_order","is_guadan")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` 
            ADD COLUMN `is_guadan` tinyint(1) DEFAULT '0' COMMENT '是否挂单';");
    }
    if (!pdo_fieldexists2("ddwx_restaurant_shop_order_goods","guadan_tag_print")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` 
            ADD COLUMN `guadan_tag_print` tinyint(1) DEFAULT '0' COMMENT '挂单后标签打印1打印0未打印';");
    }
}
if(getcustom('restaurant_shop_pinzhuo')) {
    if (!pdo_fieldexists2("ddwx_restaurant_table", "pinzhuo_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_table`  ADD COLUMN `pinzhuo_status` tinyint(1) DEFAULT '0' COMMENT '拼桌模式0：不开启  1：开启';");
    }
}
if(getcustom('score_transfer_sxf')){
    if(!pdo_fieldexists2("ddwx_admin_set", "score_transfer_sxf")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `score_transfer_sxf` tinyint(1) DEFAULT 0 COMMENT '积分转赠手续费 0：关闭 1：开启';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set", "autoclose_score_transfer")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `autoclose_score_transfer` int(11) DEFAULT '60' COMMENT '下单后多久不支付，转赠积分会自动返还';");
    }
    if (!pdo_fieldexists2("ddwx_member_level", "score_transfer_sxf_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `score_transfer_sxf_ratio` decimal(10, 2) DEFAULT 0 COMMENT '积分转赠手续费比例';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_score_transfer_order`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT 0,
      `mid` int(11) NOT NULL DEFAULT 0 COMMENT '发送用户id',
      `receive_mid` int(11) NOT NULL DEFAULT 0 COMMENT '接收用户mid',
      `score_num` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '转赠数量',
      `transfer_sxf` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '转赠手续费',
      `ordernum` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '订单号',
      `payorderid` int(11) NOT NULL DEFAULT 0,
      `paytypeid` int(11) NOT NULL DEFAULT 0,
      `paytype` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
      `paynum` varchar(60) DEFAULT '',
      `paytime` int(11) UNSIGNED NOT NULL DEFAULT 0,
      `platform` varchar(50) NOT NULL DEFAULT '',
      `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态0：待支付 1：已支付 4 关闭订单',
      `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('active_coin')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_active_coin_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `reward_type` tinyint(1) DEFAULT '0' COMMENT '计算方式 0按利润 1按订单金额',
      `reward_time` varchar(255) DEFAULT '0' COMMENT '赠送时间 0收货完成 1支付完成',
      `auto_order_num` decimal(12,2) DEFAULT '0.00' COMMENT '自动下单数量',
      `auto_order_proids` varchar(255) DEFAULT '0' COMMENT '自动下单商品id',
      `activecoin_ratio` decimal(12,2) DEFAULT '0.00' COMMENT '让利到激活币的数量',
      `createtime` int(10) DEFAULT '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='激活币设置';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_activecoin_log` (
       `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `mid` varchar(100) DEFAULT NULL,
      `value` decimal(12,2) DEFAULT '0.00',
      `after` decimal(12,2) DEFAULT NULL,
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
      `channel` varchar(20) DEFAULT '' COMMENT '变动渠道',
      `orderid` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='会员激活币变动明细';");
    if(!pdo_fieldexists2("ddwx_business", "activecoin_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `activecoin_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '商家让利到激活币比例';");
    }
    if(!pdo_fieldexists2("ddwx_business", "member_activecoin_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `member_activecoin_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '激活币分配到会员比例';");
    }
    if(!pdo_fieldexists2("ddwx_business", "business_activecoin_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `business_activecoin_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '激活币分配到商家比例';");
    }
    if(!pdo_fieldexists2("ddwx_member", "active_coin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `active_coin`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '激活币数量';");
    }
    if(!pdo_fieldexists2("ddwx_member", "active_coin_order")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `active_coin_order`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '上一次自动下单时激活币数量';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product", "teamfenhongwalletset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `teamfenhongwalletset`  tinyint(1) NULL DEFAULT 0 COMMENT '团队分红分配钱包 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product", "teamfenhongwallet")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `teamfenhongwallet`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '团队分红钱包分配';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods", "activecoin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `activecoin`  decimal(12,2) NOT NULL DEFAULT 0 COMMENT '让利数量';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order", "is_coin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `is_coin`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否发放让利 0未发放 1已发放';");
    }
    if(!pdo_fieldexists2("ddwx_maidan_order", "activecoin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `activecoin`  decimal(12,2) NOT NULL DEFAULT 0 COMMENT '让利数量';");
    }
    if(!pdo_fieldexists2("ddwx_maidan_order", "is_coin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `is_coin`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否发放让利 0未发放 1已发放';");
    }
}
if(getcustom('consumer_value_add')){
    if(!pdo_fieldexists2("ddwx_consumer_set", "categoryids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `categoryids`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set", "productids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `productids`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set", "fwtype")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `fwtype`  tinyint(1) NULL DEFAULT 0 COMMENT '0所有商品 1指定类目 2指定商品';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set", "auto_withdraw_beishu")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `auto_withdraw_beishu`  int(11) NULL DEFAULT 0 COMMENT '自动提取倍数';");
    }
    if(!pdo_fieldexists2("ddwx_member_greenscore_log", "remain")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_greenscore_log` ADD COLUMN `remain`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '剩余数量';");
    }
}

if(getcustom('car_hailing')) {
    if (!pdo_fieldexists2("ddwx_car_hailing_product", "car_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_product`  ADD COLUMN `car_num` int(11) DEFAULT '0' COMMENT '每天可供包车的总车辆数';");
    }
    if (!pdo_fieldexists2("ddwx_coupon", "carhailing_productids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon`  ADD COLUMN `carhailing_productids` varchar(100) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_car_hailing_product", "commissionset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_product`  
            ADD COLUMN  `commissionset` tinyint(1) DEFAULT '0',
            ADD COLUMN  `commissiondata1` text,
            ADD COLUMN  `commissiondata2` text,
            ADD COLUMN  `commissiondata3` text;");
    }
    if (!pdo_fieldexists2("ddwx_car_hailing_order", "parent1")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_order`  
            ADD COLUMN  `parent1` int(11) DEFAULT NULL,
            ADD COLUMN  `parent2` int(11) DEFAULT NULL,
            ADD COLUMN  `parent3` int(11) DEFAULT NULL,
            ADD COLUMN  `parent1commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN  `parent2commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN  `parent3commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN  `parent1score` int(11) DEFAULT '0',
            ADD COLUMN  `parent2score` int(11) DEFAULT '0',
            ADD COLUMN  `parent3score` int(11) DEFAULT '0',
            ADD COLUMN  `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放';");
    }
    if (!pdo_fieldexists2("ddwx_car_hailing_product", "fenhongset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_product`  
            ADD COLUMN  `fenhongset` tinyint(1) DEFAULT '1',
            ADD COLUMN  `teamfenhongset` int(2) DEFAULT '0',
            ADD COLUMN  `teamfenhongdata1` text,
            ADD COLUMN  `teamfenhongdata2` text,
            ADD COLUMN  `gdfenhongset` int(2) DEFAULT '0',
            ADD COLUMN  `gdfenhongdata1` text,
            ADD COLUMN  `gdfenhongdata2` text,
            ADD COLUMN  `teamfenhongpjset` int(2) DEFAULT '0',
            ADD COLUMN  `teamfenhongpjdata1` text,
            ADD COLUMN  `teamfenhongpjdata2` text,
            ADD COLUMN  `areafenhongset` int(2) DEFAULT NULL,
            ADD COLUMN  `areafenhongdata1` text,
            ADD COLUMN  `areafenhongdata2` text;");
    }
    if (!pdo_fieldexists2("ddwx_car_hailing_order", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_order` ADD COLUMN  `isfenhong` tinyint(1) DEFAULT '0' COMMENT '是否分红';");
    }
    if (!pdo_fieldexists2("ddwx_car_hailing_product", "feature")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_product` ADD COLUMN  `feature` varchar(255) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_car_hailing_order", "buynum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_order` ADD COLUMN  `buynum` int(11) DEFAULT '1';");
    }
    if (!pdo_fieldexists2("ddwx_car_hailing_set", "tourl")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_car_hailing_set`  ADD COLUMN `tourl` varchar(255) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_coupon", "buycarhailingprogive")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon`  
            ADD COLUMN buycarhailingprogive tinyint(1) DEFAULT '0' COMMENT '购买约车赠送 0:关闭 1：开启',
            ADD COLUMN `buycarhailingproids` text COMMENT '购买约车商品集合',
            ADD COLUMN `carhailing_give_num` text COMMENT '购买约车商品数量';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_carhailing_sucess_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_carhailing_sucess_txy` varchar(64) DEFAULT NULL;");
    }
}

if(getcustom('yx_collage_team_in_team')) {
    if (!pdo_fieldexists2("ddwx_collage_sysset", "team_in_team")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_sysset` 
            ADD COLUMN `team_in_team` tinyint(1) NOT NULL DEFAULT 0 COMMENT '团中团裂变0：关闭 1：开启',
            ADD COLUMN `teaminteam_word` varchar(255) NOT NULL DEFAULT '' COMMENT '拼团详情页自定义提示文字',
            ADD COLUMN `teaminteam_color` varchar(30) NOT NULL DEFAULT '#FFFFFF' COMMENT '文字颜色',
            ADD COLUMN `teaminteam_bgcolor` varchar(30) NOT NULL DEFAULT '#FF3143' COMMENT '文字背景色';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` 
            ADD COLUMN `teaminteam_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '团中团设置 0:关闭 1：开启',
            ADD COLUMN `teaminteam_splitnum` int NOT NULL DEFAULT 0 COMMENT '团中团新团数',
            ADD COLUMN `teaminteam_data` text NULL COMMENT '团中团修改参数',
            ADD COLUMN `teaminteam_commissiontype` tinyint(1) NOT NULL DEFAULT 0 COMMENT '团中团分销方式 0:百分比 1:固定金额' ,
            ADD COLUMN `teaminteam_commission1` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '团中团一级提成金额',
            ADD COLUMN `teaminteam_commission2` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '团中团二级提成金额',
            ADD COLUMN `teaminteam_commission3` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '团中团三级提成金额';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `teampid` int NOT NULL DEFAULT 0 COMMENT '上级团ID';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_teaminteam_commissionlog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL,
        `bid` int(11) NOT NULL DEFAULT 0,
        `mid` int(1) NOT NULL DEFAULT 0,
        `commission` decimal(12, 2) NOT NULL DEFAULT 0.00,
        `residue` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '剩余佣金',
        `frommid` int(11) NOT NULL DEFAULT 0 COMMENT '来源佣ID',
        `orderid` int(11) NOT NULL DEFAULT 0 COMMENT '来源订单id',
        `ordernum` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '来源订单号',
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `remark` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `orderid`(`orderid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '会员团中团佣金记录';");
    if (!pdo_fieldexists2("ddwx_collage_order", "isteaminteam")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `isteaminteam` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否是分裂出来的团';");
    }
}

if(getcustom('yx_mangfan_collage')) {
    if(!pdo_fieldexists2("ddwx_mangfan_set", "good_commission_type3")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mangfan_set` 
            ADD COLUMN `good_commission_type3` tinyint(1) NOT NULL DEFAULT 0,
            ADD COLUMN `collage_list` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '单独设置拼团商品';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order`
            ADD COLUMN `mangfan_commission_type` tinyint(1) NULL DEFAULT 0 COMMENT '盲返佣金类型',
            ADD COLUMN `mangfan_rate` decimal(10, 2) NULL DEFAULT 0 COMMENT '盲返佣金比例',
            ADD COLUMN `is_mangfan` tinyint(1) NULL DEFAULT 0 COMMENT '是否是盲返商品 0 否 1 是';");
    }

    if(!pdo_fieldexists2("ddwx_mangfan_order_list", "type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mangfan_order_list` ADD COLUMN `type` varchar(50) NOT NULL DEFAULT 'shop' COMMENT '订单类型';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_mangfan_commission_record` 
            ADD COLUMN `type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'shop' COMMENT '订单类型',
            ADD COLUMN `from_type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'shop';");
    }

    if(!pdo_fieldexists2("ddwx_mangfan_set", "selcollage")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mangfan_set` 
            ADD COLUMN `selcollage` tinyint(1) NOT NULL DEFAULT 0 COMMENT '选择多人拼团 0：关闭 1：开启',
            ADD COLUMN `collage_commission_type` tinyint(1) NOT NULL COMMENT '多人拼团佣金列席';");
    }
}


if(getcustom('yx_queue_free_collage')) {
    if(!pdo_fieldexists2("ddwx_collage_product", "queue_free_status")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` 
            ADD COLUMN `queue_free_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '排队免单设置 -1 不参与 0 ：跟随系统设置 1：单独设置返利比例',
            ADD COLUMN `queue_free_rate_back` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '返利比例',
            ADD COLUMN `queue_free_data` text NULL COMMENT '排队修改参数';");
    }
}

if(getcustom('yx_queue_free_moneypayjoin')) {
    if(!pdo_fieldexists2("ddwx_queue_free_set", "moneypayjoin")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `moneypayjoin` tinyint(1) NOT NULL DEFAULT 1 COMMENT '余额支付是否参与排队 0：不参与排队 1：参与排队';");
    }
}

if(getcustom('business_category_feepercent')) {
    if(!pdo_fieldexists2("ddwx_business_category", "feepercent")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_category` ADD COLUMN `feepercent` decimal(10, 2) NULL DEFAULT NULL COMMENT '抽成费率';");
    }
}

if(getcustom('yx_cashback_collage_alongeset')) {
    if(!pdo_fieldexists2("ddwx_cashback", "collage_back_data")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `collage_back_data` text NULL COMMENT '多人拼团自定义比例';");
    }
}

if(getcustom('yx_cashback_maidan')) {
    if(!pdo_fieldexists2("ddwx_cashback", "maidan_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` 
            ADD COLUMN `maidan_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '买单类型 0：全部 1：仅平台 2：仅商户',
            ADD COLUMN `maidan_minpaymoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '买单支付金额范围最低额',
            ADD COLUMN `maidan_maxpaymoney` decimal(10, 2) NOT NULL  DEFAULT 0 COMMENT '买单支付金额范围最高额' ;");
    }
}

if(getcustom('business_user_group')) {
    if(!pdo_fieldexists2("ddwx_admin_user_group", "type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_user_group` ADD COLUMN `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型 0：默认 1：仅商家可以';");
    }
}

if(getcustom('express_maiyatian_autopush')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_peisong_myt_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `bid` int(11) NOT NULL DEFAULT 0,
        `autopush_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '同城配送订单自动推送 0：关闭 1：开启',
        `autopush_start_hours` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '00:00:00' COMMENT '推送订单时间开始时间',
        `autopush_end_hours` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '00:00:00' COMMENT '推送订单时间结束时间',
        `autopush_scenes` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '推送场景',
        `autopush_timedata` text NULL COMMENT '时间范围',
        `createtime` int(11) NOT NULL DEFAULT 0,
        `updatetime` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '麦芽田配送单独设置';");
    
    if(!pdo_fieldexists2("ddwx_peisong_myt_set", "autopush_timedata")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_peisong_myt_set` ADD COLUMN `autopush_timedata` text NULL COMMENT '时间范围';");
    }
}

if(getcustom('yx_money_monthsend')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_recharge_order_monthsend_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `mid` int(11) NULL DEFAULT 0,
        `orderid` int(11) NULL DEFAULT 0,
        `month_sendmoney` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '立即返还余额',
        `month_sendscore` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '立即返还积分',
        `month_sendmoney2` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '每月返还余额',
        `month_sendscore2` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '每月返还积分',
        `month_sendnum` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '返还多少个月',
        `sendmoney` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '每月已返还余额',
        `sendscore` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '每月已返还积分',
        `sendnum` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '已返还多少个月',
        `allsendmoney` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '总共返还余额',
        `allsendscore` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '总共立即返还积分',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0：待返还 1：已返还',
        `createtime` int(10) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(10) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '充值每个月返还';");
}

if(getcustom('system_nologin_day')) {
    if(!pdo_fieldexists2("ddwx_admin_set", "nologin_day")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `nologin_day` int(11) NOT NULL DEFAULT 7 COMMENT '前端免登录时间';");
    }
}

if(getcustom('yx_collage_orderrefund_wifiprint')) {
    if(!pdo_fieldexists2("ddwx_collage_sysset", "refund_wifiprint")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_sysset` ADD COLUMN `refund_wifiprint` tinyint(1) NOT NULL DEFAULT 0 COMMENT '申请退款自动打印小票 0：关闭 1：开启';");
    }
}
if(getcustom('yx_buy_fenhong')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_buy_fenhong_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT NULL,
      `status` tinyint(1) DEFAULT '0',
      `buy_money` varchar(255) DEFAULT NULL COMMENT '消费金额',
      `times_score` int(11) DEFAULT '1' COMMENT 'n倍积分',
      `ratio` decimal(11,2) DEFAULT '0.00' COMMENT '分红比例',
      `deduct_score` varchar(255) DEFAULT NULL COMMENT '扣除百分比积分',
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_buy_fenhong_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT '0',
      `score_weight` int(11) DEFAULT NULL COMMENT '变动权',
      `score` int(11) DEFAULT NULL COMMENT '变动积分',
      `commission` decimal(11,2) DEFAULT '0.00',
      `type` varchar(20) DEFAULT NULL,
      `orderid` int(11) DEFAULT NULL,
      `ordernum` varchar(100) DEFAULT NULL,
      `ordermoney` decimal(11,2) DEFAULT '0.00',
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if(!pdo_fieldexists2("ddwx_member", "buy_fenhong_score_weight")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
        ADD COLUMN `buy_fenhong_score_weight` int(11) DEFAULT '0' COMMENT '消费加权分红 积分权';");
    }
}

if(getcustom('shop_label')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_label` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `bid` int(11) NOT NULL DEFAULT 0,
        `name` varchar(100) NULL DEFAULT '',
        `status` int(1) NULL DEFAULT 1,
        `sort` int(11) NULL DEFAULT 1,
        `limitbuy` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否限制购买 0：关闭 1：开启',
        `remark` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '备注',
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '商品标签';");
    if(!pdo_fieldexists2("ddwx_shop_product", "labelid")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `labelid` varchar(50) NOT NULL DEFAULT '' COMMENT '商品标签ID',
            ADD COLUMN `labelbgcolor` varchar(20) NOT NULL DEFAULT '' COMMENT '商品标签背景色',
            ADD COLUMN `labelcolor` varchar(20) NOT NULL DEFAULT '' COMMENT '商品标签字体色';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `labelid` text NULL COMMENT '商品标签';");
    }
}

if(getcustom('extend_staff')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_staff` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `bid` int(11) NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0 COMMENT '关联的会员id',
        `realname` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '姓名',
        `tel` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '手机号',
        `commission_rate` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '提成比例',
        `commission` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '提成',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0：关闭 1：开启',
        `sort` int(11) NOT NULL DEFAULT 0,
        `createtime` bigint(20) NOT NULL DEFAULT 0,
        `updatetime` bigint(20) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '员工';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_staff_commission_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `bid` int(11) NULL DEFAULT 0,
        `sid` int(11) NOT NULL DEFAULT 0 COMMENT '员工id',
        `commission` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '提成',
        `after` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '变更后',
        `commission_rate` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '提成比例',
        `orderid` int(11) NOT NULL DEFAULT 0,
        `totalprice` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
        `type` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'cashier' COMMENT '订单类型，默认收银台',
        `uid` int(11) NULL DEFAULT 0 COMMENT '操作付款人',
        `remark` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '备注',
        `createtime` bigint(20) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '员工提成记录';");
    if(!pdo_fieldexists2("ddwx_cashier_order", "staffid")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order` 
            ADD COLUMN `staffid` int NOT NULL DEFAULT 0 COMMENT '员工id',
            ADD COLUMN `staff_commission` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '员工提成',
            ADD COLUMN `staff_commission_rate` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '员工提成比例';");
    }
}
if(getcustom('member_commission_max_toscore')){
    if(!pdo_fieldexists2("ddwx_admin_set", "member_commission_max_toscore_st")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `member_commission_max_toscore_st` tinyint(1) DEFAULT NULL COMMENT '佣金上限转积分开关',
        ADD COLUMN `member_commission_max_toscore_ratio` decimal(11,2) DEFAULT '0.00' COMMENT '佣金上限转积分比例';");
    }
}
if(getcustom('scoredk_percent_category')){
    if(!pdo_fieldexists2("ddwx_shop_category", "scoredkmaxset")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_category`  
            ADD COLUMN `scoredkmaxval` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `scoredkmaxset` tinyint(1) DEFAULT '0';");
    }
}

if (getcustom('member_money_weishu')){
    if(pdo_fieldexists2("ddwx_member","money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member`  MODIFY COLUMN `money` decimal(17,6) DEFAULT '0.000000';");
    }
    if(pdo_fieldexists2("ddwx_member","totalmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member`  MODIFY COLUMN `totalmoney` decimal(17,6) DEFAULT '0.000000';");
    }
    if(pdo_fieldexists2("ddwx_member_moneylog","money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_moneylog`  MODIFY COLUMN `money` decimal(17,6)  DEFAULT '0.000000';");
    }
    if(pdo_fieldexists2("ddwx_member_moneylog","after")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_moneylog`  MODIFY COLUMN `after` decimal(17,6)  DEFAULT '0.000000';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","member_money_weishu")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`  ADD COLUMN `member_money_weishu` tinyint(1) DEFAULT '2' COMMENT '会员余额 位数';");
    }
}
if(getcustom('member_shop_favorite')){
    if(!pdo_fieldexists2("ddwx_member_favorite", "price")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_favorite` ADD COLUMN `price` decimal(11,2) DEFAULT '0.00';");
    }
}

if(getcustom('maidan_fenhong_new')){
    if(!pdo_fieldexists2("ddwx_maidan_order","isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `isfenhong` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否分红 0：否 1：是';");
    }
    if(!pdo_fieldexists2("ddwx_business","maidan_teamfenhongdata1")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `maidan_teamfenhongdata1` text NULL COMMENT '买单团队分红比例数据',
            ADD COLUMN `maidan_gudongfenhongdata1` text NULL COMMENT '买单股东分红比例数据',
            ADD COLUMN `maidan_areafenhongdata1` text NULL COMMENT '买单股东分红比例数据' ;");
    }
}

if(getcustom('sound')){
    if(!pdo_fieldexists2("ddwx_sound","custom_content_shop")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_sound` 
            ADD COLUMN `custom_content_shop` text NULL COMMENT '商城自定义内容',
            ADD COLUMN `custom_content_collage` text NULL COMMENT '拼团自定义内容';");
    }
}
if(getcustom('yx_team_yeji_include_self')){
    if(!pdo_fieldexists2("ddwx_team_yeji_set","include_self")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_team_yeji_set` ADD COLUMN `include_self` tinyint(1) DEFAULT '0' COMMENT '团队业绩包含自己';");
    }
}
if(getcustom('yx_team_yeji_jicha')){
    if(!pdo_fieldexists2("ddwx_team_yeji_set","is_jicha")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_team_yeji_set` ADD COLUMN `is_jicha` tinyint(1) DEFAULT '0' COMMENT '是否开启级差';");
    }
}

if(getcustom('member_level_parent_not_commission')){
    if(!pdo_fieldexists2("ddwx_member_level","parent_not_commission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `parent_not_commission` tinyint(1) DEFAULT '0' COMMENT '上级无分销奖励 ';");
    }
}

if(getcustom('member_money_weishu')){
    if(pdo_fieldexists2("ddwx_cashback_member_log","cashback_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member` MODIFY COLUMN `cashback_money` decimal(17,6) DEFAULT '0.000000';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member_log` MODIFY COLUMN `cashback_money` decimal(17,6) DEFAULT '0.000000';");
    }
}
if(getcustom('member_money_weishu') && (getcustom('yx_cashback_time') || getcustom('yx_cashback_stage'))){
    if(pdo_fieldexists2("ddwx_shop_order_goods_cashback","allmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` 
            MODIFY COLUMN `allmoney` decimal(17,6) DEFAULT '0.000000',
            MODIFY COLUMN `money` decimal(17,6) DEFAULT '0.000000',
            MODIFY COLUMN `moneyave` decimal(17,6) DEFAULT '0.000000';");
    }
}
if(getcustom('fenhong_money_weishu')){
    if(pdo_fieldexists2("ddwx_cashback_member_log","commission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member` MODIFY COLUMN `commission` decimal(17,6) DEFAULT '0.000000';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member_log` MODIFY COLUMN `commission` decimal(17,6) DEFAULT '0.000000';");
    }
}
if(getcustom('fenhong_money_weishu') && (getcustom('yx_cashback_time') || getcustom('yx_cashback_stage'))){
    if(pdo_fieldexists2("ddwx_shop_order_goods_cashback","allcommission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` 
            MODIFY COLUMN `allcommission` decimal(17,6) DEFAULT '0.000000',
            MODIFY COLUMN `commission` decimal(17,6) DEFAULT '0.000000',
            MODIFY COLUMN `commissionave` decimal(17,6) DEFAULT '0.000000';");
    }
}
if(getcustom('score_weishu')){
    if(!pdo_fieldexists2("ddwx_cashback_member_log","score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member` MODIFY COLUMN `score` decimal(12,3) DEFAULT '0.000';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member_log` MODIFY COLUMN `score` decimal(17,6) DEFAULT '0.000000';");
    }
}
if(getcustom('score_weishu') && (getcustom('yx_cashback_time') || getcustom('yx_cashback_stage'))){
    if(pdo_fieldexists2("ddwx_shop_order_goods_cashback","allscore")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` 
            MODIFY COLUMN `allscore` decimal(17,6) DEFAULT '0.000000',
            MODIFY COLUMN `score` decimal(17,6) DEFAULT '0.000000',
            MODIFY COLUMN `scoreave` decimal(17,6) DEFAULT '0.000000';");
    }
}
if(getcustom('yx_team_yeji_include_self')){
    if(!pdo_fieldexists2("ddwx_team_yeji_set","include_self")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_team_yeji_set` ADD COLUMN `include_self` tinyint(1) DEFAULT '0' COMMENT '团队业绩包含自己';");
    }
}
if(getcustom('yx_team_yeji_jicha')){
    if(!pdo_fieldexists2("ddwx_team_yeji_set","is_jicha")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_team_yeji_set` ADD COLUMN `is_jicha` tinyint(1) DEFAULT '0' COMMENT '是否开启级差';");
    }
}


if(getcustom('h5zb')){
    if(!pdo_fieldexists2("ddwx_shop_order","roomid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `roomid`  int(11) NULL DEFAULT 0;");
    }
    
    if(!pdo_fieldexists2("ddwx_shop_order_goods","roomid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `roomid`  int(11) NULL DEFAULT 0;");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_watch_record` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      `roomid` int(11) DEFAULT '0' COMMENT '直播间id',
      `mid` int(11) DEFAULT '0',
      `isend` tinyint(1) DEFAULT '0' COMMENT '是否完播',
      `num` int(11) DEFAULT '0',
      `play_time` decimal(11,2) DEFAULT '0.00',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `roomid` (`bid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `app_name` varchar(64) DEFAULT '',
      `app_key` varchar(64) DEFAULT '',
      `push_domain` varchar(255) DEFAULT '',
      `pull_domain` varchar(255) DEFAULT '',
      `createtime` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `expire_days` int(4) DEFAULT '0' COMMENT '过期天数',
      `license_url` varchar(255) DEFAULT '',
      `license_key` varchar(255) DEFAULT '',
      `redbag_to` tinyint(2) DEFAULT '0' COMMENT '0红包发放至余额 1微信转账',
      `iconlist` text,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    if(!pdo_fieldexists2("ddwx_h5zb_set","quality")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_h5zb_set` ADD COLUMN `quality`  varchar (32) NULL DEFAULT '';");
    }
    if(!pdo_fieldexists2("ddwx_h5zb_set","app_push_key")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_h5zb_set`
        ADD COLUMN `app_pull_key`  varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
        ADD COLUMN `app_push_key`  varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
        ADD COLUMN `pull_isauth`  tinyint(2) NULL DEFAULT 0,
        ADD COLUMN `push_isauth`  tinyint(2) NULL DEFAULT 0;");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_room_verify` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      `roomid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_room_record` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) DEFAULT '' COMMENT '题目解析',
      `nickname` varchar(64) DEFAULT '',
      `headimg` varchar(255) DEFAULT '',
      `roomid` int(11) DEFAULT '0',
      `eventid` int(11) DEFAULT '0',
      `eventdata` text,
      `type` varchar(32) DEFAULT '',
      `mid` int(11) DEFAULT '0',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_message` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT NULL,
      `uid` int(11) DEFAULT '0',
      `nickname` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
      `headimg` varchar(255) DEFAULT NULL,
      `unickname` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
      `uheadimg` varchar(255) DEFAULT NULL,
      `tel` varchar(255) DEFAULT NULL,
      `msgtype` varchar(255) DEFAULT NULL,
      `content` text,
      `mediaid` varchar(255) DEFAULT NULL,
      `createtime` int(11) DEFAULT NULL,
      `isreply` tinyint(1) DEFAULT '0' COMMENT '0 客户发的 1 客服回复的',
      `isread` tinyint(1) DEFAULT '0',
      `platform` varchar(100) DEFAULT 'mp',
      `iswx` tinyint(1) DEFAULT '0',
      `roomid` int(11) DEFAULT '0',
      `status` tinyint(2) DEFAULT '1',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `createtime` (`createtime`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_member_blacklist` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT NULL,
      `type` tinyint(1) DEFAULT '1' COMMENT '1 禁言 2拉黑',
      `roomid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `roomid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_live_room_product` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `roomid` int(11) DEFAULT '0',
      `proid` int(11) DEFAULT NULL,
      `status` tinyint(4) DEFAULT '0' COMMENT '0 未上架 1已上架',
      `createtime` int(11) DEFAULT NULL,
      `updatetime` int(11) DEFAULT NULL,
      `istop` tinyint(1) DEFAULT '0',
      `online_time` decimal(10,2) DEFAULT '0.00' COMMENT '上架时间 分钟，开播多长时间后自动上架',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_live_room` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `name` varchar(64) DEFAULT '',
      `push_webrtc` varchar(255) DEFAULT '' COMMENT 'webrtc 推流地址',
      `pull_webrtc` varchar(255) DEFAULT '',
      `streamname` varchar(255) DEFAULT NULL,
      `pic` varchar(255) DEFAULT NULL,
      `starttime` int(11) DEFAULT NULL,
      `endtime` int(11) DEFAULT NULL,
      `live_status` tinyint(1) DEFAULT '0',
      `type` tinyint(1) DEFAULT '0' COMMENT '直播类型，1 推流 0 手机直播',
      `screen_type` tinyint(1) DEFAULT '0' COMMENT '1：横屏 0：竖屏',
      `close_like` tinyint(1) DEFAULT '0' COMMENT '是否 关闭点赞 1 关闭',
      `close_goods` tinyint(1) DEFAULT '0' COMMENT '是否 关闭商品货架，1：关闭',
      `pinglun_banned` tinyint(1) DEFAULT '0' COMMENT '是否开启评论，1：关闭',
      `closereplay` tinyint(1) DEFAULT '0' COMMENT '是否关闭回放 1 关闭',
      `closeshare` tinyint(1) DEFAULT '0' COMMENT '是否关闭分享 1 关闭',
      `qrcode_url` varchar(255) DEFAULT NULL COMMENT '小程序直播 小程序码',
      `sort` int(11) DEFAULT '0',
      `status` tinyint(1) DEFAULT '1',
      `createtime` int(11) DEFAULT NULL,
      `mid` int(11) DEFAULT '0' COMMENT '主播mid',
      `expire_time` int(11) DEFAULT NULL COMMENT '过期时间',
      `content` longtext,
      `video_url` text,
      `video_time` decimal(10,2) DEFAULT '0.00',
      `gl_mids` varchar(255) DEFAULT '' COMMENT '管理员mid',
      `watch_num` int(11) DEFAULT '0',
      `see_num` int(11) DEFAULT '0',
      `finish_num` int(11) DEFAULT '0',
      `is_verify` tinyint(2) DEFAULT '0',
      `verify_code` varchar(16) DEFAULT '',
      `send_coupon_num` int(11) DEFAULT '0',
      `send_score_num` int(11) DEFAULT '0',
      `send_dati_num` int(11) DEFAULT '0',
      `send_redbag_num` int(11) DEFAULT '0',
      `send_score_total` int(11) DEFAULT '0',
      `send_redbag_total` decimal(10,2) DEFAULT '0.00',
      `pic2` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_event_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) DEFAULT '' COMMENT '题目解析',
      `mid` int(11) DEFAULT '0',
      `roomid` int(11) DEFAULT '0',
      `type` tinyint(2) DEFAULT '0',
      `eventid` int(11) DEFAULT '0',
      `status` tinyint(1) DEFAULT '0',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_event_record` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) DEFAULT '',
      `mid` int(11) DEFAULT '0',
      `roomid` int(11) DEFAULT '0',
      `type` tinyint(2) DEFAULT '0',
      `eventid` int(11) DEFAULT '0',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_event_log` (
     `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      `eventid` varchar(255) DEFAULT '' COMMENT '题目解析',
      `type` tinyint(2) DEFAULT '0',
      `num` int(11) DEFAULT '0',
      `roomid` int(11) DEFAULT '0',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_h5zb_event` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `name` varchar(255) DEFAULT NULL,
      `sort` int(11) DEFAULT '0',
      `status` int(1) DEFAULT '1',
      `createtime` int(11) DEFAULT NULL,
      `content` longtext,
      `roomid` int(11) DEFAULT '0' COMMENT '直播间id',
      `remark` varchar(255) DEFAULT '' COMMENT '题目解析',
      `right_index` tinyint(4) DEFAULT '-1',
      `show_time` decimal(10,2) DEFAULT '0.00',
      `redbag_num` int(11) DEFAULT '0',
      `redbag_type` tinyint(2) DEFAULT '1',
      `redbag_money` decimal(10,2) DEFAULT '0.00',
      `redbag_min` decimal(10,2) DEFAULT '0.00',
      `redbag_max` decimal(10,2) DEFAULT '0.00',
      `dati_status` tinyint(1) DEFAULT '1',
      `type` tinyint(3) DEFAULT '0' COMMENT '0 答题红包 1仅答题 2仅红包',
      `coupon_id` int(11) DEFAULT '0',
      `coupon_num` int(11) DEFAULT '0',
      `give_score` int(11) DEFAULT '0',
      `score_num` int(11) DEFAULT '0',
      `gap_time` decimal(10,2) DEFAULT '0.00',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

}
if(getcustom('teamfenhong_jicha_add_pj')){
    if(!pdo_fieldexists2("ddwx_admin_set","teamfenhong_jicha_add_pj")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `teamfenhong_jicha_add_pj` tinyint(1) DEFAULT '0' COMMENT '团队分红级差减掉平级奖 0否 1是';");
    }
}
if(getcustom('admin_user_sxpay_merchant')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_sxpay_merchant_set` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `mchname` varchar(60) DEFAULT NULL COMMENT '服务商名称',
  `orgId` varchar(60) DEFAULT NULL COMMENT '机构号',
  `orgIdOne` varchar(60) DEFAULT NULL COMMENT '一级机构号',
  `publicKey` text COMMENT '公钥',
  `privateKey` text COMMENT '私钥',
  `signType` varchar(60) DEFAULT NULL COMMENT '密钥类型RSA,RSA2',
  `feepercent` varchar(10) DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `aid` (`aid`),
  KEY `orgId` (`orgId`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}
if(getcustom('network_slide_down_max')){
    if(!pdo_fieldexists2("ddwx_member_level","slide_down_max")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `slide_down_max` int(11) DEFAULT '0' COMMENT '公排滑落下级接收人数上限';");
    }
    if(!pdo_fieldexists2("ddwx_member","slide_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `slide_num` int(11) DEFAULT '0' COMMENT '上级滑落过来的人数';");
    }
}

if(getcustom('yuyue_gobusiness')){
    if(!pdo_fieldexists2("ddwx_yuyue_product","gobids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` ADD COLUMN `gobids` text NULL COMMENT '服务商家';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_order` 
            ADD COLUMN `fwbid` int NOT NULL DEFAULT 0 COMMENT '到多商户家id',
            ADD COLUMN `fwbusiness` text NULL COMMENT '到多商户家信息';");
    }
}
if(getcustom('yuyue_arearange')){
    if(!pdo_fieldexists2("ddwx_yuyue_product","selmap")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` 
            ADD COLUMN `selmap` tinyint(1) NOT NULL DEFAULT 0 COMMENT '地图选择 0：关闭 1：开启',
            ADD COLUMN `province` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
            ADD COLUMN `city` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `province`,
            ADD COLUMN `district` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `city`,
            ADD COLUMN `peisong_lng` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
            ADD COLUMN `peisong_lat` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `peisong_lng`,
            ADD COLUMN `peisong_range` int(11) NULL DEFAULT NULL AFTER `peisong_lat`,
            ADD COLUMN `peisong_rangetype` tinyint(1) NULL DEFAULT 0 AFTER `peisong_range`,
            ADD COLUMN `peisong_rangepath` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL AFTER `peisong_rangetype`,
            ADD COLUMN `peisong_lng2` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `peisong_rangepath`,
            ADD COLUMN `peisong_lat2` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `peisong_lng2`;");
    }
}
if(getcustom('yuyue_datetype1_autoendorder')){
    if(!pdo_fieldexists2("ddwx_yuyue_product","datetype1_autoendorder")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` ADD COLUMN `datetype1_autoendorder` tinyint(1) NOT NULL DEFAULT 0 COMMENT '可预约时间段 订单完成 0：手动完成 1：自动完成' ;");
    }
    if(!pdo_fieldexists2("ddwx_yuyue_order","yyendtime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_order` 
            ADD COLUMN `yyendtime` bigint(20) NOT NULL DEFAULT 0 COMMENT '预约截止结束时间',
            ADD COLUMN `datetype` tinyint(1) NOT NULL DEFAULT 0 COMMENT '时间类型 0：无 1 时间段 2 时间点',
            ADD COLUMN `datetype1_autoendorder` tinyint(1) NOT NULL DEFAULT 0 COMMENT '可预约时间段 订单完成 0：手动完成 1：自动完成' ;");
    }
}
if(getcustom('yuyue_datetype1_model')){
    if(!pdo_fieldexists2("ddwx_yuyue_product","datetype1_model")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` 
            ADD COLUMN `datetype1_model` tinyint(1) NOT NULL DEFAULT 0 COMMENT '可预约时间段模式 0：模式一 1：模式二',
            ADD COLUMN `zaowanhours` text NULL COMMENT '可预约时间段模式二数据';");
    }
    think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` MODIFY COLUMN `zaowanhours` text NULL COMMENT '可预约时间段模式二数据';");
}
if(getcustom('yuyue_canceltime')){
    if(!pdo_fieldexists2("ddwx_yuyue_product","canceltime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` ADD COLUMN `canceltime` int NOT NULL DEFAULT 0 COMMENT '可取消时间';");
    }
}
if(getcustom('yuyue_datetype1_model_selnum')){
    if(!pdo_fieldexists2("ddwx_yuyue_product","datetype1_modelselnum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` ADD COLUMN `datetype1_modelselnum` int NOT NULL DEFAULT 1 COMMENT '时间段起定量';");
    }
    if(!pdo_fieldexists2("ddwx_yuyue_order","yy_times")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_order` 
            ADD COLUMN `yy_times` text NULL COMMENT '服务时间数组',
            ADD COLUMN `yydates` text NULL COMMENT '服务时间原数组' AFTER `yy_times`;;");
    }
}
if(getcustom('yuyue_before_starting')){
    if(!pdo_fieldexists2("ddwx_yuyue_set","serverbefore_notice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_set` 
            ADD COLUMN `serverbefore_notice` tinyint(1) NOT NULL DEFAULT 0 COMMENT '服务前通知 0 不通知 1 通知用户 2 通知服务人员 3 都通知',
            ADD COLUMN `noticedata` text NULL COMMENT '通知时间';");
    }
    if(!pdo_fieldexists2("ddwx_yuyue_order","sendnotice_time")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_order` ADD COLUMN `sendnotice_time` bigint NOT NULL DEFAULT 0 COMMENT '发生通知时间';");
    }
    if(!pdo_fieldexists2("ddwx_wx_tmplset","tmpl_yuyue_before_starting")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wx_tmplset` ADD COLUMN `tmpl_yuyue_before_starting` varchar(60) NULL DEFAULT '' COMMENT '预约服务 服务开始前通知';");
    }
    if(!pdo_fieldexists2("ddwx_mp_tmplset_new","tmpl_yuyue_before_starting")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_yuyue_before_starting` varchar(60) NULL DEFAULT '' COMMENT '预约服务 服务开始前通知';");
    }
    if (!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_yuyue_before_starting_st")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` 
            ADD COLUMN `tmpl_yuyue_before_starting_st` tinyint(1) DEFAULT '1' COMMENT '服务前通知开关',
            ADD COLUMN `tmpl_yuyue_before_starting` varchar(50) DEFAULT NULL COMMENT '服务前通知';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_yuyue_before_starting_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_yuyue_before_starting_txy` varchar(64) DEFAULT NULL;");
    }
}
if(getcustom('system_webhelp_lookrange')){
    if(!pdo_fieldexists2("ddwx_help","lookrange")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_help` 
            ADD COLUMN `lookrange` tinyint(1) NOT NULL DEFAULT 0 COMMENT '查看范围 0：全部 1：仅平台 2平台+分账户 3 仅商户 4 商户加分账户',
            ADD COLUMN `lookuids` text NULL COMMENT '查看账号' ;");
    }
}
if(getcustom('yuyue_scoredk')){
    if(!pdo_fieldexists2("ddwx_yuyue_order","scoredk_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_order` 
            ADD COLUMN `scoredk_money` float(11, 2) NULL DEFAULT NULL,
            ADD COLUMN `scoredkscore` int(11) NULL DEFAULT 0 ;");
    }
    if(!pdo_fieldexists2("ddwx_yuyue_product","scoredkmaxset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` 
            ADD COLUMN `scoredkmaxset` tinyint(1) NOT NULL DEFAULT 0 COMMENT '积分抵扣单独设置 0：跟随系统',
            ADD COLUMN `scoredkmaxval` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '积分最多抵扣';");
    }
}
if(getcustom('yx_collage_team_in_team')){
    if(!pdo_fieldexists2("ddwx_business_sysset","commission_teaminteam_kouchu")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `commission_teaminteam_kouchu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '商家团中团返款是否扣除分销佣金';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_record` ADD COLUMN `isteaminteam` tinyint(1) NOT NULL DEFAULT 0 COMMENT '拼团团中团佣金 0：否 1：是';");
    }
}

if(getcustom('member_level_zhitui_number_limit')){
    if(!pdo_fieldexists2("ddwx_member_level","zt_member_limit")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `zt_member_limit` int(11) NULL DEFAULT 0 COMMENT '直推会员数量限制';");
    }
}
if(getcustom('extend_fish_pond')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_fishpond_product` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '名称',
      `pic` varchar(255) DEFAULT '' COMMENT '图片',
      `pics` varchar(5000) DEFAULT NULL COMMENT '图片集',
      `detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '详情',
      `sell_price` float(11,2) DEFAULT '0.00' COMMENT '售价',
      `sort` int(11) DEFAULT '0' COMMENT '排序',
      `status` int(1) DEFAULT '1' COMMENT '状态 0：下架 1：上架',
      `stock` int(11) unsigned DEFAULT '100' COMMENT '库存',
      `sales` int(11) DEFAULT '0' COMMENT '已售数量',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      `guigedata` text COMMENT '规格数据',
      `basan` text COMMENT '钓点',
      `tel` char(11) COMMENT '电话',
      `address` varchar(100)  COMMENT '地址',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE,
      KEY `status` (`status`) USING BTREE,
      KEY `stock` (`stock`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_fishpond_guige` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `proid` int(11) DEFAULT NULL COMMENT '商品ID',
      `name` varchar(255) DEFAULT NULL COMMENT '规格名称',
      `hour` decimal(10,1) DEFAULT '0.0' COMMENT '小时',
      `sell_price` decimal(11,2) DEFAULT '0.00' COMMENT '销售价',
      `pic` varchar(255) DEFAULT NULL COMMENT '规格图片',
      `stock` int(11) unsigned DEFAULT '0' COMMENT '库存',
      `sales` int(11) DEFAULT '0' COMMENT '已售数量',
      `ks` varchar(255) DEFAULT NULL COMMENT '规格结构',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_fishpond_basan` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `fid` int(11) DEFAULT NULL COMMENT '鱼塘ID',
      `name` varchar(30) DEFAULT NULL COMMENT '钓点名称',
      `status` tinyint(1) DEFAULT '0' COMMENT '状态 0：未使用 1：选中 2：已售出 3：已锁定',
      `orderid` int(11) DEFAULT '0' COMMENT '订单id',
      `ordernum` varchar(255) DEFAULT NULL COMMENT '订单号',
      `starttime` int(11) DEFAULT NULL COMMENT '开始时间',
      `endtime` int(11) DEFAULT NULL COMMENT '到期时间',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `fid` (`fid`) USING BTREE,
      KEY `status` (`status`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='钓点表';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_fishpond_sysset` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `autoclose` int(11) DEFAULT '60' COMMENT '多少分钟不支付自动关闭订单',
      PRIMARY KEY (`id`),
      UNIQUE KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_fishpond_basan_record` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `fid` int(11) DEFAULT NULL COMMENT '鱼塘ID',
      `mid` int(11) DEFAULT NULL COMMENT '会员ID',
      `basanids` varchar(100) DEFAULT NULL COMMENT '钓点ID',
      `save_basanids` varchar(100) DEFAULT NULL COMMENT '变更钓点ID',
      `orderid` int(11) DEFAULT '0' COMMENT '订单id',
      `ordernum` varchar(255) DEFAULT NULL COMMENT '订单号',
      `createtime` int(11) DEFAULT NULL COMMENT '变更时间',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `fid` (`fid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='钓点表更换记录';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_fishpond_order` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT NULL,
      `mid` int(11) DEFAULT NULL,
      `ordernum` varchar(255) DEFAULT NULL,
      `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
      `proid` int(11) DEFAULT NULL,
      `proname` varchar(255) DEFAULT NULL,
      `propic` varchar(255) DEFAULT NULL,
      `ggid` int(11) DEFAULT NULL,
      `ggname` varchar(255) DEFAULT NULL,
      `num` int(11) DEFAULT '1',
      `hours` decimal(10,1) DEFAULT '0.0' COMMENT '小时',
      `sell_price` decimal(10,2) DEFAULT NULL,
      `totalprice` float(11,2) DEFAULT NULL,
      `basanid` varchar(255) DEFAULT NULL COMMENT '钓点ID',
      `basan_sum` int(11) DEFAULT '0' COMMENT '钓点数量',
      `starttime` int(11) DEFAULT NULL COMMENT '开始时间',
      `endtime` int(11) DEFAULT NULL COMMENT '到期时间',
      `createtime` int(11) DEFAULT NULL,
      `status` int(11) DEFAULT '0' COMMENT '0未支付;1已支付;2已发货,3已收货',
      `linkman` varchar(255) DEFAULT NULL,
      `tel` varchar(50) DEFAULT NULL,
      `remark` varchar(255) DEFAULT NULL,
      `refund_reason` varchar(255) DEFAULT NULL,
      `refund_money` decimal(11,2) DEFAULT '0.00',
      `refund_status` int(1) DEFAULT '0' COMMENT '1申请退款审核中 2已同意退款 3已驳回',
      `refund_time` int(11) DEFAULT NULL,
      `refund_checkremark` varchar(255) DEFAULT NULL,
      `payorderid` int(11) DEFAULT NULL,
      `paytypeid` int(11) DEFAULT NULL,
      `paytype` varchar(50) DEFAULT NULL,
      `paynum` varchar(255) DEFAULT NULL,
      `paytime` int(11) DEFAULT NULL,
      `hexiao_code` varchar(100) DEFAULT NULL,
      `hexiao_qr` varchar(255) DEFAULT NULL,
      `platform` varchar(255) DEFAULT 'wx',
      `delete` tinyint(1) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `status` (`status`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='鱼塘订单表';");

    if(!pdo_fieldexists2("ddwx_wx_tmplset","tmpl_fishpond_expire")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wx_tmplset` ADD COLUMN `tmpl_fishpond_expire` varchar(255) NULL DEFAULT '' COMMENT '鱼塘订单过期通知';");
    }


    if (!pdo_fieldexists2("ddwx_fishpond_product", "commissionset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_fishpond_product`  
            ADD COLUMN  `commissionset` tinyint(1) DEFAULT '0',
            ADD COLUMN  `commissiondata1` text,
            ADD COLUMN  `commissiondata2` text,
            ADD COLUMN  `commissiondata3` text;
        ");
    }

    if (!pdo_fieldexists2("ddwx_fishpond_order", "parent1")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_fishpond_order`  
            ADD COLUMN `parent1` int(11) DEFAULT NULL,
            ADD COLUMN `parent2` int(11) DEFAULT NULL,
		    ADD COLUMN `parent3` int(11) DEFAULT NULL,
		    ADD COLUMN `parent1commission` decimal(11,2) DEFAULT '0.00',
		    ADD COLUMN `parent2commission` decimal(11,2) DEFAULT '0.00',
		    ADD COLUMN `parent3commission` decimal(11,2) DEFAULT '0.00',
		    ADD COLUMN `parent1score` int(11) DEFAULT '0',
		    ADD COLUMN `parent2score` int(11) DEFAULT '0',
		    ADD COLUMN `parent3score` int(11) DEFAULT '0',
		    ADD COLUMN `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放';
        ");
    }
}
if(getcustom('hotel')){
	 \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_hotel` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT 0,
		  `name` varchar(100) DEFAULT '' COMMENT '酒店名称',
		  `pic` varchar(255) DEFAULT '' COMMENT '酒店背景图片',
		  `pics` text COMMENT '酒店实拍',
		  `address` varchar(255) DEFAULT '' COMMENT '酒店地址',
		  `tel` varchar(20) DEFAULT '' COMMENT '联系电话',
		  `longitude` varchar(100) DEFAULT '' COMMENT '经度',
		  `latitude` varchar(100) DEFAULT '' COMMENT '纬度',
		  `hotellevel` tinyint(1) DEFAULT '1' COMMENT '酒店星级',
		  `tag` varchar(200) DEFAULT '' COMMENT '酒店标签',
		  `ptsheshi` text COMMENT '酒店配套设施',
		  `iscomment` tinyint(1) DEFAULT '0' COMMENT '是否开启评价',
		  `days` int(11) DEFAULT '7' COMMENT '补充天数',
		  `content` text COMMENT '酒店介绍',
		  `textset` varchar(255) DEFAULT NULL,
		  `cid` int(11) DEFAULT '0' COMMENT '酒店类型id',
		  `xystatus` tinyint(1) DEFAULT '0' COMMENT '是否开启预定协议',
		  `xyname` varchar(255) DEFAULT '' COMMENT '100',
		  `xycontent` text,
		  `ysname` varchar(100) DEFAULT NULL,
		  `yscontent` text,
		  `comment` tinyint(1) DEFAULT '0' COMMENT '是否开启评论',
		  `status` tinyint(1) DEFAULT '1' COMMENT '状态',
		  `sort` int(11) DEFAULT '0',
		  `yddays` int(11) DEFAULT '10' COMMENT '默认10天未来可订天数',
		  `hotelfuwu` text,
		  `hotelquanyi` text,
		  `isyajin` tinyint(1) DEFAULT '0' COMMENT '0无需押金、1一房一押金、2仅需一份押金',
		  `yajin_money` decimal(10,0) DEFAULT '0' COMMENT '押金金额',
		  `money_dikou` tinyint(1) DEFAULT '0' COMMENT '是否开启余额抵扣',
		  `money_dikou_type` tinyint(1) DEFAULT '0' COMMENT '余额抵扣类型 0金额 1天数',
		  `dikou_bl` float(11,2) DEFAULT '0.00' COMMENT '余额抵扣比例',
		  `isqianzi` tinyint(1) DEFAULT '0' COMMENT '提交订单是否需要签字',
		  `sales` int(11) DEFAULT '0' COMMENT '销量',
		  `comment_haopercent` decimal(11,2) DEFAULT '100.00' COMMENT '好评率',
		  `comment_num` int(11) DEFAULT '0',
		  `comment_score` decimal(10,2) DEFAULT NULL,
		  `xieyiword` varchar(255) DEFAULT '',
		  `dikou_text` varchar(255) DEFAULT '',
		  `isrefund` tinyint(1) DEFAULT '0' COMMENT '是否开启退款',
		  `refund_hour` int(11) DEFAULT '0' COMMENT '可申请退款时间 几点',
		  `islimit` tinyint(1) DEFAULT '0' COMMENT '开启后同一时间不可下多笔订单',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

	\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_hotel_category` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `aid` int(11) DEFAULT NULL,
	  `pid` int(11) DEFAULT '0',
	  `name` varchar(255) DEFAULT NULL,
	  `pic` varchar(255) DEFAULT NULL,
	  `status` int(1) DEFAULT '1',
	  `sort` int(11) DEFAULT '1',
	  `showtj` varchar(255) DEFAULT '-1',
	  `createtime` int(11) DEFAULT NULL,
	  PRIMARY KEY (`id`) USING BTREE,
	  KEY `aid` (`aid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_comment` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT '0',
		  `mid` int(11) DEFAULT NULL,
		  `orderid` int(11) DEFAULT NULL,
		  `hotelid` int(11) DEFAULT NULL,
		  `hotelname` varchar(255) DEFAULT NULL,
		  `pic` varchar(255) DEFAULT NULL,
		  `roomid` int(11) DEFAULT NULL,
		  `roomname` varchar(255) DEFAULT NULL,
		  `ordernum` varchar(50) DEFAULT NULL,
		  `openid` varchar(255) DEFAULT NULL,
		  `nickname` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
		  `headimg` varchar(255) DEFAULT NULL,
		  `score` int(11) DEFAULT NULL,
		  `content` varchar(255) DEFAULT NULL,
		  `content_pic` varchar(500) DEFAULT NULL,
		  `reply_content` varchar(255) DEFAULT NULL,
		  `reply_content_pic` varchar(255) DEFAULT NULL,
		  `append_content` varchar(255) DEFAULT NULL,
		  `append_content_pic` varchar(255) DEFAULT NULL,
		  `append_reply_content` varchar(255) DEFAULT NULL,
		  `append_reply_content_pic` varchar(255) DEFAULT NULL,
		  `createtime` int(11) DEFAULT NULL,
		  `appendtime` int(11) DEFAULT NULL,
		  `status` int(1) DEFAULT '1',
		  `reply_time` int(11) DEFAULT NULL,
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `bid` (`bid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_group` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `name` varchar(255) DEFAULT NULL,
		  `pic` varchar(255) DEFAULT NULL,
		  `status` int(1) DEFAULT '1',
		  `sort` int(11) DEFAULT '1',
		  `createtime` int(11) DEFAULT NULL,
		  `hotelid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT '0',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
	
	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_order` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT '0',
		  `mid` int(11) DEFAULT NULL,
		  `title` varchar(255) DEFAULT NULL,
		  `sell_price` decimal(10,2) DEFAULT '0.00' COMMENT '房间价格',
		  `totalprice` decimal(10,2) DEFAULT NULL,
		  `totalnum` int(11) DEFAULT '1' COMMENT '/间/人数',
		  `yajin_money` decimal(10,2) DEFAULT '0.00' COMMENT '押金 可退',
		  `fuwu_money` decimal(10,2) DEFAULT '0.00' COMMENT '服务费',
		  `createtime` int(11) DEFAULT NULL,
		  `status` int(11) DEFAULT '0' COMMENT '0未支付;1已支付;2已确认 ,3已到店 4已离店  -1已关闭 ， ',
		  `ordernum` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `linkman` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `tel` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
		  `in_date` date DEFAULT NULL COMMENT '入住日期',
		  `leave_date` date DEFAULT NULL COMMENT '离店日期',
		  `longitude` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
		  `latitude` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
		  `message` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT '用户留言',
		  `remark` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `refund_reason` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `refund_money` decimal(11,2) DEFAULT '0.00',
		  `refund_status` int(1) DEFAULT '0' COMMENT '1申请退款审核中 2已同意退款 3已驳回',
		  `refund_time` int(11) DEFAULT NULL,
		  `refund_checkremark` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `payorderid` int(11) DEFAULT NULL,
		  `paytypeid` int(11) DEFAULT NULL,
		  `paytype` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
		  `paynum` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `paytime` int(11) DEFAULT NULL,
		  `delete` int(1) DEFAULT '0',
		  `roomid` int(11) DEFAULT NULL,
		  `send_time` bigint(20) DEFAULT NULL COMMENT '确认时间',
		  `collect_time` int(11) DEFAULT NULL COMMENT '收货时间',
		  `platform` varchar(50) CHARACTER SET utf8 DEFAULT 'wx' COMMENT 'wx小程序 m公众号网页',
		  `hexiao_code` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
		  `hexiao_qr` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
		  `hotelid` int(11) DEFAULT '0',
		  `daycount` int(11) DEFAULT '1' COMMENT '入住几晚',
		  `yajin_refund_status` int(11) DEFAULT '0' COMMENT '押金状态是否退款 1待审核 2已退款 3驳回',
		  `yajin_refund_money` decimal(10,2) DEFAULT '0.00' COMMENT '退还押金金额',
		  `yajin_refund_time` int(11) DEFAULT NULL,
		  `fuwu_money_status` tinyint(1) DEFAULT '0' COMMENT '服务费 状态是否退款 1待审核 2已退款 3驳回',
		  `fuwu_refund_money` decimal(11,2) DEFAULT '0.00' COMMENT '服务退还金额',
		  `fuwu_refund_time` int(11) DEFAULT NULL,
		  `yajin_refund_ordernum` varchar(50) DEFAULT NULL,
		  `fuwu_refund_ordernum` varchar(50) DEFAULT NULL,
		  `roomprices` varchar(1000) DEFAULT '',
		  `pic` varchar(255) DEFAULT NULL,
		  `daodian_time` int(11) DEFAULT '0' COMMENT '到店日期',
		  `real_leavedate` date DEFAULT NULL COMMENT '实际离店日期',
		  `fuwu_money_dj` decimal(11,2) DEFAULT '0.00' COMMENT '服务费 单价',
		  `iscomment` tinyint(1) DEFAULT '0' COMMENT '是否评价',
		  `yajin_refund_reason` varchar(255) DEFAULT NULL,
		  `signatureurl` varchar(255) DEFAULT '',
		  `dikou_money` decimal(11,2) DEFAULT '0.00' COMMENT '余额抵扣金额',
		  `use_money` float(11,2) DEFAULT '0.00',
		  `xieyi_word` varchar(255) DEFAULT NULL COMMENT '协议文档',
		  `confirm_time` int(11) DEFAULT '0' COMMENT '确认时间',
		  `coupon_money` decimal(11,2) DEFAULT '0.00' COMMENT '优惠券抵扣的金额',
		  `parent1` int(11) DEFAULT NULL,
		  `parent2` int(11) DEFAULT NULL,
		  `parent3` int(11) DEFAULT NULL,
		  `parent1commission` decimal(11,2) DEFAULT '0.00',
		  `parent2commission` decimal(11,2) DEFAULT '0.00',
		  `parent3commission` decimal(11,2) DEFAULT '0.00',
		  `parent1score` int(11) DEFAULT '0',
		  `parent2score` int(11) DEFAULT '0',
		  `parent3score` int(11) DEFAULT '0',
		  `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放',
		  `isfenhong` int(2) DEFAULT '0',
		  PRIMARY KEY (`id`) USING BTREE,
		  UNIQUE KEY `code` (`hexiao_code`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `bid` (`bid`) USING BTREE,
		  KEY `mid` (`mid`) USING BTREE,
		  KEY `status` (`status`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");
		
	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_photo` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `aid` int(11) DEFAULT NULL,
	  `bid` int(11) DEFAULT '0',
	  `name` varchar(255) DEFAULT NULL,
	  `pic` varchar(255) CHARACTER SET utf8 DEFAULT '',
	  `pics` varchar(5000) CHARACTER SET utf8 DEFAULT NULL,
	  `sort` int(11) DEFAULT '0' COMMENT '排序',
	  `status` tinyint(1) DEFAULT '1',
	  `hotelid` int(11) DEFAULT '0' COMMENT '酒店id',
	  PRIMARY KEY (`id`) USING BTREE,
	  KEY `aid` (`aid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");

	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_room` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `aid` int(11) DEFAULT NULL,
	  `bid` int(11) DEFAULT '0',
	  `cid` int(11) DEFAULT '0',
	  `name` varchar(255) DEFAULT NULL,
	  `pic` varchar(255) CHARACTER SET utf8 DEFAULT '',
	  `pics` varchar(5000) CHARACTER SET utf8 DEFAULT NULL,
	  `tag` varchar(255) DEFAULT '0',
	  `square` float(11,2) DEFAULT '0.00' COMMENT '面积',
	  `bedxing` int(11) DEFAULT '0' COMMENT '房型',
	  `bedwidth` decimal(11,2) DEFAULT NULL COMMENT '床宽 单位米',
	  `ischuanghu` varchar(20) DEFAULT '1' COMMENT '窗户 0 不显示',
	  `breakfast` varchar(100) DEFAULT '0' COMMENT '是否有早餐 0 不显示',
	  `tese` varchar(255) DEFAULT NULL,
	  `detail` text,
	  `sell_price` decimal(10,2) DEFAULT '0.00' COMMENT '房型价格',
	  `stock` int(11) DEFAULT '2' COMMENT '房型数量 每日',
	  `qrtype` tinyint(1) DEFAULT '1' COMMENT '1 即时确认 2手动确认',
	  `booking_notice` varchar(1000) DEFAULT NULL COMMENT '订房须知',
	  `scoredk` varchar(255) DEFAULT NULL,
	  `sort` int(11) DEFAULT '0' COMMENT '排序',
	  `showtj` varchar(100) DEFAULT '-1',
	  `gettj` varchar(100) DEFAULT '-1',
	  `status` tinyint(1) DEFAULT '1',
	  `hotelid` int(11) DEFAULT '0' COMMENT '酒店id',
	  `service_money` decimal(11,2) DEFAULT '0.00' COMMENT '服务费 天/人',
	  `isservice_money` tinyint(1) DEFAULT '0' COMMENT '是否开启服务费 1 开启',
	  `isyajin` tinyint(1) DEFAULT '0' COMMENT '押金设置 0跟随系统设置 1独立设置',
	  `yajin_money` decimal(11,2) DEFAULT '0.00' COMMENT '押金',
	  `gid` varchar(100) DEFAULT '0',
	  `sales` int(11) DEFAULT '0',
	  `commissionset` tinyint(1) DEFAULT '0',
	  `commissiondata1` text CHARACTER SET utf8,
	  `commissiondata2` text CHARACTER SET utf8,
	  `commissiondata3` text CHARACTER SET utf8,
	  `fenhongset` int(11) DEFAULT '1' COMMENT '分红设置',
	  `gdfenhongset` int(2) DEFAULT '0' COMMENT '0按会员等级 1价格比例  2固定金额 -1不参与分红',
	  `gdfenhongdata1` text CHARACTER SET utf8,
	  `gdfenhongdata2` text CHARACTER SET utf8,
	  `teamfenhongset` int(2) DEFAULT '0',
	  `teamfenhongdata1` text CHARACTER SET utf8,
	  `teamfenhongdata2` text CHARACTER SET utf8,
	  `areafenhongset` int(2) DEFAULT '0',
	  `areafenhongdata1` text CHARACTER SET utf8,
	  `areafenhongdata2` text CHARACTER SET utf8,
	  PRIMARY KEY (`id`) USING BTREE,
	  KEY `aid` (`aid`) USING BTREE,
	  KEY `cid` (`cid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");

	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_room_prices` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT '0',
		  `roomid` int(11) DEFAULT NULL,
		  `date` varchar(100) CHARACTER SET utf8 DEFAULT '',
		  `sell_price` decimal(11,2) DEFAULT NULL,
		  `stock` int(11) DEFAULT '2' COMMENT '房间数量',
		  `sales` int(11) DEFAULT '0' COMMENT '当日已售',
		  `name` varchar(255) DEFAULT NULL,
		  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1在售  2售空  3关房',
		  `year` int(11) DEFAULT NULL,
		  `datetime` date DEFAULT NULL COMMENT '日期',
		  `week` varchar(255) DEFAULT '' COMMENT '周几',
		  `qrtype` tinyint(1) DEFAULT '1' COMMENT '确认方式 1是自动 2是手动',
		  `hotelid` int(11) DEFAULT '0',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");

	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_set` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT NULL,
		  `name` varchar(100) DEFAULT '' COMMENT '酒店名称',
		  `pic` varchar(255) DEFAULT '' COMMENT '酒店背景图片',
		  `content` text COMMENT '酒店介绍',
		  `textset` varchar(255) DEFAULT NULL,
		  `pics` varchar(1000) DEFAULT NULL,
		  `autoclose` int(11) DEFAULT '30' COMMENT '多少分钟不支付自动关闭订单',
		  PRIMARY KEY (`id`) USING BTREE,
		  UNIQUE KEY `aid` (`aid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");

    if(!pdo_fieldexists2("ddwx_hotel","createtime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hotel` ADD COLUMN `createtime` int(11) DEFAULT '0' COMMENT '创建时间';");
    }
    if(!pdo_fieldexists2("ddwx_hotel","refundyj_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hotel` ADD COLUMN  `refundyj_type` int(11) DEFAULT '1' COMMENT '退押金类型';");
    }
	if(!pdo_fieldexists2("ddwx_hotel_order","yajin_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hotel_order` ADD COLUMN  `yajin_type` tinyint(1) DEFAULT '0' COMMENT '押金类型';");
    }
	\think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_hotel_order_yajin` (
	      `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT '0',
		  `mid` int(11) DEFAULT NULL,
		  `orderid` int(11) DEFAULT '0' COMMENT '订单号id',
		  `ordernum` varchar(255) DEFAULT NULL,
		  `yajin_money` decimal(11,2) DEFAULT '0.00' COMMENT '押金金额',
		  `refund_status` int(11) DEFAULT '0' COMMENT '押金状态是否退款 1待审核 2已退款 3驳回  0 未申请',
		  `refund_money` decimal(10,2) DEFAULT '0.00' COMMENT '退还押金金额',
		  `refund_time` int(11) DEFAULT NULL,
		  `refund_ordernum` varchar(50) DEFAULT NULL,
		  `apply_time` int(11) DEFAULT '0' COMMENT '到店日期',
		  `yajin_type` int(11) DEFAULT '1' COMMENT '1 一人一分押金   2 总共一份押金',
		  `refund_reason` varchar(255) DEFAULT NULL COMMENT '驳回原因',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `bid` (`bid`) USING BTREE,
		  KEY `mid` (`mid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");

	
    if(!pdo_fieldexists2("ddwx_hotel_room","commissionset")) {
		\think\facade\Db::execute("ALTER TABLE `ddwx_hotel_room`
		ADD COLUMN `commissionset` tinyint(1) DEFAULT '0',
		ADD COLUMN `commissiondata1` text CHARACTER SET utf8,
		ADD COLUMN `commissiondata2` text CHARACTER SET utf8,
		ADD COLUMN `commissiondata3` text CHARACTER SET utf8,
		ADD COLUMN `fenhongset` int(11) DEFAULT '1' COMMENT '分红设置',
		ADD COLUMN `gdfenhongset` int(2) DEFAULT '0' COMMENT '0按会员等级 1价格比例  2固定金额 -1不参与分红',
		ADD COLUMN `gdfenhongdata1` text CHARACTER SET utf8,
		ADD COLUMN `gdfenhongdata2` text CHARACTER SET utf8,
		ADD COLUMN `teamfenhongset` int(2) DEFAULT '0',
		ADD COLUMN `teamfenhongdata1` text CHARACTER SET utf8,
		ADD COLUMN `teamfenhongdata2` text CHARACTER SET utf8,
		ADD COLUMN `areafenhongset` int(2) DEFAULT '0',
		ADD COLUMN `areafenhongdata1` text CHARACTER SET utf8,
		ADD COLUMN `areafenhongdata2` text CHARACTER SET utf8;");
    }

    if(!pdo_fieldexists2("ddwx_hotel_order","parent1")) {
		\think\facade\Db::execute("ALTER TABLE `ddwx_hotel_order`
		ADD COLUMN `parent1` int(11) DEFAULT NULL,
		ADD COLUMN `parent2` int(11) DEFAULT NULL,
		ADD COLUMN `parent3` int(11) DEFAULT NULL,
		ADD COLUMN `parent1commission` decimal(11,2) DEFAULT '0.00',
		ADD COLUMN `parent2commission` decimal(11,2) DEFAULT '0.00',
		ADD COLUMN `parent3commission` decimal(11,2) DEFAULT '0.00',
		ADD COLUMN `parent1score` int(11) DEFAULT '0',
		ADD COLUMN `parent2score` int(11) DEFAULT '0',
		ADD COLUMN `parent3score` int(11) DEFAULT '0',
		ADD COLUMN `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放',
		ADD COLUMN `isfenhong` int(2) DEFAULT '0';");
	}
    if(!pdo_fieldexists2("ddwx_hotel","money_dikou_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hotel` ADD COLUMN `money_dikou_type` tinyint(1) DEFAULT '0' COMMENT '余额抵扣类型 0金额 1天数' AFTER `money_dikou`;");
    }
    if(!pdo_fieldexists2("ddwx_hotel_order","use_money")) {
		\think\facade\Db::execute("ALTER TABLE `ddwx_hotel_order` ADD COLUMN `use_money` float(11,2) DEFAULT '0.00' AFTER `dikou_money`;");
	}
	if(!pdo_fieldexists2("ddwx_hotel_room", "gettjtip")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `gettjtip` varchar(255) DEFAULT '您没有权限购买'");
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `gettjurl` varchar(255) DEFAULT ''");
	}
	if(!pdo_fieldexists2("ddwx_hotel_room", "limitnum")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `limitnum` int(11) DEFAULT '7' COMMENT '下单可选人数设置';");
	}
	if(!pdo_fieldexists2("ddwx_hotel_room", "isdaymoney")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `isdaymoney` tinyint(1) DEFAULT '0' COMMENT '是否开启余额定价';");
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `daymoney` int(11) DEFAULT '0' COMMENT '每晚消耗的余额';");
	}
	if(!pdo_fieldexists2("ddwx_hotel_room_prices", "daymoney")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room_prices ADD `daymoney` int(11) DEFAULT '0' COMMENT '每晚消耗的余额';");
	}
	if(!pdo_fieldexists2("ddwx_mp_tmplset_new", "tmpl_hotelbooking_success")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_mp_tmplset_new ADD `tmpl_hotelbooking_success` varchar(65) DEFAULT '' COMMENT '酒店预定成功通知';");
	}
	if(!pdo_fieldexists2("ddwx_wx_tmplset", "tmpl_hotelbooking_success")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_wx_tmplset ADD `tmpl_hotelbooking_success` varchar(65) DEFAULT '' COMMENT '酒店预定成功通知';");
	}
	if(!pdo_fieldexists2("ddwx_hotel_order", "hotelname")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_order ADD `hotelname` varchar(60) DEFAULT '' COMMENT '酒店名称';");
	}
	if(!pdo_fieldexists2("ddwx_hotel_order", "leftmoney")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_order 
		ADD `leftmoney` decimal(11,2) DEFAULT '0.00' COMMENT '抵扣完了，剩余支付的现金',
		ADD `yajin_orderid` int(11) DEFAULT '0' COMMENT '使用的押金订单id,可退回',
		ADD `leavetime` int(11) DEFAULT NULL COMMENT '离店时间';");
	}

	if(!pdo_fieldexists2("ddwx_hotel_set", "yajin_desc")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_set ADD `yajin_desc` varchar(255) DEFAULT '' COMMENT '押金描述';");
	}
	
	if(!pdo_fieldexists2("ddwx_hotel", "iscancel_otherorder")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `iscancel_otherorder` tinyint(1) DEFAULT '0' COMMENT ' 退押金是否取消其他使用押金的订单 默认为 0 不可取消     1 取消其他订单';");
	}

	if(!pdo_fieldexists2("ddwx_admin_user", "tmpl_hotelbooking_success")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_admin_user ADD `tmpl_hotelbooking_success` tinyint(1) DEFAULT '0' COMMENT '酒店预定成功通知';");
	}

	if(!pdo_fieldexists2("ddwx_hotel_order", "scoredk_money")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_order 
		ADD `scoredk_money` decimal(11,2) DEFAULT '0.00' COMMENT '积分抵扣掉的房费',
		ADD `scoredkscore` decimal(11,2) DEFAULT '0.00' COMMENT '消耗掉的积分';");
	}
	if(!pdo_fieldexists2("ddwx_hotel", "score_dikou")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel 
		ADD `score_dikou` tinyint(1) DEFAULT '0' COMMENT '是否开启积分抵扣 1 开启',
		ADD `score2money` decimal(11,2) DEFAULT '1.00' COMMENT '1积分抵扣多少钱',
		ADD `scoredkmaxpercent` decimal(11,2) DEFAULT '0.00' COMMENT '抵扣最大比例';");
	}
	if(!pdo_fieldexists2("ddwx_wx_tmplset", "tmpl_moneychange")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_wx_tmplset ADD `tmpl_moneychange` varchar(255) DEFAULT NULL;");
	}
	if(!pdo_fieldexists2("ddwx_hotel_set", "hotlimit")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_set ADD `hotlimit` int(11) DEFAULT '6' COMMENT '首页热门显示数量';");
	}
	
	if(!pdo_fieldexists2("ddwx_hotel_order", "real_usemoney")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_order 
		ADD `real_usemoney` decimal(11,2) DEFAULT '0.00' COMMENT '实际支付余额，针对提前离店',
		ADD `real_roomprice` decimal(11,2) DEFAULT '0.00' COMMENT '实际支付房费，针对提前离店',
		ADD `isbefore` tinyint(1) DEFAULT '0' COMMENT '是否为提前离店',
		ADD `real_fuwu_money` decimal(11,2) DEFAULT '0.00' COMMENT '实际支付服务费';");
	}
	if(!pdo_fieldexists2("ddwx_hotel_order_yajin", "yd_num")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_order_yajin ADD `yd_num` int(11) DEFAULT '0' COMMENT '预定人数';");
	}
	if(!pdo_fieldexists2("ddwx_hotel", "yddatedays")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `yddatedays` int(11) DEFAULT '30' COMMENT '最多可预订日期';");
	}
     
    if (!pdo_fieldexists2("ddwx_coupon", "hotelids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon`  ADD COLUMN `hotelids` varchar(100) DEFAULT NULL;");
    }
       
    if (!pdo_fieldexists2("ddwx_coupon", "roomids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon`  ADD COLUMN `roomids` varchar(100) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_hotel_room", "teamfenhongpjset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hotel_room`  ADD COLUMN `teamfenhongpjset` int(2) DEFAULT '0' COMMENT '团队分红平级奖设置-1不参与奖励0按照会员等级1单独设置奖励比例2单独设置奖励金额4单独设置奖励积分比例';");
    }
    if (!pdo_fieldexists2("ddwx_hotel_room", "teamfenhongpjdata1")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hotel_room`  ADD COLUMN `teamfenhongpjdata1` text;");
    }
    if (!pdo_fieldexists2("ddwx_hotel_room", "teamfenhongpjdata2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_hotel_room`  ADD COLUMN `teamfenhongpjdata2` text;");
    }
	if(!pdo_fieldexists2("ddwx_hotel_room", "createtime")) {
	  \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `createtime` int(11) DEFAULT 0;");
	}
    if(!pdo_fieldexists2("ddwx_hotel", "qystatus")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `qystatus` tinyint(1) DEFAULT '1' COMMENT '是否开启权益协议';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "fwstatus")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `fwstatus` tinyint(1) DEFAULT '1' COMMENT '是否政策服务权益';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "qyname")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `qyname`  varchar(30) DEFAULT '酒店权益' COMMENT '权益名称';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "fwname")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `fwname`  varchar(30) DEFAULT '政策服务' COMMENT '服务名称';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "formdata")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `formdata` text NULL COMMENT '自定义表单';");
    }
    if(!pdo_fieldexists2("ddwx_hotel_room", "lvprice")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `lvprice` tinyint(1) DEFAULT '0' COMMENT '是否开启会员价';");
    }
    if(!pdo_fieldexists2("ddwx_hotel_room", "lvprice_data")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room ADD `lvprice_data` text NULL COMMENT '会员价格';");
    }
    if(!pdo_fieldexists2("ddwx_hotel_room_prices", "lvprice")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room_prices ADD `lvprice` tinyint(1) DEFAULT '0' COMMENT '是否开启会员价';");
    }
    if(!pdo_fieldexists2("ddwx_hotel_room_prices", "lvprice_data")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room_prices ADD `lvprice_data` text NULL COMMENT '会员价格';");
    }
    if(!pdo_fieldexists2("ddwx_hotel_room_prices", "lvprice_min")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel_room_prices ADD `lvprice_min` decimal(11,2) DEFAULT '0.00' COMMENT '会员价最小值';");
    }
    if(!pdo_fieldexists2("ddwx_hotel_set", "roomstyle")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel_set ADD `roomstyle` tinyint(1) DEFAULT '1' COMMENT '房型样式1长方形2正方形';");
    }
    if(!pdo_fieldexists2("ddwx_hotel_set", "btnstyle")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel_set ADD `btnstyle` tinyint(1) DEFAULT '1' COMMENT '预定按钮样式1长方形2正方形';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "nature")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `nature` varchar(50) DEFAULT NULL COMMENT '酒店性质';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "lvprice_min")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `lvprice_min` decimal(11,2) DEFAULT '0.00' COMMENT '会员价最小值';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "xyfontsize")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `xyfontsize` int(3) DEFAULT '24' COMMENT '协议字号';");
    }
    if(!pdo_fieldexists2("ddwx_hotel", "ysfontsize")) {
        \think\facade\Db::execute("ALTER TABLE ddwx_hotel ADD `ysfontsize` int(3) DEFAULT '24' COMMENT '隐私政策字号';");
    }
}
if(getcustom('yx_team_yeji_include_self')){
    if(!pdo_fieldexists2("ddwx_admin_set","teamyeji_include_self")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `teamyeji_include_self` tinyint(1) DEFAULT '0' COMMENT '团队业绩包含自己';");
    }
}
if(getcustom('yx_team_yeji')){
    if(!pdo_fieldexists2("ddwx_member","total_team_yeji_fenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `total_team_yeji_fenhong` decimal(11,2) DEFAULT '0.00' COMMENT '团队业绩分红';");
    }
}
if(getcustom('member_level_parent_not_commission')){
    if(!pdo_fieldexists2("ddwx_shop_product","parent_not_commission_json")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `parent_not_commission_json` text NULL COMMENT '上级无任何分销奖励';");
    }
}
if(getcustom('restaurant_take_food')){
    if(!pdo_fieldexists2("ddwx_restaurant_take_food_sysset","status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_take_food_sysset` ADD COLUMN `status` tinyint(1) DEFAULT '0';");
    }
}
if(getcustom('huodong_baoming')){
    if(!pdo_fieldexists2("ddwx_huodong_baoming_order","totalscore")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_order` ADD COLUMN `totalscore` decimal(11,2) DEFAULT '0.00' COMMENT '所需积分';");
    }
	if(!pdo_fieldexists2("ddwx_huodong_baoming_product","huodong_danwei")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN huodong_danwei text;");
    }
	if(!pdo_fieldexists2("ddwx_huodong_baoming_product","score_price")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_product` ADD COLUMN `score_price` decimal(11,2) DEFAULT '0.00' COMMENT '所需积分';");
    }
	if(!pdo_fieldexists2("ddwx_huodong_baoming_guige","score_price")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_guige` ADD COLUMN `score_price` decimal(11,2) DEFAULT '0.00' COMMENT '所需积分';");
    }
	if(!pdo_fieldexists2("ddwx_huodong_baoming_set","textset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_huodong_baoming_set` ADD COLUMN `textset` varchar(100) DEFAULT NULL COMMENT '自定义文本';");
    }
}
if(getcustom('extend_qrcode_variable_fenzhang')){
    if(!pdo_fieldexists2("ddwx_qrcode_variable","restaurant_main_bid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_variable`
            ADD COLUMN `restaurant_main_bid` int(11) DEFAULT '0' COMMENT '选择多商户ID',
            ADD COLUMN `not_fenzhang_bid` varchar(100) DEFAULT NULL COMMENT '免分账商户 多个',
            ADD COLUMN  `fzbusiness_data` text COMMENT '分账商户的比例信息等';");
    }
    if(!pdo_fieldexists2("ddwx_qrcode_list_variable","restaurant_main_bid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_list_variable`
            ADD COLUMN `restaurant_main_bid` int(11) DEFAULT '0' COMMENT '选择多商户ID',
            ADD COLUMN `not_fenzhang_bid` varchar(100) DEFAULT NULL COMMENT '免分账商户 多个',
            ADD COLUMN `fzbusiness_data` text COMMENT '分账商户的比例信息等',
            ADD COLUMN `name` varchar(50) DEFAULT NULL COMMENT '名称',
            ADD COLUMN `tableid` int(11) DEFAULT NULL,
            ADD COLUMN `tableno` varchar(20) DEFAULT NULL COMMENT '桌号',
            ADD COLUMN `tablebid` int(11) DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order","qrcode_val_code")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN  `qrcode_val_code` varchar(255) DEFAULT NULL COMMENT '活码随机码';");
    }
}

if(getcustom('business_cansetscore')){
    if(!pdo_fieldexists2("ddwx_business","business_cansetscore")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `business_cansetscore` tinyint(1) NOT NULL DEFAULT 0 COMMENT '商家是否能修改积分抵扣';");
    }
}
if(getcustom('yx_team_yeji_pingji_jinsuo')){
    if(!pdo_fieldexists2("ddwx_team_yeji_set","yueji_pingji_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_team_yeji_set` 
            ADD COLUMN `yueji_pingji_status` tinyint(1) DEFAULT '0',
            ADD COLUMN `yueji_pingji_data` text;");
    }
}
if(getcustom('commission_max_times')){
    if(!pdo_fieldexists2("ddwx_member_commission_record","proid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_record` ADD COLUMN `proid`  int(11) NULL DEFAULT 0 COMMENT '商品id';");
    }
    if(!pdo_fieldexists2("ddwx_member_commission_record","level")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_record` ADD COLUMN `level`  tinyint(1) NULL DEFAULT 0 COMMENT '分销层级';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","commission_max_times_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `commission_max_times_status`  tinyint(1) NULL DEFAULT 0 COMMENT '开启分销份数 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","commission_max_times")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `commission_max_times`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '分销份数设置';");
    }
}

if(getcustom('supply_zhenxin')){
    if(!pdo_fieldexists2("ddwx_shop_product","sproid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `sproid` varchar(30) NULL DEFAULT '0' COMMENT '关联商品ID',
            ADD COLUMN `source` varchar(20) NULL DEFAULT '' COMMENT '关联商品来源 supply_zhenxin: 甄新汇选',
            ADD COLUMN `issource` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否来源其他渠道 0：否 1：是',
            ADD COLUMN `sprice` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '甄新汇选成本价',
            ADD COLUMN `zxgroup_price` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '甄新汇选最低价格',
            ADD COLUMN `zxdata` text NULL COMMENT '甄新汇选快递信息',
            ADD COLUMN `zxhtml` text NULL COMMENT '甄新汇选快递信息html';");
    }

    if(!pdo_fieldexists2("ddwx_shop_guige","skuid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` 
            ADD COLUMN `skuid` varchar(30) NULL DEFAULT '0' COMMENT '关联商品规格id',
            ADD COLUMN `sprice` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '甄新汇选成本价',
            ADD COLUMN `zxgroup_price` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '甄新汇选最低价格',
            ADD COLUMN `zxbuy_start_qty` int NOT NULL DEFAULT 0 COMMENT '甄新汇选起售数量',
            ADD COLUMN `zxservice_price` decimal(10, 2) NULL DEFAULT 0 COMMENT '甄新汇选服务费';");
    }

    if(!pdo_fieldexists2("ddwx_member_address","province_zxcode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_address` 
            ADD COLUMN `province_zxcode` varchar(30) NULL DEFAULT '' COMMENT '甄新汇选省code',
            ADD COLUMN `city_zxcode` varchar(30) NULL DEFAULT '' COMMENT '甄新汇选市code',
            ADD COLUMN `district_zxcode` varchar(30) NULL DEFAULT '' COMMENT '甄新汇选区县code';");
    }

    if(!pdo_fieldexists2("ddwx_shop_order","source")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `source` varchar(20) NULL DEFAULT '' COMMENT '关联商品来源 supply_zhenxin: 甄新汇选',
            ADD COLUMN `issource` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否来源其他渠道 0：否 1：是',
            ADD COLUMN `sordernum` varchar(30) NULL DEFAULT '' COMMENT '来源渠道订单号',
            ADD COLUMN `sresdata` text NULL COMMENT '来源渠道返还数据',
            ADD COLUMN `shipAreaCode` varchar(50) NULL DEFAULT '' COMMENT '甄新汇选区域编码拼接字符',
            ADD COLUMN `zxservice_price` decimal(10, 2) NULL DEFAULT 0 COMMENT '甄新汇选服务费',
            ADD COLUMN `usercard` varchar(30) NULL DEFAULT '' COMMENT '身份证号';");
    }

    if(!pdo_fieldexists2("ddwx_shop_order_goods","source")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD COLUMN `source` varchar(20) NULL DEFAULT '' COMMENT '关联商品来源 supply_zhenxin: 甄新汇选',
            ADD COLUMN `issource` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否来源其他渠道 0：否 1：是',
            ADD COLUMN `sordernum` varchar(30) NULL DEFAULT '' COMMENT '来源渠道订单号',
            ADD COLUMN `sproid` varchar(30) NULL DEFAULT '0' COMMENT '关联商品ID',
            ADD COLUMN `skuid` varchar(30) NULL DEFAULT '0' COMMENT '关联商品规格id',
            ADD COLUMN `sprice` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '甄新汇选成本价',
            ADD COLUMN `zxservice_price` decimal(10, 2) NULL DEFAULT 0 COMMENT '甄新汇选服务费';");
    }

    if(!pdo_fieldexists2("ddwx_shop_refund_order","zxservice_sn")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order` 
            ADD COLUMN `zxservice_sn` varchar(30) NULL DEFAULT '' COMMENT '甄新汇选退款订单号',
            ADD COLUMN `source` varchar(20) NULL DEFAULT '' COMMENT '关联商品来源 supply_zhenxin: 甄新汇选',
            ADD COLUMN `issource` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否来源其他渠道 0：否 1：是';");
    }

    if(!pdo_fieldexists2("ddwx_shop_refund_order_goods","zxservice_sn")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order_goods` 
            ADD COLUMN `zxservice_sn` varchar(30) NULL DEFAULT '' COMMENT '甄新汇选订退款单号',
            ADD COLUMN `source` varchar(20) NULL DEFAULT '' COMMENT '关联商品来源 supply_zhenxin: 甄新汇选',
            ADD COLUMN `issource` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否来源其他渠道 0：否 1：是',
            ADD COLUMN `skuid` varchar(30) NULL DEFAULT '' COMMENT '来源规格ID' AFTER `issource`;");
    }

    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_supply_zhenxin_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `bid` int(11) NOT NULL DEFAULT 0,
        `appid` varchar(50) NOT NULL DEFAULT '',
        `appsecret` varchar(100) NOT NULL DEFAULT '',
        `status` tinyint(1) NOT NULL DEFAULT 0,
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `apiurl` varchar(100) NULL DEFAULT '',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `bid`(`bid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 ROW_FORMAT=DYNAMIC COMMENT '甄新汇选设置';");

    if(!pdo_fieldexists2("ddwx_supply_zhenxin_set","apiurl")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_supply_zhenxin_set` ADD COLUMN `apiurl` varchar(100) NULL DEFAULT '';");
    }
    if(!pdo_fieldexists2("ddwx_supply_zhenxin_set","apiurl")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_supply_zhenxin_set` DROP INDEX `aid`,ADD INDEX `aid`(`aid`) USING BTREE;");
    }
}

if(getcustom('active_score')){
    if(!pdo_fieldexists2("ddwx_business","shopactivescore_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `shopactivescore_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '商城让利到积分比例',
            ADD COLUMN `member_shopactivescore_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '积分分配到会员比例',
            ADD COLUMN `business_shopactivescore_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '积分分配到商家比例',
            ADD COLUMN `shopactivescore_data` text NULL COMMENT '商品让利到积分比例变动',
            ADD COLUMN `maidanactivescore_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '买单让利到积分比例',
            ADD COLUMN `member_maidanactivescore_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '积分分配到会员比例',
            ADD COLUMN `business_maidanactivescore_ratio`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '积分分配到商家比例',
            ADD COLUMN `maidanactivescore_data` text NULL COMMENT '买单让利到积分比例变动';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","is_activescore")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `is_activescore`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否发放让利 0未发放 1已发放';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods","activescore")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `activescore`  decimal(12,2) NOT NULL DEFAULT 0 COMMENT '让利数量';");
    }
    if(!pdo_fieldexists2("ddwx_maidan_order","activescore")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` 
            ADD COLUMN `activescore`  decimal(12,2) NOT NULL DEFAULT 0 COMMENT '让利数量',
            ADD COLUMN `is_activescore`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否发放让利 0未发放 1已发放';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_active_score_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `reward_type` tinyint(1) NULL DEFAULT 0 COMMENT '计算方式 0按利润 1按订单金额',
        `reward_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '0' COMMENT '赠送时间 0收货完成 1支付完成',
        `auto_order_num` decimal(12, 2) NULL DEFAULT 0.00 COMMENT '自动下单数量',
        `auto_order_proids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '0' COMMENT '自动下单商品id',
        `shopactivescore_ratio` decimal(12, 2) NULL DEFAULT 0.00 COMMENT '商城让利到积分的数量',
        `maidanactivescore_ratio` decimal(12, 2) NULL DEFAULT 0.00 COMMENT '买单让利到积分的数量',
        `createtime` int(10) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 ROW_FORMAT=DYNAMIC COMMENT = '让利积分设置';");
}

if(getcustom('custom_control')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_custom_set` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NOT NULL DEFAULT 0 ,
        `custom`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '定制标记' ,
        `desc`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '定制标记描述' ,
        `disabled`  tinyint(1) NULL DEFAULT 0 COMMENT '0开启 1禁用' ,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
}
if(getcustom('product_supplier')){
    if(!pdo_fieldexists2("ddwx_shop_product","supplier_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `supplier_id` int(11) NULL DEFAULT 0 COMMENT '供应商id';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","supplier_number")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `supplier_number` varchar(60) NULL DEFAULT '' COMMENT '供应商编码';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_supplier` (
	      `id` int(11) NOT NULL AUTO_INCREMENT,
		  `aid` int(11) DEFAULT NULL,
		  `bid` int(11) DEFAULT '0',
		  `supplier_number` varchar(60) DEFAULT NULL COMMENT '供应商编号',
		  `supplier_name` varchar(255) DEFAULT NULL COMMENT '供应商名称',
          `name` varchar(255) DEFAULT NULL COMMENT '联系人',
          `tel` varchar(11) DEFAULT NULL COMMENT '联系电话',
          `create_time` int(11) DEFAULT NULL COMMENT '创建时间',
		  PRIMARY KEY (`id`) USING BTREE,
		  KEY `aid` (`aid`) USING BTREE,
		  KEY `bid` (`bid`) USING BTREE
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");
}

if(getcustom('shop_purchase_order')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_purchase_order` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT NULL,  
      `ordernum` varchar(255) DEFAULT NULL,
      `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
      `totalprice` decimal(10,2) DEFAULT NULL,
      `totalnum` int(11) DEFAULT '1',
      `platform` varchar(50) DEFAULT 'wx' COMMENT 'wx小程序 m公众号网页',
      `validity_day` tinyint(1) DEFAULT '1' COMMENT '有效天数',
      `create_time` int(11) DEFAULT NULL COMMENT '创建时间',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");

    if(!pdo_fieldexists2("ddwx_purchase_order","expiring_date")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_purchase_order` ADD COLUMN`expiring_date` int(11) DEFAULT NULL COMMENT '失效日期';");
    }
    if(!pdo_fieldexists2("ddwx_purchase_order","createtime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_purchase_order` CHANGE `create_time` `createtime` int(11) DEFAULT NULL COMMENT '创建时间';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_purchase_order_cart` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `bid` int(11) DEFAULT '0' COMMENT '商户ID 0代表平台的',
      `mid` int(11) DEFAULT NULL COMMENT '会员ID',
      `proid` int(11) DEFAULT NULL COMMENT '商品ID',
      `ggid` int(11) DEFAULT NULL COMMENT '商品规格ID',
      `num` int(11) DEFAULT NULL COMMENT '数量',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `proid` (`proid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='采购单购物车';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_purchase_order_goods` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',  
      `mid` int(11) DEFAULT NULL,  
      `orderid` int(11) DEFAULT NULL COMMENT '订单ID',
      `ordernum` varchar(50) DEFAULT NULL COMMENT '订单号',
      `proid` int(11) DEFAULT NULL COMMENT '商品ID',
      `name` varchar(255) DEFAULT NULL COMMENT '商品名称',
      `pic` varchar(255) DEFAULT NULL COMMENT '商品图片',
      `procode` varchar(255) DEFAULT NULL COMMENT '商品编码',
      `barcode` varchar(255) DEFAULT NULL,
      `ggid` int(11) DEFAULT NULL COMMENT '规格ID',
      `ggname` varchar(255) DEFAULT NULL COMMENT '规格名称',
      `num` int(11) DEFAULT '0' COMMENT '购买数量',
      `sell_price` decimal(11,2) DEFAULT NULL COMMENT '销售价',
      `totalprice` decimal(11,2) DEFAULT NULL COMMENT '总价',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");

    if(!pdo_fieldexists2("ddwx_shop_sysset","show_purchase_order")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `show_purchase_order` tinyint(1) DEFAULT 0 COMMENT '采购单';");
    }
}

if(getcustom('image_search')){
    if(!pdo_fieldexists2("ddwx_baidu_set","image_search_object")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_baidu_set` ADD COLUMN `image_search_object` tinyint(1) DEFAULT 1 COMMENT '图搜对象 1:主图+辅图 2:主图';");
    }
    if(!pdo_fieldexists2("ddwx_baidu_set","image_search_business")) {
      \think\facade\Db::execute("ALTER TABLE `ddwx_baidu_set` ADD COLUMN `image_search_business` tinyint(1) NULL DEFAULT 1 COMMENT '结果显示多商户商品1是0否' AFTER `image_search_object`,ADD COLUMN `image_search_business_switch` tinyint(1) NULL DEFAULT 0 COMMENT '图搜多商户开关0平台配置1独立配置' AFTER `image_search_business`,ADD COLUMN `bid` int(11) NULL DEFAULT 0 COMMENT '多商户id' AFTER `image_search_business_switch`,DROP PRIMARY KEY;");
    }
}
if(getcustom('business_more_account')){
    if(!pdo_fieldexists2("ddwx_business","wxpay_submchid_text")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `wxpay_submchid_text` text COMMENT '多个分账接收方';");
    }
	if(!pdo_fieldexists2("ddwx_wxpay_log","wxpay_submchid_text")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wxpay_log` ADD COLUMN `wxpay_submchid_text` text COMMENT '多个分账接收方明细';");
    }
}

if(getcustom('member_goldmoney_silvermoney')){
    if(!pdo_fieldexists2("ddwx_member","goldmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
            ADD COLUMN `goldmoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '金值' ,
            ADD COLUMN `silvermoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '银值' ;");
    }
    if(!pdo_fieldexists2("ddwx_register_giveset","goldmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_register_giveset` 
            ADD COLUMN `goldmoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '金值' ,
            ADD COLUMN `silvermoney` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '银值';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","goldmoneydec_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `goldmoneydec_ratio` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '金值最大抵扣比例' ,
            ADD COLUMN `silvermoneydec_ratio` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '银值最大抵扣比例';");
    }
    if(!pdo_fieldexists2("ddwx_shop_guige","givegoldmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_guige` 
            ADD COLUMN `givegoldmoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '赠送金值数额' ,
            ADD COLUMN `givesilvermoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '赠送银值数额' ;");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","givegoldmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `givegoldmoney` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '赠送金值数额',
            ADD COLUMN `givesilvermoney` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '赠送银值数额',
            ADD COLUMN `goldmoneydec` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '金值抵扣数额' ,
            ADD COLUMN `silvermoneydec` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '银值抵扣数额' ;");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods","goldmoneydec")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD COLUMN `goldmoneydec` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '金值抵扣数额',
            ADD COLUMN `silvermoneydec` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '银值抵扣数额';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_silvermoneylog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `mid` varchar(100)  NULL DEFAULT '0',
        `orderid` int(11) NOT NULL DEFAULT 0,
        `silvermoney` decimal(10, 2) NULL DEFAULT 0.00,
        `after` decimal(10, 2) NULL DEFAULT 0.00,
        `type` varchar(20)  NULL DEFAULT 'shop',
        `remark` varchar(255)  NULL DEFAULT NULL,
        `createtime` int(11) NULL DEFAULT 0,
        `updatetime` int(11) NULL DEFAULT 0,
        `ordernum` varchar(30)  NULL DEFAULT '',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '银值记录' ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_goldmoneylog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `mid` varchar(100)  NULL DEFAULT '0',
        `orderid` int(11) NOT NULL DEFAULT 0,
        `goldmoney` decimal(10, 2) NULL DEFAULT 0.00,
        `after` decimal(10, 2) NULL DEFAULT 0.00,
        `type` varchar(20)  NULL DEFAULT 'shop',
        `remark` varchar(255)  NULL DEFAULT NULL,
        `createtime` int(11) NULL DEFAULT 0,
        `updatetime` int(11) NULL DEFAULT 0,
        `ordernum` varchar(30)  NULL DEFAULT '',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '金值记录' ROW_FORMAT=DYNAMIC;");
}
if(getcustom('business_toaccount_type')){
    if(!pdo_fieldexists2("ddwx_business","toaccount_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `toaccount_type` tinyint(1) NULL DEFAULT 0 COMMENT '实际到账方式 1、按销售价 2、按市场价 3、按成本价';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods","market_price")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `market_price` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '市场价格';");
    }
}

if(getcustom('business_area_fenhong')){
    if(!pdo_fieldexists2("ddwx_business","areafenhong_province")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
        ADD COLUMN `areafenhong_province` decimal(10,2) NULL DEFAULT 0.00,
        ADD COLUMN `areafenhong_city` decimal(10,2) NULL DEFAULT 0.00,
        ADD COLUMN `areafenhong_district` decimal(10,2) NULL DEFAULT 0.00;");
    }
}
if(getcustom('cashier_area_fenhong')) {
    if (!pdo_fieldexists2("ddwx_cashier_order_goods", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order_goods` ADD COLUMN `isfenhong`  tinyint(1) NULL DEFAULT 0;");
    }
}
if(getcustom('maidan_area_fenhong')) {
    if (!pdo_fieldexists2("ddwx_maidan_order", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `isfenhong`  tinyint(1) NULL DEFAULT 0;");
    }
}
if(getcustom('yx_hongbao_queue_free')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_hongbao_queue_free_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `status` tinyint(1) DEFAULT '0',
      `gettj` varchar(255) DEFAULT NULL COMMENT '参与的等级',
      `maidan_join` tinyint(1) DEFAULT '0' COMMENT '买单是否参与',
      `productids` varchar(255) DEFAULT NULL COMMENT '产品数据',
      `createtime` int(11) DEFAULT '0',
      `rate_type` tinyint(1) DEFAULT '1' COMMENT '1比例  2金额',
      `rate_ratio` decimal(11,2) DEFAULT '0.00',
      `rate_money` decimal(11,2) DEFAULT '0.00',
      `hongbao_num` int(11) DEFAULT '0' COMMENT '红包数量',
      `hongbao_max_num` int(11) DEFAULT '0' COMMENT '红包上限数量',
      `max_type` tinyint(1) DEFAULT '1' COMMENT '1比例 2固定额度',
      `max_radio` decimal(11,2) DEFAULT '0.00' COMMENT '比例',
      `max_money` decimal(11,2) DEFAULT '0.00' COMMENT '额度',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_hongbao_queue_free` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT NULL,
      `mid` int(11) DEFAULT '0',
      `type` varchar(20) DEFAULT NULL,
      `orderid` int(11) DEFAULT NULL,
      `ordernum` varchar(100) DEFAULT NULL,
      `num` int(11) DEFAULT '0' COMMENT '红包数量 （和封顶金额满足其一退出）',
      `max_money` decimal(11,2) DEFAULT NULL COMMENT '封顶额度',
      `give_num` int(11) DEFAULT '0' COMMENT '已给红包数量',
      `give_money` decimal(11,2) DEFAULT '0.00' COMMENT '已给金额',
      `status` tinyint(1) DEFAULT '0' COMMENT '0排队中 1退出排队',
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_hongbao_queue_free_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `mid` int(11) DEFAULT NULL,
      `title` varchar(255) DEFAULT NULL,
      `queueid` int(11) DEFAULT NULL,
      `type` varchar(100) DEFAULT NULL COMMENT '类型',
      `orderid` int(11) DEFAULT NULL COMMENT '来自哪个订单id',
      `ordernum` varchar(100) DEFAULT NULL,
      `ordermoney` decimal(11,2) DEFAULT '0.00',
      `money_give` decimal(11,2) DEFAULT NULL COMMENT '产生的金额',
      `from_mid` int(11) DEFAULT NULL COMMENT '来自用户',
      `createtime` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    if(!pdo_fieldexists2("ddwx_member_moneylog","give_ordernum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_moneylog` 
            ADD COLUMN `give_ordernum` varchar(100) DEFAULT NULL COMMENT '得红包返利的排队订单号',
            ADD COLUMN `form_ordernum` varchar(100) DEFAULT NULL COMMENT '产生红包返利的订单号';");
    }
}
if(getcustom('business_deposit_refund')){
    if(!pdo_fieldexists2("ddwx_business_sysset","business_deposit_refund")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
            ADD COLUMN `business_deposit_refund` tinyint(1) DEFAULT '0' COMMENT '商家保证金退款按钮状态';");
    }
    if(!pdo_fieldexists2("ddwx_business_deposit_order","refund_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_deposit_order` 
            ADD COLUMN `refund_status` int(1) DEFAULT '0' COMMENT '1申请退款审核中 2已同意退款 3已驳回',
            ADD COLUMN `refund_time` int(11) DEFAULT NULL;");
    }
}
if(getcustom('yuyue_fuwutype_text')){
	if(!pdo_fieldexists2("ddwx_yuyue_set","fuwutype_text")){
	  \think\facade\Db::execute("ALTER TABLE ddwx_yuyue_set ADD `fuwutype_text` varchar(255) DEFAULT NULL;");
	}
}
if(getcustom('member_recharge_detail_refund')){
    if(!pdo_fieldexists2("ddwx_recharge_order","give_money")){
        \think\facade\Db::execute("ALTER TABLE ddwx_recharge_order 
            ADD COLUMN `give_money` decimal(11,2) DEFAULT '0.00' COMMENT '赠送金额',
            ADD COLUMN `refund_status` tinyint(1) DEFAULT '0' COMMENT '1申请退款审核中 2已同意退款 3已驳回',
            ADD COLUMN `refund_time` int(11) DEFAULT '0',
            ADD COLUMN `refund_money` decimal(11,2) DEFAULT '0.00' COMMENT '退款金额';");
    }
    if(!pdo_fieldexists2("ddwx_recharge_order","refund_reason")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_recharge_order` ADD COLUMN `refund_reason` varchar(255) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_recharge_order","refund_give_money")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_recharge_order` ADD COLUMN `refund_give_money` decimal(11,2) DEFAULT '0.00' COMMENT '退款赠送金额';");
    }
}
if(getcustom('yx_cashback_stage')){
    if(!pdo_fieldexists2("ddwx_cashback","stagedata")){
      \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `stagedata` text NULL COMMENT '购物返现活动';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods_cashback","stagedata")){
      \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` 
            ADD COLUMN `stagedata` text NULL COMMENT '阶梯返还数据';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods_cashback","return_type")){
      \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` 
            ADD COLUMN `return_type` tinyint(1) NULL  COMMENT '返还类型  2、阶梯返还';");
    }
}
if(getcustom('commission_bole')){
    if(!pdo_fieldexists2("ddwx_member_level","giveup_percent")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `giveup_percent`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '分销伯乐奖提成比例';");
    }
    if(!pdo_fieldexists2("ddwx_member_level","giveup_commission")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `giveup_commission`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '分销伯乐奖提成金额';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","commissionboleset")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `commissionboleset`  tinyint(1) NULL DEFAULT 0 COMMENT '分销伯乐奖开关 0按等级 1单独比例 2单独金额 -1不参与';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","commissionboledata1")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `commissionboledata1`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '分销伯乐奖比例数据';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","commissionboledata2")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `commissionboledata2`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '分销伯乐奖金额数据';");
    }
    if(!pdo_fieldexists2("ddwx_member_level","commission_bole_origin")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `commission_bole_origin`  tinyint(1) NULL DEFAULT 0 COMMENT '分销伯乐奖仅发放原上级';");
    }
}

if(getcustom('certificate_poster')){
    if(!pdo_fieldexists2("ddwx_certificate_poster_set","header_text")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_certificate_poster_set` ADD COLUMN `header_text` longtext,
        ADD COLUMN `footer_text` longtext;");
    }

    if(!pdo_fieldexists2("ddwx_certificate_poster_record","sex")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_certificate_poster_record` ADD COLUMN `sex` tinyint(1) DEFAULT '0' COMMENT '性别',
        ADD COLUMN `photo` varchar(255) DEFAULT NULL COMMENT '个人照片',
        ADD COLUMN `certificate_no` varchar(60) DEFAULT NULL COMMENT '证书编号',
        ADD COLUMN `certificate_level` varchar(60) DEFAULT NULL COMMENT '证书级别',
        ADD COLUMN `start_time` date DEFAULT NULL COMMENT '起始时间',
        ADD COLUMN `end_time`  date DEFAULT NULL COMMENT '截至时间'");
    }

    if(pdo_fieldexists2("ddwx_certificate_poster_record","start_time")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_certificate_poster_record` 
            MODIFY COLUMN `start_time` varchar(60) DEFAULT NULL COMMENT '起始时间',
            MODIFY COLUMN `end_time` varchar(60) DEFAULT NULL COMMENT '截至时间';");
    }
}
if(getcustom('shop_add_stock_cost')) {
    if (!pdo_fieldexists2("ddwx_shop_stock_order_goods", "totalprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_stock_order_goods` 
ADD COLUMN `totalprice` decimal(11, 2) NULL DEFAULT 0 COMMENT '进货费' AFTER `afterstock`,
ADD COLUMN `cost_price` decimal(11, 2) NULL DEFAULT 0 COMMENT '成本单价' AFTER `totalprice`,
ADD COLUMN `create_date` int(11) NULL DEFAULT NULL COMMENT '生产日期' AFTER `cost_price`,
ADD COLUMN `quality_days` int(5) NULL DEFAULT NULL COMMENT '保质期（天）' AFTER `create_date`,
ADD COLUMN `expire_date` int(11) NULL DEFAULT NULL COMMENT '到期日期' AFTER `quality_days`,
ADD COLUMN `notice_days` int(5) NULL DEFAULT NULL COMMENT '提前x天提醒' AFTER `quality_days`;");
    }
    if (!pdo_fieldexists2("ddwx_shop_stock_order_goods", "outstock")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_stock_order_goods` ADD COLUMN `outstock` int(11) NULL DEFAULT '0' COMMENT '出库数量' AFTER `afterstock`;");
    }
}

if(getcustom('expend')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_expend` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT '0',
  `cid` int(11) DEFAULT '0',
  `money` decimal(11,2) DEFAULT '0',
  `remark` varchar(120) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `cid` (`cid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_expend_category` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT '0',
  `pid` int(11) DEFAULT '0',
  `name` varchar(60) DEFAULT NULL,
  `pic` varchar(255) DEFAULT NULL,
  `status` int(1) DEFAULT '1',
  `sort` int(11) DEFAULT '1',
  `createtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `pid` (`pid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
}
if(getcustom('consumer_value_add') && getcustom('greenscore_max')) {
    if (pdo_fieldexists3("ddwx_admin_bonuspool_log")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_bonuspool_log`
        MODIFY COLUMN `value`  decimal(12,2) NULL DEFAULT 0 ,
        MODIFY COLUMN `after`  decimal(12,2) NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","withdraw_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `withdraw_type`  tinyint(1) NULL DEFAULT 0 COMMENT '提取模式 0汇总提取 1单笔提取';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","maximum_set")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `maximum_set`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '封顶额度倍数';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","commission_to_greenscore")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `commission_to_greenscore`  tinyint(1) NULL DEFAULT 0 COMMENT '佣金转绿色积分 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","commission_to_bonuspool_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `commission_to_bonuspool_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '佣金转绿色积分到账奖金池比例';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","commission_to_greenscore_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `commission_to_greenscore_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '佣金转绿色积分到账绿色积分比例';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","commission_to_greenscore_min")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `commission_to_greenscore_min`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '佣金转绿色积分单笔最低值';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","commission_to_greenscore_desc")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `commission_to_greenscore_desc`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","hbstatus")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `hbstatus`  tinyint(1) NULL DEFAULT 0 COMMENT '增值红包 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","hbtext")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `hbtext`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '红包提示文字';");
    }
    if(!pdo_fieldexists2("ddwx_consumer_set","hbaccount")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `hbaccount`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '红包到账方式';");
    }
    if(!pdo_fieldexists2("ddwx_member","maximum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `maximum`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '封顶额度';");
    }
    if(!pdo_fieldexists2("ddwx_member_greenscore_log","maximum_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_greenscore_log` ADD COLUMN `maximum_num`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '封顶额度';");
    }
    if(!pdo_fieldexists2("ddwx_member","green_score_hb")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `green_score_hb`  decimal(12,2) NULL DEFAULT 0 COMMENT '领走的绿色积分增值红包数量';");
    }
    if (!pdo_fieldexists2("ddwx_greenscore_withdraw_log", "green_score_hb")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_greenscore_withdraw_log` ADD COLUMN `green_score_hb`  decimal(12,2) NULL DEFAULT 0 COMMENT '扣除的已领增值红包数量';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_greenscore_hb_log` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  int(11) NULL DEFAULT NULL ,
        `ordernum`  varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单号 用于微信支付宝打款' ,
        `hbmoney`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '红包金额' ,
        `hbaccount`  varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '红包收款账户' ,
        `green_score`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '绿色积分数量' ,
        `last_green_score_price`  decimal(12,4) NULL DEFAULT 0.0000 COMMENT '昨日的绿色积分价格' ,
        `green_score_price`  decimal(12,4) NULL DEFAULT 0.0000 COMMENT '今日的绿色积分价格' ,
        `createtime`  int(10) NULL DEFAULT 0 COMMENT '创建时间' ,
        `status`  tinyint(1) NULL DEFAULT 0 COMMENT '0未领取 1已领取 2领取失败' ,
        `errmsg`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `show_greenscore_hb`  int(11) NULL DEFAULT 0 COMMENT '红包弹窗日期' ,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_maximum_log` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
        `value`  decimal(12,2) NULL DEFAULT 0.00 ,
        `after`  decimal(12,2) NULL DEFAULT NULL ,
        `createtime`  int(11) NULL DEFAULT NULL ,
        `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `channel`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '变动渠道' ,
        `orderid`  int(11) NULL DEFAULT NULL ,
        `remain`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '剩余数量' ,
        PRIMARY KEY (`id`),
        INDEX `aid` (`aid`) USING BTREE ,
        INDEX `mid` (`mid`) USING BTREE 
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic;");
    if (!pdo_fieldexists2("ddwx_admin_bonuspool_log", "green_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_bonuspool_log` ADD COLUMN `green_score`  decimal(12,2) NULL DEFAULT 0.00 ;");
    }
    if (!pdo_fieldexists2("ddwx_admin_bonuspool_log", "green_score_total")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_bonuspool_log` ADD COLUMN `green_score_total`  decimal(12,2) NULL DEFAULT 0.00;");
    }
    if (!pdo_fieldexists2("ddwx_member_greenscore_log", "dec_maximum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_greenscore_log` ADD COLUMN `dec_maximum`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '剩余封顶额度';");
    }
    if (!pdo_fieldexists2("ddwx_member_greenscore_log", "withdraw_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_greenscore_log` ADD COLUMN `withdraw_num`  tinyint(1) NULL DEFAULT 0 COMMENT '提取数量';");
    }
    if (!pdo_fieldexists2("ddwx_member_greenscore_log", "is_withdraw")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_greenscore_log` ADD COLUMN `is_withdraw`  tinyint(1) NULL DEFAULT 0 COMMENT '1已提取';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order", "give_maximum")) {

        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `give_maximum`  decimal(12,2) NULL DEFAULT 0 COMMENT '赠送封顶额度';");
    }
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "give_maximum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `give_maximum`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '赠送封顶额度';");
    }
}
if(getcustom('consumer_value_add')){
    if (!pdo_fieldexists2("ddwx_member_greenscore_log", "jilu_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_greenscore_log` ADD COLUMN `jilu_id`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;");
        \app\custom\GreenScore::repair_greenscore_jilu();
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_greenscore_jilu` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  int(11) NULL DEFAULT NULL ,
        `value`  decimal(10,2) NULL DEFAULT 0.00 COMMENT '单笔总数量' ,
        `remain`  decimal(10,2) NULL DEFAULT 0.00 COMMENT '剩余数量' ,
        `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '备注信息' ,
        `orderid`  int(11) NULL DEFAULT 0 COMMENT '订单id' ,
        `createtime`  int(10) NULL DEFAULT 0 COMMENT '创建时间' ,
        `maximum_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '封顶额度' ,
        `green_score_price`  decimal(12,4) NULL DEFAULT 0.0000 COMMENT '绿色积分价格' ,
        `is_withdraw`  tinyint(1) NULL DEFAULT 0 COMMENT '是否已提取' ,
        `withdraw_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '已提取数量' ,
        `dec_maximum`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '已扣除的封顶额度' ,
        `hbmoney`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '已领红包数量' ,
        `dec_hbmoney`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '提取已扣除红包金额' ,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
}
if(getcustom('scoreshop_otheradmin_buy')){
    if (!pdo_fieldexists2("ddwx_scoreshop_order", "otheraid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order` 
            ADD COLUMN `otheraid` int(11) NULL DEFAULT 0 COMMENT '来自其他平台的id',
            ADD COLUMN `othermid` int(11) NULL DEFAULT 0 COMMENT '来自其他平台的用户id';");
    }
    if (!pdo_fieldexists2("ddwx_member_moneylog", "optaid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_moneylog` ADD COLUMN `optaid` int(11) NULL DEFAULT 0 COMMENT '其他平台兑换总平台扣除其他平记录操作来源aid';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_scorelog` ADD COLUMN `optaid` int(11) NULL DEFAULT 0 COMMENT '其他平台兑换总平台扣除其他平记录操作来源aid';");
    }
}
if(getcustom('shop_purchase_order')){
    if (!pdo_fieldexists2("ddwx_shop_sysset", "list_sort_show")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
        ADD COLUMN `list_sort_show` varchar(100) NULL DEFAULT '' COMMENT '列表排序显示字段';");
    }
}
if(getcustom('level_product_show_image')){
    if (!pdo_fieldexists2("ddwx_member_level", "show_image")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `show_image` tinyint(1) DEFAULT '0' COMMENT '限制大图观看';");
    }
}
if(getcustom('teamfenhong_pingji_fenhong')){
    if (!pdo_fieldexists2("ddwx_admin_set", "teamfenhong_pingji_fenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `teamfenhong_pingji_fenhong` tinyint(1) DEFAULT '0' COMMENT '团队分红平级奖计算基数'");
    }
}
if(getcustom('business_withdraw_otherset')){
    if (!pdo_fieldexists2("ddwx_business_sysset", "day_withdraw_num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
        ADD COLUMN `day_withdraw_num` int(11) DEFAULT '0',
        ADD COLUMN `withdrawmax` decimal(11,2) DEFAULT '0.00';");
    }
}
if(getcustom('business_maidan_team_fenhong')){
    if (!pdo_fieldexists2("ddwx_business", "maidan_fenhong_jl_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
        ADD COLUMN `maidan_fenhong_jl_status` tinyint(1) DEFAULT '0' COMMENT '买单参与分红奖励',
        ADD COLUMN `maidan_fenhong_jl_minprice` decimal(11,2) DEFAULT '0.00' COMMENT '买单参与分红奖励最低金额',
        ADD COLUMN `maidan_fenhong_jl_data` text COMMENT '买单参与分红等级比例';");
    }
    if (!pdo_fieldexists2("ddwx_business_sysset", "maidan_fenhong_jicha")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `maidan_fenhong_jicha` tinyint(1) DEFAULT '0' COMMENT '买单满额奖励级差开关';");
    }
    if (!pdo_fieldexists2("ddwx_business", "maidan_fenhong_jl_lv")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `maidan_fenhong_jl_lv` int(11) DEFAULT '0' COMMENT '发放级数';");
    }
}
if(getcustom('teamfenhong_not_send_cengji')){
    if (!pdo_fieldexists2("ddwx_member_level", "teamfenhong_not_lv")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `teamfenhong_not_lv` varchar(100) DEFAULT NULL COMMENT '团队分红不发放级数';");
    }
}
if(getcustom('coupon_cika_wxtmpl')){
    if (!pdo_fieldexists2("ddwx_wx_tmplset", "tmpl_couponhexiao")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wx_tmplset` ADD COLUMN `tmpl_couponhexiao` varchar(100) DEFAULT NULL COMMENT '优惠券计次卡核销';");
    }
}
if(getcustom('cashier_num_weishu')){
    if(pdo_fieldexists2("ddwx_cashier_order_goods","num")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order_goods` MODIFY COLUMN `num` decimal(11,2) DEFAULT '0.00';");
    }
}
if(getcustom('yx_queue_free_change_no')){
    if(!pdo_fieldexists2("ddwx_queue_free","change_no_time")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free` ADD COLUMN `change_no_time` int(11) DEFAULT '0' COMMENT '更改排队号时间';");
    }
}
if(getcustom('extend_material')){
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS`ddwx_material_category` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `name` varchar(255) DEFAULT NULL,
      `status` int(1) DEFAULT '1',
      `sort` int(11) DEFAULT '1',
      `type` tinyint(1) DEFAULT '0' COMMENT '类型 0：图片 1：视频 ',
      `createtime` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");


    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_material_upload` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `uid` int(11) DEFAULT NULL,
      `cid` int(11) DEFAULT NULL COMMENT '分类id',
      `name` varchar(255) DEFAULT NULL COMMENT '文件名称',
      `dir` varchar(255) DEFAULT NULL COMMENT '文件名',
      `url` varchar(255) DEFAULT NULL COMMENT '文件地址',
      `cover` varchar(255) DEFAULT NULL COMMENT '封面',
      `type` varchar(100) DEFAULT NULL COMMENT '文件类型',
      `size` varchar(255) DEFAULT NULL COMMENT '文件大小',
      `width` int(11) DEFAULT 0 COMMENT '图片宽度',
      `height` int(11) DEFAULT 0 COMMENT '图片高度',
      `createtime` int(11) DEFAULT NULL COMMENT '上传时间',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `uid` (`uid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('recharge_order_wifiprint')){
    if(!pdo_fieldexists2("ddwx_wifiprint_set","print_recharge")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wifiprint_set` ADD COLUMN `print_recharge` tinyint(1) DEFAULT '0' COMMENT '充值订单打印';");
    }
    if(!pdo_fieldexists2("ddwx_recharge_order","uid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_recharge_order` ADD COLUMN `uid` int(11) DEFAULT '0' COMMENT '收银台充值的收银台管理员账号';");
    }
}
if(getcustom('commission_to_money')){
     if(!pdo_fieldexists2("ddwx_admin_set","commission_send_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `commission_send_money` tinyint(1) NOT NULL DEFAULT 0 COMMENT '佣金发放到余额 0:关闭 1：开启';");
     }
    if(!pdo_fieldexists2("ddwx_admin_set","commission_send_money_bili")) {
      \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `commission_send_money_bili` decimal(11,2) DEFAULT '100.00' COMMENT '佣金发放到余额比例';");
    }
}
if(getcustom('fenhong_cashier_order')){
    if(!pdo_fieldexists2("ddwx_admin_set","fenhong_cashier_order_money")) {
      \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `fenhong_cashier_order_money` tinyint(1) DEFAULT '0' COMMENT '收银台订单股东分红';");
    }
    if(!pdo_fieldexists2("ddwx_cashier_order_goods","isfenhong")) {
      \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order_goods` ADD COLUMN `isfenhong` tinyint(1) DEFAULT '0' COMMENT '是否分红';");
    }
}

if(getcustom('lvprice_jicha_lv')){
    if(!pdo_fieldexists2("ddwx_shop_product","lvprice_jicha_lv")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `lvprice_jicha_lv` int(11) DEFAULT '0' COMMENT '等级价格级差发放代数限制';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","lvprice_jicha_origin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `lvprice_jicha_origin` tinyint(1) DEFAULT '0' COMMENT '等级价格级是否发放给原上级 0否 1是';");
    }
}

if(getcustom('maidan_dh')){
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_maidan_dh` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `bid` int(11) NULL DEFAULT NULL,
        `menudata` text NULL,
        `backgroundColor` varchar(20) NULL DEFAULT NULL,
        `status` tinyint(1) NOT NULL DEFAULT 0,
        `updatetime` int(11) NULL DEFAULT 0,
        `createtime` int(11) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '买单页面导航' ROW_FORMAT=DYNAMIC;");
}

if(getcustom('maidan_invite')){
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_maidan_invite` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `bid` int(11) NULL DEFAULT 0,
        `invitedata` text NULL COMMENT '拉新奖励设置',
        `validday` int(11) NULL DEFAULT 0 COMMENT '邀请有效时间',
        `status` tinyint(1) NULL DEFAULT 0,
        `createtime` int(11) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '买单拉新设置' ROW_FORMAT=DYNAMIC;");
    if(!pdo_fieldexists2("ddwx_business","maidaninviteset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `maidaninviteset` tinyint(1) NULL DEFAULT 0 COMMENT '买单拉新 -1：不开启 0:按系统设置 1：单独设置 ';");
    }
    if(!pdo_fieldexists2("ddwx_maidan_order","invitemoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `invitemoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '拉新奖励金额' ;");
    }
}

if(getcustom('business_show_maidanscoredk')){
    if(!pdo_fieldexists2("ddwx_business_sysset","show_maidanscoredk")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `show_maidanscoredk` tinyint(1) NULL DEFAULT 0 COMMENT '商家列表是否显示积分抵扣';");
    }
}

if(getcustom('shoporder_ranking')){
    if(!pdo_fieldexists2("ddwx_shoporder_ranking_set","showpool")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `showpool` tinyint(1) NOT NULL DEFAULT 1 COMMENT '显示总奖金池 0:关闭 1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `showpool` tinyint(1) NOT NULL DEFAULT 1 COMMENT '显示总奖金池 0:关闭 1：开启';");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `showchildpool` tinyint(1) NOT NULL DEFAULT 1 COMMENT '显示子奖金池 0:关闭 1：开启';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `showchildpool` tinyint(1) NOT NULL DEFAULT 1 COMMENT '显示子奖金池 0:关闭 1：开启';");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `showselfranking` tinyint(1) NOT NULL DEFAULT 0 COMMENT '显示自己排名 0:关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `showselfranking` tinyint(1) NOT NULL DEFAULT 0 COMMENT '显示自己排名 0:关闭 1：开启';");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `showlast` tinyint(1) NOT NULL DEFAULT 0 COMMENT '显示自己排名 0:关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `showlast` tinyint(1) NOT NULL DEFAULT 0 COMMENT '显示自己排名 0:关闭 1：开启';");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `nickname_center_hidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '昵称中间隐藏 0：关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `nickname_center_hidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '昵称中间隐藏 0：关闭 1：开启';");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `childprefix_addmonth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '子奖金池名称前加月份 0：关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `childprefix_addmonth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '子奖金池名称前加月份 0：关闭 1：开启' ;");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `changemoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '总奖池变动金额' ;");

        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` 
            ADD COLUMN `join_ordertype` varchar(255) BINARY NULL DEFAULT 'shop' COMMENT '平台参与订单' ,
            ADD COLUMN `join_ordertype2` varchar(255) BINARY NULL DEFAULT 'shop' COMMENT '多商户参与订单' ;");
    }

    if(!pdo_fieldexists2("ddwx_shoporder_ranking_set","sendstatus")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_set` ADD COLUMN `sendstatus` tinyint(1) NOT NULL DEFAULT 0 COMMENT '发放奖励  0:关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` ADD COLUMN `sendstatus` tinyint(1) NOT NULL DEFAULT 0 COMMENT '发放奖励  0:关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log` 
            ADD COLUMN `issend` tinyint(1) NULL DEFAULT 0 COMMENT '是否发放 0：否 1：是',
            ADD COLUMN `sendtime` bigint(20) NULL DEFAULT 0 COMMENT '发放时间';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shoporder_ranking_log_detail` 
            ADD COLUMN `issend` tinyint(1) NULL DEFAULT 0 COMMENT '是否发放 0：否 1：是' ,
            ADD COLUMN `sendtime` bigint(20) NULL DEFAULT 0 COMMENT '发放时间';");
    }
}

if(getcustom('extend_linghuoxin')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_linghuoxin_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `bid` int(11) NOT NULL DEFAULT 0,
        `app_id` varchar(50) NULL DEFAULT '' COMMENT '应用id',
        `secret` varchar(100) NULL DEFAULT '' COMMENT '应用密钥',
        `corpid` varchar(30) NULL DEFAULT '' COMMENT '企业编号',
        `taskid` varchar(20) NULL DEFAULT '' COMMENT '任务id',
        `public_key` varchar(255) NULL DEFAULT NULL COMMENT '公钥',
        `status` tinyint(1) NOT NULL DEFAULT 0,
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `apiurl` varchar(100) NULL COMMENT '接口域名',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `bid`(`bid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '灵活薪设置' ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_member_linghuoxin_signlog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `bid` int(11) NOT NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0,
        `realname` varchar(30) NULL DEFAULT '' COMMENT '姓名',
        `tel` varchar(30) NULL DEFAULT '' COMMENT '电话',
        `usercard` varchar(30) NULL DEFAULT '' COMMENT '身份证',
        `bankcardnum` varchar(30) NULL DEFAULT NULL COMMENT '银行卡号',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:待提交  1：已实名认证 2：已签约',
        `createtime` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        `idFace` varchar(255) NULL DEFAULT '' COMMENT '身份证正面',
        `idReverse` varchar(255) NULL DEFAULT '' COMMENT '身份证反面',
        `resdata` text NULL COMMENT '回调返还数据',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `usercard`(`usercard`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '灵活薪签约记录' ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_member_linghuoxin_withdrawlog` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `logid` int(11) NULL DEFAULT 0 COMMENT '提现id',
        `mid` int(11) NULL DEFAULT 0,
        `ordernum` varchar(30) NULL DEFAULT '',
        `type` tinyint(1) NULL DEFAULT 0 COMMENT '提现类型 1：余额 2：佣金',
        `taskNo` varchar(30) NULL DEFAULT '' COMMENT '灵活薪打款任务号',
        `taskdata` text NULL COMMENT '灵活薪成功返回的数据',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '-1：失败 0：待提交 1：已提交 2: 已通过',
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `message` varchar(255) NULL DEFAULT NULL,
        `endtime` bigint(20) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `ordernum`(`ordernum`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '灵活薪提现记录' ROW_FORMAT=DYNAMIC;");

    if(!pdo_fieldexists2("ddwx_admin_set","withdraw_bankcard_linghuoxin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
                ADD COLUMN `withdraw_bankcard_linghuoxin` tinyint(1) NULL DEFAULT 0 COMMENT '灵活薪通银行卡' ,
                ADD COLUMN `withdraw_aliaccount_linghuoxin` tinyint(1) NULL DEFAULT 0 COMMENT '灵活薪支付宝' ;");
    }
    
    if(!pdo_fieldexists2("ddwx_member","linghuoxin_signlogid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `linghuoxin_signlogid` int NULL DEFAULT 0 COMMENT '灵活薪签约记录';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_withdrawlog` 
            ADD COLUMN `taskNo` varchar(30) NULL DEFAULT '' COMMENT '灵活薪打款任务号' ,
            ADD COLUMN `taskdata` text NULL COMMENT '灵活薪成功返回的数据' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` 
            ADD COLUMN `taskNo` varchar(30) NULL DEFAULT '' COMMENT '灵活薪打款任务号' ,
            ADD COLUMN `taskdata` text NULL COMMENT '灵活薪成功返回的数据' ;");
    }
}
if(getcustom('cashier_business_multi_account')){
    if(!pdo_fieldexists2("ddwx_cashier","uid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier` 
                ADD COLUMN `uid` int(11) DEFAULT '0' COMMENT '管理id' ;");
    }
}


if(getcustom('money_transfer')){
    if(!pdo_fieldexists2("ddwx_admin_set","money_transfer_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `money_transfer_type` varchar(30) NULL DEFAULT 'id' AFTER `money_transfer_pwd`;");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","money_transfer_min")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `money_transfer_min` int(5) NULL DEFAULT '0' AFTER `money_transfer_pwd`;");
    }
}
if(getcustom('restaurant_shop_select_renshu')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_sysset","select_renshu_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` ADD COLUMN `select_renshu_status` tinyint(1) DEFAULT '1' COMMENT '选择人数';");
    }
}
if(getcustom('fengdanjiangli')){
    if(!pdo_fieldexists2("ddwx_member_commission_withdrawlog","paymoney")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` ADD COLUMN `paymoney`  decimal(11,2) DEFAULT '0.00' COMMENT '打款金额';");
    }
   if(!pdo_fieldexists2("ddwx_member_commission_withdrawlog","tomoney")) {
      \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` ADD COLUMN `tomoney`  decimal(11,2) DEFAULT '0.00' COMMENT '到余额';");
    }
}

if(getcustom('product_library_admin_user')){
    if(!pdo_fieldexists2("ddwx_admin","product_library_aid")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin` ADD COLUMN `product_library_aid` int(11) NULL DEFAULT 1 COMMENT '公共库id';");
    }
}
if(getcustom('shop_purchase_order')){
    if(!pdo_fieldexists2("ddwx_purchase_order","remark")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_purchase_order` ADD COLUMN `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '备注';");
    }
}
if(getcustom('restaurant_is_apply_refund')){
    if(!pdo_fieldexists2("ddwx_restaurant_admin_set","is_apply_refund")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_admin_set` ADD COLUMN `is_apply_refund` tinyint(1) DEFAULT '1' COMMENT '点餐/外卖订单开启消费者自助申请退款';");
    }
}
if(getcustom('moneylog_detail')){
    if(!pdo_fieldexists2("ddwx_member_moneylog","ordernum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_moneylog` 
        ADD COLUMN `ordernum` varchar(100) DEFAULT NULL,
        ADD COLUMN `type` varchar(100) DEFAULT NULL;");
    }
}
if(getcustom('restaurant_mobile_admin_refund')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order_goods","refund_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` 
        ADD COLUMN `refund_money` decimal(11,2) DEFAULT '0.00' COMMENT '退款金额',
        ADD COLUMN `refund_num` int(11) DEFAULT '0' COMMENT '退款数量';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_takeaway_order_goods","refund_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_takeaway_order_goods` 
        ADD COLUMN `refund_money` decimal(11,2) DEFAULT '0.00' COMMENT '退款金额',
        ADD COLUMN `refund_num` int(11) DEFAULT '0' COMMENT '退款数量';");
    }
}
if(getcustom('hexiao_auto_wifiprint')){
    if(!pdo_fieldexists2("ddwx_wifiprint_set","print_hexiao")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wifiprint_set` 
        ADD COLUMN `print_hexiao` tinyint(1) DEFAULT '0' COMMENT '核销自动打印';");
    }
}
if(getcustom('teamfenhong_pingji_source')){
    if(!pdo_fieldexists2("ddwx_admin_set","teamfenhong_pingji_source")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `teamfenhong_pingji_source` tinyint(1) DEFAULT '0' COMMENT '团队分红平级奖来源 0平台 1上级团队分红`';");
    }
}
if(getcustom('yx_queue_free')){
    if (!pdo_fieldexists2("ddwx_queue_free_set", "product_join")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `product_join` tinyint(1) DEFAULT '0' COMMENT '商品默认参与 添加商品时，参与排队免单开关将继承此设置';");
    }
}
if(getcustom('image_search')){
    if (!pdo_fieldexists2("ddwx_shop_product", "baidu_img_sync_l")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `baidu_img_sync_l` tinyint(1) DEFAULT '0' COMMENT '多商户图搜是否同步';");
    }
}
if(getcustom('design_business_history')){
    if (!pdo_fieldexists2("ddwx_business_sysset", "homepage_need_login")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `homepage_need_login` tinyint(1) DEFAULT '0' COMMENT '1首页强制登录0不需要登录';");
    }
}
if(getcustom('lipinka_commission')){
    if (!pdo_fieldexists2("ddwx_lipin", "commission_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_lipin` ADD COLUMN `commission_status` tinyint(1) DEFAULT '0' COMMENT '分销状态';");
    }
}
if(getcustom('lipinka_freight_free')){
    if (!pdo_fieldexists2("ddwx_lipin", "freight_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_lipin` ADD COLUMN `freight_status` tinyint(1) DEFAULT '0' COMMENT '运费状态';");
    }
}
if(getcustom('team_jiandian')){
    if (!pdo_fieldexists2("ddwx_member_level", "team_jiandian_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `team_jiandian_status`  tinyint(1) NULL DEFAULT 0 COMMENT '购物发放团队见点奖 0关闭 1开启';");
    }
    if (!pdo_fieldexists2("ddwx_member_level", "team_jiandian_people")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `team_jiandian_people`  int(11) NULL DEFAULT 0 COMMENT '团队见点奖发放人数';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "teamjiandianset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `teamjiandianset`  tinyint(1) NULL DEFAULT 0 COMMENT '团队见点奖 0不参与分红 1单独设置分红比例 2单独设置分红金额';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "teamjiandiandata1")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `teamjiandiandata1`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '团队见点奖按比例参数';");
    }
    if (!pdo_fieldexists2("ddwx_shop_product", "teamjiandiandata2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `teamjiandiandata2`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '团队见点奖按金额参数';");
    }
}
if(getcustom('team_fuchijin')){
    if (!pdo_fieldexists2("ddwx_member_level", "team_fuchijin_lv")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `team_fuchijin_lv`  int(10) NULL DEFAULT 0 COMMENT '团队扶持金拿奖级数';");
    }
    if (!pdo_fieldexists2("ddwx_member_level", "team_fuchijin_bl")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `team_fuchijin_bl`  int(11) NULL DEFAULT 0 COMMENT '团队扶持金发放比例';");
    }
}

if(getcustom('kecheng_chaptertype') || getcustom('kecheng_lecturer')){
    if (!pdo_fieldexists2("ddwx_kecheng_list", "chaptertype")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_list` 
            ADD COLUMN `chaptertype` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个章节）',
            ADD COLUMN `chapterid` int NULL DEFAULT 0 COMMENT '关联的章节ID';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_chapter` 
            ADD COLUMN `chaptertype` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个章节）';");
    }
}

if(getcustom('kecheng_lecturer')){
    if (!pdo_fieldexists2("ddwx_kecheng_list", "lecturerid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_list` 
            ADD COLUMN `lecturerid` int NULL DEFAULT 0 COMMENT '讲师ID',
            ADD COLUMN `freecontent` text NULL COMMENT '免费内容' ,
            ADD COLUMN `ischecked` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否审核通过 0：待审核  1：已通过2：已驳回';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_chapter` ADD COLUMN `freecontent` text NULL COMMENT '免费内容' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_user` ADD COLUMN `lecturerid` int NULL DEFAULT 0 COMMENT '课程讲师ID' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_order` 
            ADD COLUMN `lecturerid` int NULL DEFAULT 0 COMMENT '讲师ID' ,
            ADD COLUMN `lecturer_sendmoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '讲师发放的分佣',
            ADD COLUMN `lecturer_commissionratio` decimal(10, 2) NULL DEFAULT 0 COMMENT '讲师发放分佣比例',
            ADD COLUMN `lecturer_mid` int(11) NULL DEFAULT 0 COMMENT '讲师关联的会员id' ;");
    }
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_kecheng_lecturer` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT NULL,
        `mid` int(11) NULL DEFAULT NULL,
        `nickname` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        `headimg` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
        `realname` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
        `shortdesc` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '简介',
        `tel` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
        `pwd` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
        `commissionratio` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '分佣比例',
        `money` decimal(11, 2) NULL DEFAULT 0.00,
        `totalmoney` decimal(11, 2) NULL DEFAULT 0.00,
        `totalnum` int(11) NULL DEFAULT 0,
        `checkstatus` tinyint(1) NULL DEFAULT 1 COMMENT '审核状态 -1 驳回 0：待审核 1：已通过',
        `checkreason` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '审核原因',
        `status` tinyint(11) NULL DEFAULT 1,
        `sort` int(11) NULL DEFAULT 0,
        `userid` int NULL DEFAULT 0 COMMENT '管理员id',
        `createtime` int(11) NULL DEFAULT 0,
        `updatetime` int(11) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '课程讲师' ROW_FORMAT=DYNAMIC;");
}

if(getcustom('withdraw_paycode')){
    if (!pdo_fieldexists2("ddwx_member", "wxpaycode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
            ADD COLUMN `wxpaycode` varchar(255) NULL DEFAULT '' COMMENT '微信收款码' ,
            ADD COLUMN `alipaycode` varchar(255) NULL DEFAULT '' COMMENT '支付宝收款码';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `withdraw_paycode` tinyint(1) NULL DEFAULT 0 COMMENT '收款码提现 0：关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_withdrawlog` 
            ADD COLUMN `wxpaycode` varchar(255)  NULL DEFAULT '' COMMENT '微信收款码' ,
            ADD COLUMN `alipaycode` varchar(255)  NULL DEFAULT '' COMMENT '支付宝收款码' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` 
            ADD COLUMN `wxpaycode` varchar(255)  NULL DEFAULT '' COMMENT '微信收款码' ,
            ADD COLUMN `alipaycode` varchar(255)  NULL DEFAULT '' COMMENT '支付宝收款码' ;");
    }
}
if(getcustom('yx_queue_free_today_average')){
    if (!pdo_fieldexists2("ddwx_queue_free_set", "today_fixed_ratio")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN `today_fixed_ratio` decimal(10,2) DEFAULT '0.00' COMMENT '今日平均 固定比例',
            ADD COLUMN `today_pj_ratio` decimal(10,2) DEFAULT '0.00' COMMENT '今日平均  平均比例',
            ADD COLUMN `today_other_ratio` decimal(10,2) DEFAULT '0.00' COMMENT '今日平均 其他比例（非当日）';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free_set", "mode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN `mode` tinyint(1) DEFAULT '0';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free_log", "mode")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_log` 
            ADD COLUMN `mode` tinyint(1) DEFAULT '0',
            ADD COLUMN `remark` varchar(255) DEFAULT NULL;");
    }
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_queue_free_pool` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `bid` int(11) NOT NULL,
      `money` decimal(10,2) DEFAULT '0.00',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_queue_free_pool_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `bid` int(11) DEFAULT NULL,
      `money` decimal(10,2) DEFAULT '0.00',
      `queueid` int(11) DEFAULT NULL,
      `ordernum` varchar(255) DEFAULT NULL COMMENT '产生非今日平均的订单号',
      `type` varchar(20) DEFAULT NULL COMMENT '产生非今日平均的订单类型',
      `remark` varchar(255) DEFAULT NULL,
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    
    if (!pdo_fieldexists2("ddwx_queue_free_pool", "today_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_pool` 
            ADD COLUMN `today_money` decimal(10,2) DEFAULT '0.00';");
    }
    if (!pdo_fieldexists2("ddwx_queue_free_pool_log", "money_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_pool_log` 
            ADD COLUMN `money_type` varchar(20) DEFAULT NULL COMMENT '加钱类型 today:当日，yesterday：非当日';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS  `ddwx_queue_free_today_average` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) DEFAULT NULL,
          `bid` int(11) DEFAULT '0',
          `mid` int(11) DEFAULT '0',
          `order` text,
          `pj_money` decimal(11,2) DEFAULT NULL,
          `newlog` text,
          `where` text,
          `set` text,
          `rate_back` decimal(11,2) DEFAULT NULL,
          `mode` tinyint(1) DEFAULT NULL,
          `remark` varchar(255) DEFAULT NULL,
          `status` tinyint(1) DEFAULT '0' COMMENT '0:未发放 1：已发放',
          `createtime` int(11) DEFAULT NULL,
          `endtime` int(11) DEFAULT '0',
          PRIMARY KEY (`id`),
          KEY `aid` (`aid`) USING BTREE,
          KEY `bid` (`bid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='排队免单 平均分配数据表';");
}
 
if(getcustom('sign_forget')){
    if (!pdo_fieldexists2("ddwx_signset", "is_forget")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` ADD COLUMN `is_forget`  tinyint(1) NULL DEFAULT 0 COMMENT '补签开关 0关 1开';");
    }
    if (!pdo_fieldexists2("ddwx_signset", "is_check")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` ADD COLUMN `is_check`  tinyint(1) NULL DEFAULT 0 COMMENT '签到是否需要审核 0否 1是';");
    }
    if(!pdo_fieldexists2("ddwx_signset", "condition")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` ADD COLUMN `condition`  tinyint(1) NULL DEFAULT 1 COMMENT '补签条件1无条件 2签到赠送';");
    }
    if(!pdo_fieldexists2("ddwx_signset", "lxqd_forget")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` ADD COLUMN `lxqd_forget`  tinyint(1) NULL DEFAULT 0 COMMENT '补签条件2无条件 2签到赠送';");
    }
    if(!pdo_fieldexists2("ddwx_signset", "bq_day")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` ADD COLUMN `bq_day`  int(11) NULL DEFAULT 0 COMMENT '赠送补签天数';");
    }    
    if(!pdo_fieldexists2('ddwx_sign_order','bq_time')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sign_order` ADD COLUMN `bq_time`  date NULL  COMMENT '补签时间';");
    }
 
    if(!pdo_fieldexists2('ddwx_sign_record','status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sign_record` ADD COLUMN `status`  tinyint(1) NULL DEFAULT 1 COMMENT '审核状态0待审核1通过2未通过';");
    }
    if(!pdo_fieldexists2('ddwx_member','bq_day')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `bq_day`  int(11) NULL DEFAULT 0 COMMENT '补签天数';");
    }
}

if(getcustom('sign_camera')){
    if(!pdo_fieldexists2("ddwx_signset", "camera")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_signset` ADD COLUMN `camera`  tinyint(1) NULL DEFAULT 0 COMMENT '是否拍照/视频0=无条件|1=拍照|2=拍视频单选';");
    }
    if(!pdo_fieldexists2('ddwx_sign_order','sign_img')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sign_order` ADD COLUMN `sign_img`  varchar(255) NULL  COMMENT '照片路径';");
    }
    if(!pdo_fieldexists2('ddwx_sign_order','sign_video')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sign_order` ADD COLUMN `sign_video`  varchar(255) NULL  COMMENT '视频路径';");
    }
    if(!pdo_fieldexists2('ddwx_sign_record','sign_video')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sign_record` ADD COLUMN `sign_video`  varchar(255) NULL  COMMENT '视频路径';");
    }
    if(!pdo_fieldexists2('ddwx_sign_record','sign_img')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sign_record` ADD COLUMN `sign_img`  varchar(255) NULL  COMMENT '照片路径';");
    }
}
if(getcustom('teamfenhong_pingji_diji_bonus')){
    if(!pdo_fieldexists2('ddwx_admin_set','teamfenhong_pingji_diji_bonus')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `teamfenhong_pingji_diji_bonus` tinyint(1) DEFAULT '0' COMMENT '低级平级奖 0关闭 1开启';");
    }
}
if(getcustom('member_register_give_parent_score')){
    if(!pdo_fieldexists2('ddwx_register_giveset','introscore')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_register_giveset` ADD COLUMN `introscore` decimal(12,3) DEFAULT '0.000' COMMENT '注册增送上级积分';");
    }
}

if(getcustom('extend_invite_redpacket')){
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_invite_redpacket` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT NULL,
        `bid` int(11) NULL DEFAULT 0,
        `name` varchar(30)  NOT NULL COMMENT '名称',
        `pic` varchar(255)  NULL DEFAULT '',
        `fwtype` tinyint(1) NULL DEFAULT 0,
        `categoryids` varchar(255)  NULL DEFAULT NULL,
        `productids` varchar(255)  NULL DEFAULT NULL,
        `starttime` int(11) NULL DEFAULT NULL,
        `endtime` int(11) NULL DEFAULT NULL,
        `redpacket` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '红包金额',
        `getradio` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '可得红包比例',
        `newnum` int(11) NULL DEFAULT 0 COMMENT '邀请新用户人数',
        `newday` int(11) NULL DEFAULT 0 COMMENT '邀请时间（天）',
        `sendtype` tinyint(1) NULL DEFAULT 0 COMMENT '发放账户 1 余额 2 佣金',
        `shortcontent` text  NULL,
        `content` text  NULL,
        `status` int(1) NULL DEFAULT 1,
        `sort` int(11) NULL DEFAULT 0,
        `createtime` int(11) NULL DEFAULT NULL,
        `updatetime` int(11) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `bid`(`bid`) USING BTREE,
        INDEX `status`(`status`) USING BTREE,
        INDEX `starttime`(`starttime`) USING BTREE,
        INDEX `endtime`(`endtime`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '邀请分红包' ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_member_invite_redpacket_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0,
        `proid` int(11) NOT NULL DEFAULT 0 COMMENT '商品id',
        `num` int(11) NOT NULL DEFAULT 0 COMMENT '购买数量',
        `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单id(暂只商城)',
        `order_gid` int(11) NOT NULL DEFAULT 0 COMMENT '订单商品表id',
        `order_mid` int(11) NOT NULL DEFAULT 0 COMMENT '订单用户id',
        `redpacketid` int(11) NOT NULL DEFAULT 0 COMMENT '邀新活动id',
        `name` varchar(30)  NULL DEFAULT '' COMMENT '名称',
        `pic` varchar(255)  NULL DEFAULT '',
        `redpacket` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '红包金额',
        `getradio` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '红包所有者获取红包金额比例',
        `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '得到的金额',
        `resmoney` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '剩余的金额',
        `decresmoney` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '变动剩余的金额',
        `newnum` int(11) NULL DEFAULT 0 COMMENT '邀请新用户人数',
        `decnewnum` int(11) NULL DEFAULT 0 COMMENT '变动邀请新用户人数',
        `newday` int(11) NULL DEFAULT 0 COMMENT '邀请时间（天）',
        `cuttime` int(11) NULL DEFAULT NULL COMMENT '截止时间',
        `sendtype` tinyint(1) NOT NULL DEFAULT 0 COMMENT '发放账户 1:余额 2：佣金',
        `shortcontent` text  NULL,
        `content` text  NULL,
        `categoryids` varchar(255)  NOT NULL DEFAULT '',
        `productids` varchar(255)  NOT NULL DEFAULT '',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0：待使用 1：已使用  -1：已过期',
        `sendtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放时间',
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `closetime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '取消时间',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `order_id`(`order_id`) USING BTREE,
        INDEX `redpacketid`(`redpacketid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `proid`(`proid`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `cuttime`(`cuttime`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '会员邀请分红包记录' ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_member_invite_redpacket_log_detail` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0,
        `logid` int(11) NOT NULL DEFAULT 0 COMMENT '记录ID',
        `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '得到的金额',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0：待使用 1：已使用  -1：已过期',
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `sendtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `closetime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '取消时间',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `logid`(`logid`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '会员邀请分红包-拆红包记录' ROW_FORMAT=DYNAMIC;");

    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_invite_redpacket_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `takenum` int(11) NOT NULL DEFAULT 0 COMMENT '老用户可拆次数',
        `takeday` int(11) NOT NULL DEFAULT 0 COMMENT '老用户下次拆间隔天数',
        `createtime` int(11) NULL DEFAULT NULL,
        `updatetime` int(11) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 COMMENT = '邀请分红包设置' ROW_FORMAT=DYNAMIC;");
}

if(getcustom('sysset_scoredkmaxpercent_memberset')){
    if(!pdo_fieldexists2('ddwx_admin_set','scoredkmaxpercent_memberset')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `scoredkmaxpercent_memberset` tinyint(1) NOT NULL DEFAULT 0 COMMENT '最多抵扣百分比会员单独设置 0：否 1：是',
            ADD COLUMN `scoredkmaxpercent_memberdata` text NULL COMMENT '最多抵扣百分比会员单独设置数据';");
    }
}

if(getcustom('yueke_extend')){
    if (!pdo_fieldexists2("ddwx_yueke_product", "guigedata")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yueke_product`
            ADD `yuyue_model` tinyint(1) NULL DEFAULT 1 COMMENT '预约模式 1：一对多模式 2：一对一陪教',
            ADD `select_worker` tinyint(1) NULL DEFAULT 0 COMMENT '选择教练 0：固定选择 1：随机分配',
            ADD `guigedata`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL ,
            ADD `commissionset` tinyint(1) NULL DEFAULT 0,
		    ADD `commissiondata1` text,
            ADD `commissiondata2` text,
            ADD `commissiondata3` text,
            ADD `workercommissiondata` text;");
    }

    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_yueke_guige` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `proid` int(11) DEFAULT NULL COMMENT '商品ID',
      `name` varchar(255) DEFAULT NULL COMMENT '规格名称',
      `pic` varchar(255) DEFAULT NULL COMMENT '规格图片',
      `sell_price` decimal(11,2) DEFAULT '0.00' COMMENT '销售价',
      `kecheng_num` int(11) DEFAULT '0' COMMENT '课程数量',
      `duration` int(11) DEFAULT '0' COMMENT '时长（课时）',
      `sales` int(11) DEFAULT '0' COMMENT '已售数量',
      `ks` varchar(255) DEFAULT NULL COMMENT '规格结构',
      `limit_start` int(11) DEFAULT '0' COMMENT '起售数量',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `proid` (`proid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='商品规格';");

    if (!pdo_fieldexists2("ddwx_yueke_order", "ggid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yueke_order`
            ADD `parent1` int(11) DEFAULT NULL,
            ADD `parent2` int(11) DEFAULT NULL,
            ADD `parent3` int(11) DEFAULT NULL,
            ADD `parent1commission` decimal(11,2) DEFAULT '0.00' COMMENT '一级分销佣金',
            ADD `parent2commission` decimal(11,2) DEFAULT '0.00' COMMENT '二级分销佣金',
            ADD `parent3commission` decimal(11,2) DEFAULT '0.00' COMMENT '三级分销佣金',
            ADD `workercommission` decimal(11,2) DEFAULT '0.00' COMMENT '服务人员分销佣金',
            ADD `ggid` int(11) NULL DEFAULT NULL COMMENT '规格ID',
            ADD `ggname` varchar(255) NULL DEFAULT NULL COMMENT '规格名称',
            ADD `sell_price` decimal(11,2) NULL DEFAULT '0.00' COMMENT '销售价',
            ADD `total_kecheng_num` int(11) NULL DEFAULT 0 COMMENT '累计课程',
            ADD `used_kecheng_num` int(11) NULL DEFAULT 0 COMMENT '使用课程',
            ADD `return_kecheng_num` int(11) NULL DEFAULT 0 COMMENT '退款课程';");
    }


    \think\facade\Db::execute("CREATE TABLE  IF NOT EXISTS `ddwx_yueke_study_record` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `mid` int(11) DEFAULT NULL COMMENT '会员ID',
      `orderid` int(11) DEFAULT NULL COMMENT '订单ID',
      `ordernum` varchar(50) DEFAULT NULL COMMENT '订单号',
      `proid` int(11) DEFAULT NULL COMMENT '商品ID',
      `name` varchar(255) DEFAULT NULL COMMENT '商品名称',
      `workerid` int(11) DEFAULT NULL COMMENT '服务人员ID',
      `start_study_time` int(11) DEFAULT NULL COMMENT '开始学习时间', 
      `parent1` int(11) DEFAULT NULL,
      `parent2` int(11) DEFAULT NULL,
      `parent3` int(11) DEFAULT NULL,
      `parent1commission` decimal(11,2) DEFAULT '0.00' COMMENT '一级分销佣金',
      `parent2commission` decimal(11,2) DEFAULT '0.00' COMMENT '二级分销佣金',
      `parent3commission` decimal(11,2) DEFAULT '0.00' COMMENT '三级分销佣金',
      `workercommission` decimal(11,2) DEFAULT '0.00' COMMENT '服务人员佣金',
      `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放',
      `comment` varchar(255) DEFAULT '' COMMENT '评价',
      `hx_time` int(11) DEFAULT NULL COMMENT '核销时间',
      `status`  tinyint(1) NULL DEFAULT 0 COMMENT '状态 0:待预约 1上课中 2已完成 3已退款',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `orderid` (`orderid`) USING BTREE,
      KEY `proid` (`proid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='学习记录';");

    if (!pdo_fieldexists2("ddwx_yueke_study_record", "workermid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yueke_study_record` ADD COLUMN `workermid` int(11) DEFAULT NULL AFTER `parent3`;");
    }

    if (!pdo_fieldexists2("ddwx_yueke_set", "autohexiao")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yueke_set` ADD COLUMN `autohexiao` int(11) NULL DEFAULT 60 COMMENT '自动核销课程';");
    }

    if (!pdo_fieldexists2("ddwx_yueke_worker", "shstatus")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yueke_worker` 
        ADD COLUMN `reason` varchar(255) DEFAULT NULL COMMENT '驳回原因',
        ADD COLUMN `shstatus` tinyint(2) DEFAULT 0 COMMENT '0 待审核 1已通过  2 已驳回';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_yueke_worker_moneylog` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `uid` int(11) DEFAULT NULL,
      `money` decimal(11,2) DEFAULT '0.00',
      `after` decimal(11,2) DEFAULT '0.00',
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `uid` (`uid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if (!pdo_fieldexists2("ddwx_yueke_set", "withdraw_weixin")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yueke_set` 
         ADD COLUMN `withdraw_weixin` tinyint(1) DEFAULT '1',
         ADD COLUMN `withdraw_aliaccount` tinyint(1) DEFAULT '1',
         ADD COLUMN `withdraw_bankcard` tinyint(1) DEFAULT '1',
         ADD COLUMN `withdrawmin` varchar(255) DEFAULT '10',
         ADD COLUMN `withdrawfee` varchar(255) DEFAULT '0';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_yueke_worker_withdrawlog` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `uid` int(11) DEFAULT NULL,
      `money` decimal(11,2) DEFAULT NULL,
      `txmoney` decimal(11,2) DEFAULT NULL,
      `weixin` varchar(255) DEFAULT NULL,
      `aliaccount` varchar(255) DEFAULT NULL,
      `ordernum` varchar(255) DEFAULT NULL,
      `paytype` varchar(255) DEFAULT NULL,
      `status` tinyint(1) DEFAULT '0',
      `createtime` int(11) DEFAULT NULL,
      `bankname` varchar(255) DEFAULT NULL,
      `bankcarduser` varchar(255) DEFAULT NULL,
      `bankcardnum` varchar(255) DEFAULT NULL,
      `paytime` int(11) DEFAULT NULL,
      `paynum` varchar(255) DEFAULT NULL,
      `platform` varchar(255) DEFAULT 'wx',
      `reason` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `uid` (`uid`) USING BTREE,
      KEY `createtime` (`createtime`) USING BTREE,
      KEY `status` (`status`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('level_auto_up')){
    if(!pdo_fieldexists2('ddwx_member_level','up_level_days')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `up_level_days`  int(11) NULL DEFAULT 0 COMMENT '降级后自动升级考核天数';");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_level_teamyeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `up_level_teamyeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '降级后自动升级考核业绩';");
    }
}
if(getcustom('product_quanyi')){
    if(!pdo_fieldexists2('ddwx_shop_order','hexiao_num_total')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `hexiao_num_total` int(11) NULL DEFAULT 0 COMMENT '权益商品核销次数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order','hexiao_num_used')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `hexiao_num_used` int(11) NULL DEFAULT 0 COMMENT '权益商品已核销次数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','hexiao_num_total')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `hexiao_num_total` int(11) NULL DEFAULT 0 COMMENT '权益商品核销次数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','hexiao_num_used')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `hexiao_num_used` int(11) NULL DEFAULT 0 COMMENT '权益商品已核销次数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','product_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `product_type` int(11) NULL DEFAULT 0 COMMENT '产品类型';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","product_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `product_type` tinyint(1) DEFAULT '0';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','product_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `product_type` int(11) NULL DEFAULT 0 COMMENT '产品类型';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','hexiao_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `hexiao_num` int(11) NULL DEFAULT 0 COMMENT '权益商品核销次数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','quanyi_hexiao_circle')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `quanyi_hexiao_circle` tinyint(1) NULL DEFAULT 0 COMMENT '核销周期 0每月 1每周 2每天' ;");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','circle_hexiao_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `circle_hexiao_num` int(11) NULL DEFAULT 0 COMMENT '每周期内可以核销的次数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','quanyi_hexiao_return')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `quanyi_hexiao_return` tinyint(1) NULL DEFAULT 0 COMMENT '核销完成是否返还本金 0否 1是';");
    }
    if(!pdo_fieldexists2('ddwx_hexiao_shopproduct','hexiao_order_id')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_hexiao_shopproduct` ADD COLUMN `hexiao_order_id` int(11) NULL DEFAULT 0 COMMENT '对应核销订单表hexiao_order中的id';");
    }

}
if(getcustom('yx_team_yeji_weight')) {
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_team_yeji_weight_log` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NOT NULL ,
        `mid`  int(11) NOT NULL ,
        `levelid`  int(11) NOT NULL ,
        `commission`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '获得佣金数量' ,
        `money`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '获得余额数量' ,
        `score`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '获得积分数量' ,
        `jiesuan_type`  tinyint(1) NULL DEFAULT 1 COMMENT '结算方式 1 按月 2按季度 3按年' ,
        `yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '会员当期总业绩' ,
        `order_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '会员当期订单业绩' ,
        `last_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '会员上期转入的业绩' ,
        `weight_num`  int(12) NULL DEFAULT 0 COMMENT '权重数量' ,
        `plate_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '平台总业绩' ,
        `createtime`  int(10) NULL DEFAULT 0 ,
        `is_fenhong`  tinyint(1) NULL DEFAULT 0 COMMENT '是否发放分红' ,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_team_yeji_weight_set` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `jiesuan_type`  tinyint(1) NULL DEFAULT 1 COMMENT '结算方式 1按月 2按季度 3按年' ,
        `status`  tinyint(1) NULL DEFAULT 1 ,
        `bili`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '分红比例' ,
        `yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '权重业绩数量' ,
        `yeji_next`  tinyint(1) NULL DEFAULT 0 COMMENT '业绩累计到下一期 0否 1是' ,
        `fwtype`  tinyint(1) NULL DEFAULT 0 COMMENT '适用范围 0所有商品 1指定类目 2指定商品' ,
        `categoryids`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
        `productids`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
        `gettj`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '参与级别' ,
        `createtime`  int(10) NULL DEFAULT 0 COMMENT '添加时间' ,
        `wallet_type`  tinyint(1) NULL DEFAULT 0 COMMENT '发放钱包 0佣金 1余额 2积分' ,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic;");
    if(!pdo_fieldexists2('ddwx_member','last_weight_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `last_weight_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '上期累计权重业绩';");
    }
}
if(getcustom('money_commission_withdraw_fenxiao')){
    if(!pdo_fieldexists2('ddwx_admin_set','money_withdrawfee_fenxiao')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `money_withdrawfee_fenxiao` tinyint(1) DEFAULT '-1' COMMENT '余额提现手续费分销0按会员等级 1单独设置 -1不参与',
            ADD COLUMN `commission_withdrawfee_fenxiao` tinyint(1) DEFAULT '-1' COMMENT '佣金提现手续费分销 0按会员等级 1单独设置 -1不参与';");
    }
    if(!pdo_fieldexists2('ddwx_member_withdrawlog','parent1')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_withdrawlog` 
            ADD COLUMN `parent1` int(11) DEFAULT NULL,
            ADD COLUMN `parent2` int(11) DEFAULT NULL,
            ADD COLUMN `parent3` int(11) DEFAULT NULL,
            ADD COLUMN `parent4` varchar(255) DEFAULT NULL,
            ADD COLUMN `parent1commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `parent2commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `parent3commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `parent4commission` decimal(11,2) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2('ddwx_member_commission_withdrawlog','parent1')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` 
            ADD COLUMN `parent1` int(11) DEFAULT NULL,
            ADD COLUMN `parent2` int(11) DEFAULT NULL,
            ADD COLUMN `parent3` int(11) DEFAULT NULL,
            ADD COLUMN `parent4` varchar(255) DEFAULT NULL,
            ADD COLUMN `parent1commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `parent2commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `parent3commission` decimal(11,2) DEFAULT '0.00',
            ADD COLUMN `parent4commission` decimal(11,2) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','money_withdrawfee_commissiondata')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `money_withdrawfee_commissiondata` text CHARACTER SET utf8,
            ADD COLUMN `commission_withdrawfee_commissiondata` text CHARACTER SET utf8; ") ;
    }
}
if(getcustom('alipay_fenzhang')){
    if(!pdo_fieldexists2('ddwx_business_sysset','alifw_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
            ADD COLUMN `alifw_status` tinyint(1) DEFAULT '0' COMMENT '支付宝分账',
            ADD COLUMN `alifw_userId` varchar(30) NOT NULL DEFAULT '' COMMENT '支付宝分账接收id';");
    }
    if(!pdo_fieldexists2('ddwx_business','alipayappid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `alipayst` tinyint(1) DEFAULT '0',
            ADD COLUMN `alipayappid`  varchar(100) NOT NULL DEFAULT '' COMMENT '支付宝分账';");
    }
    if(!pdo_fieldexists2('ddwx_business_sysset','alifw_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
			ADD COLUMN `alifw_status` tinyint(1) DEFAULT '0' COMMENT '支付宝分账',
			ADD COLUMN `alifw_userId` varchar(30) NOT NULL DEFAULT '' COMMENT '支付宝分账';");
    }
    if(!pdo_fieldexists2('ddwx_business','alipay_app_auth_token')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `alipay_app_auth_token` varchar(100) DEFAULT NULL COMMENT '支付宝';");
    }
}
if(getcustom('restaurant_team_yeji')){
    if(!pdo_fieldexists2('ddwx_admin_set','restaurant_team_yeji_open')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `restaurant_team_yeji_open` tinyint(1) DEFAULT '0' COMMENT '餐饮订单计入团队业绩 0:关闭 1：开启';");
    }
}
if(getcustom('teamfenhong_pingji_num')){
    if(!pdo_fieldexists2('ddwx_member_level','teamfenhong_pingji_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `teamfenhong_pingji_num` int(11) DEFAULT '0' COMMENT '团队分红平级奖发奖个数';");
    }
    if(!pdo_fieldexists2('ddwx_member_level','teamfenhong_pingji_num_levelids')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `teamfenhong_pingji_num_levelids` varchar(50) DEFAULT NULL COMMENT '平级奖个数合并级别';");
    }
}
if(getcustom('restaurant_fenhong')){
    if(!pdo_fieldexists2('ddwx_admin_set','restaurant_fenhong_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `restaurant_fenhong_status` tinyint(1) DEFAULT '1' COMMENT '餐饮版块儿分红开关';");
    }
    if(pdo_fieldexists3('ddwx_member_fenhong_record')){
        \think\facade\Db::execute("update ddwx_member_fenhong_record set `module`= 'restaurant_takeaway' where `module`='takeaway';");
        \think\facade\Db::execute("update ddwx_member_fenhong_record set `module`= 'restaurant_shop' where `module`='diancan';");
    }
}
if(getcustom('yx_cashback_jiange_day')){
    if(!pdo_fieldexists2('ddwx_cashback','jiange_day')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `jiange_day` int(10) DEFAULT '0' COMMENT '返现间隔天数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','jiange_day')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `jiange_day` int(10) DEFAULT '0' COMMENT '返现间隔天数';");
    }
}
if(getcustom('yx_cashback_time')){
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','return_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `return_type` tinyint(1) NULL  COMMENT '返还类型  2、阶梯返还';");
    }
}

if(getcustom('team_minyeji_count')){
    if(!pdo_fieldexists2('ddwx_member','team_minyeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `team_minyeji` decimal(12,2) DEFAULT '0.00' COMMENT '团队小市场业绩';");
    }
}

if(getcustom('freight_full_piece_package')){
    if(!pdo_fieldexists2('ddwx_freight','full_piece')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight` 
        ADD COLUMN `full_piece` int(11) DEFAULT '0' COMMENT '满多少件包邮',
        ADD COLUMN `fullpieceset` tinyint(1) DEFAULT '0' COMMENT '是否满件包邮 0否 1是';");
    }
}

if(getcustom('product_pingce')){
    if(!pdo_fieldexists2("ddwx_shop_order",'pingce')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_order ADD COLUMN `pingce` text NULL COMMENT '评测商品所需参数';");  
    }
    if(!pdo_fieldexists2("ddwx_shop_order",'is_pingce')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_order ADD COLUMN `is_pingce` tinyint(1) DEFAULT 0 COMMENT '是否评测订单1是';");  
    }
    if(!pdo_fieldexists2("ddwx_shop_order",'pingce_return')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_order ADD COLUMN `pingce_return` text NULL COMMENT '评测系统返回参数';");  
    }
    if(!pdo_fieldexists2("ddwx_shop_order",'pingce_status')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_order ADD COLUMN `pingce_status` int(1) DEFAULT '0' COMMENT '评测状态0注册完1开始未完成2已完成';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order",'pingce_result_urls')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_order ADD COLUMN `pingce_result_urls` varchar(200) DEFAULT '' COMMENT '评测商品所需参数';");  
    }
    if(!pdo_fieldexists2("ddwx_shop_sysset",'pingceinviteCode')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_sysset ADD COLUMN `pingceinviteCode` varchar(60) DEFAULT '' COMMENT '评测邀请码';");  
    }
    if(!pdo_fieldexists2("ddwx_shop_sysset",'pingce_url')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_sysset ADD COLUMN `pingce_url` varchar(60) DEFAULT '' COMMENT '测评接口域名';");
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_sysset ADD COLUMN `pingce_test_url` varchar(60) DEFAULT '' COMMENT '测评问答地址';");
        
    }
    if(!pdo_fieldexists2("ddwx_shop_product",'pingce_report')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_product ADD COLUMN `pingce_report` varchar(20) DEFAULT '' COMMENT '评测报告类型：1 32种人才心理特质报告， 2 42种职场岗位适配报告';");  
    }
    
}
if(getcustom('yx_queue_free_parent_other_mode')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','parent_mode3_pj')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN `parent_mode3_pj` decimal(12,2) DEFAULT '0.00' COMMENT '平均分配比例',
            ADD COLUMN `parent_mode3_money` decimal(12,2) DEFAULT '0.00' COMMENT '金额分配比例',
            ADD COLUMN `parent_mode4_pj` decimal(12,2) DEFAULT '0.00' COMMENT '平均分配比例',
            ADD COLUMN `parent_mode4_fixed` decimal(12,2) DEFAULT '0.00' COMMENT '固定分配比例';");
    }
}
if(getcustom('commission_two_level')){
    if(!pdo_fieldexists2("ddwx_shop_product",'two_level_commission')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_product ADD COLUMN `two_level_commission` tinyint(1) DEFAULT '0' COMMENT '二级分销 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product",'two_level_commissiondata')){
        \think\facade\Db::execute("ALTER TABLE ddwx_shop_product ADD COLUMN `two_level_commissiondata` text COMMENT '二级分销奖金数据';");
    }
}
if(getcustom('restaurnt_table_afterpay_clean')){
    if(!pdo_fieldexists2('ddwx_restaurant_shop_sysset','afterpay_clean')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` ADD COLUMN `afterpay_clean`  tinyint(1) DEFAULT '0' COMMENT '餐后付款清台';");
    }
}
if(getcustom('restaurnt_table_afterpay_print')){
    if(!pdo_fieldexists2('ddwx_restaurant_shop_sysset','afterpay_print')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` ADD COLUMN `afterpay_print`  tinyint(1) DEFAULT '0' COMMENT '餐后付款打印';");
    }
}
if(getcustom('yx_queue_free_min_order_money')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','min_order_money')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `min_order_money` decimal(10,2) DEFAULT '0.00' COMMENT '订单参与最小金额';");
    }
}
if(getcustom('yx_queue_free_max_business_yeji')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','max_business_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` ADD COLUMN `max_business_yeji` decimal(10,2) DEFAULT '0.00' COMMENT '参与排队最大业绩';");
    }
}
if(getcustom('business_maidan_commission')){
    if(!pdo_fieldexists2("ddwx_business",'maidan_commissionset')){
        \think\facade\Db::execute("ALTER TABLE ddwx_business 
        ADD COLUMN `maidan_commissionset` tinyint(1) DEFAULT '0' COMMENT '买单分销 0关闭 1单独设置比例',
		ADD COLUMN `maidan_commissiondata1` text CHARACTER SET utf8;");
    }
}

if(getcustom('business_buy_bind_show_page')){
    if(!pdo_fieldexists2("ddwx_member",'firstbuy_business')){
        \think\facade\Db::execute("ALTER TABLE ddwx_member  ADD COLUMN `firstbuy_business` int(1) DEFAULT '0' COMMENT '首次购买店铺';");
    }
}

if(getcustom('level_business_apply')){
    if(!pdo_fieldexists2("ddwx_member_level",'business_apply')){
        \think\facade\Db::execute("ALTER TABLE ddwx_member_level  ADD COLUMN `business_apply` tinyint(1) DEFAULT '1' COMMENT '商户入驻 0:关闭 1:开启';");
    }
}

if(getcustom('yuyue_worker_sametime_yynum')){
    if(!pdo_fieldexists2('ddwx_yuyue_set','worker_sametime_yynum')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_set` ADD COLUMN `worker_sametime_yynum` int NOT NULL DEFAULT 1 COMMENT '服务人员同一时间接单次数';");
    }
}
if(getcustom('restaurant_shop_pindan')){
    if(!pdo_fieldexists2('ddwx_restaurant_shop_cart','tableid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_cart` ADD COLUMN  `tableid` int(11) DEFAULT '0' COMMENT '桌台id';");
    }
    if(!pdo_fieldexists2('ddwx_restaurant_shop_order_goods','times')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` ADD COLUMN  `times` int(11) DEFAULT '0' COMMENT '第几次下单';");
    }
}
if(getcustom('team_fenhong_yeji')){
    if(!pdo_fieldexists2('ddwx_shop_order','is_bonus')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `is_bonus`  tinyint(1) NULL DEFAULT 0 COMMENT '是否参与奖金 0否 1是';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','is_bonus')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `is_bonus`  tinyint(1) NULL DEFAULT 0 COMMENT '是否参与奖金 0否 1是';");
    }
}
if(getcustom('toupiao_apply_form')){
    if(!pdo_fieldexists2('ddwx_toupiao','formdata')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_toupiao`  ADD `formdata` text NULL COMMENT '自定义表单';");
    }

    if(!pdo_fieldexists2('ddwx_toupiao_join','form0')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_toupiao_join` 
            ADD `form0` varchar(255) DEFAULT NULL,
            ADD `form1` varchar(255) DEFAULT NULL,
            ADD `form2` varchar(255) DEFAULT NULL,
            ADD `form3` varchar(255) DEFAULT NULL,
            ADD `form4` varchar(255) DEFAULT NULL,
            ADD `form5` varchar(255) DEFAULT NULL,
            ADD `form6` varchar(255) DEFAULT NULL,
            ADD `form7` varchar(255) DEFAULT NULL,
            ADD `form8` varchar(255) DEFAULT NULL,
            ADD `form9` varchar(255) DEFAULT NULL,
            ADD `form10` varchar(255) DEFAULT NULL;");
    }
    if(!pdo_fieldexists2('ddwx_toupiao_join','form11')){

        \think\facade\Db::execute("ALTER TABLE `ddwx_toupiao_join` 
            MODIFY `form0` text DEFAULT NULL,
            MODIFY `form1` text DEFAULT NULL,
            MODIFY `form2` text DEFAULT NULL,
            MODIFY `form3` text DEFAULT NULL,
            MODIFY `form4` text DEFAULT NULL,
            MODIFY `form5` text DEFAULT NULL,
            MODIFY `form6` text DEFAULT NULL,
            MODIFY `form7` text DEFAULT NULL,
            MODIFY `form8` text DEFAULT NULL,
            MODIFY `form9` text DEFAULT NULL,
            MODIFY `form10` text DEFAULT NULL;");

        \think\facade\Db::execute("ALTER TABLE `ddwx_toupiao_join` 
            ADD `form11` text DEFAULT NULL,
            ADD `form12` text DEFAULT NULL,
            ADD `form13` text DEFAULT NULL,
            ADD `form14` text DEFAULT NULL,
            ADD `form15` text DEFAULT NULL,
            ADD `form16` text DEFAULT NULL,
            ADD `form17` text DEFAULT NULL,
            ADD `form18` text DEFAULT NULL,
            ADD `form19` text DEFAULT NULL,
            ADD `form20` text DEFAULT NULL;");
    }
}
if(getcustom('extend_certificate_query')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_certificate_query_record` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NULL DEFAULT NULL ,
    `realname`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
    `sex`  tinyint(2) NULL DEFAULT 0 COMMENT '性别' ,
    `id_card`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '身份证号' ,
    `certificate_no`  varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '证书编号' ,
    `type`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '工种' ,
    `certificate_level`  varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '证书级别' ,
    `start_time`  varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '发证日期' ,
    `createtime`  int(11) NULL DEFAULT NULL ,
    PRIMARY KEY (`id`),
    INDEX `aid` (`aid`) USING BTREE 
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic;");
}
if(getcustom('form_login')){
    if(!pdo_fieldexists2('ddwx_form','need_login')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_form` ADD COLUMN `need_login` tinyint(1) DEFAULT '1' COMMENT '强制登录 0否 1是';");
    }
}
if(getcustom('login_setnickname_checklogin')){
    if(!pdo_fieldexists2('ddwx_admin_set','login_setnickname_checklogin')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `login_setnickname_checklogin` tinyint(1) DEFAULT '0' COMMENT '登录内页设置头像昵称1设置0不设置';");
    }
}

if(getcustom('extend_qrcode_variable')){
    if(!pdo_fieldexists2('ddwx_qrcode_variable','logo')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_variable` ADD COLUMN `logo` varchar(255)   COMMENT '二维码中间小图,网图';");
    }
    if(!pdo_fieldexists2('ddwx_qrcode_variable','bg_image')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_variable` ADD COLUMN `bg_image` varchar(255)   COMMENT '二维码背景图,网图';");
    }
    if(!pdo_fieldexists2('ddwx_qrcode_variable','local_logo')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_variable` ADD COLUMN `local_logo` varchar(255)   COMMENT '本地logo小图';");
    }
    if(!pdo_fieldexists2('ddwx_qrcode_variable','local_bg_image')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_variable` ADD COLUMN `local_bg_image` varchar(255)   COMMENT '本地背景图';");
    }
}
if(getcustom('score_product_membergive')){
    if(!pdo_fieldexists2('ddwx_scoreshop_order_goods','membergive_member_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order_goods` ADD COLUMN `membergive_member_status` tinyint(1) DEFAULT '0'  COMMENT '发放状态1已发放';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_order_goods','membergive_commission')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order_goods` ADD COLUMN `membergive_commission` decimal(12,2) NOT NULL DEFAULT '0.00'  COMMENT '赠送佣金';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_order_goods','membergive_score')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order_goods` ADD COLUMN `membergive_score` int(11) NOT NULL DEFAULT '0'  COMMENT '赠送积分';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_order_goods','membergive_money')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order_goods` ADD COLUMN `membergive_money` decimal(12,2) NOT NULL DEFAULT '0.00'  COMMENT '赠送余额';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_order_goods','membergive_member_id')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order_goods` ADD COLUMN `membergive_member_id` int(11) DEFAULT '0' COMMENT '赠送会员的id';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_product','membergive_commission')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` ADD COLUMN `membergive_commission` decimal(12,2) NOT NULL DEFAULT '0.00'  COMMENT '赠送佣金';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_product','membergive_score')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` ADD COLUMN `membergive_score` int(11) NOT NULL DEFAULT '0'  COMMENT '赠送积分';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_product','membergive_money')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` ADD COLUMN `membergive_money` decimal(12,2) NOT NULL DEFAULT '0.00'  COMMENT '赠送余额';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_product','membergive_member_id')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` ADD COLUMN `membergive_member_id` int(11) DEFAULT '0' COMMENT '赠送会员的id';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_sysset','membergive_fafangtime')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_sysset` ADD COLUMN `membergive_fafangtime` tinyint(1) DEFAULT '1' COMMENT '发放时间1确认收货后2付款后';");
    }
    if(!pdo_fieldexists2('ddwx_scoreshop_sysset','membergive_isfafang')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_sysset` ADD COLUMN `membergive_isfafang` tinyint(1) DEFAULT '1' COMMENT '积分商品收益是否发放1发放';");
    }   
}

if(getcustom('yx_team_yeji_tongji')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_team_yeji_tongji_set` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `aid` int(11) DEFAULT NULL,
          `levelids` varchar(255) DEFAULT NULL COMMENT '团队业绩统计等级范围',
          `yeji_name` varchar(60) NOT NULL DEFAULT '业绩' COMMENT '业绩显示名称',
          `include_self` tinyint(1) DEFAULT '0' COMMENT '团队业绩包含自己',
          `slje` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1显示数量  2显示金额  3数量+金额',
          `status` tinyint(1) DEFAULT '1',
          `createtime` int(11) DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='团队业绩统计设置';");
}

if(getcustom('form_sign_pdf')){
    if(!pdo_fieldexists2('ddwx_form','is_contract')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_form` 
        ADD COLUMN `is_contract` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是合同  0否 1是',
        ADD COLUMN `is_contract_sign` tinyint(1) NOT NULL DEFAULT '0' COMMENT '合同是否签名  0否 1是',
        ADD COLUMN `contract_template` varchar(255) NOT NULL DEFAULT '' COMMENT '合同模板地址';
    ");
    }

    if(!pdo_fieldexists2('ddwx_form_order','signatureurl')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_form_order` 
        ADD COLUMN `signatureurl` varchar(255) NOT NULL DEFAULT '' COMMENT '签名图片',
        ADD COLUMN `contract_file` varchar(255) NOT NULL DEFAULT '' COMMENT '合同文件';
    ");
    }
}
if(getcustom('restaurant_book_order')){
    if(!pdo_fieldexists2('ddwx_restaurant_shop_order','isbook')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `isbook` tinyint(1) DEFAULT 0 COMMENT '预定订单';");
    }
    if(!pdo_fieldexists2('ddwx_restaurant_shop_order','bookid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` ADD COLUMN `bookid` int(10) DEFAULT 0 COMMENT '预定id';");
    }
}
if(getcustom('yx_hbtk_muisc')){
    if(!pdo_fieldexists2('ddwx_hbtk_activity','musicurl')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_hbtk_activity` ADD COLUMN `musicurl`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '背景音乐链接';");
    }
}
if(getcustom('commission_parent_pj')){
    if(!pdo_fieldexists2('ddwx_admin_set','commission_parent_pj_no_limit')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `commission_parent_pj_no_limit` tinyint(1) DEFAULT '0' COMMENT '分销平级奖限制 0受上级佣金限制 1不限制';");
    }
}
if(getcustom('yx_queue_free_new')) {
    if (!pdo_fieldexists2('ddwx_queue_free_set', 'mode0_new_order_ratio')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
        ADD COLUMN `mode0_new_order_ratio` decimal(5,2) NOT NULL DEFAULT '0' COMMENT '固定分配：最新下单比例';
    ");
    }
}
if(getcustom('yx_team_yeji_tongji')){
    if(!pdo_fieldexists2('ddwx_team_yeji_tongji_set','tonfjiprogive')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_team_yeji_tongji_set` 
        ADD COLUMN `tonfjiprogive` tinyint(1) NOT NULL DEFAULT '0' COMMENT '业绩统计商品  0关闭  1开启',
        ADD COLUMN `tongjiproids` text NOT NULL COMMENT '业绩统计商品的商品id';
        ");
    }
}
if(getcustom('openapi_update_score')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_rainbow_set`  (
      `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `rainbow_ip_limit` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `rainbow_private_key` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
      `createtime` int(11) NULL DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");
}
if(getcustom('commission_percent_to_parent')){
    if(!pdo_fieldexists2('ddwx_member_level','commission_percent_to_parent_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `commission_percent_to_parent_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '上级佣金分账状态 0关闭  1开启',
        ADD COLUMN `commission_percent_to_parent` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '佣金分账百分比%',
        ADD COLUMN `commission_percent_to_parent_type` varchar(50) NOT NULL DEFAULT '' COMMENT '可分佣金类型 1分销  2分红   空都不可分',
        ADD COLUMN `commission_percent_to_parent_divide_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分佣方式 0额外分  1从上级扣',
        ADD COLUMN `commission_percent_to_parent_condition` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分佣条件 0脱离过  1不脱离',
        ADD COLUMN `commission_percent_to_parent_target` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分佣目标 0原上级  1现上级';
        ");
    }
}
if(getcustom('yuyue_sms')){
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_yysucess_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_yysucess_txy` varchar(64) DEFAULT NULL;");
    }
}
if(getcustom('sms_temp_money_recharge')){
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_money_recharge_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_money_recharge_txy` varchar(64) DEFAULT NULL;");
    }
}
if(getcustom('sms_temp_money_use')){
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_money_use_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_money_use_txy` varchar(64) DEFAULT NULL;");
    }
}
if(getcustom('zhaopin')){
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_checkerror_txy")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` ADD COLUMN `tmpl_checkerror_txy` varchar(64) DEFAULT NULL;");
    }
}

if(getcustom('member_recharge_minimum')){
    if(!pdo_fieldexists2("ddwx_admin_set","recharge_minimum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `recharge_minimum` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '余额充值门槛，最低充值额度';");
    }
}
if(getcustom('member_level_moneypay_price')){
    if(!pdo_fieldexists2("ddwx_admin_set","moneypay_lvprice_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `moneypay_lvprice_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '会员价仅限余额支付 0：关闭 1：开启';");
    }
    if(!pdo_fieldexists2("ddwx_payorder","moneyprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` 
            ADD COLUMN `moneyprice` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '会员购买总价',
            ADD COLUMN `putongprice` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '所有商品普通价格总价',
            ADD COLUMN `moneypaytypeid` int NULL DEFAULT 0 COMMENT '支付类型（会员价仅余额用）',
            ADD COLUMN `usemoneypay` tinyint(1) NULL DEFAULT -1 COMMENT '是否使用会员价仅余额支付 -1 之前支付默认 0：未使用 1：使用';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","product_putongprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `product_putongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '商品普通价格',
            ADD COLUMN `totalputongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通价格总价';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods","sell_putongprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD COLUMN `sell_putongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通支付单价' ,
            ADD COLUMN `totalputongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通支付总价' ;");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order","product_putongprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` 
            ADD COLUMN `product_putongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '商品普通价格',
            ADD COLUMN `totalputongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通价格总价';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_shop_order_goods","sell_putongprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` 
            ADD COLUMN `sell_putongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通支付单价'  ,
            ADD COLUMN `totalputongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通支付总价' ;");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_takeaway_order","product_putongprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_takeaway_order` 
            ADD COLUMN `product_putongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '商品普通价格',
            ADD COLUMN `totalputongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通价格总价';");
    }
    if(!pdo_fieldexists2("ddwx_restaurant_takeaway_order_goods","sell_putongprice")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_takeaway_order_goods` 
            ADD COLUMN `sell_putongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通支付单价'  ,
            ADD COLUMN `totalputongprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '普通支付总价' ;");
    }
}
if(getcustom('member_level_price_show')){
    if(!pdo_fieldexists2("ddwx_member_level","price_show")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
            ADD COLUMN `price_show` tinyint(1) NULL DEFAULT 0 COMMENT '列表、详情页是否显示此等级会员价 0：关闭 1：开启',
            ADD COLUMN `price_show_text` varchar(50) NULL DEFAULT '' COMMENT '此等级会员价格小字';");
    }
}
if(getcustom('scoreshop_lvprice_show')){
    if(!pdo_fieldexists2("ddwx_scoreshop_sysset","lvprice_show")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_sysset` ADD COLUMN `lvprice_show` tinyint(1) NULL DEFAULT 0 COMMENT '详情页展示等级价格 0否 1是';");
    }
}
if(getcustom('commission_parent_bcy_send_once')){
    if(!pdo_fieldexists2('ddwx_member_level','commission_parent_bcy')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `commission_parent_bcy` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '被超越奖';
        ");
    }
}
if(getcustom('yx_mangfan_maidan')){
    if(!pdo_fieldexists2('ddwx_mangfan_set','maidan_range')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mangfan_set` 
        ADD COLUMN `maidan_status` tinyint(1) DEFAULT '0' COMMENT '买单盲返',
        ADD COLUMN `maidan_commission_type` tinyint(1) DEFAULT '1' COMMENT '买单盲返类型',
        ADD COLUMN `maidan_range` text NOT NULL COMMENT '买单金额范围';");
    }
    if(!pdo_fieldexists2('ddwx_maidan_order','mangfan_commission_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` 
        ADD COLUMN `mangfan_rate` decimal(10,2) DEFAULT '0.00' COMMENT '盲返佣金比例',
        ADD COLUMN `mangfan_commission_type` tinyint(1) DEFAULT '0' COMMENT '盲返佣金类型',
        ADD COLUMN `is_mangfan` tinyint(1) DEFAULT '0' COMMENT '是否是盲返订单 0 否 1 是';");
    }
}

if(getcustom('yuyue_paidan_classify')){
    if(!pdo_fieldexists2('ddwx_yuyue_set','paidan_classify')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_set` 
        ADD COLUMN `paidan_classify` tinyint(1) DEFAULT 0 COMMENT '派单分类范围 0全部 1按分类';");
    }
}
if(getcustom('member_import_dyzx')){
    if(!pdo_fieldexists2('ddwx_member','import_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `import_yeji` decimal (12,2) NULL DEFAULT '0' COMMENT '导入业绩';");
    }
}
if(getcustom('team_view_down_to_down')){
    if(!pdo_fieldexists2('ddwx_member_level','team_view_down_to_down')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `team_view_down_to_down` tinyint(1) NOT NULL DEFAULT '0' COMMENT '查看团队人员的下级 0关闭 1开启';
        ");
    }
}
if(getcustom('mendian_hexiao_coupon_reward')){
    if(!pdo_fieldexists2('ddwx_coupon','hexiaogivepercent')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` 
        ADD COLUMN `hexiaogivepercent` decimal(10,2) DEFAULT '0.00' COMMENT '核销提成比例',
        ADD COLUMN `hexiaogivemoney` decimal(10,2) DEFAULT '0.00' COMMENT '核销提成金额';");
    }
}
if(getcustom('commission_parent_bcy_send_once')){
    if(!pdo_fieldexists2('ddwx_shop_product','commissionbcyset')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        ADD COLUMN `commissionbcyset` int(2) NOT NULL DEFAULT '0' COMMENT '被超越奖 0按会员等级  1单独设置比例  2单独设置奖励金额  -1不参与被超越奖',
        ADD COLUMN `commissionbcydata1` text COMMENT '被超越奖按比例参数',
        ADD COLUMN `commissionbcydata2` text COMMENT '被超越奖按金额参数';
        ");
    }
}
if(getcustom('commission_parent_bcy_send_once')){
    if(!pdo_fieldexists2('ddwx_admin_set','commission_parent_bcy_send_once')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `commission_parent_bcy_send_once` tinyint(1) NOT NULL DEFAULT '0' COMMENT '被超越奖 0关闭  1开启';
        ");
    }
}
if(getcustom('commission_parent_pj_send_once')){
    if(!pdo_fieldexists2('ddwx_admin_set','commission_parent_pj_send_once')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `commission_parent_pj_send_once` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分销平级奖只给最近的上级发一次 0关闭 1开启';
        ");
    }
}
if(getcustom('team_view_zhitui_member_num')){
    if(!pdo_fieldexists2('ddwx_member_level','team_view_zhitui_member_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `team_view_zhitui_member_num` tinyint(1) NOT NULL DEFAULT '0' COMMENT '查看一级直推人数 0关闭 1开启';
        ");
    }
}
if(getcustom('commission_gangwei')){
    if(!pdo_fieldexists2('ddwx_member_level','gangwei1')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `gangwei1` decimal (12,2) NULL DEFAULT '0' COMMENT '岗位提成1',
        ADD COLUMN `gangwei2` decimal (12,2) NULL DEFAULT '0' COMMENT '岗位提成2';
        ");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','gangwei_jinsuo_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `gangwei_jinsuo_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '岗位提成紧缩 0关闭1开启',
        ADD COLUMN `gangwei_tndn_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '岗位提成推N得N 0关闭1开启',
        ADD COLUMN `gangwei_give_origin_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '岗位提成发给原上级 0关闭1开启';
        ");
    }
}

if(getcustom('yx_shop_order_team_yeji_bonus')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_bonus`  (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT NULL,
        `bid` int(11) NULL DEFAULT 0,
        `name` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '名称',
        `back_ratio` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '返还比率',
        `mids` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
        `starttime` int(11) NULL DEFAULT NULL,
        `endtime` int(11) NULL DEFAULT NULL,
        `status` int(1) NULL DEFAULT 1,
        `createtime` int(11) NULL DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `bid`(`bid`) USING BTREE,
        INDEX `status`(`status`) USING BTREE,
        INDEX `starttime`(`starttime`) USING BTREE,
        INDEX `endtime`(`endtime`) USING BTREE
      ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '购物返现' ROW_FORMAT = Dynamic;");
  \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_bonus_log`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `mid` int(11) NOT NULL,
      `active_id` int(11) NOT NULL,
      `frommid` int(11) NULL DEFAULT NULL,
      `orderid` int(11) NULL DEFAULT NULL,
      `totalprice` decimal(10, 2) NULL DEFAULT 0.00,
      `commission` decimal(10, 2) NULL DEFAULT 0.00,
      `createtime` int(11) NULL DEFAULT NULL,
      `status` tinyint(1) NULL DEFAULT 0 COMMENT '0未发放1已发放2已退回',
      `refund_commission` decimal(10, 2) NULL DEFAULT 0.00,
      `endtime` int(11) NULL DEFAULT NULL,
      `bili` decimal(10, 2) NULL DEFAULT 0.00,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");
}
if(getcustom('yx_yeji_fenhong')){
      \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_yeji_fenhong`  (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `bid` int(11) NULL DEFAULT 0,
      `name` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '名称',
      `back_ratio` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '返还比率',
      `mids` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
      `starttime` int(11) NULL DEFAULT NULL,
      `endtime` int(11) NULL DEFAULT NULL,
      `status` int(1) NULL DEFAULT 1,
      `createtime` int(11) NULL DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      INDEX `aid`(`aid`) USING BTREE,
      INDEX `bid`(`bid`) USING BTREE,
      INDEX `status`(`status`) USING BTREE,
      INDEX `starttime`(`starttime`) USING BTREE,
      INDEX `endtime`(`endtime`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '购物返现' ROW_FORMAT = Dynamic;");
  \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_yeji_fenhong_log`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `mid` int(11) NOT NULL,
      `active_id` int(11) NOT NULL,
      `frommid` int(11) NULL DEFAULT NULL,
      `orderid` int(11) NULL DEFAULT NULL,
      `totalprice` decimal(10, 2) NULL DEFAULT 0.00,
      `commission` decimal(10, 2) NULL DEFAULT 0.00,
      `createtime` int(11) NULL DEFAULT NULL,
      `status` tinyint(1) NULL DEFAULT 0 COMMENT '0未发放1已发放2已退回',
      `refund_commission` decimal(10, 2) NULL DEFAULT 0.00,
      `endtime` int(11) NULL DEFAULT NULL,
      `bili` decimal(10, 2) NULL DEFAULT 0.00,
      `commission_total` decimal(10, 2) NULL DEFAULT 0.00,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");
}
if(getcustom('yx_score_freeze')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_score_freeze_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT NULL,
      `status` tinyint(1) DEFAULT '0',
      `releasedata` text COMMENT '释放设置',
      `min_release_score` decimal(12,3) DEFAULT '0.000',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_score_freeze_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT NULL,
      `mid` varchar(100) DEFAULT NULL,
      `score` decimal(12,3) DEFAULT '0.000',
      `after` decimal(12,3) DEFAULT NULL,
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
      `channel` varchar(20) DEFAULT '' COMMENT '变动渠道',
      `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0默认，',
      `is_cancel` tinyint(1) DEFAULT '0',
      `uid` int(11) DEFAULT '0',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `status` (`status`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='积分冻结记录';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_score_freeze_release_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT NULL,
      `mid` varchar(100) DEFAULT NULL,
      `before` decimal(12,3) DEFAULT '0.000' COMMENT '总积分',
      `ratio` decimal(11,2) DEFAULT '0.00' COMMENT '比例',
      `score` decimal(12,3) DEFAULT '0.000' COMMENT '兑换积分',
      `after` decimal(12,3) DEFAULT '0.000',
      `createtime` int(11) DEFAULT NULL,
      `remark` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='积分释放记录';");
    if(!pdo_fieldexists2('ddwx_member','score_freeze')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
        ADD COLUMN `score_freeze` decimal(12,3) DEFAULT '0.000' COMMENT '冻结积分';");
    }
}
if(getcustom('score_withdraw')){
    if(!pdo_fieldexists2('ddwx_admin_set','score_to_money_min_money')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `score_to_money_min_money` decimal(11,2) DEFAULT '0.00' COMMENT '积分兑换余额，最低金额';");
    }
}

if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','createtime')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `createtime` int(11) NOT NULL DEFAULT 0 COMMENT '后加的创建时间';");
    }
}

if(getcustom('member_auto_reg')){
    if(!pdo_fieldexists2('ddwx_admin_set','member_auto_reg')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `member_auto_reg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '游客自动注册会员';");
    }
}

if(getcustom('sound')){
    if(!pdo_fieldexists2('ddwx_sound','intomaidan_content')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sound` ADD COLUMN `intomaidan_content` text NULL COMMENT '进入买单页面播报自定义文字';");
    }
    if(!pdo_fieldexists2('ddwx_sound','cancelpay_content')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sound` ADD COLUMN `cancelpay_content` text NULL COMMENT '付款页面取消支付播报自定义文字';");
    }
}
if(getcustom('ad_adset')){
    if(!pdo_fieldexists2('ddwx_scoreshop_sysset','adset_show')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_sysset` 
          ADD COLUMN `adset_show` tinyint(1) NULL DEFAULT 0 COMMENT 'adset全屏广告开关',
          ADD COLUMN `adpid` varchar(50) NULL DEFAULT '' COMMENT '全屏广告adpid',
          ADD COLUMN `config_data` text NULL COMMENT '全屏广告看完奖励';
        ");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_adset_log`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `score` int(11) NULL DEFAULT NULL,
      `createtime` int(11) NULL DEFAULT NULL,
      `mid` int(11) NULL DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");
}
if(getcustom('commission_mendian_hexiao_coupon')){
    if(!pdo_fieldexists2('ddwx_mendian','fenrun')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` ADD COLUMN `fenrun` text NULL COMMENT '分润';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_mendian_coupon_commission_log`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `mid` int(11) NOT NULL,
      `frommid` int(11) NULL DEFAULT NULL,
      `orderid` int(11) NULL DEFAULT NULL,
      `totalcommission` decimal(10, 2) NULL DEFAULT 0.00,
      `commission` decimal(10, 2) NULL DEFAULT 0.00,
      `createtime` int(11) NULL DEFAULT NULL,
      `status` tinyint(1) NULL DEFAULT 0 COMMENT '0未发放1已发放',
      `endtime` int(11) NULL DEFAULT NULL,
      `bili` decimal(10, 2) NULL DEFAULT 0.00,
      `bid` int(11) NULL DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");
}
if(getcustom('levelup_small_market_num_product')){
    if(!pdo_fieldexists2('ddwx_member_level','up_small_market_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_small_market_num` int(11) NOT NULL DEFAULT '0' COMMENT '小市场业绩(件)';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_small_market_num_condition')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_small_market_num_condition` varchar(20) DEFAULT 'and' COMMENT '小市场业绩(件) or或，and且';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_small_market_num_proids')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_small_market_num_proids` varchar(255) DEFAULT NULL COMMENT '小市场业绩(件)指定的商品';
        ");
    }
}
if(getcustom('levelup_small_market_yeji')){
    if(!pdo_fieldexists2('ddwx_member_level','up_small_market_yeji_condition')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_small_market_yeji_condition` varchar(20) DEFAULT 'or' COMMENT '小市场业绩 or或，and且';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_small_market_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_small_market_yeji` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '小市场业绩(元)';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_small_market_yeji_proids')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_small_market_yeji_proids` varchar(255) DEFAULT NULL COMMENT '小市场业绩(元)指定的商品';
        ");
    }
}
if(getcustom('levelup_selfanddown_order_num')){
    if(!pdo_fieldexists2('ddwx_member_level','up_selfanddown_order_num_condition')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_selfanddown_order_num_condition` varchar(20) DEFAULT 'or' COMMENT '自己和下级总下单数量满 or或，and且';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_selfanddown_order_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_selfanddown_order_num` int(11) NOT NULL DEFAULT '0' COMMENT '自己和下级总下单数量满(个)';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_selfanddown_order_num_proids')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_selfanddown_order_num_proids` varchar(255) DEFAULT '' COMMENT '自己和下级总下单数量指定的商品ID';
        ");
    }
}
if(getcustom('levelup_selfanddown_order_product_num')){
    if(!pdo_fieldexists2('ddwx_member_level','up_selfanddown_order_product_num_condition')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_selfanddown_order_product_num_condition` varchar(20) DEFAULT 'or' COMMENT '自己和下级总下单商品数满(件)  or或，and且';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_selfanddown_order_product_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_selfanddown_order_product_num` int(11) NOT NULL DEFAULT '0' COMMENT '自己和下级总下单商品数满(件)';
        ");
    }
    if(!pdo_fieldexists2('ddwx_member_level','up_selfanddown_order_product_num_proids')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `up_selfanddown_order_product_num_proids` varchar(255) DEFAULT NULL COMMENT '自己和下级总下单商品总数量指定的商品ID';
        ");
    }
}
if(getcustom('business_agent_jt_jinsuo')){
    if(!pdo_fieldexists2('ddwx_member_level','business_jt_jinsuo_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN  `business_jt_jinsuo_status` tinyint(1) DEFAULT '0' COMMENT '推荐商家间推紧缩';");
    }
    if(!pdo_fieldexists2('ddwx_member_level','business_zt_pj_ratio')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN  `business_zt_pj_ratio` decimal(11,2) DEFAULT '0.00' COMMENT '推荐商家直推平级',
        ADD COLUMN  `business_jt_pj_ratio` decimal(11,2) DEFAULT '0.00' COMMENT '推荐商家间推平级';");
    }
}

if(getcustom('mingpian_addfields')){
    if(!pdo_fieldexists2('ddwx_mingpian','addfields')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mingpian` ADD COLUMN `addfields` text NULL COMMENT '添加的联系信息数据';");
    }
}

if(getcustom('mingpian_addfields_membercustom')){
    if(!pdo_fieldexists2('ddwx_mingpian_readlog','applysee')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mingpian_readlog` 
            ADD COLUMN `applysee` tinyint(1) NULL DEFAULT 0 COMMENT '申请查看增加的字段-1：驳回 0：未申请 1：等待审核中 2：已批准',
            ADD COLUMN `checkseetime` int(11) NULL DEFAULT 0 COMMENT '申请时间',
            ADD COLUMN `seeaddfields` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '可查看的字段' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_mingpianread` varchar(64) NULL DEFAULT '';");
    }
}
if(getcustom('yx_mangfan_maidan_business')){
    if(!pdo_fieldexists2('ddwx_maidan_order','mangfan_money')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` 
        ADD COLUMN `mangfan_money` decimal(11,2) DEFAULT '0.00' COMMENT '盲返金额';");
    }
    if(!pdo_fieldexists2('ddwx_business','mangfan_maidan_st')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
        ADD COLUMN `mangfan_maidan_st` tinyint(1) DEFAULT '0' COMMENT '盲返状态0跟随系统总设置1单独设置-1不设置',
        ADD COLUMN `mangfan_maidan_set` text COMMENT '盲返设置';");
    }
}
if(getcustom('member_score_withdraw')){
    if(!pdo_fieldexists2('ddwx_admin_set','member_score_withdraw')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `member_score_withdraw` tinyint(1) DEFAULT '0',
        ADD COLUMN `member_score_to_money_ratio` decimal(11,2) DEFAULT '0.00',
        ADD COLUMN `member_score_to_money_min` decimal(11,2) DEFAULT '0.00';");
    }
}
if(getcustom('yx_team_yeji_maidan')){
    if(!pdo_fieldexists2("ddwx_team_yeji_set","include_maidan_yeji")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_team_yeji_set` 
            ADD COLUMN `include_maidan_yeji` tinyint(1) DEFAULT '0';");
    }
}

if(getcustom('withdraw_custom')){
    if(!pdo_fieldexists2('ddwx_admin_set','custom_name')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `custom_status` tinyint(1) DEFAULT '0' COMMENT '自定义状态 0关闭 1开启',
        ADD COLUMN `custom_name` varchar(60) DEFAULT '' COMMENT '自定义名称';");
    }

    if(!pdo_fieldexists2('ddwx_member','customaccountname')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
        ADD COLUMN `customaccountname` varchar(60) DEFAULT '' COMMENT '账户名称',
        ADD COLUMN `customaccount` varchar(100) DEFAULT '' COMMENT '账户编号',
        ADD COLUMN `customtel` char(11) DEFAULT NULL COMMENT '手机号';");
    }
    if(!pdo_fieldexists2('ddwx_member_withdrawlog','customaccountname')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_withdrawlog` 
        ADD COLUMN `customaccountname` varchar(60) DEFAULT '' COMMENT '账户名称',
        ADD COLUMN `customaccount` varchar(100) DEFAULT '' COMMENT '账户编号',
        ADD COLUMN `customtel` char(11) DEFAULT NULL COMMENT '手机号',
        ADD COLUMN `customwithdraw` tinyint(1) DEFAULT 0 COMMENT '自定义提现方式 0：其他 1：自定义提现方式';");
    }
    if(!pdo_fieldexists2('ddwx_member_commission_withdrawlog','customaccountname')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` 
        ADD COLUMN `customaccountname` varchar(60) DEFAULT '' COMMENT '账户名称',
        ADD COLUMN `customaccount` varchar(100) DEFAULT '' COMMENT '账户编号',
        ADD COLUMN `customtel` char(11) DEFAULT NULL COMMENT '手机号',
        ADD COLUMN `customwithdraw` tinyint(1) DEFAULT 0 COMMENT '自定义提现方式 0：其他 1：自定义提现方式';");
    }
}
if(getcustom('restaurant_shop_complete_autominutes')){
    if(!pdo_fieldexists2("ddwx_restaurant_shop_sysset","autominutes")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` 
            ADD COLUMN `autominutes` int(5) DEFAULT '10' COMMENT '点餐自动完成的分钟数';");
    }
}
if(getcustom('product_thali')){
    if(!pdo_fieldexists2('ddwx_school','product_thali_region')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_school` 
        ADD COLUMN `product_thali_region` varchar(50) DEFAULT '' COMMENT '区域';");
    }
    if(!pdo_fieldexists2('ddwx_member_address','product_thali_student_name')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_address` 
        ADD COLUMN `product_thali_student_name` varchar(100) DEFAULT NULL COMMENT '学生姓名',
        ADD COLUMN `product_thali_school` varchar(150) DEFAULT NULL COMMENT '学校信息';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_thali` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `bid` int(11) DEFAULT '0',
      `title` varchar(200) NOT NULL DEFAULT '0' COMMENT '套餐名称',
      `starttime` int(11) DEFAULT NULL COMMENT '有效开始时间',
      `endtime` int(11) DEFAULT NULL COMMENT '有效结束时间',
      `deliver_type` tinyint(1) NOT NULL COMMENT '配送方式 0到校领取  1是配送',
      `freight` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '运费',
      `product_info` text COMMENT '套餐包含的商品',
      `product_info_bx` text COMMENT '必选的商品',
      `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
      `desc` text COMMENT '套餐描述',
      `createtime` int(11) DEFAULT NULL,
      `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0关闭 1开启',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`),
      KEY `title` (`title`)
    ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='商品套餐';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_thali_order` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `bid` int(11) DEFAULT '0' COMMENT '商户ID',
      `mid` int(11) DEFAULT NULL COMMENT '会员ID',
      `ordernum` varchar(255) DEFAULT NULL COMMENT '订单号',
      `title` text COMMENT '订单名称',
      `proid` int(11) DEFAULT NULL,
      `proname` varchar(255) NOT NULL,
      `propic` varchar(255) DEFAULT NULL,
      `num` int(11) DEFAULT NULL,
      `totalprice` float(11,2) DEFAULT NULL COMMENT '总金额',
      `product_price` float(11,2) DEFAULT '0.00' COMMENT '商品金额',
      `freight_price` float(11,2) DEFAULT NULL COMMENT '运费金额',
      `invoice_money` float(11, 2) NULL DEFAULT '0',
      `scoredk_money` float(11,2) DEFAULT NULL COMMENT '积分抵扣金额',
      `leveldk_money` float(11,2) DEFAULT '0.00' COMMENT '会员等级优惠金额',
      `manjian_money` decimal(11,2) DEFAULT '0.00' COMMENT '满减优惠金额',
      `coupon_money` decimal(11,2) DEFAULT '0.00' COMMENT '优惠券金额',
      `coupon_rid` varchar(255) DEFAULT NULL COMMENT '优惠券coupon_record的id',
      `discount_money_admin` decimal(11, 2) NULL DEFAULT '0' COMMENT '管理员优惠金额',
      `scoredkscore` int(11) DEFAULT '0' COMMENT '积分抵扣用掉的积分',
      `givescore` int(11) DEFAULT '0' COMMENT '赠送积分',
      `givescore2` int(11) DEFAULT '0' COMMENT '赠送积分2',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      `status` int(11) DEFAULT '0' COMMENT '0未支付;1已支付;2已发货,3已收货,4关闭',
      `linkman` varchar(255) DEFAULT NULL COMMENT '姓名',
      `company` varchar(255) DEFAULT NULL COMMENT '单位',
      `tel` varchar(50) DEFAULT NULL COMMENT '手机号',
      `area` varchar(255) DEFAULT NULL COMMENT '省市区',
      `area2` varchar(255) DEFAULT NULL COMMENT '省市区逗号分割',
      `address` varchar(255) DEFAULT NULL COMMENT '地址',
      `longitude` varchar(100) DEFAULT NULL COMMENT '经度',
      `latitude` varchar(100) DEFAULT NULL COMMENT '维度',
      `message` varchar(255) DEFAULT NULL COMMENT '留言',
      `remark` varchar(255) DEFAULT NULL COMMENT '后台备注',
      `payorderid` int(11) DEFAULT NULL COMMENT '订单支付表id',
      `paytypeid` int(11) DEFAULT NULL COMMENT '支付方式 1余额支付 2微信支付 3支付宝支付 4货到付款 5转账汇款 11百度小程序 12头条小程序',
      `paytype` varchar(50) DEFAULT NULL COMMENT '支付方式文本',
      `paynum` varchar(255) DEFAULT NULL COMMENT '支付单号',
      `paytime` int(11) DEFAULT NULL COMMENT '支付时间',
      `express_com` varchar(255) DEFAULT NULL COMMENT '快递公司',
      `express_no` varchar(255) DEFAULT NULL COMMENT '快递单号',
      `express_ogids` varchar(255) DEFAULT NULL,
      `express_isbufen` tinyint(1) DEFAULT '0' COMMENT '是否部分发货',
      `express_type` varchar(255) NULL COMMENT '物流类型',
      `express_content` text COMMENT '多个快递单号时的快递单号数据',
      `refund_reason` varchar(255) DEFAULT NULL COMMENT '退款原因',
      `refund_money` decimal(11,2) DEFAULT '0.00',
      `refund_status` int(1) DEFAULT '0' COMMENT '1申请退款审核中 2已同意退款 3已驳回',
      `refund_time` int(11) DEFAULT NULL COMMENT '退款时间',
      `refund_checkremark` varchar(255) DEFAULT NULL COMMENT '退款审核备注',
      `send_time` bigint(20) DEFAULT NULL COMMENT '发货时间',
      `collect_time` int(11) DEFAULT NULL COMMENT '收货时间',
      `freight_id` int(11) DEFAULT NULL COMMENT '配送方式ID',
      `freight_text` varchar(255) DEFAULT NULL COMMENT '配送方式',
      `freight_type` tinyint(1) DEFAULT '0' COMMENT '配送方式类型 0普通快递 1到店自提 2同城配送 3自动发货 4在线卡密',
      `mdid` int(11) DEFAULT NULL COMMENT '到店自提时门店ID',
      `freight_time` varchar(255) DEFAULT NULL COMMENT '取货时间',
      `freight_content` text COMMENT '自动发货信息 卡密',
      `hexiao_code` varchar(100) DEFAULT NULL COMMENT '唯一码 核销码',
      `hexiao_qr` varchar(255) DEFAULT NULL COMMENT '核销码图片',
      `hexiao_code_member` varchar(100) NULL,
      `platform` varchar(255) DEFAULT 'wx' COMMENT '来源平台 mp公众号 wx微信小程序',
      `iscomment` tinyint(1) DEFAULT '0' COMMENT '是否已评价',
      `delete` tinyint(1) DEFAULT '0' COMMENT '用户删除 0未删除 1已删除',
      `isfenhong` tinyint(1) DEFAULT '0' COMMENT '是否已经分红',
      `checkmemid` int(11) DEFAULT NULL,
      `balance_price` float(11,2) DEFAULT '0.00',
      `balance_pay_status` tinyint(1) DEFAULT '0',
      `balance_pay_orderid` int(11) DEFAULT NULL,
      `fromwxvideo` tinyint(1) DEFAULT '0' COMMENT '是否是视频号过来的订单',
      `scene` int(11) DEFAULT '0' COMMENT '小程序场景',
      `wxvideo_order_id` varchar(100) DEFAULT NULL,
      `sysOrderNo` varchar(255) DEFAULT NULL COMMENT '定制同步app端',
      `transfer_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '转账审核 -1 驳回 0：待审核 1：通过',
      `product_thali_student_name` varchar(100) DEFAULT NULL COMMENT '学生姓名',
      `product_thali_school` varchar(150) DEFAULT NULL COMMENT '学校信息',
      `product_thali_deliver_type` tinyint(1) DEFAULT NULL COMMENT '配送方式 0到校领取  1是配送',
      PRIMARY KEY (`id`),
      UNIQUE KEY `code` (`hexiao_code`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `status` (`status`) USING BTREE,
      KEY `createtime` (`createtime`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='套餐订单表';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_thali_order_goods` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL COMMENT '账户ID',
      `bid` int(11) DEFAULT '0' COMMENT '商户ID 0表示平台',
      `mid` int(11) DEFAULT NULL COMMENT '会员ID',
      `orderid` int(11) DEFAULT NULL COMMENT '订单ID',
      `ordernum` varchar(50) DEFAULT NULL COMMENT '订单号',
      `proid` int(11) DEFAULT NULL COMMENT '商品ID',
      `name` varchar(255) DEFAULT NULL COMMENT '商品名称',
      `pic` varchar(255) DEFAULT NULL COMMENT '商品图片',
      `procode` varchar(255) DEFAULT NULL COMMENT '商品编码',
      `barcode` varchar(255) DEFAULT NULL,
      `ggid` int(11) DEFAULT NULL COMMENT '规格ID',
      `gg_group_title` varchar(60) DEFAULT NULL COMMENT '规格分组名称',
      `ggname` varchar(255) DEFAULT NULL COMMENT '规格名称',
      `cid` varchar(255) NULL DEFAULT '0' COMMENT '分类ID',
      `num` int(11) DEFAULT '0' COMMENT '购买数量',
      `refund_num` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '退款数量',
      `cost_price` decimal(11,2) DEFAULT NULL COMMENT '成本价',
      `sell_price` decimal(11,2) DEFAULT NULL COMMENT '销售价',
      `totalprice` decimal(11,2) DEFAULT NULL COMMENT '总价',
      `total_weight` decimal(11, 2) UNSIGNED NULL DEFAULT '0',
      `scoredk_money` decimal(11, 2) NULL DEFAULT '0' ,
      `leveldk_money` decimal(11, 2) NULL DEFAULT '0' ,
      `manjian_money` decimal(11, 2) NULL DEFAULT '0' ,
      `coupon_money` decimal(11, 2) NULL DEFAULT '0' ,
      `real_totalprice` decimal(10,2) DEFAULT '0.00' COMMENT '实际商品销售金额 减去了优惠券抵扣会员折扣满减积分抵扣的金额',
      `business_total_money` decimal(11, 2) NULL DEFAULT NULL,
      `status` int(1) DEFAULT '0' COMMENT '0未付款1已付款2已发货3已收货4申请退款5已退款',
      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
      `endtime` int(11) DEFAULT NULL COMMENT '完成时间 收货时间',
      `iscomment` tinyint(1) DEFAULT '0' COMMENT '是否已评价',
      `parent1` int(11) DEFAULT NULL COMMENT '直接推荐人',
      `parent2` int(11) DEFAULT NULL COMMENT '二级推荐人',
      `parent3` int(11) DEFAULT NULL COMMENT '三级推荐人',
      `parent4` int(11) DEFAULT NULL,
      `parent1commission` decimal(11,2) DEFAULT '0.00' COMMENT '一级提成',
      `parent2commission` decimal(11,2) DEFAULT '0.00' COMMENT '二级提成',
      `parent3commission` decimal(11,2) DEFAULT '0.00' COMMENT '三级提成',
      `parent4commission` decimal(11,2) DEFAULT '0.00',
      `parent1score` int(11) DEFAULT '0' COMMENT '一级提成积分',
      `parent2score` int(11) DEFAULT '0' COMMENT '二级提成积分',
      `parent3score` int(11) DEFAULT '0' COMMENT '三级提成积分',
      `iscommission` tinyint(1) DEFAULT '0' COMMENT '佣金是否已发放',
      `isfenhong` tinyint(1) DEFAULT '0' COMMENT '分红是否已结算',
      `isfg` tinyint(1) DEFAULT '0' COMMENT '是否复购',
      `isteamfenhong` tinyint(1) DEFAULT '0' COMMENT '团队分红是否已结算',
      `isdan` int(11) DEFAULT '0',
      `hexiao_code` varchar(100) DEFAULT NULL COMMENT '唯一码 核销码',
      `hexiao_qr` varchar(255) DEFAULT NULL COMMENT '核销码图片',
      `hexiao_num` int(11) DEFAULT '0',
      `gouchebonusset` tinyint(1) NOT NULL DEFAULT '0' COMMENT '购车基金设置0不参与1按比例2按金额',
      `gouchebonusdata1` text COMMENT '购车基金按比例设置参数',
      `gouchebonusdata2` text COMMENT '购车基金按金额设置参数',
      `lvyoubonusset` tinyint(1) NOT NULL DEFAULT '0' COMMENT '旅游基金设置0不参与1按比例2按金额',
      `lvyoubonusdata1` text COMMENT '旅游基金按比例设置参数',
      `lvyoubonusdata2` text COMMENT '旅游基金按金额设置参数',
      `gtype` tinyint(4) NULL DEFAULT '0' COMMENT '类型：0默认，1 赠送商品',
      `remark` varchar(255) DEFAULT '',
      `real_totalmoney` decimal(11,2) DEFAULT '0.00' COMMENT '实际支付金额（减去优惠券抵扣、会员折扣、满减积分抵扣），区别于real_totalprice)',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `orderid` (`orderid`) USING BTREE,
      KEY `proid` (`proid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='套餐订单商品表';");
}
if(getcustom('yx_queue_free_product_not_queue')){
    if(!pdo_fieldexists2("ddwx_shop_product","queue_free_not_queue")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `queue_free_not_queue` tinyint(1) DEFAULT '0' COMMENT '不参与排队，参与返利 0关闭 1开启';");
    }
}
if(getcustom('yx_queue_free_product_not_back')){
    if(!pdo_fieldexists2("ddwx_shop_product","queue_free_not_back")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        ADD COLUMN  `queue_free_not_back` tinyint(1) DEFAULT '0' COMMENT '不参加返利0关闭 1开启';");
    }
}
if(getcustom('shop_peisong_wifiprint_tmpl')){
    if(!pdo_fieldexists2("ddwx_wifiprint_set","shop_tmpltype")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_wifiprint_set` 
        ADD COLUMN  `shop_tmpltype` tinyint(1) DEFAULT '0' COMMENT '1开启0关闭',
        ADD COLUMN `left_top_text` varchar(255) DEFAULT NULL COMMENT '左侧顶部文本',
        ADD COLUMN `right_top_text` varchar(255) DEFAULT NULL COMMENT '右侧顶部文本';");
    }
}
if(getcustom('mendian_hexiao_money_to_score')){
    if(!pdo_fieldexists2("ddwx_admin_set","money_to_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `money_to_score` tinyint(1) DEFAULT '0' COMMENT '核销赠送，商品金额换算积分 1开启0关闭',
        ADD COLUMN `money_to_score_bili` decimal(11,2) DEFAULT '100.00' COMMENT '转换比例';");
    }
}
if(getcustom('transfer_order_parent_check')){
    if(!pdo_fieldexists2('ddwx_shop_sysset','transfer_order_parent_check')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
        ADD COLUMN `transfer_order_parent_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '转单给上级审核 0关闭 1开启',
        ADD COLUMN `transfer_order_parent_check_object` tinyint(1) NOT NULL DEFAULT '0' COMMENT '转单接收对象  0直推上级 1最近的高等级上级';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS`ddwx_transfer_order_parent_check_log` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `bid` int(11) NOT NULL DEFAULT '0',
      `orderid` int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
      `mid` int(11) NOT NULL DEFAULT '0' COMMENT '审核人id',
      `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '收款审核金额',
      `tj_mid` int(11) NOT NULL DEFAULT '0' COMMENT '审核提交人id',
      `tj_mlevelid` int(11) NOT NULL DEFAULT '0' COMMENT '提交人当时级别id',
      `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待审核  1确认收款  2取消订单 3转给上级',
      `createtime` int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
      `examinetime` int(11) NOT NULL DEFAULT '0' COMMENT '审核时间',
      `submittime` int(11) NOT NULL DEFAULT '0' COMMENT '提交上级审核时间',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `orderid` (`orderid`),
      KEY `mid` (`mid`),
      KEY `tj_mid` (`tj_mid`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='转单上级审核记录表';");
}
if(getcustom('shop_yingxiao_tag')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_yingxiao_tag` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `title` varchar(255) DEFAULT NULL,
      `content` varchar(255) DEFAULT NULL,
      `btn_text` varchar(255) DEFAULT NULL COMMENT '购买按钮',
      `sales_text` varchar(255) DEFAULT NULL COMMENT '销量属性',
      `fwtype` tinyint(1) DEFAULT '0' COMMENT '使用范围0所有 1指定类目 2指定商品',
      `sort` int(11) DEFAULT '0',
      `productids` varchar(255) DEFAULT NULL COMMENT '商品',
      `categoryids` varchar(255) DEFAULT NULL COMMENT '分类ID',
      `status` tinyint(1) DEFAULT '1',
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('transfer_order_parent_check')){
    if(!pdo_fieldexists2("ddwx_transfer_order_parent_check_log","hide")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_transfer_order_parent_check_log` 
        ADD COLUMN `hide` tinyint(1) NOT NULL DEFAULT '1' COMMENT '默认隐藏   1隐藏  0开启';");
    }
}

if(getcustom('finance_invoice_baoxiao')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_invoice_baoxiao_record` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0,
        `deduct_score` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '扣除积分',
        `money` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '打款金额',
        `invoice_data` text NOT NULL COMMENT '发票信息',
        `invoice_pics` text NOT NULL COMMENT '发票图片',
        `consume_pics` text NOT NULL COMMENT '消费图片',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待审核 1已通过 2已驳回',
        `payment_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '打款状态 0未打款 1待打款 2已打款',
        `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '驳回原因',
        `paytype` varchar(60) DEFAULT '' COMMENT '支付方式',
        `payinfo` varchar(255) DEFAULT '' COMMENT '支付信息',
        `aliaccountname` varchar(255) DEFAULT '' COMMENT '支付宝姓名',
        `aliaccount` varchar(255) DEFAULT '' COMMENT '支付宝账号',
        `bankname` varchar(255) DEFAULT '' COMMENT '银行名称',
        `bankcarduser` varchar(255) DEFAULT '' COMMENT '银行卡开户人',
        `bankcardnum` varchar(255) DEFAULT '' COMMENT '银行卡号',
        `wxpaycode` varchar(255)  NULL DEFAULT '' COMMENT '微信收款码' ,
        `alipaycode` varchar(255)  NULL DEFAULT '' COMMENT '支付宝收款码',
        `operatetime` int(11) DEFAULT NULL,
        `createtime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_invoice_baoxiao_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `pic` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '图片',
        `shuoming` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '说明',
        `upload_tips` varchar(255) DEFAULT NULL COMMENT '上传提示',
        `company` varchar(255) DEFAULT NULL COMMENT '公司名称',
        `company_code` varchar(255) DEFAULT NULL COMMENT '公司代码',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_baoxiaosuccess")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` 
        ADD COLUMN `tmpl_baoxiaosuccess` varchar(64) DEFAULT NULL COMMENT '发票报销成功短信模板',
        ADD COLUMN `tmpl_baoxiaosuccess_st` tinyint(1) DEFAULT '1' COMMENT '发票报销成功短信模板 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_invoice_baoxiao_record","authenticity")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_invoice_baoxiao_record` 
        ADD COLUMN `authenticity` tinyint(1) DEFAULT 0 COMMENT '发票真伪 0发票存疑 1发票为真';");
    }
    if(pdo_fieldexists2("ddwx_invoice_baoxiao_record","authenticity")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_invoice_baoxiao_record`
        MODIFY COLUMN `authenticity` tinyint(1) DEFAULT -1 COMMENT '发票真伪 0发票存疑 1发票为真 -1待辨真伪';");
    }
    if(pdo_fieldexists2("ddwx_invoice_baoxiao_record","invoice_data")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_invoice_baoxiao_record`
        MODIFY COLUMN `invoice_data` text COMMENT '发票信息';");
    }

    if(!pdo_fieldexists2("ddwx_invoice_baoxiao_record", "bankaddress")){
        \think\facade\Db::execute("ALTER TABLE ddwx_invoice_baoxiao_record 
        ADD COLUMN `bankaddress` varchar(255) DEFAULT NULL COMMENT '所属分支行' AFTER `bankcardnum`;");
    }
}
if(getcustom('business_list_show_tel')){
    if(!pdo_fieldexists2("ddwx_business_sysset","show_business_tel")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
        ADD COLUMN `show_business_tel` tinyint(1) DEFAULT '1' COMMENT '列表是否显示商家电话 0关闭 1开启';");
    }
}

if(getcustom('scoreshop_hide_refund')){
    if(!pdo_fieldexists2("ddwx_scoreshop_sysset","show_refund")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_sysset` 
        ADD COLUMN `show_refund` tinyint(1) DEFAULT '1' COMMENT '退款入口 0关闭 1开启';");
    }
}

if(getcustom('ocr_aliyun')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_aliocr_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `access_key_id` varchar(255) NOT NULL DEFAULT '' COMMENT '阿里云AccessKey ID',
        `access_key_secret` varchar(255) DEFAULT '' COMMENT '阿里云AccessKey Secret',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}

if(getcustom('article_activity_time')){
    if(!pdo_fieldexists2("ddwx_article_set","activity_time_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_article_set` 
        ADD COLUMN `activity_time_status` tinyint(1) DEFAULT 0 COMMENT '活动时间段 0关闭 1开启',
        ADD COLUMN `show_time` tinyint(1) DEFAULT '1' COMMENT '显示发布时间 0关闭 1开启',
        ADD COLUMN `show_readcount` tinyint(1) DEFAULT '1' COMMENT '显示阅读量 0关闭 1开启';");
    }

    if(!pdo_fieldexists2("ddwx_article","activity_start_time")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_article` 
        ADD COLUMN `activity_start_time` varchar(30) DEFAULT '' COMMENT '活动开始时间',
        ADD COLUMN `activity_end_time` varchar(30) DEFAULT '' COMMENT '活动结束时间';");
    }

    if(!pdo_fieldexists2("ddwx_article","activity_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_article` 
        ADD COLUMN `activity_status` tinyint(1) DEFAULT 0 COMMENT '活动起止时间 0关闭 1开启';");
    }
}
if(getcustom('team_update_member_info')){
    if(!pdo_fieldexists2('ddwx_member_level','team_update_member_info')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
        ADD COLUMN `team_update_member_info` tinyint(1) NOT NULL DEFAULT '0' COMMENT '修改下级成员资料 0关闭 1开启';");
    }
}
if(getcustom('pay_ysepay')){
    if(!pdo_fieldexists2("ddwx_admin_setapp_h5","ysepay_merchantId")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_h5` 
        ADD COLUMN `ysepay_certId` varchar(60) DEFAULT '' COMMENT '银盛支付服务商编号',
        ADD COLUMN `ysepay_merchantId` varchar(60) DEFAULT '' COMMENT '银盛支付商户编号',
        ADD COLUMN `ysepay_publicCertPath` varchar(255) DEFAULT '' COMMENT '银盛支付公钥证书rsa',
        ADD COLUMN `ysepay_privateCertPath` varchar(255) DEFAULT '' COMMENT '银盛支付私钥证书rsa',
        ADD COLUMN `ysepay_privateCertPwd` varchar(120) DEFAULT '' COMMENT '银盛支付私钥证书密码';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ysepay_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT '0',
  `mid` int(11) DEFAULT NULL,
  `merchantId` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `tablename` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `amount` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `refund_money` decimal(11,2) DEFAULT '0.00',
  `remark` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `givescore` int(11) DEFAULT '0',
  `req_data` text,
  `reqId` varchar(60) CHARACTER SET utf8 DEFAULT NULL COMMENT '请求流水号',
  `reqMsgId` varchar(60) CHARACTER SET utf8 DEFAULT NULL COMMENT '请求返回流水号',
  `res_data` text,
  `pay_status` tinyint(1) DEFAULT '0',
  `notify_data` text,
  `tradeSn` varchar(60) CHARACTER SET utf8 DEFAULT NULL COMMENT '交易流水号',
  `isfenzhang` tinyint(1) DEFAULT '0' COMMENT '0待分账，1已分账，2分账失败，3退款退回，4取消分账',
  `fenzhangdata` text COMMENT '分账对象',
  `createtime` int(11) DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `ordernum` (`ordernum`) USING BTREE,
  KEY `tablename` (`tablename`) USING BTREE,
  KEY `merchantId` (`merchantId`) USING BTREE,
  KEY `reqMsgId` (`reqMsgId`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `pay_status` (`pay_status`),
  KEY `isfenzhang` (`isfenzhang`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ysepay_refund_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT '0',
  `mid` int(11) DEFAULT NULL,
  `merchantId` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `tablename` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `ordernum` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `origOrdernum` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `origTradeSn` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `refundAmount` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `remark` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `req_data` text,
  `reqMsgId` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `res_data` text,
  `status` tinyint(1) DEFAULT '0',
  `notify_data` text,
  `tradeSn` varchar(60) CHARACTER SET utf8 DEFAULT NULL COMMENT '交易流水号',
  `createtime` int(11) DEFAULT NULL,
  `platform` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `ordernum` (`ordernum`) USING BTREE,
  KEY `origOrdernum` (`origOrdernum`) USING BTREE,
  KEY `merchantId` (`merchantId`) USING BTREE,
  KEY `reqMsgId` (`reqMsgId`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `origTradeSn` (`origTradeSn`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;");
}
if(getcustom('bonus_pool_gold')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_bonuspool_gold_log` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
        `value`  decimal(12,2) NULL DEFAULT 0.00 ,
        `after`  decimal(12,2) NULL DEFAULT NULL ,
        `createtime`  int(11) NULL DEFAULT NULL ,
        `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `channel`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '变动渠道' ,
        `orderid`  int(11) NULL DEFAULT NULL ,
        `gold_price`  decimal(12,4) NOT NULL DEFAULT 0.0000 ,
        `dif_price`  decimal(12,4) NOT NULL DEFAULT 0.0000 ,
        `gold_num`  decimal(12,2) NULL DEFAULT NULL ,
        `gold_total`  decimal(12,2) NULL DEFAULT 0.00 ,
        `bid`  int(11) NULL DEFAULT NULL ,
        PRIMARY KEY (`id`),
        INDEX `aid` (`aid`) USING BTREE ,
        INDEX `mid` (`mid`) USING BTREE 
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic COMMENT='奖金池-奖金池变动明细';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_bonuspool_gold_set` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `createtime`  int(11) NULL DEFAULT NULL ,
        `status`  tinyint(1) NULL DEFAULT 0 COMMENT '0关闭 1开启' ,
        `shop_to_bonuspool`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '商城订单进入奖金池比例' ,
        `collage_to_bonuspool`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '拼团订单进入奖金池比例' ,
        `maidan_to_bonuspool`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '买单订单进入奖金池比例' ,
        `recharge_to_bonuspool`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '充值进入奖金池比例' ,
        `reward_time`  tinyint(1) NULL DEFAULT 0 COMMENT '订单进入奖金池时间 0收货完成 1支付完成' ,
        `bonuspool_gold_rate`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '进入奖金池比例' ,
        `buy_gold_way`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '购买金币的支付方式' ,
        `min_price`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '金币初始价值' ,
        `auto_withdraw_beishu`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '金币自动出局倍数' ,
        `business_auto_withdraw_beishu`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '商家金币自动出局倍数' ,
        `withdraw_to_wallet`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '金币兑换到账方式' ,
        `cash_fee`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '金币兑换手续费' ,
        `withdraw_desc`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '金币兑换说明' ,
        `maidan_no_min`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '买单不参与奖金池的最低金额范围' ,
        `maidan_no_max`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '买单不参与奖金池的最高金额范围' ,
        `gold_price`  decimal(20,6) NULL DEFAULT NULL ,
        `bonus_pool_total`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '奖金池总数量' ,
        `gold_total`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '金币数量' ,
        `desc`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '奖金池规则' ,
        `bgpic`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `centerpic`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `btnpic`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `score_value`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '积分价值 1积分等于多少元' ,
        `to_money`  decimal(12,2) NULL DEFAULT NULL ,
        `to_score`  decimal(12,2) NULL DEFAULT NULL ,
        `to_commission`  decimal(12,2) NULL DEFAULT NULL ,
        `show_bonus_pool`  tinyint(1) NULL DEFAULT 0 COMMENT '页面是否显示奖金池 0否 1是' ,
        `gettj` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic COMMENT='奖金池-奖金池设置';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_buy_gold_order` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  int(11) NULL DEFAULT NULL ,
        `money`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '支付金额' ,
        `ordernum`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单号' ,
        `select_paytype`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '选择的支付方式 money余额 score积分 online在线支付' ,
        `paytype`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '支付方式 money余额 score积分 online在线支付' ,
        `createtime`  int(10) NULL DEFAULT NULL ,
        `status`  tinyint(1) NULL DEFAULT 0 COMMENT '状态 0未支付 1已支付' ,
        `payorderid`  int(11) NULL DEFAULT NULL ,
        `paytime`  int(10) NULL DEFAULT 0 COMMENT '支付时间' ,
        `paytypeid`  int(11) NULL DEFAULT NULL ,
        `paynum`  varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `platform`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `gold_num`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '购买金币数量' ,
        `fee`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '手续费' ,
        `score`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '花费积分数量' ,
        `buy_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '购买金额' ,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic COMMENT='奖金池-会员购买金币记录';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_gold_price_log` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NULL DEFAULT NULL ,
    `mid`  int(11) NULL DEFAULT NULL ,
    `bid`  int(11) NULL DEFAULT NULL ,
    `old_price`  decimal(20,6) NULL DEFAULT NULL ,
    `gold_price`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '金币价格' ,
    `dif_price`  decimal(20,6) NULL DEFAULT NULL COMMENT '金币涨幅' ,
    `channel`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '来源类型' ,
    `orderid`  int(11) NULL DEFAULT NULL COMMENT '来源订单id' ,
    `gold_num`  decimal(20,6) NULL DEFAULT NULL COMMENT '金币变动数量' ,
    `pool_num`  decimal(12,2) NULL DEFAULT NULL COMMENT '奖金池变动数量' ,
    `pool_total`  decimal(12,2) NULL DEFAULT NULL COMMENT '奖金池总数量' ,
    `gold_total`  decimal(20,6) NULL DEFAULT NULL COMMENT '金币总数量' ,
    `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '备注' ,
    `createtime`  int(10) NULL DEFAULT NULL COMMENT '创建时间' ,
    PRIMARY KEY (`id`)
    )ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic COMMENT='奖金池-金币变动记录';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_gold_withdraw_log` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NOT NULL DEFAULT 0 ,
    `mid`  int(11) NOT NULL DEFAULT 0 COMMENT '会员id' ,
    `money`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '提现金额' ,
    `fee`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '手续费' ,
    `gold_num`  decimal(20,6) NOT NULL DEFAULT 0.000000 COMMENT '扣除绿色积分' ,
    `gold_price`  decimal(20,6) NOT NULL DEFAULT 0.000000 COMMENT '金币价格' ,
    `to_commission`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '转入佣金数量' ,
    `to_money`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '转入余额数量' ,
    `to_score`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '转入积分数量' ,
    `to_pool`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '回放奖金池' ,
    `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注' ,
    `createtime`  int(10) NOT NULL DEFAULT 0 COMMENT '操作时间' ,
    `remain`  decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '剩余数量' ,
    `is_business`  tinyint(1) NULL DEFAULT 0 COMMENT '是否商家 0否 1是' ,
    `log_id`  int(11) NULL DEFAULT NULL ,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic COMMENT='奖金池-提现记录';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_gold_log` (
    `id`  int(11) NOT NULL AUTO_INCREMENT ,
    `aid`  int(11) NULL DEFAULT 0 ,
    `mid`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' ,
    `orderid`  int(11) NOT NULL DEFAULT 0 ,
    `value`  decimal(20,6) NULL DEFAULT 0.000000 ,
    `after`  decimal(20,6) NULL DEFAULT 0.000000 ,
    `type`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'shop' ,
    `remark`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
    `createtime`  int(11) NULL DEFAULT 0 ,
    `updatetime`  int(11) NULL DEFAULT 0 ,
    `is_business`  tinyint(1) NULL DEFAULT 0 COMMENT '是否是商家 0否 1是' ,
    `channel`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单类型' ,
    `remain`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '剩余数量' ,
    `gold_price`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '金币价格' ,
    `dif_price`  decimal(20,6) NULL DEFAULT NULL COMMENT '价格变动' ,
    PRIMARY KEY (`id`),
    INDEX `aid` (`aid`) USING BTREE ,
    INDEX `mid` (`mid`) USING BTREE 
    ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic COMMENT='奖金池-金币明细记录';");

    if(!pdo_fieldexists2('ddwx_business','gold')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `gold`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '金币数量';");
    }
    if(!pdo_fieldexists2('ddwx_business','maidan_bonuspool_set')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `maidan_bonuspool_set`  tinyint(1) NULL DEFAULT -1 COMMENT '商家买单奖金池设置 0跟随系统 1单独设置比例 2单独设置金额 -1不参与';");
    }
    if(!pdo_fieldexists2('ddwx_business','maidan_bonuspool')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `maidan_bonuspool`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '买单进入奖金池';");
    }
    if(!pdo_fieldexists2('ddwx_business','maidan_no_min')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `maidan_no_min`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '买单不参与奖金池最低金额';");
    }
    if(!pdo_fieldexists2('ddwx_business','maidan_no_max')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `maidan_no_max`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '买单不参与奖金池最高金额';");
    }
    if(!pdo_fieldexists2('ddwx_collage_order','bonuspool_gold_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `bonuspool_gold_status`  tinyint(1) NULL DEFAULT 0 COMMENT '发放奖金池状态 0未发放 1已发放';");
    }
    if(!pdo_fieldexists2('ddwx_collage_order','bonuspool_gold_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `bonuspool_gold_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '进入奖金池数量';");
    }
    if(!pdo_fieldexists2('ddwx_collage_order','gold_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `gold_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '产生金币数量';");
    }
    if(!pdo_fieldexists2('ddwx_collage_product','bonuspool_gold_set')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `bonuspool_gold_set`  tinyint(1) NULL DEFAULT -1 COMMENT '进入奖金池设置 0跟随系统 1单独设置比例 2单独设置金额 -1不参与' ;");
    }
    if(!pdo_fieldexists2('ddwx_collage_product','bonuspool_gold')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `bonuspool_gold`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '进入奖金池比例' AFTER `bonuspool_gold_set`;");
    }
    if(!pdo_fieldexists2('ddwx_maidan_order','bonuspool_gold_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `bonuspool_gold_status`  tinyint(1) NULL DEFAULT 0 COMMENT '是否发放奖金池 0否 1是';");
    }
    if(!pdo_fieldexists2('ddwx_member','gold')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `gold`  decimal(20,6) NULL DEFAULT 0.000000 COMMENT '金币';");
    }
    if(!pdo_fieldexists2('ddwx_recharge_order','bonuspool_gold_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_recharge_order` ADD COLUMN `bonuspool_gold_status`  tinyint(1) NULL DEFAULT 0 COMMENT '发放奖金池状态 0未发放 1已发放';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order','bonuspool_gold_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `bonuspool_gold_status`  tinyint(1) NULL DEFAULT 0 COMMENT '发放奖金池状态 0未发放 1已发放';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','bonuspool_gold_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `bonuspool_gold_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '进入奖金池数量';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','gold_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `gold_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '产生金币数量';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','bonuspool_gold_set')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `bonuspool_gold_set`  tinyint(1) NULL DEFAULT -1 COMMENT '进入奖金池设置 0跟随系统 1单独设置比例 2单独设置金额 -1不参与';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','bonuspool_gold')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `bonuspool_gold`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '进入奖金池比例';");
    }
    if(!pdo_fieldexists2('ddwx_bonuspool_gold_set','show_gold_price')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_bonuspool_gold_set` ADD COLUMN `show_gold_price`  tinyint(1) NULL DEFAULT 0 COMMENT '页面是否显示金币价格 0否 1是';");
    }
    if(!pdo_fieldexists2('ddwx_collage_order','member_gold_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `member_gold_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '会员产生金币数量';");
    }
    if(!pdo_fieldexists2('ddwx_collage_product','member_gold_set')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `member_gold_set`  tinyint(1) NULL DEFAULT -1 COMMENT '用于会员购买金币 -1不参与奖金池 0跟随系统 1单独设置比例 2单独设置金额';");
    }
    if(!pdo_fieldexists2('ddwx_collage_product','member_gold')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` ADD COLUMN `member_gold`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '给会员产生金币的数量';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','member_gold_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `member_gold_num`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '会员产生金币数量';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','member_gold_set')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `member_gold_set`  tinyint(1) NULL DEFAULT -1 COMMENT '用于会员购买金币 -1不参与奖金池 0跟随系统 1单独设置比例 2单独设置金额';");
    }
    if(pdo_fieldexists2('ddwx_shop_product','member_gold_set')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` MODIFY COLUMN `member_gold_set`  tinyint(1) NULL DEFAULT -1 COMMENT '用于会员购买金币 -1不参与奖金池 0跟随系统 1单独设置比例 2单独设置金额';");
    }
    if(!pdo_fieldexists2('ddwx_shop_product','member_gold')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` ADD COLUMN `member_gold`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '给会员产生金币的数量';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','bonuspool_member')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `bonuspool_member`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '进入奖金池数量';");
    }
    if(!pdo_fieldexists2('ddwx_collage_order','bonuspool_member')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` ADD COLUMN `bonuspool_member`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '进入奖金池数量';");
    }
    if(!pdo_fieldexists2('ddwx_bonuspool_gold_set','show_buy_fee')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_bonuspool_gold_set` ADD COLUMN `show_buy_fee`  tinyint(1) NULL DEFAULT 0 COMMENT '是否显示购买手续费 0不显示 1显示';");
    }
    if(!pdo_fieldexists2('ddwx_bonuspool_gold_set','bonuspool_gold_rate_member')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_bonuspool_gold_set` ADD COLUMN `bonuspool_gold_rate_member`  tinyint(1) NULL DEFAULT 0 COMMENT '是否显示购买手续费 0不显示 1显示';");
    }
    if(!pdo_fieldexists2('ddwx_bonuspool_gold_set','buy_gold_bili')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_bonuspool_gold_set` ADD COLUMN `buy_gold_bili`   decimal(12,2) NULL DEFAULT 0.00 COMMENT '到账比例';");
    }
    if(!pdo_fieldexists2('ddwx_buy_gold_order','buy_gold_bili')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_buy_gold_order` ADD COLUMN `buy_gold_bili`   decimal(12,2) NULL DEFAULT 0.00 COMMENT '到账比例';");
    }
    if(!pdo_fieldexists2('ddwx_buy_gold_order','real_buy_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_buy_gold_order` ADD COLUMN `real_buy_num`   decimal(12,2) NULL DEFAULT 0.00 COMMENT '按到账比例计算后的金额';");
    }
    if(!pdo_fieldexists2('ddwx_bonuspool_gold_set','buy_btn_text')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_bonuspool_gold_set` ADD COLUMN `buy_btn_text` varchar(255) DEFAULT NULL COMMENT '购买按钮文字';");
    }
    if(!pdo_fieldexists2('ddwx_bonuspool_gold_set','withdraw_btn_text')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_bonuspool_gold_set` ADD COLUMN `withdraw_btn_text` varchar(255) DEFAULT NULL COMMENT '兑换按钮文字';");
    }
}

if(getcustom('commission_notice_twice')){
    if(!pdo_fieldexists2('ddwx_mp_tmplset_new','tmpl_commission_success')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` 
        ADD COLUMN `tmpl_commission_success` varchar(64) NULL DEFAULT '' COMMENT '佣金通知';");
    }
}
if(getcustom('car_management')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_car_set`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `money` decimal(10, 2) NULL DEFAULT 0.00,
      `day` int(11) NULL DEFAULT NULL,
      `createtime` int(11) NULL DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_car_order`  (
      `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL COMMENT '账户ID',
      `bid` int(11) NULL DEFAULT 0 COMMENT '商户ID 0表示平台',
      `mid` int(11) NULL DEFAULT NULL COMMENT '会员ID',
      `title` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '标题',
      `price` decimal(11, 2) NULL DEFAULT NULL COMMENT '金额',
      `ordernum` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单号',
      `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
      `status` tinyint(1) NULL DEFAULT 0 COMMENT '状态 0未支付1已支付',
      `payorderid` int(11) NULL DEFAULT NULL COMMENT '支付表id',
      `paytype` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '支付方式',
      `paytypeid` int(11) NULL DEFAULT NULL,
      `paynum` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '支付单号',
      `paytime` int(11) NULL DEFAULT NULL COMMENT '支付时间',
      `msg` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `platform` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `totalprice` float(11, 2) NULL DEFAULT NULL,
      `num` int(11) NULL DEFAULT 0,
      `car_id` int(11) NULL DEFAULT NULL,
      `oldinfo` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
      `newinfo` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
      `type` tinyint(1) NULL DEFAULT 0 COMMENT '0编辑1新增',
      PRIMARY KEY (`id`) USING BTREE,
      INDEX `aid`(`aid`) USING BTREE,
      INDEX `mid`(`mid`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '车辆管理订单' ROW_FORMAT = COMPACT;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_car`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `mid` int(11) NULL DEFAULT NULL,
      `car_num` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `car_type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `truename` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `tel` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `driving_license` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `idcard` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `idcard_back` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `baoxian_time` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `nianjian_time` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `baoyang_time` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `createtime` int(11) NULL DEFAULT NULL,
      `edittime` int(11) NULL DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");
    if(!pdo_fieldexists2('ddwx_mp_tmplset_new','tmpl_car_baoxian')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` 
        ADD COLUMN `tmpl_car_baoxian` varchar(64) NULL DEFAULT '' COMMENT '客户预约车险通知',
        ADD COLUMN `tmpl_car_baoyang` varchar(64) NULL DEFAULT '' COMMENT '车辆保养申请提醒',
        ADD COLUMN `tmpl_car_nianjian` varchar(64) NULL DEFAULT '' COMMENT '车辆年检申请提醒';");
    }

}
if(getcustom('member_notice')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_notice`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `bid` int(11) NULL DEFAULT NULL,
      `title` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `content` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
      `createtime` int(11) NULL DEFAULT NULL,
      `is_read` tinyint(1) NULL DEFAULT 0 COMMENT '0未读1已读',
      `mid` int(11) NULL DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");
}
if(getcustom('member_tag_pic')){
    if(!pdo_fieldexists2('ddwx_member_tag','pic')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_tag`
    ADD COLUMN `pic` varchar(150) DEFAULT NULL COMMENT '图片标签';");
    }
}
if(getcustom('commission_to_money_rate')){
    if(!pdo_fieldexists2('ddwx_admin_set','commission_to_money_rate')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`
    ADD COLUMN `commission_to_money_rate` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '佣金转余额费率';");
    }
}
if(getcustom('commission_zhitui_special_first_order')){
    if(!pdo_fieldexists2('ddwx_member_level','commission_zhitui_special_ratio')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level`
    ADD COLUMN `commission_zhitui_special_ratio` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '直推特殊奖比例%';");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','commission_zhitui_special_first_order')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `commission_zhitui_special_first_order` tinyint(1) NOT NULL DEFAULT '0' COMMENT '直推特殊奖 0关闭  1开启';
        ");
    }
}
if(getcustom('commission_shengdai_special')){
    if(!pdo_fieldexists2('ddwx_member_level','shengdai_special_commission1')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level`
    ADD COLUMN `shengdai_special_commission1` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '一级省代特殊奖',
    ADD COLUMN `shengdai_special_commission2` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '二级省代特殊奖',
    ADD COLUMN `shengdai_special_commission3` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '三级省代特殊奖';");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','commission_shengdai_special')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `commission_shengdai_special` tinyint(1) NOT NULL DEFAULT '0' COMMENT '省代特殊奖 0关闭 1开启';
        ");
    }
}

if(getcustom('sxpay_mendian_info')) {
    if (!pdo_fieldexists2("ddwx_admin_setapp_wx", "sxpay_deviceNo")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_wx` 
            ADD COLUMN `sxpay_deviceNo` varchar(60) NULL COMMENT '天阙终端编号';");
    }
    if (!pdo_fieldexists2("ddwx_admin_setapp_mp", "sxpay_deviceNo")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_mp` 
            ADD COLUMN `sxpay_deviceNo` varchar(60) NULL COMMENT '天阙终端编号';");
    }
    if (!pdo_fieldexists2("ddwx_admin_setapp_cashdesk", "sxpay_deviceNo")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_cashdesk` 
            ADD COLUMN `sxpay_deviceNo` varchar(60) NULL COMMENT '天阙终端编号';");
    }
    if (!pdo_fieldexists2("ddwx_admin_setapp_restaurant_cashdesk", "sxpay_deviceNo")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_restaurant_cashdesk` 
            ADD COLUMN `sxpay_deviceNo` varchar(60) NULL COMMENT '天阙终端编号';");
    }
    if (!pdo_fieldexists2("ddwx_business", "sxpay_storeNum")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `sxpay_storeNum` varchar(60) NULL COMMENT '天阙门店编号',
            ADD COLUMN `sxpay_deviceNo` varchar(60) NULL COMMENT '天阙终端编号';");
    }
}
if(getcustom('score_stacking_give_set')){
    if(!pdo_fieldexists2('ddwx_admin_set','score_stacking_give_set')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`
        ADD COLUMN `score_stacking_give_set` tinyint(1) NOT NULL DEFAULT '0' COMMENT '叠加赠送积分 0消费和购买商城商品叠加赠送  1只消费赠送  2只购买商城商品赠送';");
    }
}
if(getcustom('business_score_duli_set')){
    if(!pdo_fieldexists2('ddwx_business','scorein_money')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business`
        ADD COLUMN `scorein_money` decimal(11,2) DEFAULT NULL COMMENT '赠送积分消费每满多少元  设置为空时，则使用平台设置，设置为0则是不送积分',
        ADD COLUMN `scorein_score` int(11) DEFAULT NULL COMMENT '赠送多少积分',
        ADD COLUMN `scorecz_money` decimal(11,2) DEFAULT NULL COMMENT '赠送积分充值每满多少元  设置为空时，则使用平台设置，设置为0则是不送积分',
        ADD COLUMN `scorecz_score` int(11) DEFAULT NULL COMMENT '赠送多少积分';");
    }
}
if(getcustom('form_hxqrcode')) {
    if (!pdo_fieldexists2("ddwx_form", "hxqrcode_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_form` ADD COLUMN `hxqrcode_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '核销码开关';");
    }
    if (!pdo_fieldexists2("ddwx_form_order", "hexiao_code")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_form_order` 
    ADD COLUMN `hexiao_code` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
    ADD COLUMN `hexiao_qr` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
    ADD COLUMN `hexiao_status` tinyint(1) DEFAULT '0' COMMENT '核销状态，0未核销，1已核销',
    ADD COLUMN `hexiao_time` int(11) DEFAULT NULL,
    ADD COLUMN `remark` varchar(60) DEFAULT NULL,
    ADD INDEX `hexiao_code`(`hexiao_code`);");
    }
}
if(getcustom('member_level_set_invite_levelid')){
    if (!pdo_fieldexists2("ddwx_member_level", "invite_levelid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `invite_levelid` int(11) NOT NULL DEFAULT '0' COMMENT '指定下级等级';");
    }
}
if(getcustom('commission_withdraw_score_conversion')){
    if(!pdo_fieldexists2('ddwx_admin_set','commission_conversion')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `commission_conversion` decimal(11,2) DEFAULT '0.00' COMMENT '佣金到账比例',
            ADD COLUMN `score_conversion` decimal(11,2) DEFAULT '0.00' COMMENT '积分到账比例',
            ADD COLUMN `score_doubling` int(11) DEFAULT '0' COMMENT '积分到账倍数';");
    }
    if(!pdo_fieldexists2('ddwx_member_commission_withdrawlog','conversion_score')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_withdrawlog` 
            ADD COLUMN `conversion_score` decimal(11,2) DEFAULT '0.00' COMMENT '佣金提现部分转到积分';");
    }
}
if(getcustom('commission_withdraw_buy_product')){
    if(!pdo_fieldexists2('ddwx_admin_set','withdraw_buy_proid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `withdraw_buy_proid` varchar(100) DEFAULT NULL COMMENT '购买商品ID',
            ADD COLUMN `withdraw_buy_pro_num` varchar(100) DEFAULT NULL COMMENT '购买商品数量';");
    }
}

if(getcustom('mobile_admin_qrcode_variable_maidan')){
    if (!pdo_fieldexists2("ddwx_qrcode_list_variable", "ismaidan")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_list_variable` ADD COLUMN `ismaidan` tinyint(1) NULL DEFAULT 0 COMMENT '是否绑定为收款码 0：否 1：是';");
    }
}

if(getcustom('register_fields')){
    if (!pdo_fieldexists2("ddwx_register_form", "savetype")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_register_form`
        ADD COLUMN `savetype` tinyint(1) NULL DEFAULT 1 COMMENT '1：下单时保存表单 2：跟随系统表单';");
    }
}
if(getcustom('team_tel_hide_middle_four')){
    if(!pdo_fieldexists2('ddwx_member_level','team_tel_hide_middle_four')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level`
        ADD COLUMN `team_tel_hide_middle_four` tinyint(1) NOT NULL DEFAULT '0' COMMENT '隐藏会员电话号码中间4位  0关闭 1开启';");
    }
}
if(getcustom('hexiao_search_mendian_product')){
    if(!pdo_fieldexists2('ddwx_hexiao_order','delivery_remark')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_hexiao_order`
        ADD COLUMN `delivery_remark` varchar(200) DEFAULT '' COMMENT '配送货备注',
        ADD COLUMN `delivery_time` int(11) DEFAULT NULL COMMENT '配送货时间';");
    }
}
if(getcustom('hexiao_shopproduct_search_hxtime')){
    if(!pdo_fieldexists2('ddwx_hexiao_shopproduct','delivery_remark')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_hexiao_shopproduct`
        ADD COLUMN `delivery_remark` varchar(200) DEFAULT '' COMMENT '配送货备注',
        ADD COLUMN `delivery_time` int(11) DEFAULT NULL COMMENT '配送货时间';");
    }
}
if(getcustom('mendian_money_transfer')){
    if(!pdo_fieldexists2('ddwx_admin_set','mendian_money_transfer')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`
        ADD COLUMN `mendian_money_transfer` tinyint(1) NOT NULL DEFAULT '0' COMMENT '门店余额转账给用户 0关闭 1开启';");
    }
}
if(getcustom('freight_upload_pics') || getcustom('yuyue_form_upload_pics')){
    if(pdo_fieldexists2('ddwx_freight_formdata','form0')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form0` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form1')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form1` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form2')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form2` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form3')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form3` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form4')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form4` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form5')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form5` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form6')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form6` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form7')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form7` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form8')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form8` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form9')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form9` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form10')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form10` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form11')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form11` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form12')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form12` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form13')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form13` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form14')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form14` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form15')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form15` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form16')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form16` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form17')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form17` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form18')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form18` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form19')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form19` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form20')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form20` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form21')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form21` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form22')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form22` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form23')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form23` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form24')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form24` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form25')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form25` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form26')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form26` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form27')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form27` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form28')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form28` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form29')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form29` text CHARACTER SET utf8;");
    }
    if(pdo_fieldexists2('ddwx_freight_formdata','form30')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_freight_formdata` 
        MODIFY COLUMN `form30` text CHARACTER SET utf8;");
    }
}

if(getcustom('member_dedamount')){
    if(!pdo_fieldexists2('ddwx_register_giveset','dedamount')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_register_giveset` ADD COLUMN `dedamount` decimal(11, 2) NULL DEFAULT 0.00 COMMENT '抵扣金';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `dedamount` decimal(11, 2) NULL DEFAULT 0 COMMENT '抵扣金';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `paymoney_givepercent` decimal(10, 2) NULL DEFAULT 0 COMMENT '让利比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`
            ADD COLUMN `dedamount_type` tinyint(1) NULL DEFAULT 0 COMMENT '抵扣金消费赠送类型 0：全部 1：仅平台消费 2：仅商户消费',
            ADD COLUMN `dedamount_fullmoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '消费赠抵扣金 消费每满金额',
            ADD COLUMN `dedamount_givemoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '消费赠抵扣金 赠送金额',
            ADD COLUMN `dedamount_dkpercent` decimal(10, 2) NULL DEFAULT 0 COMMENT '抵扣金 下单抵扣比例%';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `paymoney_givemoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '商家让利部分金额',
            ADD COLUMN `dedamount_dkmoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '抵扣金抵扣金额';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_dedamountlog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `bid` int(11) NULL DEFAULT 0,
        `pid` int(11) NULL DEFAULT 0 COMMENT '减少来自哪个记录',
        `mid` int(11) NULL DEFAULT 0,
        `orderid` int(11) NULL DEFAULT 0,
        `ordernum` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
        `dedamount` decimal(11, 2) NULL DEFAULT 0.00 COMMENT '固定抵扣金',
        `dedamount2` decimal(11, 2) NULL DEFAULT 0.00 COMMENT '变动抵扣金',
        `after` decimal(11, 2) NULL DEFAULT 0.00,
        `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
        `from_mid` int(11) NULL DEFAULT 0,
        `paytype` varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
        `uid` int(11) NULL DEFAULT 0,
        `createtime` int(11) NULL DEFAULT 0,
        `updatetime` int(11) NULL DEFAULT 0,
        `type` tinyint(1) NULL DEFAULT 1 COMMENT '类型 1：增加 2：减少',
        `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '支付状态 0：未支付 1：已支付',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `pid`(`pid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='会员抵扣金记录表';");

    if(!pdo_fieldexists2('ddwx_shop_order_goods','paymoney_givemoney')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD COLUMN `paymoney_givemoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '商家让利部分金额',
            ADD COLUMN `dedamount_dkmoney` decimal(10, 2) NULL DEFAULT 0 COMMENT '抵扣金抵扣金额';");
    }

    if(!pdo_fieldexists2('ddwx_shop_order','paymoney_givepercent')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `paymoney_givepercent` decimal(10, 2) NULL DEFAULT 0 COMMENT '商家让利比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `paymoney_givepercent` decimal(10, 2) NULL DEFAULT 0 COMMENT '商家让利比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` 
            ADD COLUMN `paymoney_givemoney` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '商家让利部分金额',
            ADD COLUMN `dedamount_dkmoney` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '抵扣金抵扣金额',
            ADD COLUMN `paymoney_givepercent` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '商家让利比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_dedamountlog` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '支付状态 0：未支付 1：已支付';");
    }

    if(!pdo_fieldexists2('ddwx_shop_order','dedamount_dkpercent')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `dedamount_dkpercent` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '抵扣金抵扣比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `dedamount_dkpercent` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '抵扣金抵扣比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `dedamount_dkpercent` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '抵扣金抵扣比例';");
    }

    if(!pdo_fieldexists2('ddwx_business','paymoney_givepercent2')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `paymoney_givepercent2` decimal(10, 2) NULL DEFAULT -1 COMMENT '商家修改的让利比例';");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','dedamount_type2')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `dedamount_type2` tinyint(1) NULL DEFAULT 0 COMMENT '抵扣金消费赠送类型二 0：全部 1：仅商城订单 2：仅买单订单';");
    }
}
if(getcustom('member_level_breedcommission')){
    if(!pdo_fieldexists2('ddwx_member_level','breedcommission')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `breedcommission` decimal(10, 2) NULL DEFAULT 0 COMMENT '培育金';");
    }
}
if(getcustom('member_level_paymoney_commissionfrozenset')){
    if(!pdo_fieldexists2('ddwx_member_level','apply_paymoney_commission1')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` 
            ADD COLUMN `apply_paymoney_commission1` decimal(10, 2) NULL DEFAULT 0 COMMENT '升级费用分销 直推佣金%',
            ADD COLUMN `apply_paymoney_commission1_frozenpercent` decimal(10, 2) NULL DEFAULT 0 COMMENT '升级费用分销 直推佣金冻结比例(%)';");
    }
}
if(getcustom('extend_qrcode_variable_maidan_bindsound')){
    if(!pdo_fieldexists2('ddwx_qrcode_list_variable','soundid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_list_variable` ADD COLUMN `soundid` int NULL DEFAULT 0 COMMENT '云音响id';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `soundid` int NULL DEFAULT 0 COMMENT '云音响id';");
    }
}
if(getcustom('sound_yuzhi')){
    if(!pdo_fieldexists2('ddwx_sound','yuzhi_iotInstanceId')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_sound` 
            ADD COLUMN `yuzhi_iotInstanceId` varchar(50) NULL DEFAULT '' COMMENT '语智iotInstanceId',
            ADD COLUMN `yuzhi_accessKeyId` varchar(50) NULL DEFAULT '' COMMENT '语智accessKeyId',
            ADD COLUMN `yuzhi_accessSecret` varchar(50) NULL DEFAULT '' COMMENT '语智accessSecret',
            ADD COLUMN `yuzhi_productKey` varchar(50) NULL DEFAULT '' COMMENT '语智productKey',
            ADD COLUMN `yuzhi_deviceName` varchar(50) NULL DEFAULT '' COMMENT '语智deviceName',
            ADD COLUMN `yuzhi_regionId` varchar(50) NULL DEFAULT '' COMMENT '语智regionId',
            ADD COLUMN `yuzhi_des3` text NULL COMMENT '语智3DES密钥';");
    }
}
if(getcustom('restaurant_product_guige_hide')){
    if(!pdo_fieldexists2('ddwx_restaurant_product_guige','show_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_product_guige` 
          ADD COLUMN  `show_status` tinyint(1) DEFAULT '0' COMMENT '禁选状态 0否1是';");
    }
}
if(getcustom('yx_queue_free_business_fanli_range')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','rate_back_min')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
        ADD COLUMN	`rate_back_min` decimal(11,2) DEFAULT '0.00' COMMENT '最小返利比例',
        ADD COLUMN `rate_back_max` decimal(11,2) DEFAULT '100.00' COMMENT '最大返利比例';");
    }
    if(!pdo_fieldexists2('ddwx_queue_free_set','rate_back_status_business')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
        ADD COLUMN `rate_back_status_business` tinyint(1) DEFAULT '0' COMMENT '商户修改返利比例开关';");
    }
}
if(getcustom('yx_queue_free_business_fanli_range') || getcustom('business_apply_queue_free_rate_back')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','rate_back_status_business')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
        ADD COLUMN `rate_back_status_business` tinyint(1) DEFAULT '0' COMMENT '商户修改返利比例开关';");
    }
}
if(getcustom('yx_queue_free_platform_choucheng_fanli')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','queue_free_commission_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
        ADD COLUMN	`queue_free_commission_type` tinyint(1) DEFAULT '0' COMMENT '佣金比例类型0:手动设置 1平台抽成比例';");
    }
}
if(getcustom('yx_buy_product_manren_choujiang')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_manren_choujiang`  (
      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `bid` int(11) NULL DEFAULT NULL,
      `title` varchar(255) NULL DEFAULT NULL COMMENT '活动名称',
      `proid` varchar(255) DEFAULT '' COMMENT '商品ID',
      `canyu_type` tinyint(1) NULL DEFAULT 1 COMMENT '参与类型 1：付款后 2：确认收货后',
      `opennum` int(11) NULL DEFAULT 0 COMMENT '开奖人数',
      `custom_text` varchar(255) NULL DEFAULT 0 COMMENT '自定义文字',
      `award` text NULL COMMENT '奖品',
      `text_color` varchar(30) NOT NULL DEFAULT '#000000' COMMENT '文字颜色',
      `text_bgcolor` varchar(30) NOT NULL DEFAULT '#FFFFFF' COMMENT '文字背景色',
      `cycles` int(11) NULL DEFAULT 1 COMMENT '轮数',
      `guize` text COMMENT '活动规则',
      `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 1：开启 0：关闭',
      `createtime` int(11) NULL DEFAULT NULL COMMENT '创建时间',
      PRIMARY KEY (`id`) USING BTREE
    ) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_manren_choujiang_record` (
        `id` int(11) NOT NULL AUTO_INCREMENT ,
        `aid` int(11) NULL DEFAULT NULL,
        `bid` int(11) NULL DEFAULT NULL,
        `mid` int(11) NULL DEFAULT NULL,
        `hid` int(11) NULL DEFAULT NULL COMMENT '抽奖活动ID',
        `title` varchar(255) NULL DEFAULT NULL COMMENT '活动名称',
        `proid` int(11) NULL DEFAULT NULL COMMENT '商品ID',
        `proname` varchar(255) DEFAULT NULL COMMENT '商品名称',
        `orderid` int(11) NULL DEFAULT NULL COMMENT '订单ID',
        `cycles` int(11) NOT NULL DEFAULT 1 COMMENT '参与轮数',
        `status` tinyint(1) NULL DEFAULT 0 COMMENT '0: 未开奖, 1: 已开奖且未中奖, 2: 已开奖且已中奖',
        `createtime` int(11) NULL DEFAULT NULL COMMENT '参与时间',
        PRIMARY KEY (`id`),
        INDEX `aid` (`aid`) USING BTREE ,
        INDEX `bid` (`bid`) USING BTREE ,
        INDEX `mid` (`mid`) USING BTREE 
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");


    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_manren_choujiang_cycles` (
        `id` int(11) NOT NULL AUTO_INCREMENT ,
        `aid` int(11) NULL DEFAULT NULL,
        `bid` int(11) NULL DEFAULT NULL,
        `mid` int(11) NULL DEFAULT NULL COMMENT '中奖会员ID',
        `rid` int(11) NULL DEFAULT NULL COMMENT '中奖记录ID',
        `hid` int(11) NULL DEFAULT NULL COMMENT '抽奖活动ID',
        `cycles` int(11) NULL DEFAULT NULL COMMENT '轮数',
        `proid` varchar(255) DEFAULT '' COMMENT '商品ID',
        `award` text NULL COMMENT '奖品',
        `createtime` int(11) NULL DEFAULT NULL COMMENT '开奖时间',
        PRIMARY KEY (`id`),
        INDEX `aid` (`aid`) USING BTREE ,
        INDEX `bid` (`bid`) USING BTREE ,
        INDEX `hid` (`hid`) USING BTREE ,
        INDEX `mid` (`mid`) USING BTREE 
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=Dynamic;");

    if(pdo_fieldexists2('ddwx_manren_choujiang','canyu_type')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_manren_choujiang` 
        MODIFY COLUMN `canyu_type` tinyint(1) NULL DEFAULT 1 COMMENT '参与方式 1：共同参与 2：独立参与';");
    }

    if(!pdo_fieldexists2('ddwx_manren_choujiang','pic')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_manren_choujiang` 
        ADD COLUMN `pic` varchar(255) DEFAULT NULL COMMENT '活动图片',
        ADD COLUMN `canyu_choujiang` tinyint(1) NULL DEFAULT 1 COMMENT '参与抽奖 1：付款后 2：确认收货后' AFTER `canyu_type`,
        ADD COLUMN `bgpic` varchar(255) DEFAULT NULL COMMENT '活动背景图';");
    }

    if(!pdo_fieldexists2('ddwx_mp_tmplset_new','tmpl_winning_notice')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` 
        ADD COLUMN `tmpl_winning_notice` varchar(64) NULL DEFAULT '' COMMENT '开奖通知';");
    }

    if(!pdo_fieldexists2('ddwx_manren_choujiang_record','cyid')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_manren_choujiang_record` 
        ADD COLUMN `cyid` int(11) NULL DEFAULT NULL COMMENT '期数ID';");
    }

    if(!pdo_fieldexists2('ddwx_manren_choujiang_cycles','status')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_manren_choujiang_cycles` 
        ADD COLUMN `status` tinyint(1) NULL DEFAULT 0 COMMENT '开奖状态 0：待开奖 1：已开奖',
        ADD COLUMN `opentime` int(11) NULL DEFAULT NULL COMMENT '开奖时间';");
    }
}
if(getcustom('luckycollage_scoredk')) {
    if(!pdo_fieldexists2('ddwx_lucky_collage_product','scoredkmaxset')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` 
        ADD COLUMN `scoredkmaxset` tinyint(1) default '0' COMMENT '积分抵扣设置 0按照系统设置抵扣 1单独设置抵扣比例 2单独设置抵扣金额 -1不可用积分抵扣',
        ADD COLUMN `scoredkmaxval` decimal(11, 2) default '0.00' COMMENT '积分抵扣时最多抵扣多少';");
    }
}
if(getcustom('levelup_changepid_yeji')){
    if(!pdo_fieldexists2('ddwx_member_level','levelup_changepid_yeji_con')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `levelup_changepid_yeji_con`  varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '链动脱离人员订单金额条件 and or';");
    }
    if(!pdo_fieldexists2('ddwx_member_level','levelup_changepid_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `levelup_changepid_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '链动脱离人员订单金额';");
    }
}
if(getcustom('yx_cashback_log') || getcustom('yx_cashback_multiply')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_cashback_log` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  int(11) NULL DEFAULT NULL ,
        `cashback_id`  int(11) NULL DEFAULT NULL ,
        `og_id`  int(11) NULL DEFAULT NULL ,
        `back_price`  decimal(12,2) NULL DEFAULT NULL ,
        `after`  decimal(12,2) NULL DEFAULT NULL ,
        `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `createtime`  int(10) NULL DEFAULT NULL ,
        PRIMARY KEY (`id`)
        )  ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
    if(!pdo_fieldexists2('ddwx_member','cashback_price')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `cashback_price`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '购物返现待返现总额';");
    }
    if(!pdo_fieldexists2('ddwx_cashback_log','return_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_log` ADD COLUMN `return_type` tinyint(1) not null DEFAULT '0' COMMENT '返还类型 0、立即返还 1、自定义 2、阶梯返还 3倍增返回';");
    }
}
if(getcustom('teamfenhong_pingji_origin')){
    if(!pdo_fieldexists2('ddwx_member_level','teamfenhong_pingji_origin')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `teamfenhong_pingji_origin`  tinyint(1) NULL DEFAULT 0 COMMENT '团队分红平级奖发放给原上级 0否 1是';");
    }
}
if(getcustom('change_down_user')){
    if(!pdo_fieldexists2('ddwx_member_pid_changelog','change_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_pid_changelog` ADD COLUMN `change_status`  tinyint(1) NULL DEFAULT 0 COMMENT '是否已经换位过 0无 1是';");
    }
    if(!pdo_fieldexists2('ddwx_member_level','change_down_user')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `change_down_user`  tinyint(1) NULL DEFAULT 0 COMMENT '开启链动换位 0关闭 1开启';");
    }
}
if(getcustom('green_score_new')){
    if(!pdo_fieldexists2('ddwx_consumer_set','max_green_score')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `max_green_score`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '平台最大绿色积分数量';");
    }
    if(!pdo_fieldexists2('ddwx_consumer_set','auto_release_day')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `auto_release_day`  int(11) NULL DEFAULT 0 COMMENT '自动释放天数';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_green_score_release` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  int(11) NULL DEFAULT NULL ,
        `dec_score`  decimal(12,2) NULL DEFAULT 0.00 ,
        `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `createtime`  int(10) NULL DEFAULT 0 ,
        `green_score_value`  decimal(12,2) NULL DEFAULT 0.00 ,
        `to_commission`  decimal(12,2) NULL DEFAULT 0.00 ,
        `to_money`  decimal(12,2) NULL DEFAULT 0.00 ,
        `to_score`  decimal(12,2) NULL DEFAULT 0.00 ,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
    if(!pdo_fieldexists2('ddwx_consumer_set','show_top')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_consumer_set` ADD COLUMN `show_top`  tinyint(1) NULL DEFAULT 0 COMMENT '前台展示最大份额 0关闭 1显示';");
    }
}

if(getcustom('reg_invite_code_business')){
    if(!pdo_fieldexists2('ddwx_business','maidan_reg_invite_code')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `maidan_reg_invite_code` varchar(30) NULL DEFAULT '' COMMENT '买单邀请码' AFTER `maidan_payaftertourl`;");
    }
}
if(getcustom('pay_huifu_fenzhang') && getcustom('pay_huifu')){
    if(!pdo_fieldexists2('ddwx_business','huifu_business_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `huifu_business_status` tinyint(1) DEFAULT '0' COMMENT '汇付独立收款',
            ADD COLUMN `huifu_id` varchar(60) DEFAULT NULL COMMENT '商户号ID';");
    }
    if(!pdo_fieldexists2('ddwx_huifu_log','fenzhangdata')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_huifu_log` ADD COLUMN `fenzhangdata` text COMMENT '分账接收方';");
    }
    if(!pdo_fieldexists2('ddwx_member_commission_record','to_fenzhang')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_commission_record` 
            ADD COLUMN `to_fenzhang` tinyint(1) DEFAULT '0' COMMENT '走分账 0：否 1：是';");
    }
}
if(getcustom('restaurant_cuxiao_not_yuepay')){
    if(!pdo_fieldexists2('ddwx_restaurant_cuxiao','is_use_yuepay')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cuxiao` ADD COLUMN `is_use_yuepay` tinyint(1) DEFAULT '1' COMMENT '使用余额支付1开启0禁用';");
    }
}
if(getcustom('jushuitan')){
    if(!pdo_fieldexists2("ddwx_shop_order","jsto_id")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `jsto_id` int(11) DEFAULT 0 COMMENT '聚水潭返回的id ,用于取消订单';");
    }
}
if(getcustom('restaurant_cashdesk_print_merge')){
    if(!pdo_fieldexists2('ddwx_restaurant_cashdesk','merge_print_piao')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cashdesk` 
            ADD COLUMN `merge_print_piao` tinyint(1) DEFAULT '0' COMMENT '打印时合并数量1开启0关闭';");
    }
}

if(getcustom('levelup_team_yeji_front')){
    if(!pdo_fieldexists2('ddwx_member_level','levelup_team_yeji_front_con')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `levelup_team_yeji_front_con` varchar(10) DEFAULT '' COMMENT '业绩升级条件';");
    }
    if(!pdo_fieldexists2('ddwx_member_level','levelup_team_yeji_front_day')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `levelup_team_yeji_front_day` int(11) DEFAULT '0' COMMENT '升级业绩统计天数';");
    }
    if(!pdo_fieldexists2('ddwx_member_level','levelup_team_yeji_front_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `levelup_team_yeji_front_yeji` decimal(12,2) DEFAULT '0.00' COMMENT '升级业绩数量';");
    }
}
if(getcustom('restaurant_cashdesk_ordergoods_change_table')){
    if(!pdo_fieldexists2('ddwx_restaurant_shop_order_goods','from_ogid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` 
            ADD COLUMN `from_ogid` int(11) DEFAULT '0' COMMENT '来源order_goods，转台',
            ADD COLUMN `from_orderid` int(11) DEFAULT '0' COMMENT '来源order，转台',
            ADD COLUMN `from_num` int(11) DEFAULT '0' COMMENT '来源菜品数量，转台';");
    }
}
if(getcustom('team_show_down_order')){
    if(!pdo_fieldexists2('ddwx_member_level','team_show_down_order')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level`
        ADD COLUMN `team_show_down_order` tinyint(1) NOT NULL DEFAULT '0' COMMENT '查看下级推广订单数  0关闭 1开启';");
    }
}
if(getcustom('score_transfer') || getcustom('score_friend_transfer')){
    if(!pdo_fieldexists2('ddwx_admin_set','score_transfer_sxf_ratio')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `score_transfer_sxf_ratio` float(11,2) DEFAULT '0.00';");
    }
}
if(getcustom('restaurant_cashdesk_table_merge_pay')){
    if(!pdo_fieldexists2('ddwx_restaurant_shop_order','merge_ordernum')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` 
            ADD COLUMN `merge_ordernum` varchar(100) DEFAULT NULL COMMENT '合并订单订单号';");
    }
    if(!pdo_fieldexists2('ddwx_payorder','merge_ordernum')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` 
            ADD COLUMN `merge_ordernum` varchar(100) DEFAULT NULL COMMENT '合并桌台付款的 合并订单号',
            ADD COLUMN `merge_totalprice` decimal(11,2) DEFAULT '0.00';");
    }
}

if(getcustom('up_giveparent_help')){
    if(!pdo_fieldexists2('ddwx_admin_set','help_con_day')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `help_con_day`  int(11) NULL DEFAULT 0 COMMENT '脱离帮扶检测天数';");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','help_con_commission')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `help_con_commission`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '脱离帮扶检测佣金';");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','help_member_num')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `help_member_num`  int(11) NULL DEFAULT 0 COMMENT '脱离帮扶人数';");
    }
    if(!pdo_fieldexists2('ddwx_member_pid_changelog','is_help')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_pid_changelog` ADD COLUMN `is_help`  tinyint(1) NULL DEFAULT 0 COMMENT '是帮扶划拨人员 0否 1是 ';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_up_giveparent_log` (
        `id`  int(11) NOT NULL AUTO_INCREMENT ,
        `aid`  int(11) NULL DEFAULT NULL ,
        `mid`  int(11) NULL DEFAULT 0 COMMENT '会员id' ,
        `pid`  int(11) NULL DEFAULT 0 COMMENT '帮扶的上级会员id' ,
        `give_mids`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '0' COMMENT '脱离给上级的会员id' ,
        `createtime`  int(10) NULL DEFAULT 0 COMMENT '脱离时间' ,
        `status`  tinyint(1) NULL DEFAULT 0 COMMENT '0未检测 1已检测' ,
        `remark`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL ,
        `help_mids`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '未达到条件继续划拨给上级的会员' ,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=Dynamic;");
}
if(getcustom('yx_cashback_pid')){
    if(!pdo_fieldexists2('ddwx_cashback','cashback_pid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `cashback_pid`  tinyint(1) NULL DEFAULT 0 COMMENT '0返现给自己 1返现给上级';");
    }
    if(!pdo_fieldexists2('ddwx_cashback_member','order_mid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member` ADD COLUMN `order_mid`  int(11) NULL DEFAULT 0 COMMENT '下单人会员id';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','order_mid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `order_mid`  int(11) NULL DEFAULT 0 COMMENT '下单人会员id';");
    }

}
if(getcustom('restaurant_cashdesk_reverse_pay')){
    if(!pdo_fieldexists2('ddwx_restaurant_shop_order','reverse_orderid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order` 
            ADD COLUMN `reverse_orderid` int(11) DEFAULT '0' COMMENT '反向结账的关联订单ID';");
    }
}
if(getcustom('yx_queue_free_member_day')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','member_day_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN `member_day_type` tinyint(1) DEFAULT '0' COMMENT '会员日类型',
            ADD COLUMN `member_day_week` varchar(255) DEFAULT NULL  COMMENT '按周设置',
            ADD COLUMN `member_day_days` varchar(255) DEFAULT NULL  COMMENT '按日设置',
            ADD COLUMN `member_day_rate_back` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT '会员日返利比例';");
    }
}
if(getcustom('business_pc_yeji_ranking')){
    if(!pdo_fieldexists2('ddwx_business','yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
            ADD COLUMN `yeji` decimal(11,2) DEFAULT '0.00' COMMENT '业绩';");
    }
}
if(getcustom('yx_queue_free_quit_give_coupon')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','quit_give_coupon')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN quit_give_coupon_st tinyint(1) DEFAULT '0' COMMENT '转换优惠券：0关闭 1开启',
            ADD COLUMN quit_give_coupon int(11) DEFAULT '0' COMMENT '退出转为优惠券',
            ADD COLUMN quit_give_coupon_days int(11) DEFAULT '0' COMMENT '返利金额转为优惠券的天数';");
    }
    if(!pdo_fieldexists2('ddwx_queue_free','is_quit_coupon')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free` 
            ADD COLUMN `is_quit_coupon` tinyint(1) unsigned DEFAULT '0' COMMENT '退出赠送优惠券';");
    }
}
if(getcustom('transfer_order_parent_check')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_transfer_order_parent_check_tongji` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `mid` int(11) NOT NULL DEFAULT '0',
      `levelid` int(11) NOT NULL DEFAULT '0',
      `total_order_num` int(11) unsigned NOT NULL COMMENT '订单总量（下单就算无需支付，自己包括自己团队）',
      `sales_amount` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '销售金额（订单支付之后就进行累加，自己包括自己团队)',
      `effective_amount` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '有效金额（订单确认收货后进行累加，自己包括自己团队)',
      `submission_amount` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '上交金额（就是自己这个等级购买这个订单商品多少钱）\n',
      `differential` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '差价（自己购买和普通售价的差价）',
      `xia_differential` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '下级差价（下级购买和自己的差价）',
      `creattime` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      UNIQUE KEY `mid` (`mid`) USING BTREE,
      KEY `aid` (`aid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='分销数据统计汇总';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_transfer_order_parent_check_tongji_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL DEFAULT '0',
      `orderid` int(11) NOT NULL DEFAULT '0',
      `mid` int(11) NOT NULL DEFAULT '0' COMMENT '订单所属人id',
      `tomid` int(11) NOT NULL DEFAULT '0' COMMENT '此订单数据给哪个用户增加过',
      `total_order_num` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '此订单增加的单量',
      `sales_amount` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '此订单增加的销售金额',
      `effective_amount` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '此订单增加的有效金额',
      `submission_amount` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '此订单增加的上交金额',
      `differential` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '此订单增加的自己的差价',
      `xia_differential` decimal(11,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '此订单增加的下级和自己的差价',
      `order_paytime` int(11) NOT NULL DEFAULT '0' COMMENT '订单支付时间',
      `order_createtime` int(11) NOT NULL DEFAULT '0' COMMENT '订单下单时间',
      `createtime` int(11) NOT NULL DEFAULT '0',
      `is_add` tinyint(1) NOT NULL DEFAULT '1' COMMENT '此条数据是否给主表增加了数据，0是已剪掉',
      `transfer_order_parent_check_tongji_id` int(11) NOT NULL DEFAULT '0' COMMENT '主表id',
      `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1单量  2销售金额  3有效金额  4上交金额  5自己购买的差价  6下级购买和自己的差价',
      `is_up` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是升级操作 1是 0否',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`orderid`,`mid`,`tomid`),
      KEY `is_add` (`is_add`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='分销数据统计汇总详情';");
}
if(getcustom('pay_chinaums')){
    if(!pdo_fieldexists2('ddwx_admin_setapp_wx','ysf_appid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_wx`
        ADD COLUMN `ysf_appid` varchar(64) NULL DEFAULT '' COMMENT 'appId',
        ADD COLUMN `ysf_appkey` varchar(64) NULL DEFAULT '' COMMENT 'appKey',
        ADD COLUMN `ysf_sub_mchid` varchar(30) NULL DEFAULT '' COMMENT '商户号',
        ADD COLUMN `ysf_terminalid` varchar(30) NULL DEFAULT '' COMMENT '终端号',
        ADD COLUMN `ysf_source` varchar(30) NULL DEFAULT '' COMMENT '接入来源',
        ADD COLUMN `ysf_secretkey` varchar(64) NULL DEFAULT '' COMMENT '通讯密钥',
        ADD COLUMN `ysf_jump_appid` varchar(64) NULL DEFAULT '' COMMENT '云闪付小程序的appid',
        ADD COLUMN `ysf_jump_path` varchar(64) NULL DEFAULT '' COMMENT '云闪付小程序支付路径';");
    }

    if(!pdo_fieldexists2('ddwx_admin_setapp_wx','ysf_pay')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_wx`
        ADD COLUMN `ysf_pay` tinyint(1) NOT NULL DEFAULT '1' COMMENT '云闪付支付 0:关闭 1:开启' AFTER `ysf_appid`;");
    }
}
if(getcustom('shop_product_jialiao')){
    if(!pdo_fieldexists2('ddwx_shop_product','jl_title')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `jl_title`  varchar(30) DEFAULT '加料' COMMENT '加料名称',
            ADD COLUMN `jl_total_max`  int(11) DEFAULT '0' COMMENT '加料最大选择数量',
            ADD COLUMN `jl_total_min`  int(11) DEFAULT '0' COMMENT '加料最小选择数量';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_product_jialiao` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `proid` int(11) DEFAULT '0',
      `title` varchar(255) DEFAULT NULL,
      `price` decimal(10,2) DEFAULT NULL,
      `limit_num` int(11) DEFAULT NULL,
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
    if(!pdo_fieldexists2('ddwx_shop_cart','jldata')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_cart` 
            ADD COLUMN `jldata` text;");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods','jlprice')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD COLUMN `jlprice` decimal(10,2) DEFAULT '0.00',
            ADD COLUMN `jltitle` varchar(30) DEFAULT NULL;");
    }
}
if(getcustom('yuyue_form_save_draft')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_yuyue_form_draft` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT '0',
      `proid` int(11) DEFAULT '0',
      `ggid` int(11) DEFAULT '0',
      `formdata` text CHARACTER SET utf8,
      `createtime` int(11) DEFAULT '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
}
if(getcustom('wxpay_native_h5')){
    if(!pdo_fieldexists2('ddwx_payorder','wxpay_typeid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` 
            ADD COLUMN `wxpay_typeid` int(11) NOT NULL DEFAULT '0' COMMENT '微信支付模式  7微信收款码';");
    }
    if(!pdo_fieldexists2('ddwx_admin_setapp_h5','wx_native_h5')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_h5` 
            ADD COLUMN `wx_native_h5` tinyint(1) NOT NULL DEFAULT '0' COMMENT '微信付款码 0关闭 1开启';");
    }
    if(!pdo_fieldexists2('ddwx_admin_setapp_h5','wx_liji_pay')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_h5` 
            ADD COLUMN `wx_liji_pay` tinyint(1) NOT NULL DEFAULT '1' COMMENT '微信立即支付按钮 0关闭 1开启';");
    }
}
if(getcustom('yx_birthday_coupon')){
    if(!pdo_fieldexists2('ddwx_coupon','is_birthday_coupon')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` 
            ADD COLUMN `is_birthday_coupon` tinyint(1) DEFAULT '0' COMMENT '是否是生日券0关闭 1开启';");
    }
}
if(getcustom('yx_cashback_multiply')){
    if(!pdo_fieldexists2('ddwx_cashback','first_circle')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `first_circle`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '倍增：首期返现比例';");
    }
    if(!pdo_fieldexists2('ddwx_cashback','circle_add')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `circle_add`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '倍增：每期增长比例';");
    }
    if(!pdo_fieldexists2('ddwx_cashback','circle_max')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback` ADD COLUMN `circle_max`  int(11) NULL DEFAULT 0 COMMENT '倍增：最大返现期数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','totalprice')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `totalprice`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '消费总金额';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','first_circle')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `first_circle`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '首期返现比例';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','first_circle_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `first_circle_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '首期平台业绩';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','last_circle_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `last_circle_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '上期平台业绩';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','circle_add')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `circle_add`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '增值比例';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','circle_max')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `circle_max`  int(11) NULL DEFAULT 0 COMMENT '返现总期数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','back_price')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `back_price`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '返现总金额';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','send_circle')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `send_circle`  int(11) NULL DEFAULT 0 COMMENT '已返现期数';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `status`  tinyint(1) NULL DEFAULT 0 COMMENT '返现状态 0：未确认 1：返回中 2：返回完成';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','last_circle_send')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `last_circle_send`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '上期释放数量';");
    }
    if(!pdo_fieldexists2('ddwx_shop_order_goods_cashback','send_all')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods_cashback` ADD COLUMN `send_all`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '已发放金额';");
    }
    if(!pdo_fieldexists2('ddwx_cashback_log','circle_yeji')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_log` ADD COLUMN `circle_yeji`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '当期业绩';");
    }
    if(!pdo_fieldexists2('ddwx_cashback_log','send_circle')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_log` ADD COLUMN `send_circle`  int(11) NULL DEFAULT 0 COMMENT '发放周期';");
    }
}
if(getcustom('wxpay_b2b')){
    if(!pdo_fieldexists2('ddwx_admin_setapp_wx','wxpay_b2b_mchid')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_wx` 
            ADD COLUMN `wxpay_b2b_mchid` varchar(60) DEFAULT '' COMMENT 'b2b商户号',
            ADD COLUMN `wxpay_b2b_appkey` varchar(60) DEFAULT '' COMMENT '现网AppKey';");
    }
}
if(getcustom('member_area_agent_multi_price')){
    if(!pdo_fieldexists2('ddwx_member','areafenhong_province_commission')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `areafenhong_province_commission`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '省区域代理分红金额';");
    }
    if(!pdo_fieldexists2('ddwx_member','areafenhong_city_commission')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `areafenhong_city_commission`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '市区域代理分红金额';");
    }
    if(!pdo_fieldexists2('ddwx_member','areafenhong_area_commission')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `areafenhong_area_commission`  decimal(12,2) NULL DEFAULT 0.00 COMMENT '区县区域代理分红金额';");
    }
    if(!pdo_fieldexists2('ddwx_admin_set','areafenhong_differential_multi')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `areafenhong_differential_multi`  tinyint(1) NULL DEFAULT 0 COMMENT '多区域代理分红级差 0关闭 1开启';");
    }
}
if(getcustom('product_yeji_level')){
    if(!pdo_fieldexists2("ddwx_shop_product","yeji_level")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD `yeji_level` varchar(200) DEFAULT '-1' COMMENT '统计业绩等级 -1不限制';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order_goods","yeji")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD `yeji` decimal(12,2) NULL DEFAULT 0.00 COMMENT '有效业绩';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","yeji")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD `yeji` decimal(12,2) NULL DEFAULT 0.00 COMMENT '有效业绩';");
    }
}


if(getcustom('cps_jutuike_douyin')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_douyin_tuangou_category` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `cateid` varchar(20) DEFAULT '' COMMENT '分类ID',
      `first_cateid` varchar(20) DEFAULT '0' COMMENT '父级分类ID',
      `name` varchar(60) DEFAULT '' COMMENT '分类名称',
      `pic` varchar(255) DEFAULT '' COMMENT '分类图片',  
      `createtime` int(11) NULL DEFAULT 0 COMMENT '创建时间', 
      `updatetime` int(11) NULL DEFAULT 0 COMMENT '更新时间', 
      `status` tinyint(1) DEFAULT 1 COMMENT '状态 1启用 0禁用',
      PRIMARY KEY (`id`),
      INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");


    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_douyin_tuangou_set` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `pub_id` varchar(20) DEFAULT '' COMMENT '身份ID',
      `apikey` varchar(64) DEFAULT '' COMMENT 'apikey',
      PRIMARY KEY (`id`),
      INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_douyin_tuangou_product` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0' COMMENT '多商户ID',
      `shop_id` bigint DEFAULT NULL COMMENT '商品ID',
      `name` varchar(255) DEFAULT NULL COMMENT '商品名称',
      `type` int(11) DEFAULT NULL COMMENT '商品类型',
      `pic` varchar(255) DEFAULT '' COMMENT '商品图片',
      `first_category` varchar(20) DEFAULT NULL COMMENT '一级分类',
      `first_category_name` varchar(20) DEFAULT NULL COMMENT '一级分类名称',
      `second_category` varchar(20) DEFAULT NULL COMMENT '二级分类',
      `second_category_name` varchar(20) DEFAULT NULL COMMENT '二级分类名称',
      `third_category` varchar(20) DEFAULT NULL COMMENT '三级分类',
      `third_category_name` varchar(20) DEFAULT NULL COMMENT '三级分类名称',
      `status` int(1) DEFAULT '1' COMMENT '状态 1上架 0下架',
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `shop_id` (`shop_id`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='商品表';");
}
if(getcustom('teamfenhong_freight_money')){
    if(!pdo_fieldexists2("ddwx_member_level", "teamfenhong_freight_lv")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level`
            ADD COLUMN `teamfenhong_freight_lv`  int(11) NULL DEFAULT 0,
            ADD COLUMN `teamfenhong_freight_money`  decimal(11,2) NOT NULL DEFAULT 0.00 COMMENT '运费团队分红奖励';"
        );
    }
    if(!pdo_fieldexists2("ddwx_member_level", "teamfenhong_freight_pingji_lv")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level`
            ADD COLUMN `teamfenhong_freight_pingji_lv`  int(11) NULL DEFAULT 0,
            ADD COLUMN `teamfenhong_freight_pingji_money`  decimal(11,2) NOT NULL DEFAULT 0.00 COMMENT '运费团队分红平级奖励';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product", "teamfenhongfreightset")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product`
            ADD COLUMN `teamfenhongfreightset`  tinyint(1) NULL DEFAULT 0 COMMENT '运费团队分红设置 0按会员等级 1单独设置奖励比例 2单独设置奖励金额 -1不参与奖励',
            ADD COLUMN `teamfenhongfreightdata2`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '运费团队分红单独设置奖励金额数据';" );
    }
    if(!pdo_fieldexists2("ddwx_shop_product", "teamfenhongfreightpjset")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product`
            ADD COLUMN `teamfenhongfreightpjset`  tinyint(1) NULL DEFAULT 0 COMMENT '运费团队分红平级设置 0按会员等级 1单独设置奖励比例 2单独设置奖励金额 -1不参与奖励',
            ADD COLUMN `teamfenhongfreightpjdata2`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '运费团队分红平级单独设置奖励金额数据';" );
    }
    if (pdo_fieldexists2("ddwx_shop_product", "teamfenhongfreightpjdata2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
         MODIFY COLUMN `teamfenhongfreightpjdata2` text;");
    }
    if (pdo_fieldexists2("ddwx_shop_product", "teamfenhongfreightdata2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
         MODIFY COLUMN `teamfenhongfreightdata2` text;");
    }
}
if(getcustom('money_recharge_transfer')) {
    if (!pdo_fieldexists2("ddwx_admin_set", "money_recharge_pay_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`
            ADD COLUMN `money_recharge_pay_type` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'wxpay,alipay' COMMENT '余额充值支付方式';");
    }
    if (!pdo_fieldexists2("ddwx_recharge_order", "transfer_check")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_recharge_order`
            ADD COLUMN `transfer_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '转账审核 -1 驳回 0：待审核 1：通过';");
    }
}
if(getcustom('gdfenhong_jiesuantype')){
    if(!pdo_fieldexists2("ddwx_admin_set","gdfenhong_jiesuantype")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD `gdfenhong_jiesuantype` tinyint(1) NULL DEFAULT 0 COMMENT '股东分红结算方式 0按会员等级 1独立比例';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","gdfh_bili")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD `gdfh_bili` decimal (12,2) NULL DEFAULT 0.00 COMMENT '股东分红独立结算比例';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","gdfh_levelids")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD `gdfh_levelids` text COMMENT '股东分红独立结算参与等级';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","gd_fhjiesuanhb")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `gd_fhjiesuanhb`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红合并结算 0否 1是';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","gdfhjiesuantime_type")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `gdfhjiesuantime_type`  tinyint(4) NULL DEFAULT 0 COMMENT '股东分红结算时间 0确认收货 1付款后';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","gd_fhjiesuantime")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `gd_fhjiesuantime`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红结算周期 0每天 1月初 2 每小时 3 每分钟 4月底 5年底';");
    }


    if (!pdo_fieldexists2("ddwx_shop_order_goods", "isfenhong_gd")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_shop_order_goods` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_yuyue_order") && !pdo_fieldexists2("ddwx_yuyue_order", "isfenhong_gd") && pdo_fieldexists2("ddwx_yuyue_order", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_order` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_yuyue_order` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_scoreshop_order_goods") && !pdo_fieldexists2("ddwx_scoreshop_order_goods", "isfenhong_gd")  && pdo_fieldexists2("ddwx_scoreshop_order_goods", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order_goods` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_scoreshop_order_goods` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_lucky_collage_order") && !pdo_fieldexists2("ddwx_lucky_collage_order", "isfenhong_gd") && pdo_fieldexists2("ddwx_lucky_collage_order", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_order` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_lucky_collage_order` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_maidan_order") && !pdo_fieldexists2("ddwx_maidan_order", "isfenhong_gd") && pdo_fieldexists2("ddwx_maidan_order", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_maidan_order` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_maidan_order` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_restaurant_shop_order_goods") && !pdo_fieldexists2("ddwx_restaurant_shop_order_goods", "isfenhong_gd") && pdo_fieldexists2("ddwx_restaurant_shop_order_goods", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_order_goods` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_restaurant_shop_order_goods` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_restaurant_takeaway_order_goods") && !pdo_fieldexists2("ddwx_restaurant_takeaway_order_goods", "isfenhong_gd") && pdo_fieldexists2("ddwx_restaurant_takeaway_order_goods", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_takeaway_order_goods` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_restaurant_takeaway_order_goods` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_coupon_order") && !pdo_fieldexists2("ddwx_coupon_order", "isfenhong_gd") && pdo_fieldexists2("ddwx_coupon_order", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon_order` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_coupon_order` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
    if (pdo_fieldexists3("ddwx_kecheng_order") && !pdo_fieldexists2("ddwx_kecheng_order", "isfenhong_gd") && pdo_fieldexists2("ddwx_kecheng_order", "isfenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_order` ADD COLUMN `isfenhong_gd`  tinyint(1) NULL DEFAULT 0 COMMENT '股东分红 0未结算 1已结算';");
        \think\facade\Db::execute("update `ddwx_kecheng_order` set `isfenhong_gd`=1 where isfenhong!=0;");
    }
}

if(getcustom('member_shopscore')){
    if(!pdo_fieldexists2("ddwx_admin_set","shopscorestatus")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
            ADD COLUMN `shopscorestatus` tinyint(1) NULL DEFAULT 0 COMMENT '产品积分 0：关闭 1：开启',
            ADD COLUMN `shopscore2money` decimal(10, 2) NULL DEFAULT 0 COMMENT '每产品积分抵扣多少元',
            ADD COLUMN `shopscoredkmaxpercent` decimal(10, 2) NULL DEFAULT 0 COMMENT '最多抵扣多少比例';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` ADD COLUMN `shopscore` decimal(11, 2) NULL DEFAULT 0 COMMENT '商城商品积分';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `shopscoredk_money` decimal(10, 2) NULL DEFAULT 0 COMMENT '产品积分抵扣';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `shopscoredk_money` decimal(10, 2) NULL DEFAULT 0 COMMENT '产品积分抵扣';");
    }
    if(!pdo_fieldexists2("ddwx_business_sysset","shopscore_kouchu")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `shopscore_kouchu` tinyint(1) NULL DEFAULT 0 COMMENT '商家返款是否扣除产品积分 0：不扣除 1：扣除';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","shopscore2money")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
            ADD COLUMN `shopscore2money` decimal(10, 2) NULL DEFAULT 0 COMMENT '产品积分抵扣兑换比例',
            ADD COLUMN `shopscore` decimal(10, 2) NULL DEFAULT 0 COMMENT '产品积分使用数量';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_shopscorelog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NULL DEFAULT 0,
        `bid` int(11) NULL DEFAULT 0,
        `mid` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0',
        `shopscore` decimal(12, 2) NULL DEFAULT 0.00,
        `after` decimal(12, 2) NULL DEFAULT 0.00,
        `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        `uid` int(11) NULL DEFAULT 0,
        `frommid` int(11) NULL DEFAULT 0,
        `orderid` int(11) NULL DEFAULT 0,
        `ordernum` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单号',
        `paytype` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '支付类型',
        `createtime` int(11) NULL DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='会员产品积分记录表';");
}
if(getcustom('coupon_shop_times_coupon')){
    if(!pdo_fieldexists2("ddwx_shop_order","times_coupon_num")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `times_coupon_num` int(11) DEFAULT '0' COMMENT '计次券 核销次数';");
    }
    if(!pdo_fieldexists2("ddwx_cashier_order","times_coupon_num")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order` ADD COLUMN `times_coupon_num` int(11) DEFAULT '0' COMMENT '计次券 核销次数';");
    }
}
if(getcustom('product_thali')){
    if(!pdo_fieldexists2("ddwx_shop_sysset","product_shop_school")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `product_shop_school` tinyint(1) NULL DEFAULT 0 COMMENT '普通商品学校班级 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_shop_sysset","product_thali_school")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `product_thali_school` tinyint(1) NULL DEFAULT 0 COMMENT '套餐商品学校班级 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_shop_order","product_thali_student_name")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` 
        ADD COLUMN `product_thali_student_name` varchar(30) DEFAULT NULL COMMENT '学生姓名',
        ADD COLUMN `product_thali_school` varchar(30) DEFAULT NULL COMMENT '学校信息';
    ");
    }
    if(!pdo_fieldexists2("ddwx_product_thali","freighttype")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_product_thali` 
        ADD COLUMN `freighttype` tinyint(1) DEFAULT '0',
        ADD COLUMN `freightdata` varchar(200) CHARACTER SET utf8 DEFAULT NULL,
        ADD COLUMN `freightcontent` text CHARACTER SET utf8;
    ");
    }
}
if(getcustom('cycle_product_custom_cycle')){
    if(!pdo_fieldexists2("ddwx_cycle_product","custom_days")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_cycle_product` 
        ADD COLUMN `custom_days` int(11) DEFAULT '1' COMMENT '自定义周期天数';");
    }
}
if(getcustom('yx_team_yeji_cashdesk')){
    if(!pdo_fieldexists2("ddwx_admin_set","include_cashdesk_yeji")){
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `include_cashdesk_yeji` tinyint(1) DEFAULT '0' COMMENT '包含收银台业绩';");
    }
}
if(getcustom('article_give_score')){
    if(!pdo_fieldexists2('ddwx_article_set','read_time')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_article_set` 
        ADD COLUMN `read_time` int(11) NULL DEFAULT 0 COMMENT '阅读时间(分钟)';");
    }
}
if(getcustom('shop_order_exchange_product')) {
    if (!pdo_fieldexists2('ddwx_shop_sysset', 'exchange_product')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
        ADD COLUMN `exchange_product` tinyint(1) NOT NULL DEFAULT '0' COMMENT '换货开关 0关闭 1开启',
        ADD COLUMN `exchange_product_day` int(11) NULL DEFAULT 0 COMMENT '确认收货后几天内可换货'
        ;");
    }
    if (!pdo_fieldexists2('ddwx_shop_refund_order', 'exchange_express_com')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_refund_order` 
        ADD COLUMN `exchange_express_com` varchar(50) DEFAULT NULL COMMENT '商家换货快递公司',
        ADD COLUMN `exchange_express_no` varchar(100) DEFAULT NULL COMMENT '商家换货快递单号',
        ADD COLUMN `exchange_express_content` text COMMENT '多个快递单号时的快递单号数据',
        ADD COLUMN `exchange_expresstime` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商家换货发货时间',
        ADD COLUMN `exchange_reject_reason` varchar(200) DEFAULT NULL COMMENT '换货驳回原因'
        ;");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_refund_order_goods_exchange` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `mid` int(11) DEFAULT NULL,
      `refund_orderid` int(11) DEFAULT NULL,
      `refund_ordernum` varchar(50) DEFAULT NULL,
      `refund_order_goodsid` varchar(50) DEFAULT NULL COMMENT '退换货商品详情表id',
      `orderid` int(11) DEFAULT NULL,
      `ordernum` varchar(50) DEFAULT NULL,
      `ogid` int(11) NOT NULL,
      `proid` int(11) DEFAULT NULL,
      `name` varchar(255) DEFAULT NULL,
      `pic` varchar(255) DEFAULT NULL,
      `procode` varchar(255) DEFAULT NULL,
      `ggid` int(11) DEFAULT NULL,
      `ggname` varchar(255) DEFAULT NULL,
      `num` int(11) unsigned NOT NULL DEFAULT '0',
      `cid` varchar(255) NULL DEFAULT '0',
      `cost_price` decimal(11,2) DEFAULT NULL,
      `sell_price` decimal(11,2) DEFAULT NULL,
      `createtime` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE,
      KEY `mid` (`mid`) USING BTREE,
      KEY `refund_orderid` (`refund_orderid`) USING BTREE,
      KEY `refund_ordernum` (`refund_ordernum`) USING BTREE,
      KEY `orderid` (`orderid`) USING BTREE,
      KEY `ordernum` (`ordernum`) USING BTREE,
      KEY `refund_order_goodsid` (`refund_order_goodsid`) USING BTREE,
      KEY `proid` (`proid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='换新商品表';");
}
if(getcustom('yx_queue_free_edit_money')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','edit_money_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
        ADD COLUMN `edit_money_status` tinyint(1) DEFAULT '0' COMMENT '修改排队金额';");
    }
    if(!pdo_fieldexists2('ddwx_business','edit_money_status')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
        ADD COLUMN `edit_money_status` tinyint(1) DEFAULT '0' COMMENT '修改排队金额';");
    }
}
if(getcustom('mendian_hexiao_commission_to_money')){
    if(!pdo_fieldexists2("ddwx_mendian","commission_to_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` 
        ADD COLUMN `commission_to_money` tinyint(1) DEFAULT '0' COMMENT '核销提成转到绑定会员余额';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","commission_to_money")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        ADD COLUMN `commission_to_money` tinyint(1) DEFAULT '0' COMMENT '核销提成转到绑定会员余额';");
    }
}
if(getcustom('mendian_hexiao_give_score')){
    if(!pdo_fieldexists2("ddwx_mendian","hexiao_give_score_bili")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` 
        ADD COLUMN `hexiao_give_score_bili` decimal(10, 2) NULL DEFAULT 0 COMMENT '核销送绑定会员积分,赠送比例';");
    }
    if(!pdo_fieldexists2("ddwx_shop_product","hexiao_give_score_bili")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        ADD COLUMN `hexiao_give_score_bili` decimal(5, 2) NULL COMMENT '核销送绑定会员积分,赠送比例';");
    }
}
if(getcustom('yx_queue_free_quit_score')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','quit_score')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
        ADD COLUMN `quit_score` tinyint(1) DEFAULT '0' COMMENT '退出返积分0关闭1开启',
         ADD COLUMN `quit_score_ratio` decimal(11,2) DEFAULT '0.00' COMMENT '返还积分比例';");
    }
}
if(getcustom('yx_queue_free_addup_give')){
    if(!pdo_fieldexists2('ddwx_queue_free_set','back_money_type')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
         ADD COLUMN `back_money_type` tinyint(1) DEFAULT '0' COMMENT '返现方式0默认1满额一次性返';");
    }
    if(!pdo_fieldexists2('ddwx_queue_free','addup_money')){
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free` 
        ADD COLUMN `addup_money` decimal(11,2) DEFAULT '0.00' COMMENT '累计金额';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_queue_free_addup_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `queueid` int(11) DEFAULT '0',
          `aid` int(11) DEFAULT NULL,
          `bid` int(11) DEFAULT NULL,
          `mid` int(11) DEFAULT '0',
          `from_mid` int(11) DEFAULT '0',
          `money` decimal(11,2) DEFAULT NULL,
          `createtime` int(11) DEFAULT '0',
          `remark` varchar(255) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `aid` (`aid`),
          KEY `bid` (`bid`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='排队免单的累计记录';");
}
if(getcustom('shop_alone_give_score')){
    if(!pdo_fieldexists2("ddwx_admin_set","alone_give_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `alone_give_score` tinyint(1) DEFAULT 1 COMMENT '商品独立赠送积分 1固定积分 2按实付比例赠送';");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","maidan_give_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `maidan_give_score` tinyint(1) DEFAULT 1 COMMENT '买单按实际比例赠送积分 1固定积分 2按实付比例赠送';");
    }
}
if(getcustom('wanyue10086')) {
    if (!pdo_fieldexists2("ddwx_open_app", "wanyue_appid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_open_app` 
        ADD COLUMN `wanyue_appid` varchar(60) NULL DEFAULT '' COMMENT '移动appid',
        ADD COLUMN `wanyue_appsecret` varchar(60) NULL DEFAULT '' COMMENT '移动appsecret';");
    }
}
if(getcustom('mendian_member_levelup_fenhong')){
    if(!pdo_fieldexists2("ddwx_admin_set","mendian_member_levelup_fenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
        ADD COLUMN `mendian_member_levelup_fenhong` tinyint(1) NULL DEFAULT 0 COMMENT '扫门店码升级分红 0关闭 1开启';");
    }
    if(!pdo_fieldexists2("ddwx_member","mdid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
        ADD COLUMN `mdid` int(11) NOT NULL DEFAULT '0' COMMENT '门店id';
        ");
    }
    if(!pdo_fieldexists2("ddwx_member","add_mendian_time")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
        ADD COLUMN `add_mendian_time` int(11) NOT NULL DEFAULT '0' COMMENT '加入门店时间';
        ");
    }
    if(!pdo_fieldexists2("ddwx_member_levelup_order","mdid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_levelup_order` 
        ADD COLUMN `mdid` int(11) NOT NULL DEFAULT '0' COMMENT '扫码门店id';");
    }
    if(!pdo_fieldexists2("ddwx_mendian","member_levelup_fenhong")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian` 
        ADD COLUMN `member_levelup_fenhong` tinyint(1) NULL DEFAULT 0 COMMENT '会员扫码升级分红 0关闭 1开启',
        ADD COLUMN `member_levelup_fenhong_mid` int(11) NULL DEFAULT 0 COMMENT '会员扫码升级分红人',
        ADD COLUMN `member_levelup_fenhong_money` decimal(11,2) DEFAULT '0.00' COMMENT '会员扫码升级分红金额',
        ADD COLUMN `member_levelup_fenhong_money_type` tinyint(1) NULL DEFAULT 0 COMMENT '会员扫码升级分红金额类型：1金额  0比例'
        ;");
    }
}
if(getcustom('product_nostock_show')) {
    if (!pdo_fieldexists2('ddwx_shop_sysset', 'product_nostock_show')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
        ADD COLUMN `product_nostock_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '无库存商品 1显示 0隐藏'
        ;");
    }
}
if(getcustom('levelup_fxordermoney_self')){
    if (!pdo_fieldexists2("ddwx_member_level", "up_fxordermoney_self")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_level` ADD COLUMN `up_fxordermoney_self` tinyint(1) DEFAULT '0' COMMENT '下级总订单金额含自己，1开启' AFTER `up_fxorderlevelid`;");
    }
}
if(getcustom('nfc_open_wx')) {
    
    if(!pdo_fieldexists2("ddwx_qrcode_variable","nfc_open_wx_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_variable` 
        ADD COLUMN  `nfc_open_wx_status` tinyint(1) DEFAULT '0' COMMENT 'NFC活码打开微信小程序',
        ADD COLUMN  `nfc_model_id` varchar(30) DEFAULT NULL COMMENT '微信小程序中的设备model_id';");
    }
    if(!pdo_fieldexists2("ddwx_qrcode_list_variable","nfc_wx_openlink")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_list_variable` 
        ADD COLUMN  `nfc_wx_openlink` varchar(100) DEFAULT NULL COMMENT '微信小程序的打开链接';");
    }
    if(!pdo_fieldexists2("ddwx_qrcode_list_variable","nfc_open_wx_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_qrcode_list_variable` 
        ADD COLUMN  `nfc_open_wx_status` tinyint(1) DEFAULT '0' COMMENT 'NFC活码打开微信小程序',
        ADD COLUMN  `nfc_model_id` varchar(30) DEFAULT NULL COMMENT '微信小程序中的设备model_id';");
    }
    if(!pdo_fieldexists2("ddwx_mingpian","qrcode_variable_code")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mingpian` 
        ADD COLUMN  `qrcode_variable_code` varchar(50) DEFAULT NULL COMMENT '活码的code';");
    }
}
if(getcustom('yeji_self_manually_product')) {
    if (!pdo_fieldexists2('ddwx_member', 'yeji_self_manually_product')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
        ADD COLUMN `yeji_self_manually_product` int(11) unsigned DEFAULT '0' COMMENT '手动增加的个人业绩（按商品数量，不是金额）'
        ;");
    }
}
if(getcustom('product_glass')){
    if(!pdo_fieldexists2("ddwx_glass_record","diagnosis")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_glass_record` 
        ADD COLUMN `diagnosis` text COMMENT '诊断书',
        ADD COLUMN `optometry_company` varchar(200) DEFAULT NULL COMMENT '验光单位',
        ADD COLUMN `optometry_name` varchar(30) DEFAULT NULL COMMENT '验光师';");
    }
}
if(getcustom('product_brand')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_shop_brand` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `bid` int(11) DEFAULT '0',
      `cid` varchar(100) DEFAULT NULL COMMENT '分类id',
      `name` varchar(200) DEFAULT NULL,
      `status` int(1) DEFAULT '1',
      `sort` int(11) DEFAULT '1',
      `createtime` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `bid` (`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if(!pdo_fieldexists2("ddwx_shop_product","brand_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        ADD COLUMN `brand_id` int(11) DEFAULT NULL COMMENT '品牌id';");
    }
}
if(getcustom('product_glass')){
    if(!pdo_fieldexists2("ddwx_shop_cartlr","glass_record_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_cartlr` 
        ADD COLUMN `glass_record_id` int(11) DEFAULT '0' COMMENT '视力档案id';");
    }
}

if(getcustom('product_show_guige_type')) {
    if (!pdo_fieldexists2("ddwx_shop_product", "guige_show_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `guige_show_type` tinyint(1) DEFAULT '0' COMMENT '前端规格展示方式 0:默认 1:纵横交叉';");
    }
}
if(getcustom('member_change_mendian')) {
    if (!pdo_fieldexists2("ddwx_mendian_sysset", "changemendian_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian_sysset` 
          ADD COLUMN `changemendian_status` tinyint(1) DEFAULT '1' COMMENT '会员切换门店0关闭1开启';");
    }
}
if(getcustom('shoporder_tongji_category')) {
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "cid2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
          ADD COLUMN `cid2` varchar(255) DEFAULT NULL COMMENT '多商家商品分类';");
    }
}
if(getcustom('business_shop_order_send_show')){
    if (!pdo_fieldexists2("ddwx_business_sysset", "shop_order_send_show")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
            ADD COLUMN `shop_order_send_show` tinyint(1) DEFAULT '1' COMMENT '多商户商城订单发货按钮显示隐藏 0:隐藏 1:显示';");
    }
}
if(getcustom('business_shop_order_hexiao_show')){
    if (!pdo_fieldexists2("ddwx_business_sysset", "shop_order_hexiao_show")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` 
            ADD COLUMN `shop_order_hexiao_show` tinyint(1) DEFAULT '1' COMMENT '多商户商城订单核销按钮显示隐藏 0:隐藏 1:显示';");
    }
}
if(getcustom('restaurant_autoshdays_unit')){
    if (!pdo_fieldexists2("ddwx_restaurant_takeaway_sysset", "autoshdays_unit")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_takeaway_sysset` 
            ADD COLUMN `autoshdays_unit` tinyint(1) DEFAULT '0' COMMENT '外卖自动收货时间的单位，0天 1分钟';");
    }
    if (!pdo_fieldexists2("ddwx_restaurant_shop_sysset", "autoshdays_unit")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_shop_sysset` 
            ADD COLUMN `autoshdays_unit` tinyint(1) DEFAULT '0' COMMENT '点餐自动收货时间的单位，0天 1分钟';");
    }
}
if(getcustom('restaurant_cuxiao_use_time_range')){
    if (!pdo_fieldexists2("ddwx_restaurant_cuxiao", "use_starttime")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_cuxiao` 
            ADD COLUMN `use_starttime` varchar(30) DEFAULT NULL COMMENT '可用开始 8:00',
            ADD COLUMN `use_endtime` varchar(30) DEFAULT NULL COMMENT '可用结束 12:00';");
    }
}
if(getcustom('member_level_add_apply_mendian')){
    if (!pdo_fieldexists2("ddwx_member_levelup_order", "up_mdid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_levelup_order` 
            ADD COLUMN `up_mdid` int(11) NULL DEFAULT 0 COMMENT '升级同步注册门店id';");
    }
}
if(getcustom('mendian_member_level_set')){
    if (!pdo_fieldexists2("ddwx_mendian_sysset", "setLevel_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mendian_sysset` 
            ADD COLUMN `setLevel_status` tinyint(1) DEFAULT '1' COMMENT '设置会员等级开关0关闭1开启';");
    }
}
if(getcustom('money_dec_product')){
    if (!pdo_fieldexists2("ddwx_shop_product", "moneydecset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
            ADD COLUMN `moneydecset` tinyint(1) NULL DEFAULT 0 COMMENT '余额抵扣设置 -1：关闭 0：跟随系统 1：单独比例 2：单独金额',
            ADD COLUMN `moneydecval` decimal(10, 2) NULL DEFAULT 0 COMMENT '余额最高抵扣数值';");
    }
}
if(getcustom('yx_queue_free_activity_time')){
    if (!pdo_fieldexists2("ddwx_queue_free_set", "activity_time_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set` 
            ADD COLUMN `activity_time_status` tinyint(1) DEFAULT '0' COMMENT '排队时间设置1开启0关闭',
            ADD COLUMN `activity_time` varchar(50) DEFAULT NULL COMMENT '活动时间2025-01-01 ~ 2025-01-31';");
    }
}
if(getcustom('product_deposit_mode')) {
    if (!pdo_fieldexists2('ddwx_shop_sysset', 'product_deposit_mode')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
        ADD COLUMN `product_deposit_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '商品押金模式 1开启0关闭',
        ADD COLUMN `product_deposit_mode_withdrawalday`  int(11) NULL DEFAULT 0 COMMENT '押金几天后可提现'
        ;");
    }
    if (!pdo_fieldexists2('ddwx_shop_product', 'deposit_mode')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
        ADD COLUMN `deposit_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '押金购买模式 1开启0关闭'
        ;");
    }
    if (!pdo_fieldexists2('ddwx_shop_order_goods', 'deposit_mode')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
        ADD COLUMN `deposit_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是押金商品 1是0否'
        ;");
    }
    if (!pdo_fieldexists2('ddwx_member', 'product_deposit')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_member` 
        ADD COLUMN `product_deposit` decimal(11,2) unsigned DEFAULT '0.00' COMMENT '商品押金'
        ;");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_deposit_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` varchar(100) DEFAULT NULL,
        `money` decimal(11,2) DEFAULT '0.00' COMMENT '押金',
        `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未提现 1已申请待审核 2已驳回 3审核通过',
        `withdrawaltime` int(11) DEFAULT NULL COMMENT '可提现时间',
        `createtime` int(11) DEFAULT NULL,
        `uid` int(11) DEFAULT NULL,
        `reason` varchar(200) DEFAULT '' COMMENT '驳回原因',
        `orderid` int(11) DEFAULT NULL,
        `applywithdrawaltime` int(11) DEFAULT NULL COMMENT '申请提现时间',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='商品押金明细';");
}
if(getcustom('pay_allinpay')){
    if (!pdo_fieldexists2('ddwx_payorder', 'isallinpay')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` ADD COLUMN `isallinpay` tinyint(1) NULL DEFAULT 0 COMMENT '是否是通联支付 0：否 1：是';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_wxrefund_log` 
            ADD COLUMN `refund_ordernum` varchar(60) NULL DEFAULT '' COMMENT '退款订单号（通联支付增加）',
            ADD COLUMN `refundtype` varchar(20) NULL DEFAULT '' COMMENT '退款类型（通联支付增加）',
            ADD COLUMN `trxid` varchar(30) NULL DEFAULT '' COMMENT '收银宝平台的退款交易流水号通联支付增加）';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `withdraw_bankcard_allinpayYunst` tinyint(1) NULL DEFAULT 0 COMMENT '通联支付云商通银行卡 0：关闭 1：开启' ;");
        \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` ADD COLUMN `extendinfo` text NULL COMMENT '扩展信息（通联支付添加）';");
        \think\facade\Db::execute("ALTER TABLE `ddwx_member_withdrawlog` ADD COLUMN `allinpayorderNo` varchar(30) NULL DEFAULT '' COMMENT '通联支付 云商通订单号';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_allinpay_yunst_withdrawlog` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `logid` int(11) NULL DEFAULT 0 COMMENT '提现id',
        `mid` int(11) NULL DEFAULT 0,
        `ordernum` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
        `type` tinyint(1) NULL DEFAULT 0 COMMENT '提现类型 1：余额 2：佣金',
        `orderNo` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '云商通订单号',
        `rsdata` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '成功返回的数据',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '-1：失败 0：待提交 1：已提交 2: 已通过',
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `message` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
        `endtime` bigint(20) NULL DEFAULT 0,
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `ordernum`(`ordernum`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='通联支付云商通薪提现记录';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_allinpay_yunst_user` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0,
        `bizUserId` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商户系统用户标识，商户系统中唯一编号',
        `phone` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '手机号',
        `memberType` tinyint(1) NULL DEFAULT 3 COMMENT '会员类型 2 企业 3 个人',
        `source` tinyint(1) NULL DEFAULT 2 COMMENT '访问终端类型 1 Mobile 2 PC',
        `userId` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '云商通用户唯一标识',
        `createtime` bigint(20) UNSIGNED NULL DEFAULT 0,
        `updatetime` bigint(20) UNSIGNED NULL DEFAULT 0,
        `status` tinyint(1) NULL DEFAULT 0 COMMENT '是否绑定成功 0 ：否 1：是',
        `isAuth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否由云商通进行认证',
        `name` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '姓名',
        `identityType` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '1' COMMENT '证件类型 目前仅支持身份证',
        `identityNo` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '证件号码',
        `cardNo` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '银行卡号',
        `cardPhone` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '银行预留手机',
        `tranceNum` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '流水号 绑卡方式6、7、88返回',
        `transDate` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '申请时间',
        `bankCode` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
        `cardType` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
        `bankName` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
        `agreementNo` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '签约协议号 仅绑卡方式6或7 时返回',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='通联支付云商通会员表';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_allinpay_yunst_signlog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `bid` int(11) NOT NULL DEFAULT 0,
        `mid` int(11) NOT NULL DEFAULT 0,
        `realname` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '姓名',
        `tel` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '电话',
        `usercard` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '身份证',
        `bankcardnum` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '银行卡号',
        `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:待提交  1：已实名认证 2：已签约',
        `createtime` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        `idFace` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '身份证正面',
        `idReverse` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '身份证反面',
        `resdata` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '回调返还数据',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `mid`(`mid`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `usercard`(`usercard`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='通联支付签约记录';");
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_allinpay_yunst_set` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL DEFAULT 0,
        `bid` int(11) NOT NULL DEFAULT 0,
        `app_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '应用id',
        `secret` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '应用secret',
        `private_key` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '应用私钥证书(pfx)',
        `private_key_pwd` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '应用私钥证书密码',
        `public_key` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '通商云公钥证书(cer)',
        `status` tinyint(1) NOT NULL DEFAULT 0,
        `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
        `apiurl` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '接口域名',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE,
        INDEX `bid`(`bid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='通联支付设置';");
}
if(getcustom('lipinka_perlimit')){
    if (!pdo_fieldexists2("ddwx_shop_order_goods", "lipin_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` 
            ADD COLUMN `lipin_id` int(11) NOT NULL DEFAULT '0' COMMENT '礼品id';");
    }
    if (!pdo_fieldexists2("ddwx_lipin", "perlimit_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_lipin` 
            ADD COLUMN `perlimit_status` tinyint(1) DEFAULT '0' COMMENT '跟随商品限购';");
    }
}
if(getcustom('product_lvxin_replace_remind')) {
    if (!pdo_fieldexists2('ddwx_shop_sysset', 'product_lvxin_replace_remind')) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
        ADD COLUMN `product_lvxin_replace_remind` tinyint(1) NOT NULL DEFAULT '0' COMMENT '滤芯更换提醒 1开启0关闭',
        ADD COLUMN `product_lvxin_expireday_remind` tinyint(1) NOT NULL DEFAULT '0' COMMENT '滤芯到期前N天提醒',
        ADD COLUMN `product_lvxin_remind_type`  varchar(100) DEFAULT 'wx,sms' COMMENT '到期提醒方式'
        ;");
    }
    if(!pdo_fieldexists2("ddwx_admin_set_sms","tmpl_product_lvxin_replace_remind")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set_sms` 
        ADD COLUMN `tmpl_product_lvxin_replace_remind` varchar(50) DEFAULT NULL COMMENT '滤芯过期提醒',
        ADD COLUMN `tmpl_product_lvxin_replace_remind_txy` varchar(50) DEFAULT NULL COMMENT '滤芯过期提醒',
    	ADD COLUMN `tmpl_product_lvxin_replace_remind_st` tinyint(1) DEFAULT '1' COMMENT '滤芯过期提醒开关'
        ;");
    }
    if (!pdo_fieldexists2("ddwx_mp_tmplset_new","tmpl_product_lvxin_replace_remind")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_product_lvxin_replace_remind` varchar(64) DEFAULT NULL COMMENT '滤芯过期提醒';");
    }
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_product_lvxin_replace` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` varchar(100) DEFAULT NULL,
        `proid` int(11) DEFAULT NULL COMMENT '滤芯商品ID',
        `day` int(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余天数',
        `expiretime` int(11) DEFAULT NULL COMMENT '到期时间',
        `createtime` int(11) DEFAULT NULL,
        `updatetime` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid` (`aid`) USING BTREE,
        KEY `mid` (`mid`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='滤芯商品售后表';");
}
if(getcustom('restaurant_fenhong') && getcustom('restaurant_fenhong_product_set')){
    if (!pdo_fieldexists2("ddwx_restaurant_product", "fenhongset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_product`
		ADD COLUMN `fenhongset` int(2) DEFAULT '1' COMMENT '分红设置1参与 0不参与';");
    }
    if (!pdo_fieldexists2("ddwx_restaurant_product", "teamfenhongset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_product`
		ADD COLUMN `teamfenhongset` int(2) DEFAULT '0' COMMENT '0按会员等级 1价格比例  2固定金额 -1不参与分红',
		ADD COLUMN `teamfenhongdata1` text CHARACTER SET utf8,
		ADD COLUMN `teamfenhongdata2` text CHARACTER SET utf8;");
    }
    if (!pdo_fieldexists2("ddwx_restaurant_product", "gdfenhongset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_product`
		ADD COLUMN `gdfenhongset` int(2) DEFAULT '0' COMMENT '0按会员等级 1价格比例  2固定金额 -1不参与分红',
		ADD COLUMN `gdfenhongdata1` text CHARACTER SET utf8,
		ADD COLUMN `gdfenhongdata2` text CHARACTER SET utf8;");
    }
    if (!pdo_fieldexists2("ddwx_restaurant_product", "areafenhongset")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_product`
		ADD COLUMN `areafenhongset` int(2) DEFAULT '0' COMMENT '0按会员等级 1价格比例  2固定金额 -1不参与分红',
		ADD COLUMN `areafenhongdata1` text CHARACTER SET utf8,
		ADD COLUMN `areafenhongdata2` text CHARACTER SET utf8;");
    }
}
if(getcustom('fenhong_fafang_type')){
    if (!pdo_fieldexists2("ddwx_admin_set", "fenhong_fafang_type")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`
		ADD COLUMN `fenhong_fafang_type` tinyint(1) DEFAULT '0' COMMENT '分红发放方式,0自动发放 1审核发放';");
    }
}
if(getcustom('restaurant_coupon_apply_business')){
    if (!pdo_fieldexists2("ddwx_coupon", "apply_platform")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon`
		ADD COLUMN `apply_platform` tinyint(1) DEFAULT '1' COMMENT '适用平台0关闭1开启',
		ADD COLUMN `apply_business_type` tinyint(1) DEFAULT '1' COMMENT '适用商户,0全部 1选择商户',
		ADD COLUMN `apply_businessids` varchar(255) DEFAULT NULL COMMENT '适用商户ids';");
    }
}
if(getcustom('yx_queue_free_quit_random_score')){
    if (!pdo_fieldexists2("ddwx_queue_free_set", "quit_random_score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set`
        ADD COLUMN `quit_random_score` tinyint(1) DEFAULT '0' COMMENT '退出抽积分',
        ADD COLUMN `random_score_data` text  COMMENT '积分奖励数据';");
    }
}
if(getcustom('yx_queue_free_restaurant_shop')){
    if (!pdo_fieldexists2("ddwx_restaurant_product", "queue_free_status")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_product`
        ADD COLUMN `queue_free_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '参与排队免单 0不参与 1参与';");
    }
}
if(getcustom('yx_queue_free_quit_wxhb')){
    if (!pdo_fieldexists2("ddwx_queue_free_set", "random_wxhb_data")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_queue_free_set`
        ADD COLUMN `random_wxhb_data` text COMMENT '退出红包比例';");
    }
}
if(getcustom('business_canuseplatcoupon')){
    if (!pdo_fieldexists2("ddwx_coupon", "canused_bids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` ADD COLUMN `canused_bids` text;");
    }
    if (!pdo_fieldexists2("ddwx_coupon_record", "canused_bids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon_record` ADD COLUMN `canused_bids` text;");
    }
    if (!pdo_fieldexists2("ddwx_business", "couponmoney")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `couponmoney` decimal(11,2) DEFAULT '0.00';");
    }
    if (!pdo_fieldexists2("ddwx_business_sysset", "couponwithdrawfee")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_business_sysset` ADD COLUMN `couponwithdrawfee` decimal(11,2) DEFAULT '0.00';");
    }
    if (!pdo_fieldexists2("ddwx_coupon", "canused_bcids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` ADD COLUMN `canused_bcids` varchar(255) DEFAULT NULL;");
    }
    if (!pdo_fieldexists2("ddwx_coupon_record", "canused_bcids")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_coupon_record` ADD COLUMN `canused_bcids` varchar(255) DEFAULT NULL;");
    }
}