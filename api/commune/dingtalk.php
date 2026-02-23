<?php
/**
 * 钉钉扫码登录 - 真实API版
 */

header('Content-Type: application/json;charset=utf-8');
error_reporting(0);

// 加载配置
$config = include __DIR__ . '/dingtalk_config.php';

$conn = @mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) {
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败']);
    exit;
}
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 获取钉钉 access_token
function getAccessToken($config) {
    $url = 'https://oapi.dingtalk.com/gettoken';
    $data = [
        'appkey' => $config['app_key'],
        'appsecret' => $config['app_secret']
    ];
    
    $ch = curl_init($url . '?appkey=' . $data['appkey'] . '&appsecret=' . $data['appsecret']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}

// 获取用户ID（扫码登录）
function getUserId($accessToken, $code) {
    $url = 'https://oapi.dingtalk.com/oauth/user/getuserinfo_bycode?access_token=' . $accessToken;
    $data = ['tmp_auth_code' => $code];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// 获取用户详情
function getUserDetail($accessToken, $userId) {
    $url = 'https://oapi.dingtalk.com/topapi/v2/user/get';
    $data = ['user_id' => $userId];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-acs-dingtalk-access-token: ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['result'] ?? null;
}

// 生成扫码登录二维码
if ($action === 'get_qr') {
    $appKey = $config['app_key'];
    $redirectUri = urlencode('http://bot.gdqshop.cn/api/commune/dingtalk.php?action=callback');
    
    // 钉钉扫码登录授权 URL
    $authUrl = 'https://login.dingtalk.com/oauth2/auth?response_type=code&client_id=' . $appKey . '&redirect_uri=' . $redirectUri . '&scope=openid';
    
    // 生成二维码
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($authUrl);
    
    $session_id = 'ding_' . time() . '_' . rand(1000, 9999);
    
    // 保存会话
    file_put_contents('/tmp/dingtalk_' . $session_id . '.json', json_encode([
        'created' => time(),
        'status' => 'waiting',
        'auth_url' => $authUrl
    ]));
    
    echo json_encode([
        'code' => 1,
        'data' => [
            'session_id' => $session_id,
            'qr_image' => $qrImage,
            'auth_url' => $authUrl,
            'check_url' => '/api/commune/dingtalk.php?action=check_status&session=' . $session_id
        ]
    ]);
    exit;
}

// 检查扫码状态
if ($action === 'check_status') {
    $session_id = $_GET['session'] ?? '';
    
    if (empty($session_id)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $file = '/tmp/dingtalk_' . $session_id . '.json';
    if (!file_exists($file)) {
        echo json_encode(['code' => 0, 'msg' => '会话已过期']);
        exit;
    }
    
    $data = json_decode(file_get_contents($file), true);
    
    if ($data['status'] === 'logged_in') {
        echo json_encode([
            'code' => 1,
            'data' => [
                'status' => 'logged_in',
                'user' => $data['user']
            ]
        ]);
    } else {
        echo json_encode([
            'code' => 1,
            'data' => [
                'status' => 'waiting'
            ]
        ]);
    }
    exit;
}

// 回调处理（用户扫码后会调用这个）
if ($action === 'callback') {
    $session_id = $_GET['session'] ?? '';
    $code = $_GET['authCode'] ?? '';  // 钉钉扫码返回的 code
    
    if (empty($session_id) || empty($code)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误', 'session' => $session_id, 'code' => $code]);
        exit;
    }
    
    // 获取 access_token
    $accessToken = getAccessToken($config);
    if (!$accessToken) {
        echo json_encode(['code' => 0, 'msg' => '获取access_token失败']);
        exit;
    }
    
    // 获取用户ID
    $userInfo = getUserId($accessToken, $code);
    if (empty($userInfo['user_id'])) {
        echo json_encode(['code' => 0, 'msg' => '获取用户信息失败', 'detail' => $userInfo]);
        exit;
    }
    
    $userId = $userInfo['user_id'];
    
    // 获取用户详情
    $userDetail = getUserDetail($accessToken, $userId);
    
    $username = 'DT_' . $userId;
    $nickname = $userDetail['name'] ?? '钉钉用户';
    $company = '钉钉企业';
    $department = implode(',', $userDetail['dept_id_list'] ?? []);
    $position = $userDetail['title'] ?? '';
    
    // 保存或更新用户
    $username_esc = mysqli_real_escape_string($conn, $username);
    $nickname_esc = mysqli_real_escape_string($conn, $nickname);
    $company_esc = mysqli_real_escape_string($conn, $company);
    $dept_esc = mysqli_real_escape_string($conn, $department);
    $pos_esc = mysqli_real_escape_string($conn, $position);
    
    $sql = "INSERT INTO ddwx_commune_user (username, nickname, company, department, position, dingtalk_id, login_type, create_time)
            VALUES ('$username_esc', '$nickname_esc', '$company_esc', '$dept_esc', '$pos_esc', '$userId', 'dingtalk', UNIX_TIMESTAMP())
            ON DUPLICATE KEY UPDATE nickname='$nickname_esc', company='$company_esc', department='$dept_esc', position='$pos_esc'";
    mysqli_query($conn, $sql);
    
    // 更新会话状态
    $file = '/tmp/dingtalk_' . $session_id . '.json';
    if (file_exists($file)) {
        $sessionData = json_decode(file_get_contents($file), true);
        $sessionData['status'] = 'logged_in';
        $sessionData['user'] = [
            'username' => $username,
            'nickname' => $nickname,
            'company' => $company,
            'department' => $department,
            'position' => $position
        ];
        file_put_contents($file, json_encode($sessionData));
    }
    
    echo json_encode([
        'code' => 1,
        'msg' => '登录成功',
        'data' => [
            'username' => $username,
            'nickname' => $nickname
        ]
    ]);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);
