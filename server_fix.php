<?php
// 服务器端修复脚本 - 添加缺少的字段到collage_product表

// 定义根目录
define('ROOT_PATH', __DIR__ . '/');

// 加载配置文件
$config = include(ROOT_PATH . 'config.php');

// 数据库连接参数
$host = $config['hostname'] ?? 'localhost';
$dbname = $config['database'] ?? 'ddshop';
$username = $config['username'] ?? 'ddshop';
$password = $config['password'] ?? '6NpZeFbA2Kxc5MLz';
$port = $config['hostport'] ?? 3306;
$prefix = $config['prefix'] ?? 'ddwx_';

echo "=== 服务器端数据库修复脚本 ===\n";
echo "连接到数据库: {$dbname}@{$host}\n";

// 创建数据库连接
$mysqli = new mysqli($host, $username, $password, $dbname, $port);

// 检查连接
if ($mysqli->connect_error) {
    die("连接失败: " . $mysqli->connect_error . "\n");
}

echo "✓ 数据库连接成功\n\n";

// 检查collage_product表是否存在
$tableName = $prefix . 'collage_product';
echo "检查表: {$tableName}\n";

$checkTableSql = "SHOW TABLES LIKE '{$tableName}'";
$tableResult = $mysqli->query($checkTableSql);

if ($tableResult->num_rows == 0) {
    die("✗ 表 {$tableName} 不存在\n");
}

echo "✓ 表 {$tableName} 存在\n\n";

// 检查表的当前字段
$describeSql = "DESCRIBE {$tableName}";
$describeResult = $mysqli->query($describeSql);

$currentFields = [];
while ($row = $describeResult->fetch_assoc()) {
    $currentFields[] = $row['Field'];
}

echo "当前表字段: " . implode(', ', $currentFields) . "\n\n";

// 需要添加的字段列表
$fieldsToAdd = [
    'goods_sn' => [
        'type' => 'VARCHAR(100)',
        'default' => "''",
        'comment' => '规格编号',
        'after' => 'name'
    ],
    'market_price' => [
        'type' => 'DECIMAL(10,2)',
        'default' => '0.00',
        'comment' => '单买价',
        'after' => 'goods_sn'
    ],
    'sell_price' => [
        'type' => 'DECIMAL(10,2)',
        'default' => '0.00',
        'comment' => '拼团价',
        'after' => 'market_price'
    ],
    'leader_price' => [
        'type' => 'DECIMAL(10,2)',
        'default' => '0.00',
        'comment' => '团长价',
        'after' => 'sell_price'
    ],
    'leader_commission_rate' => [
        'type' => 'DECIMAL(5,2)',
        'default' => '0.00',
        'comment' => '团长佣金比例',
        'after' => 'leader_price'
    ],
    'weight' => [
        'type' => 'DECIMAL(10,2)',
        'default' => '0.00',
        'comment' => '重量',
        'after' => 'leader_commission_rate'
    ],
    'stock' => [
        'type' => 'INT(11)',
        'default' => '0',
        'comment' => '库存',
        'after' => 'weight'
    ],
    'xn_view_num' => [
        'type' => 'INT(11)',
        'default' => '0',
        'comment' => '虚拟浏览数',
        'after' => 'endtime'
    ],
    'xn_share_num' => [
        'type' => 'INT(11)',
        'default' => '0',
        'comment' => '虚拟分享数',
        'after' => 'xn_view_num'
    ]
];

// 检查并添加缺少的字段
$addedFields = [];
$missingFields = [];

foreach ($fieldsToAdd as $fieldName => $fieldConfig) {
    if (!in_array($fieldName, $currentFields)) {
        $missingFields[] = $fieldName;
    } else {
        echo "✓ 字段 '{$fieldName}' 已存在\n";
    }
}

if (empty($missingFields)) {
    echo "\n✓ 所有必需字段已存在，无需添加\n";
} else {
    echo "\n缺少的字段: " . implode(', ', $missingFields) . "\n";
    echo "开始添加缺少的字段...\n\n";
    
    foreach ($missingFields as $fieldName) {
        $fieldConfig = $fieldsToAdd[$fieldName];
        
        // 构建ALTER TABLE语句
        $alterSql = "ALTER TABLE `{$tableName}` ";
        $alterSql .= "ADD COLUMN `{$fieldName}` {$fieldConfig['type']} NULL DEFAULT {$fieldConfig['default']} COMMENT '{$fieldConfig['comment']}'";
        
        if (isset($fieldConfig['after'])) {
            $alterSql .= " AFTER `{$fieldConfig['after']}`";
        }
        
        $alterSql .= ";";
        
        echo "执行SQL: {$alterSql}\n";
        
        if ($mysqli->query($alterSql)) {
            echo "✓ 成功添加字段 '{$fieldName}'\n\n";
            $addedFields[] = $fieldName;
        } else {
            echo "✗ 添加字段 '{$fieldName}' 失败: " . $mysqli->error . "\n\n";
        }
    }
    
    // 重新检查表结构
    echo "=== 更新后的表字段 ===\n";
    $describeResult = $mysqli->query($describeSql);
    $currentFields = [];
    while ($row = $describeResult->fetch_assoc()) {
        $currentFields[] = $row['Field'];
    }
    echo "当前表字段: " . implode(', ', $currentFields) . "\n\n";
}

// 检查goods_sn字段是否存在（最终验证）
if (in_array('goods_sn', $currentFields)) {
    echo "=== 修复结果 ===\n";
    echo "✓ ✓ ✓ 成功修复！字段 'goods_sn' 已存在于表 {$tableName} 中\n";
    echo "现在可以正常使用商品编辑功能了\n";
} else {
    echo "=== 修复结果 ===\n";
    echo "✗ ✗ ✗ 修复失败！字段 'goods_sn' 仍然不存在\n";
    echo "请检查错误信息并手动执行SQL脚本\n";
}

// 关闭数据库连接
$mysqli->close();

echo "\n=== 修复脚本执行完成 ===\n";
