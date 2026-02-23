<?php
// 测试连接远程数据库，获取表结构

// 设置错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "开始测试远程数据库连接...\n";

// 数据库配置（从1Dataup.txt获取）
$db_config = [
    'hostname' => 'localhost', // 远程服务器上的数据库，所以是localhost
    'username' => 'ddshop',
    'password' => '6NpZeFbA2Kxc5MLz',
    'database' => 'ddshop',
    'port' => 3306,
    'prefix' => 'ddwx_'
];

try {
    // 创建数据库连接
    $conn = mysqli_connect(
        $db_config['hostname'],
        $db_config['username'],
        $db_config['password'],
        $db_config['database'],
        $db_config['port']
    );
    
    if (!$conn) {
        die("数据库连接失败: " . mysqli_connect_error());
    }
    echo "数据库连接成功\n\n";
    
    // 设置字符集
    mysqli_set_charset($conn, "utf8mb4");
    
    // 测试查询表结构
    $tables = [
        'barcode_inventory',
        'barcode_inventory_guige',
        'barcode_stock_log',
        'barcode_dealer',
        'barcode_store'
    ];
    
    foreach ($tables as $table) {
        $full_table_name = $db_config['prefix'] . $table;
        echo "表 {$full_table_name} 的结构：\n";
        
        $sql = "DESCRIBE {$full_table_name}";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            echo "字段名 | 类型 | 空值 | 主键 | 默认值 | 额外信息\n";
            echo "-|-|-|-|-|-\n";
            while ($row = mysqli_fetch_assoc($result)) {
                echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
            }
        } else {
            echo "获取表结构失败：" . mysqli_error($conn) . "\n";
        }
        echo "\n";
    }
    
    // 测试查询数据
    echo "测试查询商品数据：\n";
    $sql = "SELECT id, name FROM {$db_config['prefix']}barcode_inventory LIMIT 5";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: {$row['id']}, 名称: {$row['name']}\n";
        }
    } else {
        echo "查询商品数据失败：" . mysqli_error($conn) . "\n";
    }
    
    echo "\n测试查询规格数据：\n";
    $sql = "SELECT id, proid, name, stock FROM {$db_config['prefix']}barcode_inventory_guige LIMIT 5";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "ID: {$row['id']}, 商品ID: {$row['proid']}, 规格: {$row['name']}, 库存: {$row['stock']}\n";
        }
    } else {
        echo "查询规格数据失败：" . mysqli_error($conn) . "\n";
    }
    
    // 关闭数据库连接
    mysqli_close($conn);
    
    echo "\n测试完成！\n";
    
} catch (Exception $e) {
    echo "测试失败：" . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
