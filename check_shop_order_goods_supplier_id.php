<?php
// 检查并添加supplier_id字段到shop_order和shop_order_goods表

// 数据库配置
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '561cac2d9a8daa43',
    'database' => 'ddshop',
    'prefix' => 'ddwx_'
];

// 创建数据库连接
$conn = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);

// 检查连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

echo "=== 检查shop_order_goods表supplier_id字段 ===\n";

// 检查shop_order_goods表是否有supplier_id字段
$hasSupplierId = false;
$shopOrderGoodsFields = $conn->query("DESCRIBE {$config['prefix']}shop_order_goods");

if ($shopOrderGoodsFields) {
    while ($row = $shopOrderGoodsFields->fetch_assoc()) {
        if ($row["Field"] === 'supplier_id') {
            $hasSupplierId = true;
            break;
        }
    }
    $shopOrderGoodsFields->free();
}

if ($hasSupplierId) {
    echo "✓ {$config['prefix']}shop_order_goods表已存在supplier_id字段\n";
} else {
    echo "✗ {$config['prefix']}shop_order_goods表缺少supplier_id字段\n";
    echo "正在添加supplier_id字段...\n";
    
    // 添加supplier_id字段
    $addSql = "ALTER TABLE {$config['prefix']}shop_order_goods ADD COLUMN `supplier_id` INT(11) NOT NULL DEFAULT 0 COMMENT '供货商ID' AFTER `proid`";
    if ($conn->query($addSql) === TRUE) {
        echo "✓ 添加supplier_id字段成功\n";
    } else {
        echo "✗ 添加supplier_id字段失败: " . $conn->error . "\n";
    }
}

// 检查shop_order表是否有supplier_id字段
echo "\n=== 检查shop_order表supplier_id字段 ===\n";

$hasSupplierIdOrder = false;
$shopOrderFields = $conn->query("DESCRIBE {$config['prefix']}shop_order");

if ($shopOrderFields) {
    while ($row = $shopOrderFields->fetch_assoc()) {
        if ($row["Field"] === 'supplier_id') {
            $hasSupplierIdOrder = true;
            break;
        }
    }
    $shopOrderFields->free();
}

if ($hasSupplierIdOrder) {
    echo "✓ {$config['prefix']}shop_order表已存在supplier_id字段\n";
} else {
    echo "✗ {$config['prefix']}shop_order表缺少supplier_id字段\n";
    echo "正在添加supplier_id字段...\n";
    
    // 添加supplier_id字段
    $addSql = "ALTER TABLE {$config['prefix']}shop_order ADD COLUMN `supplier_id` INT(11) NOT NULL DEFAULT 0 COMMENT '供货商ID' AFTER `bid`";
    if ($conn->query($addSql) === TRUE) {
        echo "✓ 添加supplier_id字段成功\n";
    } else {
        echo "✗ 添加supplier_id字段失败: " . $conn->error . "\n";
    }
}

// 关闭数据库连接
$conn->close();

echo "\n=== 检查完成 ===\n";
