<?php
/**
 * 文件上传API
 */
header('Content-Type: application/json');

$upload_dir = '/www/wwwroot/gdqshop.cn/ai-chat/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['code'=>0, 'msg'=>'上传失败']); exit;
}

$file = $_FILES['file'];
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '.' . $ext;
$target = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $target)) {
    $url = '/ai-chat/uploads/' . $filename;
    $type = $file['type'];
    
    echo json_encode([
        'code'=>1,
        'data'=>[
            'url'=>$url,
            'name'=>$file['name'],
            'size'=>$file['size'],
            'type'=>$type
        ]
    ]);
} else {
    echo json_encode(['code'=>0, 'msg'=>'保存失败']);
}
