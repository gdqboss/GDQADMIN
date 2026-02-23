<?php
// 测试出库功能的脚本

// 设置错误报告
ini_set(display_errors, 1);
error_reporting(E_ALL);

// 引入ThinkPHP框架
require __DIR__ . /vendor/autoload.php;

// 创建应用实例
 = require __DIR__ . /thinkphp/base.php;

// 测试get_guige方法
 = app->http;

// 构造请求
 = hink\facade\Request::instance();
request->method(GET);
request->param([proid => 1]);

// 执行控制器方法
try {
    response = http->run(request->setPathinfo(BarcodeInventory/get_guige));
    data = json_decode(response->getContent(), true);
    echo get_guige方法执行结果：\n;
    print_r(data);
} catch (Exception e) {
    echo get_guige方法执行异常：\n;
    echo 异常信息： . e->getMessage() . \n;
    echo 异常追踪： . e->getTraceAsString() . \n;
}

// 测试outbound方法
request->method(POST);
request->param([
    proid => 1,
    specs => json_encode([[ggid => 1, num => 1]]),
    outbound_type => 2,
    dealer_id => 1
]);

echo \n\noutbound方法执行结果：\n;
try {
    response = http->run(request->setPathinfo(BarcodeInventory/outbound));
    data = json_decode(response->getContent(), true);
    print_r(data);
} catch (Exception e) {
    echo outbound方法执行异常：\n;
    echo 异常信息： . e->getMessage() . \n;
    echo 异常追踪： . e->getTraceAsString() . \n;
}
