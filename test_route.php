<?php
// 测试路由是否存在
require __DIR__ . '/vendor/autoload.php';

use think\App;
use think\facade\Route;

// 创建应用实例
$app = new App();

// 打印路由列表
echo "Testing BarcodeInventory routes...\n";
echo "\nRoute list:\n";
print_r(Route::getRules());

// 测试编辑路由
echo "\nTesting edit route...\n";
$url = url('BarcodeInventory/edit');
echo "Edit URL: " . $url . "\n";