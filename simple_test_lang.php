<?php
// 简单测试语言切换功能
require_once __DIR__ .  /app/common.php;

// 测试t()函数
echo 测试t()函数：\n;
echo 后台登录： . t(后台登录) . \n;
echo 登录： . t(登录) . \n;
echo 用户名/手机号： . t(用户名/手机号) . \n;
