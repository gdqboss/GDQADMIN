<?php
// 修复barcode_stock_log表，添加缺失的ordernum字段

// 设置错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "开始修复barcode_stock_log表...\n";

// 从config.php获取数据库配置
$config = require __DIR__ . '/config.php';

// 调整配置数组格式
$db_config = [
    'db' => [
        'hostname' => $config['hostname'],
        'hostport' => $config['hostport'],
        'database' => $config['database'],
        'username' => $config['username'],
        'password' => $config['password'],
        'charset' => 'utf8mb4',
        'prefix' => $config['prefix']
    ]
];
$config = $db_config;

// 构建数据库DSN
$dsn = "mysql:host={$config['db']['hostname']};port={$config['db']['hostport']};dbname={$config['db']['database']};charset={$config['db']['charset']}";

// 创建PDO连接
try {
    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "数据库连接成功\n";
} catch (PDOException $e) {
    echo "数据库连接失败：" . $e->getMessage() . "\n";
    exit(1);
}

// 数据库表前缀
$prefix = $config['db']['prefix'];
$tableName = "{$prefix}barcode_stock_log";

try {
    // 检查表是否存在
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    if (!$stmt->fetch()) {
        echo "表{$tableName}不存在，无法修复\n";
        exit;
    }
    
    // 检查ordernum字段是否存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
    $stmt->execute(['ordernum']);
    if (!$stmt->fetch()) {
        echo "添加ordernum字段...\n";
        $pdo->exec("ALTER TABLE {$tableName} ADD COLUMN ordernum VARCHAR(30) NOT NULL DEFAULT '' COMMENT '订单号' AFTER id");
        echo "ordernum字段添加成功\n";
    } else {
        echo "ordernum字段已存在，跳过\n";
    }
    
    // 检查outbound_type字段是否存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
    $stmt->execute(['outbound_type']);
    if (!$stmt->fetch()) {
        echo "添加outbound_type字段...\n";
        $pdo->exec("ALTER TABLE {$tableName} ADD COLUMN outbound_type TINYINT(1) NOT NULL DEFAULT 0 COMMENT '出库类型' AFTER type");
        echo "outbound_type字段添加成功\n";
    } else {
        echo "outbound_type字段已存在，跳过\n";
    }
    
    // 检查dealer_id字段是否存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
    $stmt->execute(['dealer_id']);
    if (!$stmt->fetch()) {
        echo "添加dealer_id字段...\n";
        $pdo->exec("ALTER TABLE {$tableName} ADD COLUMN dealer_id INT(11) NOT NULL DEFAULT 0 COMMENT '经销商ID' AFTER outbound_type");
        echo "dealer_id字段添加成功\n";
    } else {
        echo "dealer_id字段已存在，跳过\n";
    }
    
    // 添加索引
    echo "添加索引...\n";
    $pdo->exec("ALTER TABLE {$tableName} ADD INDEX idx_ordernum (ordernum)");
    $pdo->exec("ALTER TABLE {$tableName} ADD INDEX idx_createtime (createtime)");
    $pdo->exec("ALTER TABLE {$tableName} ADD INDEX idx_proid_ggid (proid, ggid)");
    echo "索引添加成功\n";
    
    echo "\nbarcode_stock_log表修复完成！\n";
    
} catch (PDOException $e) {
    echo "修复失败：" . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "修复失败：" . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
