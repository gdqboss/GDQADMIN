<?php
/**
 * 文件中转站 API
 */

header('Content-Type: application/json;charset=utf-8');
error_reporting(0);

// 数据库配置
$db = [
    'host' => '127.0.0.1',
    'user' => 'root',
    'pass' => '',
    'name' => 'ddshop'
];

$conn = @mysqli_connect($db['host'], $db['user'], $db['pass'], $db['name']);
if (!$conn) {
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败']);
    exit;
}

mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 获取文件列表
if ($action === 'list') {
    $sql = "SELECT * FROM ddwx_commune_file WHERE status = 1 ORDER BY id DESC LIMIT 100";
    $result = mysqli_query($conn, $sql);
    
    $files = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $files[] = $row;
    }
    
    echo json_encode(['code' => 1, 'data' => $files]);
    exit;
}

// 上传文件
if ($action === 'upload') {
    // 调试信息
    $debug = ['files' => $_FILES, 'post' => $_POST];
    file_put_contents('/tmp/upload_debug.log', print_r($debug, true));
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['code' => 0, 'msg' => '没有收到文件']);
        exit;
    }
    
    $file = $_FILES['file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['code' => 0, 'msg' => '上传错误代码: ' . $file['error']]);
        exit;
    }
    
    // 创建上传目录
    $upload_dir = '/www/wwwroot/gdqshop.cn/public/uploads/files/' . date('Ymd');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    // 允许的图片类型
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($ext), $allowed_exts)) {
        $ext = 'jpg';
    }
    
    $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
    $path = $upload_dir . '/' . $new_name;
    
    // 先尝试 copy (兼容)
    $success = copy($file['tmp_name'], $path);
    if (!$success) {
        // 再尝试 move_uploaded_file
        $success = move_uploaded_file($file['tmp_name'], $path);
    }
    
    if ($success) {
        $file_path = '/public/uploads/files/' . date('Ymd') . '/' . $new_name;
        echo json_encode(['code' => 1, 'msg' => '上传成功', 'url' => $file_path]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '保存失败, tmp: ' . $file['tmp_name'] . ', dest: ' . $path]);
    }
    exit;
}

// Base64图片上传
if ($action === 'upload_base64') {
    $data = $_POST['data'] ?? '';
    $name = $_POST['name'] ?? 'image.jpg';
    
    if (empty($data)) {
        echo json_encode(['code' => 0, 'msg' => '没有数据']);
        exit;
    }
    
    // 解析base64
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
        $ext = $matches[1];
        $base64_data = preg_replace('/^data:image\/\w+;base64,/', '', $data);
    } else if (preg_match('/^data:(\w+)\/(\w+);base64,/', $data, $matches)) {
        $ext = $matches[2];
        $base64_data = preg_replace('/^data:\w+\/\w+;base64,/', '', $data);
    } else {
        $ext = 'jpg';
        $base64_data = $data;
    }
    
    // 创建上传目录
    $upload_dir = '/www/wwwroot/gdqshop.cn/public/uploads/files/' . date('Ymd');
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            echo json_encode(['code' => 0, 'msg' => '创建目录失败']);
            exit;
        }
    }
    
    $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
    $path = $upload_dir . '/' . $new_name;
    
    // 解码并保存
    $image_data = base64_decode($base64_data, true);
    if ($image_data === false) {
        echo json_encode(['code' => 0, 'msg' => 'Base64解码失败']);
        exit;
    }
    
    if (file_put_contents($path, $image_data)) {
        $file_path = '/public/uploads/files/' . date('Ymd') . '/' . $new_name;
        echo json_encode(['code' => 1, 'msg' => '上传成功', 'url' => $file_path]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '保存失败, path: ' . $path]);
    }
    exit;
}

// 下载文件
if ($action === 'download') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($conn, "UPDATE ddwx_commune_file SET download_count = download_count + 1 WHERE id = $id");
        echo json_encode(['code' => 1, 'msg' => '计数成功']);
    } else {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
    }
    exit;
}

// 管理员：删除文件
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($conn, "UPDATE ddwx_commune_file SET status = 0 WHERE id = $id");
        echo json_encode(['code' => 1, 'msg' => '删除成功']);
    } else {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
    }
    exit;
}

// 管理员：清理旧文件
if ($action === 'clear') {
    $before_time = time() - 30 * 86400; // 30天前
    mysqli_query($conn, "UPDATE ddwx_commune_file SET status = 0 WHERE create_time < $before_time");
    echo json_encode(['code' => 1, 'msg' => '清理完成']);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);
