<?php
// 应用入口文件
require __DIR__ . '/vendor/autoload.php';

// 使用thinkacadeb
use think\facade\Db;

// 执行数据库更新
// 添加字段
$fields = [
    ['name' => 'english_name', 'type' => 'varchar(255)', 'default' => "''", 'comment' => '英文名称', 'after' => 'name'],
    ['name' => 'supplier_code', 'type' => 'varchar(100)', 'default' => "''", 'comment' => '供货商ID', 'after' => 'supplier_number'],
    ['name' => 'production_date', 'type' => 'date', 'default' => 'NULL', 'comment' => '生产日期', 'after' => 'pic'],
    ['name' => 'warehouse_date', 'type' => 'date', 'default' => 'NULL', 'comment' => '入仓日期', 'after' => 'production_date'],
];

foreach ($fields as $field) {
    try {
        $sql = "ALTER TABLE `ddwx_shop_product` ADD COLUMN IF NOT EXISTS `{$field['name']}` {$field['type']} DEFAULT {$field['default']} COMMENT '{$field['comment']}' AFTER `{$field['after']}`";
        Db::execute($sql);
        echo "成功添加字段: {$field['name']}\n";
    } catch (Exception $e) {
        echo "添加字段 {$field['name']} 失败: {$e->getMessage()}\n";
    }
}

echo "数据库字段更新完成！\n";
