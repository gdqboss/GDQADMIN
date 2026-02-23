<?php
// 测试语言切换功能
require_once __DIR__ .  /vendor/autoload.php;

use think\App;

// 初始化应用
 = new App();

// 设置语言为英文
echo 测试英文语言：\n;
->request->param([lang => en-us]);
->lang->detect();
echo 语言检测结果： . ->lang->getLangSet() . \n;
echo 后台登录： . t(后台登录) . \n;
echo 登录： . t(登录) . \n;
echo 用户名/手机号： . t(用户名/手机号) . \n;

// 设置语言为中文
echo \n测试中文语言：\n;
->request->param([lang => zh-cn]);
->lang->detect();
echo 语言检测结果： . ->lang->getLangSet() . \n;
echo 后台登录： . t(后台登录) . \n;
echo 登录： . t(登录) . \n;
echo 用户名/手机号： . t(用户名/手机号) . \n;
