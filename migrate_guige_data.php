<?php
// 数据迁移脚本：将collage_guige表的数据迁移到collage_product表

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
    
    echo "开始迁移collage_guige表数据到collage_product表...\n";
    
    // 1. 获取所有商品ID
    $stmt = $pdo->query("SELECT DISTINCT id FROM {$config['prefix']}collage_product");
    $product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($product_ids as $product_id) {
        try {
            echo "\n处理商品ID {$product_id}...\n";
            
            // 2. 获取该商品的所有规格数据
            $stmt = $pdo->prepare("SELECT * FROM {$config['prefix']}collage_guige WHERE proid = ?");
            $stmt->execute([$product_id]);
            $guige_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($guige_list)) {
                echo "   找到 " . count($guige_list) . " 个规格\n";
                
                // 3. 构建规格数据结构
                $guigedata = [];
                $specs = [];
                
                foreach ($guige_list as $guige) {
                    // 构建规格分组
                    $guigedata[] = [
                        'title' => $guige['name'],
                        'items' => [
                            ['title' => $guige['name'], 'value' => $guige['name']]
                        ]
                    ];
                    
                    // 构建specs数据
                    $specs[$guige['ks']] = [
                        'id' => $guige['id'],
                        'name' => $guige['name'],
                        'ks' => $guige['ks'],
                        'market_price' => $guige['market_price'],
                        'sell_price' => $guige['sell_price'],
                        'leader_price' => $guige['leader_price'],
                        'leader_commission_rate' => $guige['leader_commission_rate'],
                        'weight' => $guige['weight'],
                        'stock' => $guige['stock'],
                        'cost_price' => $guige['cost_price'],
                        'givescore' => $guige['givescore'],
                        'goods_sn' => $guige['goods_sn']
                    ];
                }
                
                // 4. 将规格数据保存到collage_product表
                $guigedata_json = json_encode($guigedata, JSON_UNESCAPED_UNICODE);
                $specs_json = json_encode($specs, JSON_UNESCAPED_UNICODE);
                
                $stmt = $pdo->prepare("UPDATE {$config['prefix']}collage_product SET guigedata = ? WHERE id = ?");
                $stmt->execute([$guigedata_json, $product_id]);
                
                echo "   ✅ 成功迁移规格数据到collage_product表\n";
                echo "   - guigedata: " . substr($guigedata_json, 0, 100) . "...\n";
            } else {
                echo "   没有找到规格数据\n";
            }
        } catch (PDOException $e) {
            echo "   ❌ 迁移失败：" . $e->getMessage() . "\n";
        }
    }
    
    echo "\n数据迁移完成！\n";
    
} catch (PDOException $e) {
    echo "数据库连接失败：" . $e->getMessage() . "\n";
    exit(1);
}
