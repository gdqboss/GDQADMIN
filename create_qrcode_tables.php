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

echo "=== 开始创建一物一码相关表 ===\n";

// 创建一物一码主表
$create_qrcode_table = "CREATE TABLE IF NOT EXISTS `{$config['prefix']}product_qrcode` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `code` varchar(64) NOT NULL UNIQUE COMMENT '唯一码',
  `product_id` int(11) NOT NULL COMMENT '关联商品ID',
  `product_type` tinyint(2) NOT NULL DEFAULT '1' COMMENT '商品类型（1=普通商品，2=拼团商品等）',
  `specs_id` int(11) DEFAULT NULL COMMENT '关联规格ID（可选）',
  `order_id` int(11) DEFAULT NULL COMMENT '关联订单ID（可选）',
  `delivery_id` int(11) DEFAULT NULL COMMENT '发货员ID（可选）',
  `salesman_id` int(11) DEFAULT NULL COMMENT '销售员ID（可选）',
  `customer_id` int(11) DEFAULT NULL COMMENT '客户ID（可选）',
  `customer_name` varchar(50) DEFAULT NULL COMMENT '客户姓名',
  `customer_phone` varchar(20) DEFAULT NULL COMMENT '客户电话',
  `store_id` int(11) DEFAULT NULL COMMENT '门店ID（可选）',
  `merchant_id` int(11) NOT NULL COMMENT '商家ID',
  `after_sales_id` int(11) DEFAULT NULL COMMENT '售后负责人ID（可选）',
  `installer_id` int(11) DEFAULT NULL COMMENT '安装人员ID（可选）',
  `remark` text COMMENT '备注信息',
  `qrcode_url` varchar(255) DEFAULT NULL COMMENT '二维码图片URL',
  `status` tinyint(2) NOT NULL DEFAULT '1' COMMENT '状态（1=启用，0=禁用，2=已使用）',
  `scan_count` int(11) NOT NULL DEFAULT '0' COMMENT '扫码次数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一物一码主表';";

if ($conn->query($create_qrcode_table) === TRUE) {
    echo "✓ 成功创建{$config['prefix']}product_qrcode表\n";
} else {
    echo "✗ 创建{$config['prefix']}product_qrcode表失败: " . $conn->error . "\n";
}

// 创建扫码记录表
$create_scan_table = "CREATE TABLE IF NOT EXISTS `{$config['prefix']}product_qrcode_scan` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `qrcode_id` int(11) NOT NULL COMMENT '关联二维码ID',
  `user_id` int(11) DEFAULT NULL COMMENT '扫码用户ID（可选）',
  `openid` varchar(100) DEFAULT NULL COMMENT '扫码用户openid',
  `ip` varchar(50) DEFAULT NULL COMMENT '扫码IP',
  `scan_time` int(11) NOT NULL COMMENT '扫码时间',
  `platform` varchar(20) DEFAULT NULL COMMENT '扫码平台（微信、H5等）',
  PRIMARY KEY (`id`),
  KEY `idx_qrcode_id` (`qrcode_id`),
  KEY `idx_scan_time` (`scan_time`),
  CONSTRAINT `fk_qrcode_scan` FOREIGN KEY (`qrcode_id`) REFERENCES `{$config['prefix']}product_qrcode` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='扫码记录表';";

if ($conn->query($create_scan_table) === TRUE) {
    echo "✓ 成功创建{$config['prefix']}product_qrcode_scan表\n";
} else {
    echo "✗ 创建{$config['prefix']}product_qrcode_scan表失败: " . $conn->error . "\n";
}

$conn->close();
echo "=== 表创建完成 ===\n";