<?php
// 简单的数据库字段检查脚本
// 使用ThinkPHP框架的Db类

// 包含ThinkPHP框架的入口文件
define('APP_PATH', __DIR__ . '/app/');
require __DIR__ . '/thinkphp/start.php';

try {
    // 检查collage_product表字段
    echo "=== Checking collage_product table fields ===\n";
    $collage_product_fields = \think\Db::name('collage_product')->getFields();
    $required_product_fields = [
        'id', 'aid', 'bid', 'name', 'cid', 'supplier_id', 'pic', 'pics', 
        'sellpoint', 'teamnum', 'buymax', 'teamhour', 'leaderscore', 
        'feepercent', 'freighttype', 'freightdata', 'contact_require', 
        'freightcontent', 'promote_type', 'commissionset', 'commissiondata1', 
        'commissiondata2', 'commissiondata3', 'show_recommend', 
        'recommend_productids', 'givescore_time', 'collage_type', 'starttime', 
        'endtime', 'xn_view_num', 'xn_share_num', 'is_many_times', 'max_times', 
        'is_rzh', 'relation_type', 'house_status', 'group_status', 'group_ids', 
        'mendian_ids', 'moneypay', 'jieti_data', 'guigedata', 'sales', 'sort', 
        'status', 'createtime', 'stock', 'sell_price', 'market_price', 
        'cost_price', 'goods_sn', 'leader_price', 'leader_commission_rate', 
        'weight'
    ];
    
    foreach ($required_product_fields as $field) {
        if (isset($collage_product_fields[$field])) {
            echo "✓ $field exists ({$collage_product_fields[$field]['type']})\n";
        } else {
            echo "✗ $field is missing!\n";
        }
    }
    
    // 检查collage_guige表字段
    echo "\n=== Checking collage_guige table fields ===\n";
    $collage_guige_fields = \think\Db::name('collage_guige')->getFields();
    $required_guige_fields = [
        'id', 'aid', 'proid', 'name', 'ks', 'market_price', 'cost_price', 
        'sell_price', 'weight', 'stock', 'givescore', 'pic', 'leader_price', 
        'leader_commission_rate', 'goods_sn'
    ];
    
    foreach ($required_guige_fields as $field) {
        if (isset($collage_guige_fields[$field])) {
            echo "✓ $field exists ({$collage_guige_fields[$field]['type']})\n";
        } else {
            echo "✗ $field is missing!\n";
        }
    }
    
    // 检查collage_order表的supplier_id字段
    echo "\n=== Checking collage_order table fields ===\n";
    $collage_order_fields = \think\Db::name('collage_order')->getFields();
    if (isset($collage_order_fields['supplier_id'])) {
        echo "✓ supplier_id exists in collage_order table ({$collage_order_fields['supplier_id']['type']})\n";
    } else {
        echo "✗ supplier_id is missing in collage_order table!\n";
    }
    
    echo "\n=== Check completed ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
