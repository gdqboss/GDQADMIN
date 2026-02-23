<?php
/**
 * 机器人保持在线 - 修复编码
 */
header('Content-Type: text/html; charset=utf-8');

$conn = @mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) exit;

mysqli_query($conn, "SET NAMES utf8mb4");

$botName = '江小鱼';
$botUsername = 'GDQFISH';
$time = time();

// 检查是否已在线
$result = mysqli_query($conn, "SELECT * FROM ddwx_commune_online WHERE username = '$botUsername'");
if (mysqli_num_rows($result) > 0) {
    // 更新在线状态
    mysqli_query($conn, "UPDATE ddwx_commune_online SET last_active = $time, nickname = '$botName' WHERE username = '$botUsername'");
} else {
    // 插入在线状态
    mysqli_query($conn, "INSERT INTO ddwx_commune_online (username, nickname, last_active) VALUES ('$botUsername', '$botName', $time)");
}

mysqli_close($conn);
