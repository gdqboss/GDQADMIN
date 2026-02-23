<?php
// 测试数据库连接的PHP脚本
$servername = "localhost";
$username = "ddshop";
$password = "6NpZeFbA2Kxc5MLz";
$dbname = "ddshop";
$table_prefix = "ddwx_";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 测试订单数据查询
echo "数据库连接成功！\n";

try {
    // 测试微信支付配置表
    $sql = "SELECT * FROM admin_setapp_wx WHERE aid = 1 LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        echo "微信支付配置存在！\n";
        $row = $result->fetch_assoc();
        echo "商户号: " . ($row['wxpay_mchid'] ?? '未设置') . "\n";
        echo "API密钥: " . ($row['wxpay_mchkey'] ? '已设置' : '未设置') . "\n";
        echo "API v3密钥: " . ($row['wxpay_mchkey_v3'] ? '已设置' : '未设置') . "\n";
        echo "证书路径: " . ($row['wxpay_apiclient_cert'] ?? '未设置') . "\n";
        echo "平台证书: " . ($row['wxpay_wechatpay_pem'] ?? '未设置') . "\n";
    } else {
        echo "微信支付配置不存在！\n";
    }
    
    // 测试订单数据
    $sql = "SELECT COUNT(*) AS total_orders FROM {$table_prefix}shop_order WHERE status IN (1, 2, 3) AND aid = 1";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "总订单数: " . $row['total_orders'] . "\n";
    } else {
        echo "订单查询失败: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "查询异常: " . $e->getMessage() . "\n";
}

$conn->close();
echo "测试完成！\n";
?>