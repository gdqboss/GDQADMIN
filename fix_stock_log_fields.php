<?php
// 修复barcode_stock_log表，添加缺失的字段

// 设置错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "开始修复barcode_stock_log表...\n";

// 数据库配置
$db_config = [
    'hostname' => '127.0.0.1',
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
    echo "数据库连接成功\n";
    
    // 设置字符集
    mysqli_set_charset($conn, "utf8mb4");
    
    // 表名
    $table_name = $db_config['prefix'] . 'barcode_stock_log';
    
    // 检查并添加ordernum字段
    echo "检查ordernum字段...\n";
    $result = mysqli_query($conn, "SHOW COLUMNS FROM {$table_name} LIKE 'ordernum'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE {$table_name} ADD COLUMN ordernum VARCHAR(30) NOT NULL DEFAULT '' COMMENT '订单号' AFTER id";
        if (mysqli_query($conn, $sql)) {
            echo "ordernum字段添加成功\n";
        } else {
            echo "添加ordernum字段失败: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ordernum字段已存在\n";
    }
    
    // 检查并添加outbound_type字段
    echo "检查outbound_type字段...\n";
    $result = mysqli_query($conn, "SHOW COLUMNS FROM {$table_name} LIKE 'outbound_type'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE {$table_name} ADD COLUMN outbound_type TINYINT(1) NOT NULL DEFAULT 0 COMMENT '出库类型' AFTER type";
        if (mysqli_query($conn, $sql)) {
            echo "outbound_type字段添加成功\n";
        } else {
            echo "添加outbound_type字段失败: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "outbound_type字段已存在\n";
    }
    
    // 检查并添加dealer_id字段
    echo "检查dealer_id字段...\n";
    $result = mysqli_query($conn, "SHOW COLUMNS FROM {$table_name} LIKE 'dealer_id'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE {$table_name} ADD COLUMN dealer_id INT(11) NOT NULL DEFAULT 0 COMMENT '经销商ID' AFTER outbound_type";
        if (mysqli_query($conn, $sql)) {
            echo "dealer_id字段添加成功\n";
        } else {
            echo "添加dealer_id字段失败: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "dealer_id字段已存在\n";
    }
    
    // 检查并添加total_amount字段
    echo "检查total_amount字段...\n";
    $result = mysqli_query($conn, "SHOW COLUMNS FROM {$table_name} LIKE 'total_amount'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE {$table_name} ADD COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '总金额' AFTER price";
        if (mysqli_query($conn, $sql)) {
            echo "total_amount字段添加成功\n";
        } else {
            echo "添加total_amount字段失败: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "total_amount字段已存在\n";
    }
    
    // 添加索引
    echo "添加索引...\n";
    
    // ordernum索引
    $sql = "CREATE INDEX idx_ordernum ON {$table_name} (ordernum)";
    if (mysqli_query($conn, $sql)) {
        echo "ordernum索引添加成功\n";
    } else {
        echo "添加ordernum索引失败: " . mysqli_error($conn) . "\n";
    }
    
    // createtime索引
    $sql = "CREATE INDEX idx_createtime ON {$table_name} (createtime)";
    if (mysqli_query($conn, $sql)) {
        echo "createtime索引添加成功\n";
    } else {
        echo "添加createtime索引失败: " . mysqli_error($conn) . "\n";
    }
    
    // proid_ggid索引
    $sql = "CREATE INDEX idx_proid_ggid ON {$table_name} (proid, ggid)";
    if (mysqli_query($conn, $sql)) {
        echo "proid_ggid索引添加成功\n";
    } else {
        echo "添加proid_ggid索引失败: " . mysqli_error($conn) . "\n";
    }
    
    echo "\nbarcode_stock_log表修复完成！\n";
    
    // 关闭数据库连接
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo "修复失败：" . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
