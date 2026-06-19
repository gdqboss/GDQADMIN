@echo off
chcp 65001 >nul
title 彩美特微信小程序 - 一键安装

echo ========================================
echo   彩美特微信小程序 - 自动安装脚本
echo ========================================
echo.

where node >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未检测到 Node.js，请先安装 Node.js
    echo 下载地址: https://nodejs.org/
    echo.
    pause
    exit /b 1
)

echo [1/4] 检测 Node.js 版本:
node -v
echo.

echo [2/4] 安装依赖 (npm install)...
echo 这可能需要几分钟，请耐心等待...
call npm install
if %errorlevel% neq 0 (
    echo [错误] 依赖安装失败，请检查网络连接
    pause
    exit /b 1
)
echo 依赖安装完成！
echo.

echo [3/4] 生成 tabBar 图标...
call node generate-icons.js
if %errorlevel% neq 0 (
    echo [警告] 图标生成脚本执行异常，但不影响核心功能
)
echo 图标生成完成！
echo.

echo [4/4] 构建微信小程序...
call npm run build:mp-weixin
if %errorlevel% neq 0 (
    echo [错误] 构建失败，请检查上面的错误信息
    pause
    exit /b 1
)
echo 构建完成！
echo.

echo ========================================
echo   ✅ 全部完成！
echo ========================================
echo.
echo 👉 下一步操作：
echo.
echo 1. 打开「微信开发者工具」
echo 2. 点击「导入项目」
echo 3. 项目目录选择下面这个完整路径：
echo    %cd%\dist\build\mp-weixin
echo 4. AppID 填入你的小程序 AppID（或选"测试号"）
echo 5. 点击「导入」即可看到小程序运行
echo.
echo 构建产物目录：
echo %cd%\dist\build\mp-weixin
echo.
echo 提示: 以后修改代码后，再次运行本脚本即可重新构建
pause
