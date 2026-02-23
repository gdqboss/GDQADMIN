<?php
header('Content-Type: application/json');
error_reporting(0);

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo '{"code":0,"msg":"db error"}'; exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 1);
$user_name = isset($_POST['user_name']) ? $_POST['user_name'] : (isset($_GET['user_name']) ? $_GET['user_name'] : '用户');

if ($action === 'check_in') {
    $location = isset($_POST['location']) ? $_POST['location'] : (isset($_GET['location']) ? $_GET['location'] : '');
    $remark = isset($_POST['remark']) ? $_POST['remark'] : '';
    $check_date = date('Y-m-d');
    $check_time = date('H:i:s');
    $now = time();
    
    $location_esc = mysqli_real_escape_string($conn, $location);
    $remark_esc = mysqli_real_escape_string($conn, $remark);
    $user_name_esc = mysqli_real_escape_string($conn, $user_name);
    
    $sql = "INSERT INTO ddwx_attendance (user_id, user_name, check_date, check_time, location, remark, create_time) VALUES ($user_id, '$user_name_esc', '$check_date', '$check_time', '$location_esc', '$remark_esc', $now)";
    mysqli_query($conn, $sql);
    
    echo '{"code":1,"msg":"签到成功","data":{"time":"' . $check_time . '"}}';
    exit;
}

if ($action === 'today_status') {
    $today = date('Y-m-d');
    $r = mysqli_query($conn, "SELECT * FROM ddwx_attendance WHERE user_id=$user_id AND check_date='$today' ORDER BY id DESC LIMIT 1");
    if ($row = mysqli_fetch_assoc($r)) {
        echo '{"code":1,"data":{"checked":true,"time":"' . $row['check_time'] . '","location":"' . $row['location'] . '"}}';
    } else {
        echo '{"code":1,"data":{"checked":false}}';
    }
    exit;
}

if ($action === 'attendance_list') {
    $r = mysqli_query($conn, "SELECT * FROM ddwx_attendance ORDER BY id DESC LIMIT 50");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) { $list[] = $row; }
    echo json_encode(['code'=>1,'data'=>$list]);
    exit;
}

if ($action === 'get_config') {
    $r = mysqli_query($conn, "SELECT * FROM ddwx_attendance_config");
    $config = [];
    while ($row = mysqli_fetch_assoc($r)) { $config[$row['config_key']] = $row['config_value']; }
    echo json_encode(['code'=>1,'data'=>$config]);
    exit;
}

if ($action === 'save_config') {
    $location = isset($_POST['location']) ? $_POST['location'] : '';
    $remark = isset($_POST['remark']) ? $_POST['remark'] : '';
    $now = time();
    $location_esc = mysqli_real_escape_string($conn, $location);
    $remark_esc = mysqli_real_escape_string($conn, $remark);
    mysqli_query($conn, "INSERT INTO ddwx_attendance_config VALUES (1, 'checkin_location', '$location_esc', $now) ON DUPLICATE KEY UPDATE config_value='$location_esc'");
    mysqli_query($conn, "INSERT INTO ddwx_attendance_config VALUES (2, 'checkin_remark', '$remark_esc', $now) ON DUPLICATE KEY UPDATE config_value='$remark_esc'");
    echo '{"code":1,"msg":"保存成功"}';
    exit;
}

echo '{"code":0,"msg":"unknown"}';
