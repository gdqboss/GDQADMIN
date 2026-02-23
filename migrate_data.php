<?php
// 数据迁移脚本：将相关表的数据复制到collage_product表

// 数据库连接配置
$config = [
    'hostname' => '127.0.0.1',
    'username' => 'ddshop',
    'password' => '6NpZeFbA2Kxc5MLz',
    'hostport' => '3306',
    'database' => 'ddshop',
    'prefix' => 'ddwx_'
];

try {
    // 创建PDO连接
    $dsn = "mysql:host={$config['hostname']};port={$config['hostport']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "开始数据迁移...\n";
    
    // 1. 迁移collage_guige表的数据到collage_product表
    echo "\n1. 迁移collage_guige表的数据到collage_product表...\n";
    $stmt = $pdo->query("SELECT DISTINCT proid FROM {$config['prefix']}collage_guige");
    $product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($product_ids as $proid) {
        try {
            // 获取该商品的所有规格
            $gg_stmt = $pdo->prepare("SELECT * FROM {$config['prefix']}collage_guige WHERE proid = ?");
            $gg_stmt->execute([$proid]);
            $gglist = $gg_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($gglist)) {
                // 构建规格数据结构
                $guigedata = [];
                $spec_values = [];
                
                // 提取规格名称和值
                foreach ($gglist as $gg) {
                    $spec_values[] = [
                        'title' => $gg['name'],
                        'value' => $gg['name']
                    ];
                }
                
                // 构建guigedata结构
                $guigedata[] = [
                    'title' => '规格',
                    'items' => $spec_values
                ];
                
                // 将规格数据转换为JSON格式
                $guigedata_json = json_encode($guigedata, JSON_UNESCAPED_UNICODE);
                
                // 更新collage_product表
                $update_stmt = $pdo->prepare("UPDATE {$config['prefix']}collage_product SET guigedata = ? WHERE id = ?");
                $update_stmt->execute([$guigedata_json, $proid]);
                
                echo "   ✅ 商品ID {$proid}：迁移了 " . count($gglist) . " 个规格\n";
            }
        } catch (PDOException $e) {
            echo "   ❌ 商品ID {$proid}：迁移失败 - " . $e->getMessage() . "\n";
        }
    }
    
    // 2. 检查collage_product表中是否有缺失的字段
    echo "\n2. 检查collage_product表中是否有缺失的字段...\n";
    $required_fields = [
        'contact_require',
        'freightcontent',
        'givescore_time',
        'is_many_times',
        'max_times',
        'collage_type',
        'endtime',
        'xn_view_num',
        'xn_share_num',
        'is_rzh',
        'relation_type',
        'house_status',
        'group_status',
        'group_ids',
        'mendian_ids',
        'moneypay',
        'jieti_data'
    ];
    
    $stmt = $pdo->query("DESCRIBE {$config['prefix']}collage_product");
    $existing_fields = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_fields[] = $row['Field'];
    }
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!in_array($field, $existing_fields)) {
            $missing_fields[] = $field;
        }
    }
    
    if (empty($missing_fields)) {
        echo "   ✅ collage_product表包含所有需要的字段\n";
    } else {
        echo "   ❌ collage_product表缺少以下字段：\n";
        foreach ($missing_fields as $field) {
            echo "   - {$field}\n";
        }
        
        // 自动添加缺失的字段
        echo "   ⚙️ 正在自动添加缺失的字段...\n";
        foreach ($missing_fields as $field) {
            try {
                switch ($field) {
                    case 'contact_require':
                    case 'givescore_time':
                    case 'is_many_times':
                    case 'collage_type':
                    case 'is_rzh':
                    case 'relation_type':
                    case 'house_status':
                    case 'group_status':
                    case 'moneypay':
                        $sql = "ALTER TABLE {$config['prefix']}collage_product ADD {$field} TINYINT(1) DEFAULT 0 COMMENT ''";
                        break;
                    case 'max_times':
                    case 'xn_view_num':
                    case 'xn_share_num':
                        $sql = "ALTER TABLE {$config['prefix']}collage_product ADD {$field} INT(11) DEFAULT 0 COMMENT ''";
                        break;
                    case 'endtime':
                        $sql = "ALTER TABLE {$config['prefix']}collage_product ADD {$field} INT(11) NULL DEFAULT NULL COMMENT ''";
                        break;
                    case 'freightcontent':
                    case 'jieti_data':
                        $sql = "ALTER TABLE {$config['prefix']}collage_product ADD {$field} TEXT NULL DEFAULT NULL COMMENT ''";
                        break;
                    case 'group_ids':
                    case 'mendian_ids':
                        $sql = "ALTER TABLE {$config['prefix']}collage_product ADD {$field} VARCHAR(255) NULL DEFAULT NULL COMMENT ''";
                        break;
                    default:
                        $sql = "ALTER TABLE {$config['prefix']}collage_product ADD {$field} VARCHAR(255) NULL DEFAULT NULL COMMENT ''";
                        break;
                }
                
                $pdo->exec($sql);
                echo "   ✅ 添加字段 {$field} 成功\n";
            } catch (PDOException $e) {
                echo "   ❌ 添加字段 {$field} 失败：" . $e->getMessage() . "\n";
            }
        }
    }
    
    // 3. 更新collage_product表的默认值
    echo "\n3. 更新collage_product表的默认值...\n";
    
    // 设置默认值
    $default_values = [
        'contact_require' => 0,
        'givescore_time' => 0,
        'is_many_times' => 0,
        'max_times' => 0,
        'collage_type' => 0,
        'xn_view_num' => 0,
        'xn_share_num' => 0,
        'is_rzh' => 0,
        'relation_type' => -1,
        'house_status' => 1,
        'group_status' => 0,
        'moneypay' => 1
    ];
    
    foreach ($default_values as $field => $value) {
        try {
            $sql = "UPDATE {$config['prefix']}collage_product SET {$field} = {$value} WHERE {$field} IS NULL";
            $pdo->exec($sql);
            echo "   ✅ 更新字段 {$field} 的默认值为 {$value} 成功\n";
        } catch (PDOException $e) {
            echo "   ❌ 更新字段 {$field} 的默认值失败：" . $e->getMessage() . "\n";
        }
    }
    
    echo "\n数据迁移完成！\n";
    
} catch (PDOException $e) {
    echo "数据库连接失败：" . $e->getMessage() . "\n";
    exit(1);
}
