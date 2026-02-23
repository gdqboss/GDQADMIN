<?php
// 检查数据库连接和表结构
$config = [
    'hostname' => '127.0.0.1',
    'username' => 'ddshop',
    'password' => '6NpZeFbA2Kxc5MLz',
    'database' => 'ddshop',
    'prefix' => 'ddwx_',
    'port' => 3306
];

echo "正在连接数据库...\n";
$conn = new mysqli($config['hostname'], $config['username'], $config['password'], $config['database'], $config['port']);

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

echo "数据库连接成功！\n";

echo "\n检查barcode相关表...\n";
$tables = ['barcode_inventory', 'barcode_inventory_guige', 'barcode_inventory_check', 'barcode_inventory_check_detail', 'barcode_stock_log', 'barcode_dealer', 'barcode_store'];

foreach ($tables as $table) {
    $fullTable = $config['prefix'] . $table;
    $result = $conn->query("SHOW TABLES LIKE '$fullTable'");
    if ($result->num_rows > 0) {
        echo "✓ $fullTable 存在\n";
        // 显示表结构
        $describe = $conn->query("DESCRIBE $fullTable");
        echo "  表结构: ";
        $fields = [];
        while ($row = $describe->fetch_assoc()) {
            $fields[] = $row['Field'];
        }
        echo implode(', ', $fields) . "\n";
    } else {
        echo "✗ $fullTable 不存在\n";
    }
}

$conn->close();
echo "\n检查完成！\n";