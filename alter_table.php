<?php
// 数据库连接信息
$servername = "localhost";
$username = "ddshop";
$password = "6NpZeFbA2Kxc5MLz";
$dbname = "ddshop";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 执行ALTER TABLE语句
$sql = "ALTER TABLE ddwx_shop_guige ADD COLUMN code_type tinyint(1) NOT NULL DEFAULT 1 COMMENT '码类型：1=条码，2=二维码' AFTER barcode";

if ($conn->query($sql) === TRUE) {
    echo "表修改成功！\n";
} else {
    echo "表修改失败: " . $conn->error . "\n";
}

// 检查修改后的表结构
echo "\n修改后的表结构：\n";
$sql = "DESCRIBE ddwx_shop_guige";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo $row["Field"] . "\t" . $row["Type"] . "\t" . $row["Null"] . "\t" . $row["Key"] . "\t" . $row["Default"] . "\t" . $row["Extra"] . "\n";
    }
} else {
    echo "0 结果\n";
}

$conn->close();
?>