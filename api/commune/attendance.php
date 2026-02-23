<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo '{"code":0,"msg":"db error"}'; exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;
$user_name = isset($_GET['user_name']) ? $_GET['user_name'] : '用户';

if ($action == 'check_in') {
    $location = isset($_GET['location']) ? $_GET['location'] : '';
    $remark = isset($_GET['remark']) ? $_GET['remark'] : '';
    $lat = isset($_GET['lat']) ? $_GET['lat'] : '';
    $lng = isset($_GET['lng']) ? $_GET['lng'] : '';
    $check_date = date('Y-m-d');
    $check_time = date('H:i:s');
    $now = time();
    
    // 如果有经纬度，尝试获取地址名称
    $address = $location;
    if ($lat && $lng) {
        $address = getAddressFromCoords($lat, $lng);
        if ($address) $location = $address;
    }
    
    $location_esc = mysqli_real_escape_string($conn, $location);
    $remark_esc = mysqli_real_escape_string($conn, $remark);
    $user_name_esc = mysqli_real_escape_string($conn, $user_name);
    $lat_esc = mysqli_real_escape_string($conn, $lat);
    $lng_esc = mysqli_real_escape_string($conn, $lng);
    
    mysqli_query($conn, "INSERT INTO ddwx_attendance (user_id, user_name, check_date, check_time, location, latitude, longitude, remark, create_time) VALUES ($user_id, '$user_name_esc', '$check_date', '$check_time', '$location_esc', '$lat_esc', '$lng_esc', '$remark_esc', $now)");
    
    echo '{"code":1,"msg":"签到成功","data":{"time":"' . $check_time . '","location":"' . $location . '"}}';
    exit;
}

if ($action == 'today_status') {
    $today = date('Y-m-d');
    $r = mysqli_query($conn, "SELECT * FROM ddwx_attendance WHERE user_id=$user_id AND check_date='$today' ORDER BY id DESC LIMIT 1");
    if ($row = mysqli_fetch_assoc($r)) {
        echo '{"code":1,"data":{"checked":true,"time":"' . $row['check_time'] . '","location":"' . $row['location'] . '"}}';
    } else {
        echo '{"code":1,"data":{"checked":false}}';
    }
    exit;
}

if ($action == 'attendance_list') {
    $r = mysqli_query($conn, "SELECT * FROM ddwx_attendance ORDER BY id DESC LIMIT 50");
    $list = array();
    while ($row = mysqli_fetch_assoc($r)) { $list[] = $row; }
    echo json_encode(array('code'=>1,'data'=>$list));
    exit;
}

if ($action == 'reverse_geocode') {
    $lat = isset($_GET['lat']) ? $_GET['lat'] : '';
    $lng = isset($_GET['lng']) ? $_GET['lng'] : '';
    
    if ($lat && $lng) {
        $address = getAddressFromCoords($lat, $lng);
        echo json_encode(array('code'=>1,'data'=>array('address'=>$address, 'lat'=>$lat, 'lng'=>$lng)));
    } else {
        echo json_encode(array('code'=>0,'msg'=>'no coords'));
    }
    exit;
}

if ($action == 'get_config') {
    $r = mysqli_query($conn, "SELECT * FROM ddwx_attendance_config");
    $config = array();
    while ($row = mysqli_fetch_assoc($r)) { $config[$row['config_key']] = $row['config_value']; }
    echo json_encode(array('code'=>1,'data'=>$config));
    exit;
}

if ($action == 'save_config') {
    $location = isset($_POST['location']) ? $_POST['location'] : (isset($_GET['location']) ? $_GET['location'] : '');
    $remark = isset($_POST['remark']) ? $_POST['remark'] : '';
    $now = time();
    $location_esc = mysqli_real_escape_string($conn, $location);
    $remark_esc = mysqli_real_escape_string($conn, $remark);
    mysqli_query($conn, "INSERT INTO ddwx_attendance_config VALUES (1, 'checkin_location', '$location_esc', $now) ON DUPLICATE KEY UPDATE config_value='$location_esc'");
    mysqli_query($conn, "INSERT INTO ddwx_attendance_config VALUES (2, 'checkin_remark', '$remark_esc', $now) ON DUPLICATE KEY UPDATE config_value='$remark_esc'");
    echo '{"code":1,"msg":"保存成功"}';
    exit;
}

echo '{"code":0,"msg":"unknown action"}';

// 逆地理编码函数 - 使用免费的Nominatim API
function getAddressFromCoords($lat, $lng) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&zoom=18&addressdetails=1";
    
    $context = stream_context_create(array(
        'http' => array(
            'timeout' => 5,
            'header' => "User-Agent: SeafoodApp/1.0\r\n"
        )
    ));
    
    $response = @file_get_contents($url, false, $context);
    if (!$response) return false;
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['address'])) return false;
    
    $addr = $data['address'];
    $parts = array();
    
    if (isset($addr['city'])) $parts[] = $addr['city'];
    elseif (isset($addr['town'])) $parts[] = $addr['town'];
    elseif (isset($addr['village'])) $parts[] = $addr['village'];
    
    if (isset($addr['suburb'])) $parts[] = $addr['suburb'];
    if (isset($addr['road'])) $parts[] = $addr['road'];
    
    if (empty($parts)) {
        if (isset($addr['county'])) $parts[] = $addr['county'];
    }
    
    return implode('', $parts);
}
