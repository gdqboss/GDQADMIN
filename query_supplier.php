<?php
// 数据库连接信息
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

// 查询拼团商品id=19的supplier_id
$sql = "SELECT id, supplier_id, title FROM {$table_prefix}collage_product WHERE id = 19";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // 输出数据
    while($row = $result->fetch_assoc()) {
        echo "拼团商品ID: " . $row["id"]. "\n";
        echo "商品标题: " . $row["title"]. "\n";
        echo "Supplier_ID: " . $row["supplier_id"]. "\n";
    }
} else {
    echo "未找到id=19的拼团商品";
}

$conn->close();
?>