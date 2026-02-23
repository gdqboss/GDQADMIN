<?php
// 测试barcode_stock_log表中的数据

// 设置错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "开始测试barcode_stock_log表数据...\n";

// 数据库配置（从1Dataup.txt获取）
$db_config = [
    'hostname' => 'localhost',
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
    
    // 测试查询barcode_stock_log表中的数据
    $table_name = $db_config['prefix'] . 'barcode_stock_log';
    
    // 查询数据总数
    $sql = "SELECT COUNT(*) as total FROM {$table_name}";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "barcode_stock_log表中共有 {$row['total']} 条记录\n\n";
    }
    
    // 查询出库记录
    $sql = "SELECT COUNT(*) as out_total FROM {$table_name} WHERE type = 'out'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "其中出库记录共有 {$row['out_total']} 条\n\n";
    }
    
    // 查询最新的10条出库记录
    $sql = "SELECT id, ordernum, pro_name, gg_name, num, type, price, total_amount, uid, uname, createtime, remark FROM {$table_name} WHERE type = 'out' ORDER BY createtime DESC LIMIT 10";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        echo "最新的10条出库记录：\n";
        echo "ID | 订单号 | 商品名称 | 规格 | 数量 | 类型 | 单价 | 总金额 | UID | 用户名 | 创建时间 | 备注\n";
        echo "-|-|-|-|-|-|-|-|-|-|-|-\n";
        while ($row = mysqli_fetch_assoc($result)) {
            $createtime = date('Y-m-d H:i:s', $row['createtime']);
            echo "{$row['id']} | {$row['ordernum']} | {$row['pro_name']} | {$row['gg_name']} | {$row['num']} | {$row['type']} | {$row['price']} | {$row['total_amount']} | {$row['uid']} | {$row['uname']} | {$createtime} | {$row['remark']}\n";
        }
    } else {
        echo "查询出库记录失败：" . mysqli_error($conn) . "\n";
    }
    
    // 关闭数据库连接
    mysqli_close($conn);
    
    echo "\n测试完成！\n";
    
} catch (Exception $e) {
    echo "测试失败：" . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
