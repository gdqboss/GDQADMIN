<?php

// 数据库连接信息
$host = 'localhost';
$user = 'ddshop';
$password = '6NpZeFbA2Kxc5MLz';
$database = 'ddshop';

// 连接数据库
$conn = mysqli_connect($host, $user, $password, $database);
if (!$conn) {
    die("连接失败: " . mysqli_connect_error());
}
echo "成功连接到数据库\n";

// 获取两个表的结构信息
$productColumns = [];
$result = mysqli_query($conn, "SHOW COLUMNS FROM collage_product");
while ($row = mysqli_fetch_assoc($result)) {
    $productColumns[] = $row['Field'];
}

$guigeColumns = [];
$result = mysqli_query($conn, "SHOW COLUMNS FROM collage_guige");
while ($row = mysqli_fetch_assoc($result)) {
    $guigeColumns[] = $row['Field'];
}

// 找出两个表的相同字段
$commonColumns = array_intersect($productColumns, $guigeColumns);

// 排除一些不需要同步的字段
$excludeColumns = ['id', 'aid', 'createtime', 'updatetime', 'proid', 'sort', 'isshow'];
$syncColumns = array_diff($commonColumns, $excludeColumns);

// 输出要同步的字段
echo "要同步的字段：" . implode(', ', $syncColumns) . "\n";

// 1. 遍历collage_product表中的每个商品
$result = mysqli_query($conn, "SELECT id, " . implode(', ', $syncColumns) . " FROM collage_product");
while ($product = mysqli_fetch_assoc($result)) {
    $productId = $product['id'];
    echo "\n处理商品ID：$productId\n";
    
    // 2. 找到该商品对应的所有规格
    $guiges = [];
    $guigeResult = mysqli_query($conn, "SELECT id, " . implode(', ', $syncColumns) . " FROM collage_guige WHERE proid = $productId");
    while ($guige = mysqli_fetch_assoc($guigeResult)) {
        $guiges[] = $guige;
    }
    
    if (empty($guiges)) {
        echo "  该商品没有对应的规格\n";
        continue;
    }
    
    // 3. 同步商品表数据到规格表
    foreach ($guiges as $guige) {
        $guigeId = $guige['id'];
        $updateFields = [];
        $updateValues = [];
        
        foreach ($syncColumns as $column) {
            // 如果规格表中的值为空，而商品表中的值不为空，则同步
            if (empty($guige[$column]) && !empty($product[$column])) {
                $updateFields[] = "$column = '$product[$column]'";
            }
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE collage_guige SET " . implode(', ', $updateFields) . " WHERE id = $guigeId";
            if (mysqli_query($conn, $sql)) {
                echo "  更新规格ID $guigeId：" . implode(', ', $updateFields) . "\n";
            } else {
                echo "  更新规格ID $guigeId失败：" . mysqli_error($conn) . "\n";
            }
        }
    }
    
    // 4. 同步规格表数据到商品表（只取第一个规格的值）
    $firstGuige = $guiges[0];
    $updateFields = [];
    $updateValues = [];
    
    foreach ($syncColumns as $column) {
