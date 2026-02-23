<?php
// 数据库配置
$config = [
    'host' => 'localhost',
    'username' => 'ddshop',
    'password' => '6NpZeFbA2Kxc5MLz',
    'dbname' => 'ddshop',
    'prefix' => 'ddwx_'
];

// 创建数据库连接
$conn = new mysqli($config['host'], $config['username'], $config['password'], $config['dbname']);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

echo "=== 开始扩展一物一码相关表结构 ===\n";

// 1. 扩展product_qrcode表，添加服务记录相关字段
$extendQrcodeTable = "ALTER TABLE `{$config['prefix']}product_qrcode` 
    ADD `quality_start_time` int(11) NULL DEFAULT NULL COMMENT '质保开始时间',
    ADD `quality_end_time` int(11) NULL DEFAULT NULL COMMENT '质保结束时间',
    ADD `sale_time` int(11) NULL DEFAULT NULL COMMENT '销售时间',
    ADD `install_time` int(11) NULL DEFAULT NULL COMMENT '安装时间',
    ADD `last_service_time` int(11) NULL DEFAULT NULL COMMENT '最后服务时间',
    ADD `current_status` tinyint(2) NULL DEFAULT '1' COMMENT '当前状态（1=未销售，2=已销售，3=已安装，4=已激活，5=已维修）';";

if ($conn->query($extendQrcodeTable) === TRUE) {
    echo "✓ 成功扩展{$config['prefix']}product_qrcode表\n";
} else {
    echo "✗ 扩展{$config['prefix']}product_qrcode表失败: " . $conn->error . "\n";
}

// 2. 创建服务记录表
$createServiceTable = "CREATE TABLE IF NOT EXISTS `{$config['prefix']}product_qrcode_service` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `qrcode_id` int(11) NOT NULL COMMENT '关联二维码ID',
  `code` varchar(64) NOT NULL COMMENT '唯一码',
  `operator_id` int(11) NULL DEFAULT NULL COMMENT '操作人ID',
  `operator_name` varchar(50) NULL DEFAULT NULL COMMENT '操作人姓名',
  `operator_type` tinyint(2) NOT NULL COMMENT '操作人类型（1=供货商，2=门店，3=销售员，4=安装员，5=客服，6=技术员，7=客户）',
  `action` tinyint(2) NOT NULL COMMENT '操作类型（1=数据录入，2=销售，3=安装，4=维修，5=质保查询，6=激活）',
  `content` text COMMENT '操作内容',
  `result` tinyint(2) NULL DEFAULT NULL COMMENT '操作结果（1=成功，0=失败）',
  `remark` text COMMENT '备注',
  `ip` varchar(50) NULL DEFAULT NULL COMMENT '操作IP',
  `create_time` int(11) NOT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `idx_qrcode_id` (`qrcode_id`),
  KEY `idx_code` (`code`),
  KEY `idx_operator_type` (`operator_type`),
  KEY `idx_action` (`action`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品二维码服务记录表';";

if ($conn->query($createServiceTable) === TRUE) {
    echo "✓ 成功创建{$config['prefix']}product_qrcode_service表\n";
} else {
    echo "✗ 创建{$config['prefix']}product_qrcode_service表失败: " . $conn->error . "\n";
}

$conn->close();
echo "=== 表结构扩展完成 ===\n";