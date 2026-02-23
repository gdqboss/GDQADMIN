<?php
// 获取数据库表结构的脚本
require_once __DIR__ . '/vendor/autoload.php';

use think\facade\Db;

// 定义要检查的表
$tables = [
    'shop_product',
    'shop_guige',
    'barcode_inventory',
    'barcode_inventory_guige'
];

echo "=== 表结构分析 ===\n\n";

// 遍历每个表，获取表结构
foreach ($tables as $table) {
    try {
        echo "表名: ddwx_{$table}\n";
        echo "-" . str_repeat("-", 60) . "\n";
        
        // 获取表结构
        $columns = Db::query("SHOW FULL COLUMNS FROM ddwx_{$table}");
        
        if (!empty($columns)) {
            echo str_pad("字段名", 20) . str_pad("类型", 30) . str_pad("注释", 50) . "\n";
            echo "=" . str_repeat("=", 100) . "\n";
            
            foreach ($columns as $column) {
                echo str_pad($column['Field'], 20) . 
                     str_pad($column['Type'], 30) . 
                     str_pad($column['Comment'], 50) . "\n";
            }
        } else {
            echo "表不存在或无字段\n";
        }
        
        echo "\n\n";
    } catch (Exception $e) {
        echo "获取表结构失败: " . $e->getMessage() . "\n\n";
    }
}

// 检查表之间的关联关系
echo "=== 表关联关系分析 ===\n\n";
try {
    // 检查shop_guige与shop_product的关联
    echo "ddwx_shop_guige关联字段: \n";
    $guigeColumns = Db::query("SHOW FULL COLUMNS FROM ddwx_shop_guige");
    foreach ($guigeColumns as $column) {
        if (in_array($column['Field'], ['proid', 'product_id'])) {
            echo "- {$column['Field']}: {$column['Comment']}\n";
        }
    }
    
    // 检查barcode_inventory_guige与barcode_inventory的关联
    echo "\nddwx_barcode_inventory_guige关联字段: \n";
    $barcodeGuigeColumns = Db::query("SHOW FULL COLUMNS FROM ddwx_barcode_inventory_guige");
    foreach ($barcodeGuigeColumns as $column) {
        if (in_array($column['Field'], ['proid', 'product_id', 'inventory_id'])) {
            echo "- {$column['Field']}: {$column['Comment']}\n";
        }
    }
    
    echo "\n\n";
} catch (Exception $e) {
    echo "获取关联关系失败: " . $e->getMessage() . "\n\n";
}

echo "=== 分析完成 ===\n";